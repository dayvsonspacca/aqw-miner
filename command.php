<?php

require __DIR__.'/vendor/autoload.php';

use AqwMiner\Commands\ExtractShopItemsCommand;
use AqwMiner\Commands\MineItemDataCommand;
use AqwMiner\Commands\MineItemsCommand;
use Symfony\Component\Console\Application;

$application = new Application();

$application->add(new ExtractShopItemsCommand());
$application->add(new MineItemDataCommand());
$application->add(new MineItemsCommand());

$application->run();