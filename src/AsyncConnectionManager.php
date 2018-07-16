<?php declare(strict_types = 1);

namespace AsyncConnection;

class AsyncConnectionManager extends \Consistence\ObjectPrototype
{

	/** @var \AsyncConnection\AsyncConnector */
	private $asyncConnector;

	/** @var \React\Promise\Deferred|null */
	private $connectionPromise;

	/** @var \React\Promise\Deferred|null */
	private $disconnectionPromise;

	/** @var \AsyncConnection\AsyncConnectionWriter|null */
	private $writer;

	/** @var \Psr\Log\LoggerInterface */
	private $logger;

	public function __construct(
		AsyncConnector $asyncConnector,
		\Psr\Log\LoggerInterface $logger
	)
	{
		$this->asyncConnector = $asyncConnector;
		$this->logger = $logger;
	}

	public function connect(): \React\Promise\ExtendedPromiseInterface
	{
		if ($this->isConnected() && !$this->isDisconnecting()) {
			$this->logger->debug('Connected');
			return \React\Promise\resolve(new AsyncConnectionResult($this->writer, false));
		}

		if ($this->isConnecting()) {
			$this->logger->debug('Already connecting');
			return $this->connectionPromise->promise();
		}

		if ($this->isDisconnecting()) {
			$this->logger->debug('Disconnection in progress. waiting ...');
			$waitUntilDisconnectEnds = $this->disconnectionPromise->promise();

		} else {
			$waitUntilDisconnectEnds = \React\Promise\resolve();
		}

		$this->connectionPromise = new \React\Promise\Deferred();

		$doAfterFailedDisconnect = function (\Throwable $e) {
			$this->logger->debug('Disconnection failed. No need to reconnect now.');
			$this->connectionPromise->resolve();
			$this->connectionPromise = null;

			return \React\Promise\resolve(new AsyncConnectionResult($this->writer, false));
		};

		return $waitUntilDisconnectEnds->then(function () {
			$this->logger->debug('Connecting...');
			return $this->asyncConnector->connect()->then(
				function (AsyncConnectionWriter $writer) {
					$this->logger->debug('Connecting succeeded');
					$this->writer = $writer;
					$this->connectionPromise->resolve();
					$this->connectionPromise = null;

					return \React\Promise\resolve(new AsyncConnectionResult($writer, true));
				},
				function (\Throwable $e): void {
					$this->logger->error('Connecting failed');
					$this->connectionPromise->reject($e);
					$this->connectionPromise = null;

					if ($e instanceof \React\Promise\Timer\TimeoutException) {
						throw new \AsyncConnection\AsyncConnectionTimeoutException($e->getMessage(), $e);
					}

					throw $e;
				}
			);
		}, $doAfterFailedDisconnect);
	}

	public function disconnect(): \React\Promise\ExtendedPromiseInterface
	{
		if ($this->isDisconnecting()) {
			$this->logger->debug('Already disconnecting');
			return $this->disconnectionPromise->promise();
		}

		if (!$this->isConnected() && !$this->isConnecting()) {
			$this->logger->debug('Not connected');
			return \React\Promise\resolve('Not connected.');
		}

		if ($this->isConnecting()) {
			$this->logger->debug('Connection in progress. Waiting ...');
			$waitUntilFinished = $this->connectionPromise->promise();

		} else {
			$waitUntilFinished = \React\Promise\resolve();
		}

		$this->disconnectionPromise = new \React\Promise\Deferred();

		return $waitUntilFinished->then(function () {
			$this->logger->debug('Disconnection started');

			return $this->asyncConnector->disconnect($this->writer)
				->then(function ($value) {
					$this->logger->debug('Disconnection succeeded');
					$this->disconnectionPromise->resolve();
					$this->disconnectionPromise = null;
					$this->writer = null;

					return \React\Promise\resolve($value);
				}, function (\Throwable $e): void {
					$this->logger->debug('Disconnection failed');
					$this->disconnectionPromise->reject();
					$this->disconnectionPromise = null;

					throw $e;
				});
		}, function (\Throwable $e) {
			$this->logger->error('Connection failed. No need to disconnect now.');
			$this->disconnectionPromise->resolve();
			$this->disconnectionPromise = null;

			return \React\Promise\resolve();
		});
	}

	public function isConnecting(): bool
	{
		return $this->connectionPromise !== null;
	}

	public function isDisconnecting(): bool
	{
		return $this->disconnectionPromise !== null;
	}

	public function isConnected(): bool
	{
		return $this->writer !== null && $this->writer->isValid();
	}

}
