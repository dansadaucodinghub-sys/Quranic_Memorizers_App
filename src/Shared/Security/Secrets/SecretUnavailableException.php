<?php

declare(strict_types=1);

namespace Qmdb\Shared\Security\Secrets;

use RuntimeException;

final class SecretUnavailableException extends RuntimeException
{
    private function __construct(
        private readonly SecretName $secretName,
        private readonly string $reasonCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function missing(SecretName $name): self
    {
        return new self(
            $name,
            'SECRET_MISSING',
            sprintf('Required secret %s is missing.', $name->value()),
        );
    }

    public static function empty(SecretName $name): self
    {
        return new self(
            $name,
            'SECRET_EMPTY',
            sprintf('Required secret %s is empty.', $name->value()),
        );
    }

    public function secretName(): SecretName
    {
        return $this->secretName;
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }
}
