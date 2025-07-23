<?php declare(strict_types = 1);

namespace AsyncConnection;

use React\Promise\PromiseInterface;

interface AsyncMessageSender
{

	/**
	 * @return PromiseInterface<int|null>
	 */
	public function sendMessage(
		AsyncConnectionWriter $writer,
		AsyncMessage $message
	): PromiseInterface;

}
