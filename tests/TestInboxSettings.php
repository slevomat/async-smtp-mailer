<?php declare(strict_types = 1);

namespace AsyncConnection;

class TestInboxSettings
{

	private string $mailbox;

	private string $username;

	private string $password;

	public function __construct(
		string $mailbox,
		string $username,
		string $password
	)
	{
		$this->mailbox = $mailbox;
		$this->username = $username;
		$this->password = $password;
	}

	public function getMailbox(): string
	{
		return $this->mailbox;
	}

	public function getUsername(): string
	{
		return $this->username;
	}

	public function getPassword(): string
	{
		return $this->password;
	}

}
