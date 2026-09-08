<?php

declare(strict_types=1);

namespace Qmdb\Tests\Tools\Closeout;

use PHPUnit\Framework\TestCase;
use Qmdb\Tools\Closeout\P3FreezeGenerator;
use Qmdb\Tools\Closeout\P3FreezePolicy;
use Qmdb\Tools\Support\GitMetadata;

final class P3FreezeTest extends TestCase
{
    public function testPolicyGovernsP3EvidenceAndExcludesItsSelfReferentialManifest(): void
    {
        $policy = new P3FreezePolicy();

        self::assertSame(P3FreezePolicy::FROZEN, $policy->category('src/Modules/People/Application/PersonProfileService.php'));
        self::assertSame(P3FreezePolicy::EXTENSION, $policy->category('docs/closeout/p3/01-executive-closeout-summary.md'));
        self::assertTrue($policy->isIncluded('docs/implementation/reports/QMDB-P3-B06-implementation-report.md'));
        self::assertFalse($policy->isIncluded('docs/closeout/p3/qmdb-p3-people-geography-organizations-participation-freeze.yaml'));
        self::assertFalse($policy->isIncluded('build/release/application.tar.gz'));
    }

    public function testRenderedP3FreezeIsDeterministicAndRecordsTheP3Boundary(): void
    {
        $generator = new P3FreezeGenerator();
        $git = new GitMetadata(str_repeat('a', 40), str_repeat('a', 12), 1_700_000_000, 'clean');
        $entries = [[
            'path' => 'src/Modules/People/Application/PersonProfileService.php',
            'category' => P3FreezePolicy::FROZEN,
            'sha256' => str_repeat('b', 64),
        ]];

        $first = $generator->render($entries, $git);
        $second = $generator->render($entries, $git);

        self::assertSame($first, $second);
        self::assertStringContainsString('freeze_id: QMDB-P3-FRZ-001', $first);
        self::assertStringContainsString('closeout: QMDB-P3-CLOSE', $first);
        self::assertStringContainsString('status: FROZEN', $first);
        self::assertStringContainsString('source_revision: "' . str_repeat('a', 40) . '"', $first);
        self::assertStringContainsString('    - QMDB-P3-B06', $first);
    }
}
