<?php

use AqwSocketClient\Client;
use AqwSocketClient\Configuration;
use AqwSocketClient\Server;
use AqwSocketClient\Services\AuthService;

include __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$username = $_ENV['PLAYERNAME'];
$password = $_ENV['PASSWORD'];

$token = AuthService::getAuthToken($username, $password);

$configuration = new Configuration(
    $username,
    $password,
    $token,
    logMessages: true
);

$client = new Client(
    Server::espada(),
    $configuration
);

$client->connect();