<?php

declare(strict_types=1);

namespace Qmdb\Shared\Security\Secrets;

interface SecretsProvider
{
    public function has(SecretName $name): bool;

    public function get(SecretName $name): SecretValue;
}
