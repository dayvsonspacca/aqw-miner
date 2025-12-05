<?php

namespace AqwMiner\Commands;

use AqwMiner\Miner;
use Dom\HTMLDocument;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class MineItemDataCommand extends Command
{
    private const string BASE_OUTPUT_DIR = 'output/items';

    protected function configure(): void
    {
        $this
            ->setName('mine-item-data')
            ->setDescription('Retrieve detailed information about an AQW item.')
            ->setHelp(
                'This command fetches the AQWiki page of a specified item and extracts relevant details, 
            including its full name, associated tags (e.g., AC, RARE), and image links.'
            )
            ->addArgument(
                'item-name',
                InputArgument::REQUIRED,
                'The item’s short identifier (e.g., "Necrotic Sword of Doom" -> "necrotic-sword-of-doom-sword").'
            );
    }

    private function cleanScrapedText(string $text): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        return trim($text);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $itemUrlName = $input->getArgument('item-name');
        $itemUrl = Miner::AQWIKI_URL . '/' . $itemUrlName;
        $output->writeln('Fetching ' . $itemUrlName . ' information on ' . $itemUrl);

        $itemData = [
            'urlName' => $itemUrlName,
            'url' => $itemUrl,
            'name' => null,
            'type' => null,
            'description' => null,
            'rarity' => null,
            'tags' => [],
            'images' => [],
            'hash' => null,
        ];

        try {
            $client = new Client(['verify' => false]);
            $response = $client->get($itemUrl);
            $html = (string) $response->getBody();

            
            preg_match('/<strong>\s*Description:\s*<\/strong>\s*(.*?)(?:<br\s*\/?>|<\/|\z)/is', $html, $descMatches);
            if (isset($descMatches[1])) {
                $itemData['description'] = $this->cleanScrapedText($descMatches[1]);
            } else {
                 $output->writeln('<comment>Warning: Description not found via Regex.</comment>');
            }
            
            preg_match('/<strong>\s*Rarity:\s*<\/strong>\s*(.*?)(?:<br\s*\/?>|<\/|\z)/is', $html, $rarityMatches);
            if (isset($rarityMatches[1])) {
                $itemData['rarity'] = $this->cleanScrapedText($rarityMatches[1]); 
            }
            
            preg_match_all('/\/image-tags\/(ac|rare|pseudo|legend|special|seasonal)large\.png/i', $html, $tagMatches);
            if (!empty($tagMatches[1])) {
                foreach ($tagMatches[1] as $tagString) {
                    $tagString = match (strtolower($tagString)) {
                        'legend' => 'Legend',
                        'ac' => 'Adventure Coins',
                        'rare' => 'Rare',
                        'pseudo' => 'Pseudo Rare',
                        'seasonal' => 'Seasonal',
                        'special' => 'Special Offer',
                        default => ''
                    };
                    if ($tagString) {
                        $itemData['tags'][] = $tagString;
                    }
                }
                $itemData['tags'] = array_unique($itemData['tags']);
            }

            $document = HTMLDocument::createFromString($html, \LIBXML_NOERROR);
            
            $itemData['name'] = $this->cleanScrapedText($document->getElementById('page-title')->textContent);
            
            $itemType = $this->cleanScrapedText($document->querySelector('#breadcrumbs > a:nth-last-of-type(1)')->textContent);
            $itemData['type'] = strtolower(str_replace(' ', '_', $itemType));

            foreach ($document->querySelectorAll('img[src*="imgur"]')->getIterator() as $image) {
                $itemData['images'][] = $image->attributes->getNamedItem('src')->value ?? null;
            }
            foreach ($document->querySelectorAll('img[src*="' . $itemUrlName . '"]')->getIterator() as $image) {
                $itemData['images'][] = $image->attributes->getNamedItem('src')->value ?? null;
            }
            $itemData['images'] = array_unique(array_filter($itemData['images']));


            $subDir = self::BASE_OUTPUT_DIR . '/' . $itemData['type'];

            $hashContent = $itemData['name'] . $itemData['description'];
            $fileHash = md5($hashContent);
            $itemData['hash'] = $fileHash;
            $fileName = $subDir . '/' . $fileHash . '.json';
            
            if (file_exists($fileName)) {
                $output->writeln("<comment>Item already exists. Skipping save: {$fileName}</comment>");
                return Command::SUCCESS;
            }

            if (!is_dir($subDir)) {
                if (!mkdir($subDir, 0777, true)) {
                    throw new \Exception("Failed to create directory: " . $subDir);
                }
                $output->writeln("Created directory: <info>" . $subDir . "</info>");
            }

            $jsonContent = json_encode($itemData, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);
            
            if ($jsonContent === false) {
                 throw new \Exception("Failed to encode item data to JSON.");
            }

            if (file_put_contents($fileName, $jsonContent) === false) {
                throw new \Exception("Failed to write data to file: {$fileName}");
            }
            
            $output->writeln("<fg=green>Successfully saved</> item data to <info>{$fileName}</info>");

        } catch (GuzzleException $exception) {
            $output->writeln('An error occurred when fetching ' . $itemUrl . ' information. The URL was probably not found (404).');
            $output->writeln($exception->getMessage());

            return Command::FAILURE;
        } catch (InvalidArgumentException $exception) {
            $output->writeln('An error occurred when building item data array.');
            $output->writeln($exception->getMessage());

            return Command::INVALID;
        } catch (Throwable $exception) {
            $output->writeln("An unexpected error occurred");
            $output->writeln($exception->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}