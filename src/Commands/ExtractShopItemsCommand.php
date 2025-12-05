<?php

declare(strict_types=1);

namespace AqwMiner\Commands;

use AqwMiner\AuthService;
use AqwMiner\Listeners\ShopListener;
use AqwMiner\Translators\ShopTranslator;
use AqwSocketClient\Client;
use AqwSocketClient\Configuration;
use AqwSocketClient\Interpreters\PlayerRelatedInterpreter;
use AqwSocketClient\Interpreters\ShopInterpreter;
use AqwSocketClient\Listeners\GlobalPlayerListener;
use AqwSocketClient\Server;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExtractShopItemsCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('extract-shop-items')
            ->setDescription('Loads a shop and extracts information for all items within it.')
            ->addArgument(
                'playername',
                InputArgument::REQUIRED,
                'The player\'s account username.'
            )
            ->addArgument(
                'password',
                InputArgument::REQUIRED,
                'The player\'s account password.'
            )
            ->addArgument(
                'shop-ids',
                InputArgument::REQUIRED,
                'A comma-separated string of shop IDs to extract (e.g., "216,68,105").'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("<question>*** Starting Shop Extraction ***</question>");
        
        try {
            $playername = $input->getArgument('playername');
            $password = $input->getArgument('password');
            $shopIdsString = $input->getArgument('shop-ids');
            
            $shopIds = array_map('intval', explode(',', $shopIdsString));

            $output->writeln("Targeting <info>{$playername}</info> for shops: <fg=cyan>" . implode(', ', $shopIds) . "</>");

            $output->write("Requesting authentication token... ");
            $token = AuthService::getAuthToken($playername, $password);
            $output->writeln("<info>OK</info>");

            $global = new GlobalPlayerListener();
            $shopTranslator = new ShopTranslator($global, $shopIds);

            $output->writeln("Setting up client configuration...");
            
            $shopListener = new ShopListener($output);

            $configuration = new Configuration(
                $playername,
                $password,
                $token,
                logMessages: true,
                listeners: [$global, $shopListener],
                interpreters: [new PlayerRelatedInterpreter(), new ShopInterpreter()],
                translators: [$shopTranslator]
            );

            $output->writeln("Connecting to server...");
            
            $client = new Client(
                Server::espada(),
                $configuration
            );

            $client->connect();
            
            $output->writeln("<info>Connection closed successfully.</info>");

        } catch (Exception $e) {
            $output->writeln("\n<error>An unexpected error occurred:</error>");
            $output->writeln("  <fg=red;options=bold>Error:</> {$e->getMessage()}");
            $output->writeln("  <fg=red;options=bold>File:</> {$e->getFile()} on line {$e->getLine()}");
            
            return Command::FAILURE;
        }

        $output->writeln("<question>*** Shop Extraction Finished ***</question>");
        
        return Command::SUCCESS;
    }
}