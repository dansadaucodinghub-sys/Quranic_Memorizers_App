<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Media;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\{IdentityFingerprint, IdentityFingerprintGenerator};
use Qmdb\Modules\IdentityAccess\Security\RateLimit\{IdentityRateLimiter, IdentityRateLimitDecision};
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\{AuthenticationAssuranceLevel, AuthenticationMethod, SessionAuthenticationAssurance, StepUpAction, StepUpGrant, StepUpGrantStatus};
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\StepUpGrantRepository;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentitySessions\Domain\{DeviceId, SessionId};
use Qmdb\Modules\MediaModeration\Application\{MediaGovernanceRepository, MediaGovernanceService};
use Qmdb\Modules\MediaModeration\Domain\{MediaGovernanceAction, MediaGovernanceRecord};
use Qmdb\Modules\SecurityAudit\Application\{SecurityAuditRecorder, SecurityAuditEventAppender, SecurityAuditAppendResult};
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\SecurityAuthorization\Application\Exception\AuthorizationDeniedException;
use Qmdb\Modules\Tenancy\Domain\{MembershipStatus, WorkspaceStatus};
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Modules\TenancyContext\Domain\{AccountWorkspaceTenantContext, ResolvedWorkspaceMembershipIdentity, TenantContextVersion};
use Qmdb\Shared\Database\Transaction\{TransactionManager, TransactionOptions};
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;

final class MediaGovernanceServiceTest extends TestCase
{
    /** @return array{AuthenticatedAccountContext,AccountWorkspaceTenantContext} */
    private function context(): array
    {
        $now = new DateTimeImmutable('2026-09-17T12:00:00Z');
        $actor = new AuthenticatedAccountContext(8, AccountId::generate(), 12, SessionId::generate(), 11, DeviceId::generate(), $now, 1, new SessionAuthenticationAssurance(AuthenticationMethod::PASSKEY, null, AuthenticationAssuranceLevel::PHISHING_RESISTANT, $now, $now));
        $tenant = AccountWorkspaceTenantContext::trusted(8, $actor->accountId, 12, $actor->sessionId, 4, WorkspaceId::generate(), WorkspaceStatus::ACTIVE, 1, ResolvedWorkspaceMembershipIdentity::trusted(13, UuidV7::generate(), 4, 8, MembershipStatus::ACTIVE, 1), 'Synthetic workspace', new TenantContextVersion(1), $now);
        return [$actor, $tenant];
    }

    public function testApprovalConsumesStepUpAndAppendsAuditInsideTheTransaction(): void
    {
        $this->runScenario('approve');
    }
    public function testReplayDoesNotConsumeAnotherGrantOrAppendAnEvent(): void
    {
        $this->runScenario('replay');
    }
    public function testMissingStepUpPreventsMutation(): void
    {
        $this->expectException(\DomainException::class);
        $this->runScenario('missing_grant');
    }
    public function testDeniedPermissionPreventsAnyPersistence(): void
    {
        $this->expectException(AuthorizationDeniedException::class);
        $this->runScenario('denied');
    }
    public function testRateLimitPreventsTheTransaction(): void
    {
        $this->expectException(\Qmdb\Modules\MediaModeration\Application\MediaGovernanceRateLimited::class);
        $this->runScenario('rate_limited');
    }
    public function testAuditFailurePropagatesInsteadOfReportingSuccess(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Synthetic audit failure');
        $this->runScenario('audit_failure');
    }

    private function runScenario(string $scenario): void
    {
        [$actor, $tenant] = $this->context();
        $assetId = UuidV7::generate();
        $repository = $this->createMock(MediaGovernanceRepository::class);
        $authorization = $this->createMock(AuthorizationRequirementGuard::class);
        $authorization->expects(self::once())->method('requireAllowed');
        if ($scenario === 'denied') {
            $authorization->method('requireAllowed')->willThrowException(new AuthorizationDeniedException());
        }
        $limits = $this->createMock(IdentityRateLimiter::class);
        $limits->expects($scenario === 'denied' ? self::never() : self::once())->method('consume')->willReturn($scenario === 'rate_limited' ? IdentityRateLimitDecision::throttled(60) : IdentityRateLimitDecision::allowed());
        $fingerprints = $this->createStub(IdentityFingerprintGenerator::class);
        $fingerprints->method('generate')->willReturnCallback(static fn (string $domain, string $value): IdentityFingerprint => new IdentityFingerprint(hash('sha256', $domain . $value, true)));
        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturn($actor->authenticatedAt);
        $grants = $this->createMock(StepUpGrantRepository::class);
        $grant = new StepUpGrant(1, UuidV7::generate()->toString(), 8, 12, StepUpAction::MEDIA_APPROVE, AuthenticationAssuranceLevel::PHISHING_RESISTANT, StepUpGrantStatus::ACTIVE, $actor->authenticatedAt, $actor->authenticatedAt->modify('+5 minutes'), null, null, 1);
        $mutates = in_array($scenario, ['approve','audit_failure'], true);
        $grants->expects($mutates || $scenario === 'missing_grant' ? self::once() : self::never())->method('findActiveGrant')->willReturn($scenario === 'missing_grant' ? null : $grant);
        $grants->expects($mutates ? self::once() : self::never())->method('consumeGrant')->willReturn(true);
        $transaction = $this->createMock(TransactionManager::class);
        $transaction->expects(in_array($scenario, ['denied','rate_limited'], true) ? self::never() : self::once())->method('transactional')->willReturnCallback(static function (callable $operation, ?TransactionOptions $options = null): mixed {
            self::assertNotNull($options);
            return $operation();
        });
        $repository->expects(in_array($scenario, ['denied','rate_limited'], true) ? self::never() : self::once())->method('find')->with(4, $assetId, true)->willReturn(new MediaGovernanceRecord(5, $assetId->toString(), 4, 7, 'PENDING_MODERATION', 3, true, true, false, true, true));
        $repository->method('replay')->willReturn($scenario === 'replay' ? ['asset_id' => $assetId->toString(),'status' => 'APPROVED','version' => 4] : null);
        $repository->expects($mutates ? self::once() : self::never())->method('apply');
        $repository->expects($mutates ? self::once() : self::never())->method('record');
        $recorder = $this->createMock(SecurityAuditRecorder::class);
        $append = $recorder->expects($mutates ? self::once() : self::never())->method('append');
        if ($scenario === 'audit_failure') {
            $append->willThrowException(new \RuntimeException('Synthetic audit failure'));
        } else {
            $append->willReturn(new SecurityAuditAppendResult(UuidV7::generate()->toString(), UuidV7::generate()->toString(), 1));
        }
        $service = new MediaGovernanceService($repository, $authorization, new StepUpGuard($grants, $clock), $limits, $fingerprints, new SecurityAuditEventAppender($recorder), $transaction, $clock);
        self::assertSame(['asset_id' => $assetId->toString(),'status' => 'APPROVED','version' => 4], $service->execute($actor, $tenant, UuidV7::generate(), $assetId, 3, MediaGovernanceAction::APPROVE, 'REVIEW_APPROVED'));
    }
}
