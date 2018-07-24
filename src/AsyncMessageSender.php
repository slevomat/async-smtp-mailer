<?php declare(strict_types = 1);

namespace AsyncConnection;

interface AsyncMessageSender
{

	public function sendMessage(
		AsyncConnectionWriter $writer,
		AsyncMessage $message
	): \React\Promise\ExtendedPromiseInterface;

}
