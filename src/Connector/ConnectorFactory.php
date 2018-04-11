<?php declare(strict_types = 1);

namespace AsyncConnection\Connector;

class ConnectorFactory extends \Consistence\ObjectPrototype
{

	private const DEFAULT_TIMEOUT_IN_SECONDS = 3;

	/** @var \React\EventLoop\LoopInterface */
	private $loop;

	/** @var bool */
	private $useSecureConnector;

	/** @var mixed[][] */
	private $context;

	/** @var int */
	private $timeoutInSeconds;

	/**
	 * @param \React\EventLoop\LoopInterface $loop
	 * @param bool $useSecureConnector
	 * @param mixed[][] $context
	 * @param int|null $timeoutInSeconds
	 */
	public function __construct(
		\React\EventLoop\LoopInterface $loop,
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

	public function create(): \React\Socket\ConnectorInterface
	{
		$connector = new TcpConnector($this->loop, $this->context);

		if ($this->useSecureConnector) {
			$connector = new \React\Socket\SecureConnector($connector, $this->loop, $this->context);
		}

		if ($this->timeoutInSeconds > 0) {
			$connector = new \React\Socket\TimeoutConnector($connector, $this->timeoutInSeconds, $this->loop);
		}

		return $connector;
	}

}
