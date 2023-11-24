<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncConnectionWriter;
use AsyncConnection\AsyncMessage;
use AsyncConnection\AsyncMessageSender;
use AsyncConnection\AsyncResult;
use InvalidArgumentException;
use Nette\Mail\Message;
use React\Promise\PromiseInterface;
use function array_keys;
use function array_merge;
use function key;
use function preg_replace;
use function React\Promise\reject;
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

		return $this->write($writer, $mailFromMessage)
			->then(function ($writer) use ($message) {
				$recipients = array_merge(
					(array) $message->getHeader('To'),
					(array) $message->getHeader('Cc'),
					(array) $message->getHeader('Bcc'),
				);

				$previousPromise = resolve(null);
				foreach (array_keys($recipients, null, true) as $email) {
					$previousPromise = $previousPromise->then(function () use ($email, $writer) {
						$message = sprintf('RCPT TO:<%s>', $email);
						$recipientMessage = new AsyncSingleResponseMessage($message, [SmtpCode::OK, SmtpCode::FORWARD]);

						return $this->write($writer, $recipientMessage);
					});
				}

				return $previousPromise;
			})
			->then(fn ($writer) => $this->write($writer, new AsyncSingleResponseMessage('DATA', [SmtpCode::START_MAIL])))
			->then(function ($writer) use ($message) {
				$data = $message->generateMessage();
				$data = preg_replace('#^\.#m', '..', $data);

				return $this->write($writer, new AsyncZeroResponseMessage($data));
			})
			->then(fn ($writer) => $this->write($writer, new AsyncSingleResponseMessage('.', [SmtpCode::OK])));
	}

	private function write(AsyncConnectionWriter $writer, AsyncMessage $message): PromiseInterface
	{
		return $writer->write($message)->then(static fn (AsyncResult $result) => $result->isSuccess() ? resolve($writer) : reject($result->getError()));
	}

}
