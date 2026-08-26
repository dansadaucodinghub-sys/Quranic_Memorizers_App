<?php

declare(strict_types=1);

namespace Qmdb\Shared\Console\Input;

use InvalidArgumentException;
use Stringable;

final readonly class ConsoleOptionName implements Stringable
{
    private const PATTERN = '/\A[a-z][a-z0-9]*(?:-[a-z0-9]+)*\z/';
    private const PROHIBITED = ['password', 'secret', 'token', 'credential', 'private-key'];

    public function __construct(private string $value)
    {
        if (strlen($value) > 64 || preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('Console option name is invalid.');
        }
        foreach (self::PROHIBITED as $fragment) {
            if (str_contains($value, $fragment)) {
                throw new InvalidArgumentException('Secret-bearing console options are prohibited.');
            }
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
