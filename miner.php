<?php

use AqwMiner\AuthService;
use AqwMiner\Listeners\ShopListener;
use AqwMiner\Translators\ShopTranslator;
use AqwSocketClient\Client;
use AqwSocketClient\Configuration;
use AqwSocketClient\Interpreters\PlayerRelatedInterpreter;
use AqwSocketClient\Interpreters\ShopInterpreter;
use AqwSocketClient\Listeners\GlobalPlayerListener;
use AqwSocketClient\Server;

include __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$username = $_ENV['PLAYERNAME'];
$password = $_ENV['PASSWORD'];

$token = AuthService::getAuthToken($username, $password);

$global = new GlobalPlayerListener();
$shopTranslator = new ShopTranslator($global);

$configuration = new Configuration(
    $username,
    $password,
    $token,
    logMessages: true,
    listeners: [$global, new ShopListener()],
    interpreters: [new PlayerRelatedInterpreter(), new ShopInterpreter()],
    translators: [$shopTranslator]
);

$client = new Client(
    Server::espada(),
    $configuration
);

$client->connect();