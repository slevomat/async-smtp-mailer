<?php declare(strict_types = 1);

namespace AsyncConnection;

use Throwable;

class AsyncConnectionResult
{

	private function __construct(
		private bool $isSuccess,
		private ?AsyncConnectionWriter $asyncConnectionWriter = null,
		private ?bool $connectionRequest = null, // false = already existing connection returned
		private ?Throwable $error = null
	)
	{
	}

	public static function success(AsyncConnectionWriter $writer, ?bool $connectionRequest = null): self
	{
		return new self(true, $writer, $connectionRequest);
	}

	public static function failure(Throwable $error): self
	{
		return new self(false, null, null, $error);
	}

	public function isConnected(): bool
	{
		return $this->isSuccess;
	}

	public function getWriter(): ?AsyncConnectionWriter
	{
		return $this->asyncConnectionWriter;
	}

	public function newServerRequestWasSent(): ?bool
	{
		return $this->connectionRequest === true;
	}

	public function getError(): ?Throwable
	{
		return $this->error;
	}

}
