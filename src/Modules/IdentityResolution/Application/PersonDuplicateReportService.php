<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityAccess\Domain\IdempotencyClaimStatus;
use Qmdb\Modules\IdentityAccess\Domain\Repository\IdentityAccessRepository;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityResolution\Configuration\IdentityResolutionConfiguration;
use Qmdb\Modules\IdentityResolution\Infrastructure\Persistence\MySqlIdentityResolutionRepository;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\People\Application\PeopleProfileSecurityNotificationService;
use Qmdb\Modules\People\Application\PersonCanonicalIdentityResolver;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;

final readonly class PersonDuplicateReportService
{
    public function __construct(
        private MySqlIdentityResolutionRepository $repository,
        private PersonCanonicalIdentityResolver $canonical,
        private IdentityAccessRepository $idempotency,
        private IdentityRateLimiter $rateLimiter,
        private IdentityFingerprintGenerator $fingerprints,
        private AuthorizationRequirementGuard $authorization,
        private StepUpGuard $stepUp,
        private PeopleProfileSecurityNotificationService $notifications,
        private SecurityAuditEventAppender $audit,
        private IdentityResolutionConfiguration $configuration,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    public function reportForAccount(AuthenticatedAccountContext $actor, PersonDuplicateReportCommand $command, string $peerFingerprint = 'private'): PersonDuplicateReportResult
    {
        if ($command->managedPersonPublicId === null) {
            throw new \InvalidArgumentException('A managed Person is required.');
        }

        return $this->report($actor, $command, false, $peerFingerprint);
    }

    public function reportForPlatform(AuthenticatedAccountContext $actor, string $firstRegistryCode, string $secondRegistryCode, \Qmdb\Modules\IdentityResolution\Domain\IdentityResolutionSubmissionId $submission, string $peerFingerprint = 'private'): PersonDuplicateReportResult
    {
        $this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor), new PermissionCode('platform.people_duplicates.view'), new PlatformAuthorizationScope()));
        return $this->report($actor, new PersonDuplicateReportCommand(null, $secondRegistryCode, $submission), true, $peerFingerprint, $firstRegistryCode);
    }

    private function report(AuthenticatedAccountContext $actor, PersonDuplicateReportCommand $command, bool $platform, string $peerFingerprint, ?string $platformFirstRegistryCode = null): PersonDuplicateReportResult
    {
        if (
            !$this->rateLimiter->consume([
            new IdentityRateLimitAttempt(IdentityRateLimitScope::PROFILE_DUPLICATE_REPORT_ACCOUNT, $this->fingerprints->generate('profile-duplicate-report-account', (string) $actor->accountInternalId), $this->policy()),
            new IdentityRateLimitAttempt(IdentityRateLimitScope::PROFILE_DUPLICATE_REPORT_PEER, $this->fingerprints->generate('profile-duplicate-report-peer', $peerFingerprint), $this->policy()),
            ], $this->clock->now())->allowed
        ) {
            throw new \DomainException('Duplicate report is temporarily unavailable.');
        }

        return $this->transactions->transactional(function () use ($actor, $command, $platform, $platformFirstRegistryCode): PersonDuplicateReportResult {
            $now = $this->clock->now();
            $first = $platform ? $this->repository->personByRegistryCode((string) $platformFirstRegistryCode, true) : $this->repository->personByPublicId((string) $command->managedPersonPublicId, true);
            $second = $this->repository->personByRegistryCode($command->otherPersonRegistryCode, true);
            if ($first === null || $second === null || $first['status'] !== 'ACTIVE' || $second['status'] !== 'ACTIVE') {
                throw new \DomainException('Duplicate report is unavailable.');
            }
            $firstId = $this->canonical->resolve((int) $first['id']);
            $secondId = $this->canonical->resolve((int) $second['id']);
            if ($firstId === $secondId || $this->repository->aliasForSource($firstId) !== null || $this->repository->aliasForSource($secondId) !== null) {
                throw new \DomainException('Duplicate report is unavailable.');
            }
            $authority = null;
            if (!$platform) {
                $self = $this->repository->activeSelfLinkForAccount($actor->accountInternalId, true);
                if ($self !== null && (int) $self['person_id'] === $firstId) {
                    $authority = ['type' => 'SELF', 'guardianship_id' => null];
                } else {
                    $guardian = $this->repository->guardianAuthority($actor->accountInternalId, $firstId, true);
                    if ($guardian === null) {
                        throw new \DomainException('Duplicate report is unavailable.');
                    }
                    $authority = ['type' => 'GUARDIAN', 'guardianship_id' => (int) $guardian['guardianship_id']];
                }
            }
            $this->stepUp->consumeWithGrant($actor, StepUpAction::PROFILE_DUPLICATE_REPORT);
            $ordered = [$firstId, $secondId];
            sort($ordered, SORT_NUMERIC);
            $idempotency = $this->idempotency->claimIdempotency($command->submission, 'PROFILE_DUPLICATE_REPORT', $this->fingerprints->generate('profile-duplicate-report-idempotency', $actor->accountInternalId . "\0" . implode(':', $ordered) . "\0" . ($platform ? 'PLATFORM' : $authority['type'])), $now);
            if ($idempotency === IdempotencyClaimStatus::CONFLICT) {
                throw new \DomainException('Duplicate report is unavailable.');
            }
            if ($idempotency === IdempotencyClaimStatus::REPLAY) {
                $replayed = $this->repository->duplicateCaseForReporterPair(
                    $ordered[0],
                    $ordered[1],
                    $actor->accountInternalId,
                    true,
                );
                if ($replayed === null) {
                    throw new \DomainException('Duplicate report replay is unavailable.');
                }

                return new PersonDuplicateReportResult((string) $replayed['public_id'], (string) $replayed['status'], true);
            }
            if ($this->repository->openDuplicateCase($ordered[0], $ordered[1], true) !== null) {
                throw new \DomainException('Duplicate report is unavailable.');
            }
            $authorities = array_merge($this->repository->managementAuthorities($ordered[0]), $this->repository->managementAuthorities($ordered[1]));
            if (count($authorities) > $this->configuration->maximumConsentAuthorities) {
                throw new \DomainException('Duplicate report is unavailable.');
            }
            $blocked = $this->repository->managementAuthorities($ordered[0]) === [] || $this->repository->managementAuthorities($ordered[1]) === [];
            $case = $this->repository->createDuplicateCase(UuidV7::generate()->toString(), $ordered[0], $ordered[1], $actor->accountInternalId, $platform ? 'PLATFORM_REVIEWER' : $authority['type'], $platform ? null : $authority['guardianship_id'], $blocked ? 'BLOCKED' : 'CONSENT_REQUIRED', $blocked ? 'CONSENT_UNAVAILABLE' : null, $blocked ? 'CONSENT_UNAVAILABLE' : null, $now);
            if (!$blocked) {
                $this->repository->createConsentRequirements($case, $authorities, $now);
                foreach ($authorities as $manager) {
                    $this->notifications->create($manager['account_id'], \Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType::PROFILE_DUPLICATE_CONSENT_REQUIRED, (string) $case['public_id'], $now);
                }
            }
            $this->audit->platform(SecurityEventCode::PERSON_DUPLICATE_REPORTED, SecurityEventSubjectKind::PERSON_DUPLICATE_CASE, (string) $case['public_id'], $actor->accountId->toString(), $now, ['duplicate_case_public_id' => $case['public_id'], 'consent_requirement_count' => count($authorities)], 'PERSON_DUPLICATE_REPORTED');
            $this->idempotency->completeIdempotency($command->submission, $now);

            return new PersonDuplicateReportResult((string) $case['public_id'], (string) $case['status'], false);
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    private function policy(): IdentityRateLimitPolicy
    {
        return new IdentityRateLimitPolicy($this->configuration->duplicateWindowSeconds, $this->configuration->duplicateMaximumAttempts, $this->configuration->duplicateWindowSeconds);
    }
}
