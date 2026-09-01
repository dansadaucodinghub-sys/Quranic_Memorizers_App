<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\People\Application\PersonProfileInput;
use Qmdb\Modules\People\Application\PersonProfileRepository;
use Qmdb\Modules\People\Domain\PersonProfileSubmissionId;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final readonly class MySqlPersonProfileRepository implements PersonProfileRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function profileForAccount(int $accountInternalId, bool $forUpdate = false): ?array
    {
        $suffix = $forUpdate ? ' FOR UPDATE' : '';
        $statement = $this->connection()->prepare(<<<SQL
SELECT p.id, p.public_id, p.registry_code, p.status, p.birth_date, p.sex_classification,
        BIN_TO_UUID(c.public_id) AS nationality_country_public_id, p.version, p.created_by_account_id, n.given_name, n.middle_names,
        n.family_name, n.display_name, n.script_code, n.version AS name_version,
        (SELECT pn.display_name FROM people_person_names pn WHERE pn.person_id = p.id AND pn.name_type = 'PREFERRED' AND pn.status = 'ACTIVE' LIMIT 1) AS preferred_name,
        (SELECT pn.display_name FROM people_person_names pn WHERE pn.person_id = p.id AND pn.name_type = 'ARABIC' AND pn.status = 'ACTIVE' LIMIT 1) AS arabic_name,
        (SELECT BIN_TO_UUID(a.public_id) FROM people_person_geographies pg INNER JOIN geography_administrative_areas a ON a.id = pg.level_one_area_id WHERE pg.person_id = p.id AND pg.association_type = 'ORIGIN' AND pg.status = 'ACTIVE' LIMIT 1) AS origin_level_one_area_public_id,
        (SELECT BIN_TO_UUID(a.public_id) FROM people_person_geographies pg INNER JOIN geography_administrative_areas a ON a.id = pg.level_two_area_id WHERE pg.person_id = p.id AND pg.association_type = 'ORIGIN' AND pg.status = 'ACTIVE' LIMIT 1) AS origin_level_two_area_public_id,
        (SELECT BIN_TO_UUID(a.public_id) FROM people_person_geographies pg INNER JOIN geography_administrative_areas a ON a.id = pg.level_one_area_id WHERE pg.person_id = p.id AND pg.association_type = 'RESIDENCE' AND pg.status = 'ACTIVE' LIMIT 1) AS residence_level_one_area_public_id,
        (SELECT BIN_TO_UUID(a.public_id) FROM people_person_geographies pg INNER JOIN geography_administrative_areas a ON a.id = pg.level_two_area_id WHERE pg.person_id = p.id AND pg.association_type = 'RESIDENCE' AND pg.status = 'ACTIVE' LIMIT 1) AS residence_level_two_area_public_id
FROM people_account_links l
INNER JOIN people_persons p ON p.id = l.person_id
INNER JOIN people_person_names n ON n.person_id = p.id AND n.name_type = 'PRIMARY' AND n.status = 'ACTIVE'
LEFT JOIN geography_countries c ON c.id = p.nationality_country_id
WHERE l.account_id = :account_id AND l.link_type = 'SELF' AND l.status = 'ACTIVE'
LIMIT 1{$suffix}
SQL);
        $statement->execute(['account_id' => $accountInternalId]);

        return $this->rowOrNull($statement->fetch(PDO::FETCH_ASSOC));
    }

    public function personForAccount(int $accountInternalId, bool $forUpdate = false): ?array
    {
        $profile = $this->profileForAccount($accountInternalId, $forUpdate);

        return $profile;
    }

    public function createSelf(int $accountInternalId, string $personPublicIdBinary, string $registryCode, string $namePublicIdBinary, string $linkPublicIdBinary, PersonProfileInput $input, DateTimeImmutable $now): array
    {
        $time = self::format($now);
        $statement = $this->connection()->prepare(<<<SQL
INSERT INTO people_persons (public_id, registry_code, status, birth_date, sex_classification, nationality_country_id,
    created_by_account_id, version, created_at, updated_at, restricted_at, retired_at)
VALUES (:public_id, :registry_code, 'ACTIVE', :birth_date, :sex, :country_id, :account_id, 1, :now, :now, NULL, NULL)
SQL);
        $statement->bindValue(':public_id', $personPublicIdBinary, PDO::PARAM_LOB);
        $statement->bindValue(':registry_code', $registryCode);
        $statement->bindValue(':birth_date', $input->birthDate?->value());
        $statement->bindValue(':sex', $input->sex->value);
        $countryId = $this->countryId($input->nationalityCountryPublicId);
        $statement->bindValue(':country_id', $countryId, $countryId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':now', $time);
        $statement->execute();
        $personId = (int) $this->connection()->lastInsertId();
        $this->insertName($personId, $namePublicIdBinary, $input, $now);
        $this->replaceNameVariants($personId, $input, $now);
        $link = $this->connection()->prepare(<<<SQL
INSERT INTO people_account_links (public_id, account_id, person_id, link_type, status, version, linked_at, revoked_at, created_at, updated_at)
VALUES (:public_id, :account_id, :person_id, 'SELF', 'ACTIVE', 1, :now, NULL, :now, :now)
SQL);
        $link->bindValue(':public_id', $linkPublicIdBinary, PDO::PARAM_LOB);
        $link->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $link->bindValue(':person_id', $personId, PDO::PARAM_INT);
        $link->bindValue(':now', $time);
        $link->execute();
        $this->replaceGeographies($personId, $input, 'SELF_DECLARED', $now);

        return $this->required($this->profileForAccount($accountInternalId, true));
    }

    /** @param array<string, int|string> $person
     * @return array<string, int|string>
     */
    public function updatePerson(array $person, string $namePublicIdBinary, PersonProfileInput $input, int $expectedVersion, int $expectedNameVersion, string $sourceType, DateTimeImmutable $now): array
    {
        $this->requireVersion($person, $expectedVersion);
        if ((int) ($person['name_version'] ?? 0) !== $expectedNameVersion) {
            throw new \DomainException('Person name is stale.');
        }
        $time = self::format($now);
        $update = $this->connection()->prepare(<<<SQL
UPDATE people_persons SET birth_date = :birth_date, sex_classification = :sex, nationality_country_id = :country_id,
    version = version + 1, updated_at = :now
WHERE id = :id AND status = 'ACTIVE' AND version = :version
SQL);
        $update->bindValue(':birth_date', $input->birthDate?->value());
        $update->bindValue(':sex', $input->sex->value);
        $countryId = $this->countryId($input->nationalityCountryPublicId);
        $update->bindValue(':country_id', $countryId, $countryId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $update->bindValue(':now', $time);
        $update->bindValue(':id', (int) $person['id'], PDO::PARAM_INT);
        $update->bindValue(':version', $expectedVersion, PDO::PARAM_INT);
        $update->execute();
        if ($update->rowCount() !== 1) {
            throw new \DomainException('Person profile is stale or unavailable.');
        }
        $supersede = $this->connection()->prepare(<<<SQL
UPDATE people_person_names SET status = 'SUPERSEDED', superseded_at = :now, version = version + 1, updated_at = :now
WHERE person_id = :person_id AND name_type = 'PRIMARY' AND status = 'ACTIVE'
SQL);
        $supersede->execute(['now' => $time, 'person_id' => (int) $person['id']]);
        if ($supersede->rowCount() !== 1) {
            throw new \UnexpectedValueException('Current Person name is unavailable.');
        }
        $this->insertName((int) $person['id'], $namePublicIdBinary, $input, $now);
        $this->replaceNameVariants((int) $person['id'], $input, $now);
        $this->replaceGeographies((int) $person['id'], $input, $sourceType, $now);

        return $this->required($this->profileForPerson((int) $person['id'], true));
    }

    public function profileMatchesInput(array $person, PersonProfileInput $input): bool
    {
        $expected = [
            'given_name' => $input->name->givenName(),
            'middle_names' => $input->name->middleNames(),
            'family_name' => $input->name->familyName(),
            'display_name' => $input->name->displayName(),
            'preferred_name' => $input->preferredName,
            'arabic_name' => $input->arabicName,
            'birth_date' => $input->birthDate?->value(),
            'sex_classification' => $input->sex->value,
            'nationality_country_public_id' => $input->nationalityCountryPublicId,
            'origin_level_one_area_public_id' => $input->originLevelOneAreaPublicId,
            'origin_level_two_area_public_id' => $input->originLevelTwoAreaPublicId,
            'residence_level_one_area_public_id' => $input->residenceLevelOneAreaPublicId,
            'residence_level_two_area_public_id' => $input->residenceLevelTwoAreaPublicId,
        ];
        foreach ($expected as $field => $value) {
            $actual = $person[$field] ?? null;
            if (($actual === '' ? null : $actual) !== $value) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, int|string> $person
     * @return array<string, int|string>
     */
    public function activateRole(array $person, string $roleType, string $rolePublicIdBinary, string $sourceType, DateTimeImmutable $now): array
    {
        $row = $this->roleRowForPerson((int) $person['id'], $roleType, true);
        $time = self::format($now);
        if ($row === null) {
            $insert = $this->connection()->prepare(<<<SQL
INSERT INTO people_role_profiles (public_id, person_id, role_type, status, source_type, version, activated_at, deactivated_at, created_at, updated_at)
VALUES (:public_id, :person_id, :role_type, 'ACTIVE', :source_type, 1, :now, NULL, :now, :now)
SQL);
            $insert->bindValue(':public_id', $rolePublicIdBinary, PDO::PARAM_LOB);
            $insert->bindValue(':person_id', (int) $person['id'], PDO::PARAM_INT);
            $insert->bindValue(':role_type', $roleType);
            $insert->bindValue(':source_type', $sourceType);
            $insert->bindValue(':now', $time);
            $insert->execute();

            return $this->required($this->roleRowForPerson((int) $person['id'], $roleType, true));
        }
        if ($row['status'] === 'ACTIVE') {
            return $row;
        }
        $update = $this->connection()->prepare(<<<SQL
UPDATE people_role_profiles SET status = 'ACTIVE', activated_at = :now, deactivated_at = NULL, version = version + 1, updated_at = :now
WHERE id = :id AND version = :version AND status = 'INACTIVE'
SQL);
        $update->execute(['now' => $time, 'id' => (int) $row['id'], 'version' => (int) $row['version']]);
        if ($update->rowCount() !== 1) {
            throw new \DomainException('Person role is stale.');
        }

        return $this->required($this->roleRowForPerson((int) $person['id'], $roleType, true));
    }

    /** @param array<string, int|string> $person
     * @return array<string, int|string>
     */
    public function deactivateRole(array $person, string $roleType, int $expectedVersion, DateTimeImmutable $now): array
    {
        $row = $this->required($this->roleRowForPerson((int) $person['id'], $roleType, true));
        if ((int) $row['version'] !== $expectedVersion || $row['status'] !== 'ACTIVE') {
            throw new \DomainException('Person role is stale or not active.');
        }
        $update = $this->connection()->prepare(<<<SQL
UPDATE people_role_profiles SET status = 'INACTIVE', deactivated_at = :now, version = version + 1, updated_at = :now
WHERE id = :id AND version = :version AND status = 'ACTIVE'
SQL);
        $update->execute(['now' => self::format($now), 'id' => (int) $row['id'], 'version' => $expectedVersion]);
        if ($update->rowCount() !== 1) {
            throw new \DomainException('Person role is stale.');
        }

        return $this->required($this->roleRowForPerson((int) $person['id'], $roleType, true));
    }

    /** @param array<string, int|string> $person
     * @return array<string, int|string>|null
     */
    public function activeRoleForPerson(array $person, string $roleType, bool $forUpdate = false): ?array
    {
        $role = $this->roleForPerson($person, $roleType, $forUpdate);

        return $role !== null && $role['status'] === 'ACTIVE' ? $role : null;
    }

    public function roleForPerson(array $person, string $roleType, bool $forUpdate = false): ?array
    {
        return $this->roleRowForPerson((int) $person['id'], $roleType, $forUpdate);
    }

    public function rolesForPerson(array $person): array
    {
        $statement = $this->connection()->prepare('SELECT id, public_id, person_id, role_type, status, source_type, version FROM people_role_profiles WHERE person_id = :person_id ORDER BY role_type ASC');
        $statement->execute(['person_id' => (int) $person['id']]);
        $roles = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $normalized = $this->rowOrNull($row);
            if ($normalized !== null) {
                $roles[] = $normalized;
            }
        }

        return $roles;
    }

    /** @param array<string, int|string> $guardian */
    public function activeDependentCount(array $guardian): int
    {
        $statement = $this->connection()->prepare("SELECT COUNT(*) FROM people_guardianships WHERE guardian_person_id = :guardian_id AND status = 'ACTIVE'");
        $statement->execute(['guardian_id' => (int) $guardian['id']]);

        return (int) $statement->fetchColumn();
    }

    public function personForOperation(PersonProfileSubmissionId $submission): ?array
    {
        $statement = $this->connection()->prepare(<<<SQL
SELECT p.id, p.public_id, p.registry_code, p.status, p.birth_date, p.sex_classification, p.nationality_country_id,
       p.version, p.created_by_account_id, n.given_name, n.middle_names, n.family_name, n.display_name, n.script_code,
       n.version AS name_version
FROM people_profile_operation_results o
INNER JOIN people_persons p ON p.id = o.person_id
INNER JOIN people_person_names n ON n.person_id = p.id AND n.name_type = 'PRIMARY' AND n.status = 'ACTIVE'
WHERE o.idempotency_public_id = :submission LIMIT 1
SQL);
        $statement->bindValue(':submission', $submission->toBinary(), PDO::PARAM_LOB);
        $statement->execute();

        return $this->rowOrNull($statement->fetch(PDO::FETCH_ASSOC));
    }

    public function recordPersonOperation(PersonProfileSubmissionId $submission, string $operation, int $personId, DateTimeImmutable $now): void
    {
        $statement = $this->connection()->prepare(<<<SQL
INSERT INTO people_profile_operation_results (idempotency_public_id, operation, person_id, role_profile_id, guardianship_id, created_at)
VALUES (:submission, :operation, :person_id, NULL, NULL, :now)
SQL);
        $statement->bindValue(':submission', $submission->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':operation', $operation);
        $statement->bindValue(':person_id', $personId, PDO::PARAM_INT);
        $statement->bindValue(':now', self::format($now));
        $statement->execute();
    }

    /** @param array<string, int|string> $person
     * @return array<string, int|string>
     */
    public function updateMemorizerProgress(array $person, int $juzCount, string $status, ?string $completedOn, int $expectedVersion, string $sourceType, DateTimeImmutable $now): array
    {
        $role = $this->required($this->roleRowForPerson((int) $person['id'], 'MEMORIZER', true));
        if ($role['status'] !== 'ACTIVE') {
            throw new \DomainException('An active Memorizer role is required.');
        }
        $existing = $this->progressForPerson((int) $person['id'], true);
        $time = self::format($now);
        if ($existing === null) {
            if ($expectedVersion !== 0) {
                throw new \DomainException('Memorizer progress is stale.');
            }
            $insert = $this->connection()->prepare(<<<SQL
INSERT INTO people_memorizer_progress (person_id, role_profile_id, progress_status, memorized_juz_count, completed_on, source_type, version, created_at, updated_at)
VALUES (:person_id, :role_id, :status, :juz, :completed_on, :source_type, 1, :now, :now)
SQL);
            $insert->execute(['person_id' => (int) $person['id'], 'role_id' => (int) $role['id'], 'status' => $status, 'juz' => $juzCount, 'completed_on' => $completedOn, 'source_type' => $sourceType, 'now' => $time]);
        } else {
            if ((int) $existing['version'] !== $expectedVersion) {
                throw new \DomainException('Memorizer progress is stale.');
            }
            $update = $this->connection()->prepare(<<<SQL
UPDATE people_memorizer_progress SET progress_status = :status, memorized_juz_count = :juz, completed_on = :completed_on,
    version = version + 1, updated_at = :now WHERE person_id = :person_id AND version = :version
SQL);
            $update->execute(['status' => $status, 'juz' => $juzCount, 'completed_on' => $completedOn, 'now' => $time, 'person_id' => (int) $person['id'], 'version' => $expectedVersion]);
            if ($update->rowCount() !== 1) {
                throw new \DomainException('Memorizer progress is stale.');
            }
        }

        return $this->required($this->progressForPerson((int) $person['id'], true));
    }

    public function memorizerProgressForPerson(array $person, bool $forUpdate = false): ?array
    {
        return $this->progressForPerson((int) $person['id'], $forUpdate);
    }

    /** @param array<string, int|string> $guardian
     * @return array<string, int|string>
     */
    public function createDependent(array $guardian, int $createdByAccountId, string $personPublicIdBinary, string $registryCode, string $namePublicIdBinary, string $guardianshipPublicIdBinary, PersonProfileInput $input, DateTimeImmutable $now): array
    {
        $time = self::format($now);
        $person = $this->connection()->prepare(<<<SQL
INSERT INTO people_persons (public_id, registry_code, status, birth_date, sex_classification, nationality_country_id,
    created_by_account_id, version, created_at, updated_at, restricted_at, retired_at)
VALUES (:public_id, :registry_code, 'ACTIVE', :birth_date, :sex, :country_id, :account_id, 1, :now, :now, NULL, NULL)
SQL);
        $person->bindValue(':public_id', $personPublicIdBinary, PDO::PARAM_LOB);
        $person->bindValue(':registry_code', $registryCode);
        $person->bindValue(':birth_date', $input->birthDate?->value());
        $person->bindValue(':sex', $input->sex->value);
        $countryId = $this->countryId($input->nationalityCountryPublicId);
        $person->bindValue(':country_id', $countryId, $countryId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $person->bindValue(':account_id', $createdByAccountId, PDO::PARAM_INT);
        $person->bindValue(':now', $time);
        $person->execute();
        $personId = (int) $this->connection()->lastInsertId();
        $this->insertName($personId, $namePublicIdBinary, $input, $now);
        $this->replaceNameVariants($personId, $input, $now);
        $this->replaceGeographies($personId, $input, 'GUARDIAN_DECLARED', $now);
        $guardianship = $this->connection()->prepare(<<<SQL
INSERT INTO people_guardianships (public_id, guardian_person_id, dependent_person_id, relationship_type, authority_scope,
    authority_basis, status, version, created_by_account_id, revoked_by_account_id, confirmed_at, revoked_at, created_at, updated_at)
VALUES (:public_id, :guardian_id, :dependent_id, :relationship_type, 'PROFILE_MANAGEMENT', 'SELF_DECLARED', 'ACTIVE', 1, :created_by_account_id, NULL, :now, NULL, :now, :now)
SQL);
        $guardianship->bindValue(':public_id', $guardianshipPublicIdBinary, PDO::PARAM_LOB);
        $guardianship->bindValue(':guardian_id', (int) $guardian['id'], PDO::PARAM_INT);
        $guardianship->bindValue(':dependent_id', $personId, PDO::PARAM_INT);
        $guardianship->bindValue(':relationship_type', $input->guardianshipRelationshipType);
        $guardianship->bindValue(':created_by_account_id', $createdByAccountId, PDO::PARAM_INT);
        $guardianship->bindValue(':now', $time);
        $guardianship->execute();

        return $this->required($this->profileForPerson($personId, true));
    }

    /** @param array<string, int|string> $guardian
     * @return list<array<string, int|string>>
     */
    public function dependentsForGuardian(array $guardian): array
    {
        $statement = $this->connection()->prepare(<<<SQL
SELECT g.public_id AS guardianship_public_id, g.version AS guardianship_version, p.public_id, p.registry_code, p.status,
       p.birth_date, p.sex_classification, p.version, n.display_name
FROM people_guardianships g
INNER JOIN people_persons p ON p.id = g.dependent_person_id
INNER JOIN people_person_names n ON n.person_id = p.id AND n.name_type = 'PRIMARY' AND n.status = 'ACTIVE'
WHERE g.guardian_person_id = :guardian_id AND g.status = 'ACTIVE'
ORDER BY g.created_at ASC, g.id ASC
SQL);
        $statement->execute(['guardian_id' => (int) $guardian['id']]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $normalized = $this->rowOrNull($row);
            if ($normalized !== null) {
                $result[] = $normalized;
            }
        }

        return $result;
    }

    /** @param array<string, int|string> $guardian
     * @return array<string, int|string>|null
     */
    public function dependentForGuardian(array $guardian, string $dependentPublicId, bool $forUpdate = false): ?array
    {
        $suffix = $forUpdate ? ' FOR UPDATE' : '';
        $statement = $this->connection()->prepare(<<<SQL
SELECT p.id, p.public_id, p.registry_code, p.status, p.birth_date, p.sex_classification, BIN_TO_UUID(c.public_id) AS nationality_country_public_id,
       p.version, p.created_by_account_id, n.given_name, n.middle_names, n.family_name, n.display_name, n.script_code,
       n.version AS name_version,
       (SELECT pn.display_name FROM people_person_names pn WHERE pn.person_id = p.id AND pn.name_type = 'PREFERRED' AND pn.status = 'ACTIVE' LIMIT 1) AS preferred_name,
       (SELECT pn.display_name FROM people_person_names pn WHERE pn.person_id = p.id AND pn.name_type = 'ARABIC' AND pn.status = 'ACTIVE' LIMIT 1) AS arabic_name,
       (SELECT BIN_TO_UUID(a.public_id) FROM people_person_geographies pg INNER JOIN geography_administrative_areas a ON a.id = pg.level_one_area_id WHERE pg.person_id = p.id AND pg.association_type = 'ORIGIN' AND pg.status = 'ACTIVE' LIMIT 1) AS origin_level_one_area_public_id,
       (SELECT BIN_TO_UUID(a.public_id) FROM people_person_geographies pg INNER JOIN geography_administrative_areas a ON a.id = pg.level_two_area_id WHERE pg.person_id = p.id AND pg.association_type = 'ORIGIN' AND pg.status = 'ACTIVE' LIMIT 1) AS origin_level_two_area_public_id,
       (SELECT BIN_TO_UUID(a.public_id) FROM people_person_geographies pg INNER JOIN geography_administrative_areas a ON a.id = pg.level_one_area_id WHERE pg.person_id = p.id AND pg.association_type = 'RESIDENCE' AND pg.status = 'ACTIVE' LIMIT 1) AS residence_level_one_area_public_id,
       (SELECT BIN_TO_UUID(a.public_id) FROM people_person_geographies pg INNER JOIN geography_administrative_areas a ON a.id = pg.level_two_area_id WHERE pg.person_id = p.id AND pg.association_type = 'RESIDENCE' AND pg.status = 'ACTIVE' LIMIT 1) AS residence_level_two_area_public_id
FROM people_guardianships g
INNER JOIN people_persons p ON p.id = g.dependent_person_id
INNER JOIN people_person_names n ON n.person_id = p.id AND n.name_type = 'PRIMARY' AND n.status = 'ACTIVE'
LEFT JOIN geography_countries c ON c.id = p.nationality_country_id
WHERE g.guardian_person_id = :guardian_id AND g.status = 'ACTIVE' AND p.public_id = :public_id
LIMIT 1{$suffix}
SQL);
        $statement->bindValue(':guardian_id', (int) $guardian['id'], PDO::PARAM_INT);
        $statement->bindValue(':public_id', \Qmdb\Shared\Identifier\UuidV7::fromString($dependentPublicId)->toBinary(), PDO::PARAM_LOB);
        $statement->execute();

        return $this->rowOrNull($statement->fetch(PDO::FETCH_ASSOC));
    }

    /** @param array<string, int|string> $guardian
     * @return array<string, int|string>|null
     */
    public function guardianshipForGuardian(array $guardian, string $guardianshipPublicId, bool $forUpdate = false): ?array
    {
        $suffix = $forUpdate ? ' FOR UPDATE' : '';
        $statement = $this->connection()->prepare("SELECT g.id, g.public_id, g.guardian_person_id, g.dependent_person_id, g.status, g.version, p.birth_date AS dependent_birth_date FROM people_guardianships g INNER JOIN people_persons p ON p.id = g.dependent_person_id WHERE g.guardian_person_id = :guardian_id AND g.public_id = :public_id LIMIT 1{$suffix}");
        $statement->bindValue(':guardian_id', (int) $guardian['id'], PDO::PARAM_INT);
        $statement->bindValue(':public_id', \Qmdb\Shared\Identifier\UuidV7::fromString($guardianshipPublicId)->toBinary(), PDO::PARAM_LOB);
        $statement->execute();

        return $this->rowOrNull($statement->fetch(PDO::FETCH_ASSOC));
    }

    public function guardianshipForDependent(array $guardian, string $dependentPublicId, bool $forUpdate = false): ?array
    {
        $suffix = $forUpdate ? ' FOR UPDATE' : '';
        $statement = $this->connection()->prepare("SELECT g.id, g.public_id, g.guardian_person_id, g.dependent_person_id, g.status, g.version, p.birth_date AS dependent_birth_date FROM people_guardianships g INNER JOIN people_persons p ON p.id = g.dependent_person_id WHERE g.guardian_person_id = :guardian_id AND g.status = 'ACTIVE' AND p.public_id = :public_id LIMIT 1{$suffix}");
        $statement->bindValue(':guardian_id', (int) $guardian['id'], PDO::PARAM_INT);
        $statement->bindValue(':public_id', \Qmdb\Shared\Identifier\UuidV7::fromString($dependentPublicId)->toBinary(), PDO::PARAM_LOB);
        $statement->execute();

        return $this->rowOrNull($statement->fetch(PDO::FETCH_ASSOC));
    }

    /** @param array<string, int|string> $guardianship
     * @return array<string, int|string>
     */
    public function revokeGuardianship(array $guardianship, int $expectedVersion, int $revokedByAccountId, DateTimeImmutable $now): array
    {
        if ((int) $guardianship['version'] !== $expectedVersion || $guardianship['status'] !== 'ACTIVE') {
            throw new \DomainException('Guardianship is stale or unavailable.');
        }
        $statement = $this->connection()->prepare(<<<SQL
UPDATE people_guardianships SET status = 'REVOKED', revoked_by_account_id = :revoked_by_account_id, revoked_at = :now, version = version + 1, updated_at = :now
WHERE id = :id AND status = 'ACTIVE' AND version = :version
SQL);
        $statement->execute(['now' => self::format($now), 'revoked_by_account_id' => $revokedByAccountId, 'id' => (int) $guardianship['id'], 'version' => $expectedVersion]);
        if ($statement->rowCount() !== 1) {
            throw new \DomainException('Guardianship is stale.');
        }
        $select = $this->connection()->prepare('SELECT id, public_id, guardian_person_id, dependent_person_id, status, version FROM people_guardianships WHERE id = :id');
        $select->execute(['id' => (int) $guardianship['id']]);

        return $this->required($this->rowOrNull($select->fetch(PDO::FETCH_ASSOC)));
    }

    public function activeGuardiansForDependent(array $guardianship, bool $forUpdate = false): int
    {
        $suffix = $forUpdate ? ' FOR UPDATE' : '';
        $statement = $this->connection()->prepare("SELECT id FROM people_guardianships WHERE dependent_person_id = :dependent_person_id AND status = 'ACTIVE'{$suffix}");
        $statement->execute(['dependent_person_id' => (int) $guardianship['dependent_person_id']]);

        return count($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, int|string>|null */
    private function profileForPerson(int $personId, bool $forUpdate): ?array
    {
        $suffix = $forUpdate ? ' FOR UPDATE' : '';
        $statement = $this->connection()->prepare(<<<SQL
SELECT p.id, p.public_id, p.registry_code, p.status, p.birth_date, p.sex_classification, BIN_TO_UUID(c.public_id) AS nationality_country_public_id,
       p.version, p.created_by_account_id, n.given_name, n.middle_names, n.family_name, n.display_name, n.script_code,
       n.version AS name_version,
       (SELECT pn.display_name FROM people_person_names pn WHERE pn.person_id = p.id AND pn.name_type = 'PREFERRED' AND pn.status = 'ACTIVE' LIMIT 1) AS preferred_name,
       (SELECT pn.display_name FROM people_person_names pn WHERE pn.person_id = p.id AND pn.name_type = 'ARABIC' AND pn.status = 'ACTIVE' LIMIT 1) AS arabic_name,
       (SELECT BIN_TO_UUID(a.public_id) FROM people_person_geographies pg INNER JOIN geography_administrative_areas a ON a.id = pg.level_one_area_id WHERE pg.person_id = p.id AND pg.association_type = 'ORIGIN' AND pg.status = 'ACTIVE' LIMIT 1) AS origin_level_one_area_public_id,
       (SELECT BIN_TO_UUID(a.public_id) FROM people_person_geographies pg INNER JOIN geography_administrative_areas a ON a.id = pg.level_two_area_id WHERE pg.person_id = p.id AND pg.association_type = 'ORIGIN' AND pg.status = 'ACTIVE' LIMIT 1) AS origin_level_two_area_public_id,
       (SELECT BIN_TO_UUID(a.public_id) FROM people_person_geographies pg INNER JOIN geography_administrative_areas a ON a.id = pg.level_one_area_id WHERE pg.person_id = p.id AND pg.association_type = 'RESIDENCE' AND pg.status = 'ACTIVE' LIMIT 1) AS residence_level_one_area_public_id,
       (SELECT BIN_TO_UUID(a.public_id) FROM people_person_geographies pg INNER JOIN geography_administrative_areas a ON a.id = pg.level_two_area_id WHERE pg.person_id = p.id AND pg.association_type = 'RESIDENCE' AND pg.status = 'ACTIVE' LIMIT 1) AS residence_level_two_area_public_id
FROM people_persons p INNER JOIN people_person_names n ON n.person_id = p.id AND n.name_type = 'PRIMARY' AND n.status = 'ACTIVE'
LEFT JOIN geography_countries c ON c.id = p.nationality_country_id
WHERE p.id = :id LIMIT 1{$suffix}
SQL);
        $statement->execute(['id' => $personId]);

        return $this->rowOrNull($statement->fetch(PDO::FETCH_ASSOC));
    }

    /** @return array<string, int|string>|null */
    private function roleRowForPerson(int $personId, string $roleType, bool $forUpdate): ?array
    {
        $suffix = $forUpdate ? ' FOR UPDATE' : '';
        $statement = $this->connection()->prepare("SELECT id, public_id, person_id, role_type, status, version FROM people_role_profiles WHERE person_id = :person_id AND role_type = :role_type LIMIT 1{$suffix}");
        $statement->execute(['person_id' => $personId, 'role_type' => $roleType]);

        return $this->rowOrNull($statement->fetch(PDO::FETCH_ASSOC));
    }

    /** @return array<string, int|string>|null */
    private function progressForPerson(int $personId, bool $forUpdate): ?array
    {
        $suffix = $forUpdate ? ' FOR UPDATE' : '';
        $statement = $this->connection()->prepare("SELECT person_id, role_profile_id, progress_status, memorized_juz_count, completed_on, version FROM people_memorizer_progress WHERE person_id = :person_id LIMIT 1{$suffix}");
        $statement->execute(['person_id' => $personId]);

        return $this->rowOrNull($statement->fetch(PDO::FETCH_ASSOC));
    }

    private function insertName(int $personId, string $publicIdBinary, PersonProfileInput $input, DateTimeImmutable $now): void
    {
        $name = $this->connection()->prepare(<<<SQL
INSERT INTO people_person_names (public_id, person_id, name_type, script_code, given_name, middle_names, family_name,
    display_name, search_name, status, version, effective_at, superseded_at, created_at, updated_at)
VALUES (:public_id, :person_id, 'PRIMARY', 'LATIN', :given_name, :middle_names, :family_name, :display_name, :search_name,
    'ACTIVE', 1, :now, NULL, :now, :now)
SQL);
        $name->bindValue(':public_id', $publicIdBinary, PDO::PARAM_LOB);
        $name->bindValue(':person_id', $personId, PDO::PARAM_INT);
        $name->bindValue(':given_name', $input->name->givenName());
        $name->bindValue(':middle_names', $input->name->middleNames());
        $name->bindValue(':family_name', $input->name->familyName());
        $name->bindValue(':display_name', $input->name->displayName());
        $name->bindValue(':search_name', $input->searchName);
        $name->bindValue(':now', self::format($now));
        $name->execute();
    }

    private function replaceNameVariants(int $personId, PersonProfileInput $input, DateTimeImmutable $now): void
    {
        foreach (['PREFERRED' => $input->preferredName, 'ARABIC' => $input->arabicName] as $type => $displayName) {
            $supersede = $this->connection()->prepare("UPDATE people_person_names SET status = 'SUPERSEDED', superseded_at = :now, version = version + 1, updated_at = :now WHERE person_id = :person_id AND name_type = :name_type AND status = 'ACTIVE'");
            $supersede->execute(['now' => self::format($now), 'person_id' => $personId, 'name_type' => $type]);
            if ($displayName === null) {
                continue;
            }
            $insert = $this->connection()->prepare(<<<SQL
INSERT INTO people_person_names (public_id, person_id, name_type, script_code, given_name, middle_names, family_name,
    display_name, search_name, status, version, effective_at, superseded_at, created_at, updated_at)
VALUES (:public_id, :person_id, :name_type, :script_code, NULL, NULL, NULL, :display_name, :search_name,
    'ACTIVE', 1, :now, NULL, :now, :now)
SQL);
            $insert->bindValue(':public_id', \Qmdb\Shared\Identifier\UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
            $insert->bindValue(':person_id', $personId, PDO::PARAM_INT);
            $insert->bindValue(':name_type', $type);
            $insert->bindValue(':script_code', $type === 'ARABIC' ? 'ARABIC' : 'LATIN');
            $insert->bindValue(':display_name', $displayName);
            $insert->bindValue(':search_name', mb_strtolower($displayName, 'UTF-8'));
            $insert->bindValue(':now', self::format($now));
            $insert->execute();
        }
    }

    private function replaceGeographies(int $personId, PersonProfileInput $input, string $source, DateTimeImmutable $now): void
    {
        if ($input->nationalityCountryPublicId === null) {
            if ($input->originLevelOneAreaPublicId !== null || $input->originLevelTwoAreaPublicId !== null || $input->residenceLevelOneAreaPublicId !== null || $input->residenceLevelTwoAreaPublicId !== null) {
                throw new \InvalidArgumentException('A country is required for Person geography associations.');
            }
        }
        $countryId = $this->countryId($input->nationalityCountryPublicId);
        foreach (
            [
            'ORIGIN' => [$input->originLevelOneAreaPublicId, $input->originLevelTwoAreaPublicId],
            'RESIDENCE' => [$input->residenceLevelOneAreaPublicId, $input->residenceLevelTwoAreaPublicId],
            ] as $type => [$levelOnePublicId, $levelTwoPublicId]
        ) {
            $supersede = $this->connection()->prepare("UPDATE people_person_geographies SET status = 'SUPERSEDED', superseded_at = :now, version = version + 1, updated_at = :now WHERE person_id = :person_id AND association_type = :type AND status = 'ACTIVE'");
            $supersede->execute(['now' => self::format($now), 'person_id' => $personId, 'type' => $type]);
            if ($levelOnePublicId === null && $levelTwoPublicId === null) {
                continue;
            }
            if ($countryId === null || $levelOnePublicId === null) {
                throw new \InvalidArgumentException('A level-one geography area is required.');
            }
            $levelOne = $this->areaId($levelOnePublicId, $countryId, 1, null);
            $levelTwo = $levelTwoPublicId === null ? null : $this->areaId($levelTwoPublicId, $countryId, 2, $levelOne);
            $insert = $this->connection()->prepare(<<<SQL
INSERT INTO people_person_geographies (public_id, person_id, association_type, country_id, level_one_area_id, level_two_area_id,
    source_type, status, version, effective_at, superseded_at, created_at, updated_at)
VALUES (:public_id, :person_id, :type, :country_id, :level_one, :level_two, :source, 'ACTIVE', 1, :now, NULL, :now, :now)
SQL);
            $insert->bindValue(':public_id', \Qmdb\Shared\Identifier\UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
            $insert->bindValue(':person_id', $personId, PDO::PARAM_INT);
            $insert->bindValue(':type', $type);
            $insert->bindValue(':country_id', $countryId, PDO::PARAM_INT);
            $insert->bindValue(':level_one', $levelOne, PDO::PARAM_INT);
            $insert->bindValue(':level_two', $levelTwo, $levelTwo === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $insert->bindValue(':source', $source);
            $insert->bindValue(':now', self::format($now));
            $insert->execute();
        }
    }

    private function connection(): PDO
    {
        return $this->provider->connection();
    }

    private function countryId(?string $publicId): ?int
    {
        if ($publicId === null) {
            return null;
        }
        $statement = $this->connection()->prepare("SELECT id FROM geography_countries WHERE public_id = :public_id AND status = 'ACTIVE' LIMIT 1");
        $statement->bindValue(':public_id', \Qmdb\Shared\Identifier\UuidV7::fromString($publicId)->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $value = $statement->fetchColumn();
        if (!is_int($value) && (!is_string($value) || preg_match('/\A[1-9][0-9]*\z/', $value) !== 1)) {
            throw new \InvalidArgumentException('Nationality country is unavailable.');
        }

        return (int) $value;
    }

    private function areaId(string $publicId, int $countryId, int $level, ?int $parentId): int
    {
        $statement = $this->connection()->prepare("SELECT id FROM geography_administrative_areas WHERE public_id = :public_id AND country_id = :country_id AND administrative_level = :level AND status = 'ACTIVE' AND ((:parent_id IS NULL AND parent_area_id IS NULL) OR parent_area_id = :parent_id) LIMIT 1");
        $statement->bindValue(':public_id', \Qmdb\Shared\Identifier\UuidV7::fromString($publicId)->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':country_id', $countryId, PDO::PARAM_INT);
        $statement->bindValue(':level', $level, PDO::PARAM_INT);
        $statement->bindValue(':parent_id', $parentId, $parentId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->execute();
        $value = $statement->fetchColumn();
        if (!is_int($value) && (!is_string($value) || preg_match('/\A[1-9][0-9]*\z/', $value) !== 1)) {
            throw new \InvalidArgumentException('Geography area is unavailable.');
        }

        return (int) $value;
    }

    /** @param array<string, int|string>|null $row
     * @return array<string, int|string>
     */
    private function required(?array $row): array
    {
        if ($row === null) {
            throw new \UnexpectedValueException('Expected Person profile record is unavailable.');
        }

        return $row;
    }

    /** @return array<string, int|string>|null */
    private function rowOrNull(mixed $row): ?array
    {
        if (!is_array($row)) {
            return null;
        }
        $normalized = [];
        foreach ($row as $key => $value) {
            if (!is_string($key)) {
                throw new \UnexpectedValueException('Person profile persistence row is invalid.');
            }
            if (in_array($key, ['id', 'version', 'name_version', 'guardianship_version', 'person_id', 'role_profile_id', 'guardian_person_id', 'dependent_person_id', 'created_by_account_id', 'revoked_by_account_id', 'nationality_country_id', 'origin_level_one_area_id', 'origin_level_two_area_id', 'residence_level_one_area_id', 'residence_level_two_area_id', 'memorized_juz_count'], true)) {
                if (is_int($value)) {
                    $normalized[$key] = $value;
                    continue;
                }
                if (is_string($value) && preg_match('/\A[0-9]+\z/', $value) === 1) {
                    $normalized[$key] = (int) $value;
                    continue;
                }
                if ($value === null && in_array($key, ['nationality_country_id', 'origin_level_one_area_id', 'origin_level_two_area_id', 'residence_level_one_area_id', 'residence_level_two_area_id', 'revoked_by_account_id'], true)) {
                    $normalized[$key] = '';
                    continue;
                }
                throw new \UnexpectedValueException('Person profile numeric persistence value is invalid.');
            }
            if (is_string($value)) {
                $normalized[$key] = $value;
                continue;
            }
            if ($value === null) {
                $normalized[$key] = '';
                continue;
            }
            throw new \UnexpectedValueException('Person profile persistence value is invalid.');
        }

        return $normalized;
    }

    /** @param array<string, int|string> $person */
    private function requireVersion(array $person, int $expectedVersion): void
    {
        if (($person['status'] ?? null) !== 'ACTIVE' || (int) ($person['version'] ?? 0) !== $expectedVersion) {
            throw new \DomainException('Person profile is stale or unavailable.');
        }
    }

    private static function format(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
