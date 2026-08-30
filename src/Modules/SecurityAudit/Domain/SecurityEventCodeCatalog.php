<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Domain;

/**
 * The sole trusted registration boundary for ledger event codes.
 */
final class SecurityEventCodeCatalog
{
    /** @return list<SecurityEventCode> */
    public static function all(): array
    {
        return SecurityEventCode::cases();
    }

    public static function fromTrustedString(string $value): SecurityEventCode
    {
        return SecurityEventCode::fromTrustedString($value);
    }
}
