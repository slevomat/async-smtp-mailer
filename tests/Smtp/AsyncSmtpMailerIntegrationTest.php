<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncConnectionTimeoutException;
use AsyncConnection\AsyncMessageQueueManager;
use AsyncConnection\AsyncTestTrait;
use AsyncConnection\Connector\ConnectorFactory;
use AsyncConnection\IntegrationTestCase;
use AsyncConnection\Timer\PromiseTimer;
use Exception;
use Psr\Log\LoggerInterface;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\Timer;
use Throwable;
use function array_filter;
use function count;
use function date;
use function imap_close;
use function imap_fetch_overview;
use function imap_last_error;
use function imap_open;
use function imap_search;
use function sprintf;
use function time;
use const FT_UID;
use const SE_UID;

class AsyncSmtpMailerIntegrationTest extends IntegrationTestCase
{

	use AsyncTestTrait;

	private const MAX_LOOP_EXECUTION_TIME = 30;

	private const WAIT_INTERVAL_IN_SECONDS = 20;

	/** @var Throwable|false|null */
	private $exception;

	private LoopInterface $loop;

	private LoggerInterface $logger;

	protected function setUp(): void
	{
		$settings = $this->getSettings();
		if ($settings->shouldSkipIntegrationTests()) {
			$this->markTestSkipped();

			return;
		}
		if ($settings->getTestInboxSettings() === null) {
			throw new Exception('missing testInboxSettings');
		}
		$this->logger = $this->getLogger();
		$this->ignoreTimeoutErrors = $settings->shouldIgnoreTimeoutErrors();
		$this->exception = null;
		$this->loop = Factory::create();
	}

	public function testSending(): void
	{
		$manager = $this->getManager();

		$time = time();
		$subject = sprintf('TEST SMTP %d', $time);

		$message = $this->getMessage($subject);
		$manager->send($message)->done(
			function (): void {
				$this->exception = false;
			},
			function (Throwable $e): void {
				$this->exception = $e;
			},
		);

		$this->addEmailCheckingTimer($time, $subject);

		$this->loop->run();
	}

	private function getManager(): AsyncMessageQueueManager
	{
		$managerFactory = new AsyncSmtpConnectionManagerFactory(
			new AsyncSmtpConnectionWriterFactory($this->logger),
			new ConnectorFactory($this->loop, false),
			$this->logger,
			$this->getSettings()->getSmtpSettings(),
		);

		$sender = new AsyncSmtpMessageSender();

		return new AsyncMessageQueueManager(
			$sender,
			$managerFactory->create(),
			$this->logger,
			new PromiseTimer($this->loop),
		);
	}

	private function getMessage(string $subject): MailMessage
	{
		$settings = $this->getSettings();

		$message = new MailMessage();
		$message->setFrom($settings->getEmailFrom());
		$message->setSubject($subject);
		$message->setHeader('To', [$settings->getTestInboxSettings()->getUsername() => null]);
		$message->setBody('HI THERE!');

		return $message;
	}

	private function addEmailCheckingTimer(
		int $time,
		string $subject,
		?int $waitingInterval = null
	): void
	{
		$startTime = time();
		$this->loop->addPeriodicTimer(
			1,
			function (Timer $timer) use ($time, $subject, $startTime, $waitingInterval): void {
				if (time() - $startTime > self::MAX_LOOP_EXECUTION_TIME) {
					$this->loop->stop();

					throw new Exception('Max loop running execution time exceeded.');
				}
				if ($this->exception === null) {
					return;
				}

				if ($this->exception instanceof Throwable) {
					$this->loop->stop();
					if ($this->exception instanceof AsyncConnectionTimeoutException
						&& $this->ignoreTimeoutErrors) {
						return;
					}

					throw $this->exception;
				}

				if ($this->exception !== false) {
					return;
				}

				$this->loop->cancelTimer($timer);
				$waitingInterval ??= self::WAIT_INTERVAL_IN_SECONDS;
				$this->loop->addTimer($waitingInterval, function () use ($time, $subject): void {
					$this->loop->stop();
					$settings = $this->getSettings();
					$inboxSettings = $settings->getTestInboxSettings();

					$imap = imap_open(
						$inboxSettings->getMailbox(),
						$inboxSettings->getUsername(),
						$inboxSettings->getPassword(),
					);
					if ($imap === false) {
						$error = imap_last_error();

						throw new Exception($error !== false ? $error : 'IMAP error occured.');
					}
					$searchQuery = sprintf('SUBJECT "%s" SINCE "%s" FROM "%s"', $time, date('Y-m-d', $time), $settings->getEmailFrom());
					$emails = imap_search($imap, $searchQuery, SE_UID);
					if ($emails === false) {
						imap_close($imap);
						$this->fail(sprintf('Message %s was not found in inbox', $subject));
					}
					$emails = array_filter($emails);
					if (count($emails) !== 1) {
						imap_close($imap);
						$this->fail(sprintf('Message %s was not found in inbox', $subject));
					}

					$overview = imap_fetch_overview($imap, (string) $emails[0], FT_UID);
					imap_close($imap);

					$this->assertCount(1, $emails, 'Message was not found in inbox');
					$this->assertSame($subject, $overview[0]->subject, 'Message was not found in inbox');
					$this->assertSame($settings->getEmailFrom(), $overview[0]->from);
				});
			},
		);
	}

}
