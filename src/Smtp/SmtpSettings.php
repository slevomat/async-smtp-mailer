<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use Consistence\ObjectPrototype;

class SmtpSettings extends ObjectPrototype
{

	private string $host;

	private int $port;

	private string $hello;

	private ?string $username = null;

	private ?string $password = null;

	public function __construct(
		string $host,
		int $port,
		string $hello,
		?string $username,
		?string $password
	)
	{
		$this->host = $host;
		$this->port = $port;
		$this->hello = $hello;
		$this->username = $username;
		$this->password = $password;
	}

	public function getHost(): string
	{
		return $this->host;
	}

	public function getPort(): int
	{
		return $this->port;
	}

	public function getHello(): string
	{
		return $this->hello;
	}

	public function getUsername(): ?string
	{
		return $this->username;
	}

	public function getPassword(): ?string
	{
		return $this->password;
	}

	public function updatePassword(?string $password): void
	{
		$this->password = $password;
	}

	public function updateUsername(?string $username): void
	{
		$this->username = $username;
	}

	public function updateHost(?string $host): void
	{
		$this->host = $host;
	}

}
