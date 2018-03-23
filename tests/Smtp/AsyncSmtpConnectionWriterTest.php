<?php declare(strict_types = 1);

// spell-check-ignore: EHLO mailtrap ESMTP STARTTLS dariel didn HELO

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncMessage;

class AsyncSmtpConnectionWriterTest extends \AsyncConnection\TestCase
{

	use \AsyncConnection\AsyncTestTrait;

	private const MAX_LOOP_EXECUTION_TIME = 10;

	private const DEFAULT_INTERVAL_IN_SECONDS = 1;

	/** @var \React\EventLoop\LoopInterface */
	private $loop;

	/** @var \Throwable|false|null **/
	private $exception;

	/** @var \Closure|null */
	private $doOnData;

	/** @var \Closure|null */
	private $doOnEnd;

	/** @var \Closure|null */
	private $doOnClose;

	/** @var \Closure|null */
	private $doOnError;

	/** @var string[] */
	private $serverResponses;

	/** @var \AsyncConnection\Log\Logger */
	private $logger;

	protected function setUp(): void
	{
		$this->loop = \React\EventLoop\Factory::create();
		$this->exception = $this->doOnData = $this->doOnEnd = $this->doOnClose = $this->doOnError = null;
		$this->serverResponses = [];
		$this->logger = $this->getLogger();
	}

	public function testInvalidStreamThrowsException(): void
	{
		$this->expectException(\AsyncConnection\Smtp\InvalidSmtpConnectionException::class);

		$connectionMock = $this->createMock(\React\Socket\ConnectionInterface::class);
		$connectionMock->method('isReadable')->willReturn(false);
		$connectionMock->method('isWritable')->willReturn(false);

		new AsyncSmtpConnectionWriter($connectionMock, $this->logger);
	}

	public function testUnexpectedDataThrowException(): void
	{
		$this->expectException(\AsyncConnection\Smtp\AsyncSmtpConnectionException::class);
		$this->expectExceptionMessage('Received unexpected data from server: 333 unexpectedServerResponse.');

		new AsyncSmtpConnectionWriter($this->createConnectionMock(), $this->logger);
		$this->runFailedTest('333 unexpectedServerResponse');
	}

	public function testUnexpectedConnectionEndFromServer(): void
	{
		$writer = new AsyncSmtpConnectionWriter($this->createConnectionMock(), $this->logger);
		$writer->write(new AsyncSingleResponseMessage('AUTH LOGIN', [334]))
			->done(
				function (): void {
					$this->exception = false;
				},
				function (\Throwable $exception): void {
					$this->exception = $exception;
				}
			);

		($this->doOnEnd)();
		$this->runFailedTest(null, null, 'SMTP server unexpectedly ended connection.');
	}

	public function testUnexpectedConnectionClosedByServer(): void
	{
		$writer = new AsyncSmtpConnectionWriter($this->createConnectionMock(), $this->logger);
		$writer->write(new AsyncSingleResponseMessage('AUTH LOGIN', [334]))
			->done(
				function (): void {
					$this->exception = false;
				},
				function (\Throwable $exception): void {
					$this->exception = $exception;
				}
			);

		($this->doOnClose)();
		$this->runFailedTest(null, null, 'SMTP server unexpectedly closed connection.');
	}

	public function testConnectionErrorFromServer(): void
	{
		$writer = new AsyncSmtpConnectionWriter($this->createConnectionMock(), $this->logger);
		$writer->write(new AsyncSingleResponseMessage('AUTH LOGIN', [334]))
			->done(
				function (): void {
					$this->exception = false;
				},
				function (\Throwable $exception): void {
					$this->exception = $exception;
				}
			);

		($this->doOnError)(new \Exception('Something horrible happened!'));
		$this->runFailedTest(
			null,
			null,
			'SMTP server connection error.',
			'Something horrible happened!'
		);
	}

	/**
	 * @return mixed[][]
	 */
	public function dataSuccessfulWrites(): array
	{
		return [
			[
				new AsyncDoubleResponseMessage('EHLO slevomat.local', [SmtpCode::SERVICE_READY], [SmtpCode::OK]),
				'220 mailtrap.io ESMTP ready',
				"250-mailtrap.io\n250-SIZE 5242880\n250-PIPELINING\n250-ENHANCEDSTATUSCODES\n250-8BITMIME\n250-DSN\n250-AUTH PLAIN LOGIN CRAM-MD5\n250 STARTTLS",
			],
			[
				new AsyncDoubleResponseMessage('HELO slevomat.local', [SmtpCode::SERVICE_READY], [SmtpCode::OK]),
				'220',
				"250-mailtrap.io\n250-SIZE 5242880\n250-PIPELINING\n250-ENHANCEDSTATUSCODES\n250-8BITMIME\n250-DSN\n250-AUTH PLAIN LOGIN CRAM-MD5\n250 STARTTLS",
			],
			[
				new AsyncSingleResponseMessage('AUTH LOGIN', [SmtpCode::AUTH_CONTINUE]),
				'334 VXNlcm5hbWU6',
			],
			[
				new AsyncSingleResponseMessage('base64EncodedUsername', [SmtpCode::AUTH_CONTINUE], 'credentials'),
				'334 UGFzc3dvcmQ6',
			],
			[
				new AsyncSingleResponseMessage('base64EncodedPassword', [SmtpCode::AUTH_OK], 'credentials'),
				'235 2.0.0 OK',
			],
			[
				new AsyncSingleResponseMessage('MAIL FROM:<test@slevomat.cz>', [SmtpCode::OK]),
				'250 2.1.0 Ok',
			],
			[
				new AsyncSingleResponseMessage('RCPT TO:<dariel@email.cz>', [SmtpCode::OK]),
				'250 2.1.0 Ok',
			],
			[
				new AsyncSingleResponseMessage('DATA', [SmtpCode::START_MAIL]),
				'354 Go ahead',
			],
			[
				new AsyncSingleResponseMessage('.', [SmtpCode::OK]),
				'250 2.0.0 Ok: queued',
			],
		];
	}

	/**
	 * @dataProvider dataSuccessfulWrites
	 */
	public function testSuccessfulWrites(
		AsyncMessage $message,
		?string $actualFirstResponse = null,
		?string $actualSecondResponse = null
	): void
	{
		$writer = new AsyncSmtpConnectionWriter($this->createConnectionMock($message->getText()), $this->logger);
		$writer->write($message)->done(
			function (): void {
				$this->exception = false;
			},
			function (\Throwable $exception): void {
				$this->exception = $exception;
			}
		);

		$this->runSuccessfulTest(
			$actualFirstResponse,
			$actualSecondResponse
		);
	}

	/**
	 * @return mixed[][]
	 */
	public function dataFailedWrites(): array
	{
		return [
			//invalid first response
			[
				new AsyncDoubleResponseMessage('EHLO slevomat.local', [SmtpCode::SERVICE_READY], [SmtpCode::OK]),
				'421 Service not available',
				null,
				'SMTP server didn\'t accept message EHLO slevomat.local. Expected code: 220. Actual code: 421.',
			],
			//invalid second response
			[
				new AsyncDoubleResponseMessage('EHLO slevomat.local', [SmtpCode::SERVICE_READY], [SmtpCode::OK]),
				'220 mailtrap.io ESMTP ready',
				'421 Service not available',
				'SMTP server didn\'t accept message EHLO slevomat.local. Expected code: 250. Actual code: 421.',
			],
			[
				new AsyncSingleResponseMessage('AUTH LOGIN', [SmtpCode::AUTH_CONTINUE]),
				'421 Service not available',
				null,
				'SMTP server didn\'t accept message AUTH LOGIN. Expected code: 334. Actual code: 421.',
			],
			[
				new AsyncSingleResponseMessage('AUTH LOGIN', [SmtpCode::AUTH_CONTINUE]),
				'unexpectedResponseFormat',
				null,
				'Unexpected response format: unexpectedResponseFormat.',
			],
			//hiding credentials in exception message
			[
				new AsyncSingleResponseMessage('base64EncodedUsername', [SmtpCode::AUTH_CONTINUE], 'credentials'),
				'421 Service not available',
				null,
				'SMTP server didn\'t accept credentials. Expected code: 334. Actual code: 421.',
			],
		];
	}

	/**
	 * @dataProvider dataFailedWrites
	 */
	public function testFailedWrites(
		AsyncMessage $message,
		?string $actualFirstResponse = null,
		?string $actualSecondResponse = null,
		?string $expectedExceptionMessage = null
	): void
	{
		$writer = new AsyncSmtpConnectionWriter($this->createConnectionMock($message->getText()), $this->logger);
		$writer->write($message)
			->done(
				function (): void {
					$this->exception = false;
				},
				function (\Throwable $exception): void {
					$this->exception = $exception;
				}
			);

		$this->runFailedTest(
			$actualFirstResponse,
			$actualSecondResponse,
			$expectedExceptionMessage
		);
	}

	private function createConnectionMock(?string $message = null): \React\Socket\ConnectionInterface
	{
		$connectionMock = $this->createMock(\React\Socket\ConnectionInterface::class);
		$connectionMock->method('isReadable')->willReturn(true);
		$connectionMock->method('isWritable')->willReturn(true);
		if ($message !== null) {
			$connectionMock->expects($this->once())
				->method('write')
				->with($message . \Nette\Mail\Message::EOL);
		}
		$connectionMock->method('on')
			->willReturnCallback(function ($type, $closure): void {
				if ($type === 'data') {
					$this->doOnData = $closure;
				} elseif ($type === 'end') {
					$this->doOnEnd = $closure;
				} elseif ($type === 'close') {
					$this->doOnClose = $closure;
				} elseif ($type === 'error') {
					$this->doOnError = $closure;
				} else {
					die('unexpected value' . $type);
				}
			}
		);

		return $connectionMock;
	}

	private function runSuccessfulTest(
		string $firstResponse,
		?string $secondResponse = null
	): void
	{
		$startTime = time();
		$this->loop->addPeriodicTimer(self::DEFAULT_INTERVAL_IN_SECONDS, function () use ($startTime, $firstResponse, $secondResponse): void {
			if (time() - $startTime > self::MAX_LOOP_EXECUTION_TIME) {
				$this->loop->stop();
				throw new \Exception('Max loop running execution time exceeded.');
			}
			if (count($this->serverResponses) === 0) {
				$this->serverResponses[] = $firstResponse;
				($this->doOnData)($firstResponse);
			}
			if (count($this->serverResponses) === 1 && $secondResponse !== null) {
				$this->serverResponses[] = $secondResponse;
				($this->doOnData)($secondResponse);
			}
			if ($this->exception !== null) {
				$this->loop->stop();
				if ($this->exception instanceof \Throwable) {
					throw $this->exception;

				} else {
					$this->assertTrue(true);
				}
			}
		});

		$this->loop->run();
	}

	private function runFailedTest(
		?string $firstResponse = null,
		?string $secondResponse = null,
		?string $expectedErrorMessage = null,
		?string $expectedPreviousErrorMessage = null
	): void
	{
		$startTime = time();
		$this->loop->addPeriodicTimer(self::DEFAULT_INTERVAL_IN_SECONDS, function ()
 use ($startTime, $firstResponse, $secondResponse, $expectedErrorMessage, $expectedPreviousErrorMessage): void {
			if (time() - $startTime > self::MAX_LOOP_EXECUTION_TIME) {
				$this->loop->stop();
				throw new \Exception('Max loop running execution time exceeded.');
			}
			if (count($this->serverResponses) === 0 && $firstResponse !== null) {
				$this->serverResponses[] = $firstResponse;
				($this->doOnData)($firstResponse);
			}
			if (count($this->serverResponses) === 1 && $secondResponse !== null) {
				$this->serverResponses[] = $secondResponse;
				($this->doOnData)($secondResponse);
			}
			if ($this->exception !== null) {
				$this->loop->stop();
				if ($this->exception instanceof \Throwable) {
					if ($expectedErrorMessage !== null) {
						$this->assertSame($expectedErrorMessage, $this->exception->getMessage());
						if ($expectedPreviousErrorMessage !== null) {
							$this->assertSame($expectedPreviousErrorMessage, $this->exception->getPrevious()->getMessage());
						}

					} else {
						$this->assertTrue(true);
					}

				} else {
					throw new \Exception('Exception was not thrown.');
				}
			}
		});

		$this->loop->run();
	}

}
