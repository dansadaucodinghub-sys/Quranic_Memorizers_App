<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use Qmdb\Modules\Community\Application\CommunityModerationRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlCommunityModerationRepository implements CommunityModerationRepository
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function queue(int $workspaceId, int $reviewerAccountId): array
    {
        $rows = $this->query(<<<'SQL'
SELECT m.public_id,c.public_id AS clip_public,media.public_id AS asset_public,
  c.status AS clip_status,m.status,m.priority_code,m.version,
  a.reviewer_account_id AS assigned_reviewer,
  (SELECT COUNT(*) FROM community_reports r WHERE r.workspace_id=m.workspace_id AND r.clip_id=m.clip_id) AS report_count
FROM community_moderation_cases m FORCE INDEX (ix_p10_case_queue)
JOIN recitation_clips c ON c.workspace_id=m.workspace_id AND c.id=m.clip_id
JOIN media_assets media ON media.workspace_id=c.workspace_id AND media.id=c.media_asset_id
LEFT JOIN community_moderation_assignments a ON a.workspace_id=m.workspace_id AND a.case_id=m.id AND a.revoked_at IS NULL
WHERE m.workspace_id=:workspace AND m.status IN ('SUBMITTED','TRIAGED','ASSIGNED','UNDER_REVIEW','ACTIONED')
  AND c.creator_account_id<>:reviewer
  AND NOT EXISTS (SELECT 1 FROM community_reports own_report WHERE own_report.workspace_id=m.workspace_id
    AND own_report.clip_id=m.clip_id AND own_report.reporter_account_id=:reporter)
ORDER BY FIELD(m.priority_code,'CHILD_SAFETY','URGENT','NORMAL'),m.created_at ASC,m.id ASC LIMIT 50
SQL, ['workspace' => $workspaceId, 'reviewer' => $reviewerAccountId,
            'reporter' => $reviewerAccountId])->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            if (
                !is_array($row) || !is_string($row['public_id'] ?? null)
                || !is_string($row['clip_public'] ?? null) || !is_string($row['asset_public'] ?? null)
                || !is_string($row['clip_status'] ?? null)
                || !is_string($row['status'] ?? null)
                || !is_string($row['priority_code'] ?? null)
            ) {
                throw new \UnexpectedValueException('Moderation queue row is malformed.');
            }
            $result[] = ['public_id' => UuidV7::fromBinary($row['public_id'])->toString(),
                'clip_id' => UuidV7::fromBinary($row['clip_public'])->toString(),
                'asset_id' => UuidV7::fromBinary($row['asset_public'])->toString(),
                'clip_status' => $row['clip_status'],
                'status' => $row['status'], 'priority' => $row['priority_code'],
                'version' => $this->integer($row, 'version'),
                'assigned_to_me' => $row['assigned_reviewer'] !== null
                    && $this->integer($row, 'assigned_reviewer') === $reviewerAccountId,
                'report_count' => $this->integer($row, 'report_count')];
        }
        return $result;
    }

    public function caseReports(int $workspaceId, UuidV7 $caseId, int $reviewerAccountId): array
    {
        $rows = $this->query(<<<'SQL'
SELECT r.reason_code,r.statement_nonce,r.statement_ciphertext,r.statement_key_id,r.created_at
FROM community_moderation_cases m
JOIN recitation_clips c ON c.workspace_id=m.workspace_id AND c.id=m.clip_id
JOIN community_reports r ON r.workspace_id=m.workspace_id AND r.clip_id=m.clip_id
WHERE m.workspace_id=:workspace AND m.public_id=:case AND c.creator_account_id<>:reviewer
  AND NOT EXISTS (SELECT 1 FROM community_reports own_report WHERE own_report.workspace_id=m.workspace_id
    AND own_report.clip_id=m.clip_id AND own_report.reporter_account_id=:reporter)
ORDER BY r.created_at ASC,r.id ASC LIMIT 50
SQL, ['workspace' => $workspaceId, 'case' => $caseId->toBinary(),
            'reviewer' => $reviewerAccountId, 'reporter' => $reviewerAccountId])->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            if (
                !is_array($row) || !is_string($row['reason_code'] ?? null)
                || !is_string($row['statement_nonce'] ?? null)
                || !is_string($row['statement_ciphertext'] ?? null)
                || !is_string($row['statement_key_id'] ?? null)
                || !is_string($row['created_at'] ?? null)
            ) {
                throw new \UnexpectedValueException('Confidential report row is malformed.');
            }
            $result[] = ['reason' => $row['reason_code'],
                'packed_ciphertext' => $row['statement_nonce'] . $row['statement_ciphertext'],
                'key_id' => $row['statement_key_id'], 'created_at' => $row['created_at']];
        }
        return $result;
    }

    public function lock(int $workspaceId, UuidV7 $caseId): ?array
    {
        $this->requireTransaction();
        $row = $this->query(
            'SELECT m.id,m.public_id,m.workspace_id,m.status,m.version,c.public_id AS clip_public_id,c.creator_account_id,a.reviewer_account_id AS assigned_reviewer_id FROM community_moderation_cases m JOIN recitation_clips c ON c.workspace_id=m.workspace_id AND c.id=m.clip_id LEFT JOIN community_moderation_assignments a ON a.case_id=m.id AND a.workspace_id=m.workspace_id AND a.revoked_at IS NULL WHERE m.workspace_id=:workspace AND m.public_id=:public FOR UPDATE',
            ['workspace' => $workspaceId, 'public' => $caseId->toBinary()],
        )->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        if (!is_string($row['public_id']) || !is_string($row['clip_public_id']) || !is_string($row['status'])) {
            throw new \UnexpectedValueException('Moderation case is malformed.');
        }
        return ['id' => $this->integer($row, 'id'), 'public_id' => UuidV7::fromBinary($row['public_id']),
            'workspace_id' => $this->integer($row, 'workspace_id'),
            'clip_public_id' => UuidV7::fromBinary($row['clip_public_id']),
            'creator_account_id' => $this->integer($row, 'creator_account_id'),
            'status' => $row['status'], 'version' => $this->integer($row, 'version'),
            'assigned_reviewer_id' => $row['assigned_reviewer_id'] === null
                ? null : $this->integer($row, 'assigned_reviewer_id')];
    }

    public function assignSelf(array $case, int $reviewerAccountId, DateTimeImmutable $now): void
    {
        $this->requireTransaction();
        if ($case['assigned_reviewer_id'] !== null || !in_array($case['status'], ['SUBMITTED', 'TRIAGED'], true)) {
            throw new \DomainException('Case cannot be assigned.');
        }
        $conflict = $this->query(
            'SELECT r.id FROM community_reports r JOIN community_moderation_cases m ON m.clip_id=r.clip_id AND m.workspace_id=r.workspace_id WHERE m.id=:case AND r.reporter_account_id=:reviewer LIMIT 1',
            ['case' => $case['id'], 'reviewer' => $reviewerAccountId],
        )->fetchColumn();
        if ($conflict !== false) {
            throw new \DomainException('Reporter cannot review their own report.');
        }
        $time = $this->time($now);
        $this->query(
            'INSERT INTO community_moderation_assignments (workspace_id,case_id,reviewer_account_id,assigned_by_account_id,assigned_at) VALUES (:workspace,:case,:reviewer,:actor,:assigned)',
            ['workspace' => $case['workspace_id'], 'case' => $case['id'],
            'reviewer' => $reviewerAccountId,
            'actor' => $reviewerAccountId,
            'assigned' => $time]
        );
        $this->advance($case, $reviewerAccountId, 'ASSIGNED', 'ASSIGNED', $time);
    }

    public function startReview(array $case, int $reviewerAccountId, DateTimeImmutable $now): void
    {
        $this->requireTransaction();
        if (
            $case['assigned_reviewer_id'] !== $reviewerAccountId
            || !in_array($case['status'], ['ASSIGNED', 'ACTIONED'], true)
        ) {
            throw new \DomainException('Case review requires the active assignment.');
        }
        $this->advance(
            $case,
            $reviewerAccountId,
            'UNDER_REVIEW',
            $case['status'] === 'ACTIONED' ? 'RESTORATION_REVIEW_STARTED' : 'REVIEW_STARTED',
            $this->time($now)
        );
    }

    public function decide(
        array $case,
        int $reviewerAccountId,
        string $action,
        string $reason,
        DateTimeImmutable $now
    ): string {
        $this->requireTransaction();
        if ($case['assigned_reviewer_id'] !== $reviewerAccountId || $case['status'] !== 'UNDER_REVIEW') {
            throw new \DomainException('Decision requires the active review assignment.');
        }
        $target = match ($action) {
            'NO_ACTION' => 'DISMISSED',
            'RESTORE' => 'CLOSED',
            default => 'ACTIONED',
        };
        $time = $this->time($now);
        $this->query(
            'INSERT INTO community_moderation_decisions (public_id,workspace_id,case_id,reviewer_account_id,action_code,reason_code,case_version,decided_at) VALUES (:public,:workspace,:case,:reviewer,:action,:reason,:version,:decided)',
            ['public' => UuidV7::generate()->toBinary(), 'workspace' => $case['workspace_id'],
                'case' => $case['id'], 'reviewer' => $reviewerAccountId, 'action' => $action,
            'reason' => $reason,
            'version' => $case['version'] + 1,
            'decided' => $time]
        );
        $this->advance($case, $reviewerAccountId, $target, 'DECIDED', $time);
        return $target;
    }

    /** @param array{id:int,workspace_id:int,version:int} $case */
    private function advance(array $case, int $actor, string $status, string $event, string $time): void
    {
        $updated = $this->query(
            'UPDATE community_moderation_cases SET status=:status,version=version+1,updated_at=:updated,closed_at=:closed WHERE workspace_id=:workspace AND id=:case AND version=:version',
            ['status' => $status, 'updated' => $time, 'workspace' => $case['workspace_id'],
            'closed' => $status === 'CLOSED' ? $time : null,
            'case' => $case['id'],
            'version' => $case['version']]
        );
        if ($updated->rowCount() !== 1) {
            throw new \DomainException('Case changed concurrently.');
        }
        $this->query(
            'INSERT INTO community_moderation_events (public_id,workspace_id,case_id,actor_account_id,event_code,case_version,created_at) VALUES (:public,:workspace,:case,:actor,:event,:version,:created)',
            ['public' => UuidV7::generate()->toBinary(), 'workspace' => $case['workspace_id'],
                'case' => $case['id'], 'actor' => $actor, 'event' => $event,
            'version' => $case['version'] + 1,
            'created' => $time]
        );
    }

    /** @param array<string,int|string|null> $parameters */
    private function query(string $sql, array $parameters): PDOStatement
    {
        $statement = $this->connections->connection()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Could not prepare moderation statement.');
        }
        $statement->execute($parameters);
        return $statement;
    }

    /** @param array<array-key,mixed> $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        return is_int($value) || (is_string($value) && ctype_digit($value))
            ? (int) $value : throw new \UnexpectedValueException('Moderation case is malformed.');
    }

    private function requireTransaction(): void
    {
        if (!$this->connections->connection()->inTransaction()) {
            throw new \LogicException('Moderation mutations require a transaction.');
        }
    }

    private function time(DateTimeImmutable $now): string
    {
        return $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
