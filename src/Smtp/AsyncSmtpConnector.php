<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncConnectionWriter;
use AsyncConnection\AsyncConnector;
use AsyncConnection\AsyncMessage;
use AsyncConnection\AsyncResult;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ConnectorInterface;
use function base64_encode;
use function React\Promise\reject;
use function React\Promise\resolve;
use function sprintf;

class AsyncSmtpConnector implements AsyncConnector
{

	private AsyncSmtpConnectionWriterFactory $asyncSmtpWriterFactory;

	private ConnectorInterface $connector;

	private SmtpSettings $smtpSettings;

	public function __construct(
		AsyncSmtpConnectionWriterFactory $asyncSmtpWriterFactory,
		ConnectorInterface $connector,
		SmtpSettings $smtpSettings
	)
	{
		$this->asyncSmtpWriterFactory = $asyncSmtpWriterFactory;
		$this->connector = $connector;
		$this->smtpSettings = $smtpSettings;
	}

	public function connect(): PromiseInterface
	{
		return $this->connector->connect(sprintf('%s:%d', $this->smtpSettings->getHost(), $this->smtpSettings->getPort()))
			->then(function (ConnectionInterface $connection) {
				$writer = $this->asyncSmtpWriterFactory->create($connection);

				return $this->greetServer($writer);
			})->then(fn ($writer) => $this->loginToServer($writer));
	}

	public function disconnect(AsyncConnectionWriter $writer): PromiseInterface
	{
		return $this->write($writer, new AsyncSingleResponseMessage('QUIT', [SmtpCode::DISCONNECTING]));
	}

	private function write(AsyncConnectionWriter $writer, AsyncMessage $message): PromiseInterface
	{
		return $writer->write($message)->then(static fn (AsyncResult $result) => $result->isSuccess() ? resolve($writer) : reject($result->getError()));
	}

	private function greetServer(AsyncSmtpConnectionWriter $writer): PromiseInterface
	{
		$self = $this->smtpSettings->getHello();
		$ehloMessage = new AsyncDoubleResponseMessage(sprintf('EHLO %s', $self), [SmtpCode::SERVICE_READY], [SmtpCode::OK]);

		return $this->write($writer, $ehloMessage)
			->catch(function (AsyncSmtpConnectionException $e) use ($self, $writer) {
				$heloMessage = new AsyncDoubleResponseMessage(sprintf('HELO %s', $self), [SmtpCode::SERVICE_READY], [SmtpCode::OK]);

				return $this->write($writer, $heloMessage);
			});
	}

	private function loginToServer(AsyncSmtpConnectionWriter $writer): PromiseInterface
	{
		if ($this->smtpSettings->getUsername() !== null && $this->smtpSettings->getPassword() !== null) {
			return $this->write($writer, new AsyncSingleResponseMessage('AUTH LOGIN', [SmtpCode::AUTH_CONTINUE]))
				->then(function ($writer) {
					$usernameMessage = new AsyncSingleResponseMessage(base64_encode($this->smtpSettings->getUsername()), [SmtpCode::AUTH_CONTINUE], 'credentials');

					return $this->write($writer, $usernameMessage);
				})->then(function ($writer) {
					$passwordMessage = new AsyncSingleResponseMessage(base64_encode($this->smtpSettings->getPassword()), [SmtpCode::AUTH_OK], 'credentials');

					return $this->write($writer, $passwordMessage);
				});
		}

		return resolve($writer);
	}

}
