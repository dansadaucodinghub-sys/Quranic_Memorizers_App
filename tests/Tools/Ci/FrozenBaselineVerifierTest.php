<?php

declare(strict_types=1);

namespace Qmdb\Tests\Tools\Ci;

use PHPUnit\Framework\TestCase;
use Qmdb\Tools\Ci\FrozenBaselineVerifier;

final class FrozenBaselineVerifierTest extends TestCase
{
    public function testRepositoryFrozenBaselineStillMatchesAllGovernedHashes(): void
    {
        $report = (new FrozenBaselineVerifier(dirname(__DIR__, 3)))->verify();
        self::assertTrue($report->passed(), implode("\n", $report->errors()));
        self::assertGreaterThanOrEqual(170, $report->checks());
    }

    public function testP3B02DocumentationExtensionIsBoundedAndPreservesTheHistoricalFreeze(): void
    {
        $ledger = file_get_contents(dirname(__DIR__, 3) . '/docs/project/p3-p0-freeze-extension-ledger.yaml');

        self::assertIsString($ledger);
        self::assertStringContainsString('ledger_id: QMDB-P3-P0-EXT-001', $ledger);
        self::assertStringContainsString('authorization: QMDB-P3-B02-EXEC', $ledger);
        self::assertStringContainsString('baseline_id: QMDB-P0-FRZ-001', $ledger);
        self::assertStringContainsString('preserves_historical_baseline: true', $ledger);
        self::assertSame(15, preg_match_all('/^    - path: /m', $ledger));
    }

    public function testP3B05DocumentationExtensionBuildsOnlyOnApprovedP3B02Records(): void
    {
        $ledger = file_get_contents(dirname(__DIR__, 3) . '/docs/project/p3-p0-b05-freeze-extension-ledger.yaml');

        self::assertIsString($ledger);
        self::assertStringContainsString('ledger_id: QMDB-P3-P0-EXT-002', $ledger);
        self::assertStringContainsString('authorization: QMDB-P3-B05-EXEC', $ledger);
        self::assertStringContainsString('baseline_id: QMDB-P0-FRZ-001', $ledger);
        self::assertStringContainsString('preserves_historical_baseline: true', $ledger);
        self::assertSame(15, preg_match_all('/^    - path: /m', $ledger));
    }
}
