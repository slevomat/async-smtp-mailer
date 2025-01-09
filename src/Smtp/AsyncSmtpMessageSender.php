<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncConnectionWriter;
use AsyncConnection\AsyncMessage;
use AsyncConnection\AsyncMessageSender;
use InvalidArgumentException;
use Nette\Mail\Message;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;
use function array_filter;
use function array_keys;
use function array_merge;
use function array_unique;
use function count;
use function key;
use function preg_replace;
use function React\Promise\resolve;
use function sprintf;

class AsyncSmtpMessageSender implements AsyncMessageSender
{

	private LoggerInterface $logger;

	public function __construct(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}

	public function sendMessage(AsyncConnectionWriter $writer, AsyncMessage $message): PromiseInterface
	{
		if (!$message instanceof Message) {
			throw new InvalidArgumentException('Only \Nette\Mail\Message is accepted');
		}

		$from = $message->getHeader('Return-Path') ?? key($message->getHeader('From'));

		$mailFromMessage = new AsyncSingleResponseMessage(sprintf('MAIL FROM:<%s>', $from), [SmtpCode::OK]);

		return $writer->write($mailFromMessage)
			->then(function () use ($message, $writer) {
				$recipients = array_filter(array_unique(array_keys(array_merge(
					(array) $message->getHeader('To'),
					(array) $message->getHeader('Cc'),
					(array) $message->getHeader('Bcc'),
				))));
				$hasMultipleToRecipients = count($recipients) > 1;
				if ($recipients === []) {
					throw new MissingRecipientsException();
				}

				$previousPromise = resolve(null);

				foreach ($recipients as $email) {
					if ($email === '') {
						continue;
					}

					$text = sprintf('RCPT TO:<%s>', $email);
					$recipientMessage = new AsyncSingleResponseMessage($text, [SmtpCode::OK, SmtpCode::FORWARD]);

					$previousPromise = $previousPromise->then(fn () => $writer->write($recipientMessage)->then(function () use ($hasMultipleToRecipients, $email, $message) {
						if ($hasMultipleToRecipients) {
							$notificationId = $message->getHeader('X-ForeignID');
							if ($notificationId !== null) {
								$logText = sprintf('ID %d, RCPT TO: %s', $notificationId, $email);
								$this->logger->info($logText, [
									'type' => 'notificationTo',
									'doNotIncludeSource' => true,
								]);
							}
						}

						return resolve(null);
					}));
				}

				return $previousPromise
					->then(static fn () => $writer->write(new AsyncSingleResponseMessage('DATA', [SmtpCode::START_MAIL])))
					->then(static function () use ($message, $writer) {
						$data = $message->generateMessage();
						$data = preg_replace('#^\.#m', '..', $data);

						return $writer->write(new AsyncZeroResponseMessage($data));
					})
					->then(static fn () => $writer->write(new AsyncSingleResponseMessage('.', [SmtpCode::OK])));
			});
	}

}
