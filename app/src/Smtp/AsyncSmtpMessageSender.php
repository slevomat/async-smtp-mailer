<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncConnectionWriter;

class AsyncSmtpMessageSender extends \Consistence\ObjectPrototype implements \AsyncConnection\AsyncMessageSender
{

	/**
	 * @param \AsyncConnection\AsyncConnectionWriter $writer
	 * @param mixed $message
	 * @return \React\Promise\ExtendedPromiseInterface
	 */
	public function sendMessage(AsyncConnectionWriter $writer, $message): \React\Promise\ExtendedPromiseInterface
	{
		\Consistence\Type\Type::checkType($message, \Nette\Mail\Message::class);

		$from = $message->getHeader('Return-Path') ?? key($message->getHeader('From'));

		$mailFromMessage = new AsyncSingleResponseMessage(sprintf('MAIL FROM:<%s>', $from), [SmtpCode::OK]);

		return $writer->write($mailFromMessage)
			->then(function () use ($message, $writer) {
				$recipients = array_merge(
					(array) $message->getHeader('To'),
					(array) $message->getHeader('Cc'),
					(array) $message->getHeader('Bcc')
				);

				$previousPromise = \React\Promise\resolve();
				foreach ($recipients as $email => $name) {
					$previousPromise = $previousPromise->then(function () use ($email, $writer) {
						$message = sprintf('RCPT TO:<%s>', $email);
						$recipientMessage = new AsyncSingleResponseMessage($message, [SmtpCode::OK, SmtpCode::FORWARD]);
						return $writer->write($recipientMessage);
					});
				}

				return $previousPromise;
			})
			->then(function () use ($writer) {
				return $writer->write(new AsyncSingleResponseMessage('DATA', [SmtpCode::START_MAIL]));
			})
			->then(function () use ($message, $writer) {
				$data = $message->generateMessage();
				$data = preg_replace('#^\.#m', '..', $data);

				return $writer->write(new AsyncZeroResponseMessage($data));
			})
			->then(function () use ($writer) {
				return $writer->write(new AsyncSingleResponseMessage('.', [SmtpCode::OK]));
			});
	}

}
