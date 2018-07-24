<?php declare(strict_types = 1);

namespace AsyncConnection;

use AsyncConnection\Timer\PromiseTimer;

class AsyncMessageQueueManager extends \Consistence\ObjectPrototype
{

	private const MIN_INTERVAL_BETWEEN_MESSAGES = 0.1;

	private const MAX_INTERVAL_BETWEEN_MESSAGES = 60;

	private const MAX_MESSAGES_PER_CONNECTION = 500;

	/** @var \AsyncConnection\AsyncMessageSender */
	private $asyncMessageSender;

	/** @var \AsyncConnection\AsyncConnectionManager */
	private $asyncConnectionManager;

	/** @var \Psr\Log\LoggerInterface */
	private $logger;

	/** @var \AsyncConnection\Timer\PromiseTimer */
	private $promiseTimer;

	/** @var mixed[] */
	private $messageQueue = [];

	/** @var \React\Promise\Deferred[] */
	private $processingRequests = [];

	/** @var float */
	private $maxIntervalBetweenMessages;

	/** @var float */
	private $minIntervalBetweenMessages;

	/** @var int */
	private $maxMessagesPerConnection;

	/** @var int */
	private $lastSentMessageTime;

	/** @var int */
	private static $sentMessagesCount = 0;

	/** @var bool */
	private $forceReconnect = false;

	/** @var \React\Promise\ExtendedPromiseInterface */
	private $minIntervalPromise;

	public function __construct(
		AsyncMessageSender $asyncMessageSender,
		AsyncConnectionManager $asyncConnectionManager,
		\Psr\Log\LoggerInterface $logger,
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
		$this->minIntervalPromise = \React\Promise\resolve();
	}

	public function send(AsyncMessage $message): \React\Promise\ExtendedPromiseInterface
	{
		static $requestsCounter = 0;
		$previousRequestsCount = $requestsCounter;
		++$requestsCounter;

		$this->log(sprintf('previous requests count: %d', $previousRequestsCount), $requestsCounter);

		$this->messageQueue[$requestsCounter] = $message;

		if (count($this->messageQueue) === 1) {
			$previousRequestPromise = \React\Promise\resolve();

		} else {
			/** @var \React\Promise\Deferred $previousRequest */
			$previousRequest = array_values(array_slice($this->processingRequests, -1))[0];
			$previousRequestPromise = $previousRequest->promise();
			$this->log('waiting until previous request finishes ...', $requestsCounter);
		}

		$currentRequest = new \React\Promise\Deferred();
		$this->processingRequests[$requestsCounter] = $currentRequest;

		return $previousRequestPromise->then(
			function () use ($requestsCounter) {
				$this->log('previous request finished', $requestsCounter);
				return \React\Promise\resolve();
			},
			function (\Throwable $e) use ($requestsCounter) {
				$this->log('previous request failed', $requestsCounter);
				return \React\Promise\resolve();
			}
		)->then(
			function () use ($requestsCounter) {
				if ($this->shouldReconnect()) {
					return $this->reconnect($requestsCounter);
				}

				return \React\Promise\resolve();
			}
		)->then(
			function () use ($requestsCounter) {
				$this->log('started', $requestsCounter);

				return $this->asyncConnectionManager->connect();
			}
		)->then(
			function (AsyncConnectionResult $result) use ($requestsCounter) {
				$this->log('connected', $requestsCounter);

				return $this->minIntervalPromise->then(function () use ($result) {
					return \React\Promise\resolve($result);
				});
			},
			function (\Throwable $exception) use ($requestsCounter): void {
				$this->log('connection failed', $requestsCounter);
				$this->finishWithError($exception, $requestsCounter);

				throw $exception;
			}
		)->then(
			function (AsyncConnectionResult $result) use ($message) {
				return $this->asyncMessageSender->sendMessage($result->getWriter(), $message)->then(function () use ($result) {
					return \React\Promise\resolve($result);
				});
			}
		)->then(
			function (AsyncConnectionResult $result) use ($requestsCounter): void {
				if ($result->hasConnectedToServer()) {
					self::$sentMessagesCount = 1;
				} else {
					++self::$sentMessagesCount;
				}
				$this->log('sending ok', $requestsCounter);
				$this->lastSentMessageTime = time();
				$this->finishWithSuccess($requestsCounter);
			},
			function (\Throwable $exception) use ($requestsCounter): void {
				$this->log('sending failed', $requestsCounter);
				$this->forceReconnect = true;
				$this->finishWithError($exception, $requestsCounter);
				throw $exception;
			}
		);
	}

	public function getQueuedMessagesCount(): int
	{
		return count($this->messageQueue);
	}

	/**
	 * @return mixed[]
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

	private function finishWithError(\Throwable $exception, int $requestsCounter): void
	{
		unset($this->messageQueue[$requestsCounter]);
		$this->processingRequests[$requestsCounter]->reject($exception);
		$this->minIntervalPromise = \React\Promise\resolve();
	}

	private function finishWithSuccess(int $requestsCounter): void
	{
		unset($this->messageQueue[$requestsCounter]);
		$this->processingRequests[$requestsCounter]->resolve();

		if ($this->minIntervalBetweenMessages === null) {
			$this->minIntervalPromise = \React\Promise\resolve();
		} else {
			$this->minIntervalPromise = $this->promiseTimer->wait($this->minIntervalBetweenMessages);
		}
	}

	private function shouldReconnect(): bool
	{
		return ($this->lastSentMessageTime !== null && $this->lastSentMessageTime <= time() - $this->maxIntervalBetweenMessages)
			|| self::$sentMessagesCount >= $this->maxMessagesPerConnection
			|| $this->forceReconnect;
	}

	private function reconnect(int $requestsCounter): \React\Promise\ExtendedPromiseInterface
	{
		$this->log('reconnecting started', $requestsCounter);
		$this->forceReconnect = false;

		return $this->asyncConnectionManager->disconnect()->then(function (): \React\Promise\ExtendedPromiseInterface {
			$this->resetSentMessagesCounter();
			return \React\Promise\resolve();
		});
	}

	private function log(string $message, int $requestNumber): void
	{
		$this->logger->debug(sprintf('Request #%d: %s', $requestNumber, $message));
	}

}
