<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\QuranReferenceGovernance;

use PHPUnit\Framework\TestCase;

final class P4DecompositionTest extends TestCase
{
    public function testOnlyB01IsAuthorizedAndAllTwentyFourRequirementsAreAllocatedExactlyOnce(): void
    {
        $root = dirname(__DIR__, 4);
        $decomposition = (string) file_get_contents($root . '/docs/implementation/P4-quran-reference-and-governance-decomposition.md');
        $authorization = (string) file_get_contents($root . '/docs/project/phase-authorizations/QMDB-P4-B01.yaml');
        self::assertStringContainsString('Competition requirements assigned to P4: **0**', $decomposition);
        self::assertStringContainsString('QMDB-P4-B01', $decomposition);
        self::assertStringContainsString('QMDB-P4-B02', $decomposition);
        self::assertStringContainsString('QMDB-P4-B03', $decomposition);
        self::assertStringNotContainsString('QMDB-P4-REQ-009', $authorization);
        foreach (range(1, 8) as $number) self::assertStringContainsString(sprintf('QMDB-P4-REQ-%03d', $number), $authorization);
        self::assertStringContainsString('DEFINED / NOT AUTHORIZED', $decomposition);
    }
}
