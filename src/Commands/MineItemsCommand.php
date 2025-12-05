<?php

namespace AqwMiner\Commands;

use AqwMiner\Miner;
use Dom\HTMLDocument;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class MineItemsCommand extends Command
{
    private const int MAX_PROCESSES = 20;
    private array $processes = [];

    protected function configure(): void
    {
        $this
            ->setName('mine-items')
            ->setDescription('Extract information for all items of a given type.')
            ->setHelp(
                'This command retrieves the AQWiki page for a specified item type, gathers the links to all related items, 
            and triggers the MineItemDataCommand for each one.'
            )
            ->addArgument(
                'item-type',
                InputArgument::REQUIRED,
                'The type of item to fetch (e.g., "armors").'
            )
            ->addArgument(
                'page-count',
                InputArgument::REQUIRED,
                'Max page count of an item type'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $start = microtime(true);
        $itemType = $input->getArgument('item-type');
        $pageCount = min(30, (int)$input->getArgument('page-count'));
        $itemCount = 0;
        
        $output->writeln("<question>*** Starting Item Extraction for {$itemType} ***</question>");
        $output->writeln("Target URL: " . Miner::AQWIKI_URL . "/{$itemType}");
        $output->writeln("Max pages to process: <info>{$pageCount}</info>");

        $itemsUrl = Miner::AQWIKI_URL . '/' . $itemType;

        try {
            for ($page = 1; $page <= $pageCount; $page++) {
                $output->writeln("\n<fg=yellow>--- Processing Page {$page}/{$pageCount} ---</>");

                $url = $itemsUrl . '/p/' . $page;
                $output->write("Fetching page content from {$url}... ");

                $document = HTMLDocument::createFromString(
                    new Client()->get($url)->getBody(),
                    \LIBXML_NOERROR
                );

                $output->writeln("<info>OK</info>");
                
                $itemLinks = $document->querySelectorAll('.list-pages-item > p > a');
                $itemsOnPage = $itemLinks->count();
                $output->writeln("Found <fg=cyan>{$itemsOnPage}</> items on this page.");


                foreach ($itemLinks->getIterator() as $item) {
                    if (!isset($item->attributes)) {
                        continue;
                    }

                    $itemName = substr($item->attributes->getNamedItem('href')->value, 1);
                    $itemCount++;

                    while (count($this->processes) >= self::MAX_PROCESSES) {
                        $output->writeln(
                            "<comment>Max processes reached ({self::MAX_PROCESSES}). Waiting for a process to finish...</comment>"
                        );
                        foreach ($this->processes as $key => $process) {
                            if (!$process->isRunning()) {
                                $output->writeln("<fg=green>Process finished</>: {$this->processes[$key]->getCommandLine()}");
                                $output->write($this->processes[$key]->getOutput());
                                unset($this->processes[$key]);
                            }
                        }
                        usleep(500000);
                    }

                    $process = new Process(['php', 'command.php', 'mine-item-data', $itemName]);
                    $process->setTimeout(600);
                    $process->start();
                    $this->processes[] = $process;
                    $output->writeln("Starting process for item: <fg=blue>{$itemName}</> (Total active: " . count($this->processes) . ")");
                }
            }
            
            $output->writeln("\n<question>Finished queuing all pages. Waiting for remaining processes...</question>");
            while (!empty($this->processes)) {
                foreach ($this->processes as $key => $process) {
                    if (!$process->isRunning()) {
                        $output->writeln("<fg=green>Final process finished</>: {$this->processes[$key]->getCommandLine()}");
                        $output->write($this->processes[$key]->getOutput());
                        unset($this->processes[$key]);
                    }
                }
                usleep(500000);
            }

        } catch (GuzzleException $exception) {
            $output->writeln("\n<error>An error occurred when fetching items page: {$itemType} on page {$page}</error>");
            $output->writeln("Error: {$exception->getMessage()}");
            return Command::FAILURE;
        } catch (\InvalidArgumentException $exception) {
            $output->writeln("\n<error>Invalid Argument Error:</error>");
            $output->writeln("Error: {$exception->getMessage()}");
            return Command::FAILURE;
        } catch (ProcessFailedException $exception) {
            $output->writeln("\n<error>An error occurred when executing MineItemDataCommand</error>");
            $output->writeln("Error: {$exception->getMessage()}");
            return Command::FAILURE;
        } catch (\Throwable $exception) {
            $output->writeln("\n<error>An unexpected error occurred</error>");
            $output->writeln("Error: {$exception->getMessage()}");
            return Command::FAILURE;
        }

        $duration = microtime(true) - $start;
        $output->writeln("\n<question>*** Extraction Finished ***</question>");
        $output->writeln("Total items processed: <info>{$itemCount}</info>");
        $output->writeln('Process took <info>' . number_format($duration, 2) . ' seconds</info>.');
        return Command::SUCCESS;
    }
}