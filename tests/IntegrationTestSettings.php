<?php declare(strict_types = 1);

namespace AsyncConnection;

use AsyncConnection\Smtp\SmtpSettings;

class IntegrationTestSettings
{

	private bool $skipIntegrationTests;

	private bool $ignoreTimeoutErrors;

	private string $emailFrom;

	private string $recipientsEmail;

	private SmtpSettings $smtpSettings;

	private ?TestInboxSettings $testInboxSettings = null;

	public function __construct(
		bool $skipIntegrationTests,
		bool $ignoreTimeoutErrors,
		string $emailFrom,
		string $recipientsEmail,
		SmtpSettings $smtpSettings,
		?TestInboxSettings $testInboxSettings
	)
	{
		$this->skipIntegrationTests = $skipIntegrationTests;
		$this->ignoreTimeoutErrors = $ignoreTimeoutErrors;
		$this->emailFrom = $emailFrom;
		$this->recipientsEmail = $recipientsEmail;
		$this->smtpSettings = $smtpSettings;
		$this->testInboxSettings = $testInboxSettings;
	}

	public function shouldIgnoreTimeoutErrors(): bool
	{
		return $this->ignoreTimeoutErrors;
	}

	public function getEmailFrom(): string
	{
		return $this->emailFrom;
	}

	public function shouldSkipIntegrationTests(): bool
	{
		return $this->skipIntegrationTests;
	}

	public function getRecipientsEmail(): string
	{
		return $this->recipientsEmail;
	}

	public function getSmtpSettings(): SmtpSettings
	{
		return $this->smtpSettings;
	}

	public function getTestInboxSettings(): ?TestInboxSettings
	{
		return $this->testInboxSettings;
	}

}
