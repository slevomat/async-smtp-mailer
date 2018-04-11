<?php declare(strict_types = 1);

// spell-check-ignore: helo HELO EHLO

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncMessage;

class AsyncSmtpConnectorTest extends \AsyncConnection\TestCase
{

	use \AsyncConnection\AsyncTestTrait;

	private const INVALID_USERNAME_MESSAGE = 'Invalid username';
	private const INVALID_PASSWORD_MESSAGE = 'Invalid password';
	private const INVALID_CONNECTION_MESSAGE = 'Connection failed';
	private const INVALID_GREETING_MESSAGE = 'Greeting failed';

	/** @var \React\EventLoop\LoopInterface */
	private $loop;

	/** @var bool */
	private $heloMessageWasSent;

	/** @var bool */
	private $authLoginWasSent;

	/** @var bool */
	private $usernameWasSent;

	protected function setUp(): void
	{
		$this->loop = \React\EventLoop\Factory::create();
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
			}
		);
	}

	public function testConnectionFailureReturnsRejectedPromise(): void
	{
		$this->createConnectorAndConnect(true);
		$this->runFailedTest($this->loop, function (\Throwable $e): void {
			$this->assertSame(self::INVALID_CONNECTION_MESSAGE, $e->getMessage());
		});
	}

	public function testGreetingFailureReturnsRejectedPromise(): void
	{
		$this->createConnectorAndConnect(false, true);
		$this->runFailedTest($this->loop, function (\Throwable $e): void {
			$this->assertSame(self::INVALID_GREETING_MESSAGE, $e->getMessage());
		});
	}

	public function testInvalidUsernameReturnsRejectedPromise(): void
	{
		$this->createConnectorAndConnect(false, false, true);
		$this->runFailedTest($this->loop, function (\Throwable $e): void {
			$this->assertSame(self::INVALID_USERNAME_MESSAGE, $e->getMessage());
		});
	}

	public function testInvalidPasswordReturnsRejectedPromise(): void
	{
		$this->createConnectorAndConnect(false, false, false, true);
		$this->runFailedTest($this->loop, function (\Throwable $e): void {
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
			function (\Throwable $e): void {
				$this->setException($e);
			}
		);
	}

	private function createConnector(
		bool $connectionShouldFail = false,
		bool $greetingShouldFail = false,
		bool $usernameIsInvalid = false,
		bool $passwordIsInvalid = false
	): AsyncSmtpConnector
	{
		$connectorMock = $this->createMock(\React\Socket\ConnectorInterface::class);
		$connectionMock = $this->createMock(\React\Socket\ConnectionInterface::class);
		$writerMock = $this->createMock(AsyncSmtpConnectionWriter::class);
		$writerFactoryMock = $this->createMock(AsyncSmtpConnectionWriterFactory::class);

		$connectionResult = $connectionShouldFail
			? \React\Promise\reject(new \Exception(self::INVALID_CONNECTION_MESSAGE))
			: \React\Promise\resolve($connectionMock);
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
			): \React\Promise\ExtendedPromiseInterface {
				if ($message->getText() === 'EHLO slevomat.cz') {
					return \React\Promise\reject(new \AsyncConnection\Smtp\AsyncSmtpConnectionException(''));

				} elseif ($message->getText() === 'HELO slevomat.cz') {
					$this->heloMessageWasSent = true;

					return $greetingShouldFail
						? \React\Promise\reject(new \AsyncConnection\Smtp\AsyncSmtpConnectionException(self::INVALID_GREETING_MESSAGE))
						: \React\Promise\resolve();
				}

				if ($message->getText() === 'AUTH LOGIN') {
					$this->authLoginWasSent = true;

				} elseif ($this->authLoginWasSent) {
					if (!$this->usernameWasSent) {
						$this->usernameWasSent = true;

						return $usernameIsInvalid
							? \React\Promise\reject(new \AsyncConnection\Smtp\AsyncSmtpConnectionException(self::INVALID_USERNAME_MESSAGE))
							: \React\Promise\resolve();

					}

					return $passwordIsInvalid
						? \React\Promise\reject(new \AsyncConnection\Smtp\AsyncSmtpConnectionException(self::INVALID_PASSWORD_MESSAGE))
						: \React\Promise\resolve();
				}

				return \React\Promise\resolve();
			});

		return new AsyncSmtpConnector(
			$writerFactoryMock,
			$connectorMock,
			new SmtpSettings(
				'slevomat.cz',
				25,
				'slevomat.cz',
				'username',
				'password'
			)
		);
	}

}
