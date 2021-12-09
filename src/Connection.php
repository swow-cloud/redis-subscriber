<?php
/**
 * This file is part of SwowCloud
 * @license  https://github.com/swow-cloud/music-server/blob/main/LICENSE
 */

declare(strict_types=1);

namespace SwowCloud\RedisSubscriber;

use Swow\Socket;
use Swow\Socket\Exception as SocketException;
use Swow\Stream\EofStream;
use Swow\Stream\StreamException;

class Connection
{
    public string $host = '';

    public int $port = 6379;

    public int $timeout = 0;

    protected ?Socket $client = null;

    protected bool $closed = false;

    /**
     * EOF
     */
    public const EOF = "\r\n";

    /**
     * Connection constructor.
     *
     * @throws \Swow\Exception
     */
    public function __construct(string $host, int $port, int $timeout = 5 * 1000)
    {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout * 1000;
        $client = new EofStream();
        try {
            $client->connect($host, $port, $timeout);
            $this->client = $client;
        } catch (SocketException $e) {
            //todo Catch the error
        }
    }

    public function send(string $message, ?int $timeout = null): void
    {
        try {
            $this->client->sendMessageString($message, $timeout);
        } catch (Throwable $throwable) {
            throw new StreamException(
                sprintf(
                    'SwowSocket An error occurred while writing to the socket error:%s in:[%d] file:%s',
                    $throwable->getMessage(),
                    $throwable->getLine(),
                    $throwable->getFile()
                )
            );
        }
    }

    public function recv(int $timeout = null): string
    {
        try {
            return $this->client->recvMessageString($timeout);
        } catch (Throwable $throwable) {
            throw new StreamException(
                sprintf(
                    'SwowSocket An error occurred while reading the socket error:%s in:[%d] file:%s ',
                    $throwable->getMessage(),
                    $throwable->getLine(),
                    $throwable->getFile()
                )
            );
        }
    }

    public function close(): bool
    {
        return $this->client->close();
    }
}
