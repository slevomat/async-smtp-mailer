<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncMessage;
use Consistence\ObjectPrototype;

class AsyncZeroResponseMessage extends ObjectPrototype implements AsyncMessage
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
