<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Qmdb\Tests\Support\MySql\AuthorizationMySqlFixture;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

#[Group('IdentityResolution')]
#[Group('Constraints')]
final class P3IdentityResolutionConstraintIntegrationTest extends MySqlIntegrationTestCase
{
    private PDO $connection;
    private AuthorizationMySqlFixture $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->provider()->connection();
        $this->fixture = new AuthorizationMySqlFixture($this->connection, dirname(__DIR__, 3));
        $this->fixture->rebuild();
    }

    protected function tearDown(): void
    {
        $this->fixture->dropBusinessTables();
        parent::tearDown();
    }

    public function testPairingGeneratedMarkersAndChecksRejectUnsafeState(): void
    {
        [$account] = $this->fixture->account();
        $pairing = $this->insertPairing($account, 'AAAABBBBCCCC');

        $this->assertRejected(fn (): bool => $this->insertPairing($account, 'AAAABBBBCCCD'));
        $this->assertRejected(fn (): bool => $this->connection->prepare('UPDATE people_profile_claim_pairings SET hmac_key_version = 0 WHERE id = :id')->execute(['id' => $pairing]));
        $this->assertRejected(fn (): bool => $this->connection->prepare('UPDATE people_profile_claim_pairings SET attempt_count = max_attempts + 1 WHERE id = :id')->execute(['id' => $pairing]));
        $this->assertRejected(fn (): bool => $this->connection->prepare("UPDATE people_profile_claim_pairings SET status = 'CONSUMED' WHERE id = :id")->execute(['id' => $pairing]));

        $explain = $this->connection->prepare('EXPLAIN FORMAT=JSON SELECT id FROM people_profile_claim_pairings WHERE code_selector = :selector');
        $explain->execute(['selector' => 'AAAABBBBCCCC']);
        $plan = (string) $explain->fetchColumn();
        self::assertStringContainsString('code_selector', $plan);
        self::assertStringContainsString('uq_people_profile_claim_pairings_selector', $plan);
    }

    public function testVerificationAssertionsRetainRevokedHistoryButRejectDuplicateActiveFacts(): void
    {
        [$actor] = $this->fixture->account();
        $person = $this->insertPerson($actor, 'QMP-B05-CONSTRAINT-001');
        $first = $this->insertAssertion($person, $actor, 'ACCOUNT_CLAIMED', 'ACCOUNT', 'ACTIVE');

        $this->assertRejected(fn (): bool => $this->insertAssertion($person, $actor, 'ACCOUNT_CLAIMED', 'ACCOUNT', 'ACTIVE'));
        self::assertTrue($this->connection->prepare("UPDATE people_profile_verification_assertions SET status = 'REVOKED', revoked_at = UTC_TIMESTAMP(6), version = version + 1 WHERE id = :id")->execute(['id' => $first]));
        $second = $this->insertAssertion($person, $actor, 'ACCOUNT_CLAIMED', 'ACCOUNT', 'ACTIVE');
        self::assertGreaterThan($first, $second);
        self::assertSame(2, $this->countRows('people_profile_verification_assertions'));
        $this->assertRejected(fn (): bool => $this->connection->prepare("UPDATE people_profile_verification_assertions SET status = 'INVALID' WHERE id = :id")->execute(['id' => $second]));
    }

    public function testDuplicateConsentAndAliasConstraintsProtectCaseAndCanonicalHistory(): void
    {
        [$reporter] = $this->fixture->account();
        [$authority] = $this->fixture->account();
        $first = $this->insertPerson($reporter, 'QMP-B05-CONSTRAINT-002');
        $second = $this->insertPerson($reporter, 'QMP-B05-CONSTRAINT-003');
        $case = $this->insertOpenCase($first, $second, $reporter);

        $this->assertRejected(fn (): bool => $this->insertOpenCase($second, $first, $reporter));
        $consent = $this->insertConsent($case, $first, $authority, 'SELF', null);
        self::assertGreaterThan(0, $consent);
        $this->assertRejected(fn (): bool => $this->insertConsent($case, $first, $authority, 'SELF', null));
        $this->assertRejected(fn (): bool => $this->insertConsent($case, $first, $authority, 'GUARDIAN', null));

        $alias = $this->insertAlias($second, $first, $case, $reporter);
        self::assertGreaterThan(0, $alias);
        $this->assertRejected(fn (): bool => $this->insertAlias($second, $first, $case, $reporter));
        $this->assertRejected(fn (): bool => $this->insertAlias($first, $first, $case, $reporter));

        $explain = $this->connection->prepare('EXPLAIN FORMAT=JSON SELECT id FROM people_duplicate_consent_requirements WHERE authority_account_id = :account_id AND decision = :decision');
        $explain->execute(['account_id' => $authority, 'decision' => 'PENDING']);
        $plan = (string) $explain->fetchColumn();
        self::assertStringContainsString('authority_account_id', $plan);
        self::assertStringContainsString('ix_people_duplicate_consent_requirements_authority_decision', $plan);
    }

    private function insertPairing(int $accountId, string $selector): int
    {
        $statement = $this->connection->prepare("INSERT INTO people_profile_claim_pairings (public_id,account_id,code_selector,secret_hash,hmac_key_version,status,attempt_count,max_attempts,expires_at,version,created_at,updated_at) VALUES (UUID_TO_BIN(UUID()),:account_id,:selector,UNHEX(SHA2(:secret_selector,256)),1,'ACTIVE',0,5,DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 15 MINUTE),1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        $statement->execute(['account_id' => $accountId, 'selector' => $selector, 'secret_selector' => $selector]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertPerson(int $accountId, string $registryCode): int
    {
        $statement = $this->connection->prepare("INSERT INTO people_persons (public_id,registry_code,status,sex_classification,created_by_account_id,version,created_at,updated_at) VALUES (UUID_TO_BIN(UUID()),:registry_code,'ACTIVE','NOT_RECORDED',:account_id,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        $statement->execute(['registry_code' => $registryCode, 'account_id' => $accountId]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertAssertion(int $personId, int $actorId, string $type, string $authority, string $status): int
    {
        $statement = $this->connection->prepare("INSERT INTO people_profile_verification_assertions (public_id,person_id,assertion_type,authority_type,actor_account_id,status,version,recorded_at,created_at,updated_at) VALUES (UUID_TO_BIN(UUID()),:person_id,:type,:authority,:actor_id,:status,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        $statement->execute(['person_id' => $personId, 'type' => $type, 'authority' => $authority, 'actor_id' => $actorId, 'status' => $status]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertOpenCase(int $firstPersonId, int $secondPersonId, int $reporterId): int
    {
        $ordered = [$firstPersonId, $secondPersonId];
        sort($ordered, SORT_NUMERIC);
        $statement = $this->connection->prepare("INSERT INTO people_duplicate_cases (public_id,first_person_id,second_person_id,reported_by_account_id,reporter_authority_type,status,version,reported_at,created_at,updated_at) VALUES (UUID_TO_BIN(UUID()),:first,:second,:reporter,'PLATFORM_REVIEWER','CONSENT_REQUIRED',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        $statement->execute(['first' => $ordered[0], 'second' => $ordered[1], 'reporter' => $reporterId]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertConsent(int $caseId, int $personId, int $authorityAccountId, string $authorityType, ?int $guardianshipId): int
    {
        $statement = $this->connection->prepare("INSERT INTO people_duplicate_consent_requirements (public_id,case_id,person_id,authority_type,authority_account_id,guardianship_id,decision,version,created_at,updated_at) VALUES (UUID_TO_BIN(UUID()),:case_id,:person_id,:authority_type,:authority_account_id,:guardianship_id,'PENDING',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        $statement->execute(['case_id' => $caseId, 'person_id' => $personId, 'authority_type' => $authorityType, 'authority_account_id' => $authorityAccountId, 'guardianship_id' => $guardianshipId]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertAlias(int $sourcePersonId, int $canonicalPersonId, int $caseId, int $actorId): int
    {
        $statement = $this->connection->prepare('INSERT INTO people_person_aliases (public_id,source_person_id,canonical_person_id,duplicate_case_id,created_by_account_id,created_at) VALUES (UUID_TO_BIN(UUID()),:source,:canonical,:case_id,:actor,UTC_TIMESTAMP(6))');
        $statement->execute(['source' => $sourcePersonId, 'canonical' => $canonicalPersonId, 'case_id' => $caseId, 'actor' => $actorId]);

        return (int) $this->connection->lastInsertId();
    }

    private function countRows(string $table): int
    {
        $statement = $this->connection->query('SELECT COUNT(*) FROM ' . $table);
        self::assertNotFalse($statement);

        return (int) $statement->fetchColumn();
    }

    /** @param callable(): bool $operation */
    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
            self::fail('A guarded MySQL identity-resolution mutation unexpectedly succeeded.');
        } catch (PDOException) {
            self::addToAssertionCount(1);
        }
    }
}
