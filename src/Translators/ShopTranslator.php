<?php

declare(strict_types=1);

namespace AqwMiner\Translators;

use AqwSocketClient\Commands\LoadPlayerInventoryCommand;
use AqwSocketClient\Commands\LoadShopCommand;
use AqwSocketClient\Events\JoinedAreaEvent;
use AqwSocketClient\Events\PlayerInventoryLoadedEvent;
use AqwSocketClient\Events\ShopLoadedEvent;
use AqwSocketClient\Interfaces\EventInterface;
use AqwSocketClient\Interfaces\CommandInterface;
use AqwSocketClient\Interfaces\TranslatorInterface;
use AqwSocketClient\Listeners\GlobalPlayerListener;

class ShopTranslator implements TranslatorInterface
{
    public function __construct(
        public readonly GlobalPlayerListener $global,
        private array $shopIds
    ) {
    }

    public function translate(EventInterface $event): CommandInterface|false
    {
        if ($event instanceof JoinedAreaEvent) {
            return new LoadPlayerInventoryCommand($event->areaId, $this->global->socketId);
        }

        if ($event instanceof PlayerInventoryLoadedEvent) {
            $shopId = array_shift($this->shopIds);
            return new LoadShopCommand($this->global->areaId, $shopId);
        }

        if ($event instanceof ShopLoadedEvent && count($this->shopIds) > 0) {
            sleep(1);
            $shopId = array_shift($this->shopIds);
            return new LoadShopCommand($this->global->areaId, $shopId);
        }

        return false;
    }
}
