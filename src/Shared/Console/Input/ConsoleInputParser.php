<?php

declare(strict_types=1);

namespace Qmdb\Shared\Console\Input;

use InvalidArgumentException;
use Qmdb\Shared\Console\Command\ConsoleCommandName;

final readonly class ConsoleInputParser
{
    /** @param list<string> $arguments */
    public function parse(array $arguments): ConsoleInput
    {
        $rawCommand = array_shift($arguments) ?? 'help';
        if ($rawCommand === '--help' || $rawCommand === '-h') {
            if ($arguments !== []) {
                throw new InvalidArgumentException('Help does not accept options.');
            }
            $rawCommand = 'help:show';
        } elseif ($rawCommand === 'help') {
            $rawCommand = 'help:show';
        }

        if (preg_match('/\A[a-z][a-z0-9]*\z/D', $rawCommand) === 1) {
            $rawCommand = 'unknown:' . $rawCommand;
        }

        $options = [];
        foreach ($arguments as $argument) {
            if (preg_match('/\A--([a-z][a-z0-9]*(?:-[a-z0-9]+)*)(?:=(.*))?\z/D', $argument, $match) !== 1) {
                throw new InvalidArgumentException('Console argument is malformed.');
            }
            $hasValue = str_contains($argument, '=');
            $options[] = new ConsoleOption(
                new ConsoleOptionName($match[1]),
                $hasValue ? ($match[2] ?? null) : null,
            );
        }

        return new ConsoleInput(new ConsoleCommandName($rawCommand), $options);
    }
}
