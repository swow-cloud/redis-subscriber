<?php
/**
 * This file is part of SwowCloud
 * @license  https://github.com/swow-cloud/music-server/blob/main/LICENSE
 */

declare(strict_types=1);

namespace SwowCloud\RedisSubscriber;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Swow\Channel;
use Swow\Coroutine;
use SwowCloud\Contract\StdoutLoggerInterface;

class Command
{
    protected ContainerInterface $container;

    protected StdoutLoggerInterface $stderrLogger;

    protected Connection $connection;

    protected Channel $resultChannel;

    protected Channel $messageChannel;

    /**
     * @param \SwowCloud\RedisSubscriber\Connection $connection
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __construct(ContainerInterface $container, Connection $connection)
    {
        $this->container = $container;
        if (!$this->container->has(StdoutLoggerInterface::class)) {
            throw new InvalidArgumentException('stdoutLogger not found!');
        }
        $this->stderrLogger = $this->container->get(StdoutLoggerInterface::class);
        $this->connection = $connection;
        $this->resultChannel = new Channel();
        $this->messageChannel = new Channel(100);
        Coroutine::run(function () use ($connection) {
            $this->loop($connection);
        });
    }

    /**
     * @param \SwowCloud\RedisSubscriber\Connection $connection
     */
    public function loop(Connection $connection): void
    {
        $buffer = null;
        while (true) {
            $line = $connection->recv();
            if ($line === '') {
                $this->interrupt();
                break;
            }
            if ($line === Status::OK) {
                $this->resultChannel->push($line);
                continue;
            }
            if ($line === Status::COUNT) {
                if (!empty($buffer)) {
                    $this->resultChannel->push($buffer);
                    $buffer = null;
                }
                $buffer[] = $line;
                continue;
            }

            $buffer[] = $line;

            $type = $buffer[2] ?? false;

            if ($type === 'subscribe' && count($buffer) === 6) {
                $this->resultChannel->push($buffer);
                $buffer = null;
                continue;
            }

            if ($type === 'unsubscribe' && count($buffer) === 6) {
                $this->resultChannel->push($buffer);
                $buffer = null;
                continue;
            }

            if ($type === 'message' && count($buffer) === 7) {
                $message = new Message();
                $message->channel = $buffer[4];
                $message->payload = $buffer[6];
                $coroutine = Coroutine::run(function () use ($message) {
                    sleep(30);
                    $this->stderrLogger->error(sprintf('Message channel (%s) is 30 seconds full, disconnected', $message->channel));
                    $this->interrupt();
                });
                $this->messageChannel->push($message);
                //当通道满的时候此处会发生IO阻塞,当超过30秒后断开该连接，但是此处有个问题当socket断开的时候swow
                //会提示Socket read has been canceled 需要考虑优化一下.
                $coroutine->kill();
                $buffer = null;
            }
        }
    }

    /**
     * @throws \Throwable
     */
    public function invoke(string $command, int $number): array
    {
        try {
            $this->connection->send($command . $this->connection::EOF);
        } catch (\Throwable $e) {
            $this->interrupt();
            throw $e;
        }
        $result = [];
        for ($i = 0; $i < $number; $i++) {
            $result[] = $this->resultChannel->pop();
        }

        return $result;
    }

    /**
     * Channel
     */
    public function channel(): Channel
    {
        return $this->messageChannel;
    }

    public function interrupt(): bool
    {
        $this->connection->close();
        $this->resultChannel->close();
        $this->messageChannel->close();

        return true;
    }
}
