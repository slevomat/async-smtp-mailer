<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

class AsyncSmtpConnectionWriterFactory extends \Consistence\ObjectPrototype
{

	/** @var \Psr\Log\LoggerInterface */
	private $logger;

	public function __construct(\Psr\Log\LoggerInterface $logger)
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
