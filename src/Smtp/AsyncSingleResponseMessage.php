<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncMessage;

class AsyncSingleResponseMessage implements AsyncMessage
{

	private string $text;

	/** @var array<int> */
	private array $expectedResponseCodes = [];

	private ?string $textReplacement = null;

	/**
	 * @param array<int> $expectedResponseCodes
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
	 * @return array<int>
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
