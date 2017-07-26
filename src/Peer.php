<?php declare(strict_types=1);

namespace DaveRandom\SslAsyncIssueDemo;

use Amp\Deferred;
use Amp\Loop;
use Amp\Promise;

class Peer
{
    private $socket;
    private $address;
    private $port;

    public function __construct($socket, string $address, int $port)
    {
        $this->socket = $socket;
        $this->address = $address;
        $this->port = $port;

        \stream_set_blocking($this->socket, false);
    }

    public function __destruct()
    {
        if ($this->socket !== null) {
            $this->close();
        }
    }

    /**
     * @return resource
     */
    public function getSocket()
    {
        return $this->socket;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function awaitReadable($value = null): Promise
    {
        $deferred = new Deferred;

        Loop::onReadable($this->socket, static function($watcher) use($deferred, $value) {
            Loop::cancel($watcher);
            $deferred->resolve($value);
        });

        return $deferred->promise();
    }

    public function awaitWritable($value = null): Promise
    {
        $deferred = new Deferred;

        Loop::onWritable($this->socket, static function($watcher) use($deferred, $value) {
            Loop::cancel($watcher);
            $deferred->resolve($value);
        });

        return $deferred->promise();
    }

    public function close()
    {
        \fclose($this->socket);
        $this->socket = null;
    }
}
