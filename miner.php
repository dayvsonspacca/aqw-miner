<?php

use AqwMiner\Listeners\MinerListerner;
use AqwSocketClient\Client;
use AqwSocketClient\Configuration;
use AqwSocketClient\Interpreters\PlayerRelatedInterpreter;
use AqwSocketClient\Server;
use AqwSocketClient\Services\AuthService;

include __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$username = $_ENV['PLAYERNAME'];
$password = $_ENV['PASSWORD'];

$token = AuthService::getAuthToken($username, $password);

$minerListener = new MinerListerner();

$configuration = new Configuration(
    $username,
    $password,
    $token,
    logMessages: true,
    listeners: [$minerListener],
    interpreters: [new PlayerRelatedInterpreter()]
);

$client = new Client(
    Server::espada(),
    $configuration
);

$client->connect();