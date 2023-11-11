<?php declare(strict_types = 1);

namespace AsyncConnection;

use AsyncConnection\Timer\PromiseTimer;
use Psr\Log\LoggerInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Throwable;
use function array_slice;
use function array_values;
use function count;
use function React\Promise\resolve;
use function sprintf;
use function time;

class AsyncMessageQueueManager
{

	private const MIN_INTERVAL_BETWEEN_MESSAGES = 0.1;

	private const MAX_INTERVAL_BETWEEN_MESSAGES = 60;

	private const MAX_MESSAGES_PER_CONNECTION = 500;

	private AsyncMessageSender $asyncMessageSender;

	private AsyncConnectionManager $asyncConnectionManager;

	private LoggerInterface $logger;

	private PromiseTimer $promiseTimer;

	/** @var array<int, AsyncMessage> */
	private array $messageQueue = [];

	/** @var array<Deferred> */
	private array $processingRequests = [];

	private float $maxIntervalBetweenMessages;

	private float $minIntervalBetweenMessages;

	private int $maxMessagesPerConnection;

	private ?int $lastSentMessageTime = null;

	private static int $sentMessagesCount = 0;

	private bool $forceReconnect = false;

	private PromiseInterface $minIntervalPromise;

	public function __construct(
		AsyncMessageSender $asyncMessageSender,
		AsyncConnectionManager $asyncConnectionManager,
		LoggerInterface $logger,
		PromiseTimer $promiseTimer,
		?float $maxIntervalBetweenMessages = null,
		?int $maxMessagesPerConnection = null,
		?float $minIntervalBetweenMessages = null
	)
	{
		$this->asyncMessageSender = $asyncMessageSender;
		$this->asyncConnectionManager = $asyncConnectionManager;
		$this->logger = $logger;
		$this->promiseTimer = $promiseTimer;
		$this->maxIntervalBetweenMessages = $maxIntervalBetweenMessages ?? self::MAX_INTERVAL_BETWEEN_MESSAGES;
		$this->minIntervalBetweenMessages = $minIntervalBetweenMessages ?? self::MIN_INTERVAL_BETWEEN_MESSAGES;
		$this->maxMessagesPerConnection = $maxMessagesPerConnection ?? self::MAX_MESSAGES_PER_CONNECTION;
		$this->minIntervalPromise = resolve(true);
	}

	public function send(AsyncMessage $message): PromiseInterface
	{
		static $requestsCounter = 0;
		$previousRequestsCount = $requestsCounter;
		$requestsCounter++;

		$this->log(sprintf('previous requests count: %d', $previousRequestsCount), $requestsCounter);

		$this->messageQueue[$requestsCounter] = $message;

		if (count($this->messageQueue) === 1) {
			$previousRequestPromise = resolve(null);

		} else {
			/** @var Deferred $previousRequest */
			$previousRequest = array_values(array_slice($this->processingRequests, -1))[0];
			$previousRequestPromise = $previousRequest->promise();
			$this->log('waiting until previous request finishes ...', $requestsCounter);
		}

		$currentRequest = new Deferred();
		$this->processingRequests[$requestsCounter] = $currentRequest;

		return $previousRequestPromise->then(
			function () use ($requestsCounter) {
				$this->log('previous request finished', $requestsCounter);

				return resolve(true);
			},
			function (Throwable $e) use ($requestsCounter) {
				$this->log('previous request failed', $requestsCounter);

				return resolve(true);
			},
		)->then(
			function () use ($requestsCounter) {
				if ($this->shouldReconnect()) {
					return $this->reconnect($requestsCounter);
				}

				return resolve(true);
			},
		)->then(
			function () use ($requestsCounter) {
				$this->log('started', $requestsCounter);

				return $this->asyncConnectionManager->connect();
			},
		)->then(
			function (AsyncConnectionResult $result) use ($requestsCounter) {
				$this->log('connected', $requestsCounter);

				return $this->minIntervalPromise->then(static fn () => resolve($result));
			},
			function (Throwable $exception) use ($requestsCounter): void {
				$this->log('connection failed', $requestsCounter);
				$this->finishWithError($exception, $requestsCounter);

				throw $exception;
			},
		)->then(
			fn (AsyncConnectionResult $result) => $this->asyncMessageSender->sendMessage($result->getWriter(), $message)->then(static fn () => resolve($result)),
		)->then(
			function (AsyncConnectionResult $result) use ($requestsCounter): void {
				if ($result->hasConnectedToServer()) {
					self::$sentMessagesCount = 1;
				} else {
					self::$sentMessagesCount++;
				}
				$this->log('sending ok', $requestsCounter);
				$this->lastSentMessageTime = time();
				$this->finishWithSuccess($requestsCounter);
			},
			function (Throwable $exception) use ($requestsCounter): void {
				$this->log('sending failed', $requestsCounter);
				$this->forceReconnect = true;
				$this->finishWithError($exception, $requestsCounter);

				throw $exception;
			},
		);
	}

	public function getQueuedMessagesCount(): int
	{
		return count($this->messageQueue);
	}

	/**
	 * @return array<mixed>
	 */
	public function getQueuedMessages(): array
	{
		return $this->messageQueue;
	}

	public function getSentMessagesCount(): int
	{
		return self::$sentMessagesCount;
	}

	public function resetSentMessagesCounter(): void
	{
		self::$sentMessagesCount = 0;
	}

	private function finishWithError(Throwable $exception, int $requestsCounter): void
	{
		unset($this->messageQueue[$requestsCounter]);
		$this->processingRequests[$requestsCounter]->reject($exception);
		$this->minIntervalPromise = resolve(true);
	}

	private function finishWithSuccess(int $requestsCounter): void
	{
		unset($this->messageQueue[$requestsCounter]);
		$this->processingRequests[$requestsCounter]->resolve(null);

		$this->minIntervalPromise = $this->promiseTimer->wait($this->minIntervalBetweenMessages);
	}

	private function shouldReconnect(): bool
	{
		return ($this->lastSentMessageTime !== null && $this->lastSentMessageTime <= time() - $this->maxIntervalBetweenMessages)
			|| self::$sentMessagesCount >= $this->maxMessagesPerConnection
			|| $this->forceReconnect;
	}

	private function reconnect(int $requestsCounter): PromiseInterface
	{
		$this->log('reconnecting started', $requestsCounter);
		$this->forceReconnect = false;

		return $this->asyncConnectionManager->disconnect()->then(function (): PromiseInterface {
			$this->resetSentMessagesCounter();

			return resolve(true);
		});
	}

	private function log(string $message, int $requestNumber): void
	{
		$this->logger->debug(sprintf('Request #%d: %s', $requestNumber, $message));
	}

}
