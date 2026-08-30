<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use DateTimeImmutable;
use PDO;
use Qmdb\Modules\IdentityAccess\Infrastructure\Persistence\MySqlIdentityRateLimiter;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\HmacIdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccountState\Application\AccountStateOperationCommand;
use Qmdb\Modules\IdentityAccountState\Application\AccountStateOperationService;
use Qmdb\Modules\IdentityAccountState\Application\AccountStateSecurityNotificationService;
use Qmdb\Modules\IdentityAccountState\Configuration\AccountStateConfiguration;
use Qmdb\Modules\IdentityAccountState\Domain\AccountStateJustification;
use Qmdb\Modules\IdentityAccountState\Domain\AccountStateOperationType;
use Qmdb\Modules\IdentityAccountState\Domain\AccountStateReasonCode;
use Qmdb\Modules\IdentityAccountState\Domain\AccountStateReference;
use Qmdb\Modules\IdentityAccountState\Infrastructure\Persistence\MySqlAccountStateRepository;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Persistence\MySqlIdentityMultiFactorRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Configuration\SecurityNotificationConfiguration;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\SecurityNotificationDeduplicationKeyFactory;
use Qmdb\Modules\IdentitySecurityNotifications\Infrastructure\Persistence\MySqlAccountSecurityNotificationRepository;
use Qmdb\Modules\SecurityAudit\Infrastructure\Persistence\HashChainedSecurityAuditRecorder;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditHashChain;
use Qmdb\Modules\SecurityAudit\Configuration\SecurityAuditConfiguration;
use Qmdb\Modules\SecurityAudit\Domain\CanonicalSecurityEventMetadataSerializer;
use Qmdb\Modules\SecurityAudit\Domain\SecurityAuditIntegrityKeyProvider;
use Qmdb\Modules\SecurityAuthorization\Application\AuthenticationAssuranceComparator;
use Qmdb\Modules\SecurityAuthorization\Application\BaseRoleAuthorizationGuard;
use Qmdb\Modules\SecurityAuthorization\Application\RoleBasedAuthorizationService;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalogRegistry;
use Qmdb\Modules\SecurityAuthorization\Infrastructure\Persistence\MySqlEffectivePermissionRepository;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlConnectionProvider;
use Qmdb\Tests\Support\IdentityAccess\FixedIdentityClock;
use Qmdb\Tests\Support\MySql\AuthorizationMySqlFixture;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;
use Qmdb\Tests\Support\Observability\InMemoryEventLogger;

#[\PHPUnit\Framework\Attributes\Group('AccountStateSecurity')]
final class P2AccountStateIntegrationTest extends MySqlIntegrationTestCase
{
    private MySqlConnectionProvider $database;
    private PDO $connection;
    private AuthorizationMySqlFixture $fixture;
    private AccountStateOperationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = $this->provider();
        $this->connection = $this->database->connection();
        $this->fixture = new AuthorizationMySqlFixture($this->connection, dirname(__DIR__, 3));
        $this->fixture->rebuild();
        $this->service = $this->service();
    }

    protected function tearDown(): void
    {
        $this->fixture->dropBusinessTables();
        parent::tearDown();
    }

    public function testSuspensionAndReactivationPreserveEvidenceAndNeverRestoreAccess(): void
    {
        [$actorInternalId, $actorId] = $this->fixture->account();
        [$targetInternalId, $targetId] = $this->fixture->account();
        $this->fixture->verifiedEmail($targetInternalId);
        $this->fixture->platformAssignment($actorInternalId, 'platform.security_administrator', $actorInternalId);
        $this->fixture->stepUpGrant($targetInternalId, StepUpAction::ACCOUNT_SUSPEND->value);

        $suspension = new AccountStateOperationCommand(
            $this->fixture->authenticatedStepUpContext($actorInternalId, $actorId, StepUpAction::ACCOUNT_SUSPEND->value),
            AccountStateOperationType::SUSPEND,
            UuidV7::fromString($targetId->toString()),
            1,
            UuidV7::generate(),
            AccountStateReasonCode::SECURITY_INCIDENT,
            new AccountStateJustification('Verified security incident requires temporary account suspension.', 2000),
            new AccountStateReference('INC-2026-0001', 128),
            str_repeat('a', 32),
        );
        $first = $this->service->execute($suspension);
        $replay = $this->service->execute($suspension);

        self::assertSame($first->operationPublicId, $replay->operationPublicId);
        self::assertSame('SUSPENDED', $this->scalar('SELECT account_status FROM user_accounts WHERE id = ' . $targetInternalId));
        self::assertSame(2, (int) $this->scalar('SELECT version FROM user_accounts WHERE id = ' . $targetInternalId));
        self::assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM account_state_operations'));
        self::assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM security_audit_events WHERE event_code = 'identity.account.suspended'"));
        self::assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM account_security_notifications WHERE notification_type = 'ACCOUNT_SUSPENDED'"));
        self::assertSame('REVOKED', $this->scalar('SELECT status FROM user_sessions WHERE account_id = ' . $targetInternalId));
        self::assertSame('REVOKED', $this->scalar('SELECT status FROM account_step_up_grants WHERE account_id = ' . $targetInternalId));

        $reactivation = new AccountStateOperationCommand(
            $this->fixture->authenticatedStepUpContext($actorInternalId, $actorId, StepUpAction::ACCOUNT_REACTIVATE->value),
            AccountStateOperationType::REACTIVATE,
            UuidV7::fromString($targetId->toString()),
            2,
            UuidV7::generate(),
            AccountStateReasonCode::SECURITY_REMEDIATION_COMPLETE,
            new AccountStateJustification('The documented remediation has completed and was independently reviewed.', 2000),
            new AccountStateReference('INC-2026-0001', 128),
            str_repeat('b', 32),
        );
        $this->service->execute($reactivation);

        self::assertSame('ACTIVE', $this->scalar('SELECT account_status FROM user_accounts WHERE id = ' . $targetInternalId));
        self::assertSame(3, (int) $this->scalar('SELECT version FROM user_accounts WHERE id = ' . $targetInternalId));
        self::assertSame(2, (int) $this->scalar('SELECT COUNT(*) FROM account_state_operations'));
        self::assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM security_audit_events WHERE event_code = 'identity.account.reactivated'"));
        self::assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM account_security_notifications WHERE notification_type = 'ACCOUNT_REACTIVATED'"));
        self::assertSame('REVOKED', $this->scalar('SELECT status FROM user_sessions WHERE account_id = ' . $targetInternalId));
    }

    private function service(): AccountStateOperationService
    {
        $clock = new FixedIdentityClock(new DateTimeImmutable('2026-08-28T10:05:00.000000Z'));
        $multiFactor = new MySqlIdentityMultiFactorRepository($this->database);
        $transactions = $this->transactionManager($this->database);
        $authorization = new BaseRoleAuthorizationGuard(new RoleBasedAuthorizationService(
            AuthorizationCatalogRegistry::withAuditAccountState(),
            new MySqlEffectivePermissionRepository($this->database),
            new AuthenticationAssuranceComparator(),
            new InMemoryEventLogger(),
        ));
        $auditConfiguration = new SecurityAuditConfiguration(false, 4096, 1000, 3600, 10000, 1);
        $keys = new class implements SecurityAuditIntegrityKeyProvider {
            public function keyForVersion(int $version): string
            {
                if ($version !== 1) {
                    throw new \RuntimeException('Unexpected test integrity-key version.');
                }

                return hash('sha256', 'QMDB-NON-PRODUCTION-SECURITY-AUDIT-KEY-V1', true);
            }
        };

        return new AccountStateOperationService(
            $authorization,
            new StepUpGuard($multiFactor, $clock),
            new MySqlIdentityRateLimiter($this->database, $transactions),
            new HmacIdentityFingerprintGenerator(str_repeat('f', 32)),
            new MySqlAccountStateRepository($this->database),
            new HashChainedSecurityAuditRecorder(
                $this->database,
                new CanonicalSecurityEventMetadataSerializer(4096),
                $keys,
                $auditConfiguration,
                new SecurityAuditHashChain(),
            ),
            new AccountStateSecurityNotificationService(
                $multiFactor,
                new MySqlAccountSecurityNotificationRepository($this->database),
                new SecurityNotificationDeduplicationKeyFactory(),
                new SecurityNotificationConfiguration(25, 5, 120, 60, 300),
            ),
            new AccountStateConfiguration(2000, 128, 900, 10),
            $transactions,
            $clock,
        );
    }

    private function scalar(string $sql): int|string
    {
        $statement = $this->connection->query($sql);
        self::assertInstanceOf(\PDOStatement::class, $statement);
        $value = $statement->fetchColumn();
        self::assertTrue(is_int($value) || is_string($value));

        return $value;
    }
}
