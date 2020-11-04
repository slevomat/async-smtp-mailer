<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use Consistence\ObjectPrototype;
use Psr\Log\LoggerInterface;
use React\Socket\ConnectionInterface;

class AsyncSmtpConnectionWriterFactory extends ObjectPrototype
{

	private LoggerInterface $logger;

	public function __construct(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}

	public function create(
		ConnectionInterface $connection
	): AsyncSmtpConnectionWriter
	{
		return new AsyncSmtpConnectionWriter($connection, $this->logger);
	}

}
