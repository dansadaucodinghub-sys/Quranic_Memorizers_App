<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap;

final readonly class RuntimeRequirementResult
{
    /**
     * @param list<RuntimeViolation> $violations
     */
    public function __construct(private array $violations)
    {
    }

    public function isSatisfied(): bool
    {
        return $this->violations === [];
    }

    /** @return list<RuntimeViolation> */
    public function violations(): array
    {
        return $this->violations;
    }

    public function toCliString(): string
    {
        if ($this->isSatisfied()) {
            return 'Runtime requirements are satisfied.';
        }

        $lines = ['Runtime requirements are not satisfied:'];

        foreach ($this->violations as $violation) {
            $lines[] = sprintf('- %s: %s', $violation->code(), $violation->message());
        }

        return implode("\n", $lines);
    }
}
