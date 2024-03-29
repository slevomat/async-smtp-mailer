<?php declare(strict_types = 1);

namespace AsyncConnection\Connector;

use React\EventLoop\LoopInterface;
use React\Socket\ConnectorInterface;
use React\Socket\SecureConnector;
use React\Socket\TimeoutConnector;

class ConnectorFactory
{

	private const DEFAULT_TIMEOUT_IN_SECONDS = 3;

	private LoopInterface $loop;

	private bool $useSecureConnector;

	/** @var array<array<mixed>> */
	private array $context;

	private int $timeoutInSeconds;

	/**
	 * @param array<array<mixed>> $context
	 */
	public function __construct(
		LoopInterface $loop,
		bool $useSecureConnector = false,
		array $context = [],
		?int $timeoutInSeconds = null
	)
	{
		$this->loop = $loop;
		$this->useSecureConnector = $useSecureConnector;
		$this->context = $context;
		$this->timeoutInSeconds = $timeoutInSeconds ?? self::DEFAULT_TIMEOUT_IN_SECONDS;
	}

	public function create(): ConnectorInterface
	{
		$connector = new TcpConnector($this->loop, $this->context);

		if ($this->useSecureConnector) {
			$connector = new SecureConnector($connector, $this->loop, $this->context);
		}

		if ($this->timeoutInSeconds > 0) {
			$connector = new TimeoutConnector($connector, $this->timeoutInSeconds, $this->loop);
		}

		return $connector;
	}

}
