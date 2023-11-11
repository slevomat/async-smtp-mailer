<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncMessage;
use AsyncConnection\AsyncTestTrait;
use AsyncConnection\TestCase;
use Exception;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ConnectorInterface;
use Throwable;
use function React\Promise\reject;
use function React\Promise\resolve;

class AsyncSmtpConnectorTest extends TestCase
{

	use AsyncTestTrait;

	private const INVALID_USERNAME_MESSAGE = 'Invalid username';
	private const INVALID_PASSWORD_MESSAGE = 'Invalid password';
	private const INVALID_CONNECTION_MESSAGE = 'Connection failed';
	private const INVALID_GREETING_MESSAGE = 'Greeting failed';

	private LoopInterface $loop;

	private bool $heloMessageWasSent;

	private bool $authLoginWasSent;

	private bool $usernameWasSent;

	protected function setUp(): void
	{
		$this->loop = Factory::create();
		$this->heloMessageWasSent = false;
		$this->authLoginWasSent = false;
		$this->usernameWasSent = false;
	}

	public function testHeloMessageIsUsedWhenEhloMessageIsRejected(): void
	{
		$this->createConnectorAndConnect();
		$this->runSuccessfulTest(
			$this->loop,
			function (): void {
				$this->assertTrue($this->heloMessageWasSent);
			},
		);
	}

	public function testConnectionFailureReturnsRejectedPromise(): void
	{
		$this->createConnectorAndConnect(true);
		$this->runFailedTest($this->loop, function (Throwable $e): void {
			$this->assertSame(self::INVALID_CONNECTION_MESSAGE, $e->getMessage());
		});
	}

	public function testGreetingFailureReturnsRejectedPromise(): void
	{
		$this->createConnectorAndConnect(false, true);
		$this->runFailedTest($this->loop, function (Throwable $e): void {
			$this->assertSame(self::INVALID_GREETING_MESSAGE, $e->getMessage());
		});
	}

	public function testInvalidUsernameReturnsRejectedPromise(): void
	{
		$this->createConnectorAndConnect(false, false, true);
		$this->runFailedTest($this->loop, function (Throwable $e): void {
			$this->assertSame(self::INVALID_USERNAME_MESSAGE, $e->getMessage());
		});
	}

	public function testInvalidPasswordReturnsRejectedPromise(): void
	{
		$this->createConnectorAndConnect(false, false, false, true);
		$this->runFailedTest($this->loop, function (Throwable $e): void {
			$this->assertSame(self::INVALID_PASSWORD_MESSAGE, $e->getMessage());
		});
	}

	private function createConnectorAndConnect(
		bool $connectionShouldFail = false,
		bool $greetingShouldFail = false,
		bool $usernameIsInvalid = false,
		bool $passwordIsInvalid = false
	): void
	{
		$connector = $this->createConnector($connectionShouldFail, $greetingShouldFail, $usernameIsInvalid, $passwordIsInvalid);
		$connector->connect()->then(
			function (): void {
				$this->setException(false);
			},
			function (Throwable $e): void {
				$this->setException($e);
			},
		);
	}

	private function createConnector(
		bool $connectionShouldFail = false,
		bool $greetingShouldFail = false,
		bool $usernameIsInvalid = false,
		bool $passwordIsInvalid = false
	): AsyncSmtpConnector
	{
		$connectorMock = $this->createMock(ConnectorInterface::class);
		$connectionMock = $this->createMock(ConnectionInterface::class);
		$writerMock = $this->createMock(AsyncSmtpConnectionWriter::class);
		$writerFactoryMock = $this->createMock(AsyncSmtpConnectionWriterFactory::class);

		$connectionResult = $connectionShouldFail
			? reject(new Exception(self::INVALID_CONNECTION_MESSAGE))
			: resolve($connectionMock);
		$connectorMock->method('connect')
			->with('slevomat.cz:25')
			->willReturn($connectionResult);

		$writerFactoryMock->method('create')
			->with($connectionMock)
			->willReturn($writerMock);

		$writerMock->method('write')
			->willReturnCallback(function (AsyncMessage $message) use (
				$greetingShouldFail,
				$usernameIsInvalid,
				$passwordIsInvalid
			): PromiseInterface {
				if ($message->getText() === 'EHLO slevomat.cz') {
					return reject(new AsyncSmtpConnectionException(''));
				}

				if ($message->getText() === 'HELO slevomat.cz') {
					$this->heloMessageWasSent = true;

					return $greetingShouldFail
						? reject(new AsyncSmtpConnectionException(self::INVALID_GREETING_MESSAGE))
						: resolve(null);
				}

				if ($message->getText() === 'AUTH LOGIN') {
					$this->authLoginWasSent = true;

				} elseif ($this->authLoginWasSent) {
					if (!$this->usernameWasSent) {
						$this->usernameWasSent = true;

						return $usernameIsInvalid
							? reject(new AsyncSmtpConnectionException(self::INVALID_USERNAME_MESSAGE))
							: resolve(null);
					}

					return $passwordIsInvalid
						? reject(new AsyncSmtpConnectionException(self::INVALID_PASSWORD_MESSAGE))
						: resolve(null);
				}

				return resolve(null);
			});

		return new AsyncSmtpConnector(
			$writerFactoryMock,
			$connectorMock,
			new SmtpSettings(
				'slevomat.cz',
				25,
				'slevomat.cz',
				'username',
				'password',
			),
		);
	}

}
