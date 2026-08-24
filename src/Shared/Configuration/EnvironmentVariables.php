<?php

declare(strict_types=1);

namespace Qmdb\Shared\Configuration;

use InvalidArgumentException;
use LogicException;

final readonly class EnvironmentVariables
{
    /** @var array<string, string> */
    private array $values;

    /** @param array<array-key, mixed> $values */
    public function __construct(array $values)
    {
        foreach ($values as $name => $value) {
            if (!is_string($name)) {
                throw new InvalidArgumentException('Environment-variable name is invalid.');
            }

            self::assertValidName($name);

            if (!is_string($value)) {
                throw new InvalidArgumentException(sprintf('%s must contain a string value.', $name));
            }
        }

        $this->values = $values;
    }

    public function has(string $name): bool
    {
        self::assertValidName($name);

        return array_key_exists($name, $this->values);
    }

    public function optionalString(string $name): ?string
    {
        self::assertValidName($name);

        return $this->values[$name] ?? null;
    }

    public function requiredString(string $name): string
    {
        $value = $this->optionalString($name);

        if ($value === null || trim($value) === '') {
            throw new InvalidArgumentException(sprintf('%s is required.', $name));
        }

        return $value;
    }

    public function boolean(string $name, bool $default): bool
    {
        $value = $this->optionalString($name);

        if ($value === null) {
            return $default;
        }

        return match (strtolower(trim($value))) {
            'true', '1' => true,
            'false', '0' => false,
            default => throw new InvalidArgumentException(
                sprintf('%s must be a recognized boolean.', $name),
            ),
        };
    }

    /** @return array{variables: string} */
    public function __debugInfo(): array
    {
        return ['variables' => '[REDACTED]'];
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        throw new LogicException('Environment-variable collections cannot be serialized.');
    }

    /** @param array<never, never> $data */
    public function __unserialize(array $data): void
    {
        throw new LogicException('Environment-variable collections cannot be unserialized.');
    }

    private static function assertValidName(string $name): void
    {
        if (preg_match('/\A[A-Z][A-Z0-9_]*\z/', $name) !== 1) {
            throw new InvalidArgumentException('Environment-variable name is invalid.');
        }
    }
}
