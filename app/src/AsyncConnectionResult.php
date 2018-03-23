<?php declare(strict_types = 1);

namespace AsyncConnection;

class AsyncConnectionResult extends \Consistence\ObjectPrototype
{

	/** @var \AsyncConnection\AsyncConnectionWriter */
	private $asyncConnectionWriter;

	/** @var bool */
	private $connectionRequest; // false = already existing connection returned

	public function __construct(AsyncConnectionWriter $asyncConnectionWriter, bool $connectionRequest)
	{
		$this->asyncConnectionWriter = $asyncConnectionWriter;
		$this->connectionRequest = $connectionRequest;
	}

	public function getWriter(): AsyncConnectionWriter
	{
		return $this->asyncConnectionWriter;
	}

	public function hasConnectedToServer(): bool
	{
		return $this->connectionRequest;
	}

}
