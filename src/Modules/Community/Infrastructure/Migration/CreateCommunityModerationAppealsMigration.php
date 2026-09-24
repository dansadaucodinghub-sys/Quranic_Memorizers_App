<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** An independent, append-only review outcome for an eligible moderation action. */
final readonly class CreateCommunityModerationAppealsMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260922113000_create_community_moderation_appeals');
    }

    public function description(): string
    {
        return 'Preserve confidential, independently reviewed community moderation appeals.';
    }

    public function dependencies(): array
    {
        return [(new AddRecitationClipSupersessionConstraintMigration())->id()];
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
            new SqlMigrationStep(new MigrationStepId('001_appeals'), 'Create one protected appeal per moderation decision.', <<<'SQL'
CREATE TABLE community_moderation_appeals (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL,
 case_id BIGINT UNSIGNED NOT NULL, decision_id BIGINT UNSIGNED NOT NULL, appellant_account_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'SUBMITTED', version INT UNSIGNED NOT NULL DEFAULT 1,
 statement_ciphertext VARBINARY(4096) NOT NULL, statement_nonce BINARY(24) NOT NULL,
 statement_key_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 submitted_at DATETIME(6) NOT NULL, decided_at DATETIME(6) NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p10_appeal_public(public_id), UNIQUE KEY uq_p10_appeal_decision(decision_id),
 UNIQUE KEY uq_p10_appeal_workspace_id(workspace_id,id), KEY ix_p10_appeal_queue(workspace_id,status,submitted_at,id),
 CONSTRAINT fk_p10_appeal_case FOREIGN KEY(workspace_id,case_id) REFERENCES community_moderation_cases(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_appeal_decision FOREIGN KEY(decision_id) REFERENCES community_moderation_decisions(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_appeal_appellant FOREIGN KEY(appellant_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p10_appeal CHECK(status IN ('SUBMITTED','UPHELD','RESTORED') AND version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_reviews'), 'Preserve immutable appeal review decisions.', <<<'SQL'
CREATE TABLE community_moderation_appeal_reviews (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL,
 appeal_id BIGINT UNSIGNED NOT NULL, reviewer_account_id BIGINT UNSIGNED NOT NULL,
 outcome_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 reason_code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, decided_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p10_appeal_review_public(public_id), UNIQUE KEY uq_p10_appeal_review_once(appeal_id),
 CONSTRAINT fk_p10_appeal_review_appeal FOREIGN KEY(workspace_id,appeal_id) REFERENCES community_moderation_appeals(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_appeal_review_reviewer FOREIGN KEY(reviewer_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p10_appeal_review CHECK(outcome_code IN ('UPHELD','RESTORED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_review_immutable_update'), 'Reject appeal review changes.', <<<'SQL'
CREATE TRIGGER trg_p10_appeal_review_no_update BEFORE UPDATE ON community_moderation_appeal_reviews
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Community appeal reviews are immutable'
SQL),
            new SqlMigrationStep(new MigrationStepId('004_review_immutable_delete'), 'Reject appeal review deletion.', <<<'SQL'
CREATE TRIGGER trg_p10_appeal_review_no_delete BEFORE DELETE ON community_moderation_appeal_reviews
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Community appeal reviews are immutable'
SQL),
        ];
    }
}
