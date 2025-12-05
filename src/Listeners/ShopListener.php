<?php

declare(strict_types=1);

namespace AqwMiner\Listeners;

use AqwSocketClient\Events\ShopLoadedEvent;
use AqwSocketClient\Interfaces\EventInterface;
use AqwSocketClient\Interfaces\ListenerInterface;
use AqwSocketClient\Objects\Item;
use AqwSocketClient\Objects\Shop;
use Symfony\Component\Console\Output\OutputInterface;

class ShopListener implements ListenerInterface
{
    private const SHOP_FILE = 'output/shops.csv';
    private const ITEM_FILE = 'output/items.csv';
    
    public function __construct(private readonly OutputInterface $output) {}

    public function listen(EventInterface $event)
    {
        if ($event instanceof ShopLoadedEvent) {
            /** @var Shop $shop */
            $shop = $event->shop;

            $this->output->writeln("  <info>Processing Shop #{$shop->id} - {$shop->name}</info>");
            
            $this->writeShopHeader($shop);
            $this->writeItems($shop);
        }
    }

    private function writeShopHeader(Shop $shop): void
    {
        $file = fopen(self::SHOP_FILE, 'a');

        if (filesize(self::SHOP_FILE) == 0) {
            fputcsv($file, ['id', 'name', 'type', 'memberOnly', 'itemCount'], ';');
        }
        
        $data = [
            $shop->id,
            $shop->name,
            $shop->type,
            $shop->memberOnly ? 'YES' : 'NO',
            count($shop->items)
        ];

        fputcsv($file, $data, ';');
        fclose($file);
        
        $this->output->writeln("  <comment>-> Shop header saved to " . self::SHOP_FILE . "</comment>");
    }

    private function writeItems(Shop $shop): void
    {
        $file = fopen(self::ITEM_FILE, 'a');

        if (filesize(self::ITEM_FILE) == 0) {
            fputcsv($file, ['shopId', 'itemId', 'name', 'description', 'asset_url', 'type', 'memberOnly', 'coinType', 'coinAmount'], ';');
        }

        foreach ($shop->items as $item) {
            /** @var Item $item */
            fputcsv($file, [
                $shop->id,
                $item->id,
                $item->name,
                $item->description,
                $item->assetUrl ? 'https://game.aq.com/game/gamefiles/' . $item->assetUrl : null,
                $item->type,
                $item->memberOnly ? 'YES' : 'NO',
                $item->coinType === Item::AC ? 'AC' : 'COINS',
                $item->coinAmount
            ], ';');
        }
        
        fclose($file);
        $this->output->writeln("  <comment>-> {$shop->count()} items saved to " . self::ITEM_FILE . "</comment>");
    }
}