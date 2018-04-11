<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\Log\Logger;

class AsyncSmtpConnectionWriterFactory extends \Consistence\ObjectPrototype
{

	/** @var \AsyncConnection\Log\Logger */
	private $logger;

	public function __construct(Logger $logger)
	{
		$this->logger = $logger;
	}

	public function create(
		\React\Socket\ConnectionInterface $connection
	): AsyncSmtpConnectionWriter
	{
		return new AsyncSmtpConnectionWriter($connection, $this->logger);
	}

}
