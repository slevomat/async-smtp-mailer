<?php declare(strict_types = 1);

namespace AsyncConnection\Timer;

class PromiseTimer
{

	/** @var \React\EventLoop\LoopInterface */
	private $loop;

	public function __construct(\React\EventLoop\LoopInterface $loop)
	{
		$this->loop = $loop;
	}

	/**
	 * @param int|float $seconds
	 * @return \React\Promise\ExtendedPromiseInterface
	 */
	public function wait($seconds): \React\Promise\ExtendedPromiseInterface
	{
		$deferred = new \React\Promise\Deferred();
		$this->loop->addTimer($seconds, function () use ($deferred): void {
			$deferred->resolve();
		});

		return $deferred->promise();
	}

}
