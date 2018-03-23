<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

class AsyncDoubleResponseMessage extends \Consistence\ObjectPrototype implements \AsyncConnection\AsyncMessage
{

	/** @var string */
	private $text;

	/** @var int[] */
	private $expectedFirstResponseCodes = [];

	/** @var int[] */
	private $expectedSecondResponseCodes = [];

	/** @var string|null */
	private $textReplacement;

	/**
	 * @param string $text
	 * @param int[] $expectedFirstResponseCodes
	 * @param int[] $expectedSecondResponseCodes
	 * @param null|string $textReplacement
	 */
	public function __construct(
		string $text,
		array $expectedFirstResponseCodes,
		array $expectedSecondResponseCodes,
		?string $textReplacement = null
	)
	{
		\Consistence\Type\Type::checkType($expectedFirstResponseCodes, 'int[]');
		\Consistence\Type\Type::checkType($expectedSecondResponseCodes, 'int[]');
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
