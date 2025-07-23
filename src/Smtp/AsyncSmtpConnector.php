<?php declare(strict_types = 1);

namespace AsyncConnection\Smtp;

use AsyncConnection\AsyncConnectionWriter;
use AsyncConnection\AsyncConnector;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ConnectorInterface;
use Throwable;
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

	/**
	 * @return PromiseInterface<AsyncConnectionWriter>
	 */
	public function connect(): PromiseInterface
	{
		return $this->connector->connect(sprintf('%s:%d', $this->smtpSettings->getHost(), $this->smtpSettings->getPort()))
			->then(function (ConnectionInterface $connection) {
				$writer = $this->asyncSmtpWriterFactory->create($connection);

				return $this->greetServer($writer);
			})->then(fn ($writer) => $this->loginToServer($writer))->then(static fn ($writer) => resolve($writer))
			->catch(static fn (Throwable $e) => reject($e));
	}

	/**
	 * @return PromiseInterface<int|null>
	 */
	public function disconnect(AsyncConnectionWriter $writer): PromiseInterface
	{
		return $writer->write(new AsyncSingleResponseMessage('QUIT', [SmtpCode::DISCONNECTING]));
	}

	/**
	 * @return PromiseInterface<AsyncSmtpConnectionWriter>
	 */
	private function greetServer(AsyncSmtpConnectionWriter $writer): PromiseInterface
	{
		$self = $this->smtpSettings->getHello();
		$ehloMessage = new AsyncDoubleResponseMessage(sprintf('EHLO %s', $self), [SmtpCode::SERVICE_READY], [SmtpCode::OK]);

		return $writer->write($ehloMessage)
			->catch(static function (AsyncSmtpConnectionException $e) use ($self, $writer) {
				$heloMessage = new AsyncDoubleResponseMessage(sprintf('HELO %s', $self), [SmtpCode::SERVICE_READY], [SmtpCode::OK]);

				return $writer->write($heloMessage);
			})->then(static fn () => resolve($writer));
	}

	/**
	 * @return PromiseInterface<AsyncSmtpConnectionWriter>
	 */
	private function loginToServer(AsyncSmtpConnectionWriter $writer): PromiseInterface
	{
		if ($this->smtpSettings->getUsername() !== null && $this->smtpSettings->getPassword() !== null) {
			return $writer->write(new AsyncSingleResponseMessage('AUTH LOGIN', [SmtpCode::AUTH_CONTINUE]))
				->then(function () use ($writer) {
					$usernameMessage = new AsyncSingleResponseMessage(base64_encode($this->smtpSettings->getUsername()), [SmtpCode::AUTH_CONTINUE], 'credentials');

					return $writer->write($usernameMessage);
				})->then(function () use ($writer) {
					$passwordMessage = new AsyncSingleResponseMessage(base64_encode($this->smtpSettings->getPassword()), [SmtpCode::AUTH_OK], 'credentials');

					return $writer->write($passwordMessage);
				})->then(static fn () => resolve($writer));
		}

		return resolve($writer);
	}

}
