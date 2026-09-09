<?php

declare(strict_types=1);

namespace Qmdb\Tests\Tools\Closeout;

use PHPUnit\Framework\TestCase;
use Qmdb\Tools\Closeout\P3GovernanceRefreshFreezePolicy;

final class P3GovernanceRefreshFreezePolicyTest extends TestCase
{
    public function testP4ReportsEnterTheLedgerEnforcedInventory(): void
    {
        $policy = new P3GovernanceRefreshFreezePolicy();

        self::assertTrue($policy->isIncluded('docs/implementation/reports/QMDB-P4-B01-scope-blocker-analysis.md'));
        self::assertFalse($policy->isIncluded('docs/implementation/reports/QMDB-P5-B01-scope-resolution.md'));
        self::assertFalse($policy->isIncluded('build/release/qmdb.tar.gz'));
    }
}
