<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Infrastructure\Security;

use Qmdb\Modules\SecurityAudit\Configuration\SecurityAuditConfiguration;
use Qmdb\Modules\SecurityAudit\Domain\SecurityAuditIntegrityKeyProvider;
use Qmdb\Shared\Security\Secrets\SecretName;
use Qmdb\Shared\Security\Secrets\SecretsProvider;

final readonly class EnvironmentSecurityAuditIntegrityKeyProvider implements SecurityAuditIntegrityKeyProvider
{
    public function __construct(private SecretsProvider $secrets, private SecurityAuditConfiguration $configuration)
    {
    }

    public function keyForVersion(int $version): string
    {
        if ($version !== $this->configuration->integrityKeyVersion) {
            throw new \RuntimeException('Security audit integrity key version is unavailable.');
        }
        if (!$this->secrets->has(SecretName::fromString('AUTH_SECURITY_AUDIT_HMAC_KEY'))) {
            if ($this->configuration->productionLike) {
                throw new \RuntimeException('Security audit integrity key is unavailable.');
            }

            return hash('sha256', 'QMDB-NON-PRODUCTION-SECURITY-AUDIT-KEY-V1', true);
        }
        $raw = $this->secrets->get(SecretName::fromString('AUTH_SECURITY_AUDIT_HMAC_KEY'))->reveal();
        $key = base64_decode($raw, true);
        if (!is_string($key) || strlen($key) < 32) {
            throw new \RuntimeException('Security audit integrity key is invalid.');
        }

        return $key;
    }
}
