<?php
/**
 * This file is part of SwowCloud
 * @license  https://github.com/swow-cloud/music-server/blob/main/LICENSE
 */

declare(strict_types=1);

namespace SwowCloud\RedisSubscriber;

interface SubscriberInterface
{
    /**
     * @param string ...$channels
     */
    public function subscribe(string ...$channels);

    /**
     * @param string ...$channels
     */
    public function unsubscribe(string ...$channels);
}
