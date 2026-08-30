<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use DateTimeImmutable;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use Qmdb\Modules\SecurityAudit\Infrastructure\Persistence\HashChainedSecurityAuditRecorder;
use Qmdb\Modules\SecurityAudit\Infrastructure\Persistence\MySqlSecurityAuditRepository;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditAppendCommand;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditHashChain;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditListFilter;
use Qmdb\Modules\SecurityAudit\Infrastructure\Persistence\SecurityAuditCheckpointService;
use Qmdb\Modules\SecurityAudit\Infrastructure\Persistence\SecurityAuditVerifier;
use Qmdb\Modules\SecurityAudit\Configuration\SecurityAuditConfiguration;
use Qmdb\Modules\SecurityAudit\Domain\CanonicalSecurityEventMetadataSerializer;
use Qmdb\Modules\SecurityAudit\Domain\SecurityAuditIntegrityKeyProvider;
use Qmdb\Modules\SecurityAudit\Domain\SecurityAuditStreamIdentity;
use Qmdb\Modules\SecurityAudit\Domain\SecurityAuditStreamType;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventActorKind;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventOutcome;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Tests\Support\IdentityAccess\FixedIdentityClock;
use Qmdb\Tests\Support\MySql\AuthorizationMySqlFixture;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

final class P2SecurityAuditIntegrationTest extends MySqlIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new AuthorizationMySqlFixture($this->provider()->connection(), dirname(__DIR__, 3)))->rebuild();
    }

    #[Test]
    public function appendRollbackImmutabilityCheckpointAndVerificationAreEnforcedByMySql(): void
    {
        $provider = $this->provider();
        $transactions = $this->transactionManager($provider);
        $hashChain = new SecurityAuditHashChain();
        $configuration = new SecurityAuditConfiguration(false, 4096, 1000, 3600, 10000, 1);
        $keys = new class implements SecurityAuditIntegrityKeyProvider {
            public function keyForVersion(int $version): string
            {
                if ($version !== 1) {
                    throw new \RuntimeException('Unexpected test integrity-key version.');
                }

                return hash('sha256', 'QMDB-NON-PRODUCTION-SECURITY-AUDIT-KEY-V1', true);
            }
        };
        $recorder = new HashChainedSecurityAuditRecorder(
            $provider,
            new CanonicalSecurityEventMetadataSerializer(4096),
            $keys,
            $configuration,
            $hashChain,
        );
        $account = UuidV7::generate();
        $now = new DateTimeImmutable('2026-08-29T12:00:00.000000Z');
        $command = static fn (UuidV7 $subject, DateTimeImmutable $time): SecurityAuditAppendCommand => new SecurityAuditAppendCommand(
            new SecurityAuditStreamIdentity(SecurityAuditStreamType::ACCOUNT, $subject),
            SecurityEventCode::ACCOUNT_SUSPENDED,
            SecurityEventOutcome::SUCCESS,
            SecurityEventActorKind::SYSTEM,
            null,
            null,
            null,
            SecurityEventSubjectKind::ACCOUNT,
            $subject,
            'SECURITY_RESPONSE',
            null,
            null,
            ['new_status' => 'SUSPENDED', 'previous_status' => 'ACTIVE'],
            $time,
        );

        $first = $transactions->transactional(fn () => $recorder->append($command($account, $now)));
        $second = $transactions->transactional(fn () => $recorder->append($command($account, $now->modify('+1 second'))));

        self::assertSame(1, $first->sequenceNumber);
        self::assertSame(2, $second->sequenceNumber);
        self::assertSame($first->streamPublicId, $second->streamPublicId);

        $rolledBack = null;
        try {
            $transactions->transactional(function () use ($recorder, $command, $account, $now, &$rolledBack): never {
                $rolledBack = $recorder->append($command($account, $now->modify('+2 seconds')));
                throw new \RuntimeException('Force authoritative mutation rollback.');
            });
        } catch (\RuntimeException) {
        }
        self::assertNotNull($rolledBack);
        $missing = $provider->connection()->prepare('SELECT COUNT(*) FROM security_audit_events WHERE public_id = UUID_TO_BIN(:public_id)');
        $missing->execute([':public_id' => $rolledBack->eventPublicId]);
        self::assertSame(0, (int) $missing->fetchColumn());

        $updateRejected = false;
        try {
            $mutation = $provider->connection()->prepare('UPDATE security_audit_events SET reason_code = :reason WHERE public_id = UUID_TO_BIN(:public_id)');
            $mutation->execute([':reason' => 'ALTERED', ':public_id' => $first->eventPublicId]);
        } catch (PDOException) {
            $updateRejected = true;
        }
        $deleteRejected = false;
        try {
            $mutation = $provider->connection()->prepare('DELETE FROM security_audit_events WHERE public_id = UUID_TO_BIN(:public_id)');
            $mutation->execute([':public_id' => $first->eventPublicId]);
        } catch (PDOException) {
            $deleteRejected = true;
        }

        self::assertTrue($updateRejected);
        self::assertTrue($deleteRejected);
    }

    #[Test]
    public function checkpointIsDeterministicForAnUnchangedLedgerAndVerifierAcceptsItsSnapshot(): void
    {
        $provider = $this->provider();
        $transactions = $this->transactionManager($provider);
        $configuration = new SecurityAuditConfiguration(false, 4096, 1000, 3600, 10000, 1);
        $keys = new class implements SecurityAuditIntegrityKeyProvider {
            public function keyForVersion(int $version): string
            {
                return hash('sha256', 'QMDB-NON-PRODUCTION-SECURITY-AUDIT-KEY-V1', true);
            }
        };
        $clock = new FixedIdentityClock(new DateTimeImmutable('2026-08-29T12:05:00.000000Z'));
        $chain = new SecurityAuditHashChain();
        $checkpoints = new SecurityAuditCheckpointService($provider, $transactions, $keys, $configuration, $chain, $clock);

        $created = $checkpoints->createWhenChanged();
        $unchanged = $checkpoints->createWhenChanged();
        $verifier = new SecurityAuditVerifier($provider, $keys, $chain, $configuration);
        $report = $verifier->verify();

        self::assertTrue($created->created);
        self::assertFalse($unchanged->created);
        self::assertTrue($verifier->verifyControls()->isValid());
        self::assertGreaterThanOrEqual(1, $report->checkpointCount);
        self::assertTrue($report->isValid(), implode(', ', $report->errors));
    }

    #[Test]
    public function auditListingPathsRemainBoundedAndIndexBacked(): void
    {
        $statement = $this->provider()->connection()->prepare(
            'SELECT index_name FROM information_schema.statistics '
            . 'WHERE table_schema = DATABASE() AND table_name = :table',
        );
        $statement->execute([':table' => 'security_audit_events']);
        $indexes = [];
        foreach ($statement->fetchAll(\PDO::FETCH_COLUMN) as $index) {
            if (is_string($index)) {
                $indexes[$index] = true;
            }
        }

        foreach (
            [
                'ix_security_audit_events_stream_occurred', 'ix_security_audit_events_code_occurred',
                'ix_security_audit_events_actor_occurred', 'ix_security_audit_events_subject_occurred',
                'ix_security_audit_events_workspace_occurred', 'ix_security_audit_events_severity_occurred',
            ] as $index
        ) {
            self::assertArrayHasKey($index, $indexes);
        }

        $repository = new MySqlSecurityAuditRepository($this->provider());
        $filter = new SecurityAuditListFilter(null, null, null, null, null, null);
        $this->assertInvalidPageSize(static fn () => $repository->listPlatform($filter, null, 101));
        $this->assertInvalidPageSize(static fn () => $repository->listForAccount(UuidV7::generate()->toString(), null, 101));
    }

    /** @param \Closure(): mixed $operation */
    private function assertInvalidPageSize(\Closure $operation): void
    {
        try {
            $operation();
            self::fail('An unbounded audit page must be rejected.');
        } catch (\InvalidArgumentException) {
            self::addToAssertionCount(1);
        }
    }
}
