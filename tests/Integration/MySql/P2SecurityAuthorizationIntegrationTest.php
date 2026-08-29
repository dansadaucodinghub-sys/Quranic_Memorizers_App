<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use DateTimeImmutable;
use PDO;
use PDOException;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\SessionAuthenticationAssurance;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentitySessions\Domain\DeviceId;
use Qmdb\Modules\IdentitySessions\Domain\SessionId;
use Qmdb\Modules\SecurityAuthorization\Application\AuthenticationAssuranceComparator;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Application\RoleBasedAuthorizationService;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalogRegistry;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationDecisionReason;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceAuthorizationScope;
use Qmdb\Modules\SecurityAuthorization\Infrastructure\Persistence\MySqlEffectivePermissionRepository;
use Qmdb\Modules\SecurityAuthorization\Infrastructure\Persistence\MySqlWorkspaceRoleAssignmentRepository;
use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;
use Qmdb\Shared\Schema\Checksum\CanonicalChecksum;
use Qmdb\Shared\Schema\Migration\MigrationChecksum;
use Qmdb\Shared\Schema\Migration\MigrationRegistry;
use Qmdb\Shared\Schema\Seed\SeedChecksum;
use Qmdb\Shared\Schema\Seed\SeedRegistry;
use Qmdb\Tests\Support\MySql\AuthorizationMySqlFixture;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;
use Qmdb\Tests\Support\Observability\InMemoryEventLogger;
use Qmdb\Tests\Support\TenancyContext\AccountWorkspaceTenantContextFactory;

final class P2SecurityAuthorizationIntegrationTest extends MySqlIntegrationTestCase
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

    public function testMigrationsAndSeedProduceExactDurableCatalogWithoutAssignments(): void
    {
        $migrationFactory = require dirname(__DIR__, 3) . '/database/migrations.php';
        $seedFactory = require dirname(__DIR__, 3) . '/database/seeds.php';
        self::assertIsCallable($migrationFactory);
        self::assertIsCallable($seedFactory);
        $migrations = $migrationFactory();
        $seeds = $seedFactory();
        self::assertInstanceOf(MigrationRegistry::class, $migrations);
        self::assertInstanceOf(SeedRegistry::class, $seeds);
        $migrationChecksum = new MigrationChecksum(new CanonicalChecksum());
        $seedChecksum = new SeedChecksum(new CanonicalChecksum());
        foreach (array_slice($migrations->ordered(), -3) as $migration) {
            self::assertSame(64, strlen($migrationChecksum->migrationHex($migration)));
            self::assertTrue($migration->reversible());
        }
        self::assertCount(1, $seeds->ordered());
        self::assertSame(64, strlen($seedChecksum->hexadecimal($seeds->ordered()[0])));

        self::assertSame(10, $this->fixture->tableCount('authorization_permissions'));
        self::assertSame(7, $this->fixture->tableCount('authorization_roles'));
        self::assertSame(27, $this->fixture->tableCount('authorization_role_permissions'));
        self::assertSame(0, $this->fixture->tableCount('platform_role_assignments'));
        self::assertSame(0, $this->fixture->tableCount('workspace_role_assignments'));
        self::assertSame([
            'authorization_permissions',
            'authorization_role_permissions',
            'authorization_roles',
            'platform_role_assignments',
            'workspace_role_assignments',
        ], array_keys($this->fixture->tableEngines()));
        foreach ($this->fixture->tableEngines() as $engine) {
            self::assertSame('InnoDB', $engine);
        }
        $workspaceDefinition = $this->fixture->tableDefinition('workspace_role_assignments');
        self::assertStringContainsString('datetime(6)', strtolower($workspaceDefinition));
        self::assertStringContainsString('binary(16)', strtolower($workspaceDefinition));
        self::assertStringContainsString('fk_workspace_role_assignments_membership', $workspaceDefinition);
        self::assertStringContainsString(
            '(`workspace_id`,`membership_id`)',
            str_replace(' ', '', $workspaceDefinition),
        );
        self::assertStringNotContainsString('ON DELETE CASCADE', $workspaceDefinition);
    }

    public function testCatalogAndAssignmentConstraintsRejectInvalidOrCrossScopeRows(): void
    {
        $this->assertRejected(fn () => $this->fixture->duplicatePermission('public_id'));
        $this->assertRejected(fn () => $this->fixture->duplicatePermission('code'));
        $this->assertRejected(fn () => $this->fixture->invalidPermission('GLOBAL', 'PRIMARY', 'ACTIVE', 1));
        $this->assertRejected(fn () => $this->fixture->invalidPermission('PLATFORM', 'WEAK', 'ACTIVE', 1));
        $this->assertRejected(fn () => $this->fixture->invalidPermission('PLATFORM', 'PRIMARY', 'UNKNOWN', 1));
        $this->assertRejected(fn () => $this->fixture->invalidPermission('PLATFORM', 'PRIMARY', 'ACTIVE', 0));
        $this->assertRejected(fn () => $this->fixture->invalidRole('GLOBAL', 'ACTIVE', 1));
        $this->assertRejected(fn () => $this->fixture->invalidRole('PLATFORM', 'UNKNOWN', 1));
        $this->assertRejected(fn () => $this->fixture->invalidRole('PLATFORM', 'ACTIVE', 0));
        $this->assertRejected(fn () => $this->fixture->mapRoles(
            'platform.authorization_auditor',
            'workspace.authorization.view',
        ));
        $this->assertRejected(fn () => $this->fixture->mapRoles(
            'platform.authorization_auditor',
            'platform.authorization.view',
        ));

        [$accountInternalId] = $this->fixture->account();
        $this->assertRejected(fn () => $this->fixture->platformAssignment(
            $accountInternalId,
            'workspace.viewer',
            $accountInternalId,
        ));
        $this->fixture->platformAssignment(
            $accountInternalId,
            'platform.authorization_auditor',
            $accountInternalId,
        );
        $this->assertRejected(fn () => $this->fixture->platformAssignment(
            $accountInternalId,
            'platform.authorization_auditor',
            $accountInternalId,
        ));

        [$workspaceA] = $this->fixture->workspace();
        [$workspaceB] = $this->fixture->workspace();
        [$membershipA] = $this->fixture->membership($workspaceA, $accountInternalId);
        $this->assertRejected(fn () => $this->fixture->crossWorkspaceAssignment(
            $workspaceB,
            $membershipA,
            $accountInternalId,
        ));
    }

    public function testPlatformAuthorizationIsRoleBackedDenyByDefaultAndAssuranceAware(): void
    {
        [$accountInternalId, $accountId] = $this->fixture->account();
        $assignmentId = $this->fixture->platformAssignment(
            $accountInternalId,
            'platform.authorization_auditor',
            $accountInternalId,
        );
        $service = $this->service();
        $view = fn (AuthenticationAssuranceLevel $assurance): AuthorizationRequest => new AuthorizationRequest(
            $this->subject($accountInternalId, $accountId, $assurance),
            new PermissionCode('platform.authorization.view'),
            new PlatformAuthorizationScope(),
        );

        self::assertSame(
            AuthorizationDecisionReason::DENIED_INSUFFICIENT_ASSURANCE,
            $service->decide($view(AuthenticationAssuranceLevel::PRIMARY))->reason,
        );
        self::assertTrue($service->decide($view(AuthenticationAssuranceLevel::MULTI_FACTOR))->isAllowed());
        self::assertTrue($service->decide($view(AuthenticationAssuranceLevel::PHISHING_RESISTANT))->isAllowed());
        self::assertSame(
            AuthorizationDecisionReason::DENIED_NO_ROLE_ASSIGNMENT,
            $service->decide(new AuthorizationRequest(
                $this->subject($accountInternalId, $accountId, AuthenticationAssuranceLevel::PHISHING_RESISTANT),
                new PermissionCode('platform.authorization.assign'),
                new PlatformAuthorizationScope(),
            ))->reason,
        );

        $this->fixture->revokePlatformAssignment($assignmentId);
        self::assertSame(
            AuthorizationDecisionReason::DENIED_NO_ROLE_ASSIGNMENT,
            $service->decide($view(AuthenticationAssuranceLevel::MULTI_FACTOR))->reason,
        );
        $this->fixture->platformAssignment(
            $accountInternalId,
            'platform.authorization_auditor',
            $accountInternalId,
        );
        $this->fixture->updateRoleStatus('platform.authorization_auditor', 'RETIRED');
        self::assertSame(
            AuthorizationDecisionReason::DENIED_ROLE_NOT_ACTIVE,
            $service->decide($view(AuthenticationAssuranceLevel::MULTI_FACTOR))->reason,
        );
        $this->fixture->updateRoleStatus('platform.authorization_auditor', 'ACTIVE');
        $this->fixture->updatePermissionStatus('platform.authorization.view', 'RETIRED');
        self::assertSame(
            AuthorizationDecisionReason::DENIED_PERMISSION_NOT_ACTIVE,
            $service->decide($view(AuthenticationAssuranceLevel::MULTI_FACTOR))->reason,
        );
        $this->fixture->updatePermissionStatus('platform.authorization.view', 'ACTIVE');
        $this->fixture->updateAccountStatus($accountInternalId, 'SUSPENDED');
        self::assertSame(
            AuthorizationDecisionReason::DENIED_ACCOUNT_NOT_ACTIVE,
            $service->decide($view(AuthenticationAssuranceLevel::MULTI_FACTOR))->reason,
        );
    }

    public function testWorkspaceAuthorizationAndRepositoryAreExactTenantScoped(): void
    {
        [$accountInternalId, $accountId] = $this->fixture->account();
        [$workspaceA, $workspaceAId, $repositoryContextA] = $this->fixture->workspace();
        [$workspaceB, $workspaceBId, $repositoryContextB] = $this->fixture->workspace();
        [$membershipA, $membershipAId] = $this->fixture->membership($workspaceA, $accountInternalId);
        [$membershipB, $membershipBId] = $this->fixture->membership($workspaceB, $accountInternalId);
        $assignmentId = $this->fixture->workspaceAssignment(
            $workspaceA,
            $membershipA,
            'workspace.viewer',
            $accountInternalId,
        );
        $service = $this->service();
        $authenticated = $this->authenticated(
            $accountInternalId,
            $accountId,
            AuthenticationAssuranceLevel::PRIMARY,
        );
        $contextA = AccountWorkspaceTenantContextFactory::create(
            $authenticated,
            $workspaceA,
            $workspaceAId,
            $membershipA,
            $membershipAId,
        );
        $contextB = AccountWorkspaceTenantContextFactory::create(
            $authenticated,
            $workspaceB,
            $workspaceBId,
            $membershipB,
            $membershipBId,
        );
        $request = fn (AccountWorkspaceTenantContext $context): AuthorizationRequest => new AuthorizationRequest(
            AuthorizationSubject::fromAuthenticatedContext($authenticated),
            new PermissionCode('workspace.authorization.view'),
            new WorkspaceAuthorizationScope($context),
        );

        self::assertTrue($service->decide($request($contextA))->isAllowed());
        self::assertSame(
            AuthorizationDecisionReason::DENIED_NO_ROLE_ASSIGNMENT,
            $service->decide($request($contextB))->reason,
        );
        $repository = new MySqlWorkspaceRoleAssignmentRepository($this->provider());
        self::assertNotNull($repository->findActiveAssignment($repositoryContextA, $assignmentId));
        self::assertNull($repository->findActiveAssignment($repositoryContextB, $assignmentId));

        $this->fixture->updateMembershipStatus($membershipA, 'INVITED');
        self::assertSame(
            AuthorizationDecisionReason::DENIED_MEMBERSHIP_NOT_ACTIVE,
            $service->decide($request($contextA))->reason,
        );
        $this->fixture->updateMembershipStatus($membershipA, 'ACTIVE');
        $this->fixture->updateWorkspaceStatus($workspaceA, 'SUSPENDED');
        self::assertSame(
            AuthorizationDecisionReason::DENIED_WORKSPACE_NOT_ACTIVE,
            $service->decide($request($contextA))->reason,
        );
        $this->fixture->updateWorkspaceStatus($workspaceA, 'ACTIVE');
        $this->fixture->revokeWorkspaceAssignment($assignmentId);
        self::assertSame(
            AuthorizationDecisionReason::DENIED_NO_ROLE_ASSIGNMENT,
            $service->decide($request($contextA))->reason,
        );
    }

    private function service(): RoleBasedAuthorizationService
    {
        return new RoleBasedAuthorizationService(
            AuthorizationCatalogRegistry::foundational(),
            new MySqlEffectivePermissionRepository($this->provider()),
            new AuthenticationAssuranceComparator(),
            new InMemoryEventLogger(),
        );
    }

    private function subject(
        int $accountInternalId,
        AccountId $accountId,
        AuthenticationAssuranceLevel $level,
    ): AuthorizationSubject {
        return AuthorizationSubject::fromAuthenticatedContext(
            $this->authenticated($accountInternalId, $accountId, $level),
        );
    }

    private function authenticated(
        int $accountInternalId,
        AccountId $accountId,
        AuthenticationAssuranceLevel $level,
    ): AuthenticatedAccountContext {
        $now = new DateTimeImmutable('2026-08-28T10:00:00Z');
        $secondary = match ($level) {
            AuthenticationAssuranceLevel::PRIMARY => null,
            AuthenticationAssuranceLevel::MULTI_FACTOR => AuthenticationMethod::TOTP,
            AuthenticationAssuranceLevel::PHISHING_RESISTANT => AuthenticationMethod::PASSKEY,
        };

        return new AuthenticatedAccountContext(
            $accountInternalId,
            $accountId,
            1,
            SessionId::generate(),
            1,
            DeviceId::generate(),
            $now,
            1,
            new SessionAuthenticationAssurance(
                AuthenticationMethod::PASSWORD,
                $secondary,
                $level,
                $now,
                $level === AuthenticationAssuranceLevel::PRIMARY ? null : $now,
            ),
        );
    }

    /** @param \Closure(): mixed $operation */
    private function assertRejected(\Closure $operation): void
    {
        try {
            $operation();
            self::fail('MySQL must reject the invalid authorization row.');
        } catch (PDOException) {
            self::addToAssertionCount(1);
        }
    }
}
