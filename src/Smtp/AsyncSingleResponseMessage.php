<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

class AsyncSingleResponseMessage extends \Consistence\ObjectPrototype implements \AsyncConnection\AsyncMessage
{

	/** @var string */
	private $text;

	/** @var int[] */
	private $expectedResponseCodes = [];

	/** @var string|null */
	private $textReplacement;

	/**
	 * @param string $text
	 * @param int[] $expectedResponseCodes
	 * @param null|string $textReplacement
	 */
	public function __construct(
		string $text,
		array $expectedResponseCodes,
		?string $textReplacement = null
	)
	{
		\Consistence\Type\Type::checkType($expectedResponseCodes, 'int[]');
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
