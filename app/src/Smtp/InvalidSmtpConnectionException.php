<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

class InvalidSmtpConnectionException extends \AsyncConnection\AsyncConnectionException
{

	public function __construct(?\Throwable $previous = null)
	{
		parent::__construct('SMTP connection stream is not readable or/and not writable.', $previous);
	}

}
