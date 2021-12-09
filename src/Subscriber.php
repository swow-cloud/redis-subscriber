<?php
/**
 * This file is part of SwowCloud
 * @license  https://github.com/swow-cloud/music-server/blob/main/LICENSE
 */

declare(strict_types=1);

namespace SwowCloud\RedisSubscriber;

use Hyperf\Utils\ApplicationContext;
use JetBrains\PhpStorm\Pure;
use Psr\Container\ContainerInterface;
use Swow\Channel;
use SwowCloud\RedisSubscriber\Exception\SubscribeException;

class Subscriber implements SubscriberInterface
{
    public string $host = '127.0.0.1';

    public int $port = 6379;

    public int|float $timeout = 5 * 1000;

    public string $password = '';

    /**
     * @var \SwowCloud\RedisSubscriber\Command
     */
    protected Command $command;

    public bool $closed = false;

    protected ContainerInterface $container;

    public function __construct(string $host, int $port = 6379, string $password = '', int $timeout = 5 * 1000)
    {
        $this->host = $host;
        $this->port = $port;
        $this->password = $password;
        $this->timeout = $timeout;
        $this->container = ApplicationContext::getContainer();
        $this->connect();
    }

    protected function connect(): void
    {
        $connection = new Connection($this->host, $this->port, $this->timeout);
        $this->command = new Command($this->container, $connection);
        if ($this->password !== '') {
            $this->command->invoke("auth {$this->password}", 1);
        }
    }

    /** @noinspection ReturnTypeCanBeDeclaredInspection */
    /**
     * @param string ...$channels
     *
     * @throws \Throwable
     */
    public function subscribe(string ...$channels)
    {
        $result = $this->command->invoke('subscribe ' . implode(' ', $channels), count($channels));
        foreach ($result as $value) {
            if ($value === false || $value === null) {
                $this->command->interrupt();
                throw new SubscribeException('Subscribe failed');
            }
        }
    }

    /** @noinspection ReturnTypeCanBeDeclaredInspection */
    /**
     * @param string ...$channels
     *
     * @throws \Throwable
     */
    public function unsubscribe(string ...$channels)
    {
        $result = $this->command->invoke('unsubscribe ' . implode(' ', $channels), count($channels));
        foreach ($result as $value) {
            if ($value === false) {
                $this->commandInvoker->interrupt();
                throw new UnsubscribeException('Unsubscribe failed');
            }
        }
    }

    /**
     * @return \Swow\Channel
     */
    #[Pure]
    public function channel(): Channel
    {
        return $this->command->channel();
    }

    public function close(): void
    {
        $this->closed = true;
        $this->command->interrupt();
    }
}
