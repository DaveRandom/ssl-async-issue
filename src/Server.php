<?php declare(strict_types=1);

namespace DaveRandom\SslAsyncIssueDemo;

use Amp\Deferred;
use Amp\Failure;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;

class Server
{
    /** @var resource */
    private $socket;

    /** @var string */
    private $watcher;

    /** @var Deferred */
    private $deferred;

    /** @var Peer[] */
    private $peerQueue = [];

    /** @var \Throwable */
    private $acceptError;

    public function __construct(string $address, int $port, string $certPath, string $keyPath)
    {
        $path = \realpath($certPath);
        if ($path === false) {
            throw new \LogicException("Invalid certificate path: {$certPath}");
        }
        $certPath = $path;

        $path = \realpath($keyPath);
        if ($path === false) {
            throw new \LogicException("Invalid key path: {$keyPath}");
        }
        $keyPath = $path;

        $ctx = \stream_context_create([
            'ssl' => [
                'local_cert' => $certPath,
                'local_pk' => $keyPath,
            ]
        ]);
        $socket = $this->socket = \stream_socket_server("tcp://{$address}:{$port}", $errNo, $errStr, \STREAM_SERVER_BIND | \STREAM_SERVER_LISTEN, $ctx);

        if (!$this->socket) {
            throw new \RuntimeException("Failed to create server socket: {$errNo}: {$errStr}", $errNo);
        }

        $deferred = &$this->deferred;
        $peerQueue = &$this->peerQueue;
        $this->watcher = Loop::onReadable($this->socket, static function() use($socket, &$deferred, &$peerQueue) {
            $client = \stream_socket_accept($socket, 0, $peerName);

            if (!$client) {
                $error = new \RuntimeException('stream_socket_accept() call failed');

                if ($deferred === null) {
                    $this->acceptError = $error;
                } else {
                    $this->deferred->fail($error);
                }

                return;
            }

            [$address, $port] = explode(':', $peerName);

            $peer = new Peer($client, $address, (int)$port);

            if ($deferred === null) {
                $peerQueue[] = $peer;
            }

            $temp = $deferred;
            $deferred = null;
            $temp->resolve($peer);
        });
    }

    public function accept(): Promise
    {
        if ($this->socket === null) {
            throw new \LogicException('Server is closed');
        }

        if (!empty($this->acceptError)) {
            return new Failure($this->acceptError);
        }

        if (!empty($this->peerQueue)) {
            return new Success(\array_shift($this->peerQueue));
        }

        if ($this->deferred !== null) {
            throw new \LogicException("Multiple concurrent calls to accept() are not permitted");
        }

        return ($this->deferred = new Deferred)->promise();
    }

    public function close()
    {
        Loop::cancel($this->watcher);

        \fclose($this->socket);
        $this->socket = null;

        if ($this->deferred !== null) {
            $this->deferred->resolve(null);
            $this->deferred = null;
        }
    }
}
