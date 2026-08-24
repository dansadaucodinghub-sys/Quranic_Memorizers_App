<?php

declare(strict_types=1);

namespace Qmdb\Shared\Security\Secrets;

use Qmdb\Shared\Configuration\EnvironmentVariables;

final readonly class EnvironmentSecretsProvider implements SecretsProvider
{
    public function __construct(private EnvironmentVariables $variables)
    {
    }

    public function has(SecretName $name): bool
    {
        $value = $this->variables->optionalString($name->value());

        return $value !== null && trim($value) !== '';
    }

    public function get(SecretName $name): SecretValue
    {
        $value = $this->variables->optionalString($name->value());

        if ($value === null) {
            throw SecretUnavailableException::missing($name);
        }

        if (trim($value) === '') {
            throw SecretUnavailableException::empty($name);
        }

        return new SecretValue($value);
    }

    /** @return array{provider: string, values: string} */
    public function __debugInfo(): array
    {
        return [
            'provider' => 'environment',
            'values' => '[REDACTED]',
        ];
    }
}
