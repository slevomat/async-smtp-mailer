<?php declare(strict_types = 1);

namespace AsyncConnection;

class SimpleAsyncMessage implements AsyncMessage
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
