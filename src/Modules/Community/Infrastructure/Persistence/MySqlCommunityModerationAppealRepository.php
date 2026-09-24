<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use Qmdb\Modules\Community\Application\CommunityModerationAppealRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlCommunityModerationAppealRepository implements CommunityModerationAppealRepository
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function ownerEligible(int $workspaceId, int $appellantAccountId, DateTimeImmutable $since): array
    {
        $rows = $this->query(<<<'SQL'
SELECT m.public_id AS case_public_id,c.public_id AS clip_public_id,d.action_code,d.decided_at
FROM community_moderation_cases m
JOIN recitation_clips c ON c.workspace_id=m.workspace_id AND c.id=m.clip_id
JOIN community_moderation_decisions d ON d.workspace_id=m.workspace_id AND d.case_id=m.id
WHERE m.workspace_id=:workspace AND c.creator_account_id=:appellant AND m.status='ACTIONED'
 AND d.action_code IN ('HIDE','REMOVE') AND d.decided_at>=:since
 AND d.id=(SELECT MAX(latest.id) FROM community_moderation_decisions latest
   WHERE latest.workspace_id=m.workspace_id AND latest.case_id=m.id)
 AND NOT EXISTS (SELECT 1 FROM community_moderation_appeals a WHERE a.decision_id=d.id)
ORDER BY d.decided_at DESC,d.id DESC LIMIT 50
SQL, ['workspace' => $workspaceId, 'appellant' => $appellantAccountId,
            'since' => $this->time($since)])->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            if (
                !is_array($row) || !is_string($row['case_public_id'] ?? null)
                || !is_string($row['clip_public_id'] ?? null)
                || !is_string($row['action_code'] ?? null)
                || !is_string($row['decided_at'] ?? null)
            ) {
                throw new \UnexpectedValueException('Owner appeal row is malformed.');
            }
            $result[] = ['case_id' => UuidV7::fromBinary($row['case_public_id'])->toString(),
                'clip_id' => UuidV7::fromBinary($row['clip_public_id'])->toString(),
                'action' => $row['action_code'], 'decided_at' => $row['decided_at']];
        }
        return $result;
    }

    public function eligibleDecision(int $workspaceId, UuidV7 $caseId, int $appellantAccountId): ?array
    {
        $this->requireTransaction();
        $row = $this->query(<<<'SQL'
SELECT m.workspace_id,m.id AS case_id,d.id AS decision_id,c.public_id AS clip_public_id,
 d.reviewer_account_id AS decision_reviewer_id,d.decided_at
FROM community_moderation_cases m
JOIN recitation_clips c ON c.workspace_id=m.workspace_id AND c.id=m.clip_id
JOIN community_moderation_decisions d ON d.workspace_id=m.workspace_id AND d.case_id=m.id
WHERE m.workspace_id=:workspace AND m.public_id=:case AND c.creator_account_id=:appellant
 AND m.status='ACTIONED' AND d.action_code IN ('HIDE','REMOVE')
 AND d.id=(SELECT MAX(latest.id) FROM community_moderation_decisions latest
   WHERE latest.workspace_id=m.workspace_id AND latest.case_id=m.id)
 AND NOT EXISTS (SELECT 1 FROM community_moderation_appeals a WHERE a.decision_id=d.id)
FOR UPDATE
SQL, ['workspace' => $workspaceId, 'case' => $caseId->toBinary(),
            'appellant' => $appellantAccountId])->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        if (!is_string($row['clip_public_id'] ?? null) || !is_string($row['decided_at'] ?? null)) {
            throw new \UnexpectedValueException('Appealable moderation decision is malformed.');
        }
        return ['workspace_id' => $this->integer($row, 'workspace_id'),
            'case_id' => $this->integer($row, 'case_id'),
            'decision_id' => $this->integer($row, 'decision_id'),
            'clip_id' => UuidV7::fromBinary($row['clip_public_id']),
            'decision_reviewer_id' => $this->integer($row, 'decision_reviewer_id'),
            'decided_at' => new DateTimeImmutable($row['decided_at'], new DateTimeZone('UTC'))];
    }

    public function submit(array $decision, int $appellantAccountId, string $ciphertext, string $keyId, DateTimeImmutable $now): UuidV7
    {
        $this->requireTransaction();
        if (strlen($ciphertext) < 25 || strlen($ciphertext) > 4120) {
            throw new \InvalidArgumentException('Encrypted appeal statement is invalid.');
        }
        $id = UuidV7::generate();
        $this->query(<<<'SQL'
INSERT INTO community_moderation_appeals
 (public_id,workspace_id,case_id,decision_id,appellant_account_id,status,version,
 statement_nonce,statement_ciphertext,statement_key_id,submitted_at)
VALUES (:public,:workspace,:case,:decision,:appellant,'SUBMITTED',1,:nonce,:cipher,:key,:submitted)
SQL, ['public' => $id->toBinary(), 'workspace' => $decision['workspace_id'],
            'case' => $decision['case_id'], 'decision' => $decision['decision_id'],
            'appellant' => $appellantAccountId, 'nonce' => substr($ciphertext, 0, 24),
            'cipher' => substr($ciphertext, 24), 'key' => $keyId, 'submitted' => $this->time($now)]);
        return $id;
    }

    public function queue(int $workspaceId, int $reviewerAccountId): array
    {
        $rows = $this->query(<<<'SQL'
SELECT a.public_id,m.public_id AS case_public_id,c.public_id AS clip_public_id,a.submitted_at
FROM community_moderation_appeals a
JOIN community_moderation_cases m ON m.workspace_id=a.workspace_id AND m.id=a.case_id
JOIN recitation_clips c ON c.workspace_id=m.workspace_id AND c.id=m.clip_id
JOIN community_moderation_decisions d ON d.id=a.decision_id AND d.workspace_id=a.workspace_id
WHERE a.workspace_id=:workspace AND a.status='SUBMITTED'
 AND a.appellant_account_id<>:reviewer AND d.reviewer_account_id<>:original_reviewer
 AND NOT EXISTS (SELECT 1 FROM community_reports r WHERE r.workspace_id=a.workspace_id
   AND r.clip_id=m.clip_id AND r.reporter_account_id=:reporter)
ORDER BY a.submitted_at ASC,a.id ASC LIMIT 50
SQL, ['workspace' => $workspaceId, 'reviewer' => $reviewerAccountId,
            'original_reviewer' => $reviewerAccountId,
            'reporter' => $reviewerAccountId])->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            if (
                !is_array($row) || !is_string($row['public_id'] ?? null)
                || !is_string($row['case_public_id'] ?? null)
                || !is_string($row['clip_public_id'] ?? null)
                || !is_string($row['submitted_at'] ?? null)
            ) {
                throw new \UnexpectedValueException('Moderation appeal queue row is malformed.');
            }
            $result[] = ['public_id' => UuidV7::fromBinary($row['public_id'])->toString(),
                'case_id' => UuidV7::fromBinary($row['case_public_id'])->toString(),
                'clip_id' => UuidV7::fromBinary($row['clip_public_id'])->toString(),
                'submitted_at' => $row['submitted_at']];
        }
        return $result;
    }

    public function statement(int $workspaceId, UuidV7 $appealId, int $reviewerAccountId): ?array
    {
        $row = $this->query(<<<'SQL'
SELECT a.statement_nonce,a.statement_ciphertext,a.statement_key_id
FROM community_moderation_appeals a
JOIN community_moderation_cases m ON m.workspace_id=a.workspace_id AND m.id=a.case_id
JOIN community_moderation_decisions d ON d.id=a.decision_id AND d.workspace_id=a.workspace_id
WHERE a.workspace_id=:workspace AND a.public_id=:public AND a.status='SUBMITTED'
 AND a.appellant_account_id<>:reviewer AND d.reviewer_account_id<>:original_reviewer
 AND NOT EXISTS (SELECT 1 FROM community_reports r WHERE r.workspace_id=a.workspace_id
   AND r.clip_id=m.clip_id AND r.reporter_account_id=:reporter)
SQL, ['workspace' => $workspaceId, 'public' => $appealId->toBinary(),
            'reviewer' => $reviewerAccountId, 'original_reviewer' => $reviewerAccountId,
            'reporter' => $reviewerAccountId])->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        if (
            !is_string($row['statement_nonce'] ?? null)
            || !is_string($row['statement_ciphertext'] ?? null)
            || !is_string($row['statement_key_id'] ?? null)
        ) {
            throw new \UnexpectedValueException('Confidential appeal statement is malformed.');
        }
        return ['packed_ciphertext' => $row['statement_nonce'] . $row['statement_ciphertext'],
            'key_id' => $row['statement_key_id']];
    }

    public function lock(int $workspaceId, UuidV7 $appealId, int $reviewerAccountId): ?array
    {
        $this->requireTransaction();
        $row = $this->query(<<<'SQL'
SELECT a.id,a.public_id,a.workspace_id,a.case_id,a.status,a.version,a.appellant_account_id,
 m.status AS case_status,m.version AS case_version,
 d.reviewer_account_id AS decision_reviewer_id,c.public_id AS clip_public_id
FROM community_moderation_appeals a
JOIN community_moderation_cases m ON m.workspace_id=a.workspace_id AND m.id=a.case_id
JOIN recitation_clips c ON c.workspace_id=m.workspace_id AND c.id=m.clip_id
JOIN community_moderation_decisions d ON d.id=a.decision_id AND d.workspace_id=a.workspace_id
WHERE a.workspace_id=:workspace AND a.public_id=:public
 AND NOT EXISTS (SELECT 1 FROM community_reports r WHERE r.workspace_id=a.workspace_id
   AND r.clip_id=m.clip_id AND r.reporter_account_id=:reporter)
FOR UPDATE
SQL, ['workspace' => $workspaceId, 'public' => $appealId->toBinary(),
            'reporter' => $reviewerAccountId])->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        if (
            !is_string($row['public_id'] ?? null) || !is_string($row['clip_public_id'] ?? null)
            || !is_string($row['status'] ?? null) || !is_string($row['case_status'] ?? null)
        ) {
            throw new \UnexpectedValueException('Moderation appeal is malformed.');
        }
        return ['id' => $this->integer($row, 'id'),
            'public_id' => UuidV7::fromBinary($row['public_id']),
            'workspace_id' => $this->integer($row, 'workspace_id'),
            'case_id' => $this->integer($row, 'case_id'),
            'case_status' => $row['case_status'],
            'case_version' => $this->integer($row, 'case_version'),
            'clip_id' => UuidV7::fromBinary($row['clip_public_id']),
            'status' => $row['status'], 'version' => $this->integer($row, 'version'),
            'appellant_account_id' => $this->integer($row, 'appellant_account_id'),
            'decision_reviewer_id' => $this->integer($row, 'decision_reviewer_id')];
    }

    public function decide(array $appeal, int $reviewerAccountId, string $outcome, string $reasonCode, DateTimeImmutable $now): void
    {
        $this->requireTransaction();
        if ($appeal['status'] !== 'SUBMITTED' || !in_array($outcome, ['UPHELD', 'RESTORED'], true)) {
            throw new \DomainException('Appeal cannot be decided.');
        }
        $time = $this->time($now);
        $changed = $this->query(<<<'SQL'
UPDATE community_moderation_appeals SET status=:status,version=version+1,decided_at=:decided
WHERE workspace_id=:workspace AND id=:appeal AND status='SUBMITTED' AND version=:version
SQL, ['status' => $outcome, 'decided' => $time, 'workspace' => $appeal['workspace_id'],
            'appeal' => $appeal['id'], 'version' => $appeal['version']]);
        if ($changed->rowCount() !== 1) {
            throw new \DomainException('Appeal changed concurrently.');
        }
        $this->query(<<<'SQL'
INSERT INTO community_moderation_appeal_reviews
 (public_id,workspace_id,appeal_id,reviewer_account_id,outcome_code,reason_code,decided_at)
VALUES (:public,:workspace,:appeal,:reviewer,:outcome,:reason,:decided)
SQL, ['public' => UuidV7::generate()->toBinary(), 'workspace' => $appeal['workspace_id'],
            'appeal' => $appeal['id'], 'reviewer' => $reviewerAccountId,
            'outcome' => $outcome, 'reason' => $reasonCode, 'decided' => $time]);
        if ($outcome === 'RESTORED') {
            if ($appeal['case_status'] !== 'ACTIONED') {
                throw new \DomainException('Moderation case changed before appeal restoration.');
            }
            $caseUpdate = $this->query(<<<'SQL'
UPDATE community_moderation_cases SET status='CLOSED',version=version+1,updated_at=:updated,closed_at=:closed
WHERE workspace_id=:workspace AND id=:case AND status='ACTIONED' AND version=:version
SQL, ['workspace' => $appeal['workspace_id'], 'case' => $appeal['case_id'],
                'version' => $appeal['case_version'], 'updated' => $time, 'closed' => $time]);
            if ($caseUpdate->rowCount() !== 1) {
                throw new \DomainException('Moderation case changed before appeal restoration.');
            }
            $this->query(<<<'SQL'
INSERT INTO community_moderation_events
 (public_id,workspace_id,case_id,actor_account_id,event_code,case_version,created_at)
VALUES (:public,:workspace,:case,:actor,'APPEAL_RESTORED',:version,:created)
SQL, ['public' => UuidV7::generate()->toBinary(), 'workspace' => $appeal['workspace_id'],
                'case' => $appeal['case_id'], 'actor' => $reviewerAccountId,
                'version' => $appeal['case_version'] + 1, 'created' => $time]);
        }
    }

    /** @param array<string,int|string> $parameters */
    private function query(string $sql, array $parameters): PDOStatement
    {
        $statement = $this->connections->connection()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Could not prepare moderation appeal statement.');
        }
        $statement->execute($parameters);
        return $statement;
    }

    /** @param array<array-key,mixed> $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        return is_int($value) || (is_string($value) && ctype_digit($value))
            ? (int) $value : throw new \UnexpectedValueException('Moderation appeal row is malformed.');
    }

    private function requireTransaction(): void
    {
        if (!$this->connections->connection()->inTransaction()) {
            throw new \LogicException('Moderation appeal mutations require a transaction.');
        }
    }

    private function time(DateTimeImmutable $now): string
    {
        return $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
