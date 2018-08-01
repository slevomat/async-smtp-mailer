<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

class UnexpectedResponseCodeException extends \AsyncConnection\AsyncConnectionException
{

	/** @var int */
	private $actualCode;

	/** @var int[] */
	private $expectedCodes;

	/**
	 * @param int $actualCode
	 * @param int[] $expectedCodes
	 * @param string $errorMessage
	 * @param string $responseMessage
	 */
	public function __construct(
		int $actualCode,
		array $expectedCodes,
		string $errorMessage,
		string $responseMessage
	)
	{
		parent::__construct(sprintf('%s: %s', $errorMessage, $responseMessage));
		$this->actualCode = $actualCode;
		$this->expectedCodes = $expectedCodes;
	}

	public function getActualCode(): int
	{
		return $this->actualCode;
	}

	/**
	 * @return int[]
	 */
	public function getExpectedCodes(): array
	{
		return $this->expectedCodes;
	}

}
