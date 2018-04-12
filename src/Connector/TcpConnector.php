<?php declare(strict_types = 1);

namespace AsyncConnection\Connector;

class TcpConnector extends \Consistence\ObjectPrototype implements \React\Socket\ConnectorInterface
{

	/** @var \React\EventLoop\LoopInterface */
	private $loop;

	/** @var mixed[]  */
	private $context;

	/**
	 * @param \React\EventLoop\LoopInterface $loop
	 * @param mixed[] $context
	 */
	public function __construct(\React\EventLoop\LoopInterface $loop, array $context = [])
	{
		$this->loop = $loop;
		$this->context = $context;
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 * @param string $uri
	 * @return \React\Promise\ExtendedPromiseInterface
	 */
	public function connect($uri): \React\Promise\ExtendedPromiseInterface
	{
		$socket = @stream_socket_client(
			$uri,
			$errno,
			$error,
			0,
			STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT,
			stream_context_create($this->context)
		);
		if ($socket === false) {
			return \React\Promise\reject(new \RuntimeException(
				sprintf('Connection to %s failed: %s', $uri, $error)
			));
		}
		stream_set_blocking($socket, false);

		return $this->waitForStreamOnce($socket);
	}

	/**
	 * @param resource $stream
	 * @return \React\Promise\Promise
	 */
	private function waitForStreamOnce($stream): \React\Promise\Promise
	{
		$loop = $this->loop;

		return new \React\Promise\Promise(function ($resolve, $reject) use ($loop, $stream): void {
			$loop->addWriteStream($stream, function ($stream) use ($loop, $resolve, $reject): void {
				$loop->removeWriteStream($stream);

				// The following hack looks like the only way to
				// detect connection refused errors with PHP's stream sockets.
				if (stream_socket_get_name($stream, true) === false) {
					fclose($stream);

					$reject(new \RuntimeException('Connection refused'));
				} else {
					$resolve(new \React\Socket\Connection($stream, $loop));
				}
			});
		}, function () use ($loop, $stream): void {
			$loop->removeWriteStream($stream);
			fclose($stream);

			throw new \RuntimeException('Cancelled while waiting for TCP/IP connection to be established');
		});
	}

}
