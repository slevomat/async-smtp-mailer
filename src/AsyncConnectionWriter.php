<?php declare(strict_types = 1);

namespace AsyncConnection;

interface AsyncConnectionWriter
{

	public function isValid(): bool;// checks whether server has not closed connection meanwhile

	public function write(AsyncMessage $message): \React\Promise\ExtendedPromiseInterface;

}
