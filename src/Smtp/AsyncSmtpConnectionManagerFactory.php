<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncConnectionManager;
use AsyncConnection\Connector\ConnectorFactory;
use Psr\Log\LoggerInterface;

class AsyncSmtpConnectionManagerFactory
{

	private AsyncSmtpConnectionWriterFactory $writerFactory;

	private ConnectorFactory $connectorFactory;

	private LoggerInterface $logger;

	private SmtpSettings $smtpSettings;

	public function __construct(
		AsyncSmtpConnectionWriterFactory $writerFactory,
		ConnectorFactory $connectorFactory,
		LoggerInterface $logger,
		SmtpSettings $smtpSettings
	)
	{
		$this->writerFactory = $writerFactory;
		$this->connectorFactory = $connectorFactory;
		$this->logger = $logger;
		$this->smtpSettings = $smtpSettings;
	}

	public function create(): AsyncConnectionManager
	{
		$connection = new AsyncSmtpConnector(
			$this->writerFactory,
			$this->connectorFactory->create(),
			$this->smtpSettings,
		);

		return new AsyncConnectionManager($connection, $this->logger);
	}

}
