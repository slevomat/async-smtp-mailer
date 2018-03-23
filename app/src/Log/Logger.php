<?php declare(strict_types = 1);

namespace AsyncConnection\Log;

interface Logger
{

	public function log(string $message): void;

}
