<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Infrastructure\Persistence;

use PDO;
use Qmdb\Modules\People\Application\PersonCanonicalizationParticipant;
use Qmdb\Modules\People\Application\PersonCanonicalizationPreflight;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

/**
 * Bounded People-profile participant. It never commits and deliberately leaves
 * names, demographic values and geography historical rather than choosing one.
 */
final readonly class MySqlPeopleProfilesCanonicalizationParticipant implements PersonCanonicalizationParticipant
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function preflight(int $sourcePersonId, int $canonicalPersonId, int $maximumAffectedRecords): PersonCanonicalizationPreflight
    {
        $connection = $this->provider->connection();
        $conflicts = [];
        if ($this->count("SELECT COUNT(*) FROM people_account_links WHERE person_id IN (:source,:target) AND link_type = 'SELF' AND status = 'ACTIVE'", ['source' => $sourcePersonId, 'target' => $canonicalPersonId]) > 1) {
            $conflicts[] = 'IDENTITY_LINK_CONFLICT';
        }
        $sourceProgress = $this->progress($sourcePersonId);
        $targetProgress = $this->progress($canonicalPersonId);
        if ($sourceProgress !== null && $targetProgress !== null && ($sourceProgress['progress_status'] !== $targetProgress['progress_status'] || $sourceProgress['memorized_juz_count'] !== $targetProgress['memorized_juz_count'] || $sourceProgress['completed_on'] !== $targetProgress['completed_on'])) {
            $conflicts[] = 'MEMORIZER_PROGRESS_CONFLICT';
        }
        if ($this->hasGuardianConflict($sourcePersonId, $canonicalPersonId)) {
            $conflicts[] = 'GUARDIANSHIP_CONFLICT';
        }
        $affected = $this->count('SELECT COUNT(*) FROM people_role_profiles WHERE person_id = :source', ['source' => $sourcePersonId])
            + $this->count('SELECT COUNT(*) FROM people_guardianships WHERE guardian_person_id = :source OR dependent_person_id = :source', ['source' => $sourcePersonId])
            + $this->count('SELECT COUNT(*) FROM people_profile_verification_assertions WHERE person_id = :source AND status = \'ACTIVE\'', ['source' => $sourcePersonId])
            + $this->count('SELECT COUNT(*) FROM people_profile_claims WHERE person_id = :source AND status = \'PENDING_ACCEPTANCE\'', ['source' => $sourcePersonId]);
        if ($affected > $maximumAffectedRecords) {
            $conflicts[] = 'AFFECTED_RECORD_LIMIT_EXCEEDED';
        }

        return new PersonCanonicalizationPreflight(array_values(array_unique($conflicts)), $affected);
    }

    public function apply(int $sourcePersonId, int $canonicalPersonId, int $actorAccountId): int
    {
        $connection = $this->provider->connection();
        if (!$connection->inTransaction()) {
            throw new \LogicException('People canonicalization requires an active caller-owned transaction.');
        }
        $affected = $this->copyRoles($sourcePersonId, $canonicalPersonId);
        $affected += $this->copyProgress($sourcePersonId, $canonicalPersonId);
        $affected += $this->transferSelfLink($sourcePersonId, $canonicalPersonId);
        $affected += $this->transferGuardianships($sourcePersonId, $canonicalPersonId, $actorAccountId);

        return $affected;
    }

    private function copyRoles(int $sourcePersonId, int $canonicalPersonId): int
    {
        $connection = $this->provider->connection();
        $roles = $connection->prepare("SELECT role_type,status,source_type FROM people_role_profiles WHERE person_id = :source FOR UPDATE");
        $roles->execute(['source' => $sourcePersonId]);
        $affected = 0;
        foreach ($this->rows($roles) as $role) {
            $target = $connection->prepare('SELECT id,status FROM people_role_profiles WHERE person_id = :target AND role_type = :role_type FOR UPDATE');
            $roleType = $this->stringValue($role, 'role_type');
            $target->execute(['target' => $canonicalPersonId, 'role_type' => $roleType]);
            if ($target->fetch(PDO::FETCH_ASSOC) === false) {
                $insert = $connection->prepare("INSERT INTO people_role_profiles (public_id,person_id,role_type,status,source_type,version,activated_at,deactivated_at,created_at,updated_at) VALUES (:public_id,:person_id,:role_type,:status,:source_type,1,CASE WHEN :status = 'ACTIVE' THEN UTC_TIMESTAMP(6) ELSE NULL END,CASE WHEN :status = 'INACTIVE' THEN UTC_TIMESTAMP(6) ELSE NULL END,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
                $insert->bindValue(':public_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
                $insert->execute(['person_id' => $canonicalPersonId, 'role_type' => $roleType, 'status' => $this->stringValue($role, 'status'), 'source_type' => $this->stringValue($role, 'source_type')]);
                ++$affected;
            }
        }
        $deactivate = $connection->prepare("UPDATE people_role_profiles SET status = 'INACTIVE', deactivated_at = COALESCE(deactivated_at, UTC_TIMESTAMP(6)), version = version + 1, updated_at = UTC_TIMESTAMP(6) WHERE person_id = :source AND status = 'ACTIVE'");
        $deactivate->execute(['source' => $sourcePersonId]);

        return $affected + $deactivate->rowCount();
    }

    private function copyProgress(int $sourcePersonId, int $canonicalPersonId): int
    {
        $source = $this->progress($sourcePersonId);
        if ($source === null || $this->progress($canonicalPersonId) !== null) {
            return 0;
        }
        $connection = $this->provider->connection();
        $role = $connection->prepare("SELECT id FROM people_role_profiles WHERE person_id = :person_id AND role_type = 'MEMORIZER' LIMIT 1 FOR UPDATE");
        $role->execute(['person_id' => $canonicalPersonId]);
        $roleId = $role->fetchColumn();
        if ($roleId === false) {
            throw new \DomainException('Canonical Person memorizer role is unavailable.');
        }
        $insert = $connection->prepare('INSERT INTO people_memorizer_progress (person_id,role_profile_id,progress_status,memorized_juz_count,completed_on,source_type,version,created_at,updated_at) VALUES (:person_id,:role_profile_id,:status,:juz_count,:completed_on,:source_type,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))');
        $insert->execute(['person_id' => $canonicalPersonId, 'role_profile_id' => (int) $roleId, 'status' => $source['progress_status'], 'juz_count' => $source['memorized_juz_count'], 'completed_on' => $source['completed_on'], 'source_type' => $source['source_type']]);

        return 1;
    }

    private function transferSelfLink(int $sourcePersonId, int $canonicalPersonId): int
    {
        $connection = $this->provider->connection();
        $link = $connection->prepare("SELECT id,account_id FROM people_account_links WHERE person_id = :source AND link_type = 'SELF' AND status = 'ACTIVE' LIMIT 1 FOR UPDATE");
        $link->execute(['source' => $sourcePersonId]);
        $source = $this->row($link);
        if ($source === false) {
            return 0;
        }
        $target = $connection->prepare("SELECT id FROM people_account_links WHERE person_id = :target AND link_type = 'SELF' AND status = 'ACTIVE' LIMIT 1 FOR UPDATE");
        $target->execute(['target' => $canonicalPersonId]);
        if ($target->fetchColumn() !== false) {
            throw new \DomainException('Canonical Person identity-link conflict exists.');
        }
        $revoke = $connection->prepare("UPDATE people_account_links SET status = 'REVOKED', revoked_at = UTC_TIMESTAMP(6), version = version + 1, updated_at = UTC_TIMESTAMP(6) WHERE id = :id AND status = 'ACTIVE'");
        $revoke->execute(['id' => $this->integerValue($source, 'id')]);
        $insert = $connection->prepare("INSERT INTO people_account_links (public_id,account_id,person_id,link_type,status,version,linked_at,revoked_at,created_at,updated_at) VALUES (:public_id,:account_id,:person_id,'SELF','ACTIVE',1,UTC_TIMESTAMP(6),NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        $insert->bindValue(':public_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        $insert->execute(['account_id' => $this->integerValue($source, 'account_id'), 'person_id' => $canonicalPersonId]);

        return 2;
    }

    private function transferGuardianships(int $sourcePersonId, int $canonicalPersonId, int $actorAccountId): int
    {
        $connection = $this->provider->connection();
        $statement = $connection->prepare("SELECT guardian_person_id,dependent_person_id,relationship_type,authority_basis FROM people_guardianships WHERE (guardian_person_id = :source OR dependent_person_id = :source) AND status = 'ACTIVE' FOR UPDATE");
        $statement->execute(['source' => $sourcePersonId]);
        $affected = 0;
        foreach ($this->rows($statement) as $relationship) {
            $relationshipGuardian = $this->integerValue($relationship, 'guardian_person_id');
            $relationshipDependent = $this->integerValue($relationship, 'dependent_person_id');
            $guardian = $relationshipGuardian === $sourcePersonId ? $canonicalPersonId : $relationshipGuardian;
            $dependent = $relationshipDependent === $sourcePersonId ? $canonicalPersonId : $relationshipDependent;
            if ($guardian === $dependent) {
                throw new \DomainException('Canonical Person guardianship conflict exists.');
            }
            $exists = $connection->prepare("SELECT id FROM people_guardianships WHERE guardian_person_id = :guardian AND dependent_person_id = :dependent AND authority_scope = 'PROFILE_MANAGEMENT' AND status = 'ACTIVE' LIMIT 1 FOR UPDATE");
            $exists->execute(['guardian' => $guardian, 'dependent' => $dependent]);
            if ($exists->fetchColumn() === false) {
                $insert = $connection->prepare("INSERT INTO people_guardianships (public_id,guardian_person_id,dependent_person_id,relationship_type,authority_scope,authority_basis,status,version,created_by_account_id,revoked_by_account_id,confirmed_at,revoked_at,created_at,updated_at) VALUES (:public_id,:guardian,:dependent,:relationship_type,'PROFILE_MANAGEMENT',:authority_basis,'ACTIVE',1,:actor,NULL,UTC_TIMESTAMP(6),NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
                $insert->bindValue(':public_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
                $insert->execute(['guardian' => $guardian, 'dependent' => $dependent, 'relationship_type' => $this->stringValue($relationship, 'relationship_type'), 'authority_basis' => $this->stringValue($relationship, 'authority_basis'), 'actor' => $actorAccountId]);
                ++$affected;
            }
        }
        $revoke = $connection->prepare("UPDATE people_guardianships SET status = 'REVOKED', revoked_by_account_id = :actor, revoked_at = UTC_TIMESTAMP(6), version = version + 1, updated_at = UTC_TIMESTAMP(6) WHERE (guardian_person_id = :source OR dependent_person_id = :source) AND status = 'ACTIVE'");
        $revoke->execute(['source' => $sourcePersonId, 'actor' => $actorAccountId]);

        return $affected + $revoke->rowCount();
    }

    /** @return array{progress_status:string,memorized_juz_count:int,completed_on:?string,source_type:string}|null */
    private function progress(int $personId): ?array
    {
        $statement = $this->provider->connection()->prepare('SELECT progress_status,memorized_juz_count,completed_on,source_type FROM people_memorizer_progress WHERE person_id = :person_id LIMIT 1');
        $statement->execute(['person_id' => $personId]);
        $row = $this->row($statement);
        if ($row === false) {
            return null;
        }

        return ['progress_status' => $this->stringValue($row, 'progress_status'), 'memorized_juz_count' => $this->integerValue($row, 'memorized_juz_count'), 'completed_on' => $this->nullableStringValue($row, 'completed_on'), 'source_type' => $this->stringValue($row, 'source_type')];
    }

    private function hasGuardianConflict(int $sourcePersonId, int $canonicalPersonId): bool
    {
        $statement = $this->provider->connection()->prepare("SELECT 1 FROM people_guardianships WHERE status = 'ACTIVE' AND ((guardian_person_id = :source AND dependent_person_id = :canonical) OR (guardian_person_id = :canonical AND dependent_person_id = :source)) LIMIT 1");
        $statement->execute(['source' => $sourcePersonId, 'canonical' => $canonicalPersonId]);

        return $statement->fetchColumn() !== false;
    }

    /** @param array<string,int> $parameters */
    private function count(string $sql, array $parameters): int
    {
        $statement = $this->provider->connection()->prepare($sql);
        $statement->execute($parameters);

        $value = $statement->fetchColumn();
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new \UnexpectedValueException('People canonicalization count is invalid.');
    }

    /** @return list<array<string, mixed>> */
    private function rows(\PDOStatement $statement): array
    {
        $fetched = $statement->fetchAll(PDO::FETCH_ASSOC);
        $rows = [];
        foreach ($fetched as $item) {
            if (!is_array($item)) {
                throw new \UnexpectedValueException('People canonicalization row is invalid.');
            }
            $row = [];
            foreach ($item as $key => $value) {
                $row[(string) $key] = $value;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return array<string, mixed>|false */
    private function row(\PDOStatement $statement): array|false
    {
        $fetched = $statement->fetch(PDO::FETCH_ASSOC);
        if ($fetched === false) {
            return false;
        }
        if (!is_array($fetched)) {
            throw new \UnexpectedValueException('People canonicalization row is invalid.');
        }
        $row = [];
        foreach ($fetched as $key => $value) {
            $row[(string) $key] = $value;
        }

        return $row;
    }

    /** @param array<string, mixed> $row */
    private function stringValue(array $row, string $field): string
    {
        $value = $row[$field] ?? null;
        if (!is_string($value)) {
            throw new \UnexpectedValueException('People canonicalization field is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private function nullableStringValue(array $row, string $field): ?string
    {
        $value = $row[$field] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new \UnexpectedValueException('People canonicalization field is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private function integerValue(array $row, string $field): int
    {
        $value = $row[$field] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new \UnexpectedValueException('People canonicalization integer field is invalid.');
    }
}
