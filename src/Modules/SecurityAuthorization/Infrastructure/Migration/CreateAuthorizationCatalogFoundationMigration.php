<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Infrastructure\Migration;

use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration\CreatePasskeyFoundationMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateAuthorizationCatalogFoundationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826011600_create_authorization_catalog_foundation');
    }

    public function description(): string
    {
        return 'Create authorization permission and role catalog foundation.';
    }

    public function dependencies(): array
    {
        return [(new CreatePasskeyFoundationMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_create_authorization_permissions'),
                'Create immutable authorization permission definitions.',
                <<<'SQL'
CREATE TABLE authorization_permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    code VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    scope_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    required_assurance_level VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    owning_module VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    retired_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_authorization_permissions_public_id (public_id),
    UNIQUE KEY uq_authorization_permissions_code (code),
    UNIQUE KEY uq_authorization_permissions_id_scope (id, scope_type),
    KEY ix_authorization_permissions_scope_status_code (scope_type, status, code),
    KEY ix_authorization_permissions_status_updated (status, updated_at, id),
    CONSTRAINT ck_authorization_permissions_scope CHECK (scope_type IN ('PLATFORM','WORKSPACE')),
    CONSTRAINT ck_authorization_permissions_assurance CHECK (
        required_assurance_level IN ('PRIMARY','MULTI_FACTOR','PHISHING_RESISTANT')
    ),
    CONSTRAINT ck_authorization_permissions_status CHECK (status IN ('ACTIVE','RETIRED')),
    CONSTRAINT ck_authorization_permissions_version CHECK (version >= 1),
    CONSTRAINT ck_authorization_permissions_retired CHECK (
        (status = 'ACTIVE' AND retired_at IS NULL) OR (status = 'RETIRED' AND retired_at IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_create_authorization_roles'),
                'Create immutable authorization system-role definitions.',
                <<<'SQL'
CREATE TABLE authorization_roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    code VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    scope_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    is_system TINYINT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    retired_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_authorization_roles_public_id (public_id),
    UNIQUE KEY uq_authorization_roles_scope_code (scope_type, code),
    UNIQUE KEY uq_authorization_roles_id_scope (id, scope_type),
    KEY ix_authorization_roles_scope_status_code (scope_type, status, code),
    KEY ix_authorization_roles_status_updated (status, updated_at, id),
    CONSTRAINT ck_authorization_roles_scope CHECK (scope_type IN ('PLATFORM','WORKSPACE')),
    CONSTRAINT ck_authorization_roles_status CHECK (status IN ('ACTIVE','RETIRED')),
    CONSTRAINT ck_authorization_roles_system CHECK (is_system IN (0,1)),
    CONSTRAINT ck_authorization_roles_version CHECK (version >= 1),
    CONSTRAINT ck_authorization_roles_retired CHECK (
        (status = 'ACTIVE' AND retired_at IS NULL) OR (status = 'RETIRED' AND retired_at IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('003_create_authorization_role_permissions'),
                'Create explicit same-scope role-permission mappings.',
                <<<'SQL'
CREATE TABLE authorization_role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    role_scope_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    permission_scope_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    KEY ix_authorization_role_permissions_permission_role (permission_id, role_id),
    CONSTRAINT fk_authorization_role_permissions_role FOREIGN KEY (role_id, role_scope_type)
        REFERENCES authorization_roles (id, scope_type) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_authorization_role_permissions_permission FOREIGN KEY (permission_id, permission_scope_type)
        REFERENCES authorization_permissions (id, scope_type) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_authorization_role_permissions_scope CHECK (role_scope_type = permission_scope_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('004_extend_step_up_actions'),
                'Add action-bound authorization role-mutation step-up actions.',
                <<<'SQL'
ALTER TABLE account_step_up_grants
    DROP CHECK ck_step_up_grants_action,
    ADD CONSTRAINT ck_step_up_grants_action CHECK (action IN (
        'MFA_ENROLL_TOTP','MFA_REGISTER_PASSKEY','MFA_ENABLE','MFA_DISABLE',
        'MFA_REGENERATE_RECOVERY_CODES','MFA_REVOKE_TOTP','MFA_REVOKE_PASSKEY',
        'AUTHORIZATION_PLATFORM_ROLE_ASSIGN','AUTHORIZATION_PLATFORM_ROLE_REVOKE',
        'AUTHORIZATION_WORKSPACE_ROLE_ASSIGN','AUTHORIZATION_WORKSPACE_ROLE_REVOKE'
    ))
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('005_extend_security_notification_types'),
                'Add platform and workspace role-change security-notification types.',
                <<<'SQL'
ALTER TABLE account_security_notifications
    DROP CHECK ck_security_notifications_type,
    ADD CONSTRAINT ck_security_notifications_type CHECK (notification_type IN (
        'PASSWORD_RESET_COMPLETED','MFA_ENABLED','MFA_DISABLED','TOTP_AUTHENTICATOR_ADDED',
        'TOTP_AUTHENTICATOR_REMOVED','PASSKEY_ADDED','PASSKEY_REMOVED',
        'RECOVERY_CODES_REGENERATED','RECOVERY_CODE_USED','PASSKEY_SUSPENDED',
        'PLATFORM_ROLE_ASSIGNED','PLATFORM_ROLE_REVOKED',
        'WORKSPACE_ROLE_ASSIGNED','WORKSPACE_ROLE_REVOKED'
    ))
SQL,
            ),
        ];
    }

    public function down(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_restore_security_notification_types'),
                'Restore pre-authorization security-notification types.',
                <<<'SQL'
ALTER TABLE account_security_notifications
    DROP CHECK ck_security_notifications_type,
    ADD CONSTRAINT ck_security_notifications_type CHECK (notification_type IN (
        'PASSWORD_RESET_COMPLETED','MFA_ENABLED','MFA_DISABLED','TOTP_AUTHENTICATOR_ADDED',
        'TOTP_AUTHENTICATOR_REMOVED','PASSKEY_ADDED','PASSKEY_REMOVED',
        'RECOVERY_CODES_REGENERATED','RECOVERY_CODE_USED','PASSKEY_SUSPENDED'
    ))
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_restore_step_up_actions'),
                'Restore pre-authorization step-up actions.',
                <<<'SQL'
ALTER TABLE account_step_up_grants
    DROP CHECK ck_step_up_grants_action,
    ADD CONSTRAINT ck_step_up_grants_action CHECK (action IN (
        'MFA_ENROLL_TOTP','MFA_REGISTER_PASSKEY','MFA_ENABLE','MFA_DISABLE',
        'MFA_REGENERATE_RECOVERY_CODES','MFA_REVOKE_TOTP','MFA_REVOKE_PASSKEY'
    ))
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('003_drop_authorization_role_permissions'),
                'Drop authorization role-permission mappings.',
                'DROP TABLE authorization_role_permissions',
            ),
            new SqlMigrationStep(
                new MigrationStepId('004_drop_authorization_roles'),
                'Drop authorization role definitions.',
                'DROP TABLE authorization_roles',
            ),
            new SqlMigrationStep(
                new MigrationStepId('005_drop_authorization_permissions'),
                'Drop authorization permission definitions.',
                'DROP TABLE authorization_permissions',
            ),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
