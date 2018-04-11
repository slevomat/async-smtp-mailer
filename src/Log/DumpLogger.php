<?php declare(strict_types = 1);

namespace AsyncConnection\Log;

class DumpLogger extends \Consistence\ObjectPrototype implements \AsyncConnection\Log\Logger
{

	/** @var bool */
	private $enableLogging;

	public function __construct(bool $enableLogging)
	{
		$this->enableLogging = $enableLogging;
	}

	public function log(string $message): void
	{
		if (!$this->enableLogging) {
			return;
		}

		var_dump($message);
	}

}
