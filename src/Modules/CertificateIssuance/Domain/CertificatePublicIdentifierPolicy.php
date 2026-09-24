<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Domain;

/**
 * Canonical public contracts for certificate identifiers.
 *
 * Serial numbers are operational identifiers, not authentication secrets.
 * Verification codes contain 256 bits of random material and are persisted
 * only as SHA-256 values.
 */
final class CertificatePublicIdentifierPolicy
{
    public const VERIFICATION_CODE_RANDOM_BYTES = 32;

    public const VERIFICATION_CODE_LENGTH = 43;

    private const SERIAL_PATTERN = '/\AQMDB-(?:[2-9][0-9]{3})-[A-Z0-9][A-Z0-9_-]{0,63}-[0-9]{6}\z/';

    private const VERIFICATION_CODE_PATTERN = '/\A[A-Za-z0-9_-]{43}\z/';

    private function __construct()
    {
    }

    public static function serial(int $year, string $workspaceCode, int $sequence): string
    {
        $normalized = strtoupper($workspaceCode);
        if (
            $year < 2000 || $year > 9999 || $sequence < 1 || $sequence > 999999
            || preg_match('/\A[A-Z0-9][A-Z0-9_-]{0,63}\z/', $normalized) !== 1
        ) {
            throw new \InvalidArgumentException('Certificate serial allocation input is invalid.');
        }

        return sprintf('QMDB-%04d-%s-%06d', $year, $normalized, $sequence);
    }

    public static function isSerial(string $value): bool
    {
        return preg_match(self::SERIAL_PATTERN, $value) === 1;
    }

    public static function isVerificationCode(string $value): bool
    {
        return preg_match(self::VERIFICATION_CODE_PATTERN, $value) === 1;
    }
}
