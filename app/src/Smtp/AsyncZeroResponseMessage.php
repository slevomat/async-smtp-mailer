<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

class AsyncZeroResponseMessage extends \Consistence\ObjectPrototype implements \AsyncConnection\AsyncMessage
{

	/** @var string */
	private $text;

	/** @var string|null */
	private $textReplacement;

	public function __construct(
		string $text,
		?string $textReplacement = null
	)
	{
		$this->text = $text;
		$this->textReplacement = $textReplacement;
	}

	public function getText(): string
	{
		return $this->text;
	}

	public function getTextReplacement(): ?string
	{
		return $this->textReplacement;
	}

}
