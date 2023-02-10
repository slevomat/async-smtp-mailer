<?php declare(strict_types = 1);

namespace AsyncConnection\Connector;

use React\EventLoop\LoopInterface;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\Promise;
use React\Socket\Connection;
use React\Socket\ConnectorInterface;
use RuntimeException;
use function fclose;
use function React\Promise\reject;
use function sprintf;
use function stream_context_create;
use function stream_set_blocking;
use function stream_socket_client;
use function stream_socket_get_name;
use const STREAM_CLIENT_ASYNC_CONNECT;
use const STREAM_CLIENT_CONNECT;

class TcpConnector implements ConnectorInterface
{

	private LoopInterface $loop;

	/** @var array<mixed> */
	private array $context;

	/**
	 * @param array<mixed> $context
	 */
	public function __construct(LoopInterface $loop, array $context = [])
	{
		$this->loop = $loop;
		$this->context = $context;
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
	 *
	 * @param string $uri
	 */
	public function connect($uri): ExtendedPromiseInterface
	{
		$socket = @stream_socket_client(
			$uri,
			$errno,
			$error,
			0,
			STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT,
			stream_context_create($this->context),
		);
		if ($socket === false) {
			return reject(new RuntimeException(
				sprintf('Connection to %s failed: %s', $uri, $error),
			));
		}
		stream_set_blocking($socket, false);

		return $this->waitForStreamOnce($socket);
	}

	/**
	 * @param resource $stream
	 */
	private function waitForStreamOnce($stream): Promise
	{
		$loop = $this->loop;

		return new Promise(static function ($resolve, $reject) use ($loop, $stream): void {
			$loop->addWriteStream($stream, static function ($stream) use ($loop, $resolve, $reject): void {
				$loop->removeWriteStream($stream);

				// The following hack looks like the only way to
				// detect connection refused errors with PHP's stream sockets.
				if (stream_socket_get_name($stream, true) === false) {
					fclose($stream);

					$reject(new RuntimeException('Connection refused'));
				} else {
					$resolve(new Connection($stream, $loop));
				}
			});
		}, static function () use ($loop, $stream): void {
			$loop->removeWriteStream($stream);
			fclose($stream);

			throw new RuntimeException('Cancelled while waiting for TCP/IP connection to be established');
		});
	}

}
