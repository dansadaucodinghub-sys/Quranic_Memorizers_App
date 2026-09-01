<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Application;

use DateTimeImmutable;
use Qmdb\Modules\People\Domain\PersonProfileSubmissionId;

interface PersonProfileRepository
{
    /** @return array<string, int|string>|null */
    public function profileForAccount(int $accountInternalId, bool $forUpdate = false): ?array;

    /** @return array<string, int|string>|null */
    public function personForAccount(int $accountInternalId, bool $forUpdate = false): ?array;

    /** @return array<string, int|string> */
    public function createSelf(int $accountInternalId, string $personPublicIdBinary, string $registryCode, string $namePublicIdBinary, string $linkPublicIdBinary, PersonProfileInput $input, DateTimeImmutable $now): array;

    /** @param array<string, int|string> $person
     * @return array<string, int|string>
     */
    public function updatePerson(array $person, string $namePublicIdBinary, PersonProfileInput $input, int $expectedVersion, int $expectedNameVersion, string $sourceType, DateTimeImmutable $now): array;

    /** @param array<string, int|string> $person */
    public function profileMatchesInput(array $person, PersonProfileInput $input): bool;

    /** @param array<string, int|string> $person
     * @return array<string, int|string>
     */
    public function activateRole(array $person, string $roleType, string $rolePublicIdBinary, string $sourceType, DateTimeImmutable $now): array;

    /** @param array<string, int|string> $person
     * @return array<string, int|string>
     */
    public function deactivateRole(array $person, string $roleType, int $expectedVersion, DateTimeImmutable $now): array;

    /** @param array<string, int|string> $person
     * @return array<string, int|string>|null
     */
    public function activeRoleForPerson(array $person, string $roleType, bool $forUpdate = false): ?array;

    /** @param array<string, int|string> $person
     * @return array<string, int|string>|null
     */
    public function roleForPerson(array $person, string $roleType, bool $forUpdate = false): ?array;

    /** @param array<string, int|string> $person
     * @return list<array<string, int|string>>
     */
    public function rolesForPerson(array $person): array;

    /** @param array<string, int|string> $guardian */
    public function activeDependentCount(array $guardian): int;

    /** @return array<string, int|string>|null */
    public function personForOperation(PersonProfileSubmissionId $submission): ?array;

    public function recordPersonOperation(PersonProfileSubmissionId $submission, string $operation, int $personId, DateTimeImmutable $now): void;

    /** @param array<string, int|string> $person
     * @return array<string, int|string>
     */
    public function updateMemorizerProgress(array $person, int $juzCount, string $status, ?string $completedOn, int $expectedVersion, string $sourceType, DateTimeImmutable $now): array;

    /** @param array<string, int|string> $person
     * @return array<string, int|string>|null
     */
    public function memorizerProgressForPerson(array $person, bool $forUpdate = false): ?array;

    /** @param array<string, int|string> $guardian
     * @return array<string, int|string>
     */
    public function createDependent(array $guardian, int $createdByAccountId, string $personPublicIdBinary, string $registryCode, string $namePublicIdBinary, string $guardianshipPublicIdBinary, PersonProfileInput $input, DateTimeImmutable $now): array;

    /** @param array<string, int|string> $guardian
     * @return list<array<string, int|string>>
     */
    public function dependentsForGuardian(array $guardian): array;

    /** @param array<string, int|string> $guardian
     * @return array<string, int|string>|null
     */
    public function dependentForGuardian(array $guardian, string $dependentPublicId, bool $forUpdate = false): ?array;

    /** @param array<string, int|string> $guardian
     * @return array<string, int|string>|null
     */
    public function guardianshipForGuardian(array $guardian, string $guardianshipPublicId, bool $forUpdate = false): ?array;

    /** @param array<string, int|string> $guardian
     * @return array<string, int|string>|null
     */
    public function guardianshipForDependent(array $guardian, string $dependentPublicId, bool $forUpdate = false): ?array;

    /** @param array<string, int|string> $guardianship
     * @return array<string, int|string>
     */
    public function revokeGuardianship(array $guardianship, int $expectedVersion, int $revokedByAccountId, DateTimeImmutable $now): array;

    /** @param array<string, int|string> $guardianship */
    public function activeGuardiansForDependent(array $guardianship, bool $forUpdate = false): int;
}
