<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncConnectionWriter;
use AsyncConnection\AsyncMessage;
use Nette\Mail\Message;
use Psr\Log\LoggerInterface;
use React\Promise\Deferred;
use React\Promise\ExtendedPromiseInterface;
use React\Socket\ConnectionInterface;
use Throwable;
use function array_shift;
use function count;
use function implode;
use function in_array;
use function preg_match;
use function React\Promise\reject;
use function React\Promise\resolve;
use function sprintf;
use function trim;

class AsyncSmtpConnectionWriter implements AsyncConnectionWriter
{

	/** @var mixed[][] */
	private array $expectedResponses = [];

	private ConnectionInterface $connection;

	private LoggerInterface $logger;

	private ?Deferred $dataProcessingPromise = null;

	public function __construct(
		ConnectionInterface $connection,
		LoggerInterface $logger
	)
	{
		if (!$connection->isReadable() || !$connection->isWritable()) {
			throw new InvalidSmtpConnectionException();
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

		$connection->on('error', function (Throwable $e): void {
			$this->processNonDataResponse('error', 'SMTP server connection error.', $e);
		});

		$this->connection = $connection;
		$this->logger = $logger;
	}

	public function isValid(): bool
	{
		return $this->connection->isReadable() && $this->connection->isWritable();
	}

	public function write(AsyncMessage $message): ExtendedPromiseInterface
	{
		$this->logger->debug($message->getText());

		if (!$this->isValid()) {
			$this->logger->error('stream not valid');

			return reject(new InvalidSmtpConnectionException());
		}

		if ($message instanceof AsyncDoubleResponseMessage) {
			$firstResponse = new Deferred();
			$secondResponse = new Deferred();
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

			$this->connection->write(sprintf('%s%s', $message->getText(), Message::EOL));

			return $firstResponse->promise()
				->then(static fn () => $secondResponse->promise());
		}

		$deferred = new Deferred();
		$this->connection->write(sprintf('%s%s', $message->getText(), Message::EOL));

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

	private function processDataResponse(string $data): void
	{
		$this->dataProcessingPromise = new Deferred();

		$this->logger->debug('RECEIVED DATA: ' . $data);

		if (count($this->expectedResponses) === 0) {
			return;
		}

		[$deferred, $expectedCodes, $message, $messageToReplace] = array_shift($this->expectedResponses);

		if (preg_match('~^[\d]{3}$~i', $data) !== 1
			&& preg_match('~^[\d]{3}[^\d]+~i', $data) !== 1) {
			$deferred->reject(new AsyncSmtpConnectionException(sprintf('Unexpected response format: %s.', $data)));
		}

		$code = (int) $data;
		if (in_array($code, $expectedCodes, true)) {
			$this->logger->debug('code OK');
			$deferred->resolve();

		} else {
			$this->logger->debug('code WRONG');
			$errorMessage = sprintf(
				'SMTP server did not accept %s. Expected code: %s. Actual code: %s.',
				$messageToReplace ?? sprintf('message %s', $message),
				implode('|', $expectedCodes),
				$code,
			);
			$tooManyMessagesData = [
				'550 5.7.0 Requested action not taken: too many emails per second',
			];

			$exception = in_array(trim($data), $tooManyMessagesData, true)
				? new TooManyMessagesException($errorMessage)
				: new AsyncSmtpConnectionException($errorMessage);

			$deferred->reject($exception);
		}

		$this->dataProcessingPromise->resolve();
		$this->dataProcessingPromise = null;
	}

	private function processNonDataResponse(
		string $debugMessage,
		string $exceptionMessage,
		?Throwable $previousException = null
	): void
	{
		$this->logger->debug($debugMessage);
		if (count($this->expectedResponses) === 0) {
			return;
		}

		$dataProcessingPromise = $this->dataProcessingPromise !== null ? $this->dataProcessingPromise->promise() : resolve();
		$dataProcessingPromise->then(function () use ($exceptionMessage, $previousException): void {
			if (count($this->expectedResponses) <= 0) {
				return;
			}

			while (count($this->expectedResponses) > 0) {
				[$deferred] = array_shift($this->expectedResponses);
				if ($deferred === null) {
					continue;
				}

				$deferred->reject(new AsyncSmtpConnectionException($exceptionMessage, 0, $previousException));
			}
		});
	}

}
