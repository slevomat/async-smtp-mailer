<?php declare(strict_types = 1);

namespace AsyncConnection;

use Closure;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\EventLoop\LoopInterface;
use Throwable;
use function sprintf;
use function time;

trait AsyncTestTrait
{

	/** @var Throwable|false|null */
	private $exception = null;

	private bool $ignoreTimeoutErrors = false;

	private ?int $customMaxLoopExecutionTime = null;

	private ?int $customTimerInterval = null;

	public function runSuccessfulTest(
		LoopInterface $loop,
		?Closure $assertOnSuccess = null
	): void
	{
		$startTime = time();
		$loop->addPeriodicTimer($this->getTimerInterval(), function () use ($startTime, $assertOnSuccess, $loop): void {
			$this->checkLoopExecutionTime($loop, $startTime);

			if ($this->exception === null) {
				return;
			}

			$loop->stop();
			if ($this->exception instanceof Throwable) {
				if ($this->exception instanceof AsyncConnectionTimeoutException
					&& $this->ignoreTimeoutErrors
				) {
					return;
				}

				throw $this->exception;
			}

			if ($assertOnSuccess !== null) {
				$assertOnSuccess();

			} else {
				$this->assertTrue(true);
			}
		});
		$loop->run();
	}

	public function runFailedTest(
		LoopInterface $loop,
		?Closure $assertOnFail = null,
		?string $errorMessage = null
	): void
	{
		$startTime = time();
		$loop->addPeriodicTimer($this->getTimerInterval(), function () use ($assertOnFail, $errorMessage, $startTime, $loop): void {
			$this->checkLoopExecutionTime($loop, $startTime);

			if ($this->exception === null) {
				return;
			}

			$loop->stop();
			if ($this->exception === false) {
				$this->fail($errorMessage ?? 'No exception was thrown');

			} elseif ($this->exception instanceof AsyncConnectionTimeoutException
				&& $this->ignoreTimeoutErrors
			) {
				return;
			} elseif ($assertOnFail !== null) {
				$assertOnFail($this->exception);

			} else {
				$this->assertTrue(true);
			}
		});
		$loop->run();
	}

	/**
	 * @param Throwable|false|null $exception
	 */
	public function setException($exception): void
	{
		$this->exception = $exception;
	}

	public function ignoreTimeoutErrors(bool $value): void
	{
		$this->ignoreTimeoutErrors = $value;
	}

	public function getTimerInterval(): int
	{
		return $this->customTimerInterval ?? 1;
	}

	public function getMaxLoopExecutionTime(): int
	{
		return $this->customMaxLoopExecutionTime ?? 15;
	}

	public function getLogger(): LoggerInterface
	{
		return new NullLogger();
	}

	private function checkLoopExecutionTime(
		LoopInterface $loop,
		int $startTime
	): void
	{
		$maxLoopExecutionTime = $this->getMaxLoopExecutionTime();
		if (time() - $startTime <= $maxLoopExecutionTime) {
			return;
		}

		$loop->stop();
		$this->fail(sprintf('Max loop running execution time of %ds exceeded.', $maxLoopExecutionTime));
	}

}
