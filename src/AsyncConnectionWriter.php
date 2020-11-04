<?php declare(strict_types = 1);

namespace AsyncConnection;

use React\Promise\ExtendedPromiseInterface;

interface AsyncConnectionWriter
{

	public function isValid(): bool;// checks whether server has not closed connection meanwhile

	public function write(AsyncMessage $message): ExtendedPromiseInterface;

}
