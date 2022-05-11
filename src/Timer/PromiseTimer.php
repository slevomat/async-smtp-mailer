<?php declare(strict_types = 1);

namespace AsyncConnection\Timer;

use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\ExtendedPromiseInterface;

class PromiseTimer
{

	private LoopInterface $loop;

	public function __construct(LoopInterface $loop)
	{
		$this->loop = $loop;
	}

	/**
	 * @param int|float $seconds
	 */
	public function wait($seconds): ExtendedPromiseInterface
	{
		$deferred = new Deferred();
		$this->loop->addTimer($seconds, static function () use ($deferred): void {
			$deferred->resolve();
		});

		return $deferred->promise();
	}

}
