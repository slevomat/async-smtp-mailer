<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncConnectionException;

class InvalidSmtpConnectionException extends AsyncConnectionException
{

	public function __construct(string $message)
	{
		parent::__construct($message);
	}

}
