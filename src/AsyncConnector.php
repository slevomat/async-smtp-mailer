<?php declare(strict_types = 1);

namespace AsyncConnection;

use React\Promise\PromiseInterface;

interface AsyncConnector
{

	/**
	 * @return PromiseInterface<AsyncConnectionWriter>
	 */
	public function connect(): PromiseInterface;

	/**
	 * @return PromiseInterface<int|null>
	 */
	public function disconnect(AsyncConnectionWriter $writer): PromiseInterface;

}
