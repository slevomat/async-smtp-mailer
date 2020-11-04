<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncMessage;
use Consistence\ObjectPrototype;
use Consistence\Type\Type;

class AsyncSingleResponseMessage extends ObjectPrototype implements AsyncMessage
{

	private string $text;

	/** @var int[] */
	private array $expectedResponseCodes = [];

	private ?string $textReplacement = null;

	/**
	 * @param string $text
	 * @param int[] $expectedResponseCodes
	 * @param string|null $textReplacement
	 */
	public function __construct(
		string $text,
		array $expectedResponseCodes,
		?string $textReplacement = null
	)
	{
		Type::checkType($expectedResponseCodes, 'int[]');
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
