<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

class SmtpSettings extends \Consistence\ObjectPrototype
{

	/** @var string */
	private $host;

	/** @var int */
	private $port;

	/** @var string|null */
	private $hello;

	/** @var string|null */
	private $username;

	/** @var string|null */
	private $password;

	public function __construct(
		?string $host,
		int $port,
		?string $hello,
		?string $username,
		?string $password
	)
	{
		$this->host = $host ?? ini_get('SMTP');
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

	public function getHello(): ?string
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
