<?php

declare(strict_types=1);

namespace AqwMiner\Listeners;

use AqwSocketClient\Events\JoinedAreaEvent;
use AqwSocketClient\Interfaces\EventInterface;
use AqwSocketClient\Interfaces\ListenerInterface;

class MinerListerner implements ListenerInterface
{
    public array $players;

    public function listen(EventInterface $event)
    {
        if ($event instanceof JoinedAreaEvent) {
            $this->players = $players;
        }
    }
}
