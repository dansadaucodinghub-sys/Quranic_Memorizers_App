<?php

declare(strict_types=1);

namespace Qmdb\Shared\Configuration;

use InvalidArgumentException;
use RuntimeException;

final class ConfigurationException extends RuntimeException
{
    /** @var list<ConfigurationViolation> */
    private array $violations;

    /** @param list<ConfigurationViolation> $violations */
    public function __construct(array $violations)
    {
        if ($violations === []) {
            throw new InvalidArgumentException('A configuration exception requires at least one violation.');
        }

        $this->violations = $violations;

        parent::__construct($this->toCliString());
    }

    public static function fromViolation(ConfigurationViolation $violation): self
    {
        return new self([$violation]);
    }

    /** @return list<ConfigurationViolation> */
    public function violations(): array
    {
        return $this->violations;
    }

    public function toCliString(): string
    {
        $lines = ['Application configuration is invalid:'];

        foreach ($this->violations as $violation) {
            $lines[] = sprintf(
                '- [%s] %s: %s',
                $violation->code(),
                $violation->variableName(),
                $violation->message(),
            );
        }

        return implode("\n", $lines);
    }
}
