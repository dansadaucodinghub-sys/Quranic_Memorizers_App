<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Application;

use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\QuranReferenceGovernance\Domain\QuranReleaseLifecycle;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Time\Clock;

final readonly class QuranReleaseLifecycleService
{
    public function __construct(
        private QuranReleaseLifecycleRepository $releases,
        private QuranReleaseLifecycle $lifecycle,
        private AuthorizationRequirementGuard $authorization,
        private StepUpGuard $stepUp,
        private IdentityRateLimiter $rateLimits,
        private IdentityFingerprintGenerator $fingerprints,
        private SecurityAuditEventAppender $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    public function transition(QuranReleaseTransitionCommand $command): QuranReleaseTransitionResult
    {
        $this->authorization->requireAllowed(new AuthorizationRequest(
            AuthorizationSubject::fromAuthenticatedContext($command->actor),
            new PermissionCode($command->action->permission()),
            new PlatformAuthorizationScope(),
        ));
        $policy = new IdentityRateLimitPolicy(60, 10, 60);
        if (
            !$this->rateLimits->consume([
            new IdentityRateLimitAttempt(IdentityRateLimitScope::QURAN_GOVERNANCE_MUTATION_ACCOUNT, $this->fingerprints->generate('quran-governance-account', (string) $command->actor->accountInternalId), $policy),
            new IdentityRateLimitAttempt(IdentityRateLimitScope::QURAN_GOVERNANCE_MUTATION_PEER, $this->fingerprints->generate('quran-governance-peer', (string) $command->actor->sessionInternalId), $policy),
            ], $this->clock->now())->allowed
        ) {
            throw new \DomainException('Qur’an release transition is temporarily unavailable.');
        }

        return $this->transactions->transactional(fn (): QuranReleaseTransitionResult => $this->inTransaction($command), TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(1, 0, 0)));
    }

    private function inTransaction(QuranReleaseTransitionCommand $command): QuranReleaseTransitionResult
    {
        $fingerprint = $this->fingerprints->generate('quran-release-transition', implode("\0", [$command->actor->accountInternalId, $command->releasePublicId->toString(), $command->expectedVersion, $command->action->value, $command->reasonCode ?? '']));
        $completed = $this->releases->findCompleted($command->submissionId, $fingerprint->toBinary());
        if ($completed !== null) {
            return $completed;
        }
        $release = $this->releases->lockRelease($command->releasePublicId);
        if ($release === null || $release['version'] !== $command->expectedVersion) {
            throw new \DomainException('Qur’an release transition is stale or unavailable.');
        }
        $target = $command->action->targetStatus();
        $this->lifecycle->assertTransition($release['status'], $target);
        if ($target === 'ACTIVE' && $this->releases->activeReleaseExists()) {
            throw new \DomainException('Qur’an release activation requires an explicit supersession operation.');
        }
        $now = $this->clock->now();
        $stepUp = $command->action->stepUpAction();
        $grant = $stepUp === null ? null : $this->stepUp->consumeWithGrant($command->actor, $stepUp);
        if (!$this->releases->transition($release, $target, $command->actor->accountInternalId, $now)) {
            throw new \DomainException('Qur’an release transition is stale.');
        }
        $correlation = $command->correlationId ?? bin2hex(random_bytes(16));
        $this->releases->appendEvent($release, $command->action->value, $target, $command->actor->accountInternalId, $command->reasonCode, $correlation, $now);
        $audit = $this->audit->platform($command->action->auditCode(), SecurityEventSubjectKind::QURAN_RELEASE, $command->releasePublicId->toString(), $command->actor->accountId->toString(), $now, ['release_code' => $release['release_code'], 'release_version' => $release['release_version'], 'previous_status' => $release['status'], 'new_status' => $target, 'version_before' => $release['version'], 'version_after' => $release['version'] + 1], $command->reasonCode, $correlation);
        $this->releases->record($command->submissionId, $fingerprint->toBinary(), $release, $command, $grant?->internalId, $audit->eventPublicId, $now);

        return new QuranReleaseTransitionResult($command->releasePublicId->toString(), $target, $release['version'] + 1, false);
    }
}
