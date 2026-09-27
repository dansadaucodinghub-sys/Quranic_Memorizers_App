<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Domain;

use Qmdb\Shared\Configuration\EnvironmentVariables;

final readonly class OfflineCryptographicMaterial
{
    private string $signingSeed;
    private string $encryptionKey;
    private bool $configured;

    public function __construct(EnvironmentVariables $environment)
    {
        $signing = $this->decode($environment->optionalString('P13_OFFLINE_SIGNING_SEED'));
        $encryption = $this->decode($environment->optionalString('P13_OFFLINE_ENCRYPTION_KEY'));
        $this->configured = $signing !== null && $encryption !== null;
        $this->signingSeed = $signing ?? hash('sha256', 'qmdb-p13-local-test-signing-seed', true);
        $this->encryptionKey = $encryption ?? hash('sha256', 'qmdb-p13-local-test-encryption-key', true);
    }

    public function cryptography(): OfflinePackageCryptography
    {
        return new OfflinePackageCryptography($this->signingSeed, $this->encryptionKey, $this->configured);
    }

    private function decode(?string $encoded): ?string
    {
        $decoded = is_string($encoded) ? base64_decode($encoded, true) : false;

        return is_string($decoded) && strlen($decoded) === 32 ? $decoded : null;
    }
}
