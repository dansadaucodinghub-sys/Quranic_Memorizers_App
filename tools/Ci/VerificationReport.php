<?php

declare(strict_types=1);

namespace Qmdb\Tools\Ci;

final class VerificationReport
{
    /** @var list<string> */
    private array $errors = [];

    /** @var list<string> */
    private array $warnings = [];

    private int $checks = 0;

    public function check(bool $condition, string $error): void
    {
        $this->checks++;
        if (!$condition) {
            $this->errors[] = $error;
        }
    }

    public function warning(string $warning): void
    {
        $this->warnings[] = $warning;
    }

    public function passed(): bool
    {
        return $this->errors === [];
    }

    public function checks(): int
    {
        return $this->checks;
    }

    /** @return list<string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function merge(self $other): void
    {
        $this->checks += $other->checks;
        array_push($this->errors, ...$other->errors);
        array_push($this->warnings, ...$other->warnings);
    }
}
