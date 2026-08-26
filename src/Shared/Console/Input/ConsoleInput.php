<?php

declare(strict_types=1);

namespace Qmdb\Shared\Console\Input;

use InvalidArgumentException;
use Qmdb\Shared\Console\Command\ConsoleCommandName;

final readonly class ConsoleInput
{
    /** @var array<string, ConsoleOption> */
    private array $options;

    /** @param list<ConsoleOption> $options */
    public function __construct(private ConsoleCommandName $commandName, array $options)
    {
        $indexed = [];
        foreach ($options as $option) {
            $name = $option->name()->value();
            if (isset($indexed[$name])) {
                throw new InvalidArgumentException('Console option was repeated.');
            }
            $indexed[$name] = $option;
        }
        ksort($indexed);
        $this->options = $indexed;
    }

    public function commandName(): ConsoleCommandName
    {
        return $this->commandName;
    }

    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    public function option(string $name): ?ConsoleOption
    {
        return $this->options[$name] ?? null;
    }

    /** @param list<string> $allowed */
    public function assertOnlyOptions(array $allowed): void
    {
        foreach (array_keys($this->options) as $name) {
            if (!in_array($name, $allowed, true)) {
                throw new InvalidArgumentException('Unknown option for this command.');
            }
        }
    }

    public function requireFlag(string $name): bool
    {
        $option = $this->option($name);
        if ($option === null) {
            return false;
        }
        if (!$option->isFlag()) {
            throw new InvalidArgumentException('Boolean option must not contain a value.');
        }

        return true;
    }

    public function scalar(string $name): ?string
    {
        $option = $this->option($name);
        if ($option === null) {
            return null;
        }
        if ($option->isFlag()) {
            throw new InvalidArgumentException('Named option requires a value.');
        }

        return $option->value();
    }
}
