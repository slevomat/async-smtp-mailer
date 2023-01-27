<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncMessage;

class AsyncZeroResponseMessage implements AsyncMessage
{

	private string $text;

	public function __construct(string $text)
	{
		$this->text = $text;
	}

	public function getText(): string
	{
		return $this->text;
	}

}
