<?php declare(strict_types = 1);

// spell-check-ignore: didn

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncMessage;
use AsyncConnection\Log\Logger;

class AsyncSmtpConnectionWriter extends \Consistence\ObjectPrototype implements \AsyncConnection\AsyncConnectionWriter
{

	/** @var mixed[][] */
	private $expectedResponses = [];

	/** @var \React\Socket\ConnectionInterface */
	private $connection;

	/** @var \AsyncConnection\Log\Logger */
	private $logger;

	/** @var \React\Promise\Deferred|null */
	private $dataProcessingPromise;

	public function __construct(
		\React\Socket\ConnectionInterface $connection,
		Logger $logger
	)
	{
		if (!$connection->isReadable() || !$connection->isWritable()) {
			throw new \AsyncConnection\Smtp\InvalidSmtpConnectionException();
		}

		$connection->on('data', function ($data): void {
			$this->processDataResponse($data);
		});

		$connection->on('end', function (): void {
			$this->processNonDataResponse('end', 'SMTP server unexpectedly ended connection.');
		});

		$connection->on('close', function (): void {
			$this->processNonDataResponse('close', 'SMTP server unexpectedly closed connection.');
		});

		$connection->on('error', function (\Throwable $e): void {
			$this->processNonDataResponse('error', 'SMTP server connection error.', $e);
		});

		$this->connection = $connection;
		$this->logger = $logger;
	}

	public function isValid(): bool
	{
		return $this->connection->isReadable() && $this->connection->isWritable();
	}

	public function write(AsyncMessage $message): \React\Promise\ExtendedPromiseInterface
	{
		$this->logger->log($message->getText());

		if (!$this->isValid()) {
			$this->logger->log('stream not valid');
			return \React\Promise\reject(new \AsyncConnection\Smtp\InvalidSmtpConnectionException());
		}

		if ($message instanceof AsyncDoubleResponseMessage) {
			$firstResponse = new \React\Promise\Deferred();
			$secondResponse = new \React\Promise\Deferred();
			$this->expectedResponses[] = [
				$firstResponse,
				$message->getExpectedFirstResponseCodes(),
				$message->getText(),
				$message->getTextReplacement(),
			];
			$this->expectedResponses[] = [
				$secondResponse,
				$message->getExpectedSecondResponseCodes(),
				$message->getText(),
				$message->getTextReplacement(),
			];

			$this->connection->write(sprintf('%s%s', $message->getText(), \Nette\Mail\Message::EOL));

			return $firstResponse->promise()
				->then(function () use ($secondResponse) {
					return $secondResponse->promise();
				});

		} else {
			$deferred = new \React\Promise\Deferred();
			$this->connection->write(sprintf('%s%s', $message->getText(), \Nette\Mail\Message::EOL));

			if ($message instanceof AsyncSingleResponseMessage) {
				$this->expectedResponses[] = [
					$deferred,
					$message->getExpectedResponseCodes(),
					$message->getText(),
					$message->getTextReplacement(),
				];

			} else {
				$deferred->resolve();
			}

			return $deferred->promise();
		}
	}

	private function processDataResponse(string $data): void
	{
		$this->dataProcessingPromise = new \React\Promise\Deferred();

		$this->logger->log('RECEIVED DATA: ' . $data);
		if (count($this->expectedResponses) === 0) {
			throw new \AsyncConnection\Smtp\AsyncSmtpConnectionException(sprintf('Received unexpected data from server: %s.', $data));
		}

		list($deferred, $expectedCodes, $message, $messageToReplace) = array_shift($this->expectedResponses);

		if (preg_match('~^[\d]{3}$~i', $data) !== 1
			&& preg_match('~^[\d]{3}[^\d]+~i', $data) !== 1) {
			$deferred->reject(new \AsyncConnection\Smtp\AsyncSmtpConnectionException(sprintf('Unexpected response format: %s.', $data)));
		}

		$code = (int) $data;
		if (in_array($code, $expectedCodes, true)) {
			$this->logger->log('code OK');
			$deferred->resolve();

		} else {
			$this->logger->log('code WRONG');
			$errorMessage = sprintf(
				"SMTP server didn't accept %s. Expected code: %s. Actual code: %s.",
			$messageToReplace ? $messageToReplace : sprintf('message %s', $message), implode('|', $expectedCodes), $code);
			$tooManyMessagesData = [
				'550 5.7.0 Requested action not taken: too many emails per second',
			];

			if (in_array(trim($data), $tooManyMessagesData, true)) {
				$exception = new \AsyncConnection\Smtp\TooManyMessagesException($errorMessage);
			} else {
				$exception = new \AsyncConnection\Smtp\AsyncSmtpConnectionException($errorMessage);
			}

			$deferred->reject($exception);
		}

		$this->dataProcessingPromise->resolve();
		$this->dataProcessingPromise = null;
	}

	private function processNonDataResponse(
		string $debugMessage,
		string $exceptionMessage,
		?\Throwable $previousException = null
	): void
	{
		$this->logger->log($debugMessage);
		if (count($this->expectedResponses) === 0) {
			return;
		}

		$dataProcessingPromise = $this->dataProcessingPromise !== null ? $this->dataProcessingPromise->promise() : \React\Promise\resolve();
		$dataProcessingPromise->then(function () use ($exceptionMessage, $previousException): void {
			if (count($this->expectedResponses) > 0) {
				while (count($this->expectedResponses) > 0) {
					list($deferred) = array_shift($this->expectedResponses);
					if ($deferred !== null) {
						$deferred->reject(new \AsyncConnection\Smtp\AsyncSmtpConnectionException($exceptionMessage, $previousException));
					}
				}
			}
		});
	}

}
