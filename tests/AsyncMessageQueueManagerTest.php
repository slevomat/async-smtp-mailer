<?php declare(strict_types = 1);

namespace AsyncConnection;

use AsyncConnection\Smtp\SmtpCode;
use Closure;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Throwable;
use function array_pop;
use function array_shift;
use function count;
use function React\Promise\reject;
use function React\Promise\resolve;
use function sleep;
use function time;

class AsyncMessageQueueManagerTest extends TestCase
{

	use AsyncTestTrait;

	/** @var AsyncConnectionManager|MockObject */
	private $connectionManagerMock;

	/** @var AsyncConnectionWriter|MockObject */
	private $writerMock;

	/** @var AsyncMessageSender|MockObject */
	private $senderMock;

	private LoopInterface $loop;

	/** @var array<string, bool|Throwable> */
	private array $exceptions = [];

	private int $disconnectsCount = 0;

	private int $connectsCount = 0;

	private LoggerInterface $logger;

	protected function setUp(): void
	{
		$this->logger = $this->getLogger();
		$this->connectionManagerMock = $this->createMock(AsyncConnectionManager::class);
		$this->writerMock = $this->createMock(AsyncConnectionWriter::class);
		$this->senderMock = $this->createMock(AsyncMessageSender::class);
		$this->loop = Factory::create();
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
			->willReturn(resolve(new AsyncConnectionResult($this->writerMock, true)));

		$this->senderMock->method('sendMessage')
			->willReturn(resolve(SmtpCode::OK));

		$this->runSuccessfulSendingTest($this->createManager(), 'message');
	}

	public function testFailedSendingWithExpectedError(): void
	{
		$this->connectionManagerMock->method('connect')
			->willReturn(resolve(new AsyncConnectionResult($this->writerMock, true)));

		$this->senderMock->method('sendMessage')
			->willReturn(reject(new AsyncConnectionException('Sending failed')));

		$assertOnFail = function (Throwable $e): void {
			$this->assertInstanceOf(AsyncConnectionException::class, $e);
			$this->assertSame('Sending failed', $e->getMessage());
		};

		$this->runFailedSendingTest(
			$this->createManager(),
			'message',
			'Failed sending returned resolved promise.',
			$assertOnFail,
		);
	}

	public function testFailedSendingWithUnexpectedError(): void
	{
		$this->connectionManagerMock->method('connect')
			->willReturn(resolve(new AsyncConnectionResult($this->writerMock, true)));

		$this->senderMock->method('sendMessage')
			->willReturn(reject(new Exception('Unexpected error')));

		$assertOnFail = function (Throwable $e): void {
			$this->assertSame('Unexpected error', $e->getMessage());
		};

		$this->runFailedSendingTest(
			$this->createManager(),
			'message',
			'Failed sending returned resolved promise.',
			$assertOnFail,
		);
	}

	public function testFailedConnectionWithUnexpectedError(): void
	{
		$this->connectionManagerMock->method('connect')
			->willReturn(reject(new Exception('Unexpected error')));

		$this->senderMock->method('sendMessage')
			->willReturn(resolve(SmtpCode::OK));

		$assertOnFail = function (Throwable $e): void {
			$this->assertSame('Unexpected error', $e->getMessage());
		};

		$this->runFailedSendingTest(
			$this->createManager(),
			'message',
			'Failed connection returned resolved promise.',
			$assertOnFail,
		);
	}

	public function testFailedConnectionWithExpectedError(): void
	{
		$this->connectionManagerMock->method('connect')
			->willReturn(reject(new AsyncConnectionException('Connection failed')));

		$this->senderMock->method('sendMessage')
			->willReturn(resolve(SmtpCode::OK));

		$assertOnFail = function (Throwable $e): void {
			$this->assertInstanceOf(AsyncConnectionException::class, $e);
			$this->assertSame('Connection failed', $e->getMessage());
		};

		$this->runFailedSendingTest(
			$this->createManager(),
			'message',
			'Failed connection returned resolved promise.',
			$assertOnFail,
		);
	}

	public function testMultipleRequestsAreProcessedInQueue(): void
	{
		$this->connectionManagerMock->method('connect')
			->willReturnCallback(function (): PromiseInterface {
				$this->connectsCount++;

				return resolve(new AsyncConnectionResult($this->writerMock, $this->connectsCount === 1));
			});

		$this->senderMock->method('sendMessage')
			->will($this->returnCallback(
				function (): PromiseInterface {
					$deferred = new Deferred();

					$this->loop->addTimer(5, static function () use ($deferred): void {
						$deferred->resolve(SmtpCode::OK);
					});

					return $deferred->promise();
				},
			));

		$this->exceptions = [];

		$manager = $this->createManager();
		$manager->send(new SimpleAsyncMessage('a'))->then(
			function (): void {
				$this->exceptions['first'] = false;
			},
			function (Throwable $e): void {
				$this->exceptions['first'] = $e;
			},
		);

		$this->assertSame(1, $manager->getQueuedMessagesCount(), 'Unexpected queued messages count.');
		$this->assertSame(0, $manager->getSentMessagesCount(), 'Unexpected sent messages count');

		$manager->send(new SimpleAsyncMessage('b'))->then(
			function (): void {
				$this->exceptions['second'] = false;
			},
			function (Throwable $e): void {
				$this->exceptions['second'] = $e;
			},
		);

		$messages = $manager->getQueuedMessages();
		$firstMessage = array_shift($messages);
		$lastMessage = array_pop($messages);
		$this->assertSame(2, $manager->getQueuedMessagesCount(), 'Unexpected queued messages count.');
		$this->assertSame('a', $firstMessage->getText(), 'Unexpected first emails subject');
		$this->assertSame('b', $lastMessage->getText(), 'Unexpected second emails subject');

		$this->loop->addPeriodicTimer($this->getTimerInterval(), function () use ($manager): void {
			if (isset($this->exceptions['second'])) {
				$this->loop->stop();
				$exception = $this->exceptions['second'];
				if ($exception instanceof Throwable) {
					throw $exception;
				}

				$this->assertSame(0, $manager->getQueuedMessagesCount(), 'Unexpected queued messages count.');
				$this->assertSame(2, $manager->getSentMessagesCount(), 'Unexpected sent messages count');

			} elseif (isset($this->exceptions['first'])) {
				$exception = $this->exceptions['first'];
				if ($exception instanceof Throwable) {
					$this->loop->stop();

					throw $exception;
				}

				try {
					$this->assertSame(1, $manager->getQueuedMessagesCount(), 'Unexpected queued messages count.');
					$messages = $manager->getQueuedMessages();
					$message = array_shift($messages);
					$this->assertSame('b', $message->getText());
					$this->assertSame(1, $manager->getSentMessagesCount(), 'Unexpected sent messages count');

				} catch (Throwable $e) {
					$this->loop->stop();

					throw $e;
				}
			}
		});

		$this->loop->run();
	}

	public function testSendingWhenPreviousRequestSucceeded(): void
	{
		$this->connectionManagerMock->method('connect')
			->willReturn(resolve(new AsyncConnectionResult($this->writerMock, true)));

		$this->senderMock->method('sendMessage')
			->willReturn(resolve(SmtpCode::OK));

		$manager = $this->createManager();

		$manager->send(new SimpleAsyncMessage('message'))
			->then(
				static function (): void {
				},
				function (Throwable $e): void {
					$this->setException($e);
				},
			);

		$manager->send(new SimpleAsyncMessage('message'))
			->then(
				function (): void {
					$this->setException(false);
				},
				function (Throwable $e): void {
					$this->setException($e);
				},
			);

		$this->runSuccessfulTest($this->loop);
	}

	public function testSendingWhenPreviousConnectionFailed(): void
	{
		$this->connectionManagerMock->method('disconnect')
			->willReturnCallback(static fn (): PromiseInterface => resolve(null));

		$this->connectionManagerMock->method('connect')
			->willReturnCallback(function (): PromiseInterface {
				if (count($this->exceptions) === 0) {
					return reject(new Exception('Unexpected error'));
				}

				$this->connectsCount++;

				return resolve(new AsyncConnectionResult($this->writerMock, true));
			});

		$this->senderMock->method('sendMessage')
			->willReturn(resolve(SmtpCode::OK));

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
			->willReturnCallback(function (): PromiseInterface {
				$this->connectsCount++;

				return resolve(new AsyncConnectionResult($this->writerMock, $this->connectsCount === 1));
			});

		$this->connectionManagerMock->method('disconnect')
			->willReturn(resolve(null));

		$this->senderMock->method('sendMessage')
			->willReturnCallback(fn (): PromiseInterface => $this->connectsCount === 1
					? reject(new AsyncConnectionException('Sending failed'))
					: resolve(SmtpCode::OK));

		$asserts = function (array $exceptions, AsyncMessageQueueManager $manager): void {
			$this->assertInstanceOf(AsyncConnectionException::class, $exceptions['first']);
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
			$maxIntervalBetweenMessages,
			$maxMessagesPerConnection,
			$minIntervalBetweenMessages,
		);
		$manager->resetSentMessagesCounter();

		return $manager;
	}

	private function runSuccessfulSendingTest(
		AsyncMessageQueueManager $manager,
		string $message,
		?Closure $assertOnSuccess = null
	): void
	{
		$manager->send(new SimpleAsyncMessage($message))
			->then(
				function (): void {
					$this->setException(false);
				},
				function (Throwable $exception): void {
					$this->setException($exception);
				},
			);

		$this->runSuccessfulTest($this->loop, $assertOnSuccess);
	}

	private function runFailedSendingTest(
		AsyncMessageQueueManager $manager,
		string $message,
		string $errorMessage,
		Closure $assertOnFail
	): void
	{
		$manager->send(new SimpleAsyncMessage($message))
			->then(
				function (): void {
					$this->setException(false);
				},
				function (Throwable $exception): void {
					$this->setException($exception);
				},
			);

		$this->runFailedTest($this->loop, $assertOnFail, $errorMessage);
	}

	private function runDisconnectionTest(
		?int $maxIntervalBetweenMessages = null,
		?int $maxMessagesPerConnection = null
	): void
	{
		$this->senderMock->method('sendMessage')
			->willReturn(resolve(SmtpCode::OK));

		$this->connectionManagerMock
			->method('connect')
			// intentionally setting $connectionRequest = false (otherwise sentMessagesCounter will be reset)
			->willReturn(resolve(new AsyncConnectionResult($this->writerMock, false)));

		$this->connectionManagerMock
			->method('disconnect')
			->will($this->returnCallback(function () {
				$this->disconnectsCount++;

				return resolve(null);
			}));

		$this->disconnectsCount = 0;
		$this->connectsCount = 0;

		$manager = $this->createManager($maxIntervalBetweenMessages, $maxMessagesPerConnection);

		$manager->send(new SimpleAsyncMessage('message'))
			->then(
				function () use ($manager, $maxIntervalBetweenMessages): void {
					try {
						$this->assertSame(1, $manager->getSentMessagesCount(), 'Unexpected sent messages count.');
						$this->assertSame(0, $this->disconnectsCount, 'Unexpected disconnects count.');
						if ($maxIntervalBetweenMessages !== 0) {
							sleep($maxIntervalBetweenMessages + 1);
						}

						$manager->send(new SimpleAsyncMessage('message'))
							->then(
								function (): void {
									$this->setException(false);
								},
								function (Throwable $e): void {
									$this->setException($e);
								},
							);

					} catch (Throwable $e) {
						$this->setException($e);
					}
				},
				function (Throwable $e): void {
					$this->setException($e);
				},
			);

		$asserts = function () use ($manager): void {
			$this->assertSame(1, $this->disconnectsCount, 'Unexpected total disconnects count.');
			$this->assertSame(1, $manager->getSentMessagesCount(), 'Unexpected total sent messages count.');
		};

		$this->runSuccessfulTest($this->loop, $asserts);
	}

	private function runTwoRequestsTest(Closure $asserts): void
	{
		$manager = $this->createManager();
		$manager->send(new SimpleAsyncMessage('message'))
			->then(
				function (): void {
					$this->exceptions['first'] = false;
				},
				function (Throwable $e): void {
					$this->exceptions['first'] = $e;
				},
			);

		$manager->send(new SimpleAsyncMessage('message'))
			->then(
				function (): void {
					$this->exceptions['second'] = false;
				},
				function (Throwable $e): void {
					$this->exceptions['second'] = $e;
				},
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
