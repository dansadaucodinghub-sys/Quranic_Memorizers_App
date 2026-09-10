<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\QuranReferenceGovernance;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\QuranReferenceGovernance\Domain\QuranReleaseAction;
use Qmdb\Modules\QuranReferenceGovernance\Domain\QuranReleaseLifecycle;
use Qmdb\Modules\QuranReferenceGovernance\Domain\QuranSourceArtifactPathGuard;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;

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

    public function testLifecycleRejectsImplicitActivationAndTerminalTransitions(): void
    {
        $lifecycle = new QuranReleaseLifecycle();

        foreach ([['DRAFT', 'ACTIVE'], ['REJECTED', 'STAGED'], ['SUPERSEDED', 'ACTIVE']] as [$from, $to]) {
            try {
                $lifecycle->assertTransition($from, $to);
                self::fail(sprintf('Unexpected lifecycle transition: %s -> %s', $from, $to));
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testLifecycleActionsUseNarrowPermissionsAndActionBoundSecurityControls(): void
    {
        self::assertSame('platform.quran_releases.manage', QuranReleaseAction::STAGE->permission());
        self::assertSame('platform.quran_releases.manage', QuranReleaseAction::VALIDATE->permission());
        self::assertSame('platform.quran_releases.approve', QuranReleaseAction::APPROVE->permission());
        self::assertSame('platform.quran_releases.activate', QuranReleaseAction::ACTIVATE->permission());
        self::assertSame(StepUpAction::QURAN_RELEASE_APPROVE, QuranReleaseAction::APPROVE->stepUpAction());
        self::assertSame(StepUpAction::QURAN_RELEASE_ACTIVATE, QuranReleaseAction::ACTIVATE->stepUpAction());
        self::assertSame(StepUpAction::QURAN_RELEASE_REJECT, QuranReleaseAction::REJECT->stepUpAction());
        self::assertNull(QuranReleaseAction::STAGE->stepUpAction());
        self::assertSame(SecurityEventCode::QURAN_RELEASE_ACTIVATED, QuranReleaseAction::ACTIVATE->auditCode());
    }

    public function testArtifactPathGuardRejectsAbsoluteAndTraversalInputs(): void
    {
        $guard = new QuranSourceArtifactPathGuard();
        foreach (['C:\\private.txt', '../artifact.txt', "artifact\0.txt"] as $invalid) {
            try {
                $guard->resolve(__DIR__, $invalid);
                self::fail('Unsafe path was accepted.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
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
