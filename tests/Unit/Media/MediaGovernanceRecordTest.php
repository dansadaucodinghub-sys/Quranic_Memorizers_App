<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Media;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\MediaModeration\Domain\{MediaGovernanceAction, MediaGovernanceRecord};

final class MediaGovernanceRecordTest extends TestCase
{
    private function asset(string $status = 'PENDING_MODERATION', bool $rights = true, bool $consent = true, bool $held = false, bool $clean = true, bool $processed = true): MediaGovernanceRecord
    {
        return new MediaGovernanceRecord(1, '019941f8-75a0-7000-8000-000000000001', 1, 7, $status, 3, $rights, $consent, $held, $clean, $processed);
    }

    public function testIndependentReviewRequiresAllEvidence(): void
    {
        self::assertSame('APPROVED', $this->asset()->target(MediaGovernanceAction::APPROVE, 8));
        self::assertSame('REJECTED', $this->asset()->target(MediaGovernanceAction::REJECT, 8));
    }

    /** @return iterable<string,array{string,bool,bool,bool,bool,bool,int}> */
    public static function approvalFailures(): iterable
    {
        yield 'self approval' => ['PENDING_MODERATION', true, true, false, true, true, 7];
        yield 'rights absent' => ['PENDING_MODERATION', false, true, false, true, true, 8];
        yield 'consent absent' => ['PENDING_MODERATION', true, false, false, true, true, 8];
        yield 'held' => ['PENDING_MODERATION', true, true, true, true, true, 8];
        yield 'scan not clean' => ['PENDING_MODERATION', true, true, false, false, true, 8];
        yield 'variant absent' => ['PENDING_MODERATION', true, true, false, true, false, 8];
        foreach (['STAGING','QUARANTINED','SCANNING','PROCESSING','APPROVED','PUBLISHED','WITHDRAWN','ARCHIVED','REJECTED'] as $state) {
            yield $state => [$state, true, true, false, true, true, 8];
        }
    }

    #[DataProvider('approvalFailures')]
    public function testUnsafeApprovalFails(string $status, bool $rights, bool $consent, bool $held, bool $clean, bool $processed, int $actor): void
    {
        $this->expectException(\DomainException::class);
        $this->asset($status, $rights, $consent, $held, $clean, $processed)->target(MediaGovernanceAction::APPROVE, $actor);
    }

    public function testWithdrawalWorksWhileHeldWithoutGrantingPublication(): void
    {
        self::assertSame('WITHDRAWN', $this->asset('APPROVED', held: true)->target(MediaGovernanceAction::WITHDRAW_CONSENT, 7));
        self::assertSame('ARCHIVED', $this->asset('ARCHIVED')->target(MediaGovernanceAction::WITHDRAW_CONSENT, 7));
        self::assertSame('WITHDRAWN', $this->asset('WITHDRAWN', held: true)->target(MediaGovernanceAction::RELEASE_HOLD, 8));
    }

    public function testAnotherCreatorCannotWithdrawConsent(): void
    {
        $this->expectException(\DomainException::class);
        $this->asset()->target(MediaGovernanceAction::WITHDRAW_CONSENT, 8);
    }

    public function testHeldMediaCannotBeArchived(): void
    {
        $this->expectException(\DomainException::class);
        $this->asset('WITHDRAWN', held: true)->target(MediaGovernanceAction::ARCHIVE, 8);
    }

    public function testEveryActionHasAnExactCsrfAndPermissionContract(): void
    {
        foreach (MediaGovernanceAction::cases() as $action) {
            self::assertStringStartsWith('media.', $action->csrf()->value);
            self::assertStringNotContainsString('*', $action->permission());
            if ($action !== MediaGovernanceAction::WITHDRAW_CONSENT) {
                self::assertSame('PHISHING_RESISTANT', $action->stepUp()?->requirement()->value);
            }
        }
    }

    public function testConsentReviewDoesNotApproveOrPublishMedia(): void
    {
        self::assertSame('PENDING_MODERATION', $this->asset(rights: false, consent: false)->target(MediaGovernanceAction::GRANT_CONSENT, 8));
    }

    public function testUploaderCannotAttestIndependentConsentReview(): void
    {
        $this->expectException(\DomainException::class);
        $this->asset()->target(MediaGovernanceAction::GRANT_CONSENT, 7);
    }

    public function testHoldBlocksConsentGrant(): void
    {
        $this->expectException(\DomainException::class);
        $this->asset(held: true)->target(MediaGovernanceAction::GRANT_CONSENT, 8);
    }

    public function testConsentGrantCannotReactivateWithdrawnMedia(): void
    {
        $this->expectException(\DomainException::class);
        $this->asset('WITHDRAWN')->target(MediaGovernanceAction::GRANT_CONSENT, 8);
    }
}
