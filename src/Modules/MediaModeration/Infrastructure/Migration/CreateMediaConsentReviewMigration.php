<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaModeration\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\{Migration, MigrationId, MigrationStepId, SqlMigrationStep};

final readonly class CreateMediaConsentReviewMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260917140000_create_media_consent_reviews');
    }
    public function description(): string
    {
        return 'Persist independent private-media rights and consent evidence reviews.';
    }
    public function dependencies(): array
    {
        return [(new ExtendMediaGovernanceSecurityMigration())->id()];
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
        // Reuse the immutable predecessor snapshot, never the live/future enum vocabulary.
        $stepUp = str_replace("'MEDIA_REMOVE'", "'MEDIA_REMOVE',\n'MEDIA_CONSENT_GRANT'", (new ExtendMediaGovernanceSecurityMigration())->up()[0]->sql());
        return [
            new SqlMigrationStep(new MigrationStepId('001_review'), 'Create immutable evidence review receipts.', <<<'SQL'
CREATE TABLE media_consent_reviews (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 workspace_id BIGINT UNSIGNED NOT NULL,
 asset_id BIGINT UNSIGNED NOT NULL,
 asset_version INT UNSIGNED NOT NULL,
 reviewer_account_id BIGINT UNSIGNED NOT NULL,
 evidence_reference BINARY(16) NOT NULL,
 evidence_sha256 BINARY(32) NOT NULL,
 participant_count SMALLINT UNSIGNED NOT NULL,
 consent_count SMALLINT UNSIGNED NOT NULL,
 minor_count SMALLINT UNSIGNED NOT NULL,
 guardian_consent_count SMALLINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id),
 UNIQUE KEY uq_media_consent_review_version(asset_id,asset_version),
 KEY ix_media_consent_review_workspace(workspace_id,asset_id,id),
 CONSTRAINT fk_media_consent_review_asset FOREIGN KEY(workspace_id,asset_id) REFERENCES media_assets(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_media_consent_review_actor FOREIGN KEY(reviewer_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_media_consent_counts CHECK(participant_count BETWEEN 1 AND 1000 AND consent_count=participant_count AND minor_count<=participant_count AND guardian_consent_count=minor_count AND asset_version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_no_update'), 'Preserve consent review evidence.', "CREATE TRIGGER trg_media_consent_no_update BEFORE UPDATE ON media_consent_reviews FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Media consent reviews are append-only'"),
            new SqlMigrationStep(new MigrationStepId('003_no_delete'), 'Preserve consent review history.', "CREATE TRIGGER trg_media_consent_no_delete BEFORE DELETE ON media_consent_reviews FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Media consent reviews are append-only'"),
            new SqlMigrationStep(new MigrationStepId('004_grant_action'), 'Add only the consent review operation.', "ALTER TABLE media_governance_operations DROP CHECK ck_media_governance_action, ADD CONSTRAINT ck_media_governance_action CHECK(action_code IN ('approve','reject','withdraw-consent','hold','release-hold','remove','archive','consent-review'))"),
            new SqlMigrationStep(new MigrationStepId('005_step_up'), 'Add a distinct action-bound consent review grant.', $stepUp),
        ];
    }
}
