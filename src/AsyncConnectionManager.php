<?php declare(strict_types = 1);

namespace AsyncConnection;

use Psr\Log\LoggerInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
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

	public function connect(): PromiseInterface
	{
		if ($this->isConnected() && !$this->isDisconnecting()) {
			$this->logger->debug('Connected');

			return resolve(AsyncConnectionResult::success($this->writer, false));
		}

		if ($this->isConnecting()) {
			$this->logger->debug('Already connecting');

			return $this->connectionPromise->promise();
		}

		if ($this->isDisconnecting()) {
			$this->logger->debug('Disconnection in progress. waiting ...');
			$waitUntilDisconnectEnds = $this->disconnectionPromise->promise();

		} else {
			$waitUntilDisconnectEnds = resolve(AsyncDisconnectionResult::success());
		}

		$this->connectionPromise = new Deferred();

		return $waitUntilDisconnectEnds->then(function (AsyncDisconnectionResult $result) {
			if (!$result->isDisconnected()) {
				$this->logger->debug('Disconnection failed. No need to reconnect now.');
				$result = AsyncConnectionResult::success($this->writer, false);
				$this->connectionPromise->resolve($result);

				return resolve($result);
			}

			$this->logger->debug('Connecting...');

			return $this->asyncConnector->connect()
				->then(
					function (AsyncConnectionWriter $writer) {
						$this->logger->debug('Connecting succeeded');
						$this->writer = $writer;
						$result = AsyncConnectionResult::success($writer, true);
						$this->connectionPromise->resolve($result);

						return resolve($result);
					},
				)
				->catch(function (Throwable $e): PromiseInterface {
					$this->logger->error('Connecting failed');
					$this->connectionPromise->resolve(AsyncConnectionResult::failure($e));

					if ($e instanceof TimeoutException) {
						$exception = new AsyncConnectionTimeoutException($e->getMessage(), $e->getCode(), $e);

						return resolve(AsyncConnectionResult::failure($exception));
					}

					return resolve(AsyncConnectionResult::failure($e));
				});
		})->finally(function (): void {
			$this->connectionPromise = null;
		});
	}

	public function disconnect(): PromiseInterface
	{
		if ($this->isDisconnecting()) {
			$this->logger->debug('Already disconnecting');

			return $this->disconnectionPromise->promise();
		}

		if (!$this->isConnected() && !$this->isConnecting()) {
			$this->logger->debug('Not connected');

			return resolve(AsyncDisconnectionResult::success());
		}

		if ($this->isConnecting()) {
			$this->logger->debug('Connection in progress. Waiting ...');
			$waitUntilFinished = $this->connectionPromise->promise();

		} elseif ($this->isConnected()) {
			$waitUntilFinished = resolve(AsyncConnectionResult::success($this->writer));
		}

		$this->disconnectionPromise = new Deferred();

		return $waitUntilFinished->then(function (AsyncConnectionResult $connectionResult) {
			if (!$connectionResult->isConnected()) {
				$this->logger->error('Connection failed. No need to disconnect now.');

				return resolve(AsyncDisconnectionResult::success());
			}
			$this->logger->debug('Disconnection started');

			return $this->asyncConnector->disconnect($this->writer)
				->then(function () {
					$this->logger->debug('Disconnection succeeded');
					$this->writer = null;

					$result = AsyncDisconnectionResult::success();
					$this->disconnectionPromise->resolve($result);

					return resolve($result);
				})
				->catch(function (Throwable $e) {
					$this->logger->debug('Disconnection failed');

					$result = AsyncDisconnectionResult::failure($e);
					$this->disconnectionPromise->resolve($result);

					return resolve($result);
				})->finally(function (): void {
					$this->disconnectionPromise = null;
				});
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
