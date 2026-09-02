<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\IdentityResolution\Configuration\IdentityResolutionConfiguration;
use Qmdb\Modules\IdentityResolution\Application\IdentityResolutionRepository;
use Qmdb\Modules\IdentityResolution\Domain\ProfileClaimPairingCode;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final readonly class MySqlIdentityResolutionRepository implements IdentityResolutionRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function report(): array
    {
        $connection = $this->provider->connection();
        return [
            'active_pairings' => $this->count($connection, "SELECT COUNT(*) FROM people_profile_claim_pairings WHERE status = 'ACTIVE'"),
            'pending_claims' => $this->count($connection, "SELECT COUNT(*) FROM people_profile_claims WHERE status = 'PENDING_ACCEPTANCE'"),
            'accepted_claims' => $this->count($connection, "SELECT COUNT(*) FROM people_profile_claims WHERE status = 'ACCEPTED'"),
            'active_assertions' => $this->count($connection, "SELECT COUNT(*) FROM people_profile_verification_assertions WHERE status = 'ACTIVE'"),
            'open_cases' => $this->count($connection, "SELECT COUNT(*) FROM people_duplicate_cases WHERE status IN ('REPORTED','CONSENT_REQUIRED','READY_FOR_REVIEW','UNDER_REVIEW')"),
            'blocked_cases' => $this->count($connection, "SELECT COUNT(*) FROM people_duplicate_cases WHERE status = 'BLOCKED'"),
            'resolved_cases' => $this->count($connection, "SELECT COUNT(*) FROM people_duplicate_cases WHERE status = 'RESOLVED'"),
            'aliases' => $this->count($connection, 'SELECT COUNT(*) FROM people_person_aliases'),
            'invalid_rows' => $this->invalidRows($connection),
        ];
    }

    public function expirePairings(DateTimeImmutable $now, int $limit): int
    {
        $statement = $this->provider->connection()->prepare("UPDATE people_profile_claim_pairings SET status = 'EXPIRED', version = version + 1, updated_at = :updated_at WHERE status = 'ACTIVE' AND expires_at <= :expires_before ORDER BY expires_at, id LIMIT :limit");
        $statement->bindValue(':updated_at', self::time($now));
        $statement->bindValue(':expires_before', self::time($now));
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount();
    }

    public function expireClaims(DateTimeImmutable $now, int $limit): int
    {
        $expired = 0;
        foreach ($this->expiredPendingClaims($now, $limit) as $claim) {
            $this->transitionClaim($claim, 'EXPIRED', 0, 'PROFILE_CLAIM_EXPIRED', $now);
            ++$expired;
        }

        return $expired;
    }

    /** @return list<array<string, int|string>> */
    public function expiredPendingClaims(DateTimeImmutable $now, int $limit): array
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT id,BIN_TO_UUID(public_id) AS public_id,pairing_id,person_id,claimant_account_id,'
            . 'authorization_type,authorized_by_account_id,authorization_guardianship_id,status,'
            . 'review_reference,review_justification,expires_at,version '
            . "FROM people_profile_claims WHERE status = 'PENDING_ACCEPTANCE' AND expires_at <= :now "
            . 'ORDER BY expires_at,id LIMIT :limit FOR UPDATE',
        );
        $statement->bindValue(':now', self::time($now));
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $this->rows($this->fetchAllRows($statement));
    }

    /** @return array<string, int|string>|null */
    public function activeAccount(int $accountId, bool $forUpdate = false): ?array
    {
        return $this->row($this->select('SELECT id, BIN_TO_UUID(public_id) AS public_id, account_status, preferred_locale FROM user_accounts WHERE id = :id AND account_status = \'ACTIVE\' LIMIT 1', ['id' => $accountId], $forUpdate));
    }

    /** @return array<string, int|string>|null */
    public function activeSelfLinkForAccount(int $accountId, bool $forUpdate = false): ?array
    {
        return $this->row($this->select("SELECT l.id, l.account_id, l.person_id, l.version, BIN_TO_UUID(l.public_id) AS public_id FROM people_account_links l WHERE l.account_id = :account_id AND l.link_type = 'SELF' AND l.status = 'ACTIVE' LIMIT 1", ['account_id' => $accountId], $forUpdate));
    }

    /** @return array<string, int|string>|null */
    public function activeSelfLinkForPerson(int $personId, bool $forUpdate = false): ?array
    {
        return $this->row($this->select("SELECT l.id, l.account_id, l.person_id, l.version, BIN_TO_UUID(l.public_id) AS public_id FROM people_account_links l WHERE l.person_id = :person_id AND l.link_type = 'SELF' AND l.status = 'ACTIVE' LIMIT 1", ['person_id' => $personId], $forUpdate));
    }

    /** @return array<string, int|string>|null */
    public function pendingClaimForAccount(int $accountId, bool $forUpdate = false): ?array
    {
        return $this->row($this->select("SELECT id, BIN_TO_UUID(public_id) AS public_id, person_id, claimant_account_id, status, version, authorization_type, expires_at FROM people_profile_claims WHERE claimant_account_id = :account_id AND status = 'PENDING_ACCEPTANCE' LIMIT 1", ['account_id' => $accountId], $forUpdate));
    }

    /** @return array<string, int|string>|null */
    public function pendingClaimForPerson(int $personId, bool $forUpdate = false): ?array
    {
        return $this->row($this->select("SELECT id, BIN_TO_UUID(public_id) AS public_id, person_id, claimant_account_id, status, version, authorization_type, expires_at FROM people_profile_claims WHERE person_id = :person_id AND status = 'PENDING_ACCEPTANCE' LIMIT 1", ['person_id' => $personId], $forUpdate));
    }

    /** @return array<string, int|string>|null */
    public function pairingBySelector(string $selector, bool $forUpdate = false): ?array
    {
        return $this->row($this->select('SELECT id, BIN_TO_UUID(public_id) AS public_id, account_id, code_selector, secret_hash, hmac_key_version, status, attempt_count, max_attempts, expires_at, version FROM people_profile_claim_pairings WHERE code_selector = :selector LIMIT 1', ['selector' => $selector], $forUpdate));
    }

    /** @return array<string, int|string>|null */
    public function activePairingForAccount(int $accountId, bool $forUpdate = false): ?array
    {
        return $this->row($this->select("SELECT id, BIN_TO_UUID(public_id) AS public_id, account_id, status, expires_at, version FROM people_profile_claim_pairings WHERE account_id = :account_id AND status = 'ACTIVE' LIMIT 1", ['account_id' => $accountId], $forUpdate));
    }

    /**
     * @param list<int> $personIds
     * @return list<string>
     */
    public function activeAssertionTypesForPersonIds(array $personIds): array
    {
        if ($personIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($personIds), '?'));
        $statement = $this->provider->connection()->prepare("SELECT DISTINCT assertion_type FROM people_profile_verification_assertions WHERE person_id IN ({$placeholders}) AND status = 'ACTIVE' ORDER BY assertion_type");
        foreach ($personIds as $position => $personId) {
            $statement->bindValue($position + 1, $personId, PDO::PARAM_INT);
        }
        $statement->execute();
        $types = [];
        foreach ($this->fetchAllRows($statement) as $row) {
            $types[] = $this->stringValue($row, 'assertion_type');
        }

        return $types;
    }

    /** @return list<array<string, int|string>> */
    public function claimsForAccount(int $accountId, int $limit = 50): array
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT c.id,BIN_TO_UUID(c.public_id) AS public_id,c.person_id,c.authorization_type,c.status,'
            . 'c.expires_at,c.version,p.display_name FROM people_profile_claims c '
            . 'INNER JOIN people_persons p ON p.id = c.person_id '
            . 'WHERE c.claimant_account_id = :account_id ORDER BY c.created_at DESC,c.id DESC LIMIT :limit',
        );
        $statement->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $this->rows($this->fetchAllRows($statement));
    }

    /** @return array<string, int|string>|null */
    public function claimDetailForAccount(string $claimPublicId, int $accountId): ?array
    {
        return $this->row($this->select(
            'SELECT c.id,BIN_TO_UUID(c.public_id) AS public_id,c.person_id,c.authorization_type,c.status,'
            . 'c.expires_at,c.version,p.display_name FROM people_profile_claims c '
            . 'INNER JOIN people_persons p ON p.id = c.person_id '
            . 'WHERE c.public_id = :public_id AND c.claimant_account_id = :account_id LIMIT 1',
            ['public_id' => UuidV7::fromString($claimPublicId)->toBinary(), 'account_id' => $accountId],
            false,
            ['public_id'],
        ));
    }

    /** @return list<array<string, int|string>> */
    public function platformClaimQueue(int $limit = 100): array
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT BIN_TO_UUID(c.public_id) AS public_id,c.authorization_type,c.status,c.expires_at,c.version,'
            . 'p.registry_code,p.display_name FROM people_profile_claims c '
            . 'INNER JOIN people_persons p ON p.id = c.person_id '
            . "WHERE c.status = 'PENDING_ACCEPTANCE' ORDER BY c.created_at ASC,c.id ASC LIMIT :limit",
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $this->rows($this->fetchAllRows($statement));
    }

    /** @return array<string, int|string>|null */
    public function pairingForAccount(string $pairingPublicId, int $accountId, bool $forUpdate = false): ?array
    {
        return $this->row($this->select('SELECT id, BIN_TO_UUID(public_id) AS public_id, account_id, status, expires_at, version FROM people_profile_claim_pairings WHERE public_id = :public_id AND account_id = :account_id LIMIT 1', ['public_id' => UuidV7::fromString($pairingPublicId)->toBinary(), 'account_id' => $accountId], $forUpdate, ['public_id']));
    }

    /** @param array<string, int|string> $pairing */
    public function revokePairing(array $pairing, DateTimeImmutable $now): void
    {
        $statement = $this->provider->connection()->prepare("UPDATE people_profile_claim_pairings SET status = 'REVOKED', revoked_at = :revoked_at, version = version + 1, updated_at = :updated_at WHERE id = :id AND account_id = :account_id AND status = 'ACTIVE' AND version = :version");
        $statement->execute(['revoked_at' => self::time($now), 'updated_at' => self::time($now), 'id' => (int) $pairing['id'], 'account_id' => (int) $pairing['account_id'], 'version' => (int) $pairing['version']]);
        if ($statement->rowCount() !== 1) {
            throw new \DomainException('Profile claim pairing is unavailable.');
        }
    }

    /** @return array<string, int|string> */
    public function createPairing(string $publicId, int $accountId, ProfileClaimPairingCode $code, string $secretHash, IdentityResolutionConfiguration $configuration, DateTimeImmutable $now): array
    {
        $connection = $this->provider->connection();
        $revoke = $connection->prepare("UPDATE people_profile_claim_pairings SET status = 'REVOKED', revoked_at = :revoked_at, version = version + 1, updated_at = :updated_at WHERE account_id = :account_id AND status = 'ACTIVE'");
        $revoke->execute(['revoked_at' => self::time($now), 'updated_at' => self::time($now), 'account_id' => $accountId]);
        $insert = $connection->prepare("INSERT INTO people_profile_claim_pairings (public_id,account_id,code_selector,secret_hash,hmac_key_version,status,attempt_count,max_attempts,expires_at,consumed_at,revoked_at,exhausted_at,version,created_at,updated_at) VALUES (:public_id,:account_id,:selector,:hash,:key_version,'ACTIVE',0,:max_attempts,:expires_at,NULL,NULL,NULL,1,:created_at,:updated_at)");
        $insert->bindValue(':public_id', UuidV7::fromString($publicId)->toBinary(), PDO::PARAM_LOB);
        $insert->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $insert->bindValue(':selector', $code->selector);
        $insert->bindValue(':hash', $secretHash, PDO::PARAM_LOB);
        $insert->bindValue(':key_version', $configuration->pairingHmacKeyVersion, PDO::PARAM_INT);
        $insert->bindValue(':max_attempts', $configuration->pairingMaximumAttempts, PDO::PARAM_INT);
        $insert->bindValue(':expires_at', self::time($now->modify('+' . $configuration->pairingTtlSeconds . ' seconds')));
        $insert->bindValue(':created_at', self::time($now));
        $insert->bindValue(':updated_at', self::time($now));
        $insert->execute();

        return ['id' => (int) $connection->lastInsertId(), 'public_id' => $publicId, 'account_id' => $accountId, 'status' => 'ACTIVE', 'version' => 1];
    }

    /** @param array<string, int|string> $pairing */
    public function recordPairingFailure(array $pairing, DateTimeImmutable $now): void
    {
        if ($pairing['status'] !== 'ACTIVE') {
            return;
        }
        $statement = $this->provider->connection()->prepare("UPDATE people_profile_claim_pairings SET attempt_count = attempt_count + 1, status = CASE WHEN attempt_count + 1 >= max_attempts THEN 'ATTEMPTS_EXHAUSTED' ELSE status END, exhausted_at = CASE WHEN attempt_count + 1 >= max_attempts THEN :exhausted_at ELSE exhausted_at END, version = version + 1, updated_at = :updated_at WHERE id = :id AND status = 'ACTIVE' AND version = :version");
        $statement->execute(['exhausted_at' => self::time($now), 'updated_at' => self::time($now), 'id' => (int) $pairing['id'], 'version' => (int) $pairing['version']]);
    }

    /** @param array<string, int|string> $pairing */
    public function expirePairing(array $pairing, DateTimeImmutable $now): void
    {
        $statement = $this->provider->connection()->prepare("UPDATE people_profile_claim_pairings SET status = 'EXPIRED', version = version + 1, updated_at = :now WHERE id = :id AND status = 'ACTIVE'");
        $statement->execute(['now' => self::time($now), 'id' => (int) $pairing['id']]);
    }

    /** @param array<string, int|string> $pairing */
    public function consumePairing(array $pairing, DateTimeImmutable $now): void
    {
        $statement = $this->provider->connection()->prepare("UPDATE people_profile_claim_pairings SET status = 'CONSUMED', consumed_at = :consumed_at, version = version + 1, updated_at = :updated_at WHERE id = :id AND status = 'ACTIVE' AND version = :version");
        $statement->execute(['consumed_at' => self::time($now), 'updated_at' => self::time($now), 'id' => (int) $pairing['id'], 'version' => (int) $pairing['version']]);
        if ($statement->rowCount() !== 1) {
            throw new \DomainException('Claim pairing is unavailable.');
        }
    }

    /** @return array<string, int|string>|null */
    public function personByRegistryCode(string $registryCode, bool $forUpdate = false): ?array
    {
        return $this->row($this->select("SELECT id, BIN_TO_UUID(public_id) AS public_id, registry_code, status, birth_date, sex_classification, nationality_country_id, version FROM people_persons WHERE registry_code = :registry_code LIMIT 1", ['registry_code' => strtoupper($registryCode)], $forUpdate));
    }

    /** @return array<string, int|string>|null */
    public function personByPublicId(string $publicId, bool $forUpdate = false): ?array
    {
        return $this->row($this->select('SELECT id, BIN_TO_UUID(public_id) AS public_id, registry_code, status, birth_date, sex_classification, nationality_country_id, version FROM people_persons WHERE public_id = :public_id LIMIT 1', ['public_id' => UuidV7::fromString($publicId)->toBinary()], $forUpdate, ['public_id']));
    }

    /** @return array<string, int|string>|null */
    public function personById(int $personId, bool $forUpdate = false): ?array
    {
        return $this->row($this->select('SELECT id, BIN_TO_UUID(public_id) AS public_id, registry_code, status, birth_date, sex_classification, nationality_country_id, version FROM people_persons WHERE id = :id LIMIT 1', ['id' => $personId], $forUpdate));
    }

    /** @return array<string, int|string>|null */
    public function personComparisonDetail(int $personId): ?array
    {
        return $this->row($this->select(
            'SELECT BIN_TO_UUID(public_id) AS public_id,registry_code,display_name,birth_date,sex_classification,'
            . 'nationality_country_id,status,version FROM people_persons WHERE id = :id LIMIT 1',
            ['id' => $personId],
            false,
        ));
    }

    /** @return array<string, int|string>|null */
    public function guardianAuthority(int $accountId, int $personId, bool $forUpdate = false): ?array
    {
        $sql = "SELECT g.id AS guardianship_id, g.version AS guardianship_version, gp.id AS guardian_person_id FROM people_account_links l INNER JOIN people_persons gp ON gp.id = l.person_id AND gp.status = 'ACTIVE' INNER JOIN people_role_profiles r ON r.person_id = gp.id AND r.role_type = 'GUARDIAN' AND r.status = 'ACTIVE' INNER JOIN people_guardianships g ON g.guardian_person_id = gp.id AND g.dependent_person_id = :person_id AND g.authority_scope = 'PROFILE_MANAGEMENT' AND g.status = 'ACTIVE' INNER JOIN user_accounts a ON a.id = l.account_id AND a.account_status = 'ACTIVE' WHERE l.account_id = :account_id AND l.link_type = 'SELF' AND l.status = 'ACTIVE' LIMIT 1";

        return $this->row($this->select($sql, ['account_id' => $accountId, 'person_id' => $personId], $forUpdate));
    }

    /** @return list<array{person_id:int,account_id:int,authority_type:string,guardianship_id:?int}> */
    public function managementAuthorities(int $personId): array
    {
        $sql = "SELECT DISTINCT :self_person_value AS person_id, l.account_id, 'SELF' AS authority_type, NULL AS guardianship_id FROM people_account_links l INNER JOIN user_accounts a ON a.id = l.account_id AND a.account_status = 'ACTIVE' WHERE l.person_id = :self_person_filter AND l.link_type = 'SELF' AND l.status = 'ACTIVE' UNION SELECT DISTINCT :guardian_person_value AS person_id, l.account_id, 'GUARDIAN' AS authority_type, g.id AS guardianship_id FROM people_guardianships g INNER JOIN people_account_links l ON l.person_id = g.guardian_person_id AND l.link_type = 'SELF' AND l.status = 'ACTIVE' INNER JOIN people_role_profiles r ON r.person_id = g.guardian_person_id AND r.role_type = 'GUARDIAN' AND r.status = 'ACTIVE' INNER JOIN user_accounts a ON a.id = l.account_id AND a.account_status = 'ACTIVE' WHERE g.dependent_person_id = :guardian_person_filter AND g.authority_scope = 'PROFILE_MANAGEMENT' AND g.status = 'ACTIVE'";
        $statement = $this->provider->connection()->prepare($sql);
        foreach ([':self_person_value', ':self_person_filter', ':guardian_person_value', ':guardian_person_filter'] as $parameter) {
            $statement->bindValue($parameter, $personId, PDO::PARAM_INT);
        }
        $statement->execute();
        $rows = [];
        foreach ($this->fetchAllRows($statement) as $row) {
            $rows[] = [
                'person_id' => $this->integerValue($row, 'person_id'),
                'account_id' => $this->integerValue($row, 'account_id'),
                'authority_type' => $this->stringValue($row, 'authority_type'),
                'guardianship_id' => $this->nullableIntegerValue($row, 'guardianship_id'),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, int|string> $pairing
     * @return array<string, int|string>
     */
    public function createClaim(string $publicId, array $pairing, int $personId, int $authorizedByAccountId, string $authorizationType, ?int $guardianshipId, ?string $reviewReference, ?string $reviewJustification, IdentityResolutionConfiguration $configuration, DateTimeImmutable $now): array
    {
        $this->consumePairing($pairing, $now);
        $connection = $this->provider->connection();
        $insert = $connection->prepare("INSERT INTO people_profile_claims (public_id,pairing_id,person_id,claimant_account_id,authorization_type,authorized_by_account_id,authorization_guardianship_id,status,review_reference,review_justification,requested_at,expires_at,accepted_at,declined_at,revoked_at,expired_at,version,created_at,updated_at) VALUES (:public_id,:pairing_id,:person_id,:account_id,:authorization_type,:authorized_by_account_id,:guardianship_id,'PENDING_ACCEPTANCE',:review_reference,:review_justification,:requested_at,:expires_at,NULL,NULL,NULL,NULL,1,:created_at,:updated_at)");
        $insert->bindValue(':public_id', UuidV7::fromString($publicId)->toBinary(), PDO::PARAM_LOB);
        $insert->bindValue(':pairing_id', (int) $pairing['id'], PDO::PARAM_INT);
        $insert->bindValue(':person_id', $personId, PDO::PARAM_INT);
        $insert->bindValue(':account_id', (int) $pairing['account_id'], PDO::PARAM_INT);
        $insert->bindValue(':authorization_type', $authorizationType);
        $insert->bindValue(':authorized_by_account_id', $authorizedByAccountId, PDO::PARAM_INT);
        $insert->bindValue(':guardianship_id', $guardianshipId, $guardianshipId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $insert->bindValue(':review_reference', $reviewReference, $reviewReference === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $insert->bindValue(':review_justification', $reviewJustification, $reviewJustification === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $insert->bindValue(':requested_at', self::time($now));
        $insert->bindValue(':created_at', self::time($now));
        $insert->bindValue(':updated_at', self::time($now));
        $insert->bindValue(':expires_at', self::time($now->modify('+' . $configuration->claimTtlSeconds . ' seconds')));
        $insert->execute();
        $claim = ['id' => (int) $connection->lastInsertId(), 'public_id' => $publicId, 'person_id' => $personId, 'claimant_account_id' => (int) $pairing['account_id'], 'authorization_type' => $authorizationType, 'authorized_by_account_id' => $authorizedByAccountId, 'authorization_guardianship_id' => $guardianshipId ?? 0, 'status' => 'PENDING_ACCEPTANCE', 'version' => 1];
        $this->appendClaimEvent((int) $claim['id'], 'AUTHORIZED', 'ACCOUNT', $authorizedByAccountId, 'PROFILE_CLAIM_AUTHORIZED', null, $now);

        return $claim;
    }

    /** @return array<string, int|string>|null */
    public function claimForAccount(string $claimPublicId, int $accountId, bool $forUpdate = false): ?array
    {
        return $this->row($this->select('SELECT id,BIN_TO_UUID(public_id) AS public_id,pairing_id,person_id,claimant_account_id,authorization_type,authorized_by_account_id,authorization_guardianship_id,status,review_reference,review_justification,expires_at,version FROM people_profile_claims WHERE public_id = :public_id AND claimant_account_id = :account_id LIMIT 1', ['public_id' => UuidV7::fromString($claimPublicId)->toBinary(), 'account_id' => $accountId], $forUpdate, ['public_id']));
    }

    /** @return array<string, int|string>|null */
    public function claimByPublicId(string $claimPublicId, bool $forUpdate = false): ?array
    {
        return $this->row($this->select('SELECT id,BIN_TO_UUID(public_id) AS public_id,pairing_id,person_id,claimant_account_id,authorization_type,authorized_by_account_id,authorization_guardianship_id,status,review_reference,review_justification,expires_at,version FROM people_profile_claims WHERE public_id = :public_id LIMIT 1', ['public_id' => UuidV7::fromString($claimPublicId)->toBinary()], $forUpdate, ['public_id']));
    }

    /** @return array<string, int|string>|null */
    public function platformClaimByReview(int $personId, int $authorizerAccountId, string $reference, string $justification, bool $forUpdate = false): ?array
    {
        return $this->row($this->select(
            "SELECT id,BIN_TO_UUID(public_id) AS public_id,pairing_id,person_id,claimant_account_id,authorization_type,authorized_by_account_id,authorization_guardianship_id,status,review_reference,review_justification,expires_at,version FROM people_profile_claims WHERE person_id = :person_id AND authorized_by_account_id = :authorizer_account_id AND authorization_type = 'PLATFORM_RECORD_REVIEW' AND review_reference = :reference AND review_justification = :justification ORDER BY id DESC LIMIT 1",
            ['person_id' => $personId, 'authorizer_account_id' => $authorizerAccountId, 'reference' => $reference, 'justification' => $justification],
            $forUpdate,
        ));
    }

    /**
     * @param array<string, int|string> $claim
     * @return array<string, int|string>
     */
    public function transitionClaim(array $claim, string $status, int $actorAccountId, string $reasonCode, DateTimeImmutable $now): array
    {
        if ($claim['status'] !== 'PENDING_ACCEPTANCE' || !in_array($status, ['ACCEPTED', 'DECLINED', 'REVOKED', 'EXPIRED'], true)) {
            throw new \DomainException('Profile claim is unavailable.');
        }
        $timestampColumn = match ($status) {
            'ACCEPTED' => 'accepted_at', 'DECLINED' => 'declined_at', 'REVOKED' => 'revoked_at', 'EXPIRED' => 'expired_at',
        };
        $statement = $this->provider->connection()->prepare("UPDATE people_profile_claims SET status = :status, {$timestampColumn} = :transitioned_at, version = version + 1, updated_at = :updated_at WHERE id = :id AND status = 'PENDING_ACCEPTANCE' AND version = :version");
        $statement->execute(['status' => $status, 'transitioned_at' => self::time($now), 'updated_at' => self::time($now), 'id' => (int) $claim['id'], 'version' => (int) $claim['version']]);
        if ($statement->rowCount() !== 1) {
            throw new \DomainException('Profile claim is unavailable.');
        }
        $this->appendClaimEvent((int) $claim['id'], $status, $status === 'EXPIRED' ? 'SYSTEM' : 'ACCOUNT', $status === 'EXPIRED' ? null : $actorAccountId, $reasonCode, null, $now);
        $claim['status'] = $status;
        $claim['version'] = (int) $claim['version'] + 1;

        return $claim;
    }

    public function createSelfLink(int $accountId, int $personId, DateTimeImmutable $now): void
    {
        $statement = $this->provider->connection()->prepare("INSERT INTO people_account_links (public_id,account_id,person_id,link_type,status,version,linked_at,revoked_at,created_at,updated_at) VALUES (:public_id,:account_id,:person_id,'SELF','ACTIVE',1,:linked_at,NULL,:created_at,:updated_at)");
        $statement->bindValue(':public_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $statement->bindValue(':person_id', $personId, PDO::PARAM_INT);
        $statement->bindValue(':linked_at', self::time($now));
        $statement->bindValue(':created_at', self::time($now));
        $statement->bindValue(':updated_at', self::time($now));
        $statement->execute();
    }

    public function revokeActiveGuardianshipsForDependent(int $personId, int $actorAccountId, DateTimeImmutable $now): int
    {
        $statement = $this->provider->connection()->prepare("UPDATE people_guardianships SET status = 'REVOKED', revoked_by_account_id = :actor, revoked_at = :revoked_at, version = version + 1, updated_at = :updated_at WHERE dependent_person_id = :person_id AND authority_scope = 'PROFILE_MANAGEMENT' AND status = 'ACTIVE'");
        $statement->execute(['actor' => $actorAccountId, 'revoked_at' => self::time($now), 'updated_at' => self::time($now), 'person_id' => $personId]);

        return $statement->rowCount();
    }

    /** @param array<string, int|string> $claim */
    public function createClaimAssertions(array $claim, int $actorAccountId, DateTimeImmutable $now): void
    {
        $this->createAssertion((int) $claim['person_id'], 'ACCOUNT_CLAIMED', 'ACCOUNT', $actorAccountId, (int) $claim['id'], null, null, null, $now);
        if ($claim['authorization_type'] === 'GUARDIAN') {
            $this->createAssertion((int) $claim['person_id'], 'GUARDIAN_CONFIRMED', 'GUARDIAN', (int) $claim['authorized_by_account_id'], (int) $claim['id'], (int) $claim['authorization_guardianship_id'], null, null, $now);
        } else {
            $this->createAssertion((int) $claim['person_id'], 'QMDB_RECORD_REVIEWED', 'PLATFORM_REVIEWER', (int) $claim['authorized_by_account_id'], (int) $claim['id'], null, (string) $claim['review_reference'], (string) $claim['review_justification'], $now);
        }
    }

    public function createAssertion(int $personId, string $type, string $authorityType, int $actorAccountId, ?int $claimId, ?int $guardianshipId, ?string $reference, ?string $justification, DateTimeImmutable $now): void
    {
        $connection = $this->provider->connection();
        $revoke = $connection->prepare("UPDATE people_profile_verification_assertions SET status = 'REVOKED', revoked_at = :revoked_at, version = version + 1, updated_at = :updated_at WHERE person_id = :person_id AND assertion_type = :type AND status = 'ACTIVE'");
        $revoke->execute(['revoked_at' => self::time($now), 'updated_at' => self::time($now), 'person_id' => $personId, 'type' => $type]);
        $insert = $connection->prepare("INSERT INTO people_profile_verification_assertions (public_id,person_id,assertion_type,authority_type,actor_account_id,claim_id,guardianship_id,reference_code,review_justification,status,version,recorded_at,revoked_at,created_at,updated_at) VALUES (:public_id,:person_id,:type,:authority,:actor,:claim_id,:guardianship_id,:reference,:justification,'ACTIVE',1,:recorded_at,NULL,:created_at,:updated_at)");
        $insert->bindValue(':public_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        $insert->bindValue(':person_id', $personId, PDO::PARAM_INT);
        $insert->bindValue(':type', $type);
        $insert->bindValue(':authority', $authorityType);
        $insert->bindValue(':actor', $actorAccountId, PDO::PARAM_INT);
        $insert->bindValue(':claim_id', $claimId, $claimId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $insert->bindValue(':guardianship_id', $guardianshipId, $guardianshipId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $insert->bindValue(':reference', $reference, $reference === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $insert->bindValue(':justification', $justification, $justification === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $insert->bindValue(':recorded_at', self::time($now));
        $insert->bindValue(':created_at', self::time($now));
        $insert->bindValue(':updated_at', self::time($now));
        $insert->execute();
    }

    public function recordReviewerAssertion(int $personId, int $actorAccountId, string $reference, string $justification, DateTimeImmutable $now): string
    {
        $publicId = UuidV7::generate()->toString();
        $connection = $this->provider->connection();
        $connection->prepare("UPDATE people_profile_verification_assertions SET status = 'REVOKED', revoked_at = :revoked_at, version = version + 1, updated_at = :updated_at WHERE person_id = :person_id AND assertion_type = 'QMDB_RECORD_REVIEWED' AND status = 'ACTIVE'")->execute(['revoked_at' => self::time($now), 'updated_at' => self::time($now), 'person_id' => $personId]);
        $insert = $connection->prepare("INSERT INTO people_profile_verification_assertions (public_id,person_id,assertion_type,authority_type,actor_account_id,claim_id,guardianship_id,reference_code,review_justification,status,version,recorded_at,revoked_at,created_at,updated_at) VALUES (:public_id,:person_id,'QMDB_RECORD_REVIEWED','PLATFORM_REVIEWER',:actor,NULL,NULL,:reference,:justification,'ACTIVE',1,:recorded_at,NULL,:created_at,:updated_at)");
        $insert->bindValue(':public_id', UuidV7::fromString($publicId)->toBinary(), PDO::PARAM_LOB);
        $insert->bindValue(':person_id', $personId, PDO::PARAM_INT);
        $insert->bindValue(':actor', $actorAccountId, PDO::PARAM_INT);
        $insert->bindValue(':reference', $reference);
        $insert->bindValue(':justification', $justification);
        $insert->bindValue(':recorded_at', self::time($now));
        $insert->bindValue(':created_at', self::time($now));
        $insert->bindValue(':updated_at', self::time($now));
        $insert->execute();

        return $publicId;
    }

    /** @return array<string, int|string>|null */
    public function reviewerAssertionByEvidence(int $personId, int $actorAccountId, string $reference, string $justification, bool $forUpdate = false): ?array
    {
        return $this->row($this->select(
            "SELECT id,BIN_TO_UUID(public_id) AS public_id,person_id,assertion_type,authority_type,actor_account_id,status,version FROM people_profile_verification_assertions WHERE person_id = :person_id AND actor_account_id = :actor_account_id AND assertion_type = 'QMDB_RECORD_REVIEWED' AND authority_type = 'PLATFORM_REVIEWER' AND reference_code = :reference AND review_justification = :justification ORDER BY id DESC LIMIT 1",
            ['person_id' => $personId, 'actor_account_id' => $actorAccountId, 'reference' => $reference, 'justification' => $justification],
            $forUpdate,
        ));
    }

    /** @return array<string, int|string>|null */
    public function reviewerAssertion(string $assertionPublicId, int $personId, bool $forUpdate = false): ?array
    {
        return $this->row($this->select("SELECT id,BIN_TO_UUID(public_id) AS public_id,person_id,assertion_type,authority_type,actor_account_id,status,version FROM people_profile_verification_assertions WHERE public_id = :public_id AND person_id = :person_id AND assertion_type = 'QMDB_RECORD_REVIEWED' LIMIT 1", ['public_id' => UuidV7::fromString($assertionPublicId)->toBinary(), 'person_id' => $personId], $forUpdate, ['public_id']));
    }

    /** @param array<string, int|string> $assertion */
    public function revokeReviewerAssertion(array $assertion, DateTimeImmutable $now): void
    {
        $statement = $this->provider->connection()->prepare("UPDATE people_profile_verification_assertions SET status = 'REVOKED', revoked_at = :revoked_at, version = version + 1, updated_at = :updated_at WHERE id = :id AND status = 'ACTIVE' AND version = :version");
        $statement->execute(['revoked_at' => self::time($now), 'updated_at' => self::time($now), 'id' => (int) $assertion['id'], 'version' => (int) $assertion['version']]);
        if ($statement->rowCount() !== 1) {
            throw new \DomainException('Profile record-status assertion is unavailable.');
        }
    }

    public function revokeOtherPendingClaims(int $personId, int $accountId, int $actorAccountId, DateTimeImmutable $now): int
    {
        $statement = $this->provider->connection()->prepare("SELECT id,BIN_TO_UUID(public_id) AS public_id,pairing_id,person_id,claimant_account_id,authorization_type,authorized_by_account_id,authorization_guardianship_id,status,expires_at,version FROM people_profile_claims WHERE (person_id = :person_id OR claimant_account_id = :account_id) AND status = 'PENDING_ACCEPTANCE' FOR UPDATE");
        $statement->execute(['person_id' => $personId, 'account_id' => $accountId]);
        $count = 0;
        foreach ($this->fetchAllRows($statement) as $row) {
            $claim = $this->row($row);
            if ($claim !== null) {
                $this->transitionClaim($claim, 'REVOKED', $actorAccountId, 'SELF_LINK_CREATED', $now);
                ++$count;
            }
        }

        return $count;
    }

    public function revokePendingClaimsForPerson(int $personId, int $actorAccountId, DateTimeImmutable $now): int
    {
        $statement = $this->provider->connection()->prepare("SELECT id,BIN_TO_UUID(public_id) AS public_id,pairing_id,person_id,claimant_account_id,authorization_type,authorized_by_account_id,authorization_guardianship_id,status,expires_at,version FROM people_profile_claims WHERE person_id = :person_id AND status = 'PENDING_ACCEPTANCE' FOR UPDATE");
        $statement->execute(['person_id' => $personId]);
        $count = 0;
        foreach ($this->fetchAllRows($statement) as $row) {
            $claim = $this->row($row);
            if ($claim !== null) {
                $this->transitionClaim($claim, 'REVOKED', $actorAccountId, 'PERSON_CANONICALIZED', $now);
                ++$count;
            }
        }

        return $count;
    }

    /** @return array<string, int|string> */
    public function createDuplicateCase(string $publicId, int $firstPersonId, int $secondPersonId, int $reporterAccountId, string $reporterAuthorityType, ?int $reporterGuardianshipId, string $status, ?string $outcome, ?string $conflictCode, DateTimeImmutable $now): array
    {
        if ($firstPersonId >= $secondPersonId || !in_array($status, ['REPORTED', 'CONSENT_REQUIRED', 'BLOCKED'], true)) {
            throw new \InvalidArgumentException('Duplicate case is invalid.');
        }
        $statement = $this->provider->connection()->prepare('INSERT INTO people_duplicate_cases (public_id,first_person_id,second_person_id,reported_by_account_id,reporter_authority_type,reporter_guardianship_id,status,resolution_outcome,conflict_code,canonical_person_id,duplicate_person_id,reviewed_by_account_id,review_reference,review_justification,version,reported_at,review_started_at,resolved_at,dismissed_at,blocked_at,created_at,updated_at) VALUES (:public_id,:first_person_id,:second_person_id,:reporter,:authority,:guardianship,:status,:outcome,:conflict,NULL,NULL,NULL,NULL,NULL,1,:reported_at,NULL,NULL,NULL,CASE WHEN :blocked_status = \'BLOCKED\' THEN :blocked_at ELSE NULL END,:created_at,:updated_at)');
        $statement->bindValue(':public_id', UuidV7::fromString($publicId)->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':first_person_id', $firstPersonId, PDO::PARAM_INT);
        $statement->bindValue(':second_person_id', $secondPersonId, PDO::PARAM_INT);
        $statement->bindValue(':reporter', $reporterAccountId, PDO::PARAM_INT);
        $statement->bindValue(':authority', $reporterAuthorityType);
        $statement->bindValue(':guardianship', $reporterGuardianshipId, $reporterGuardianshipId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':status', $status);
        $statement->bindValue(':blocked_status', $status);
        $statement->bindValue(':outcome', $outcome, $outcome === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':conflict', $conflictCode, $conflictCode === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':reported_at', self::time($now));
        $statement->bindValue(':blocked_at', self::time($now));
        $statement->bindValue(':created_at', self::time($now));
        $statement->bindValue(':updated_at', self::time($now));
        $statement->execute();
        $caseId = (int) $this->provider->connection()->lastInsertId();
        $this->appendDuplicateEvent($caseId, 'REPORTED', 'ACCOUNT', $reporterAccountId, 'PERSON_DUPLICATE_REPORTED', null, $now);

        return ['id' => $caseId, 'public_id' => $publicId, 'first_person_id' => $firstPersonId, 'second_person_id' => $secondPersonId, 'reported_by_account_id' => $reporterAccountId, 'status' => $status, 'version' => 1];
    }

    /** @return array<string, int|string>|null */
    public function duplicateCase(string $casePublicId, bool $forUpdate = false): ?array
    {
        return $this->row($this->select('SELECT id,BIN_TO_UUID(public_id) AS public_id,first_person_id,second_person_id,reported_by_account_id,reporter_authority_type,reporter_guardianship_id,status,resolution_outcome,conflict_code,canonical_person_id,duplicate_person_id,reviewed_by_account_id,version FROM people_duplicate_cases WHERE public_id = :public_id LIMIT 1', ['public_id' => UuidV7::fromString($casePublicId)->toBinary()], $forUpdate, ['public_id']));
    }

    /** @return array<string, int|string>|null */
    public function duplicateCaseById(int $caseId, bool $forUpdate = false): ?array
    {
        return $this->row($this->select('SELECT id,BIN_TO_UUID(public_id) AS public_id,first_person_id,second_person_id,reported_by_account_id,reporter_authority_type,reporter_guardianship_id,status,resolution_outcome,conflict_code,canonical_person_id,duplicate_person_id,reviewed_by_account_id,version FROM people_duplicate_cases WHERE id = :id LIMIT 1', ['id' => $caseId], $forUpdate));
    }

    /** @return array<string, int|string>|null */
    public function duplicateCaseForAccount(string $casePublicId, int $accountId, bool $forUpdate = false): ?array
    {
        return $this->row($this->select(
            'SELECT DISTINCT c.id,BIN_TO_UUID(c.public_id) AS public_id,c.first_person_id,c.second_person_id,c.reported_by_account_id,c.reporter_authority_type,c.reporter_guardianship_id,c.status,c.resolution_outcome,c.conflict_code,c.canonical_person_id,c.duplicate_person_id,c.reviewed_by_account_id,c.version '
            . 'FROM people_duplicate_cases c LEFT JOIN people_duplicate_consent_requirements r ON r.case_id = c.id AND r.invalidated_at IS NULL AND r.authority_account_id = :consent_account_id '
            . 'WHERE c.public_id = :public_id AND (c.reported_by_account_id = :reporter_account_id OR r.id IS NOT NULL) LIMIT 1',
            ['consent_account_id' => $accountId, 'public_id' => UuidV7::fromString($casePublicId)->toBinary(), 'reporter_account_id' => $accountId],
            $forUpdate,
            ['public_id'],
        ));
    }

    /** @return list<array<string, int|string>> */
    public function duplicateCasesForAccount(int $accountId, int $limit = 100): array
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT DISTINCT BIN_TO_UUID(c.public_id) AS public_id,c.status,c.resolution_outcome,c.conflict_code,'
            . 'c.version,c.first_person_id,c.second_person_id FROM people_duplicate_cases c '
            . 'LEFT JOIN people_duplicate_consent_requirements r ON r.case_id = c.id '
            . 'AND r.invalidated_at IS NULL AND r.authority_account_id = :consent_account_id '
            . 'WHERE c.reported_by_account_id = :reporter_account_id OR r.id IS NOT NULL '
            . 'ORDER BY c.reported_at DESC,c.id DESC LIMIT :limit',
        );
        $statement->bindValue(':consent_account_id', $accountId, PDO::PARAM_INT);
        $statement->bindValue(':reporter_account_id', $accountId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $this->rows($this->fetchAllRows($statement));
    }

    /** @return list<array<string, int|string>> */
    public function platformDuplicateCases(int $limit = 100): array
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT BIN_TO_UUID(public_id) AS public_id,status,resolution_outcome,conflict_code,version '
            . 'FROM people_duplicate_cases ORDER BY reported_at DESC,id DESC LIMIT :limit',
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $this->rows($this->fetchAllRows($statement));
    }

    /** @return list<array<string, int|string>> */
    public function consentRequirementsForCase(int $caseId, ?int $accountId = null): array
    {
        $sql = 'SELECT BIN_TO_UUID(public_id) AS public_id,person_id,authority_type,authority_account_id,decision,version '
            . 'FROM people_duplicate_consent_requirements WHERE case_id = :case_id AND invalidated_at IS NULL';
        if ($accountId !== null) {
            $sql .= ' AND authority_account_id = :account_id';
        }
        $sql .= ' ORDER BY id ASC';
        $statement = $this->provider->connection()->prepare($sql);
        $statement->bindValue(':case_id', $caseId, PDO::PARAM_INT);
        if ($accountId !== null) {
            $statement->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        }
        $statement->execute();

        return $this->rows($this->fetchAllRows($statement));
    }

    /** @return array<string, int|string>|null */
    public function openDuplicateCase(int $firstPersonId, int $secondPersonId, bool $forUpdate = false): ?array
    {
        return $this->row($this->select("SELECT id,BIN_TO_UUID(public_id) AS public_id,first_person_id,second_person_id,reported_by_account_id,reporter_authority_type,reporter_guardianship_id,status,resolution_outcome,conflict_code,canonical_person_id,duplicate_person_id,reviewed_by_account_id,version FROM people_duplicate_cases WHERE first_person_id = :first_person_id AND second_person_id = :second_person_id AND status IN ('REPORTED','CONSENT_REQUIRED','READY_FOR_REVIEW','UNDER_REVIEW') LIMIT 1", ['first_person_id' => $firstPersonId, 'second_person_id' => $secondPersonId], $forUpdate));
    }

    /** @return array<string, int|string>|null */
    public function duplicateCaseForReporterPair(int $firstPersonId, int $secondPersonId, int $reporterAccountId, bool $forUpdate = false): ?array
    {
        return $this->row($this->select(
            'SELECT id,BIN_TO_UUID(public_id) AS public_id,first_person_id,second_person_id,reported_by_account_id,reporter_authority_type,reporter_guardianship_id,status,resolution_outcome,conflict_code,canonical_person_id,duplicate_person_id,reviewed_by_account_id,version FROM people_duplicate_cases WHERE first_person_id = :first_person_id AND second_person_id = :second_person_id AND reported_by_account_id = :reporter_account_id ORDER BY id DESC LIMIT 1',
            ['first_person_id' => $firstPersonId, 'second_person_id' => $secondPersonId, 'reporter_account_id' => $reporterAccountId],
            $forUpdate,
        ));
    }

    /** @param array<string, int|string> $case
     * @param list<array{person_id:int,account_id:int,authority_type:string,guardianship_id:?int}> $authorities
     */
    public function createConsentRequirements(array $case, array $authorities, DateTimeImmutable $now): int
    {
        $statement = $this->provider->connection()->prepare('INSERT INTO people_duplicate_consent_requirements (public_id,case_id,person_id,authority_type,authority_account_id,guardianship_id,decision,version,created_at,responded_at,invalidated_at,updated_at) VALUES (:public_id,:case_id,:person_id,:authority_type,:account_id,:guardianship_id,\'PENDING\',1,:created_at,NULL,NULL,:updated_at)');
        $created = 0;
        foreach ($authorities as $authority) {
            $statement->bindValue(':public_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
            $statement->bindValue(':case_id', (int) $case['id'], PDO::PARAM_INT);
            $statement->bindValue(':person_id', $authority['person_id'], PDO::PARAM_INT);
            $statement->bindValue(':authority_type', $authority['authority_type']);
            $statement->bindValue(':account_id', $authority['account_id'], PDO::PARAM_INT);
            $statement->bindValue(':guardianship_id', $authority['guardianship_id'], $authority['guardianship_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $statement->bindValue(':created_at', self::time($now));
            $statement->bindValue(':updated_at', self::time($now));
            $statement->execute();
            ++$created;
        }
        if ($created > 0) {
            $this->appendDuplicateEvent((int) $case['id'], 'CONSENT_REQUIREMENTS_CREATED', 'SYSTEM', null, 'DUPLICATE_CONSENT_REQUIREMENTS_CREATED', null, $now);
        }

        return $created;
    }

    /**
     * @param array<string, int|string> $case
     * @return array<string, int|string>
     */
    public function transitionDuplicateCase(array $case, string $status, ?string $outcome, ?string $conflictCode, ?int $reviewerAccountId, ?int $canonicalPersonId, ?int $duplicatePersonId, ?string $reference, ?string $justification, DateTimeImmutable $now): array
    {
        $columns = ['status = :status', 'resolution_outcome = :outcome', 'conflict_code = :conflict', 'reviewed_by_account_id = COALESCE(:reviewer, reviewed_by_account_id)', 'canonical_person_id = :canonical_person_id', 'duplicate_person_id = :duplicate_person_id', 'review_reference = :reference', 'review_justification = :justification', 'version = version + 1', 'updated_at = :updated_at'];
        if ($status === 'DISMISSED') {
            $columns[] = 'dismissed_at = :dismissed_at';
        }
        if ($status === 'BLOCKED') {
            $columns[] = 'blocked_at = :blocked_at';
        }
        if ($status === 'RESOLVED') {
            $columns[] = 'resolved_at = :resolved_at';
        }
        if ($status === 'UNDER_REVIEW') {
            $columns[] = 'review_started_at = :review_started_at';
        }
        $statement = $this->provider->connection()->prepare('UPDATE people_duplicate_cases SET ' . implode(',', $columns) . ' WHERE id = :id AND version = :version');
        $statement->bindValue(':status', $status);
        $statement->bindValue(':outcome', $outcome, $outcome === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':conflict', $conflictCode, $conflictCode === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':reviewer', $reviewerAccountId, $reviewerAccountId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':canonical_person_id', $canonicalPersonId, $canonicalPersonId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':duplicate_person_id', $duplicatePersonId, $duplicatePersonId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':reference', $reference, $reference === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':justification', $justification, $justification === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':updated_at', self::time($now));
        foreach ([':dismissed_at', ':blocked_at', ':resolved_at', ':review_started_at'] as $parameter) {
            if (str_contains(implode(',', $columns), $parameter)) {
                $statement->bindValue($parameter, self::time($now));
            }
        }
        $statement->bindValue(':id', (int) $case['id'], PDO::PARAM_INT);
        $statement->bindValue(':version', (int) $case['version'], PDO::PARAM_INT);
        $statement->execute();
        if ($statement->rowCount() !== 1) {
            throw new \DomainException('Duplicate case is unavailable.');
        }
        $case['status'] = $status;
        $case['version'] = (int) $case['version'] + 1;

        return $case;
    }

    /** @return array<string, int|string>|null */
    public function activeConsentRequirement(string $requirementPublicId, int $accountId, bool $forUpdate = false): ?array
    {
        return $this->row($this->select('SELECT id,BIN_TO_UUID(public_id) AS public_id,case_id,person_id,authority_type,authority_account_id,guardianship_id,decision,version FROM people_duplicate_consent_requirements WHERE public_id = :public_id AND authority_account_id = :account_id AND invalidated_at IS NULL LIMIT 1', ['public_id' => UuidV7::fromString($requirementPublicId)->toBinary(), 'account_id' => $accountId], $forUpdate, ['public_id']));
    }

    /** @param array<string, int|string> $requirement */
    public function decideConsent(array $requirement, string $decision, DateTimeImmutable $now): void
    {
        if (!in_array($decision, ['APPROVED', 'DECLINED'], true) || $requirement['decision'] !== 'PENDING') {
            throw new \DomainException('Duplicate consent is unavailable.');
        }
        $statement = $this->provider->connection()->prepare('UPDATE people_duplicate_consent_requirements SET decision = :decision, responded_at = :responded_at, version = version + 1, updated_at = :updated_at WHERE id = :id AND decision = \'PENDING\' AND invalidated_at IS NULL AND version = :version');
        $statement->execute(['decision' => $decision, 'responded_at' => self::time($now), 'updated_at' => self::time($now), 'id' => (int) $requirement['id'], 'version' => (int) $requirement['version']]);
        if ($statement->rowCount() !== 1) {
            throw new \DomainException('Duplicate consent is unavailable.');
        }
    }

    /** @param array<string, int|string> $case
     * @return array{pending:int,approved:int,declined:int}
     */
    public function consentSummary(array $case): array
    {
        $statement = $this->provider->connection()->prepare("SELECT decision,COUNT(*) AS total FROM people_duplicate_consent_requirements WHERE case_id = :case_id AND invalidated_at IS NULL GROUP BY decision");
        $statement->execute(['case_id' => (int) $case['id']]);
        $summary = ['pending' => 0, 'approved' => 0, 'declined' => 0];
        foreach ($this->fetchAllRows($statement) as $row) {
            $total = $this->integerValue($row, 'total');
            switch (strtolower($this->stringValue($row, 'decision'))) {
                case 'pending':
                    $summary['pending'] = $total;
                    break;
                case 'approved':
                    $summary['approved'] = $total;
                    break;
                case 'declined':
                    $summary['declined'] = $total;
                    break;
                default:
                    throw new \UnexpectedValueException('Duplicate consent decision is invalid.');
            }
        }

        return $summary;
    }

    /** @param array<string, int|string> $case */
    public function invalidateConsentRequirements(array $case, DateTimeImmutable $now): int
    {
        $statement = $this->provider->connection()->prepare('UPDATE people_duplicate_consent_requirements SET invalidated_at = :invalidated_at, version = version + 1, updated_at = :updated_at WHERE case_id = :case_id AND invalidated_at IS NULL');
        $statement->execute(['invalidated_at' => self::time($now), 'updated_at' => self::time($now), 'case_id' => (int) $case['id']]);
        if ($statement->rowCount() > 0) {
            $this->appendDuplicateEvent((int) $case['id'], 'CONSENT_REQUIREMENTS_INVALIDATED', 'SYSTEM', null, 'DUPLICATE_CONSENT_AUTHORITY_CHANGED', null, $now);
        }

        return $statement->rowCount();
    }

    /** @param array<string, int|string> $case
     * @param list<array{person_id:int,account_id:int,authority_type:string,guardianship_id:?int}> $authorities
     */
    public function authoritySnapshotMatches(array $case, array $authorities): bool
    {
        $statement = $this->provider->connection()->prepare('SELECT person_id,authority_account_id,authority_type,guardianship_id FROM people_duplicate_consent_requirements WHERE case_id = :case_id AND invalidated_at IS NULL ORDER BY person_id,authority_account_id,authority_type,COALESCE(guardianship_id,0)');
        $statement->execute(['case_id' => (int) $case['id']]);
        $stored = [];
        foreach ($this->fetchAllRows($statement) as $row) {
            $stored[] = $this->integerValue($row, 'person_id') . ':' . $this->integerValue($row, 'authority_account_id') . ':' . $this->stringValue($row, 'authority_type') . ':' . ($this->nullableIntegerValue($row, 'guardianship_id') ?? 0);
        }
        $current = [];
        foreach ($authorities as $authority) {
            $current[] = $authority['person_id'] . ':' . $authority['account_id'] . ':' . $authority['authority_type'] . ':' . (int) ($authority['guardianship_id'] ?? 0);
        }
        sort($current);

        return $stored === $current;
    }

    public function recordDuplicateEvent(int $caseId, string $eventType, ?int $actorAccountId, string $reasonCode, DateTimeImmutable $now): void
    {
        $this->appendDuplicateEvent($caseId, $eventType, $actorAccountId === null ? 'SYSTEM' : 'ACCOUNT', $actorAccountId, $reasonCode, null, $now);
    }

    public function aliasForSource(int $sourcePersonId): ?int
    {
        $statement = $this->provider->connection()->prepare('SELECT canonical_person_id FROM people_person_aliases WHERE source_person_id = :source_person_id LIMIT 1');
        $statement->execute(['source_person_id' => $sourcePersonId]);
        $value = $statement->fetchColumn();
        if ($value === false) {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new \UnexpectedValueException('Identity-resolution persistence scalar is invalid.');
    }

    public function inboundAliasCount(int $canonicalPersonId): int
    {
        return $this->count($this->provider->connection(), 'SELECT COUNT(*) FROM people_person_aliases WHERE canonical_person_id = ' . (int) $canonicalPersonId);
    }

    public function geographyConflict(int $firstPersonId, int $secondPersonId): bool
    {
        $statement = $this->provider->connection()->prepare("SELECT 1 FROM people_person_geographies first_geo INNER JOIN people_person_geographies second_geo ON second_geo.association_type = first_geo.association_type AND second_geo.status = 'ACTIVE' AND second_geo.person_id = :second_person_id WHERE first_geo.person_id = :first_person_id AND first_geo.status = 'ACTIVE' AND (first_geo.country_id <> second_geo.country_id OR first_geo.level_one_area_id <> second_geo.level_one_area_id OR COALESCE(first_geo.level_two_area_id,0) <> COALESCE(second_geo.level_two_area_id,0)) LIMIT 1");
        $statement->execute(['first_person_id' => $firstPersonId, 'second_person_id' => $secondPersonId]);

        return $statement->fetchColumn() !== false;
    }

    /** @param array<string, int|string> $case */
    public function retirePersonAndAlias(int $sourcePersonId, int $canonicalPersonId, array $case, int $actorAccountId, DateTimeImmutable $now): string
    {
        $retire = $this->provider->connection()->prepare("UPDATE people_persons SET status = 'RETIRED', retired_at = :retired_at, version = version + 1, updated_at = :updated_at WHERE id = :source_person_id AND status = 'ACTIVE'");
        $retire->execute(['retired_at' => self::time($now), 'updated_at' => self::time($now), 'source_person_id' => $sourcePersonId]);
        if ($retire->rowCount() !== 1) {
            throw new \DomainException('Duplicate source Person is unavailable.');
        }
        $publicId = UuidV7::generate()->toString();
        $insert = $this->provider->connection()->prepare('INSERT INTO people_person_aliases (public_id,source_person_id,canonical_person_id,duplicate_case_id,created_by_account_id,created_at) VALUES (:public_id,:source_person_id,:canonical_person_id,:case_id,:actor_account_id,:now)');
        $insert->bindValue(':public_id', UuidV7::fromString($publicId)->toBinary(), PDO::PARAM_LOB);
        $insert->bindValue(':source_person_id', $sourcePersonId, PDO::PARAM_INT);
        $insert->bindValue(':canonical_person_id', $canonicalPersonId, PDO::PARAM_INT);
        $insert->bindValue(':case_id', (int) $case['id'], PDO::PARAM_INT);
        $insert->bindValue(':actor_account_id', $actorAccountId, PDO::PARAM_INT);
        $insert->bindValue(':now', self::time($now));
        $insert->execute();

        return $publicId;
    }

    public function consolidateVerificationAssertions(int $sourcePersonId, int $canonicalPersonId, DateTimeImmutable $now): int
    {
        $statement = $this->provider->connection()->prepare("SELECT assertion_type,authority_type,actor_account_id,claim_id,guardianship_id,reference_code,review_justification FROM people_profile_verification_assertions WHERE person_id = :source_person_id AND status = 'ACTIVE' FOR UPDATE");
        $statement->execute(['source_person_id' => $sourcePersonId]);
        $count = 0;
        foreach ($this->fetchAllRows($statement) as $assertion) {
            $assertionType = $this->stringValue($assertion, 'assertion_type');
            $target = $this->provider->connection()->prepare("SELECT id FROM people_profile_verification_assertions WHERE person_id = :canonical_person_id AND assertion_type = :assertion_type AND status = 'ACTIVE' LIMIT 1 FOR UPDATE");
            $target->execute(['canonical_person_id' => $canonicalPersonId, 'assertion_type' => $assertionType]);
            if ($target->fetchColumn() === false) {
                $this->createAssertion($canonicalPersonId, $assertionType, $this->stringValue($assertion, 'authority_type'), $this->integerValue($assertion, 'actor_account_id'), $this->nullableIntegerValue($assertion, 'claim_id'), $this->nullableIntegerValue($assertion, 'guardianship_id'), $this->nullableStringValue($assertion, 'reference_code'), $this->nullableStringValue($assertion, 'review_justification'), $now);
            }
            $revoke = $this->provider->connection()->prepare("UPDATE people_profile_verification_assertions SET status = 'REVOKED', revoked_at = :revoked_at, version = version + 1, updated_at = :updated_at WHERE person_id = :source_person_id AND assertion_type = :assertion_type AND status = 'ACTIVE'");
            $revoke->execute(['revoked_at' => self::time($now), 'updated_at' => self::time($now), 'source_person_id' => $sourcePersonId, 'assertion_type' => $assertionType]);
            ++$count;
        }

        return $count;
    }

    private function appendDuplicateEvent(int $caseId, string $eventType, string $actorType, ?int $actorAccountId, string $reasonCode, ?string $correlationId, DateTimeImmutable $now): void
    {
        $statement = $this->provider->connection()->prepare('INSERT INTO people_duplicate_case_events (public_id,case_id,event_type,actor_type,actor_account_id,reason_code,correlation_id,occurred_at,created_at) VALUES (:public_id,:case_id,:event_type,:actor_type,:actor_account_id,:reason_code,:correlation_id,:occurred_at,:created_at)');
        $statement->bindValue(':public_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':case_id', $caseId, PDO::PARAM_INT);
        $statement->bindValue(':event_type', $eventType);
        $statement->bindValue(':actor_type', $actorType);
        $statement->bindValue(':actor_account_id', $actorAccountId, $actorAccountId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':reason_code', $reasonCode);
        $statement->bindValue(':correlation_id', $correlationId, $correlationId === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':occurred_at', self::time($now));
        $statement->bindValue(':created_at', self::time($now));
        $statement->execute();
    }

    private function appendClaimEvent(int $claimId, string $eventType, string $actorType, ?int $actorAccountId, string $reasonCode, ?string $correlationId, DateTimeImmutable $now): void
    {
        $statement = $this->provider->connection()->prepare('INSERT INTO people_profile_claim_events (public_id,claim_id,event_type,actor_type,actor_account_id,reason_code,correlation_id,occurred_at,created_at) VALUES (:public_id,:claim_id,:event_type,:actor_type,:actor_account_id,:reason_code,:correlation_id,:occurred_at,:created_at)');
        $statement->bindValue(':public_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':claim_id', $claimId, PDO::PARAM_INT);
        $statement->bindValue(':event_type', $eventType);
        $statement->bindValue(':actor_type', $actorType);
        $statement->bindValue(':actor_account_id', $actorAccountId, $actorAccountId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':reason_code', $reasonCode);
        $statement->bindValue(':correlation_id', $correlationId, $correlationId === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':occurred_at', self::time($now));
        $statement->bindValue(':created_at', self::time($now));
        $statement->execute();
    }

    private function invalidRows(PDO $connection): int
    {
        return $this->count($connection, "SELECT COUNT(*) FROM (SELECT account_id FROM people_profile_claim_pairings WHERE status = 'ACTIVE' GROUP BY account_id HAVING COUNT(*) > 1) pairings")
            + $this->count($connection, "SELECT COUNT(*) FROM (SELECT person_id FROM people_profile_claims WHERE status = 'PENDING_ACCEPTANCE' GROUP BY person_id HAVING COUNT(*) > 1) claims")
            + $this->count($connection, "SELECT COUNT(*) FROM (SELECT claimant_account_id FROM people_profile_claims WHERE status = 'PENDING_ACCEPTANCE' GROUP BY claimant_account_id HAVING COUNT(*) > 1) claimants")
            + $this->count($connection, "SELECT COUNT(*) FROM people_profile_claims c INNER JOIN people_account_links l ON l.account_id = c.claimant_account_id AND l.person_id = c.person_id AND l.link_type = 'SELF' AND l.status = 'ACTIVE' WHERE c.status = 'ACCEPTED'")
            + $this->count($connection, "SELECT COUNT(*) FROM people_person_aliases a INNER JOIN people_persons p ON p.id = a.source_person_id WHERE p.status <> 'RETIRED'")
            + $this->count($connection, 'SELECT COUNT(*) FROM people_person_aliases WHERE source_person_id = canonical_person_id')
            + $this->count($connection, 'SELECT COUNT(*) FROM people_person_aliases a INNER JOIN people_person_aliases b ON b.source_person_id = a.canonical_person_id');
    }

    private function count(PDO $connection, string $sql): int
    {
        $statement = $connection->query($sql);
        if ($statement === false) {
            throw new \RuntimeException('Identity-resolution report query failed.');
        }
        $value = $statement->fetchColumn();
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new \UnexpectedValueException('Identity-resolution report count is invalid.');
    }

    /** @param list<array<string, mixed>> $rows
     * @return list<array<string, int|string>>
     */
    private function rows(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $value = $this->row($row);
            if ($value !== null) {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }

    /** @param array<string, int|string> $parameters
     * @param list<string> $binaryParameters
     * @return array<string, mixed>|false
     */
    private function select(string $sql, array $parameters, bool $forUpdate, array $binaryParameters = []): array|false
    {
        $statement = $this->provider->connection()->prepare($sql . ($forUpdate ? ' FOR UPDATE' : ''));
        foreach ($parameters as $name => $value) {
            $type = in_array($name, $binaryParameters, true) ? PDO::PARAM_LOB : (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            $statement->bindValue(':' . $name, $value, $type);
        }
        $statement->execute();
        $fetched = $statement->fetch(PDO::FETCH_ASSOC);
        if ($fetched === false) {
            return false;
        }
        if (!is_array($fetched)) {
            throw new \UnexpectedValueException('Identity-resolution persistence row is invalid.');
        }
        $row = [];
        foreach ($fetched as $key => $value) {
            $row[(string) $key] = $value;
        }

        return $row;
    }

    /** @param array<string, mixed>|false $row
     * @return array<string, int|string>|null
     */
    private function row(array|false $row): ?array
    {
        if ($row === false) {
            return null;
        }
        $normalized = [];
        foreach ($row as $key => $value) {
            if (is_int($value)) {
                $normalized[$key] = $value;
            } elseif (is_string($value)) {
                $normalized[$key] = preg_match('/\\A(?:0|[1-9][0-9]*)\\z/', $value) === 1 && !in_array($key, ['public_id', 'registry_code', 'code_selector', 'status', 'birth_date'], true) ? (int) $value : $value;
            } elseif ($value === null) {
                $normalized[$key] = '';
            } else {
                throw new \UnexpectedValueException('Identity-resolution persistence row is invalid.');
            }
        }

        return $normalized;
    }

    /** @return list<array<string, mixed>> */
    private function fetchAllRows(\PDOStatement $statement): array
    {
        $fetched = $statement->fetchAll(PDO::FETCH_ASSOC);
        $rows = [];
        foreach ($fetched as $item) {
            if (!is_array($item)) {
                throw new \UnexpectedValueException('Identity-resolution persistence row is invalid.');
            }
            $row = [];
            foreach ($item as $key => $value) {
                $row[(string) $key] = $value;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** @param array<string, mixed> $row */
    private function stringValue(array $row, string $field): string
    {
        $value = $row[$field] ?? null;
        if (!is_string($value)) {
            throw new \UnexpectedValueException('Identity-resolution persistence field is invalid.');
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
            throw new \UnexpectedValueException('Identity-resolution persistence field is invalid.');
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

        throw new \UnexpectedValueException('Identity-resolution persistence integer field is invalid.');
    }

    /** @param array<string, mixed> $row */
    private function nullableIntegerValue(array $row, string $field): ?int
    {
        $value = $row[$field] ?? null;
        if ($value === null) {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new \UnexpectedValueException('Identity-resolution persistence integer field is invalid.');
    }

    private static function time(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
