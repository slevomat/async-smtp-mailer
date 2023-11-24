<?php declare(strict_types = 1);

namespace AsyncConnection;

use Closure;
use Exception;
use Psr\Log\LoggerInterface;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\Timer\TimeoutException;
use Throwable;
use function React\Promise\reject;
use function React\Promise\resolve;

class AsyncConnectionManagerTest extends TestCase
{

	use AsyncTestTrait;

	private LoopInterface $loop;

	private bool $streamIsValid = true;

	private LoggerInterface $logger;

	protected function setUp(): void
	{
		$this->loop = Factory::create();
		$this->logger = $this->getLogger();
	}

	public function testConnectWhenNotConnected(): void
	{
		$connectorMock = $this->getConnector();
		$manager = $this->getConnectionManager($connectorMock);
		$manager->connect()->then(
			function (AsyncConnectionResult $result): void {
				if ($result->isConnected()) {
					$this->setException(false);
				} else {
					$this->setException($result->getError());
				}
			},
		);

		$this->runSuccessfulTest($this->loop);
	}

	public function testConnectWhenConnected(): void
	{
		$connectorMock = $this->getConnector();
		$manager = $this->getConnectionManager($connectorMock);
		$manager->connect()->then(
			function () use ($manager): void {
				$manager->connect()->then(
					function (AsyncConnectionResult $result): void {
						if ($result->isConnected()) {
							try {
								$this->setException(false);

							} catch (Throwable $e) {
								$this->setException($e);
							}
						} else {
							$this->setException($result->getError());
						}
					},
				);
			},
			function (Throwable $e): void {
				$this->setException($e);
			},
		);

		$this->runSuccessfulTest($this->loop);
	}

	public function testConnectingWhenConnectedToInvalidStream(): void
	{
		$connectorMock = $this->getConnector(
			2,
			0,
			0,
			0,
			fn (): bool => $this->streamIsValid,
		);
		$manager = $this->getConnectionManager($connectorMock);
		$manager->connect()->then(
			function () use ($manager): void {
				$this->streamIsValid = false;
				$manager->connect()->then(
					function (AsyncConnectionResult $result): void {
						if ($result->isConnected()) {
							$this->setException(false);
						} else {
							$this->setException($result->getError());
						}
					},
				);
			},
			function (Throwable $e): void {
				$this->setException($e);
			},
		);

		$this->runSuccessfulTest($this->loop);
	}

	public function testConnectWhenConnecting(): void
	{
		$connectorMock = $this->getConnector(1, 0, 2);
		$manager = $this->getConnectionManager($connectorMock);
		$manager->connect()->then(
			function (AsyncConnectionResult $result): void {
				if (!$result->isConnected()) {
					$this->setException($result->getError());
				}
			},
		);

		$manager->connect()->then(
			function (AsyncConnectionResult $result): void {
				if ($result->isConnected()) {
					$this->setException(false);
				} else {
					$this->setException($result->getError());
				}
			},
		);

		$this->runSuccessfulTest($this->loop);
	}

	public function testConnectWhenDisconnecting(): void
	{
		$connectorMock = $this->getConnector(2, 1, 0, 2);
		$manager = $this->getConnectionManager($connectorMock);
		$manager->connect()->then(
			function () use ($manager): void {
				$manager->disconnect()->then(
					function (AsyncDisconnectionResult $result): void {
						if (!$result->isDisconnected()) {
							$this->setException($result->getError());
						}
					},
				);

				$manager->connect()->then(
					function (AsyncConnectionResult $result): void {
						if ($result->isConnected()) {
							$this->setException(false);
						} else {
							$this->setException($result->getError());
						}
					},
				);
			},
			function (Throwable $e): void {
				$this->setException($e);
			},
		);

		$this->runSuccessfulTest($this->loop);
	}

	public function testDisconnectWhenConnected(): void
	{
		$connectorMock = $this->getConnector(1, 1);
		$manager = $this->getConnectionManager($connectorMock);
		$manager->connect()->then(
			function (AsyncConnectionResult $result) use ($manager): void {
				if ($result->isConnected()) {
					$manager->disconnect()->then(
						function (AsyncDisconnectionResult $result): void {
							if ($result->isDisconnected()) {
								$this->setException(false);
							} else {
								$this->setException($result->getError());
							}
						},
					);
				} else {
					$this->setException($result->getError());
				}
			},
		);

		$this->runSuccessfulTest($this->loop);
	}

	public function testDisconnectWhenNotConnected(): void
	{
		$connectorMock = $this->getConnector(0, 0);
		$manager = $this->getConnectionManager($connectorMock);
		$manager->disconnect()->then(
			function (AsyncDisconnectionResult $result): void {
				if ($result->isDisconnected()) {
					$this->setException(false);
				} else {
					$this->setException($result->getError());
				}
			},
			function (Throwable $e): void {
				$this->setException($e);
			},
		);

		$this->runSuccessfulTest($this->loop);
	}

	public function testDisconnectWhenConnecting(): void
	{
		$connectorMock = $this->getConnector(1, 1, 2);
		$manager = $this->getConnectionManager($connectorMock);
		$manager->connect()->then(
			function (AsyncConnectionResult $result): void {
				if (!$result->isConnected()) {
					$this->setException($result->getError());
				}
			},
		);

		$manager->disconnect()->then(
			function (AsyncDisconnectionResult $result): void {
				if ($result->isDisconnected()) {
					$this->setException(false);
				} else {
					$this->setException($result->getError());
				}
			},
		);

		$this->runSuccessfulTest($this->loop);
	}

	public function testDisconnectWhenDisconnecting(): void
	{
		$connectorMock = $this->getConnector(1, 1, 0, 2);
		$manager = $this->getConnectionManager($connectorMock);
		$manager->connect()->then(
			function (AsyncConnectionResult $result) use ($manager): void {
				if ($result->isConnected()) {
					$manager->disconnect()->then(
						function (AsyncDisconnectionResult $result): void {
							if ($result->isDisconnected()) {
								$this->setException(false);
							} else {
								$this->setException($result->getError());
							}
						},
					);

					$manager->disconnect()->then(
						function (AsyncDisconnectionResult $result): void {
							if ($result->isDisconnected()) {
								$this->setException(false);
							} else {
								$this->setException($result->getError());
							}
						},
					);
				} else {
					$this->setException($e);
				}
			},
		);

		$this->runSuccessfulTest($this->loop);
	}

	public function testConnectionFailureReturnValue(): void
	{
		$connectorMock = $this->createMock(AsyncConnector::class);
		$connectorMock->method('connect')
			->willReturn(reject(new Exception('Connection failed.')));

		$manager = $this->getConnectionManager($connectorMock);
		$manager->connect()->then(
			function (AsyncConnectionResult $result): void {
				if ($result->isConnected()) {
					$this->setException(false);
				} else {
					$this->setException($result->getError());
				}
			},
		);

		$this->runFailedTest($this->loop, function (Throwable $e): void {
			$this->assertSame('Connection failed.', $e->getMessage());
		});
	}

	public function testTimeoutCausesPromiseRejection(): void
	{
		$connectorMock = $this->createMock(AsyncConnector::class);
		$connectorMock->method('connect')
			->willReturn(reject(new TimeoutException(3, 'Timed out after 3 second')));

		$manager = $this->getConnectionManager($connectorMock);
		$manager->connect()->then(
			function (AsyncConnectionResult $result): void {
				if ($result->isConnected()) {
					$this->setException(false);
				} else {
					$this->setException($result->getError());
				}
			},
		);

		$this->runFailedTest($this->loop, function (Throwable $e): void {
			$this->assertInstanceOf(AsyncConnectionTimeoutException::class, $e);
			$this->assertSame('Timed out after 3 second', $e->getMessage());
		});
	}

	public function testDisconnectFailure(): void
	{
		$writerMock = $this->createMock(AsyncConnectionWriter::class);
		$writerMock->method('isValid')->willReturn(true);

		$connectorMock = $this->createMock(AsyncConnector::class);
		$connectorMock->method('connect')
			->willReturn(resolve($writerMock));

		$connectorMock->method('disconnect')
			->willReturn(reject(new Exception('Disconnection failed.')));

		$manager = $this->getConnectionManager($connectorMock);
		$manager->connect()->then(
			function (AsyncConnectionResult $result) use ($manager): void {
				if ($result->isConnected()) {
					$manager->disconnect()->then(
						function (AsyncDisconnectionResult $result): void {
							if ($result->isDisconnected()) {
								$this->setException(false);
							} else {
								$this->setException($result->getError());
							}
						},
					);
				}
			},
		);

		$this->runFailedTest($this->loop, function (Throwable $e): void {
			$this->assertSame('Disconnection failed.', $e->getMessage());
		});
	}

	private function getConnector(
		?int $expectedConnectionAttempts = 1,
		?int $expectedDisconnectionAttempts = 0,
		?int $connectionDelayInSeconds = 0,
		?int $disconnectionDelayInSeconds = 0,
		?Closure $isValidCallback = null
	): AsyncConnector
	{
		$connectorMock = $this->createMock(AsyncConnector::class);
		$writerMock = $this->createMock(AsyncConnectionWriter::class);
		if ($isValidCallback === null) {
			$writerMock->method('isValid')->willReturn(true);
		} else {
			$writerMock->method('isValid')->willReturnCallback($isValidCallback);
		}

		if ($expectedConnectionAttempts > 0) {
			$delayedConnection = new Deferred();
			$this->loop->addTimer($connectionDelayInSeconds, static function () use ($delayedConnection, $writerMock): void {
				$delayedConnection->resolve($writerMock);
			});
			$connectorMock
				->expects($this->exactly($expectedConnectionAttempts))
				->method('connect')
				->willReturn($delayedConnection->promise());
		} else {
			$connectorMock
				->expects($this->never())
				->method('connect');
		}

		if ($expectedDisconnectionAttempts > 0) {
			$delayedDisconnection = new Deferred();
			$this->loop->addTimer($disconnectionDelayInSeconds, static function () use ($delayedDisconnection, $writerMock): void {
				$delayedDisconnection->resolve($writerMock);
			});
			$connectorMock
				->expects($this->exactly($expectedDisconnectionAttempts))
				->method('disconnect')
				->willReturn($delayedDisconnection->promise());
		} else {
			$connectorMock
				->expects($this->never())
				->method('disconnect');
		}

		return $connectorMock;
	}

	private function getConnectionManager(AsyncConnector $connectorMock): AsyncConnectionManager
	{
		return new AsyncConnectionManager($connectorMock, $this->logger);
	}

}
