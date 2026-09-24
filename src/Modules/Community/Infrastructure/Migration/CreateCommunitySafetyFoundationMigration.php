<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** Forward-only P10 social and confidential safety state; no P9/P4 mutation. */
final readonly class CreateCommunitySafetyFoundationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260922101000_create_community_safety_foundation');
    }
    public function description(): string
    {
        return 'Create bounded community profiles, interactions, confidential reports, and moderation cases.';
    }
    public function dependencies(): array
    {
        return [(new CreateRecitationClipFoundationMigration())->id()];
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
            new SqlMigrationStep(new MigrationStepId('001_profiles'), 'Create private-by-default profiles.', <<<'SQL'
CREATE TABLE community_profiles (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, person_id BIGINT UNSIGNED NOT NULL, managing_account_id BIGINT UNSIGNED NOT NULL,
 alias VARCHAR(80) NOT NULL, visibility VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'PRIVATE',
 status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'ACTIVE', public_consent TINYINT NOT NULL DEFAULT 0,
 version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p10_profile_public(public_id), UNIQUE KEY uq_p10_profile_person(person_id),
 KEY ix_p10_profile_visibility(status,visibility,id),
 CONSTRAINT fk_p10_profile_person FOREIGN KEY(person_id) REFERENCES people_persons(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_profile_manager FOREIGN KEY(managing_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p10_profile CHECK(visibility IN ('PRIVATE','FOLLOWERS','PUBLIC') AND status IN ('ACTIVE','SUSPENDED','ARCHIVED') AND public_consent IN (0,1) AND version>=1 AND CHAR_LENGTH(alias) BETWEEN 1 AND 80)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_profile_events'), 'Append-only profile history.', <<<'SQL'
CREATE TABLE community_profile_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, profile_id BIGINT UNSIGNED NOT NULL, actor_account_id BIGINT UNSIGNED NOT NULL,
 event_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, profile_version INT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p10_profile_event_public(public_id), UNIQUE KEY uq_p10_profile_event_version(profile_id,profile_version),
 CONSTRAINT fk_p10_profile_event_profile FOREIGN KEY(profile_id) REFERENCES community_profiles(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_profile_event_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_follows'), 'Create private follow relationships and requests.', <<<'SQL'
CREATE TABLE community_follows (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, follower_account_id BIGINT UNSIGNED NOT NULL, followed_profile_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p10_follow_public(public_id), UNIQUE KEY uq_p10_follow_pair(follower_account_id,followed_profile_id),
 KEY ix_p10_follow_profile(followed_profile_id,status,id),
 CONSTRAINT fk_p10_follow_account FOREIGN KEY(follower_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_follow_profile FOREIGN KEY(followed_profile_id) REFERENCES community_profiles(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p10_follow CHECK(status IN ('PENDING','ACTIVE','DECLINED','REVOKED') AND version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('004_blocks'), 'Create private unilateral blocks.', <<<'SQL'
CREATE TABLE community_blocks (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, blocker_account_id BIGINT UNSIGNED NOT NULL, blocked_account_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL, revoked_at DATETIME(6) NULL,
 active_blocked BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN revoked_at IS NULL THEN blocked_account_id ELSE NULL END) STORED,
 PRIMARY KEY(id), UNIQUE KEY uq_p10_active_block(blocker_account_id,active_blocked),
 KEY ix_p10_block_reverse(blocked_account_id,revoked_at,blocker_account_id),
 CONSTRAINT fk_p10_block_owner FOREIGN KEY(blocker_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_block_target FOREIGN KEY(blocked_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p10_block_self CHECK(blocker_account_id<>blocked_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('005_mutes'), 'Create private feed mutes.', <<<'SQL'
CREATE TABLE community_mutes (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, muter_account_id BIGINT UNSIGNED NOT NULL, muted_account_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL, revoked_at DATETIME(6) NULL,
 active_muted BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN revoked_at IS NULL THEN muted_account_id ELSE NULL END) STORED,
 PRIMARY KEY(id), UNIQUE KEY uq_p10_active_mute(muter_account_id,active_muted),
 CONSTRAINT fk_p10_mute_owner FOREIGN KEY(muter_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_mute_target FOREIGN KEY(muted_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p10_mute_self CHECK(muter_account_id<>muted_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('006_reactions'), 'Create bounded current Clip reactions.', <<<'SQL'
CREATE TABLE community_reactions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, workspace_id BIGINT UNSIGNED NOT NULL, clip_id BIGINT UNSIGNED NOT NULL, actor_account_id BIGINT UNSIGNED NOT NULL,
 reaction_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, created_at DATETIME(6) NOT NULL, revoked_at DATETIME(6) NULL,
 active_actor BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN revoked_at IS NULL THEN actor_account_id ELSE NULL END) STORED,
 PRIMARY KEY(id), UNIQUE KEY uq_p10_reaction_current(clip_id,active_actor), KEY ix_p10_reaction_count(workspace_id,clip_id,revoked_at),
 CONSTRAINT fk_p10_reaction_clip FOREIGN KEY(workspace_id,clip_id) REFERENCES recitation_clips(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_reaction_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p10_reaction CHECK(reaction_code IN ('APPRECIATE'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('007_bookmarks'), 'Create private Clip bookmarks.', <<<'SQL'
CREATE TABLE community_bookmarks (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, workspace_id BIGINT UNSIGNED NOT NULL, clip_id BIGINT UNSIGNED NOT NULL, actor_account_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL, revoked_at DATETIME(6) NULL,
 active_actor BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN revoked_at IS NULL THEN actor_account_id ELSE NULL END) STORED,
 PRIMARY KEY(id), UNIQUE KEY uq_p10_bookmark_current(clip_id,active_actor), KEY ix_p10_bookmark_private(actor_account_id,revoked_at,id),
 CONSTRAINT fk_p10_bookmark_clip FOREIGN KEY(workspace_id,clip_id) REFERENCES recitation_clips(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_bookmark_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('008_comments'), 'Create bounded, versioned Clip comments.', <<<'SQL'
CREATE TABLE community_comments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL,
 clip_id BIGINT UNSIGNED NOT NULL, parent_comment_id BIGINT UNSIGNED NULL, actor_account_id BIGINT UNSIGNED NOT NULL,
 body VARCHAR(2000) NOT NULL, status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'VISIBLE',
 version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p10_comment_public(public_id), UNIQUE KEY uq_p10_comment_workspace_id(workspace_id,id),
 KEY ix_p10_comments_clip(workspace_id,clip_id,status,created_at,id),
 CONSTRAINT fk_p10_comment_clip FOREIGN KEY(workspace_id,clip_id) REFERENCES recitation_clips(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_comment_parent FOREIGN KEY(workspace_id,parent_comment_id) REFERENCES community_comments(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_comment_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p10_comment CHECK(status IN ('VISIBLE','EDITED','HIDDEN','REMOVED','MODERATION_HELD') AND version>=1 AND CHAR_LENGTH(body) BETWEEN 1 AND 2000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('009_comment_events'), 'Preserve immutable comment bodies across edits.', <<<'SQL'
CREATE TABLE community_comment_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL,
 comment_id BIGINT UNSIGNED NOT NULL, actor_account_id BIGINT UNSIGNED NOT NULL, event_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 prior_body VARCHAR(2000) NULL, comment_version INT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p10_comment_event_public(public_id), UNIQUE KEY uq_p10_comment_event_version(comment_id,comment_version),
 CONSTRAINT fk_p10_comment_event_comment FOREIGN KEY(workspace_id,comment_id) REFERENCES community_comments(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_comment_event_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('010_reports'), 'Create confidential, deduplicated Clip reports.', <<<'SQL'
CREATE TABLE community_reports (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL,
 clip_id BIGINT UNSIGNED NOT NULL, reporter_account_id BIGINT UNSIGNED NOT NULL, reason_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 statement_ciphertext VARBINARY(4096) NULL, statement_nonce BINARY(24) NULL, statement_key_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'SUBMITTED', version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p10_report_public(public_id), UNIQUE KEY uq_p10_report_actor_target(clip_id,reporter_account_id),
 KEY ix_p10_report_queue(workspace_id,status,created_at,id),
 CONSTRAINT fk_p10_report_clip FOREIGN KEY(workspace_id,clip_id) REFERENCES recitation_clips(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_report_actor FOREIGN KEY(reporter_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p10_report CHECK(reason_code IN ('CHILD_SAFETY','HARASSMENT','HATE','PRIVACY','RIGHTS','MISLEADING_REFERENCE','OTHER') AND status IN ('SUBMITTED','TRIAGED','UNDER_REVIEW','ACTIONED','DISMISSED','CLOSED') AND version>=1 AND ((statement_ciphertext IS NULL AND statement_nonce IS NULL AND statement_key_id IS NULL) OR (statement_ciphertext IS NOT NULL AND statement_nonce IS NOT NULL AND statement_key_id IS NOT NULL)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('011_cases'), 'Create one confidential moderation case per Clip.', <<<'SQL'
CREATE TABLE community_moderation_cases (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, clip_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'SUBMITTED', priority_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'NORMAL',
 version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, closed_at DATETIME(6) NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p10_case_public(public_id), UNIQUE KEY uq_p10_case_clip(clip_id), UNIQUE KEY uq_p10_case_workspace_id(workspace_id,id),
 KEY ix_p10_case_queue(workspace_id,status,priority_code,created_at,id),
 CONSTRAINT fk_p10_case_clip FOREIGN KEY(workspace_id,clip_id) REFERENCES recitation_clips(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p10_case CHECK(status IN ('SUBMITTED','TRIAGED','ASSIGNED','UNDER_REVIEW','ACTIONED','DISMISSED','CLOSED') AND priority_code IN ('NORMAL','URGENT','CHILD_SAFETY') AND version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('012_assignments'), 'Record moderation assignments.', <<<'SQL'
CREATE TABLE community_moderation_assignments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, workspace_id BIGINT UNSIGNED NOT NULL, case_id BIGINT UNSIGNED NOT NULL,
 reviewer_account_id BIGINT UNSIGNED NOT NULL, assigned_by_account_id BIGINT UNSIGNED NOT NULL, assigned_at DATETIME(6) NOT NULL, revoked_at DATETIME(6) NULL,
 active_case BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN revoked_at IS NULL THEN case_id ELSE NULL END) STORED,
 PRIMARY KEY(id), UNIQUE KEY uq_p10_case_assignment_current(active_case), KEY ix_p10_assignment_reviewer(reviewer_account_id,revoked_at,id),
 CONSTRAINT fk_p10_assignment_case FOREIGN KEY(workspace_id,case_id) REFERENCES community_moderation_cases(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_assignment_reviewer FOREIGN KEY(reviewer_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_assignment_actor FOREIGN KEY(assigned_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('013_decisions'), 'Preserve append-only governed moderation decisions.', <<<'SQL'
CREATE TABLE community_moderation_decisions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL,
 case_id BIGINT UNSIGNED NOT NULL, reviewer_account_id BIGINT UNSIGNED NOT NULL, action_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 reason_code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, case_version INT UNSIGNED NOT NULL,
 decided_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p10_decision_public(public_id), UNIQUE KEY uq_p10_decision_case_version(case_id,case_version),
 CONSTRAINT fk_p10_decision_case FOREIGN KEY(workspace_id,case_id) REFERENCES community_moderation_cases(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_decision_reviewer FOREIGN KEY(reviewer_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p10_decision CHECK(action_code IN ('NO_ACTION','HIDE','REMOVE','RESTORE','RESTRICT_INTERACTIONS','SUSPEND_PUBLICATION','ESCALATE') AND case_version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('014_case_events'), 'Preserve append-only moderation case history.', <<<'SQL'
CREATE TABLE community_moderation_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL,
 case_id BIGINT UNSIGNED NOT NULL, actor_account_id BIGINT UNSIGNED NOT NULL, event_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 case_version INT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p10_case_event_public(public_id), UNIQUE KEY uq_p10_case_event_version(case_id,case_version),
 CONSTRAINT fk_p10_case_event_case FOREIGN KEY(workspace_id,case_id) REFERENCES community_moderation_cases(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p10_case_event_actor FOREIGN KEY(actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            ...$this->immutability('community_profile_events', 'profile', 15),
            ...$this->immutability('community_comment_events', 'comment', 17),
            ...$this->immutability('community_moderation_decisions', 'decision', 19),
            ...$this->immutability('community_moderation_events', 'case_event', 21),
        ];
    }

    /** @return list<SqlMigrationStep> */
    private function immutability(string $table, string $name, int $step): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId(sprintf('%03d_%s_no_update', $step, $name)), 'Forbid evidence update.', "CREATE TRIGGER trg_p10_{$name}_no_update BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='P10 evidence is append-only'"),
            new SqlMigrationStep(new MigrationStepId(sprintf('%03d_%s_no_delete', $step + 1, $name)), 'Forbid evidence deletion.', "CREATE TRIGGER trg_p10_{$name}_no_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='P10 evidence is append-only'"),
        ];
    }
}
