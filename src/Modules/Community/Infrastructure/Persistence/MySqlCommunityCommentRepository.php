<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use Qmdb\Modules\Community\Application\CommunityCommentRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlCommunityCommentRepository implements CommunityCommentRepository
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function heldQueue(int $workspaceId, int $reviewerAccountId): array
    {
        $rows = $this->query(<<<'SQL'
SELECT comment.public_id,clip.public_id AS clip_public_id,comment.body,comment.version,comment.created_at
FROM community_comments comment
JOIN recitation_clips clip ON clip.workspace_id=comment.workspace_id AND clip.id=comment.clip_id
WHERE comment.workspace_id=:workspace AND comment.status='MODERATION_HELD'
 AND comment.actor_account_id<>:reviewer AND clip.creator_account_id<>:creator
ORDER BY comment.created_at ASC,comment.id ASC LIMIT 50
SQL, ['workspace' => $workspaceId, 'reviewer' => $reviewerAccountId,
            'creator' => $reviewerAccountId])->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            if (
                !is_array($row) || !is_string($row['public_id'] ?? null)
                || !is_string($row['clip_public_id'] ?? null) || !is_string($row['body'] ?? null)
                || !is_string($row['created_at'] ?? null)
            ) {
                throw new \UnexpectedValueException('Held comment row is malformed.');
            }
            $result[] = ['public_id' => UuidV7::fromBinary($row['public_id'])->toString(),
                'clip_id' => UuidV7::fromBinary($row['clip_public_id'])->toString(),
                'body' => $row['body'], 'version' => $this->integer($row, 'version'),
                'created_at' => $row['created_at']];
        }
        return $result;
    }

    public function moderateHeld(
        int $workspaceId,
        UuidV7 $commentId,
        int $reviewerAccountId,
        string $action,
        int $expectedVersion,
        DateTimeImmutable $now,
    ): array {
        $this->requireTransaction();
        if (!in_array($action, ['APPROVE', 'REMOVE'], true)) {
            throw new \InvalidArgumentException('Comment moderation action is invalid.');
        }
        $row = $this->query(<<<'SQL'
SELECT comment.id,comment.actor_account_id,comment.body,comment.status,comment.version,clip.creator_account_id
FROM community_comments comment
JOIN recitation_clips clip ON clip.workspace_id=comment.workspace_id AND clip.id=comment.clip_id
WHERE comment.workspace_id=:workspace AND comment.public_id=:public FOR UPDATE
SQL, ['workspace' => $workspaceId, 'public' => $commentId->toBinary()])->fetch(PDO::FETCH_ASSOC);
        if (
            !is_array($row) || $row['status'] !== 'MODERATION_HELD'
            || $this->integer($row, 'version') !== $expectedVersion
            || $this->integer($row, 'actor_account_id') === $reviewerAccountId
            || $this->integer($row, 'creator_account_id') === $reviewerAccountId
            || !is_string($row['body'] ?? null)
        ) {
            throw new \DomainException('Held comment is unavailable for independent review.');
        }
        $status = $action === 'APPROVE' ? 'VISIBLE' : 'REMOVED';
        $body = $action === 'APPROVE' ? $row['body'] : '[removed]';
        $time = $this->time($now);
        $changed = $this->query(<<<'SQL'
UPDATE community_comments SET body=:body,status=:status,version=version+1,updated_at=:updated
WHERE workspace_id=:workspace AND id=:comment AND status='MODERATION_HELD' AND version=:version
SQL, ['body' => $body, 'status' => $status, 'updated' => $time,
            'workspace' => $workspaceId, 'comment' => $this->integer($row, 'id'),
            'version' => $expectedVersion]);
        if ($changed->rowCount() !== 1) {
            throw new \DomainException('Held comment changed concurrently.');
        }
        $this->event(
            $workspaceId,
            $this->integer($row, 'id'),
            $reviewerAccountId,
            'MODERATION_' . $action,
            $row['body'],
            $expectedVersion + 1,
            $time
        );
        return ['public_id' => $commentId->toString(), 'status' => $status,
            'version' => $expectedVersion + 1];
    }

    public function visible(int $workspaceId, UuidV7 $clipId, ?int $viewerAccountId): array
    {
        $this->requireTransaction();
        $rows = $this->query(
            "SELECT c.public_id,p.public_id AS parent_public_id,c.body,c.status,c.version,c.created_at,c.actor_account_id
             FROM community_comments c
             JOIN recitation_clips clip ON clip.workspace_id=c.workspace_id AND clip.id=c.clip_id
             LEFT JOIN community_comments p ON p.workspace_id=c.workspace_id AND p.id=c.parent_comment_id
             WHERE clip.workspace_id=:workspace AND clip.public_id=:clip
               AND (c.status IN ('VISIBLE','EDITED')
                    OR (c.status='MODERATION_HELD' AND c.actor_account_id=:viewer))
               AND EXISTS (
                   SELECT 1 FROM user_accounts author
                   JOIN people_account_links link ON link.account_id=author.id
                     AND link.link_type='SELF' AND link.status='ACTIVE'
                   JOIN people_persons person ON person.id=link.person_id AND person.status='ACTIVE'
                   WHERE author.id=c.actor_account_id AND author.account_status='ACTIVE'
                     AND person.birth_date IS NOT NULL
                     AND person.birth_date<=DATE_SUB(UTC_DATE(), INTERVAL 18 YEAR)
               )
               AND NOT EXISTS (
                   SELECT 1 FROM community_blocks safety
                   WHERE safety.revoked_at IS NULL
                     AND ((safety.blocker_account_id=clip.creator_account_id
                       AND safety.blocked_account_id=c.actor_account_id)
                       OR (safety.blocker_account_id=c.actor_account_id
                         AND safety.blocked_account_id=clip.creator_account_id))
               )
               AND (:viewer_present IS NULL OR NOT EXISTS (
                   SELECT 1 FROM community_blocks viewer_safety
                   WHERE viewer_safety.revoked_at IS NULL
                     AND ((viewer_safety.blocker_account_id=:viewer_blocker
                       AND viewer_safety.blocked_account_id=c.actor_account_id)
                       OR (viewer_safety.blocker_account_id=c.actor_account_id
                         AND viewer_safety.blocked_account_id=:viewer_blocked))
               ))
             ORDER BY c.created_at,c.id LIMIT 100",
            ['workspace' => $workspaceId, 'clip' => $clipId->toBinary(), 'viewer' => $viewerAccountId,
                'viewer_present' => $viewerAccountId, 'viewer_blocker' => $viewerAccountId,
                'viewer_blocked' => $viewerAccountId]
        )->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            if (
                !is_array($row) || !is_string($row['public_id'] ?? null)
                || !is_string($row['body'] ?? null) || !is_string($row['status'] ?? null)
                || !is_string($row['created_at'] ?? null)
            ) {
                throw new \UnexpectedValueException('Comment projection is malformed.');
            }
            $parent = $row['parent_public_id'] ?? null;
            $result[] = [
                'public_id' => UuidV7::fromBinary($row['public_id'])->toString(),
                'parent_id' => is_string($parent) ? UuidV7::fromBinary($parent)->toString() : null,
                'body' => $row['body'],
                'status' => $row['status'],
                'version' => $this->integer($row, 'version'),
                'created_at' => $row['created_at'],
                'is_mine' => $viewerAccountId !== null
                    && $this->integer($row, 'actor_account_id') === $viewerAccountId,
            ];
        }
        return $result;
    }

    public function create(
        int $workspaceId,
        UuidV7 $clipId,
        int $actorId,
        ?UuidV7 $parentId,
        string $body,
        DateTimeImmutable $now
    ): array {
        $this->requireTransaction();
        $clip = $this->clip($workspaceId, $clipId);
        if ($clip['comment_policy'] === 'DISABLED') {
            throw new \DomainException('Clip comments are unavailable.');
        }
        $parent = null;
        if ($parentId !== null) {
            $parent = $this->query(
                'SELECT id,parent_comment_id,status FROM community_comments WHERE workspace_id=:workspace AND clip_id=:clip AND public_id=:public FOR UPDATE',
                ['workspace' => $workspaceId, 'clip' => $clip['id'], 'public' => $parentId->toBinary()]
            )->fetch(PDO::FETCH_ASSOC);
            if (
                !is_array($parent) || $parent['parent_comment_id'] !== null
                || !in_array($parent['status'], ['VISIBLE', 'EDITED'], true)
            ) {
                throw new \DomainException('Reply target is unavailable.');
            }
        }
        $commentId = UuidV7::generate();
        $status = $clip['comment_policy'] === 'REVIEW' ? 'MODERATION_HELD' : 'VISIBLE';
        $time = $this->time($now);
        $this->query(
            'INSERT INTO community_comments (public_id,workspace_id,clip_id,parent_comment_id,actor_account_id,body,status,version,created_at,updated_at) VALUES (:public,:workspace,:clip,:parent,:actor,:body,:status,1,:created,:updated)',
            ['public' => $commentId->toBinary(), 'workspace' => $workspaceId,
                'clip' => $clip['id'], 'parent' => is_array($parent) ? $this->integer($parent, 'id') : null,
                'actor' => $actorId, 'body' => $body, 'status' => $status,
            'created' => $time,
            'updated' => $time]
        );
        $internalId = (int) $this->connections->connection()->lastInsertId();
        $this->event($workspaceId, $internalId, $actorId, 'CREATED', null, 1, $time);
        return ['public_id' => $commentId->toString(), 'status' => $status, 'version' => 1];
    }

    public function transition(
        int $workspaceId,
        UuidV7 $clipId,
        UuidV7 $commentId,
        int $actorId,
        string $action,
        int $expectedVersion,
        string $body,
        DateTimeImmutable $now
    ): array {
        $this->requireTransaction();
        $clip = $this->clip($workspaceId, $clipId);
        $row = $this->query(
            'SELECT id,actor_account_id,body,status,version,created_at FROM community_comments WHERE workspace_id=:workspace AND clip_id=:clip AND public_id=:public FOR UPDATE',
            ['workspace' => $workspaceId, 'clip' => $clip['id'], 'public' => $commentId->toBinary()]
        )->fetch(PDO::FETCH_ASSOC);
        if (
            !is_array($row) || $this->integer($row, 'actor_account_id') !== $actorId
            || $this->integer($row, 'version') !== $expectedVersion
            || !in_array($row['status'], ['VISIBLE', 'EDITED', 'MODERATION_HELD'], true)
        ) {
            throw new \DomainException('Comment changed or is unavailable.');
        }
        if ($action === 'EDIT') {
            if (!is_string($row['created_at']) || $now->getTimestamp() > (new DateTimeImmutable($row['created_at'], new DateTimeZone('UTC')))->getTimestamp() + 1800) {
                throw new \DomainException('Comment editing window has closed.');
            }
            $status = $row['status'] === 'MODERATION_HELD' ? 'MODERATION_HELD' : 'EDITED';
            $newBody = $body;
        } elseif ($action === 'REMOVE') {
            $status = 'REMOVED';
            $newBody = '[removed]';
        } else {
            throw new \InvalidArgumentException('Comment action is invalid.');
        }
        if (!is_string($row['body'])) {
            throw new \UnexpectedValueException('Comment body is malformed.');
        }
        $time = $this->time($now);
        $updated = $this->query(
            'UPDATE community_comments SET body=:body,status=:status,version=version+1,updated_at=:updated WHERE workspace_id=:workspace AND id=:comment AND version=:version',
            ['body' => $newBody, 'status' => $status, 'updated' => $time,
                'workspace' => $workspaceId, 'comment' => $this->integer($row, 'id'),
            'version' => $expectedVersion]
        );
        if ($updated->rowCount() !== 1) {
            throw new \DomainException('Comment changed concurrently.');
        }
        $this->event(
            $workspaceId,
            $this->integer($row, 'id'),
            $actorId,
            $action === 'EDIT' ? 'EDITED' : 'REMOVED',
            $row['body'],
            $expectedVersion + 1,
            $time
        );
        return ['public_id' => $commentId->toString(), 'status' => $status,
            'version' => $expectedVersion + 1];
    }

    /** @return array{id:int,comment_policy:string} */
    private function clip(int $workspaceId, UuidV7 $clipId): array
    {
        $row = $this->query(
            "SELECT id,comment_policy FROM recitation_clips WHERE workspace_id=:workspace AND public_id=:public AND status='PUBLISHED' FOR UPDATE",
            ['workspace' => $workspaceId, 'public' => $clipId->toBinary()]
        )->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !is_string($row['comment_policy'])) {
            throw new \DomainException('Clip is unavailable.');
        }
        return ['id' => $this->integer($row, 'id'), 'comment_policy' => $row['comment_policy']];
    }

    private function event(
        int $workspaceId,
        int $commentId,
        int $actorId,
        string $code,
        ?string $priorBody,
        int $version,
        string $time
    ): void {
        $this->query(
            'INSERT INTO community_comment_events (public_id,workspace_id,comment_id,actor_account_id,event_code,prior_body,comment_version,created_at) VALUES (:public,:workspace,:comment,:actor,:code,:prior,:version,:created)',
            ['public' => UuidV7::generate()->toBinary(), 'workspace' => $workspaceId,
                'comment' => $commentId, 'actor' => $actorId, 'code' => $code,
            'prior' => $priorBody,
            'version' => $version,
            'created' => $time]
        );
    }

    /** @param array<string,int|string|null> $parameters */
    private function query(string $sql, array $parameters): PDOStatement
    {
        $statement = $this->connections->connection()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Could not prepare comment statement.');
        }
        $statement->execute($parameters);
        return $statement;
    }

    /** @param array<array-key,mixed> $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        return is_int($value) || (is_string($value) && ctype_digit($value))
            ? (int) $value : throw new \UnexpectedValueException('Comment row is malformed.');
    }

    private function requireTransaction(): void
    {
        if (!$this->connections->connection()->inTransaction()) {
            throw new \LogicException('Comment mutation requires a transaction.');
        }
    }

    private function time(DateTimeImmutable $now): string
    {
        return $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
