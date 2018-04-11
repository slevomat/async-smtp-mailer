<?php declare(strict_types = 1);

namespace AsyncConnection;

interface AsyncMessageSender
{

	/**
	 * @param \AsyncConnection\AsyncConnectionWriter $writer
	 * @param mixed $message
	 * @return \React\Promise\ExtendedPromiseInterface
	 */
	public function sendMessage(
		AsyncConnectionWriter $writer,
		$message
	): \React\Promise\ExtendedPromiseInterface;

}
