<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Persistence;

use PDO;
use PDOStatement;
use Qmdb\Modules\Community\Application\CommunityFeedCandidates;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

/** A bounded keyset scan; final visibility is always rechecked by the public Clip reader. */
final readonly class MySqlCommunityFeedCandidates implements CommunityFeedCandidates
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function publicProfileAlias(UuidV7 $profileId, ?int $viewerAccountId): ?string
    {
        if (!$this->connections->connection()->inTransaction()) {
            throw new \LogicException('Public profile reads require a consistent transaction.');
        }
        $statement = $this->connections->connection()->prepare(<<<'SQL'
SELECT p.alias
FROM community_profiles p
JOIN people_persons person ON person.id=p.person_id
JOIN people_account_links link ON link.person_id=p.person_id AND link.account_id=p.managing_account_id
  AND link.link_type='SELF' AND link.status='ACTIVE'
JOIN user_accounts account ON account.id=p.managing_account_id AND account.account_status='ACTIVE'
WHERE p.public_id=:profile AND p.status='ACTIVE' AND p.visibility='PUBLIC' AND p.public_consent=1
  AND person.status='ACTIVE' AND person.birth_date IS NOT NULL
  AND person.birth_date<=DATE_SUB(UTC_DATE(), INTERVAL 18 YEAR)
  AND NOT EXISTS (
    SELECT 1 FROM community_blocks block
    WHERE block.revoked_at IS NULL AND :viewer IS NOT NULL
      AND ((block.blocker_account_id=:viewer_blocker AND block.blocked_account_id=p.managing_account_id)
        OR (block.blocker_account_id=p.managing_account_id AND block.blocked_account_id=:viewer_blocked))
  )
LIMIT 1
SQL);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Could not prepare public profile read.');
        }
        $statement->execute(['profile' => $profileId->toBinary(), 'viewer' => $viewerAccountId,
            'viewer_blocker' => $viewerAccountId, 'viewer_blocked' => $viewerAccountId]);
        $alias = $statement->fetchColumn();
        return is_string($alias) ? $alias : null;
    }

    public function newest(
        ?int $viewerAccountId,
        ?string $beforeTime,
        ?int $beforeId,
        ?string $language,
        ?int $surah,
        bool $followingOnly,
        ?UuidV7 $profileId
    ): array {
        if (!$this->connections->connection()->inTransaction()) {
            throw new \LogicException('Feed candidates require a consistent transaction.');
        }
        if ($followingOnly && $viewerAccountId === null) {
            throw new \DomainException('Following feed requires authentication.');
        }
        $sql = <<<'SQL'
SELECT c.id,c.public_id,c.published_at
FROM recitation_clips c FORCE INDEX (ix_p10_clip_public_feed)
JOIN recitation_clip_public_snapshots s
  ON s.workspace_id=c.workspace_id AND s.clip_id=c.id AND s.clip_version=c.version
JOIN community_profiles p
  ON p.person_id=c.creator_person_id AND p.managing_account_id=c.creator_account_id
WHERE c.status='PUBLISHED' AND c.audience='PUBLIC'
  AND p.status='ACTIVE' AND p.visibility='PUBLIC' AND p.public_consent=1
  AND NOT EXISTS (
    SELECT 1 FROM community_mutes m
    WHERE m.muter_account_id=:viewer AND m.muted_account_id=c.creator_account_id
      AND m.revoked_at IS NULL
  )
SQL;
        $params = ['viewer' => $viewerAccountId];
        if ($followingOnly) {
            $sql .= <<<'SQL'

  AND EXISTS (
    SELECT 1 FROM community_follows f
    WHERE f.follower_account_id=:follower AND f.followed_profile_id=p.id AND f.status='ACTIVE'
  )
SQL;
            $params['follower'] = $viewerAccountId;
        }
        if ($profileId !== null) {
            $sql .= "\n  AND p.public_id=:profile";
            $params['profile'] = $profileId->toBinary();
        }
        if ($beforeTime !== null && $beforeId !== null) {
            $sql .= "\n  AND (c.published_at<:before_time OR (c.published_at=:at_time AND c.id<:before_id))";
            $params['before_time'] = $beforeTime;
            $params['at_time'] = $beforeTime;
            $params['before_id'] = $beforeId;
        }
        if ($language !== null) {
            $sql .= "\n  AND s.caption_language=:language";
            $params['language'] = $language;
        }
        if ($surah !== null) {
            $sql .= "\n  AND s.surah_number=:surah";
            $params['surah'] = $surah;
        }
        $sql .= "\nORDER BY c.published_at DESC,c.id DESC LIMIT 41";
        $statement = $this->connections->connection()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Could not prepare community feed candidate query.');
        }
        $statement->execute($params);
        $result = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (
                !is_array($row) || !is_string($row['public_id'] ?? null)
                || !is_string($row['published_at'] ?? null)
                || !preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{6}\z/', $row['published_at'])
            ) {
                throw new \UnexpectedValueException('Community feed candidate is malformed.');
            }
            $id = $row['id'] ?? null;
            if (!(is_int($id) || (is_string($id) && ctype_digit($id))) || (int) $id < 1) {
                throw new \UnexpectedValueException('Community feed candidate has no valid key.');
            }
            $result[] = ['clip_id' => UuidV7::fromBinary($row['public_id']),
                'published_at' => $row['published_at'], 'internal_id' => (int) $id];
        }
        return $result;
    }
}
