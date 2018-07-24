<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

class AsyncZeroResponseMessage extends \Consistence\ObjectPrototype implements \AsyncConnection\AsyncMessage
{

	/** @var string */
	private $text;

	public function __construct(string $text)
	{
		$this->text = $text;
	}

	public function getText(): string
	{
		return $this->text;
	}

}
