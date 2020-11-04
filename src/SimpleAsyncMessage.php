<?php declare(strict_types = 1);

namespace AsyncConnection;

use Consistence\ObjectPrototype;

class SimpleAsyncMessage extends ObjectPrototype implements AsyncMessage
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
