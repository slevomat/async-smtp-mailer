<?php declare(strict_types = 1);

namespace AsyncConnection;

use AsyncConnection\Timer\PromiseTimer;

class AsyncMessageQueueManagerTest extends \AsyncConnection\TestCase
{

	use \AsyncConnection\AsyncTestTrait;

	/** @var \AsyncConnection\AsyncConnectionManager|\PHPUnit_Framework_MockObject_MockObject */
	private $connectionManagerMock;

	/** @var \AsyncConnection\AsyncConnectionWriter|\PHPUnit_Framework_MockObject_MockObject */
	private $writerMock;

	/** @var \AsyncConnection\AsyncMessageSender|\PHPUnit_Framework_MockObject_MockObject */
	private $senderMock;

	/** @var \React\EventLoop\LoopInterface */
	private $loop;

	/** @var mixed[] */
	private $exceptions = [];

	/** @var int */
	private $disconnectsCount = 0;

	/** @var int */
	private $connectsCount = 0;

	/** @var \Psr\Log\LoggerInterface */
	private $logger;

	public function setUp(): void
	{
		$this->logger = $this->getLogger();
		$this->connectionManagerMock = $this->createMock(AsyncConnectionManager::class);
		$this->writerMock = $this->createMock(AsyncConnectionWriter::class);
		$this->senderMock = $this->createMock(AsyncMessageSender::class);
		$this->loop = \React\EventLoop\Factory::create();
	}

	public function testDisconnectingWhenSentMailsCountExceedsLimit(): void
	{
		$this->runDisconnectionTest(null, 1);
	}

	public function testDisconnectingWhenTimeSinceLastSentMailExceedsLimit(): void
	{
		$this->runDisconnectionTest(1);
	}

	public function testSuccessfulSendingReturnsPromise(): void
	{
		$this->connectionManagerMock->method('connect')
			->willReturn(\React\Promise\resolve(new AsyncConnectionResult($this->writerMock, true)));

		$this->senderMock->method('sendMessage')
			->willReturn(\React\Promise\resolve());

		$this->runSuccessfulSendingTest($this->createManager(), 'message');
	}

	public function testFailedSendingWithExpectedError(): void
	{
		$this->connectionManagerMock->method('connect')
			->willReturn(\React\Promise\resolve(new AsyncConnectionResult($this->writerMock, true)));

		$this->senderMock->method('sendMessage')
			->willReturn(\React\Promise\reject(new \AsyncConnection\AsyncConnectionException('Sending failed')));

		$assertOnFail = function (\Throwable $e): void {
			$this->assertInstanceOf(\AsyncConnection\AsyncConnectionException::class, $e);
			$this->assertSame('Sending failed', $e->getMessage());
		};

		$this->runFailedSendingTest(
			$this->createManager(),
			'message',
			'Failed sending returned resolved promise.',
			$assertOnFail
		);
	}

	public function testFailedSendingWithUnexpectedError(): void
	{
		$this->connectionManagerMock->method('connect')
			->willReturn(\React\Promise\resolve(new AsyncConnectionResult($this->writerMock, true)));

		$this->senderMock->method('sendMessage')
			->willReturn(\React\Promise\reject(new \Exception('Unexpected error')));

		$assertOnFail = function (\Throwable $e): void {
			$this->assertSame('Unexpected error', $e->getMessage());
		};

		$this->runFailedSendingTest(
			$this->createManager(),
			'message',
			'Failed sending returned resolved promise.',
			$assertOnFail
		);
	}

	public function testFailedConnectionWithUnexpectedError(): void
	{
		$this->connectionManagerMock->method('connect')
			->willReturn(\React\Promise\reject(new \Exception('Unexpected error')));

		$this->senderMock->method('sendMessage')
			->willReturn(\React\Promise\resolve());

		$assertOnFail = function (\Throwable $e): void {
			$this->assertSame('Unexpected error', $e->getMessage());
		};

		$this->runFailedSendingTest(
			$this->createManager(),
			'message',
			'Failed connection returned resolved promise.',
			$assertOnFail
		);
	}

	public function testFailedConnectionWithExpectedError(): void
	{
		$this->connectionManagerMock->method('connect')
			->willReturn(\React\Promise\reject(new \AsyncConnection\AsyncConnectionException('Connection failed')));

		$this->senderMock->method('sendMessage')
			->willReturn(\React\Promise\resolve());

		$assertOnFail = function (\Throwable $e): void {
			$this->assertInstanceOf(\AsyncConnection\AsyncConnectionException::class, $e);
			$this->assertSame('Connection failed', $e->getMessage());
		};

		$this->runFailedSendingTest(
			$this->createManager(),
			'message',
			'Failed connection returned resolved promise.',
			$assertOnFail
		);
	}

	public function testMultipleRequestsAreProcessedInQueue(): void
	{
		$this->connectionManagerMock->method('connect')
			->willReturnCallback(function (): \React\Promise\ExtendedPromiseInterface {
				$this->connectsCount++;

				return \React\Promise\resolve(new AsyncConnectionResult($this->writerMock, $this->connectsCount === 1));
			});

		$this->senderMock->method('sendMessage')
			->will($this->returnCallback(
				function (): \React\Promise\ExtendedPromiseInterface {
					$deferred = new \React\Promise\Deferred();

					$this->loop->addTimer(5, function () use ($deferred): void {
						$deferred->resolve();
					});

					return $deferred->promise();
				}
			));

		$this->exceptions = [];

		$manager = $this->createManager();
		$manager->send('a')->done(
			function (): void {
				$this->exceptions['first'] = false;
			},
			function (\Throwable $e): void {
				$this->exceptions['first'] = $e;
			}
		);

		$this->assertSame(1, $manager->getQueuedMessagesCount(), 'Unexpected queued messages count.');
		$this->assertSame(0, $manager->getSentMessagesCount(), 'Unexpected sent messages count');

		$manager->send('b')->done(
			function (): void {
				$this->exceptions['second'] = false;
			},
			function (\Throwable $e): void {
				$this->exceptions['second'] = $e;
			}
		);

		$messages = $manager->getQueuedMessages();
		$firstMessage = array_shift($messages);
		$lastMessage = array_pop($messages);
		$this->assertSame(2, $manager->getQueuedMessagesCount(), 'Unexpected queued messages count.');
		$this->assertSame('a', $firstMessage, 'Unexpected first emails subject');
		$this->assertSame('b', $lastMessage, 'Unexpected second emails subject');

		$this->loop->addPeriodicTimer($this->getTimerInterval(), function () use ($manager): void {
			if (isset($this->exceptions['second'])) {
				$this->loop->stop();
				$exception = $this->exceptions['second'];
				if ($exception instanceof \Throwable) {
					throw $exception;
				} else {
					$this->assertSame(0, $manager->getQueuedMessagesCount(), 'Unexpected queued messages count.');
					$this->assertSame(2, $manager->getSentMessagesCount(), 'Unexpected sent messages count');
				}

			} elseif (isset($this->exceptions['first'])) {
				$exception = $this->exceptions['first'];
				if ($exception instanceof \Throwable) {
					$this->loop->stop();
					throw $exception;
				} else {
					try {
						$this->assertSame(1, $manager->getQueuedMessagesCount(), 'Unexpected queued messages count.');
						$messages = $manager->getQueuedMessages();
						$message = array_shift($messages);
						$this->assertSame('b', $message);
						$this->assertSame(1, $manager->getSentMessagesCount(), 'Unexpected sent messages count');

					} catch (\Throwable $e) {
						$this->loop->stop();
						throw $e;
					}
				}
			}
		});

		$this->loop->run();
	}

	public function testSendingWhenPreviousRequestSucceeded(): void
	{
		$this->connectionManagerMock->method('connect')
			->willReturn(\React\Promise\resolve(new AsyncConnectionResult($this->writerMock, true)));

		$this->senderMock->method('sendMessage')
			->willReturn(\React\Promise\resolve());

		$manager = $this->createManager();

		$manager->send('message')
			->done(
				function (): void {
				},
				function (\Throwable $e): void {
					$this->setException($e);
				}
			);

		$manager->send('message')
			->done(
				function (): void {
					$this->setException(false);
				},
				function (\Throwable $e): void {
					$this->setException($e);
				}
			);

		$this->runSuccessfulTest($this->loop);
	}

	public function testSendingWhenPreviousConnectionFailed(): void
	{
		$this->connectionManagerMock->method('connect')
			->willReturnCallback(function (): \React\Promise\ExtendedPromiseInterface {
				if (count($this->exceptions) === 0) {
					return \React\Promise\reject(new \Exception('Unexpected error'));
				}

				$this->connectsCount++;
				return \React\Promise\resolve(new AsyncConnectionResult($this->writerMock, true));
			});

		$this->senderMock->method('sendMessage')
			->willReturn(\React\Promise\resolve());

		$asserts = function (array $exceptions, AsyncMessageQueueManager $manager): void {
			$this->assertSame('Unexpected error', $exceptions['first']->getMessage());
			$this->assertFalse($exceptions['second']);
			$this->assertSame(1, $manager->getSentMessagesCount());
			$this->assertSame(1, $this->connectsCount);
		};

		$this->runTwoRequestsTest($asserts);
	}

	public function testSendingWhenPreviousSendingFailed(): void
	{
		$this->connectionManagerMock->method('connect')
			->willReturnCallback(function (): \React\Promise\ExtendedPromiseInterface {
				$this->connectsCount++;

				return \React\Promise\resolve(new AsyncConnectionResult($this->writerMock, $this->connectsCount === 1));
			});

		$this->connectionManagerMock->method('disconnect')
			->willReturn(\React\Promise\resolve());

		$this->senderMock->method('sendMessage')
			->willReturnCallback(function (): \React\Promise\ExtendedPromiseInterface {
				return $this->connectsCount === 1
					? \React\Promise\reject(new \AsyncConnection\AsyncConnectionException('Sending failed'))
					: \React\Promise\resolve();
			});

		$asserts = function (array $exceptions, AsyncMessageQueueManager $manager): void {
			$this->assertInstanceOf(\AsyncConnection\AsyncConnectionException::class, $exceptions['first']);
			$this->assertFalse($exceptions['second']);
			$this->assertSame(1, $manager->getSentMessagesCount());
			$this->assertSame(2, $this->connectsCount);
		};

		$this->runTwoRequestsTest($asserts);
	}

	private function createManager(
		?int $maxIntervalBetweenMessages = null,
		?int $maxMessagesPerConnection = null,
		?int $minIntervalBetweenMessages = null
	): AsyncMessageQueueManager
	{
		$manager = new AsyncMessageQueueManager(
			$this->senderMock,
			$this->connectionManagerMock,
			$this->logger,
			new PromiseTimer($this->loop),
			$maxIntervalBetweenMessages,
			$maxMessagesPerConnection,
			$minIntervalBetweenMessages
		);
		$manager->resetSentMessagesCounter();

		return $manager;
	}

	private function runSuccessfulSendingTest(
		AsyncMessageQueueManager $manager,
		string $message,
		?\Closure $assertOnSuccess = null
	): void
	{
		$manager->send($message)
			->done(
				function (): void {
					$this->setException(false);
				},
				function (\Throwable $exception): void {
					$this->setException($exception);
				}
			);

		$this->runSuccessfulTest($this->loop, $assertOnSuccess);
	}

	private function runFailedSendingTest(
		AsyncMessageQueueManager $manager,
		string $message,
		string $errorMessage,
		\Closure $assertOnFail
	): void
	{
		$manager->send($message)
			->done(
				function (): void {
					$this->setException(false);
				},
				function (\Throwable $exception): void {
					$this->setException($exception);
				}
			);

		$this->runFailedTest($this->loop, $assertOnFail, $errorMessage);
	}

	private function runDisconnectionTest(
		?int $maxIntervalBetweenMessages = null,
		?int $maxMessagesPerConnection = null
	): void
	{
		$this->senderMock->method('sendMessage')
			->willReturn(\React\Promise\resolve());

		$this->connectionManagerMock
			->method('connect')
			// intentionally setting $connectionRequest = false (otherwise sentMessagesCounter will be reset)
			->willReturn(\React\Promise\resolve(new AsyncConnectionResult($this->writerMock, false)));

		$this->connectionManagerMock
			->method('disconnect')
			->will($this->returnCallback(function () {
				$this->disconnectsCount++;

				return \React\Promise\resolve();
			}));

		$this->disconnectsCount = $this->connectsCount = 0;

		$manager = $this->createManager($maxIntervalBetweenMessages, $maxMessagesPerConnection);

		$manager->send('message')
			->then(
				function () use ($manager, $maxIntervalBetweenMessages): void {
					try {
						$this->assertSame(1, $manager->getSentMessagesCount(), 'Unexpected sent messages count.');
						$this->assertSame(0, $this->disconnectsCount, 'Unexpected disconnects count.');
						if ($maxIntervalBetweenMessages !== 0) {
							sleep($maxIntervalBetweenMessages + 1);
						}

						$manager->send('message')
							->done(
								function (): void {
									$this->setException(false);
								},
								function (\Throwable $e): void {
									$this->setException($e);
								}
							);

					} catch (\Throwable $e) {
						$this->setException($e);
					}
				},
				function (\Throwable $e): void {
					$this->setException($e);
				}
			);

		$asserts = function () use ($manager): void {
			$this->assertSame(1, $this->disconnectsCount, 'Unexpected total disconnects count.');
			$this->assertSame(1, $manager->getSentMessagesCount(), 'Unexpected total sent messages count.');
		};

		$this->runSuccessfulTest($this->loop, $asserts);
	}

	private function runTwoRequestsTest(\Closure $asserts): void
	{
		$manager = $this->createManager();
		$manager->send('message')
			->done(
				function (): void {
					$this->exceptions['first'] = false;
				},
				function (\Throwable $e): void {
					$this->exceptions['first'] = $e;
				}
			);

		$manager->send('message')
			->done(
				function (): void {
					$this->exceptions['second'] = false;
				},
				function (\Throwable $e): void {
					$this->exceptions['second'] = $e;
				}
			);

		$startTime = time();
		$this->loop->addPeriodicTimer($this->getTimerInterval(), function () use ($startTime, $asserts, $manager): void {
			$this->checkLoopExecutionTime($this->loop, $startTime);
			if (count($this->exceptions) !== 2) {
				return;
			}

			$this->loop->stop();
			$asserts($this->exceptions, $manager);
		});
		$this->loop->run();
	}

}
