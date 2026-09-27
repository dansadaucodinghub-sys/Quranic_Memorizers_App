<?php

declare(strict_types=1);

namespace Qmdb\Modules\ProductionHardening\Infrastructure\Migration;

use Qmdb\Modules\SearchAnalytics\Infrastructure\Migration\HardenAnalyticsSnapshotIdentityMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** QMDB-MIG-017: policy-governed notifications and scoped integrations. */
final readonly class CreateNotificationsIntegrationsMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260927100000_create_notifications_integrations');
    }

    public function description(): string
    {
        return 'Create notification, API client, credential, webhook, and provider-reference foundations.';
    }

    public function dependencies(): array
    {
        return [(new HardenAnalyticsSnapshotIdentityMigration())->id()];
    }

    public function reversible(): bool
    {
        return false;
    }

    public function down(): array
    {
        return [];
    }

    public function up(): array
    {
        return [
            $this->step('001_notification_templates', 'Create immutable localized notification templates.', <<<'SQL'
CREATE TABLE notification_templates (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, template_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 locale VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, channel_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 subject_template VARCHAR(240) NOT NULL, body_template TEXT NOT NULL, classification_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 mandatory_flag TINYINT(1) NOT NULL DEFAULT 0, version INT UNSIGNED NOT NULL, checksum BINARY(32) NOT NULL,
 status_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, created_at DATETIME(6) NOT NULL, retired_at DATETIME(6) NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_notification_template_public(public_id), UNIQUE KEY uq_p12_notification_template_version(template_code,locale,channel_code,version),
 KEY ix_p12_notification_template_active(template_code,locale,status_code,version),
 CONSTRAINT ck_p12_notification_template CHECK(locale IN ('en','ar') AND channel_code IN ('IN_APP','EMAIL') AND classification_code IN ('PUBLIC','INTERNAL','RESTRICTED','HIGHLY_RESTRICTED') AND mandatory_flag IN (0,1) AND version>=1 AND status_code IN ('ACTIVE','RETIRED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('002_notification_preferences', 'Create account notification preferences.', <<<'SQL'
CREATE TABLE notification_preferences (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, account_id BIGINT UNSIGNED NOT NULL,
 notification_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, channel_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 enabled_flag TINYINT(1) NOT NULL, quiet_start TIME NULL, quiet_end TIME NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_notification_preference_public(public_id), UNIQUE KEY uq_p12_notification_preference(account_id,notification_code,channel_code),
 CONSTRAINT fk_p12_notification_preference_account FOREIGN KEY(account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_notification_preference CHECK(channel_code IN ('IN_APP','EMAIL') AND enabled_flag IN (0,1) AND version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('003_notifications', 'Create durable deduplicated notification intents.', <<<'SQL'
CREATE TABLE notifications (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NULL, recipient_account_id BIGINT UNSIGNED NOT NULL,
 source_event_public_id BINARY(16) NOT NULL, template_id BIGINT UNSIGNED NOT NULL, deduplication_key BINARY(32) NOT NULL,
 locale VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, safe_subject VARCHAR(240) NOT NULL, safe_body TEXT NOT NULL,
 source_uri VARCHAR(500) NULL, classification_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 status_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, read_at DATETIME(6) NULL, expires_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_notification_public(public_id), UNIQUE KEY uq_p12_notification_dedupe(recipient_account_id,deduplication_key),
 KEY ix_p12_notification_recipient(recipient_account_id,status_code,created_at,id), KEY ix_p12_notification_workspace(workspace_id,status_code,id),
 CONSTRAINT fk_p12_notification_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p12_notification_recipient FOREIGN KEY(recipient_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p12_notification_template FOREIGN KEY(template_id) REFERENCES notification_templates(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_notification CHECK(locale IN ('en','ar') AND classification_code IN ('PUBLIC','INTERNAL','RESTRICTED','HIGHLY_RESTRICTED') AND status_code IN ('PENDING','PARTIAL','DELIVERED','DEAD_LETTER','EXPIRED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('004_notification_deliveries', 'Create bounded notification delivery work.', <<<'SQL'
CREATE TABLE notification_deliveries (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, notification_id BIGINT UNSIGNED NOT NULL,
 channel_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, endpoint_hash BINARY(32) NOT NULL,
 status_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 available_at DATETIME(6) NOT NULL, leased_until DATETIME(6) NULL, lease_token BINARY(16) NULL, delivered_at DATETIME(6) NULL,
 last_error_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_notification_delivery_public(public_id), UNIQUE KEY uq_p12_notification_channel(notification_id,channel_code),
 KEY ix_p12_notification_delivery_due(status_code,available_at,id),
 CONSTRAINT fk_p12_notification_delivery_intent FOREIGN KEY(notification_id) REFERENCES notifications(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_notification_delivery CHECK(channel_code IN ('IN_APP','EMAIL') AND status_code IN ('PENDING','PROCESSING','DELIVERED','RETRY','DEAD_LETTER','SUPPRESSED') AND attempt_count<=10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('005_notification_attempts', 'Create append-only notification attempts.', <<<'SQL'
CREATE TABLE notification_delivery_attempts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, delivery_id BIGINT UNSIGNED NOT NULL,
 attempt_number SMALLINT UNSIGNED NOT NULL, outcome_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 provider_correlation_hash BINARY(32) NULL, response_class VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
 error_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL, started_at DATETIME(6) NOT NULL, completed_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_notification_attempt_public(public_id), UNIQUE KEY uq_p12_notification_attempt(delivery_id,attempt_number),
 CONSTRAINT fk_p12_notification_attempt_delivery FOREIGN KEY(delivery_id) REFERENCES notification_deliveries(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_notification_attempt CHECK(attempt_number>=1 AND outcome_code IN ('DELIVERED','TRANSIENT_FAILURE','PERMANENT_FAILURE','SUPPRESSED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('006_notification_dead_letters', 'Create notification dead-letter review records.', <<<'SQL'
CREATE TABLE notification_dead_letters (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, delivery_id BIGINT UNSIGNED NOT NULL,
 reason_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, safe_context_json JSON NOT NULL,
 status_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, created_at DATETIME(6) NOT NULL, resolved_at DATETIME(6) NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_notification_dead_public(public_id), UNIQUE KEY uq_p12_notification_dead_delivery(delivery_id),
 KEY ix_p12_notification_dead_status(status_code,created_at,id),
 CONSTRAINT fk_p12_notification_dead_delivery FOREIGN KEY(delivery_id) REFERENCES notification_deliveries(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_notification_dead CHECK(status_code IN ('OPEN','REQUEUED','RESOLVED') AND JSON_VALID(safe_context_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('007_api_clients', 'Create scoped API clients.', <<<'SQL'
CREATE TABLE api_clients (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NULL,
 client_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, display_name VARCHAR(191) NOT NULL,
 owner_account_id BIGINT UNSIGNED NOT NULL, status_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 quota_per_minute SMALLINT UNSIGNED NOT NULL DEFAULT 60, allowed_cidrs_json JSON NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME(6) NOT NULL, activated_at DATETIME(6) NULL, suspended_at DATETIME(6) NULL, revoked_at DATETIME(6) NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_api_client_public(public_id), UNIQUE KEY uq_p12_api_client_code(client_code),
 KEY ix_p12_api_client_workspace(workspace_id,status_code,id),
 CONSTRAINT fk_p12_api_client_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p12_api_client_owner FOREIGN KEY(owner_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_api_client CHECK(status_code IN ('PENDING','ACTIVE','SUSPENDED','REVOKED') AND quota_per_minute BETWEEN 1 AND 600 AND JSON_VALID(allowed_cidrs_json) AND version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('008_api_scopes', 'Create allowlisted client scopes.', <<<'SQL'
CREATE TABLE api_client_scopes (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, api_client_id BIGINT UNSIGNED NOT NULL, scope_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 granted_by_account_id BIGINT UNSIGNED NOT NULL, granted_at DATETIME(6) NOT NULL, revoked_at DATETIME(6) NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_api_client_scope(api_client_id,scope_code), KEY ix_p12_api_scope_active(scope_code,revoked_at,api_client_id),
 CONSTRAINT fk_p12_api_scope_client FOREIGN KEY(api_client_id) REFERENCES api_clients(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p12_api_scope_granter FOREIGN KEY(granted_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_api_scope CHECK(scope_code IN ('projections.results.read','projections.certificates.read','notifications.status.read','webhooks.manage'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('009_api_credentials', 'Create hashed rotating API credentials.', <<<'SQL'
CREATE TABLE api_credentials (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, api_client_id BIGINT UNSIGNED NOT NULL,
 key_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, secret_hash VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 status_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, not_before DATETIME(6) NOT NULL, expires_at DATETIME(6) NOT NULL,
 last_used_at DATETIME(6) NULL, rotated_from_id BIGINT UNSIGNED NULL, created_at DATETIME(6) NOT NULL, revoked_at DATETIME(6) NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_api_credential_public(public_id), UNIQUE KEY uq_p12_api_credential_key(key_id),
 KEY ix_p12_api_credential_active(api_client_id,status_code,expires_at),
 CONSTRAINT fk_p12_api_credential_client FOREIGN KEY(api_client_id) REFERENCES api_clients(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p12_api_credential_rotated FOREIGN KEY(rotated_from_id) REFERENCES api_credentials(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_api_credential CHECK(status_code IN ('ACTIVE','ROTATING','REVOKED','EXPIRED') AND expires_at>not_before)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('010_webhook_subscriptions', 'Create validated webhook subscriptions.', <<<'SQL'
CREATE TABLE webhook_subscriptions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NULL, api_client_id BIGINT UNSIGNED NOT NULL,
 event_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, endpoint_url VARCHAR(1000) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 endpoint_hash BINARY(32) NOT NULL, signing_key_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 signing_secret_ciphertext VARBINARY(512) NOT NULL, payload_classification VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 status_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, failure_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 version INT UNSIGNED NOT NULL DEFAULT 1, verified_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, suspended_at DATETIME(6) NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_webhook_subscription_public(public_id), UNIQUE KEY uq_p12_webhook_subscription_endpoint(api_client_id,event_code,endpoint_hash),
 KEY ix_p12_webhook_subscription_workspace(workspace_id,status_code,id),
 CONSTRAINT fk_p12_webhook_subscription_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p12_webhook_subscription_client FOREIGN KEY(api_client_id) REFERENCES api_clients(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_webhook_subscription CHECK(status_code IN ('PENDING_VERIFICATION','ACTIVE','SUSPENDED','REVOKED') AND payload_classification IN ('PUBLIC','INTERNAL','RESTRICTED') AND failure_count<=100 AND version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('011_integration_events', 'Create immutable minimized integration events.', <<<'SQL'
CREATE TABLE integration_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NULL,
 event_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, source_kind VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 source_public_id BINARY(16) NOT NULL, source_version INT UNSIGNED NOT NULL, classification_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 payload_json JSON NOT NULL, payload_hash BINARY(32) NOT NULL, correlation_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 occurred_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_integration_event_public(public_id), UNIQUE KEY uq_p12_integration_event_source(source_kind,source_public_id,source_version,event_code),
 KEY ix_p12_integration_event_workspace(workspace_id,id), KEY ix_p12_integration_event_code(event_code,id),
 CONSTRAINT fk_p12_integration_event_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_integration_event CHECK(source_version>=1 AND classification_code IN ('PUBLIC','INTERNAL','RESTRICTED') AND JSON_VALID(payload_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('012_webhook_deliveries', 'Create at-least-once webhook deliveries.', <<<'SQL'
CREATE TABLE webhook_deliveries (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, subscription_id BIGINT UNSIGNED NOT NULL, integration_event_id BIGINT UNSIGNED NOT NULL,
 delivery_key BINARY(32) NOT NULL, status_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 available_at DATETIME(6) NOT NULL, leased_until DATETIME(6) NULL, lease_token BINARY(16) NULL, delivered_at DATETIME(6) NULL,
 dead_lettered_at DATETIME(6) NULL, last_error_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_webhook_delivery_public(public_id), UNIQUE KEY uq_p12_webhook_delivery_once(subscription_id,integration_event_id),
 KEY ix_p12_webhook_delivery_due(status_code,available_at,id),
 CONSTRAINT fk_p12_webhook_delivery_subscription FOREIGN KEY(subscription_id) REFERENCES webhook_subscriptions(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p12_webhook_delivery_event FOREIGN KEY(integration_event_id) REFERENCES integration_events(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_webhook_delivery CHECK(status_code IN ('PENDING','PROCESSING','DELIVERED','RETRY','DEAD_LETTER','CANCELLED') AND attempt_count<=10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('013_webhook_attempts', 'Create append-only webhook attempts.', <<<'SQL'
CREATE TABLE webhook_delivery_attempts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, delivery_id BIGINT UNSIGNED NOT NULL,
 attempt_number SMALLINT UNSIGNED NOT NULL, key_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 signature_digest BINARY(32) NOT NULL, request_timestamp DATETIME(6) NOT NULL, response_status SMALLINT UNSIGNED NULL,
 response_class VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL, error_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 started_at DATETIME(6) NOT NULL, completed_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_webhook_attempt_public(public_id), UNIQUE KEY uq_p12_webhook_attempt(delivery_id,attempt_number),
 CONSTRAINT fk_p12_webhook_attempt_delivery FOREIGN KEY(delivery_id) REFERENCES webhook_deliveries(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_webhook_attempt CHECK(attempt_number>=1 AND (response_status IS NULL OR response_status BETWEEN 100 AND 599))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('014_provider_references', 'Create scoped external-provider references without provider secrets.', <<<'SQL'
CREATE TABLE external_provider_references (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NULL,
 provider_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, local_subject_kind VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 local_subject_public_id BINARY(16) NOT NULL, external_reference_hash BINARY(32) NOT NULL, status_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 last_reconciled_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p12_provider_reference_public(public_id), UNIQUE KEY uq_p12_provider_reference(provider_code,external_reference_hash),
 KEY ix_p12_provider_reference_local(workspace_id,local_subject_kind,local_subject_public_id),
 CONSTRAINT fk_p12_provider_reference_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p12_provider_reference CHECK(status_code IN ('ACTIVE','STALE','REVOKED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            $this->step('015_attempt_update_trigger', 'Protect notification delivery attempts from updates.', "CREATE TRIGGER trg_p12_notification_attempt_no_update BEFORE UPDATE ON notification_delivery_attempts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Notification attempts are immutable'"),
            $this->step('016_attempt_delete_trigger', 'Protect notification delivery attempts from deletion.', "CREATE TRIGGER trg_p12_notification_attempt_no_delete BEFORE DELETE ON notification_delivery_attempts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Notification attempts are immutable'"),
            $this->step('017_integration_update_trigger', 'Protect integration events from updates.', "CREATE TRIGGER trg_p12_integration_event_no_update BEFORE UPDATE ON integration_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Integration events are immutable'"),
            $this->step('018_integration_delete_trigger', 'Protect integration events from deletion.', "CREATE TRIGGER trg_p12_integration_event_no_delete BEFORE DELETE ON integration_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Integration events are immutable'"),
            $this->step('019_webhook_attempt_update_trigger', 'Protect webhook attempts from updates.', "CREATE TRIGGER trg_p12_webhook_attempt_no_update BEFORE UPDATE ON webhook_delivery_attempts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Webhook attempts are immutable'"),
            $this->step('020_webhook_attempt_delete_trigger', 'Protect webhook attempts from deletion.', "CREATE TRIGGER trg_p12_webhook_attempt_no_delete BEFORE DELETE ON webhook_delivery_attempts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Webhook attempts are immutable'"),
        ];
    }

    private function step(string $id, string $description, string $sql): SqlMigrationStep
    {
        return new SqlMigrationStep(new MigrationStepId($id), $description, $sql);
    }
}
