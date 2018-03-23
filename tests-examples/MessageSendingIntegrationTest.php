<?php declare(strict_types = 1);

namespace AsyncConnection\Examples;

use AsyncConnection\IntegrationTestSettings;
use AsyncConnection\Smtp\SmtpSettings;
use AsyncConnection\TestInboxSettings;

class MessageSendingIntegrationTest extends \AsyncConnection\Smtp\AsyncSmtpMailerIntegrationTest
{

	public function getSettings(): IntegrationTestSettings
	{
		return new IntegrationTestSettings(
			false,
			false,
			'__EMAIL_FROM__',
			'__EMAIL_TO__',
			new SmtpSettings(
				'__SMTP_HOST__',
				2525,
				'__HELLO_MESSAGE__',
				'__YOUR_USERNAME__',
				'__YOUR_PASSWORD__'
			),
			new TestInboxSettings(
				'__IMAP_INBOX_ADDRESS__',
				'__YOUR_USERNAME__',
				'__YOUR_PASSWORD__'
			)
		);
	}

}
