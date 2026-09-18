<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaModeration\Infrastructure\Persistence;

use DateTimeImmutable;
use PDO;
use Qmdb\Modules\MediaModeration\Application\MediaGovernanceRepository;
use Qmdb\Modules\MediaModeration\Domain\MediaGovernanceAction;
use Qmdb\Modules\MediaModeration\Domain\MediaGovernanceRecord;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlMediaGovernanceRepository implements MediaGovernanceRepository
{
    private const SELECT = "SELECT a.id,a.public_id,a.workspace_id,a.created_by_account_id,a.status,a.version,COALESCE(p.rights_granted,0) AS rights_granted,COALESCE(p.consent_granted,0) AS consent_granted,EXISTS(SELECT 1 FROM media_holds h WHERE h.asset_id=a.id AND h.workspace_id=a.workspace_id AND h.released_at IS NULL) AS held,EXISTS(SELECT 1 FROM media_scan_results s WHERE s.asset_id=a.id AND s.workspace_id=a.workspace_id AND s.result_code='CLEAN') AS clean,EXISTS(SELECT 1 FROM media_variants v WHERE v.asset_id=a.id AND v.workspace_id=a.workspace_id AND v.status='READY' AND v.variant_code='NORMALIZED_V1' AND v.mime_type IN ('audio/mpeg','video/mp4')) AS processed FROM media_assets a LEFT JOIN media_delivery_policies p ON p.asset_id=a.id AND p.workspace_id=a.workspace_id ";

    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function find(int $workspace, UuidV7 $asset, bool $lock = false): ?MediaGovernanceRecord
    {
        $row = $this->execute(self::SELECT . 'WHERE a.workspace_id=:workspace AND a.public_id=:asset' . ($lock ? ' FOR UPDATE' : ''), ['workspace' => $workspace, 'asset' => $asset->toBinary()])->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->decode($row) : null;
    }

    public function recent(int $workspace): array
    {
        $statement = $this->execute(self::SELECT . 'WHERE a.workspace_id=:workspace ORDER BY a.created_at DESC,a.id DESC LIMIT 50', ['workspace' => $workspace]);
        $records = [];
        while (is_array($row = $statement->fetch(PDO::FETCH_ASSOC))) {
            $records[] = $this->decode($row);
        }
        return $records;
    }

    public function replay(UuidV7 $submission, string $fingerprint): ?array
    {
        $row = $this->execute('SELECT request_fingerprint,asset_public_id,result_status,result_version FROM media_governance_operations WHERE submission_id=:submission FOR UPDATE', ['submission' => $submission->toBinary()])->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        if (!hash_equals($this->string($row, 'request_fingerprint'), $fingerprint)) {
            throw new \DomainException('Submission identifier was used for a different request.');
        }
        return ['asset_id' => UuidV7::fromBinary($this->string($row, 'asset_public_id'))->toString(), 'status' => $this->string($row, 'result_status'), 'version' => $this->integer($row, 'result_version')];
    }

    public function apply(MediaGovernanceRecord $asset, MediaGovernanceAction $action, string $target, int $actor, string $holdCode, DateTimeImmutable $now): void
    {
        $time = $now->format('Y-m-d H:i:s.u');
        if ($action === MediaGovernanceAction::HOLD) {
            $existing = $this->execute('SELECT id FROM media_holds WHERE workspace_id=:workspace AND asset_id=:asset AND hold_code=:code AND released_at IS NULL FOR UPDATE', ['workspace' => $asset->workspaceId, 'asset' => $asset->id, 'code' => $holdCode])->fetchColumn();
            if ($existing !== false) {
                throw new \DomainException('This hold is already active.');
            }
            $this->execute('INSERT INTO media_holds (workspace_id,asset_id,hold_code,placed_by_account_id,created_at) VALUES (:workspace,:asset,:code,:actor,:time)', ['workspace' => $asset->workspaceId, 'asset' => $asset->id, 'code' => $holdCode, 'actor' => $actor, 'time' => $time]);
        }
        if ($action === MediaGovernanceAction::RELEASE_HOLD) {
            $released = $this->execute('UPDATE media_holds SET released_by_account_id=:actor,released_at=:time WHERE workspace_id=:workspace AND asset_id=:asset AND hold_code=:code AND released_at IS NULL', ['actor' => $actor, 'time' => $time, 'workspace' => $asset->workspaceId, 'asset' => $asset->id, 'code' => $holdCode]);
            if ($released->rowCount() !== 1) {
                throw new \DomainException('The selected active hold is unavailable.');
            }
        }
        if ($action === MediaGovernanceAction::WITHDRAW_CONSENT) {
            $this->execute("UPDATE media_delivery_policies SET consent_granted=0,visibility_code='PRIVATE',updated_at=:time,version=version+1 WHERE workspace_id=:workspace AND asset_id=:asset", ['time' => $time, 'workspace' => $asset->workspaceId, 'asset' => $asset->id]);
        }
        $updated = $this->execute('UPDATE media_assets SET status=:target,version=version+1,updated_at=:time WHERE workspace_id=:workspace AND id=:asset AND version=:version', ['target' => $target, 'time' => $time, 'workspace' => $asset->workspaceId, 'asset' => $asset->id, 'version' => $asset->version]);
        if ($updated->rowCount() !== 1) {
            throw new \DomainException('Media changed concurrently.');
        }
    }

    public function record(UuidV7 $submission, string $fingerprint, MediaGovernanceRecord $asset, MediaGovernanceAction $action, string $target, int $actor, string $reason, DateTimeImmutable $now): void
    {
        $time = $now->format('Y-m-d H:i:s.u');
        $this->execute('INSERT INTO media_governance_operations (submission_id,request_fingerprint,workspace_id,asset_id,asset_public_id,actor_account_id,action_code,reason_code,result_status,result_version,created_at) VALUES (:submission,:fingerprint,:workspace,:asset,:public,:actor,:action,:reason,:status,:version,:time)', ['submission' => $submission->toBinary(), 'fingerprint' => $fingerprint, 'workspace' => $asset->workspaceId, 'asset' => $asset->id, 'public' => UuidV7::fromString($asset->publicId)->toBinary(), 'actor' => $actor, 'action' => $action->value, 'reason' => $reason, 'status' => $target, 'version' => $asset->version + 1, 'time' => $time]);
        $this->execute('INSERT INTO media_events (workspace_id,asset_id,event_code,actor_account_id,created_at) VALUES (:workspace,:asset,:event,:actor,:time)', ['workspace' => $asset->workspaceId, 'asset' => $asset->id, 'event' => 'media.' . str_replace('-', '_', $action->value), 'actor' => $actor, 'time' => $time]);
    }
    public function grantConsent(MediaGovernanceRecord $asset, \Qmdb\Modules\MediaModeration\Domain\MediaConsentEvidence $evidence, int $actor, DateTimeImmutable $now): void
    {
        if (!$this->connections->connection()->inTransaction() || $actor === $asset->creatorId) {
            throw new \DomainException('Consent requires an independent transactional review.');
        }
        $time = $now->format('Y-m-d H:i:s.u');
        $checksum = hex2bin($evidence->evidenceSha256);
        if ($checksum === false) {
            throw new \InvalidArgumentException('Invalid consent evidence checksum.');
        }
        $this->execute('INSERT INTO media_consent_reviews (workspace_id,asset_id,asset_version,reviewer_account_id,evidence_reference,evidence_sha256,participant_count,consent_count,minor_count,guardian_consent_count,created_at) VALUES (:workspace,:asset,:version,:actor,:reference,:checksum,:participants,:consents,:minors,:guardians,:time)', ['workspace' => $asset->workspaceId, 'asset' => $asset->id, 'version' => $asset->version, 'actor' => $actor, 'reference' => $evidence->evidenceReference->toBinary(), 'checksum' => $checksum, 'participants' => $evidence->participants, 'consents' => $evidence->participantConsents, 'minors' => $evidence->minors, 'guardians' => $evidence->guardianConsents, 'time' => $time]);
        $updated = $this->execute("UPDATE media_delivery_policies SET rights_granted=1,consent_granted=1,visibility_code='PRIVATE',version=version+1,updated_at=:time WHERE workspace_id=:workspace AND asset_id=:asset", ['time' => $time, 'workspace' => $asset->workspaceId, 'asset' => $asset->id]);
        if ($updated->rowCount() !== 1) {
            throw new \DomainException('Private media policy is unavailable.');
        }
    }

    /** @param array<string,int|string|null> $parameters */
    private function execute(string $sql, array $parameters): \PDOStatement
    {
        $statement = $this->connections->connection()->prepare($sql);
        $statement->execute($parameters);
        return $statement;
    }

    /** @param array<array-key,mixed> $row */
    private function decode(array $row): MediaGovernanceRecord
    {
        return new MediaGovernanceRecord($this->integer($row, 'id'), UuidV7::fromBinary($this->string($row, 'public_id'))->toString(), $this->integer($row, 'workspace_id'), $this->integer($row, 'created_by_account_id'), $this->string($row, 'status'), $this->integer($row, 'version'), $this->integer($row, 'rights_granted') === 1, $this->integer($row, 'consent_granted') === 1, $this->integer($row, 'held') === 1, $this->integer($row, 'clean') === 1, $this->integer($row, 'processed') === 1);
    }

    /** @param array<array-key,mixed> $row */
    private function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        return is_string($value) ? $value : throw new \UnexpectedValueException('Invalid media record.');
    }

    /** @param array<array-key,mixed> $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : throw new \UnexpectedValueException('Invalid media record.');
    }
}
