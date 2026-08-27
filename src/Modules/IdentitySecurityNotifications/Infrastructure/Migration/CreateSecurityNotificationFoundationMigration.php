<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySecurityNotifications\Infrastructure\Migration;

use Qmdb\Modules\IdentityRecovery\Infrastructure\Migration\CreatePasswordRecoveryFoundationMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateSecurityNotificationFoundationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826011100_create_security_notification_foundation');
    }

    public function description(): string
    {
        return 'Create durable account security notification delivery records.';
    }

    public function dependencies(): array
    {
        return [(new CreatePasswordRecoveryFoundationMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_create_account_security_notifications'),
                'Create account security notification intents.',
                <<<'SQL'
CREATE TABLE account_security_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    account_email_address_id BIGINT UNSIGNED NOT NULL,
    notification_type VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    deduplication_key BINARY(32) NOT NULL,
    locale VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    maximum_attempts SMALLINT UNSIGNED NOT NULL,
    next_attempt_at DATETIME(6) NOT NULL,
    claim_execution_id BINARY(16) NULL,
    claimed_at DATETIME(6) NULL,
    lease_expires_at DATETIME(6) NULL,
    delivered_at DATETIME(6) NULL,
    failed_at DATETIME(6) NULL,
    failure_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    occurred_at DATETIME(6) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_security_notifications_public_id (public_id),
    UNIQUE KEY uq_security_notifications_deduplication (deduplication_key),
    KEY ix_security_notifications_due (status, next_attempt_at, id),
    KEY ix_security_notifications_lease (status, lease_expires_at, id),
    KEY ix_security_notifications_account (account_id, occurred_at, id),
    KEY ix_security_notifications_type (notification_type, occurred_at, id),
    CONSTRAINT fk_security_notifications_account FOREIGN KEY (account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_security_notifications_account_email FOREIGN KEY (account_id, account_email_address_id)
        REFERENCES account_email_addresses (user_account_id, id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_security_notifications_type CHECK (notification_type IN ('PASSWORD_RESET_COMPLETED')),
    CONSTRAINT ck_security_notifications_locale CHECK (locale IN ('en','ar')),
    CONSTRAINT ck_security_notifications_status CHECK (
        status IN ('PENDING','CLAIMED','DELIVERED','FAILED','SUPPRESSED')
    ),
    CONSTRAINT ck_security_notifications_attempts CHECK (attempt_count <= maximum_attempts),
    CONSTRAINT ck_security_notifications_maximum CHECK (maximum_attempts >= 1),
    CONSTRAINT ck_security_notifications_version CHECK (version >= 1),
    CONSTRAINT ck_security_notifications_claim CHECK (
        status <> 'CLAIMED'
        OR (claim_execution_id IS NOT NULL AND claimed_at IS NOT NULL AND lease_expires_at IS NOT NULL)
    ),
    CONSTRAINT ck_security_notifications_delivered CHECK (status <> 'DELIVERED' OR delivered_at IS NOT NULL),
    CONSTRAINT ck_security_notifications_failed CHECK (status <> 'FAILED' OR failed_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_create_account_security_notification_events'),
                'Create append-only security notification delivery history.',
                <<<'SQL'
CREATE TABLE account_security_notification_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    notification_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    attempt_number SMALLINT UNSIGNED NULL,
    claim_execution_id BINARY(16) NULL,
    failure_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    occurred_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_security_notification_events_public_id (public_id),
    KEY ix_security_notification_events_notification (notification_id, occurred_at, id),
    KEY ix_security_notification_events_type (event_type, occurred_at, id),
    CONSTRAINT fk_security_notification_events_notification FOREIGN KEY (notification_id)
        REFERENCES account_security_notifications (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_security_notification_events_type CHECK (
        event_type IN ('CREATED','CLAIMED','DELIVERED','RETRY_SCHEDULED','FAILED','SUPPRESSED')
    ),
    CONSTRAINT ck_security_notification_events_attempt CHECK (attempt_number IS NULL OR attempt_number >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
            ),
        ];
    }

    public function down(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_drop_account_security_notification_events'),
                'Drop security notification delivery history.',
                'DROP TABLE account_security_notification_events',
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_drop_account_security_notifications'),
                'Drop security notification intents.',
                'DROP TABLE account_security_notifications',
            ),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
