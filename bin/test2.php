<?php declare(strict_types=1);

use Amp\Loop;
use function Amp\Promise\first;
use DaveRandom\SslAsyncIssueDemo\Client;
use DaveRandom\SslAsyncIssueDemo\Peer;
use DaveRandom\SslAsyncIssueDemo\Server;

require __DIR__ . '/../vendor/autoload.php';

const ADDRESS = 'localhost';
const PORT = 56789;
const CERT_PATH = __DIR__ . '/../resources/localhost.pem';
const KEY_PATH = __DIR__ . '/../resources/localhost.key';

$server = new Server(
    ADDRESS,
    PORT,
    __DIR__ . '/../resources/localhost.pem',
    __DIR__ . '/../resources/localhost.key'
);
$client = new Client(ADDRESS, PORT);

function handle_server(Server $server)
{
    /** @var Peer $peer */
    while ($peer = yield $server->accept()) {
        yield $peer->awaitReadable();

        while (!$result = \stream_socket_enable_crypto($peer->getSocket(), true, STREAM_CRYPTO_METHOD_TLS_SERVER)) {
            if ($result === false) {
                throw new \RuntimeException('Could not enable crypto on server peer');
            }

            switch (yield first([$peer->awaitReadable(1), $peer->awaitWritable(2)])) {
                case 1:
                    echo "Server peer is readable\n";
                    break;

                case 2:
                    echo "Server peer is writable\n";
                    break;
            }
        }

        echo "Server peer crypto enabled\n";
    }
}

function handle_client(Client $client)
{
    yield $client->awaitWritable();

    while (!$result = \stream_socket_enable_crypto($client->getSocket(), true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        if ($result === false) {
            throw new \RuntimeException('Could not enable crypto on server peer');
        }

        switch (yield first([$client->awaitReadable(1), $client->awaitWritable(2)])) {
            case 1:
                echo "Client peer is readable\n";
                break;

            case 2:
                echo "Client peer is writable\n";
                break;
        }

        yield new \Amp\Delayed(500);
    }

    echo "Client peer crypto enabled\n";
}

Loop::run(function() use($client, $server) {
    $serverCoRoutine = new \Amp\Coroutine(handle_server($server));
    yield new \Amp\Coroutine(handle_client($client));
    $server->close();
    yield $serverCoRoutine;
});
