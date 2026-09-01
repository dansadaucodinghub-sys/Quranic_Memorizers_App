<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Application;

use DateTimeImmutable;
use Qmdb\Modules\IdentityAccess\Domain\IdempotencyClaimStatus;
use Qmdb\Modules\IdentityAccess\Domain\Repository\IdentityAccessRepository;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\People\Configuration\PeopleProfilesConfiguration;
use Qmdb\Modules\People\Domain\PersonProfileSubmissionId;
use Qmdb\Modules\People\Domain\PersonPublicId;
use Qmdb\Modules\People\Domain\PersonBirthDate;
use Qmdb\Modules\People\Domain\PersonProfileAccessPolicy;
use Qmdb\Modules\People\Domain\PersonRegistryCodeGenerator;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditAppendCommand;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditRecorder;
use Qmdb\Modules\SecurityAudit\Domain\SecurityAuditStreamIdentity;
use Qmdb\Modules\SecurityAudit\Domain\SecurityAuditStreamType;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventActorKind;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventOutcome;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;

final readonly class PersonProfileService
{
    public function __construct(
        private PersonProfileRepository $profiles,
        private IdentityAccessRepository $idempotency,
        private IdentityRateLimiter $rateLimiter,
        private IdentityFingerprintGenerator $fingerprints,
        private StepUpGuard $stepUp,
        private SecurityAuditRecorder $audit,
        private PeopleProfileSecurityNotificationService $notifications,
        private PersonRegistryCodeGenerator $registryCodes,
        private PeopleProfilesConfiguration $configuration,
        private PersonProfileAccessPolicy $accessPolicy,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    /** @return array<string, int|string>|null */
    public function profile(AuthenticatedAccountContext $actor): ?array
    {
        $profile = $this->profiles->profileForAccount($actor->accountInternalId);

        return $this->accessPolicy->self($profile)->allowed ? $profile : null;
    }

    public function createSelf(AuthenticatedAccountContext $actor, PersonProfileSubmissionId $submission, PersonProfileInput $input): PersonProfileResult
    {
        $this->consumeMutationRate($actor, false);

        return $this->transactions->transactional(function () use ($actor, $submission, $input): PersonProfileResult {
            $now = $this->clock->now();
            $claim = $this->claim($submission, 'PERSON_PROFILE_CREATE', $actor, $input->name->searchName($this->configuration->searchNameMaximumBytes));
            $existing = $this->profiles->profileForAccount($actor->accountInternalId, true);
            if ($claim === IdempotencyClaimStatus::REPLAY) {
                if ($existing === null) {
                    throw new \DomainException('Profile creation replay is incomplete.');
                }

                return $this->result($existing, true);
            }
            if ($claim === IdempotencyClaimStatus::CONFLICT) {
                throw new \DomainException('Profile submission conflicts with a prior request.');
            }
            if ($existing !== null) {
                throw new \DomainException('An Account can have only one active self Person link.');
            }
            $person = PersonPublicId::generate();
            $profile = $this->profiles->createSelf(
                $actor->accountInternalId,
                $person->toBinary(),
                $this->registryCodes->generate()->value(),
                UuidV7::generate()->toBinary(),
                UuidV7::generate()->toBinary(),
                $input,
                $now,
            );
            $this->provisionInitialRoles($actor, $profile, $input, 'SELF_DECLARED', $now);
            $this->profiles->recordPersonOperation($submission, 'PERSON_PROFILE_CREATE', (int) $profile['id'], $now);
            $this->appendAudit($actor, $person, SecurityEventCode::PERSON_PROFILE_CREATED, SecurityEventSubjectKind::PERSON, ['profile_version' => 1], $now);
            $this->notifications->create($actor->accountInternalId, AccountSecurityNotificationType::PERSON_PROFILE_CREATED, $person->toString(), $now);
            $this->idempotency->completeIdempotency($submission, $now);

            return $this->result($profile);
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    public function updateSelf(AuthenticatedAccountContext $actor, PersonProfileSubmissionId $submission, PersonProfileInput $input, int $expectedVersion, int $expectedNameVersion): PersonProfileResult
    {
        $this->consumeMutationRate($actor, false);

        return $this->transactions->transactional(function () use ($actor, $submission, $input, $expectedVersion, $expectedNameVersion): PersonProfileResult {
            $now = $this->clock->now();
            $claim = $this->claim($submission, 'PERSON_PROFILE_UPDATE', $actor, $expectedVersion . "\0" . $expectedNameVersion . "\0" . $input->searchName);
            $person = $this->requiredPerson($actor);
            if ($claim === IdempotencyClaimStatus::REPLAY) {
                return $this->result($person, true);
            }
            if ($claim === IdempotencyClaimStatus::CONFLICT) {
                throw new \DomainException('Profile submission conflicts with a prior request.');
            }
            if ($this->profiles->profileMatchesInput($person, $input)) {
                $this->idempotency->completeIdempotency($submission, $now);

                return $this->result($person);
            }
            $this->stepUp->consumeWithGrant($actor, StepUpAction::PERSON_PROFILE_SENSITIVE_UPDATE);
            $updated = $this->profiles->updatePerson($person, UuidV7::generate()->toBinary(), $input, $expectedVersion, $expectedNameVersion, 'SELF_DECLARED', $now);
            $personId = PersonPublicId::fromBinary((string) $updated['public_id']);
            $this->appendAudit($actor, $personId, SecurityEventCode::PERSON_PROFILE_UPDATED, SecurityEventSubjectKind::PERSON, ['profile_version' => (int) $updated['version']], $now);
            $this->notifications->create($actor->accountInternalId, AccountSecurityNotificationType::PERSON_PROFILE_UPDATED, $personId->toString(), $now);
            $this->idempotency->completeIdempotency($submission, $now);

            return $this->result($updated);
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    /** @return array<string, int|string> */
    public function changeRole(AuthenticatedAccountContext $actor, PersonProfileSubmissionId $submission, string $roleType, bool $activate, int $expectedVersion = 0): array
    {
        if (!in_array($roleType, ['MEMORIZER', 'RECITER', 'COMPETITOR', 'GUARDIAN'], true)) {
            throw new \InvalidArgumentException('Person role type is invalid.');
        }
        $this->consumeMutationRate($actor, false);

        return $this->transactions->transactional(function () use ($actor, $submission, $roleType, $activate, $expectedVersion): array {
            $now = $this->clock->now();
            $operation = $activate ? 'PERSON_ROLE_ACTIVATE' : 'PERSON_ROLE_DEACTIVATE';
            $claim = $this->claim($submission, $operation, $actor, $roleType . "\0" . $expectedVersion);
            $person = $this->requiredPerson($actor);
            if ($claim === IdempotencyClaimStatus::REPLAY) {
                $replayed = $this->profiles->roleForPerson($person, $roleType, true);
                if ($replayed === null) {
                    throw new \DomainException('Role replay is incomplete.');
                }

                return $replayed;
            }
            if ($claim === IdempotencyClaimStatus::CONFLICT) {
                throw new \DomainException('Role submission conflicts with a prior request.');
            }
            if (!$activate && $roleType === 'GUARDIAN' && $this->profiles->activeDependentCount($person) > 0) {
                throw new \DomainException('Guardian role cannot be deactivated while active dependent relationships exist.');
            }
            $role = $activate
                ? $this->profiles->activateRole($person, $roleType, UuidV7::generate()->toBinary(), 'SELF_DECLARED', $now)
                : $this->profiles->deactivateRole($person, $roleType, $expectedVersion, $now);
            $this->appendAudit($actor, PersonPublicId::fromBinary((string) $role['public_id']), $activate ? SecurityEventCode::PERSON_ROLE_ACTIVATED : SecurityEventCode::PERSON_ROLE_DEACTIVATED, SecurityEventSubjectKind::PERSON_ROLE, ['role_type' => $roleType, 'role_version' => (int) $role['version']], $now);
            $this->idempotency->completeIdempotency($submission, $now);

            return $role;
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    /** @return array<string, int|string> */
    public function updateMemorizerProgress(AuthenticatedAccountContext $actor, PersonProfileSubmissionId $submission, int $juzCount, string $status, ?string $completedOn, int $expectedVersion): array
    {
        if ($juzCount < 0 || $juzCount > 30 || !in_array($status, ['NOT_RECORDED', 'IN_PROGRESS', 'COMPLETE', 'MAINTENANCE'], true)) {
            throw new \InvalidArgumentException('Memorizer progress is invalid.');
        }
        if (in_array($status, ['COMPLETE', 'MAINTENANCE'], true) && $juzCount !== 30) {
            throw new \InvalidArgumentException('Complete Memorizer progress requires 30 Juz.');
        }
        if ($completedOn !== null && !self::isIsoDate($completedOn)) {
            throw new \InvalidArgumentException('Completion date is invalid.');
        }
        $this->consumeMutationRate($actor, false);

        return $this->transactions->transactional(function () use ($actor, $submission, $juzCount, $status, $completedOn, $expectedVersion): array {
            $now = $this->clock->now();
            $claim = $this->claim($submission, 'MEMORIZER_PROGRESS_UPDATE', $actor, implode("\0", [(string) $juzCount, $status, $completedOn ?? '', (string) $expectedVersion]));
            $person = $this->requiredPerson($actor);
            if ($claim === IdempotencyClaimStatus::CONFLICT) {
                throw new \DomainException('Progress submission conflicts with a prior request.');
            }
            if ($claim === IdempotencyClaimStatus::REPLAY) {
                $replayed = $this->profiles->memorizerProgressForPerson($person, true);
                if ($replayed === null) {
                    throw new \DomainException('Progress replay is incomplete.');
                }

                return $replayed;
            }
            $progress = $this->profiles->updateMemorizerProgress($person, $juzCount, $status, $completedOn, $expectedVersion, 'SELF_DECLARED', $now);
            $this->appendAudit($actor, PersonPublicId::fromBinary((string) $person['public_id']), SecurityEventCode::MEMORIZER_PROGRESS_UPDATED, SecurityEventSubjectKind::PERSON, ['progress_version' => (int) $progress['version'], 'status' => $status, 'memorized_juz_count' => $juzCount], $now);
            $this->idempotency->completeIdempotency($submission, $now);

            return $progress;
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    public function createDependent(AuthenticatedAccountContext $actor, PersonProfileSubmissionId $submission, PersonProfileInput $input): PersonProfileResult
    {
        $this->consumeMutationRate($actor, true);

        return $this->transactions->transactional(function () use ($actor, $submission, $input): PersonProfileResult {
            $now = $this->clock->now();
            $claim = $this->claim($submission, 'DEPENDENT_PROFILE_CREATE', $actor, $input->searchName);
            $guardian = $this->requiredPerson($actor);
            $guardianRole = $this->profiles->activeRoleForPerson($guardian, 'GUARDIAN', true);
            if (!$this->accessPolicy->guardian($guardian, $guardianRole, $guardian)->allowed) {
                throw new \DomainException('An active Guardian role is required before creating a dependent.');
            }
            if ($claim === IdempotencyClaimStatus::CONFLICT) {
                throw new \DomainException('Dependent submission conflicts with a prior request.');
            }
            if ($claim === IdempotencyClaimStatus::REPLAY) {
                $replayed = $this->profiles->personForOperation($submission);
                if ($replayed === null) {
                    throw new \DomainException('Dependent creation replay is incomplete.');
                }

                return $this->result($replayed, true);
            }
            $guardianBirthDate = $guardian['birth_date'] ?? '';
            if (!is_string($guardianBirthDate) || $guardianBirthDate === '' || PersonBirthDate::fromString($guardianBirthDate, $now, $this->configuration->maximumAgeYears)->isBelowAge($this->configuration->minorThresholdYears, $now)) {
                throw new \DomainException('Guardian profile does not meet the configured adult policy.');
            }
            if ($input->birthDate === null || !$input->birthDate->isBelowAge($this->configuration->minorThresholdYears, $now)) {
                throw new \DomainException('Dependent profile does not meet the configured minor policy.');
            }
            if ($this->profiles->activeDependentCount($guardian) >= $this->configuration->maximumDependentsPerGuardian) {
                throw new \DomainException('Guardian dependent limit has been reached.');
            }
            $this->stepUp->consumeWithGrant($actor, StepUpAction::DEPENDENT_PROFILE_CREATE);
            $dependentId = PersonPublicId::generate();
            $dependent = $this->profiles->createDependent(
                $guardian,
                $actor->accountInternalId,
                $dependentId->toBinary(),
                $this->registryCodes->generate()->value(),
                UuidV7::generate()->toBinary(),
                UuidV7::generate()->toBinary(),
                $input,
                $now,
            );
            $this->provisionInitialRoles($actor, $dependent, $input, 'GUARDIAN_DECLARED', $now);
            $this->profiles->recordPersonOperation($submission, 'DEPENDENT_PROFILE_CREATE', (int) $dependent['id'], $now);
            $this->appendAudit($actor, $dependentId, SecurityEventCode::DEPENDENT_PROFILE_CREATED, SecurityEventSubjectKind::PERSON, ['profile_version' => 1], $now);
            $this->notifications->create($actor->accountInternalId, AccountSecurityNotificationType::DEPENDENT_PROFILE_CREATED, $dependentId->toString(), $now);
            $this->idempotency->completeIdempotency($submission, $now);

            return $this->result($dependent);
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    /** @return list<array<string, int|string>> */
    public function dependents(AuthenticatedAccountContext $actor): array
    {
        $guardian = $this->requiredPerson($actor, false);
        if ($this->profiles->activeRoleForPerson($guardian, 'GUARDIAN') === null) {
            return [];
        }

        return $this->profiles->dependentsForGuardian($guardian);
    }

    /** @return array<string, int|string>|null */
    public function dependent(AuthenticatedAccountContext $actor, string $dependentPublicId): ?array
    {
        $guardian = $this->requiredPerson($actor, false);
        $guardianRole = $this->profiles->activeRoleForPerson($guardian, 'GUARDIAN');
        $dependent = $this->profiles->dependentForGuardian($guardian, $dependentPublicId);

        return $this->accessPolicy->guardian($guardian, $guardianRole, $dependent)->allowed ? $dependent : null;
    }

    /** @return array<string, int|string>|null */
    public function guardianship(AuthenticatedAccountContext $actor, string $guardianshipPublicId): ?array
    {
        $guardian = $this->requiredPerson($actor, false);
        if ($this->profiles->activeRoleForPerson($guardian, 'GUARDIAN') === null) {
            return null;
        }

        return $this->profiles->guardianshipForGuardian($guardian, $guardianshipPublicId);
    }

    /** @return array<string, int|string>|null */
    public function dependentGuardianship(AuthenticatedAccountContext $actor, string $dependentPublicId): ?array
    {
        $guardian = $this->requiredPerson($actor, false);
        if ($this->profiles->activeRoleForPerson($guardian, 'GUARDIAN') === null) {
            return null;
        }

        return $this->profiles->guardianshipForDependent($guardian, $dependentPublicId);
    }

    /** @return list<array<string, int|string>> */
    public function dependentRoles(AuthenticatedAccountContext $actor, string $dependentPublicId): array
    {
        $dependent = $this->dependent($actor, $dependentPublicId);

        return $dependent === null ? [] : $this->profiles->rolesForPerson($dependent);
    }

    /** @return list<array<string, int|string>> */
    public function roles(AuthenticatedAccountContext $actor): array
    {
        return $this->profiles->rolesForPerson($this->requiredPerson($actor, false));
    }

    /** @return array<string, int|string>|null */
    public function memorizerProgress(AuthenticatedAccountContext $actor): ?array
    {
        return $this->profiles->memorizerProgressForPerson($this->requiredPerson($actor, false));
    }

    /** @return array<string, int|string>|null */
    public function dependentMemorizerProgress(AuthenticatedAccountContext $actor, string $dependentPublicId): ?array
    {
        $dependent = $this->dependent($actor, $dependentPublicId);

        return $dependent === null ? null : $this->profiles->memorizerProgressForPerson($dependent);
    }

    public function updateDependent(AuthenticatedAccountContext $actor, PersonProfileSubmissionId $submission, string $dependentPublicId, PersonProfileInput $input, int $expectedVersion, int $expectedNameVersion): PersonProfileResult
    {
        $this->consumeMutationRate($actor, false);

        return $this->transactions->transactional(function () use ($actor, $submission, $dependentPublicId, $input, $expectedVersion, $expectedNameVersion): PersonProfileResult {
            $now = $this->clock->now();
            $claim = $this->claim($submission, 'DEPENDENT_PROFILE_UPDATE', $actor, $dependentPublicId . "\0" . $expectedVersion . "\0" . $expectedNameVersion . "\0" . $input->searchName);
            $guardian = $this->requiredPerson($actor);
            $guardianRole = $this->profiles->activeRoleForPerson($guardian, 'GUARDIAN', true);
            $dependent = $this->profiles->dependentForGuardian($guardian, $dependentPublicId, true);
            if ($dependent === null || !$this->accessPolicy->guardian($guardian, $guardianRole, $dependent)->allowed) {
                throw new \DomainException('Dependent profile is unavailable.');
            }
            if ($claim === IdempotencyClaimStatus::CONFLICT) {
                throw new \DomainException('Dependent submission conflicts with a prior request.');
            }
            if ($claim === IdempotencyClaimStatus::REPLAY) {
                return $this->result($dependent, true);
            }
            if ($this->profiles->profileMatchesInput($dependent, $input)) {
                $this->idempotency->completeIdempotency($submission, $now);

                return $this->result($dependent);
            }
            $this->stepUp->consumeWithGrant($actor, StepUpAction::PERSON_PROFILE_SENSITIVE_UPDATE);
            $updated = $this->profiles->updatePerson($dependent, UuidV7::generate()->toBinary(), $input, $expectedVersion, $expectedNameVersion, 'GUARDIAN_DECLARED', $now);
            $personId = PersonPublicId::fromBinary((string) $updated['public_id']);
            $this->appendAudit($actor, $personId, SecurityEventCode::PERSON_PROFILE_UPDATED, SecurityEventSubjectKind::PERSON, ['profile_version' => (int) $updated['version'], 'managed_by_guardian' => true], $now);
            $this->notifications->create($actor->accountInternalId, AccountSecurityNotificationType::PERSON_PROFILE_UPDATED, $personId->toString(), $now);
            $this->idempotency->completeIdempotency($submission, $now);

            return $this->result($updated);
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    /** @return array<string, int|string> */
    public function updateDependentMemorizerProgress(AuthenticatedAccountContext $actor, PersonProfileSubmissionId $submission, string $dependentPublicId, int $juzCount, string $status, ?string $completedOn, int $expectedVersion): array
    {
        if ($juzCount < 0 || $juzCount > 30 || !in_array($status, ['NOT_RECORDED', 'IN_PROGRESS', 'COMPLETE', 'MAINTENANCE'], true)) {
            throw new \InvalidArgumentException('Memorizer progress is invalid.');
        }
        if (in_array($status, ['COMPLETE', 'MAINTENANCE'], true) && $juzCount !== 30) {
            throw new \InvalidArgumentException('Complete Memorizer progress requires 30 Juz.');
        }
        $this->consumeMutationRate($actor, false);

        return $this->transactions->transactional(function () use ($actor, $submission, $dependentPublicId, $juzCount, $status, $completedOn, $expectedVersion): array {
            $now = $this->clock->now();
            $claim = $this->claim($submission, 'MEMORIZER_PROGRESS_UPDATE', $actor, $dependentPublicId . "\0" . $juzCount . "\0" . $status . "\0" . $expectedVersion);
            $guardian = $this->requiredPerson($actor);
            $guardianRole = $this->profiles->activeRoleForPerson($guardian, 'GUARDIAN', true);
            $dependent = $this->profiles->dependentForGuardian($guardian, $dependentPublicId, true);
            if ($dependent === null || !$this->accessPolicy->guardian($guardian, $guardianRole, $dependent)->allowed) {
                throw new \DomainException('Dependent profile is unavailable.');
            }
            if ($claim === IdempotencyClaimStatus::CONFLICT) {
                throw new \DomainException('Dependent progress conflicts with a prior request.');
            }
            if ($claim === IdempotencyClaimStatus::REPLAY) {
                $replayed = $this->profiles->memorizerProgressForPerson($dependent, true);
                if ($replayed === null) {
                    throw new \DomainException('Dependent progress replay is incomplete.');
                }

                return $replayed;
            }
            $progress = $this->profiles->updateMemorizerProgress($dependent, $juzCount, $status, $completedOn, $expectedVersion, 'GUARDIAN_DECLARED', $now);
            $this->appendAudit($actor, PersonPublicId::fromBinary((string) $dependent['public_id']), SecurityEventCode::MEMORIZER_PROGRESS_UPDATED, SecurityEventSubjectKind::PERSON, ['progress_version' => (int) $progress['version'], 'managed_by_guardian' => true], $now);
            $this->idempotency->completeIdempotency($submission, $now);

            return $progress;
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    /** @return array<string, int|string> */
    public function revokeGuardianship(AuthenticatedAccountContext $actor, PersonProfileSubmissionId $submission, string $guardianshipPublicId, int $expectedVersion): array
    {
        $this->consumeMutationRate($actor, false);

        return $this->transactions->transactional(function () use ($actor, $submission, $guardianshipPublicId, $expectedVersion): array {
            $now = $this->clock->now();
            $claim = $this->claim($submission, 'GUARDIANSHIP_REVOKE', $actor, $guardianshipPublicId . "\0" . $expectedVersion);
            $guardian = $this->requiredPerson($actor);
            if ($this->profiles->activeRoleForPerson($guardian, 'GUARDIAN', true) === null) {
                throw new \DomainException('Guardianship is unavailable.');
            }
            $guardianship = $this->profiles->guardianshipForGuardian($guardian, $guardianshipPublicId, true);
            if ($guardianship === null) {
                throw new \DomainException('Guardianship is unavailable.');
            }
            if ($claim === IdempotencyClaimStatus::CONFLICT) {
                throw new \DomainException('Guardianship submission conflicts with a prior request.');
            }
            if ($claim === IdempotencyClaimStatus::REPLAY) {
                return $guardianship;
            }
            $dependentBirthDate = $guardianship['dependent_birth_date'] ?? '';
            if (is_string($dependentBirthDate) && $dependentBirthDate !== '' && PersonBirthDate::fromString($dependentBirthDate, $now, $this->configuration->maximumAgeYears)->isBelowAge($this->configuration->minorThresholdYears, $now) && $this->profiles->activeGuardiansForDependent($guardianship, true) <= 1) {
                throw new \DomainException('The last active guardian for a minor cannot be revoked.');
            }
            $this->stepUp->consumeWithGrant($actor, StepUpAction::GUARDIANSHIP_REVOKE);
            $revoked = $this->profiles->revokeGuardianship($guardianship, $expectedVersion, $actor->accountInternalId, $now);
            $guardianshipId = PersonPublicId::fromBinary((string) $revoked['public_id']);
            $this->appendAudit($actor, $guardianshipId, SecurityEventCode::GUARDIANSHIP_REVOKED, SecurityEventSubjectKind::GUARDIANSHIP, ['guardianship_version' => (int) $revoked['version']], $now);
            $this->notifications->create($actor->accountInternalId, AccountSecurityNotificationType::GUARDIANSHIP_REVOKED, $guardianshipId->toString(), $now);
            $this->idempotency->completeIdempotency($submission, $now);

            return $revoked;
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    private function consumeMutationRate(AuthenticatedAccountContext $actor, bool $dependent): void
    {
        $policy = $dependent
            ? new IdentityRateLimitPolicy($this->configuration->dependentCreationWindowSeconds, $this->configuration->dependentCreationMaximumAttempts, $this->configuration->dependentCreationWindowSeconds)
            : new IdentityRateLimitPolicy($this->configuration->mutationWindowSeconds, $this->configuration->mutationMaximumAttempts, $this->configuration->mutationWindowSeconds);
        $scopes = $dependent
            ? [IdentityRateLimitScope::DEPENDENT_PROFILE_CREATION_ACCOUNT, IdentityRateLimitScope::DEPENDENT_PROFILE_CREATION_PEER]
            : [IdentityRateLimitScope::PERSON_PROFILE_MUTATION_ACCOUNT, IdentityRateLimitScope::PERSON_PROFILE_MUTATION_PEER];
        $attempts = [
            new IdentityRateLimitAttempt($scopes[0], $this->fingerprints->generate('people-profile-account', (string) $actor->accountInternalId), $policy),
            new IdentityRateLimitAttempt($scopes[1], $this->fingerprints->generate('people-profile-session', (string) $actor->sessionInternalId), $policy),
        ];
        if (!$this->rateLimiter->consume($attempts, $this->clock->now())->allowed) {
            throw new \DomainException('Person profile operation is temporarily unavailable.');
        }
    }

    private function claim(PersonProfileSubmissionId $submission, string $operation, AuthenticatedAccountContext $actor, string $content): IdempotencyClaimStatus
    {
        return $this->idempotency->claimIdempotency(
            $submission,
            $operation,
            $this->fingerprints->generate('people-profile-idempotency', $actor->accountInternalId . "\0" . $operation . "\0" . hash('sha256', $content)),
            $this->clock->now(),
        );
    }

    /** @return array<string, int|string> */
    private function requiredPerson(AuthenticatedAccountContext $actor, bool $forUpdate = true): array
    {
        $person = $this->profiles->personForAccount($actor->accountInternalId, $forUpdate);
        if ($person === null || !$this->accessPolicy->self($person)->allowed) {
            throw new \DomainException('An active self Person profile is required.');
        }

        return $person;
    }

    /** @param array<string, int|string> $person */
    private function provisionInitialRoles(AuthenticatedAccountContext $actor, array $person, PersonProfileInput $input, string $sourceType, DateTimeImmutable $now): void
    {
        foreach ($input->selectedRoleTypes as $roleType) {
            $role = $this->profiles->activateRole($person, $roleType, UuidV7::generate()->toBinary(), $sourceType, $now);
            $this->appendAudit($actor, PersonPublicId::fromBinary((string) $role['public_id']), SecurityEventCode::PERSON_ROLE_ACTIVATED, SecurityEventSubjectKind::PERSON_ROLE, ['role_type' => $roleType, 'role_version' => (int) $role['version']], $now);
        }
        if ($input->memorizerProgress === null) {
            return;
        }
        $progress = $this->profiles->updateMemorizerProgress(
            $person,
            $input->memorizerProgress['memorized_juz_count'],
            $input->memorizerProgress['progress_status'],
            $input->memorizerProgress['completed_on'],
            0,
            $sourceType,
            $now,
        );
        $this->appendAudit($actor, PersonPublicId::fromBinary((string) $person['public_id']), SecurityEventCode::MEMORIZER_PROGRESS_UPDATED, SecurityEventSubjectKind::PERSON, ['progress_version' => (int) $progress['version']], $now);
    }

    /** @param array<string, bool|int|string> $metadata */
    private function appendAudit(AuthenticatedAccountContext $actor, PersonPublicId $subject, SecurityEventCode $code, SecurityEventSubjectKind $kind, array $metadata, DateTimeImmutable $now): void
    {
        $this->audit->append(new SecurityAuditAppendCommand(
            new SecurityAuditStreamIdentity(SecurityAuditStreamType::ACCOUNT, UuidV7::fromString($actor->accountId->toString())),
            $code,
            SecurityEventOutcome::SUCCESS,
            SecurityEventActorKind::ACCOUNT,
            UuidV7::fromString($actor->accountId->toString()),
            UuidV7::fromString($actor->sessionId->toString()),
            null,
            $kind,
            UuidV7::fromString($subject->toString()),
            null,
            null,
            null,
            $metadata,
            $now,
        ));
    }

    /** @param array<string, int|string> $profile */
    private function result(array $profile, bool $replayed = false): PersonProfileResult
    {
        return new PersonProfileResult(PersonPublicId::fromBinary((string) $profile['public_id'])->toString(), (int) $profile['version'], $replayed);
    }

    private static function isIsoDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
    }
}
