<?php declare(strict_types = 1);

namespace AsyncConnection;

use React\Promise\ExtendedPromiseInterface;

interface AsyncMessageSender
{

	public function sendMessage(
		AsyncConnectionWriter $writer,
		AsyncMessage $message
	): ExtendedPromiseInterface;

}
