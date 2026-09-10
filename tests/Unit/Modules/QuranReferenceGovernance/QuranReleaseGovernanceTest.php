<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\QuranReferenceGovernance;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\QuranReferenceGovernance\Domain\QuranReleaseLifecycle;
use Qmdb\Modules\QuranReferenceGovernance\Domain\QuranSourceArtifactPathGuard;

final class QuranReleaseGovernanceTest extends TestCase
{
    public function testLifecycleAllowsOnlyExplicitForwardTransitions(): void
    {
        $lifecycle = new QuranReleaseLifecycle();
        $lifecycle->assertTransition('DRAFT', 'STAGED');
        $lifecycle->assertTransition('APPROVED', 'ACTIVE');
        self::assertTrue($lifecycle->isTerminal('REJECTED'));
        $this->expectException(InvalidArgumentException::class);
        $lifecycle->assertTransition('ACTIVE', 'DRAFT');
    }

    public function testArtifactPathGuardRejectsAbsoluteAndTraversalInputs(): void
    {
        $guard = new QuranSourceArtifactPathGuard();
        foreach (['C:\\private.txt', '../artifact.txt', "artifact\0.txt"] as $invalid) {
            try { $guard->resolve(__DIR__, $invalid); self::fail('Unsafe path was accepted.'); } catch (InvalidArgumentException) { self::addToAssertionCount(1); }
        }
    }

    public function testArtifactPathGuardAcceptsOnlyAnExistingChildFile(): void
    {
        $directory = sys_get_temp_dir() . '/qmdb-quran-artifact-' . bin2hex(random_bytes(4));
        mkdir($directory, 0700, true);
        $path = $directory . '/source.txt';
        file_put_contents($path, 'metadata-only-fixture');
        try {
            self::assertSame(realpath($path), (new QuranSourceArtifactPathGuard())->resolve($directory, 'source.txt'));
        } finally {
            unlink($path);
            rmdir($directory);
        }
    }
}
