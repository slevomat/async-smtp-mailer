<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncConnectionWriter;
use AsyncConnection\AsyncMessage;
use AsyncConnection\AsyncMessageSender;
use InvalidArgumentException;
use Nette\Mail\Message;
use React\Promise\PromiseInterface;
use function array_keys;
use function array_merge;
use function key;
use function preg_replace;
use function React\Promise\resolve;
use function sprintf;

class AsyncSmtpMessageSender implements AsyncMessageSender
{

	public function sendMessage(AsyncConnectionWriter $writer, AsyncMessage $message): PromiseInterface
	{
		if (!$message instanceof Message) {
			throw new InvalidArgumentException('Only \Nette\Mail\Message is accepted');
		}

		$from = $message->getHeader('Return-Path') ?? key($message->getHeader('From'));

		$mailFromMessage = new AsyncSingleResponseMessage(sprintf('MAIL FROM:<%s>', $from), [SmtpCode::OK]);

		return $writer->write($mailFromMessage)
			->then(static function () use ($message, $writer) {
				$recipients = array_merge(
					(array) $message->getHeader('To'),
					(array) $message->getHeader('Cc'),
					(array) $message->getHeader('Bcc'),
				);

				$previousPromise = resolve(null);
				foreach (array_keys($recipients, null, true) as $email) {
					$previousPromise = $previousPromise->then(static function () use ($email, $writer) {
						$message = sprintf('RCPT TO:<%s>', $email);
						$recipientMessage = new AsyncSingleResponseMessage($message, [SmtpCode::OK, SmtpCode::FORWARD]);

						return $writer->write($recipientMessage);
					});
				}

				return $previousPromise;
			})
			->then(static fn () => $writer->write(new AsyncSingleResponseMessage('DATA', [SmtpCode::START_MAIL])))
			->then(static function () use ($message, $writer) {
				$data = $message->generateMessage();
				$data = preg_replace('#^\.#m', '..', $data);

				return $writer->write(new AsyncZeroResponseMessage($data));
			})
			->then(static fn () => $writer->write(new AsyncSingleResponseMessage('.', [SmtpCode::OK])));
	}

}
