<?php declare(strict_types = 1);

namespace AsyncConnection;

interface AsyncConnector
{

	public function connect(): \React\Promise\PromiseInterface;

	public function disconnect(AsyncConnectionWriter $writer): \React\Promise\PromiseInterface;

}
