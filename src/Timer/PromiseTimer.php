<?php declare(strict_types = 1);

namespace AsyncConnection\Timer;

use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

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
	public function wait($seconds): PromiseInterface
	{
		$deferred = new Deferred();
		$this->loop->addTimer($seconds, static function () use ($deferred): void {
			$deferred->resolve(null);
		});

		return $deferred->promise();
	}

}
