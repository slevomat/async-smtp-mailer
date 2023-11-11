<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncMessage;
use AsyncConnection\AsyncTestTrait;
use AsyncConnection\TestCase;
use Closure;
use Nette\Utils\Strings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use Throwable;
use function React\Promise\reject;
use function React\Promise\resolve;

class AsyncSmtpMessageSenderTest extends TestCase
{

	use AsyncTestTrait;

	/** @var AsyncSmtpConnectionWriter|MockObject */
	private $writerMock;

	private LoopInterface $loop;

	/** @var array<string> */
	private array $recipients = [];

	protected function setUp(): void
	{
		/** @var AsyncSmtpConnectionWriter|MockObject $writerMock */
		$writerMock = $this->createMock(AsyncSmtpConnectionWriter::class);
		$writerMock->method('isValid')->willReturn(true);
		$this->writerMock = $writerMock;
		$this->loop = Factory::create();
	}

	public function testSuccessfulSendingReturnsPromise(): void
	{
		$this->writerMock->method('write')
			->willReturn(resolve(null));

		$this->runSuccessfulSendingTest($this->createMessage());
	}

	/**
	 * @return list<array{0: string}>
	 */
	public static function dataFailedSendingThrowsException(): array
	{
		return [
			['MAIL FROM:<test@slevomat.cz>'],
			['RCPT TO:'],
			['DATA'],
			['MIME-Version'],
			['.'],
		];
	}

	#[DataProvider('dataFailedSendingThrowsException')]
	public function testFailedSendingThrowsException(string $messageToFail): void
	{
		$this->writerMock->method('write')
			->willReturnCallback(static fn (AsyncMessage $message) => Strings::startsWith($message->getText(), $messageToFail)
					? reject(new AsyncSmtpConnectionException('Sending failed'))
					: resolve(null));

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
			->will($this->returnCallback(function (AsyncMessage $message): PromiseInterface {
				$matches = Strings::match($message->getText(), '~RCPT TO:\s?\<(?<recipient>[^>]+)\>~i');
				if ($matches !== null) {
					$this->recipients[] = $matches['recipient'];
				}

				return resolve(null);
			}));

		$assertOnSuccess = function (): void {
			$this->assertCount(6, $this->recipients);
			$this->assertSame('aa@seznam.cz', $this->recipients[0]);
			$this->assertSame('bb@seznam.cz', $this->recipients[1]);
			$this->assertSame('cc@seznam.cz', $this->recipients[2]);
			$this->assertSame('dd@seznam.cz', $this->recipients[3]);
			$this->assertSame('ee@seznam.cz', $this->recipients[4]);
			$this->assertSame('ff@seznam.cz', $this->recipients[5]);
		};

		$message = $this->createMessage();
		$message->setHeader('To', ['aa@seznam.cz' => null, 'bb@seznam.cz' => null]);
		$message->setHeader('Cc', ['cc@seznam.cz' => null, 'dd@seznam.cz' => null]);
		$message->setHeader('Bcc', ['ee@seznam.cz' => null, 'ff@seznam.cz' => null]);

		$this->runSuccessfulSendingTest($message, $assertOnSuccess);
	}

	private function runSuccessfulSendingTest(
		MailMessage $message,
		?Closure $assertOnSuccess = null
	): void
	{
		$sender = new AsyncSmtpMessageSender();
		$sender->sendMessage($this->writerMock, $message)
			->then(
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
			->then(
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
