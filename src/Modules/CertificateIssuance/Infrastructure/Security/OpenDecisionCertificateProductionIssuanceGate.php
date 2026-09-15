<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Infrastructure\Security;

use Qmdb\Modules\CertificateIssuance\Application\CertificateProductionIssuanceGate;

/**
 * Safe default for unresolved OD-017, OD-040, OD-065, and OD-066.
 * A future approved custody-and-format implementation must replace this
 * adapter through a governed source change; environment variables alone
 * cannot authorize production signing.
 */
final readonly class OpenDecisionCertificateProductionIssuanceGate implements CertificateProductionIssuanceGate
{
    public function __construct(private bool $production)
    {
    }

    public function assertPreparationPermitted(): void
    {
        $this->assertPermitted();
    }

    public function assertIssuancePermitted(): void
    {
        $this->assertPermitted();
    }

    private function assertPermitted(): void
    {
        if ($this->production) {
            throw new \DomainException('Production certificate issuance is unavailable pending governed authority decisions.');
        }
    }
}
