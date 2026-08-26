<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Logging;

final readonly class SensitiveKeyMatcher
{
    /** @var list<string> */
    private const SENSITIVE_FRAGMENTS = [
        'password',
        'passwd',
        'secret',
        'token',
        'authorization',
        'cookie',
        'session',
        'csrf',
        'api_key',
        'apikey',
        'private_key',
        'signing_key',
        'credential',
        'database_password',
        'db_password',
        'recovery',
        'mfa',
        'otp',
        'nin',
        'identity_document',
    ];

    public function matches(string $key): bool
    {
        $normalized = strtolower(str_replace('-', '_', $key));

        foreach (self::SENSITIVE_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
