<?php declare(strict_types = 1);

namespace AsyncConnection;

use React\Promise\PromiseInterface;

interface AsyncConnector
{

	public function connect(): PromiseInterface;

	public function disconnect(AsyncConnectionWriter $writer): PromiseInterface;

}
