<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Community;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\Community\Application\CommunityGlobalReceipts;
use Qmdb\Modules\Community\Application\CommunityProfileRepository;
use Qmdb\Modules\Community\Application\CommunityProfileService;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprint;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitDecision;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\SessionAuthenticationAssurance;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentitySessions\Domain\DeviceId;
use Qmdb\Modules\IdentitySessions\Domain\SessionId;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\Tenancy\Domain\MembershipStatus;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Modules\Tenancy\Domain\WorkspaceStatus;
use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;
use Qmdb\Modules\TenancyContext\Domain\ResolvedWorkspaceMembershipIdentity;
use Qmdb\Modules\TenancyContext\Domain\TenantContextVersion;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;

final class CommunityProfileServiceTest extends TestCase
{
    public function testCompletedCreateReplayDoesNotRateLimitOrCreateAgain(): void
    {
        $now = new DateTimeImmutable('2026-09-22T12:00:00Z');
        $actor = new AuthenticatedAccountContext(
            8,
            AccountId::generate(),
            12,
            SessionId::generate(),
            11,
            DeviceId::generate(),
            $now,
            1,
            new SessionAuthenticationAssurance(
                AuthenticationMethod::PASSKEY,
                null,
                AuthenticationAssuranceLevel::PHISHING_RESISTANT,
                $now,
                $now
            )
        );
        $tenant = AccountWorkspaceTenantContext::trusted(
            8,
            $actor->accountId,
            12,
            $actor->sessionId,
            4,
            WorkspaceId::generate(),
            WorkspaceStatus::ACTIVE,
            1,
            ResolvedWorkspaceMembershipIdentity::trusted(
                13,
                UuidV7::generate(),
                4,
                8,
                MembershipStatus::ACTIVE,
                1
            ),
            'Synthetic workspace',
            new TenantContextVersion(1),
            $now
        );
        $submission = UuidV7::generate();
        $profile = UuidV7::generate();
        $result = ['public_id' => $profile->toString(), 'status' => 'PRIVATE', 'version' => 1];
        $repository = $this->createMock(CommunityProfileRepository::class);
        $repository->expects(self::once())->method('createPrivate')->willReturn(
            ['public_id' => $profile->toString(), 'visibility' => 'PRIVATE', 'version' => 1]
        );
        $receipts = $this->createMock(CommunityGlobalReceipts::class);
        $receipts->expects(self::exactly(2))->method('completedForActor')
            ->willReturnOnConsecutiveCalls(null, $result);
        $receipts->expects(self::once())->method('claim')->willReturn(null);
        $receipts->expects(self::once())->method('complete');
        $limiter = $this->createMock(IdentityRateLimiter::class);
        $limiter->expects(self::once())->method('consume')->willReturn(IdentityRateLimitDecision::allowed());
        $fingerprints = $this->createStub(IdentityFingerprintGenerator::class);
        $fingerprints->method('generate')->willReturn(new IdentityFingerprint(str_repeat('x', 32)));
        $transactions = $this->createStub(TransactionManager::class);
        $transactions->method('transactional')->willReturnCallback(
            static fn (callable $operation): mixed => $operation()
        );
        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturn($now);
        $service = new CommunityProfileService(
            $repository,
            $this->createStub(AuthorizationRequirementGuard::class),
            $transactions,
            $clock,
            $receipts,
            $limiter,
            $fingerprints
        );
        self::assertSame($result, $service->save($actor, $tenant, $submission, null, 0, 'Reciter', 'PRIVATE'));
        self::assertSame($result, $service->save($actor, $tenant, $submission, null, 0, 'Reciter', 'PRIVATE'));
    }
}
