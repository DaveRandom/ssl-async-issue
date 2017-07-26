<?php declare(strict_types=1);

namespace DaveRandom\SslAsyncIssueDemo;

class Client extends Peer
{
    public function __construct(string $address, int $port)
    {
        $ctx = \stream_context_create([
            'ssl' => [
                'verify_peer' => false,
            ]
        ]);
        $socket = \stream_socket_client("tcp://{$address}:{$port}", $errNo, $errStr, 0, \STREAM_CLIENT_CONNECT | \STREAM_CLIENT_ASYNC_CONNECT, $ctx);

        if (!$socket) {
            throw new \RuntimeException("Failed to create client socket: {$errNo}: {$errStr}", $errNo);
        }

        parent::__construct($socket, $address, $port);
    }
}
