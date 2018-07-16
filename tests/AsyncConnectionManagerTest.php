<?php declare(strict_types = 1);

namespace AsyncConnection;

class AsyncConnectionManagerTest extends \AsyncConnection\TestCase
{

	use \AsyncConnection\AsyncTestTrait;

	/** @var \React\EventLoop\LoopInterface */
	private $loop;

	/** @var bool */
	private $streamIsValid = true;

	/** @var \Psr\Log\LoggerInterface */
	private $logger;

	protected function setUp(): void
	{
		$this->loop = \React\EventLoop\Factory::create();
		$this->logger = $this->getLogger();
	}

	public function testConnectWhenNotConnected(): void
	{
		$connectorMock = $this->getConnector();
		$manager = $this->getConnectionManager($connectorMock);
		$manager->connect()->done(
			function (): void {
				$this->setException(false);
			},
			function (\Throwable $exception): void {
				$this->setException($exception);
			}
		);

		$this->runSuccessfulTest($this->loop);
	}

	public function testConnectWhenConnected(): void
	{
		$connectorMock = $this->getConnector();
		$manager = $this->getConnectionManager($connectorMock);
		$manager->connect()->then(
			function () use ($manager): void {
				$manager->connect()->done(
					function (): void {
						try {
							$this->setException(false);

						} catch (\Throwable $e) {
							$this->setException($e);
						}
					},
					function (\Throwable $e): void {
						$this->setException($e);
					}
				);
			},
			function (\Throwable $e): void {
				$this->setException($e);
			}
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
			function (): bool {
				return $this->streamIsValid;
			}
		);
		$manager = $this->getConnectionManager($connectorMock);
		$manager->connect()->then(
			function () use ($manager): void {
				$this->streamIsValid = false;
				$manager->connect()->done(
					function (): void {
						$this->setException(false);
					},
					function (\Throwable $e): void {
						$this->setException($e);
					}
				);
			},
			function (\Throwable $e): void {
				$this->setException($e);
			}
		);

		$this->runSuccessfulTest($this->loop);
	}

	public function testConnectWhenConnecting(): void
	{
		$connectorMock = $this->getConnector(1, 0, 2);
		$manager = $this->getConnectionManager($connectorMock);
		$manager->connect()->then(
			function (): void {
			},
			function (\Throwable $e): void {
				$this->setException($e);
			}
		);

		$manager->connect()->then(
			function (): void {
				$this->setException(false);
			},
			function (\Throwable $e): void {
				$this->setException($e);
			}
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
					function (): void {
					},
					function (\Throwable $e): void {
						$this->setException($e);
					}
				);

				$manager->connect()->then(
					function (): void {
						$this->setException(false);
					},
					function (\Throwable $e): void {
						$this->setException($e);
					}
				);
			},
			function (\Throwable $e): void {
				$this->setException($e);
			}
		);

		$this->runSuccessfulTest($this->loop);
	}

	public function testDisconnectWhenConnected(): void
	{
		$connectorMock = $this->getConnector(1, 1);
		$manager = $this->getConnectionManager($connectorMock);
		$manager->connect()->then(
			function () use ($manager): void {
				$manager->disconnect()->done(
					function (): void {
						$this->setException(false);
					},
					function (\Throwable $e): void {
						$this->setException($e);
					}
				);
			},
			function (\Throwable $exception): void {
				$this->setException($exception);
			}
		);

		$this->runSuccessfulTest($this->loop);
	}

	public function testDisconnectWhenNotConnected(): void
	{
		$connectorMock = $this->getConnector(0, 0);
		$manager = $this->getConnectionManager($connectorMock);
		$manager->disconnect()->done(
			function ($message): void {
				try {
					$this->assertSame('Not connected.', $message);
					$this->setException(false);
				} catch (\Throwable $e) {
					$this->setException($e);
				}
			},
			function (\Throwable $e): void {
				$this->setException($e);
			}
		);

		$this->runSuccessfulTest($this->loop);
	}

	public function testDisconnectWhenConnecting(): void
	{
		$connectorMock = $this->getConnector(1, 1, 2);
		$manager = $this->getConnectionManager($connectorMock);
		$manager->connect()->then(
			function (): void {
			},
			function (\Throwable $e): void {
				$this->setException($e);
			}
		);

		$manager->disconnect()->then(
			function (): void {
				$this->setException(false);
			},
			function (\Throwable $e): void {
				$this->setException($e);
			}
		);

		$this->runSuccessfulTest($this->loop);
	}

	public function testDisconnectWhenDisconnecting(): void
	{
		$connectorMock = $this->getConnector(1, 1, 0, 2);
		$manager = $this->getConnectionManager($connectorMock);
		$manager->connect()->then(
			function () use ($manager): void {
				$manager->disconnect()->then(
					function (): void {
					},
					function (\Throwable $e): void {
						$this->setException($e);
					}
				);

				$manager->disconnect()->then(
					function (): void {
						$this->setException(false);
					},
					function (\Throwable $e): void {
						$this->setException($e);
					}
				);
			},
			function (\Throwable $e): void {
				$this->setException($e);
			}
		);

		$this->runSuccessfulTest($this->loop);
	}

	public function testConnectionFailureReturnsRejectedPromise(): void
	{
		$connectorMock = $this->createMock(AsyncConnector::class);
		$connectorMock->method('connect')
			->willReturn(\React\Promise\reject(new \Exception('Connection failed.')));

		$manager = $this->getConnectionManager($connectorMock);
		$manager->connect()->done(
			function (): void {
				$this->setException(false);
			},
			function (\Throwable $e): void {
				$this->setException($e);
			}
		);

		$this->runFailedTest($this->loop, function (\Throwable $e): void {
			$this->assertSame('Connection failed.', $e->getMessage());
		});
	}

	public function testTimeoutCausesPromiseRejection(): void
	{
		$connectorMock = $this->createMock(AsyncConnector::class);
		$connectorMock->method('connect')
			->willReturn(\React\Promise\reject(new \React\Promise\Timer\TimeoutException(3, 'Timed out after 3 second')));

		$manager = $this->getConnectionManager($connectorMock);
		$manager->connect()->done(
			function (): void {
				$this->setException(false);
			},
			function (\Throwable $e): void {
				$this->setException($e);
			}
		);

		$this->runFailedTest($this->loop, function (\Throwable $e): void {
			$this->assertInstanceOf(\AsyncConnection\AsyncConnectionTimeoutException::class, $e);
			$this->assertSame('Timed out after 3 second', $e->getMessage());
		});
	}

	public function testDisconnectFailureReturnsRejectedPromise(): void
	{
		$writerMock = $this->createMock(AsyncConnectionWriter::class);
		$writerMock->method('isValid')->willReturn(true);

		$connectorMock = $this->createMock(AsyncConnector::class);
		$connectorMock->method('connect')
			->willReturn(\React\Promise\resolve($writerMock));

		$connectorMock->method('disconnect')
			->willReturn(\React\Promise\reject(new \Exception('Disconnection failed.')));

		$manager = $this->getConnectionManager($connectorMock);
		$manager->connect()->then(
			function () use ($manager): void {
				$manager->disconnect()->done(
					function (): void {
						$this->setException(false);
					},
					function (\Throwable $e): void {
						$this->setException($e);
					}
				);
			}
		);

		$this->runFailedTest($this->loop, function (\Throwable $e): void {
			$this->assertSame('Disconnection failed.', $e->getMessage());
		});
	}

	private function getConnector(
		?int $expectedConnectionAttempts = 1,
		?int $expectedDisconnectionAttempts = 0,
		?int $connectionDelayInSeconds = 0,
		?int $disconnectionDelayInSeconds = 0,
		?\Closure $isValidCallback = null
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
			$delayedConnection = new \React\Promise\Deferred();
			$this->loop->addTimer($connectionDelayInSeconds, function () use ($delayedConnection, $writerMock): void {
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
			$delayedDisconnection = new \React\Promise\Deferred();
			$this->loop->addTimer($disconnectionDelayInSeconds, function () use ($delayedDisconnection, $writerMock): void {
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
