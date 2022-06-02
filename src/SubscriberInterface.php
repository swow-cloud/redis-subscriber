<?php
/**
 * This file is part of SwowCloud
 * @license  https://github.com/swow-cloud/websocket-server/blob/main/LICENSE
 */

declare(strict_types=1);

namespace SwowCloud\RedisSubscriber;

interface SubscriberInterface
{
    public function subscribe(string ...$channels);

    public function unsubscribe(string ...$channels);
}
