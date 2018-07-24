<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

class MailMessage extends \Nette\Mail\Message implements \AsyncConnection\AsyncMessage
{

	public function getText(): string
	{
		return $this->getBody() ?? 'undefined';
	}

}
