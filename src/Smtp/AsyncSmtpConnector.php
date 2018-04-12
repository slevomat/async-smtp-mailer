<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncConnectionWriter;

class AsyncSmtpConnector extends \Consistence\ObjectPrototype implements \AsyncConnection\AsyncConnector
{

	/** @var \AsyncConnection\Smtp\AsyncSmtpConnectionWriterFactory */
	private $asyncSmtpWriterFactory;

	/** @var \React\Socket\ConnectorInterface */
	private $connector;

	/** @var \AsyncConnection\Smtp\SmtpSettings */
	private $smtpSettings;

	public function __construct(
		AsyncSmtpConnectionWriterFactory $asyncSmtpWriterFactory,
		\React\Socket\ConnectorInterface $connector,
		SmtpSettings $smtpSettings
	)
	{
		$this->asyncSmtpWriterFactory = $asyncSmtpWriterFactory;
		$this->connector = $connector;
		$this->smtpSettings = $smtpSettings;
	}

	public function connect(): \React\Promise\PromiseInterface
	{
		return $this->connector->connect(sprintf('%s:%d', $this->smtpSettings->getHost(), $this->smtpSettings->getPort()))
			->then(function (\React\Socket\ConnectionInterface $connection) {
				$writer = $this->asyncSmtpWriterFactory->create($connection);
				return $this->greetServer($writer);
			})->then(function ($writer) {
				return $this->loginToServer($writer);
			})->then(function ($writer) {
				return \React\Promise\resolve($writer);
			});
	}

	public function disconnect(AsyncConnectionWriter $writer): \React\Promise\PromiseInterface
	{
		return $writer->write(new AsyncSingleResponseMessage('QUIT', [SmtpCode::DISCONNECTING]));
	}

	private function greetServer(AsyncSmtpConnectionWriter $writer): \React\Promise\PromiseInterface
	{
		$self = $this->smtpSettings->getHello() ?: (isset($_SERVER['HTTP_HOST']) && preg_match('#^[\w.-]+\z#', $_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
		$ehloMessage = new AsyncDoubleResponseMessage(sprintf('EHLO %s', $self), [SmtpCode::SERVICE_READY], [SmtpCode::OK]);

		return $writer->write($ehloMessage)
			->otherwise(function (\AsyncConnection\Smtp\AsyncSmtpConnectionException $e) use ($self, $writer) {
				$heloMessage = new AsyncDoubleResponseMessage(sprintf('HELO %s', $self), [SmtpCode::SERVICE_READY], [SmtpCode::OK]);
				return $writer->write($heloMessage);
			})->then(function () use ($writer) {
				return \React\Promise\resolve($writer);
			});
	}

	private function loginToServer(AsyncSmtpConnectionWriter $writer): \React\Promise\PromiseInterface
	{
		if ($this->smtpSettings->getUsername() !== null && $this->smtpSettings->getPassword() !== null) {

			return $writer->write(new AsyncSingleResponseMessage('AUTH LOGIN', [SmtpCode::AUTH_CONTINUE]))
				->then(function () use ($writer) {
					$usernameMessage = new AsyncSingleResponseMessage(base64_encode($this->smtpSettings->getUsername()), [SmtpCode::AUTH_CONTINUE], 'credentials');
					return $writer->write($usernameMessage);
				})->then(function () use ($writer) {
					$passwordMessage = new AsyncSingleResponseMessage(base64_encode($this->smtpSettings->getPassword()), [SmtpCode::AUTH_OK], 'credentials');
					return $writer->write($passwordMessage);
				})->then(function () use ($writer) {
					return \React\Promise\resolve($writer);
				});
		}

		return \React\Promise\resolve($writer);
	}

}
