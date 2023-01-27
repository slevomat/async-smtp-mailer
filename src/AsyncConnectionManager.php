<?php declare(strict_types = 1);

namespace AsyncConnection;

use Psr\Log\LoggerInterface;
use React\Promise\Deferred;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\Timer\TimeoutException;
use Throwable;
use function React\Promise\resolve;

class AsyncConnectionManager
{

	private AsyncConnector $asyncConnector;

	private ?Deferred $connectionPromise = null;

	private ?Deferred $disconnectionPromise = null;

	private ?AsyncConnectionWriter $writer = null;

	private LoggerInterface $logger;

	public function __construct(
		AsyncConnector $asyncConnector,
		LoggerInterface $logger
	)
	{
		$this->asyncConnector = $asyncConnector;
		$this->logger = $logger;
	}

	public function connect(): ExtendedPromiseInterface
	{
		if ($this->isConnected() && !$this->isDisconnecting()) {
			$this->logger->debug('Connected');

			return resolve(new AsyncConnectionResult($this->writer, false));
		}

		if ($this->isConnecting()) {
			$this->logger->debug('Already connecting');

			return $this->connectionPromise->promise();
		}

		if ($this->isDisconnecting()) {
			$this->logger->debug('Disconnection in progress. waiting ...');
			$waitUntilDisconnectEnds = $this->disconnectionPromise->promise();

		} else {
			$waitUntilDisconnectEnds = resolve();
		}

		$this->connectionPromise = new Deferred();

		$doAfterFailedDisconnect = function (Throwable $e) {
			$this->logger->debug('Disconnection failed. No need to reconnect now.');
			$this->connectionPromise->resolve();
			$this->connectionPromise = null;

			return resolve(new AsyncConnectionResult($this->writer, false));
		};

		return $waitUntilDisconnectEnds->then(function () {
			$this->logger->debug('Connecting...');

			return $this->asyncConnector->connect()->then(
				function (AsyncConnectionWriter $writer) {
					$this->logger->debug('Connecting succeeded');
					$this->writer = $writer;
					$this->connectionPromise->resolve();
					$this->connectionPromise = null;

					return resolve(new AsyncConnectionResult($writer, true));
				},
				function (Throwable $e): void {
					$this->logger->error('Connecting failed');
					$this->connectionPromise->reject($e);
					$this->connectionPromise = null;

					if ($e instanceof TimeoutException) {
						throw new AsyncConnectionTimeoutException($e->getMessage(), $e->getCode(), $e);
					}

					throw $e;
				},
			);
		}, $doAfterFailedDisconnect);
	}

	public function disconnect(): ExtendedPromiseInterface
	{
		if ($this->isDisconnecting()) {
			$this->logger->debug('Already disconnecting');

			return $this->disconnectionPromise->promise();
		}

		if (!$this->isConnected() && !$this->isConnecting()) {
			$this->logger->debug('Not connected');

			return resolve('Not connected.');
		}

		if ($this->isConnecting()) {
			$this->logger->debug('Connection in progress. Waiting ...');
			$waitUntilFinished = $this->connectionPromise->promise();

		} else {
			$waitUntilFinished = resolve();
		}

		$this->disconnectionPromise = new Deferred();

		return $waitUntilFinished->then(function () {
			$this->logger->debug('Disconnection started');

			return $this->asyncConnector->disconnect($this->writer)
				->then(function ($value) {
					$this->logger->debug('Disconnection succeeded');
					$this->disconnectionPromise->resolve();
					$this->disconnectionPromise = null;
					$this->writer = null;

					return resolve($value);
				}, function (Throwable $e): void {
					$this->logger->debug('Disconnection failed');
					$this->disconnectionPromise->reject();
					$this->disconnectionPromise = null;

					throw $e;
				});
		}, function (Throwable $e) {
			$this->logger->error('Connection failed. No need to disconnect now.');
			$this->disconnectionPromise->resolve();
			$this->disconnectionPromise = null;

			return resolve();
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
