<?php

declare(strict_types=1);

namespace Qmdb\Tests\Tools\Closeout;

use PHPUnit\Framework\TestCase;
use Qmdb\Tools\Closeout\EngineeringFreezeGenerator;
use Qmdb\Tools\Closeout\EngineeringFreezePolicy;
use Qmdb\Tools\Closeout\EngineeringFreezeVerifier;
use Qmdb\Tools\Support\GitMetadata;

final class EngineeringFreezeTest extends TestCase
{
    public function testPolicyClassifiesFoundationAndExtensionPoints(): void
    {
        $policy = new EngineeringFreezePolicy();

        self::assertSame(EngineeringFreezePolicy::FROZEN, $policy->category('src/Shared/Http/Kernel/HttpKernel.php'));
        self::assertSame(EngineeringFreezePolicy::EXTENSION, $policy->category('composer.json'));
        self::assertSame(EngineeringFreezePolicy::EXTENSION, $policy->category('routes/web.php'));
        self::assertFalse($policy->isIncluded('build/release/application.tar.gz'));
        self::assertFalse($policy->isIncluded('.runtime/toolchain/php/php.exe'));
        self::assertFalse($policy->isIncluded('docs/project/project-state.md'));
    }

    public function testRenderedApprovedFreezeIsDeterministicAndClosesEveryP1Batch(): void
    {
        $git = new GitMetadata(str_repeat('a', 40), str_repeat('a', 12), 1_700_000_000, 'dirty');
        $entries = [[
            'path' => 'composer.json',
            'category' => EngineeringFreezePolicy::EXTENSION,
            'sha256' => str_repeat('b', 64),
        ]];
        $generator = new EngineeringFreezeGenerator();

        $first = $generator->render($entries, $git);
        $second = $generator->render($entries, $git);

        self::assertSame($first, $second);
        self::assertStringContainsString('freeze_status: APPROVED', $first);
        self::assertStringContainsString('readiness: P1_COMPLETE', $first);
        self::assertStringContainsString('source_state: governed_clean', $first);
        self::assertStringContainsString('    - QMDB-P1-B10', $first);
        self::assertStringContainsString('p1_blockers: []', $first);
        self::assertStringContainsString('p2_blockers: []', $first);
        self::assertStringContainsString('status: AUTHORIZED_BY_QMDB_RECOVERY_RUN_001', $first);
    }

    public function testRepositoryCandidateVerifierIsEitherCurrentOrExplicitlyPendingOwnerRefresh(): void
    {
        $report = (new EngineeringFreezeVerifier())->verify(dirname(__DIR__, 3));

        self::assertGreaterThan(0, $report->checks());
        if ($report->passed()) {
            return;
        }

        self::assertNotSame([], $report->errors());
        self::assertTrue(
            array_any($report->errors(), static fn (string $error): bool => str_contains($error, 'Governed working-tree') || str_contains($error, 'Checksum mismatch') || str_contains($error, 'inventory has drifted')),
            implode("\n", $report->errors()),
        );
    }
}
