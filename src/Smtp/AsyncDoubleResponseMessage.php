<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncMessage;

class AsyncDoubleResponseMessage implements AsyncMessage
{

	private string $text;

	/** @var int[] */
	private array $expectedFirstResponseCodes = [];

	/** @var int[] */
	private array $expectedSecondResponseCodes = [];

	private ?string $textReplacement = null;

	/**
	 * @param int[] $expectedFirstResponseCodes
	 * @param int[] $expectedSecondResponseCodes
	 */
	public function __construct(
		string $text,
		array $expectedFirstResponseCodes,
		array $expectedSecondResponseCodes,
		?string $textReplacement = null
	)
	{
		$this->text = $text;
		$this->expectedFirstResponseCodes = $expectedFirstResponseCodes;
		$this->expectedSecondResponseCodes = $expectedSecondResponseCodes;
		$this->textReplacement = $textReplacement;
	}

	public function getText(): string
	{
		return $this->text;
	}

	/**
	 * @return int[]
	 */
	public function getExpectedFirstResponseCodes(): array
	{
		return $this->expectedFirstResponseCodes;
	}

	/**
	 * @return int[]
	 */
	public function getExpectedSecondResponseCodes(): array
	{
		return $this->expectedSecondResponseCodes;
	}

	public function getTextReplacement(): ?string
	{
		return $this->textReplacement;
	}

}
