<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncMessage;

class AsyncSingleResponseMessage implements AsyncMessage
{

	private string $text;

	/** @var int[] */
	private array $expectedResponseCodes = [];

	private ?string $textReplacement = null;

	/**
	 * @param int[] $expectedResponseCodes
	 */
	public function __construct(
		string $text,
		array $expectedResponseCodes,
		?string $textReplacement = null
	)
	{
		$this->text = $text;
		$this->expectedResponseCodes = $expectedResponseCodes;
		$this->textReplacement = $textReplacement;
	}

	public function getText(): string
	{
		return $this->text;
	}

	/**
	 * @return int[]
	 */
	public function getExpectedResponseCodes(): array
	{
		return $this->expectedResponseCodes;
	}

	public function getTextReplacement(): ?string
	{
		return $this->textReplacement;
	}

}
