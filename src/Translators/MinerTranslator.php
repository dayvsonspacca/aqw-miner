<?php

declare(strict_types=1);

namespace AqwMiner\Translators;

use AqwMiner\Listeners\MinerListerner;
use AqwSocketClient\Interfaces\EventInterface;
use AqwSocketClient\Interfaces\CommandInterface;
use AqwSocketClient\Interfaces\TranslatorInterface;

class MinerTranslator implements TranslatorInterface
{
    public function __construct(
        private readonly MinerListerner $listener
    ) {}

    public function translate(EventInterface $event): CommandInterface|false
    {
        return false;
    }
}
