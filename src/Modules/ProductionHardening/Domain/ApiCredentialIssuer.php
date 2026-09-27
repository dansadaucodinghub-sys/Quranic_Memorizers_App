<?php

declare(strict_types=1);

namespace Qmdb\Modules\ProductionHardening\Domain;

final readonly class ApiCredentialIssuer
{
    /** @return array{key_id:string,secret:string,secret_hash:string} */
    public function issue(): array
    {
        $keyId = 'qmdb_' . bin2hex(random_bytes(12));
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $hash = password_hash($secret, PASSWORD_ARGON2ID);
        return ['key_id' => $keyId, 'secret' => $secret, 'secret_hash' => $hash];
    }

    public function verify(string $secret, string $hash): bool
    {
        return password_verify($secret, $hash);
    }
}
