<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncMessage;

class AsyncSmtpMessageSenderTest extends \AsyncConnection\TestCase
{

	use \AsyncConnection\AsyncTestTrait;

	/** @var \AsyncConnection\Smtp\AsyncSmtpConnectionWriter|\PHPUnit_Framework_MockObject_MockObject */
	private $writerMock;

	/** @var \React\EventLoop\LoopInterface */
	private $loop;

	/** @var string[] */
	private $recipients = [];

	public function setUp(): void
	{
		/** @var \AsyncConnection\Smtp\AsyncSmtpConnectionWriter|\PHPUnit_Framework_MockObject_MockObject $writerMock */
		$writerMock = $this->createMock(AsyncSmtpConnectionWriter::class);
		$writerMock->method('isValid')->willReturn(true);
		$this->writerMock = $writerMock;
		$this->loop = \React\EventLoop\Factory::create();
	}

	public function testSuccessfulSendingReturnsPromise(): void
	{
		$this->writerMock->method('write')
			->willReturn(\React\Promise\resolve());

		$this->runSuccessfulSendingTest($this->createMessage());
	}

	/**
	 * @return mixed[][]
	 */
	public function dataFailedSendingThrowsException(): array
	{
		return [
			['MAIL FROM:<test@slevomat.cz>'],
			['RCPT TO:'],
			['DATA'],
			['MIME-Version'],
			['.'],
		];
	}

	/**
	 * @dataProvider dataFailedSendingThrowsException
	 * @param string $messageToFail
	 */
	public function testFailedSendingThrowsException(string $messageToFail): void
	{
		$this->writerMock->method('write')
			->willReturnCallback(function (AsyncMessage $message) use ($messageToFail) {
				return \Nette\Utils\Strings::startsWith($message->getText(), $messageToFail)
					? \React\Promise\reject(new \AsyncConnection\Smtp\AsyncSmtpConnectionException('Sending failed'))
					: \React\Promise\resolve();
			});

		$assertOnFail = function (\Throwable $exception): void {
			$this->assertInstanceOf(\AsyncConnection\Smtp\AsyncSmtpConnectionException::class, $exception);
			$this->assertSame('Sending failed', $exception->getMessage());
		};

		$this->runFailedSendingTest(
			$this->createMessage(),
			'Failed sending returned resolved promise.',
			$assertOnFail
		);
	}

	public function testMultipleRecipients(): void
	{
		$this->writerMock->method('write')
			->will($this->returnCallback(function (AsyncMessage $message): \React\Promise\ExtendedPromiseInterface {
				$matches = \Nette\Utils\Strings::match($message->getText(), '~RCPT TO:\s?\<(?<recipient>[^>]+)\>~i');
				if ($matches !== null) {
					$this->recipients[] = $matches['recipient'];
				}

				return \React\Promise\resolve();
			}));

		$assertOnSuccess = function (): void {
			$this->assertCount(3, $this->recipients);
			$this->assertSame('test@seznam.cz', $this->recipients[0]);
			$this->assertSame('cc@seznam.cz', $this->recipients[1]);
			$this->assertSame('bcc@seznam.cz', $this->recipients[2]);
		};

		$message = $this->createMessage();
		$message->setHeader('Cc', ['cc@seznam.cz' => null]);
		$message->setHeader('Bcc', ['bcc@seznam.cz' => null]);

		$this->runSuccessfulSendingTest($message, $assertOnSuccess);
	}

	private function runSuccessfulSendingTest(
		MailMessage $message,
		?\Closure $assertOnSuccess = null
	): void
	{
		$sender = new AsyncSmtpMessageSender();
		$sender->sendMessage($this->writerMock, $message)
			->done(
				function (): void {
					$this->setException(false);
				},
				function (\Throwable $exception): void {
					$this->setException($exception);
				}
			);

		$this->runSuccessfulTest($this->loop, $assertOnSuccess);
	}

	private function runFailedSendingTest(
		MailMessage $message,
		string $errorMessage,
		\Closure $assertOnFail
	): void
	{
		$sender = new AsyncSmtpMessageSender();
		$sender->sendMessage($this->writerMock, $message)
			->done(
				function (): void {
					$this->setException(false);
				},
				function (\Throwable $exception): void {
					$this->setException($exception);
				}
			);

		$this->runFailedTest($this->loop, $assertOnFail, $errorMessage);
	}

	private function createMessage(?string $subject = 'TEST'): MailMessage
	{
		$message = new MailMessage();
		$message->setFrom('test@slevomat.cz');
		$message->setSubject($subject);
		$message->setHeader('To', ['test@seznam.cz' => null]);
		$message->setBody('AHOJ!');

		return $message;
	}

}
