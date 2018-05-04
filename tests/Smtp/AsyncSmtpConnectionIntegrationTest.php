<?php declare(strict_types = 1);

// spell-check-ignore: blabla getaddresses getaddrinfo

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncConnectionManager;
use AsyncConnection\Connector\ConnectorFactory;

class AsyncSmtpConnectionIntegrationTest extends \AsyncConnection\IntegrationTestCase
{

	use \AsyncConnection\AsyncTestTrait;

	/** @var \React\EventLoop\LoopInterface */
	private $loop;

	/** @var \AsyncConnection\Log\DumpLogger */
	private $logger;

	protected function setUp(): void
	{
		$settings = $this->getSettings();
		if ($settings->shouldSkipIntegrationTests()) {
			$this->markTestSkipped();
			return;
		}
		$this->loop = \React\EventLoop\Factory::create();
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

		$assertOnFail = function (\Throwable $exception): void {
			$this->assertInstanceOf(\AsyncConnection\AsyncConnectionException::class, $exception);
			$this->assertStringStartsWith('SMTP server did not accept credentials.', $exception->getMessage());
		};

		$this->failedConnectionTest($settings, 'Connection with invalid password was successful.', $assertOnFail);
	}

	public function testInvalidUsername(): void
	{
		$settings = $this->getSettings()->getSmtpSettings();
		$settings->updateUsername('blabla');

		$assertOnFail = function (\Throwable $exception): void {
			$this->assertInstanceOf(\AsyncConnection\AsyncConnectionException::class, $exception);
			$this->assertStringStartsWith('SMTP server did not accept credentials.', $exception->getMessage());
		};

		$this->failedConnectionTest($settings, 'Connection with invalid username was successful.', $assertOnFail);
	}

	public function testInvalidHostName(): void
	{
		$settings = $this->getSettings()->getSmtpSettings();
		$settings->updateHost('nonexistent.domain');

		$assertOnFail = function (\Throwable $exception) use ($settings): void {
			$this->assertInstanceOf(\RuntimeException::class, $exception);
			$this->assertStringStartsWith(
				sprintf(
					'Connection to %s:%s failed: php_network_getaddresses: getaddrinfo failed',
					$settings->getHost(),
					$settings->getPort()
				),
				$exception->getMessage()
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

	private function successfulConnectionTest(SmtpSettings $settings): void
	{
		$connection = $this->createConnectionManager($settings);
		$connection->connect()->done(
			function (): void {
				$this->setException(false);
			},
			function (\Throwable $e): void {
				$this->setException($e);
			}
		);

		$this->runSuccessfulTest($this->loop);
	}

	private function failedConnectionTest(
		SmtpSettings $settings,
		string $errorMessage,
		\Closure $assertOnFail
	): void
	{
		$connection = $this->createConnectionManager($settings);
		$connection->connect()->done(
			function (): void {
				$this->setException(false);
			},
			function (\Throwable $e): void {
				$this->setException($e);
			}
		);

		$this->runFailedTest($this->loop, $assertOnFail, $errorMessage);
	}

	private function createConnectionManager(SmtpSettings $settings): AsyncConnectionManager
	{
		$factory = new AsyncSmtpConnectionManagerFactory(
			new AsyncSmtpConnectionWriterFactory($this->logger),
			new ConnectorFactory($this->loop, false),
			$this->logger,
			$settings
		);

		return $factory->create();
	}

}
