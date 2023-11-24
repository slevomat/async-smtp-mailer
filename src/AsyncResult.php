<?php declare(strict_types = 1);

namespace AsyncConnection;

use Throwable;

class AsyncResult
{

	private function __construct(
		private bool $isSuccess,
		private ?Throwable $error = null
	)
	{
	}

	public static function success(): self
	{
		return new self(true);
	}

	public static function failure(Throwable $error): self
	{
		return new self(false, $error);
	}

	public function getError(): ?Throwable
	{
		return $this->error;
	}

	public function isSuccess(): bool
	{
		return $this->isSuccess;
	}

	public function isFailure(): bool
	{
		return !$this->isSuccess();
	}

}
