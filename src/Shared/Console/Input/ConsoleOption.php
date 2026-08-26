<?php

declare(strict_types=1);

namespace Qmdb\Shared\Console\Input;

use InvalidArgumentException;

final readonly class ConsoleOption
{
    public function __construct(private ConsoleOptionName $name, private ?string $value)
    {
        if ($value !== null && (strlen($value) > 256 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1)) {
            throw new InvalidArgumentException('Console option value is invalid.');
        }
    }

    public function name(): ConsoleOptionName
    {
        return $this->name;
    }

    public function isFlag(): bool
    {
        return $this->value === null;
    }

    public function value(): ?string
    {
        return $this->value;
    }
}
