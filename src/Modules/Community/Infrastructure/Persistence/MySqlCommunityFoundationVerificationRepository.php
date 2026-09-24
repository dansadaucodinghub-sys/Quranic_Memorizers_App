<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Persistence;

use PDO;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

/** Read-only P10 schema and authorization inventory. */
final readonly class MySqlCommunityFoundationVerificationRepository
{
    private const TABLES = [
        'recitation_clips', 'recitation_clip_quran_references', 'recitation_clip_events',
        'recitation_clip_public_snapshots', 'community_profiles', 'community_profile_events',
        'community_follows', 'community_blocks', 'community_mutes',
        'community_reactions', 'community_bookmarks', 'community_comments',
        'community_comment_events', 'community_reports', 'community_moderation_cases',
        'community_moderation_assignments', 'community_moderation_decisions',
        'community_moderation_events', 'community_operation_receipts',
        'community_moderation_appeals', 'community_moderation_appeal_reviews',
        'community_notification_intents', 'community_notification_events',
        'community_global_operation_receipts', 'community_social_events',
        'community_interaction_events',
    ];

    private const MIGRATIONS = [
        '20260922100000_create_recitation_clip_foundation',
        '20260922101000_create_community_safety_foundation',
        '20260922103000_extend_community_security_vocabulary',
        '20260922104000_create_community_operation_receipts',
        '20260922105000_create_community_social_operations',
        '20260922110000_create_community_interaction_events',
        '20260922111000_add_community_feed_keyset_index',
        '20260922112000_add_clip_supersession_constraint',
        '20260922113000_create_community_moderation_appeals',
        '20260922113500_harden_community_moderation_appeal_tenant_key',
        '20260922114000_create_community_notification_intents',
    ];

    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    /** @return array{tables:int,migrations:int,permissions:int} */
    public function verify(): array
    {
        $database = $this->connections->connection();
        $tables = $database->query('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE()');
        if ($tables === false) {
            throw new \RuntimeException('P10 table inventory is unavailable.');
        }
        $found = $tables->fetchAll(PDO::FETCH_COLUMN);
        foreach (self::TABLES as $table) {
            if (!in_array($table, $found, true)) {
                throw new \RuntimeException('Required P10 table is missing: ' . $table);
            }
        }
        $migrations = $database->query("SELECT migration_id FROM qmdb_schema_migrations WHERE status='APPLIED'");
        if ($migrations === false) {
            throw new \RuntimeException('P10 migration ledger is unavailable.');
        }
        $applied = $migrations->fetchAll(PDO::FETCH_COLUMN);
        foreach (self::MIGRATIONS as $id) {
            if (!in_array($id, $applied, true)) {
                throw new \RuntimeException('Required P10 migration is not applied: ' . $id);
            }
        }
        $seed = $database->query(
            "SELECT COUNT(*) FROM qmdb_schema_seeds "
            . "WHERE seed_id='20260922102000_seed_p10_community_authorization' AND status='APPLIED'"
        );
        if ($seed === false || (int) $seed->fetchColumn() !== 1) {
            throw new \RuntimeException('P10 authorization seed is not applied.');
        }
        $permissions = $database->query(
            "SELECT COUNT(*) FROM authorization_permissions "
            . "WHERE owning_module='community.recitation_clips' AND status='ACTIVE'"
        );
        if ($permissions === false || (int) $permissions->fetchColumn() !== 6) {
            throw new \RuntimeException('P10 authorization catalog is incomplete.');
        }
        return ['tables' => count(self::TABLES), 'migrations' => count(self::MIGRATIONS), 'permissions' => 6];
    }

    public function verifyFeedQueryPlan(): string
    {
        $statement = $this->connections->connection()->prepare(<<<'SQL'
EXPLAIN SELECT id FROM recitation_clips FORCE INDEX (ix_p10_clip_public_feed)
WHERE status='PUBLISHED' AND audience='PUBLIC' ORDER BY published_at DESC,id DESC LIMIT 25
SQL);
        if (!$statement instanceof \PDOStatement) {
            throw new \RuntimeException('P10 feed query plan could not be prepared.');
        }
        $statement->execute();
        $plan = $statement->fetch(PDO::FETCH_ASSOC);
        if (
            !is_array($plan) || !is_string($plan['key'] ?? null)
            || $plan['key'] !== 'ix_p10_clip_public_feed'
        ) {
            throw new \RuntimeException('P10 feed query is not using its bounded index.');
        }

        return $plan['key'];
    }
}
