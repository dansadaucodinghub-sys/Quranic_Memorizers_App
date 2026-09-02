<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\Identity\Domain\AccountContactStatus;
use Qmdb\Modules\Identity\Domain\AccountStatus;
use Qmdb\Modules\Identity\Domain\Exception\DuplicateIdentityContactException;
use Qmdb\Modules\Identity\Domain\UserAccount;
use Qmdb\Modules\Identity\Domain\Value\AccountEmailId;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\Identity\Domain\Value\CredentialId;
use Qmdb\Modules\Identity\Domain\Value\LookupHash;
use Qmdb\Modules\Identity\Domain\Value\SensitivePasswordHash;
use Qmdb\Modules\Identity\Infrastructure\Migration\CreateAccountSecurityFoundationMigration;
use Qmdb\Modules\Identity\Infrastructure\Migration\CreateUserAccountsMigration;
use Qmdb\Modules\Identity\Infrastructure\Persistence\MySqlAccountRepository;
use Qmdb\Modules\Identity\Infrastructure\Security\SodiumContactCipher;
use Qmdb\Modules\Tenancy\Application\TenantContext;
use Qmdb\Modules\Tenancy\Domain\MembershipStatus;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Modules\Tenancy\Domain\Workspace;
use Qmdb\Modules\Tenancy\Domain\WorkspaceMembership;
use Qmdb\Modules\Tenancy\Domain\WorkspaceStatus;
use Qmdb\Modules\Tenancy\Infrastructure\Migration\CreateWorkspaceMembershipsMigration;
use Qmdb\Modules\Tenancy\Infrastructure\Migration\CreateWorkspacesMigration;
use Qmdb\Modules\Tenancy\Infrastructure\Persistence\MySqlWorkspaceMembershipRepository;
use Qmdb\Modules\Tenancy\Infrastructure\Persistence\MySqlWorkspaceRepository;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

final class IdentityTenancyFoundationIntegrationTest extends MySqlIntegrationTestCase
{
    private PDO $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->provider()->connection();
        $this->dropTables();
        foreach ($this->migrations() as $migration) {
            foreach ($migration->up() as $step) {
                $this->connection->prepare($step->sql())->execute($step->parameters());
            }
        }
    }

    protected function tearDown(): void
    {
        $this->dropTables();
        parent::tearDown();
    }

    public function testMigrationsCreateInnoDbTablesConstraintsAndStableDependencies(): void
    {
        $statement = $this->connection->prepare(
            "SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() "
            . "AND TABLE_NAME IN ('workspaces','user_accounts','account_email_addresses',"
            . "'account_phone_numbers','account_credentials','account_status_events','workspace_memberships') "
            . 'ORDER BY TABLE_NAME',
        );
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        self::assertCount(7, $rows);
        foreach ($rows as $row) {
            self::assertIsArray($row);
            self::assertSame('InnoDB', $row['ENGINE'] ?? null);
        }
        self::assertSame(
            ['20260826010300_create_account_security_foundation'],
            array_map(
                static fn ($id): string => $id->value(),
                (new CreateWorkspaceMembershipsMigration())->dependencies(),
            ),
        );
    }

    public function testRepositoriesEnforceOpaqueIdsOptimisticVersionAndTenantScope(): void
    {
        $provider = $this->provider();
        $workspaceRepository = new MySqlWorkspaceRepository($provider);
        $accountRepository = new MySqlAccountRepository($provider);
        $membershipRepository = new MySqlWorkspaceMembershipRepository($provider);
        $now = new DateTimeImmutable('2026-08-26T12:00:00.000000Z');

        $workspaceId = WorkspaceId::generate();
        $workspaceInternalId = $workspaceRepository->create(new Workspace(
            null,
            $workspaceId,
            'synthetic-workspace',
            'Synthetic Workspace',
            WorkspaceStatus::ACTIVE,
            1,
            $now,
            $now,
        ));
        $accountId = AccountId::generate();
        $accountInternalId = $accountRepository->create(new UserAccount(
            null,
            $accountId,
            AccountStatus::PENDING_VERIFICATION,
            'en',
            'UTC',
            1,
            $now,
            $now,
        ));
        $cipher = new SodiumContactCipher(random_bytes(32), 'test-v1');
        $lookupHash = LookupHash::keyed('email', 'synthetic@example.test', random_bytes(32));
        $accountRepository->addEmail(
            $accountInternalId,
            AccountEmailId::generate(),
            $cipher->encrypt('synthetic@example.test'),
            $cipher->keyId(),
            $lookupHash,
            AccountContactStatus::UNVERIFIED,
            $now,
        );
        $accountRepository->addPasswordCredential(
            $accountInternalId,
            CredentialId::generate(),
            new SensitivePasswordHash(password_hash('synthetic-password-value', PASSWORD_ARGON2ID)),
            'argon2id',
            1,
            $now,
        );

        $authentication = $accountRepository->authenticationByEmailHash($lookupHash);
        self::assertNotNull($authentication);
        self::assertSame($accountId->toString(), $authentication->accountId->toString());
        self::assertTrue($workspaceRepository->changeStatus($workspaceId, WorkspaceStatus::SUSPENDED, 1, $now));
        self::assertFalse($workspaceRepository->changeStatus($workspaceId, WorkspaceStatus::ACTIVE, 1, $now));

        $context = TenantContext::trusted($workspaceInternalId, $workspaceId);
        $membership = new WorkspaceMembership(
            null,
            UuidV7::generate(),
            $workspaceInternalId,
            $accountInternalId,
            MembershipStatus::ACTIVE,
            1,
            $now,
            $now,
        );
        $membershipRepository->create($context, $membership);
        self::assertCount(1, $membershipRepository->forAccount($context, $accountInternalId));

        $this->expectException(\DomainException::class);
        $membershipRepository->create(
            TenantContext::trusted($workspaceInternalId + 1, WorkspaceId::generate()),
            $membership,
        );
    }

    public function testActiveEmailLookupHashIsGloballyUnique(): void
    {
        $provider = $this->provider();
        $accounts = new MySqlAccountRepository($provider);
        $now = new DateTimeImmutable('2026-08-26T12:00:00.000000Z');
        $hash = LookupHash::keyed('email', 'duplicate@example.test', random_bytes(32));
        $cipher = new SodiumContactCipher(random_bytes(32), 'test-v1');

        foreach ([1, 2] as $attempt) {
            $accountId = $accounts->create(new UserAccount(
                null,
                AccountId::generate(),
                AccountStatus::PENDING_VERIFICATION,
                'en',
                'UTC',
                1,
                $now,
                $now,
            ));
            if ($attempt === 2) {
                $this->expectException(DuplicateIdentityContactException::class);
            }
            $accounts->addEmail(
                $accountId,
                AccountEmailId::generate(),
                $cipher->encrypt('duplicate@example.test'),
                $cipher->keyId(),
                $hash,
                AccountContactStatus::UNVERIFIED,
                $now,
            );
        }
    }

    /** @return list<Migration> */
    private function migrations(): array
    {
        return [
            new CreateWorkspacesMigration(),
            new CreateUserAccountsMigration(),
            new CreateAccountSecurityFoundationMigration(),
            new CreateWorkspaceMembershipsMigration(),
        ];
    }

    private function dropTables(): void
    {
        foreach (
            [
                'organization_affiliation_status_events', 'organization_affiliation_role_assignments',
                'organization_affiliation_unit_assignments', 'organization_affiliations',
                'organization_affiliation_role_definitions', 'organization_unit_locations',
                'organization_unit_names', 'organization_units', 'organization_jurisdictions',
                'organization_classification_assignments', 'organization_names', 'organizations',
                'organization_classifications',
                'people_duplicate_case_events', 'people_duplicate_consent_requirements',
                'people_person_aliases', 'people_duplicate_cases',
                'people_profile_verification_assertions', 'people_profile_claim_events', 'people_profile_claims',
                'people_profile_claim_pairings',
                'people_profile_operation_results', 'people_guardianships', 'people_memorizer_progress',
                'people_role_profiles', 'people_person_geographies', 'people_account_links', 'people_person_names',
                'people_persons',
                'account_state_operations', 'security_audit_checkpoint_heads', 'security_audit_checkpoints',
                'security_audit_events', 'security_audit_streams',
                'privileged_access_reviews', 'privileged_access_events', 'privileged_access_activations',
                'privileged_access_approvals', 'privileged_access_request_permissions',
                'privileged_access_requests', 'privileged_access_permission_policies',
                'workspace_role_assignments',
                'platform_role_assignments',
                'authorization_role_permissions',
                'authorization_roles',
                'authorization_permissions',
                'account_webauthn_ceremonies',
                'account_passkey_credentials',
                'account_webauthn_user_handles',
                'account_recovery_codes',
                'account_recovery_code_sets',
                'account_totp_authenticators',
                'account_step_up_grants',
                'account_authentication_transactions',
                'account_mfa_policies',
                'account_security_notification_events',
                'account_security_notifications',
                'account_password_recovery_events',
                'account_password_recovery_challenges',
                'user_sessions',
                'user_devices',
                'identity_rate_limit_buckets',
                'account_email_verification_challenges',
                'identity_idempotency_records',
                'workspace_memberships',
                'account_status_events',
                'account_credentials',
                'account_phone_numbers',
                'account_email_addresses',
                'user_accounts',
                'workspaces',
            ] as $table
        ) {
            $this->connection->exec('DROP TABLE IF EXISTS ' . $table);
        }
    }
}
