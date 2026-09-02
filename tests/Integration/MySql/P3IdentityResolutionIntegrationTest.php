<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Qmdb\Modules\IdentityResolution\Configuration\IdentityResolutionConfiguration;
use Qmdb\Modules\IdentityResolution\Domain\ProfileClaimPairingCode;
use Qmdb\Modules\IdentityResolution\Infrastructure\Persistence\MySqlIdentityResolutionRepository;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Tests\Support\MySql\AuthorizationMySqlFixture;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

#[Group('IdentityResolution')]
#[Group('Concurrency')]
final class P3IdentityResolutionIntegrationTest extends MySqlIntegrationTestCase
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

    public function testMysqlRejectsDuplicateOpenClaimsForEitherPersonOrClaimant(): void
    {
        [$claimantOne] = $this->fixture->account();
        [$claimantTwo] = $this->fixture->account();
        [$reviewer] = $this->fixture->account();
        $personOne = $this->insertPerson($reviewer, 'QMP-AAAAAAAAAAAAAAAB');
        $personTwo = $this->insertPerson($reviewer, 'QMP-AAAAAAAAAAAAAAAC');

        $this->insertPendingClaim($this->insertConsumedPairing($claimantOne, 'AAAAAAAAAAAB'), $personOne, $claimantOne, $reviewer);
        try {
            $this->insertPendingClaim($this->insertConsumedPairing($claimantTwo, 'AAAAAAAAAAAC'), $personOne, $claimantTwo, $reviewer);
            self::fail('A second pending claim for one Person was accepted.');
        } catch (PDOException) {
            self::addToAssertionCount(1);
        }
        try {
            $this->insertPendingClaim($this->insertConsumedPairing($claimantOne, 'AAAAAAAAAAAD'), $personTwo, $claimantOne, $reviewer);
            self::fail('A second pending claim for one Account was accepted.');
        } catch (PDOException) {
            self::addToAssertionCount(1);
        }
    }

    public function testLifecycleAndAliasHistoryAreImmutableInMysql(): void
    {
        [$claimant] = $this->fixture->account();
        [$reviewer] = $this->fixture->account();
        $first = $this->insertPerson($reviewer, 'QMP-AAAAAAAAAAAAAAAE');
        $second = $this->insertPerson($reviewer, 'QMP-AAAAAAAAAAAAAAAF');
        $pairing = $this->insertConsumedPairing($claimant, 'AAAAAAAAAAAE');
        $claim = $this->insertPendingClaim($pairing, $first, $claimant, $reviewer);
        $case = $this->insertBlockedCase($first, $second, $reviewer);
        $event = UuidV7::generate()->toString();
        $this->connection->prepare("INSERT INTO people_profile_claim_events (public_id,claim_id,event_type,actor_type,actor_account_id,reason_code,correlation_id,occurred_at,created_at) VALUES (UUID_TO_BIN(:public_id),:claim_id,'AUTHORIZED','ACCOUNT',:actor,'TEST_EVENT',NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))")->execute(['public_id' => $event, 'claim_id' => $claim, 'actor' => $reviewer]);
        $alias = UuidV7::generate()->toString();
        $this->connection->prepare('INSERT INTO people_person_aliases (public_id,source_person_id,canonical_person_id,duplicate_case_id,created_by_account_id,created_at) VALUES (UUID_TO_BIN(:public_id),:source,:canonical,:case_id,:actor,UTC_TIMESTAMP(6))')->execute(['public_id' => $alias, 'source' => $second, 'canonical' => $first, 'case_id' => $case, 'actor' => $reviewer]);

        foreach (
            [
            "UPDATE people_profile_claim_events SET reason_code = 'TAMPERED' WHERE public_id = UUID_TO_BIN(:public_id)" => $event,
            'DELETE FROM people_person_aliases WHERE public_id = UUID_TO_BIN(:public_id)' => $alias,
            ] as $sql => $publicId
        ) {
            try {
                $this->connection->prepare($sql)->execute(['public_id' => $publicId]);
                self::fail('Immutable identity-resolution history was mutated.');
            } catch (PDOException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testConcurrentClaimPairingRevocationHasExactlyOneOptimisticWinner(): void
    {
        [$account] = $this->fixture->account();
        $pairing = $this->insertActivePairing($account, 'AAAAAAAAAAAG');
        $results = $this->runWorkers([$pairing, $pairing]);
        sort($results);

        self::assertSame([false, true], $results);
        $statement = $this->connection->prepare('SELECT status,version FROM people_profile_claim_pairings WHERE id = :id');
        $statement->execute(['id' => $pairing]);
        $row = $statement->fetch(PDO::FETCH_NUM);
        if (!is_array($row)) {
            self::fail('Concurrent pairing mutation did not leave a database row.');
        }
        self::assertSame(['REVOKED', 2], array_values($row));
    }

    public function testProductionRepositoryPersistsPairingClaimAndExpiryLifecycleWithNativePrepares(): void
    {
        [$claimant] = $this->fixture->account();
        [$reviewer] = $this->fixture->account();
        $person = $this->insertPerson($reviewer, 'QMP-AAAAAAAAAAAAAAAH');
        $otherPerson = $this->insertPerson($reviewer, 'QMP-AAAAAAAAAAAAAAAJ');
        $now = new DateTimeImmutable('2026-09-02 18:15:00.000000', new DateTimeZone('UTC'));
        $configuration = new IdentityResolutionConfiguration(str_repeat('k', 32), 1, 128, 900, 5, 172800, 50, 100, 8, 2000, 128, 100, 60, 5, 60, 5);
        $repository = new MySqlIdentityResolutionRepository($this->provider());
        $code = ProfileClaimPairingCode::issue('AAAAAAAAAAAH', '0123456789012345678901');
        $pairing = $repository->createPairing(UuidV7::generate()->toString(), $claimant, $code, hash_hmac('sha256', 'test', str_repeat('k', 32), true), $configuration, $now);
        $claim = $repository->createClaim(UuidV7::generate()->toString(), $pairing, $person, $reviewer, 'PLATFORM_RECORD_REVIEW', null, 'TEST-REVIEW', 'test justification', $configuration, $now);
        $stored = $repository->claimByPublicId((string) $claim['public_id'], true);

        self::assertNotNull($stored);
        self::assertSame('PENDING_ACCEPTANCE', $stored['status']);
        $repository->transitionClaim($stored, 'EXPIRED', 0, 'PROFILE_CLAIM_EXPIRED', $now->modify('+3 days'));
        self::assertSame(2, $this->rowCount('people_profile_claim_events'));
        self::assertSame('EXPIRED', $this->column('SELECT status FROM people_profile_claims WHERE id = :id', ['id' => $claim['id']]));

        $blocked = $repository->createDuplicateCase(UuidV7::generate()->toString(), $person, $otherPerson, $reviewer, 'PLATFORM_REVIEWER', null, 'BLOCKED', 'CONSENT_UNAVAILABLE', 'TEST_CONFLICT', $now);
        self::assertSame('BLOCKED', $blocked['status']);
        $consentRequired = $repository->createDuplicateCase(UuidV7::generate()->toString(), $person, $otherPerson, $reviewer, 'PLATFORM_REVIEWER', null, 'CONSENT_REQUIRED', null, null, $now);
        $repository->createConsentRequirements($consentRequired, [['person_id' => $person, 'account_id' => $claimant, 'authority_type' => 'SELF', 'guardianship_id' => null]], $now);
        $updated = $repository->transitionDuplicateCase($consentRequired, 'UNDER_REVIEW', null, null, $reviewer, null, null, null, null, $now);
        self::assertSame('UNDER_REVIEW', $updated['status']);
        self::assertSame(3, $this->rowCount('people_duplicate_case_events'));
    }

    private function insertPerson(int $accountId, string $registryCode): int
    {
        $statement = $this->connection->prepare("INSERT INTO people_persons (public_id,registry_code,status,sex_classification,created_by_account_id,version,created_at,updated_at) VALUES (UUID_TO_BIN(UUID()),:registry_code,'ACTIVE','NOT_RECORDED',:account_id,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        $statement->execute(['registry_code' => $registryCode, 'account_id' => $accountId]);

        return (int) $this->connection->lastInsertId();
    }

    /** @param array<string, int|string> $parameters */
    private function column(string $sql, array $parameters): string
    {
        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);

        return (string) $statement->fetchColumn();
    }

    private function rowCount(string $table): int
    {
        $statement = $this->connection->query('SELECT COUNT(*) FROM ' . $table);
        self::assertNotFalse($statement);
        $value = $statement->fetchColumn();
        if (!is_int($value) && !is_string($value)) {
            self::fail('MySQL count result is invalid.');
        }

        return (int) $value;
    }

    private function insertActivePairing(int $accountId, string $selector): int
    {
        $statement = $this->connection->prepare("INSERT INTO people_profile_claim_pairings (public_id,account_id,code_selector,secret_hash,hmac_key_version,status,attempt_count,max_attempts,expires_at,version,created_at,updated_at) VALUES (UUID_TO_BIN(UUID()),:account_id,:selector,UNHEX(SHA2(:hash_selector,256)),1,'ACTIVE',0,5,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 15 MINUTE),1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        $statement->execute(['account_id' => $accountId, 'selector' => $selector, 'hash_selector' => $selector]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertConsumedPairing(int $accountId, string $selector): int
    {
        $id = $this->insertActivePairing($accountId, $selector);
        $this->connection->prepare("UPDATE people_profile_claim_pairings SET status = 'CONSUMED',consumed_at = UTC_TIMESTAMP(6) WHERE id = :id")->execute(['id' => $id]);

        return $id;
    }

    private function insertPendingClaim(int $pairingId, int $personId, int $claimantId, int $reviewerId): int
    {
        $statement = $this->connection->prepare("INSERT INTO people_profile_claims (public_id,pairing_id,person_id,claimant_account_id,authorization_type,authorized_by_account_id,authorization_guardianship_id,status,review_reference,review_justification,requested_at,expires_at,version,created_at,updated_at) VALUES (UUID_TO_BIN(UUID()),:pairing_id,:person_id,:claimant_id,'PLATFORM_RECORD_REVIEW',:reviewer_id,NULL,'PENDING_ACCEPTANCE',:reference,'test justification',UTC_TIMESTAMP(6),DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 2 DAY),1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        $statement->execute(['pairing_id' => $pairingId, 'person_id' => $personId, 'claimant_id' => $claimantId, 'reviewer_id' => $reviewerId, 'reference' => 'TEST-' . bin2hex(random_bytes(4))]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertBlockedCase(int $firstPersonId, int $secondPersonId, int $reporterId): int
    {
        $ordered = [$firstPersonId, $secondPersonId];
        sort($ordered, SORT_NUMERIC);
        $statement = $this->connection->prepare("INSERT INTO people_duplicate_cases (public_id,first_person_id,second_person_id,reported_by_account_id,reporter_authority_type,status,resolution_outcome,conflict_code,version,reported_at,blocked_at,created_at,updated_at) VALUES (UUID_TO_BIN(UUID()),:first,:second,:reporter,'PLATFORM_REVIEWER','BLOCKED','CONSENT_UNAVAILABLE','TEST_CONFLICT',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        $statement->execute(['first' => $ordered[0], 'second' => $ordered[1], 'reporter' => $reporterId]);

        return (int) $this->connection->lastInsertId();
    }

    /** @param list<int> $pairingIds
     * @return list<bool>
     */
    private function runWorkers(array $pairingIds): array
    {
        $processes = [];
        $worker = dirname(__DIR__, 2) . '/Support/MySql/P3IdentityResolutionMutationWorker.php';
        foreach ($pairingIds as $pairingId) {
            $pipes = [];
            $process = proc_open([PHP_BINARY, $worker], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 3), null, ['bypass_shell' => true]);
            self::assertIsResource($process);
            fwrite($pipes[0], json_encode(['pairing_id' => $pairingId], JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
            $processes[] = [$process, $pipes];
        }
        $results = [];
        foreach ($processes as [$process, $pipes]) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), is_string($stderr) ? $stderr : '');
            $decoded = json_decode(is_string($stdout) ? $stdout : '', true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($decoded) || !array_key_exists('mutated', $decoded) || !is_bool($decoded['mutated'])) {
                self::fail('Identity-resolution mutation worker returned an invalid result.');
            }
            $results[] = $decoded['mutated'];
        }

        return $results;
    }
}
