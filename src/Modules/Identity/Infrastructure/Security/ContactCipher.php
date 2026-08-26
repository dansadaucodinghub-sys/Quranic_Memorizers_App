<?php

declare(strict_types=1);

namespace Qmdb\Modules\Identity\Infrastructure\Security;

interface ContactCipher
{
    public function encrypt(string $plaintext): string;

    public function decrypt(string $ciphertext): string;

    public function keyId(): string;
}
