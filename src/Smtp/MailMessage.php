<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncMessage;
use Nette\Mail\Message;

class MailMessage extends Message implements AsyncMessage
{

	public function getText(): string
	{
		return $this->getBody();
	}

}
