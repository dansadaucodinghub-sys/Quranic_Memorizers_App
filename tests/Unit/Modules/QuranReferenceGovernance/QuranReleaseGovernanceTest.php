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
}
