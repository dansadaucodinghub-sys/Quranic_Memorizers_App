<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

interface TotpSecretEncryptor
{
    public function encrypt(
        TotpSecret $secret,
        string $accountPublicId,
        string $authenticatorPublicId,
    ): EncryptedTotpSecret;

    public function decrypt(
        EncryptedTotpSecret $secret,
        string $accountPublicId,
        string $authenticatorPublicId,
    ): TotpSecret;
}
