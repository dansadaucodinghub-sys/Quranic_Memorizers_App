<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use DateTimeImmutable;
use PDO;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Persistence\MySqlIdentityMultiFactorRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Configuration\SecurityNotificationConfiguration;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\SecurityNotificationDeduplicationKeyFactory;
use Qmdb\Modules\IdentitySecurityNotifications\Infrastructure\Persistence\MySqlAccountSecurityNotificationRepository;
use Qmdb\Modules\SecurityAuthorization\Application\AuthenticationAssuranceComparator;
use Qmdb\Modules\SecurityAuthorization\Application\Exception\AuthorizationDeniedException;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationGuard;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSecurityNotificationService;
use Qmdb\Modules\SecurityAuthorization\Application\DelegationValidator;
use Qmdb\Modules\SecurityAuthorization\Application\PlatformRoleAssignmentCommand;
use Qmdb\Modules\SecurityAuthorization\Application\PlatformRoleAssignmentService;
use Qmdb\Modules\SecurityAuthorization\Application\PlatformRoleRevocationCommand;
use Qmdb\Modules\SecurityAuthorization\Application\PlatformRoleRevocationService;
use Qmdb\Modules\SecurityAuthorization\Application\RoleBasedAuthorizationService;
use Qmdb\Modules\SecurityAuthorization\Application\WorkspaceRoleAssignmentCommand;
use Qmdb\Modules\SecurityAuthorization\Application\WorkspaceRoleAssignmentService;
use Qmdb\Modules\SecurityAuthorization\Application\WorkspaceRoleRevocationCommand;
use Qmdb\Modules\SecurityAuthorization\Application\WorkspaceRoleRevocationService;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalogRegistry;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentReasonCode;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentStatus;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleCode;
use Qmdb\Modules\SecurityAuthorization\Infrastructure\Persistence\MySqlAuthorizationAdministrationRepository;
use Qmdb\Modules\SecurityAuthorization\Infrastructure\Persistence\MySqlEffectivePermissionRepository;
use Qmdb\Modules\SecurityAuthorization\Infrastructure\Persistence\MySqlPlatformRoleAssignmentRepository;
use Qmdb\Modules\SecurityAuthorization\Infrastructure\Persistence\MySqlWorkspaceRoleAssignmentRepository;
use Qmdb\Modules\SecurityAudit\Infrastructure\Persistence\HashChainedSecurityAuditRecorder;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditHashChain;
use Qmdb\Modules\SecurityAudit\Configuration\SecurityAuditConfiguration;
use Qmdb\Modules\SecurityAudit\Domain\CanonicalSecurityEventMetadataSerializer;
use Qmdb\Modules\SecurityAudit\Domain\SecurityAuditIntegrityKeyProvider;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlConnectionProvider;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Tests\Support\IdentityAccess\FixedIdentityClock;
use Qmdb\Tests\Support\MySql\AuthorizationMySqlFixture;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;
use Qmdb\Tests\Support\Observability\InMemoryEventLogger;
use Qmdb\Tests\Support\TenancyContext\AccountWorkspaceTenantContextFactory;

final class P2SecurityAuthorizationAdministrationIntegrationTest extends MySqlIntegrationTestCase
{
    private PDO $connection;
    private AuthorizationMySqlFixture $fixture;
    private MySqlConnectionProvider $database;
    private MySqlPlatformRoleAssignmentRepository $platformAssignments;
    private MySqlWorkspaceRoleAssignmentRepository $workspaceAssignments;
    private PlatformRoleAssignmentService $platformAssignmentService;
    private PlatformRoleRevocationService $platformRevocationService;
    private WorkspaceRoleAssignmentService $workspaceAssignmentService;
    private WorkspaceRoleRevocationService $workspaceRevocationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = $this->provider();
        $this->connection = $this->database->connection();
        $this->fixture = new AuthorizationMySqlFixture($this->connection, dirname(__DIR__, 3));
        $this->fixture->rebuild();
        $this->buildServices();
    }

    protected function tearDown(): void
    {
        $this->fixture->dropBusinessTables();
        parent::tearDown();
    }

    public function testPlatformAssignmentAndRevocationAreAtomicStepUpProtectedAndNotified(): void
    {
        [$actorInternalId, $actorId] = $this->fixture->account();
        [$targetInternalId, $targetId] = $this->fixture->account();
        $this->fixture->verifiedEmail($targetInternalId);
        $this->fixture->platformAssignment(
            $actorInternalId,
            'platform.security_administrator',
            $actorInternalId,
        );
        $assignAction = StepUpAction::AUTHORIZATION_PLATFORM_ROLE_ASSIGN;
        $assignActor = $this->fixture->authenticatedStepUpContext(
            $actorInternalId,
            $actorId,
            $assignAction->value,
        );

        $result = $this->platformAssignmentService->assign(new PlatformRoleAssignmentCommand(
            $assignActor,
            $targetId,
            new RoleCode('platform.authorization_auditor'),
            RoleAssignmentReasonCode::SECURITY_ADMINISTRATION,
            self::correlation(1),
        ));

        self::assertSame(RoleAssignmentStatus::ACTIVE, $result->status);
        self::assertSame(1, $result->version->value);
        self::assertSame(1, $this->fixture->activePlatformAssignmentCount(
            $targetInternalId,
            'platform.authorization_auditor',
        ));
        self::assertSame(1, $this->fixture->consumedStepUpGrantCount($actorInternalId, $assignAction->value));
        self::assertSame(1, $this->fixture->notificationCount(
            AccountSecurityNotificationType::PLATFORM_ROLE_ASSIGNED->value,
        ));

        $revokeAction = StepUpAction::AUTHORIZATION_PLATFORM_ROLE_REVOKE;
        $revokeActor = $this->fixture->authenticatedStepUpContext(
            $actorInternalId,
            $actorId,
            $revokeAction->value,
        );
        self::assertTrue($this->platformRevocationService->revoke(new PlatformRoleRevocationCommand(
            $revokeActor,
            $targetId,
            $result->assignmentId,
            RoleAssignmentReasonCode::SECURITY_RESPONSE,
            self::correlation(2),
        )));
        self::assertSame(0, $this->fixture->activePlatformAssignmentCount(
            $targetInternalId,
            'platform.authorization_auditor',
        ));
        self::assertSame(1, $this->fixture->consumedStepUpGrantCount($actorInternalId, $revokeAction->value));
        self::assertSame(1, $this->fixture->notificationCount(
            AccountSecurityNotificationType::PLATFORM_ROLE_REVOKED->value,
        ));
    }

    public function testWorkspaceAssignmentAndRevocationRemainInsideExactTenantScope(): void
    {
        [$actorInternalId, $actorId] = $this->fixture->account();
        [$targetInternalId] = $this->fixture->account();
        $this->fixture->verifiedEmail($targetInternalId);
        [$workspaceInternalId, $workspaceId] = $this->fixture->workspace();
        [$otherWorkspaceInternalId] = $this->fixture->workspace();
        [$actorMembershipInternalId, $actorMembershipId] = $this->fixture->membership(
            $workspaceInternalId,
            $actorInternalId,
        );
        [$targetMembershipInternalId, $targetMembershipId] = $this->fixture->membership(
            $workspaceInternalId,
            $targetInternalId,
        );
        $this->fixture->membership($otherWorkspaceInternalId, $targetInternalId);
        $this->fixture->workspaceAssignment(
            $workspaceInternalId,
            $actorMembershipInternalId,
            'workspace.owner',
            $actorInternalId,
        );
        $assignAction = StepUpAction::AUTHORIZATION_WORKSPACE_ROLE_ASSIGN;
        $assignActor = $this->fixture->authenticatedStepUpContext(
            $actorInternalId,
            $actorId,
            $assignAction->value,
        );
        $assignTenantContext = AccountWorkspaceTenantContextFactory::create(
            $assignActor,
            $workspaceInternalId,
            $workspaceId,
            $actorMembershipInternalId,
            $actorMembershipId,
        );

        $result = $this->workspaceAssignmentService->assign(new WorkspaceRoleAssignmentCommand(
            $assignActor,
            $assignTenantContext,
            $targetMembershipId,
            new RoleCode('workspace.viewer'),
            RoleAssignmentReasonCode::SECURITY_ADMINISTRATION,
            self::correlation(3),
        ));

        self::assertSame($workspaceId->toString(), $result->workspaceId->toString());
        self::assertSame(1, $this->fixture->activeWorkspaceAssignmentCount(
            $workspaceInternalId,
            $targetMembershipInternalId,
        ));
        self::assertSame(1, $this->fixture->notificationCount(
            AccountSecurityNotificationType::WORKSPACE_ROLE_ASSIGNED->value,
        ));

        $revokeAction = StepUpAction::AUTHORIZATION_WORKSPACE_ROLE_REVOKE;
        $revokeActor = $this->fixture->authenticatedStepUpContext(
            $actorInternalId,
            $actorId,
            $revokeAction->value,
        );
        $revokeTenantContext = AccountWorkspaceTenantContextFactory::create(
            $revokeActor,
            $workspaceInternalId,
            $workspaceId,
            $actorMembershipInternalId,
            $actorMembershipId,
        );
        self::assertTrue($this->workspaceRevocationService->revoke(new WorkspaceRoleRevocationCommand(
            $revokeActor,
            $revokeTenantContext,
            $result->assignmentId,
            RoleAssignmentReasonCode::MEMBERSHIP_STATE_CHANGE,
            self::correlation(4),
        )));
        self::assertSame(0, $this->fixture->activeWorkspaceAssignmentCount(
            $workspaceInternalId,
            $targetMembershipInternalId,
        ));
        self::assertSame(1, $this->fixture->consumedStepUpGrantCount($actorInternalId, $revokeAction->value));
        self::assertSame(1, $this->fixture->notificationCount(
            AccountSecurityNotificationType::WORKSPACE_ROLE_REVOKED->value,
        ));
    }

    public function testMissingNotificationTargetRollsBackAssignmentAndStepUpConsumption(): void
    {
        [$actorInternalId, $actorId] = $this->fixture->account();
        [$targetInternalId, $targetId] = $this->fixture->account();
        $this->fixture->platformAssignment(
            $actorInternalId,
            'platform.security_administrator',
            $actorInternalId,
        );
        $action = StepUpAction::AUTHORIZATION_PLATFORM_ROLE_ASSIGN;
        $actor = $this->fixture->authenticatedStepUpContext($actorInternalId, $actorId, $action->value);

        $denied = false;
        try {
            $this->platformAssignmentService->assign(new PlatformRoleAssignmentCommand(
                $actor,
                $targetId,
                new RoleCode('platform.authorization_auditor'),
                RoleAssignmentReasonCode::SECURITY_ADMINISTRATION,
                self::correlation(5),
            ));
            self::fail('A verified notification target must be required before privilege mutation commits.');
        } catch (\UnexpectedValueException $exception) {
            self::assertSame('Verified security-notification target is unavailable.', $exception->getMessage());
        }

        self::assertSame(0, $this->fixture->activePlatformAssignmentCount(
            $targetInternalId,
            'platform.authorization_auditor',
        ));
        self::assertSame(0, $this->fixture->consumedStepUpGrantCount($actorInternalId, $action->value));
        self::assertSame(0, $this->fixture->notificationCount(
            AccountSecurityNotificationType::PLATFORM_ROLE_ASSIGNED->value,
        ));
    }

    public function testDelegationRevalidationRejectsAssignmentAfterConcurrentAuthorityRevocation(): void
    {
        [$actorInternalId, $actorId] = $this->fixture->account();
        [$targetInternalId, $targetId] = $this->fixture->account();
        $this->fixture->verifiedEmail($targetInternalId);
        $authorityAssignment = $this->fixture->platformAssignment(
            $actorInternalId,
            'platform.security_administrator',
            $actorInternalId,
        );
        $action = StepUpAction::AUTHORIZATION_PLATFORM_ROLE_ASSIGN;
        $actor = $this->fixture->authenticatedStepUpContext($actorInternalId, $actorId, $action->value);
        $worker = dirname(__DIR__, 2) . '/Support/MySql/P2AuthorizationMutationWorker.php';
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, $worker],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 3),
            null,
            ['bypass_shell' => true],
        );
        self::assertIsResource($process);
        fwrite($pipes[0], json_encode([
            'operation' => 'revoke_platform_authority_delayed',
            'assignment_id' => $this->fixture->platformAssignmentInternalId($authorityAssignment),
            'delay_microseconds' => 2_000_000,
        ], JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        self::assertSame("locked\n", fgets($pipes[1]));

        $denied = false;
        try {
            $this->platformAssignmentService->assign(new PlatformRoleAssignmentCommand(
                $actor,
                $targetId,
                new RoleCode('platform.authorization_auditor'),
                RoleAssignmentReasonCode::SECURITY_ADMINISTRATION,
                self::correlation(6),
            ));
        } catch (AuthorizationDeniedException $exception) {
            $denied = true;
            self::assertSame('The requested operation is not permitted.', $exception->getMessage());
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), is_string($stderr) ? $stderr : '');
        $result = json_decode(is_string($stdout) ? $stdout : '', true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['mutated' => true], $result);
        self::assertTrue($denied, 'Delegation must be revalidated after locking the actor assignments.');
        self::assertSame(0, $this->fixture->activePlatformAssignmentCount(
            $targetInternalId,
            'platform.authorization_auditor',
        ));
        self::assertSame(0, $this->fixture->consumedStepUpGrantCount($actorInternalId, $action->value));
        self::assertSame(0, $this->fixture->notificationCount(
            AccountSecurityNotificationType::PLATFORM_ROLE_ASSIGNED->value,
        ));
    }

    private function buildServices(): void
    {
        $effectivePermissions = new MySqlEffectivePermissionRepository($this->database);
        $administration = new MySqlAuthorizationAdministrationRepository($this->database);
        $authorization = new AuthorizationGuard(new RoleBasedAuthorizationService(
            AuthorizationCatalogRegistry::foundational(),
            $effectivePermissions,
            new AuthenticationAssuranceComparator(),
            new InMemoryEventLogger(),
        ));
        $delegation = new DelegationValidator($effectivePermissions, $administration);
        $multiFactor = new MySqlIdentityMultiFactorRepository($this->database);
        $clock = new FixedIdentityClock(new DateTimeImmutable('2026-08-28T10:05:00.000000Z'));
        $stepUp = new StepUpGuard($multiFactor, $clock);
        $notifications = new AuthorizationSecurityNotificationService(
            $multiFactor,
            new MySqlAccountSecurityNotificationRepository($this->database),
            new SecurityNotificationDeduplicationKeyFactory(),
            new SecurityNotificationConfiguration(25, 5, 120, 60, 300),
        );
        $transactions = $this->transactionManager($this->database);
        $logger = new InMemoryEventLogger();
        $this->platformAssignments = new MySqlPlatformRoleAssignmentRepository($this->database);
        $this->workspaceAssignments = new MySqlWorkspaceRoleAssignmentRepository($this->database);
        $this->platformAssignmentService = new PlatformRoleAssignmentService(
            $authorization,
            $administration,
            $this->platformAssignments,
            $delegation,
            $stepUp,
            $notifications,
            $this->auditAppender(),
            $transactions,
            $logger,
            $clock,
        );
        $this->platformRevocationService = new PlatformRoleRevocationService(
            $authorization,
            $administration,
            $this->platformAssignments,
            $delegation,
            $stepUp,
            $notifications,
            $this->auditAppender(),
            $transactions,
            $logger,
            $clock,
        );
        $this->workspaceAssignmentService = new WorkspaceRoleAssignmentService(
            $authorization,
            $administration,
            $this->workspaceAssignments,
            $delegation,
            $stepUp,
            $notifications,
            $this->auditAppender(),
            $transactions,
            $logger,
            $clock,
        );
        $this->workspaceRevocationService = new WorkspaceRoleRevocationService(
            $authorization,
            $administration,
            $this->workspaceAssignments,
            $delegation,
            $stepUp,
            $notifications,
            $this->auditAppender(),
            $transactions,
            $logger,
            $clock,
        );
    }

    private static function correlation(int $sequence): CorrelationId
    {
        return new CorrelationId(str_pad(dechex($sequence), CorrelationId::LENGTH, '0', STR_PAD_LEFT));
    }

    private function auditAppender(): SecurityAuditEventAppender
    {
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

        return new SecurityAuditEventAppender(new HashChainedSecurityAuditRecorder(
            $this->database,
            new CanonicalSecurityEventMetadataSerializer(4096),
            $keys,
            $configuration,
            new SecurityAuditHashChain(),
        ));
    }
}
