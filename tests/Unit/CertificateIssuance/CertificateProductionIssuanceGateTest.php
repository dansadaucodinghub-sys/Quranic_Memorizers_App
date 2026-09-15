<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\CertificateIssuance;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\CertificateIssuance\Infrastructure\Security\OpenDecisionCertificateProductionIssuanceGate;

final class CertificateProductionIssuanceGateTest extends TestCase
{
    public function testLocalAndTestEnvironmentsRetainFoundationCoverage(): void
    {
        $gate = new OpenDecisionCertificateProductionIssuanceGate(false);
        $gate->assertPreparationPermitted();
        $gate->assertIssuancePermitted();
        self::addToAssertionCount(1);
    }

    public function testProductionPreparationAndIssuanceFailClosedUntilGovernedAuthorityExists(): void
    {
        $gate = new OpenDecisionCertificateProductionIssuanceGate(true);
        try {
            $gate->assertPreparationPermitted();
            self::fail('Production preparation was allowed while authority decisions are open.');
        } catch (\DomainException $error) {
            self::assertStringContainsString('governed authority decisions', $error->getMessage());
        }
        $this->expectException(\DomainException::class);
        $gate->assertIssuancePermitted();
    }
}
