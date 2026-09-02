<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Application;

use DateTimeImmutable;
use Qmdb\Modules\Tenancy\Application\TenantContext;
use Qmdb\Modules\Tenancy\Application\TenantScopedRepository;

/** @phpstan-type Row array<string, int|string|null> */
interface OrganizationAffiliationRepository extends TenantScopedRepository
{
    /** @return Row|null */
    public function organization(TenantContext $tenant, string $organizationPublicId, bool $forUpdate = false): ?array;
    /** @return Row|null */
    public function candidateByRegistryCode(string $registryCode): ?array;
    /**
     * @param list<string> $codes
     *
     * @return list<Row>
     */
    public function activeRoleDefinitions(array $codes): array;
    /**
     * @param Row $organization
     * @param list<string> $publicIds
     *
     * @return list<Row>
     */
    public function activeUnits(TenantContext $tenant, array $organization, array $publicIds): array;
    /**
     * @param Row $organization
     *
     * @return Row|null
     */
    public function openAffiliation(TenantContext $tenant, array $organization, int $personInternalId, bool $forUpdate = false): ?array;
    /**
     * @param Row $organization
     *
     * @return Row|null
     */
    public function affiliation(TenantContext $tenant, array $organization, string $affiliationPublicId, bool $forUpdate = false): ?array;
    /** @return Row|null */
    public function responseAffiliation(int $accountId, string $affiliationPublicId, bool $forUpdate = false): ?array;
    public function personHasRequiredRole(int $personInternalId, string $roleType): bool;
    /**
     * @param Row $organization
     * @param Row $candidate
     * @param list<array{code: string, is_primary: bool, unit_public_id: ?string, title: ?string}> $roles
     * @param list<array{public_id: string, is_primary: bool}> $units
     *
     * @return Row
     */
    public function createRequest(TenantContext $tenant, array $organization, array $candidate, int $actorAccountId, string $publicId, string $code, DateTimeImmutable $expiresAt, array $roles, array $units, DateTimeImmutable $now): array;
    /** @return Row|null */
    public function personAffiliation(int $accountId, string $affiliationPublicId, bool $forUpdate = false): ?array;
    /** @return list<Row> */
    public function accountAffiliations(int $accountId, ?int $managedPersonId = null): array;
    /**
     * @param Row $organization
     *
     * @return list<Row>
     */
    public function roster(TenantContext $tenant, array $organization, string $status, int $limit): array;
    /**
     * @param Row $affiliation
     *
     * @return list<Row>
     */
    public function assignments(TenantContext $tenant, array $affiliation): array;
    /** @return array{authority:string,guardianship_id:?int}|null */
    public function responseAuthority(int $accountId, int $personId): ?array;
    /** @return list<array{account_id:int,email_id:int,account_public_id:string,locale:string}> */
    public function notificationTargets(int $personId): array;
    /** @return list<Row> */
    public function expiredPending(int $limit, DateTimeImmutable $now): array;
    /**
     * @param Row $affiliation
     *
     * @return Row
     */
    public function transition(array $affiliation, string $toStatus, ?int $respondedByAccountId, ?string $responseAuthority, ?int $guardianshipId, string $reasonCode, string $actorAuthority, ?int $actorAccountId, ?int $actorGuardianshipId, ?string $correlationId, DateTimeImmutable $now): array;
    /** @param Row $affiliation */
    public function activateProposedAssignments(array $affiliation, DateTimeImmutable $now): void;
    /** @param Row $affiliation */
    public function cancelProposedAssignments(array $affiliation, DateTimeImmutable $now): void;
    /** @param Row $affiliation */
    public function removeActiveAssignments(array $affiliation, int $actorAccountId, DateTimeImmutable $now): void;
    /**
     * @param Row $organization
     * @param Row $affiliation
     * @param list<array{code: string, is_primary: bool, unit_public_id: ?string, title: ?string}> $roles
     * @param list<array{public_id: string, is_primary: bool}> $units
     */
    public function replaceAssignments(TenantContext $tenant, array $organization, array $affiliation, int $actorAccountId, array $roles, array $units, DateTimeImmutable $now): void;
    /**
     * @param Row $affiliation
     *
     * @return Row
     */
    public function incrementVersion(array $affiliation, DateTimeImmutable $now): array;
    /** @return array{role_definitions:int,pending:int,active:int,suspended:int,ended:int,invalid_rows:int} */
    public function report(): array;
    public function organizationHasOpenAffiliations(int $workspaceId, int $organizationId): bool;
    public function unitHasOpenAssignments(int $workspaceId, int $organizationId, int $unitId): bool;
}
