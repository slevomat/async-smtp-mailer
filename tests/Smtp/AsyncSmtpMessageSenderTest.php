<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncMessage;
use AsyncConnection\AsyncTestTrait;
use AsyncConnection\TestCase;
use Closure;
use Nette\Utils\Strings;
use PHPUnit_Framework_MockObject_MockObject;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Promise\ExtendedPromiseInterface;
use Throwable;
use function React\Promise\reject;
use function React\Promise\resolve;

class AsyncSmtpMessageSenderTest extends TestCase
{

	use AsyncTestTrait;

	/** @var AsyncSmtpConnectionWriter|PHPUnit_Framework_MockObject_MockObject */
	private $writerMock;

	private LoopInterface $loop;

	/** @var string[] */
	private array $recipients = [];

	protected function setUp(): void
	{
		/** @var AsyncSmtpConnectionWriter|PHPUnit_Framework_MockObject_MockObject $writerMock */
		$writerMock = $this->createMock(AsyncSmtpConnectionWriter::class);
		$writerMock->method('isValid')->willReturn(true);
		$this->writerMock = $writerMock;
		$this->loop = Factory::create();
	}

	public function testSuccessfulSendingReturnsPromise(): void
	{
		$this->writerMock->method('write')
			->willReturn(resolve());

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
	 *
	 * @param string $messageToFail
	 */
	public function testFailedSendingThrowsException(string $messageToFail): void
	{
		$this->writerMock->method('write')
			->willReturnCallback(static fn (AsyncMessage $message) => Strings::startsWith($message->getText(), $messageToFail)
					? reject(new AsyncSmtpConnectionException('Sending failed'))
					: resolve());

		$assertOnFail = function (Throwable $exception): void {
			$this->assertInstanceOf(AsyncSmtpConnectionException::class, $exception);
			$this->assertSame('Sending failed', $exception->getMessage());
		};

		$this->runFailedSendingTest(
			$this->createMessage(),
			'Failed sending returned resolved promise.',
			$assertOnFail,
		);
	}

	public function testMultipleRecipients(): void
	{
		$this->writerMock->method('write')
			->will($this->returnCallback(function (AsyncMessage $message): ExtendedPromiseInterface {
				$matches = Strings::match($message->getText(), '~RCPT TO:\s?\<(?<recipient>[^>]+)\>~i');
				if ($matches !== null) {
					$this->recipients[] = $matches['recipient'];
				}

				return resolve();
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
		?Closure $assertOnSuccess = null
	): void
	{
		$sender = new AsyncSmtpMessageSender();
		$sender->sendMessage($this->writerMock, $message)
			->done(
				function (): void {
					$this->setException(false);
				},
				function (Throwable $exception): void {
					$this->setException($exception);
				},
			);

		$this->runSuccessfulTest($this->loop, $assertOnSuccess);
	}

	private function runFailedSendingTest(
		MailMessage $message,
		string $errorMessage,
		Closure $assertOnFail
	): void
	{
		$sender = new AsyncSmtpMessageSender();
		$sender->sendMessage($this->writerMock, $message)
			->done(
				function (): void {
					$this->setException(false);
				},
				function (Throwable $exception): void {
					$this->setException($exception);
				},
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
