<?php declare(strict_types = 1);

// spell-check-ignore: blabla getaddresses getaddrinfo

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncConnectionException;
use AsyncConnection\AsyncConnectionManager;
use AsyncConnection\AsyncTestTrait;
use AsyncConnection\Connector\ConnectorFactory;
use AsyncConnection\IntegrationTestCase;
use Closure;
use Psr\Log\LoggerInterface;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use RuntimeException;
use Throwable;
use function sprintf;

class AsyncSmtpConnectionIntegrationTest extends IntegrationTestCase
{

	use AsyncTestTrait;

	private LoopInterface $loop;

	private LoggerInterface $logger;

	protected function setUp(): void
	{
		$settings = $this->getSettings();
		if ($settings->shouldSkipIntegrationTests()) {
			$this->markTestSkipped();
		}

		$this->loop = Factory::create();
		$this->logger = $this->getLogger();
		$this->ignoreTimeoutErrors($settings->shouldIgnoreTimeoutErrors());
	}

	public function testConnection(): void
	{
		$settings = $this->getSettings()->getSmtpSettings();

		$this->successfulConnectionTest($settings);
	}

	public function testInvalidPassword(): void
	{
		$settings = $this->getSettings()->getSmtpSettings();
		$settings->updatePassword('blabla');

		$assertOnFail = function (Throwable $exception): void {
			$this->assertInstanceOf(AsyncConnectionException::class, $exception);
			$this->assertStringStartsWith('SMTP server did not accept credentials.', $exception->getMessage());
		};

		$this->failedConnectionTest($settings, 'Connection with invalid password was successful.', $assertOnFail);
	}

	public function testInvalidUsername(): void
	{
		$settings = $this->getSettings()->getSmtpSettings();
		$settings->updateUsername('blabla');

		$assertOnFail = function (Throwable $exception): void {
			$this->assertInstanceOf(AsyncConnectionException::class, $exception);
			$this->assertStringStartsWith('SMTP server did not accept credentials.', $exception->getMessage());
		};

		$this->failedConnectionTest($settings, 'Connection with invalid username was successful.', $assertOnFail);
	}

	public function testInvalidHostName(): void
	{
		$settings = $this->getSettings()->getSmtpSettings();
		$settings->updateHost('nonexistent.domain');

		$assertOnFail = function (Throwable $exception) use ($settings): void {
			$this->assertInstanceOf(RuntimeException::class, $exception);
			$this->assertStringStartsWith(
				sprintf(
					'Connection to %1$s:%2$s failed: php_network_getaddresses: getaddrinfo for %1$s failed: Name or service not known',
					$settings->getHost(),
					$settings->getPort(),
				),
				$exception->getMessage(),
			);
		};

		$this->failedConnectionTest($settings, 'Connection with invalid host name was successful.', $assertOnFail);
	}

	public function testSubsequentConnectionRequests(): void
	{
		$settings = $this->getSettings()->getSmtpSettings();

		$connection = $this->createConnectionManager($settings);
		$connection->connect()->done(
			function () use ($connection): void {
				$connection->connect()->done(
					function (): void {
						$this->setException(false);
					},
					function (Throwable $e): void {
						$this->setException($e);
					},
				);
			},
			function (Throwable $e): void {
				$this->setException($e);
			},
		);

		$this->runSuccessfulTest($this->loop);
	}

	private function successfulConnectionTest(SmtpSettings $settings): void
	{
		$connection = $this->createConnectionManager($settings);
		$connection->connect()->done(
			function (): void {
				$this->setException(false);
			},
			function (Throwable $e): void {
				$this->setException($e);
			},
		);

		$this->runSuccessfulTest($this->loop);
	}

	private function failedConnectionTest(
		SmtpSettings $settings,
		string $errorMessage,
		Closure $assertOnFail
	): void
	{
		$connection = $this->createConnectionManager($settings);
		$connection->connect()->done(
			function (): void {
				$this->setException(false);
			},
			function (Throwable $e): void {
				$this->setException($e);
			},
		);

		$this->runFailedTest($this->loop, $assertOnFail, $errorMessage);
	}

	private function createConnectionManager(SmtpSettings $settings): AsyncConnectionManager
	{
		$factory = new AsyncSmtpConnectionManagerFactory(
			new AsyncSmtpConnectionWriterFactory($this->logger),
			new ConnectorFactory($this->loop, false),
			$this->logger,
			$settings,
		);

		return $factory->create();
	}

}
