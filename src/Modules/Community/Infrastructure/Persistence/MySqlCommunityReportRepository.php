<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use Qmdb\Modules\Community\Application\CommunityReportRepository;
use Qmdb\Modules\Community\Application\RecitationClipRepository;
use Qmdb\Modules\Community\Domain\ClipStatus;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlCommunityReportRepository implements CommunityReportRepository
{
    public function __construct(
        private DatabaseConnectionProvider $connections,
        private RecitationClipRepository $clips
    ) {
    }

    public function visibleTarget(UuidV7 $clipId, int $reporterAccountId): array
    {
        $this->requireTransaction();
        $row = $this->query(
            'SELECT c.workspace_id,c.creator_account_id,w.public_id AS workspace_public_id FROM recitation_clips c JOIN workspaces w ON w.id=c.workspace_id WHERE c.public_id=:public',
            ['public' => $clipId->toBinary()],
        )->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !is_string($row['workspace_public_id'])) {
            throw new \DomainException('Clip is unavailable.');
        }
        $workspace = $this->integer($row, 'workspace_id');
        $creator = $this->integer($row, 'creator_account_id');
        $clip = $this->clips->lock($workspace, $clipId);
        if ($clip === null || $clip->status !== ClipStatus::PUBLISHED || $clip->audience !== 'PUBLIC') {
            throw new \DomainException('Clip is unavailable.');
        }
        $this->clips->publicationEvidence($clip)->assertPubliclyEligible();
        $block = $this->query(
            'SELECT id FROM community_blocks WHERE revoked_at IS NULL AND ((blocker_account_id=:reporter AND blocked_account_id=:creator) OR (blocker_account_id=:creator2 AND blocked_account_id=:reporter2)) LIMIT 1',
            ['reporter' => $reporterAccountId, 'creator' => $creator,
                'creator2' => $creator, 'reporter2' => $reporterAccountId],
        )->fetchColumn();
        if ($block !== false) {
            throw new \DomainException('Clip is unavailable.');
        }
        return ['workspace_id' => $workspace,
            'workspace_public_id' => UuidV7::fromBinary($row['workspace_public_id'])->toString(),
            'clip_id' => $clip->internalId, 'creator_account_id' => $creator];
    }

    public function submit(
        array $target,
        int $reporterAccountId,
        string $reasonCode,
        string $packedCiphertext,
        string $keyId,
        DateTimeImmutable $now
    ): array {
        $this->requireTransaction();
        $existing = $this->query(
            'SELECT id FROM community_reports WHERE clip_id=:clip AND reporter_account_id=:reporter FOR UPDATE',
            ['clip' => $target['clip_id'], 'reporter' => $reporterAccountId]
        )->fetchColumn();
        if ($existing !== false) {
            throw new \DomainException('A report for this content is already recorded.');
        }
        if (strlen($packedCiphertext) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES || strlen($packedCiphertext) > 4096) {
            throw new \InvalidArgumentException('Encrypted report statement is invalid.');
        }
        $id = UuidV7::generate();
        $time = $this->time($now);
        $this->query(
            "INSERT INTO community_reports (public_id,workspace_id,clip_id,reporter_account_id,reason_code,statement_ciphertext,statement_nonce,statement_key_id,status,version,created_at,updated_at) VALUES (:public,:workspace,:clip,:reporter,:reason,:cipher,:nonce,:key,'SUBMITTED',1,:created,:updated)",
            ['public' => $id->toBinary(), 'workspace' => $target['workspace_id'],
                'clip' => $target['clip_id'], 'reporter' => $reporterAccountId,
                'reason' => $reasonCode,
                'cipher' => substr($packedCiphertext, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
                'nonce' => substr($packedCiphertext, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
                'key' => $keyId, 'created' => $time, 'updated' => $time],
        );
        $priority = $reasonCode === 'CHILD_SAFETY' ? 'CHILD_SAFETY' : 'NORMAL';
        $created = $this->query(
            "INSERT IGNORE INTO community_moderation_cases (public_id,workspace_id,clip_id,status,priority_code,version,created_at,updated_at) VALUES (:public,:workspace,:clip,'SUBMITTED',:priority,1,:created,:updated)",
            ['public' => UuidV7::generate()->toBinary(), 'workspace' => $target['workspace_id'],
                'clip' => $target['clip_id'], 'priority' => $priority, 'created' => $time, 'updated' => $time],
        );
        if ($created->rowCount() === 1) {
            $caseId = (int) $this->connections->connection()->lastInsertId();
            $this->query(
                "INSERT INTO community_moderation_events (public_id,workspace_id,case_id,actor_account_id,event_code,case_version,created_at) VALUES (:public,:workspace,:case,:actor,'SUBMITTED',1,:created)",
                ['public' => UuidV7::generate()->toBinary(), 'workspace' => $target['workspace_id'],
                    'case' => $caseId, 'actor' => $reporterAccountId, 'created' => $time],
            );
        } else {
            $case = $this->query(
                'SELECT id,status,version,priority_code FROM community_moderation_cases WHERE workspace_id=:workspace AND clip_id=:clip FOR UPDATE',
                ['workspace' => $target['workspace_id'], 'clip' => $target['clip_id']]
            )->fetch(PDO::FETCH_ASSOC);
            if (!is_array($case)) {
                throw new \UnexpectedValueException('Existing moderation case is unavailable.');
            }
            if (
                !is_string($case['priority_code'])
                || !in_array($case['priority_code'], ['NORMAL', 'URGENT', 'CHILD_SAFETY'], true)
            ) {
                throw new \UnexpectedValueException('Existing moderation priority is malformed.');
            }
            $caseId = $this->integer($case, 'id');
            if (in_array($case['status'], ['ACTIONED', 'DISMISSED', 'CLOSED'], true)) {
                $this->query(
                    'UPDATE community_moderation_assignments SET revoked_at=:revoked WHERE workspace_id=:workspace AND case_id=:case AND revoked_at IS NULL',
                    ['revoked' => $time, 'workspace' => $target['workspace_id'], 'case' => $caseId]
                );
                $this->query(
                    "UPDATE community_moderation_cases SET status='SUBMITTED',priority_code=:priority,version=version+1,closed_at=NULL,updated_at=:updated WHERE workspace_id=:workspace AND id=:case AND version=:version",
                    ['priority' => $reasonCode === 'CHILD_SAFETY' ? 'CHILD_SAFETY' : $case['priority_code'],
                        'updated' => $time, 'workspace' => $target['workspace_id'],
                        'case' => $caseId, 'version' => $this->integer($case, 'version')]
                );
                $this->query(
                    "INSERT INTO community_moderation_events (public_id,workspace_id,case_id,actor_account_id,event_code,case_version,created_at) VALUES (:public,:workspace,:case,:actor,'REOPENED',:version,:created)",
                    ['public' => UuidV7::generate()->toBinary(), 'workspace' => $target['workspace_id'],
                        'case' => $caseId, 'actor' => $reporterAccountId,
                        'version' => $this->integer($case, 'version') + 1, 'created' => $time]
                );
            } elseif ($reasonCode === 'CHILD_SAFETY' && $case['priority_code'] !== 'CHILD_SAFETY') {
                $this->query(
                    "UPDATE community_moderation_cases SET priority_code='CHILD_SAFETY',updated_at=:time WHERE workspace_id=:workspace AND id=:case",
                    ['time' => $time, 'workspace' => $target['workspace_id'], 'case' => $caseId]
                );
            }
        }
        return ['public_id' => $id->toString(), 'status' => 'SUBMITTED', 'version' => 1];
    }

    /** @param array<string,int|string|null> $parameters */
    private function query(string $sql, array $parameters): PDOStatement
    {
        $statement = $this->connections->connection()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Could not prepare community report statement.');
        }
        $statement->execute($parameters);
        return $statement;
    }

    /** @param array<array-key,mixed> $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        return is_int($value) || (is_string($value) && ctype_digit($value))
            ? (int) $value : throw new \UnexpectedValueException('Report target is malformed.');
    }

    private function requireTransaction(): void
    {
        if (!$this->connections->connection()->inTransaction()) {
            throw new \LogicException('Community report reads and writes require a transaction.');
        }
    }

    private function time(DateTimeImmutable $now): string
    {
        return $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
