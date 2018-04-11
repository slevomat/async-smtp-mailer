<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncConnectionManager;
use AsyncConnection\Connector\ConnectorFactory;
use AsyncConnection\Log\Logger;

class AsyncSmtpConnectionManagerFactory extends \Consistence\ObjectPrototype
{

	/** @var \AsyncConnection\Smtp\AsyncSmtpConnectionWriterFactory */
	private $writerFactory;

	/** @var \AsyncConnection\Connector\ConnectorFactory */
	private $connectorFactory;

	/** @var \AsyncConnection\Log\Logger */
	private $logger;

	/** @var \AsyncConnection\Smtp\SmtpSettings */
	private $smtpSettings;

	public function __construct(
		AsyncSmtpConnectionWriterFactory $writerFactory,
		ConnectorFactory $connectorFactory,
		Logger $logger,
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
			$this->smtpSettings
		);

		return new AsyncConnectionManager($connection, $this->logger);
	}

}
