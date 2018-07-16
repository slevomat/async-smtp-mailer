<?php declare(strict_types = 1);

namespace AsyncConnection;

trait AsyncTestTrait
{

	/** @var \Throwable|null|false */
	private $exception = null;

	/** @var bool */
	private $ignoreTimeoutErrors = false;

	/** @var int|null */
	private $customMaxLoopExecutionTime;

	/** @var int|null */
	private $customTimerInterval;

	public function runSuccessfulTest(
		\React\EventLoop\LoopInterface $loop,
		?\Closure $assertOnSuccess = null
	): void
	{
		$startTime = time();
		$loop->addPeriodicTimer($this->getTimerInterval(), function () use ($startTime, $assertOnSuccess, $loop): void {
			$this->checkLoopExecutionTime($loop, $startTime);

			if ($this->exception === null) {
				return;
			}

			$loop->stop();
			if ($this->exception instanceof \Throwable) {
				if ($this->exception instanceof \AsyncConnection\AsyncConnectionTimeoutException
					&& $this->ignoreTimeoutErrors
				) {
					return;
				}
				throw $this->exception;

			} elseif ($assertOnSuccess !== null) {
				$assertOnSuccess();

			} else {
				$this->assertTrue(true);
			}
		});
		$loop->run();
	}

	public function runFailedTest(
		\React\EventLoop\LoopInterface $loop,
		?\Closure $assertOnFail = null,
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

			} elseif ($this->exception instanceof \AsyncConnection\AsyncConnectionTimeoutException
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
	 * @param \Throwable|null|false $exception
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

	public function getLogger(): \Psr\Log\LoggerInterface
	{
		return new \Psr\Log\NullLogger();
	}

	private function checkLoopExecutionTime(
		\React\EventLoop\LoopInterface $loop,
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
