<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncConnectionException;

class InvalidSmtpConnectionException extends AsyncConnectionException
{

	public function __construct()
	{
		parent::__construct('SMTP connection stream is not readable or/and not writable.');
	}

}
