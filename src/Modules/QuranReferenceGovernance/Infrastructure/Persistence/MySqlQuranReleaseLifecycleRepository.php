<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranReleaseLifecycleRepository;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranReleaseTransitionCommand;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranReleaseTransitionResult;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Schema\State\PdoResultReader;

final readonly class MySqlQuranReleaseLifecycleRepository implements QuranReleaseLifecycleRepository
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function lockRelease(UuidV7 $publicId): ?array
    {
        $statement = $this->connections->connection()->prepare('SELECT id, public_id, status, version, release_code, release_version FROM quran_reference_releases WHERE public_id = :public_id FOR UPDATE');
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Qur’an release lock statement could not be prepared.');
        }
        $statement->bindValue(':public_id', $publicId->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $row = self::row($row);
        if ($row === null) {
            return null;
        }
        return ['id' => PdoResultReader::integer($row, 'id'),'public_id' => PdoResultReader::string($row, 'public_id'),'status' => PdoResultReader::string($row, 'status'),'version' => PdoResultReader::integer($row, 'version'),'release_code' => PdoResultReader::string($row, 'release_code'),'release_version' => PdoResultReader::string($row, 'release_version')];
    }

    public function activeReleaseExists(): bool
    {
        $statement = $this->connections->connection()->query("SELECT id FROM quran_reference_releases WHERE status = 'ACTIVE' FOR UPDATE");
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Active Qur’an release lookup could not be prepared.');
        }
        return $statement->fetchColumn() !== false;
    }

    /** @param array{id:int,public_id:string,status:string,version:int,release_code:string,release_version:string} $release */
    public function transition(array $release, string $status, int $actorAccountId, DateTimeImmutable $now): bool
    {
        $fields = ['status = :status','version = version + 1','updated_at = :now'];
        if ($status === 'STAGED') {
            $fields[] = 'staged_at = :now';
        }
        if ($status === 'VALIDATED') {
            $fields[] = 'validated_at = :now';
        }
        if ($status === 'APPROVED') {
            $fields[] = 'approved_at = :now';
            $fields[] = 'approved_by_account_id = :actor';
        }
        if ($status === 'ACTIVE') {
            $fields[] = 'activated_at = :now';
            $fields[] = 'activated_by_account_id = :actor';
        }
        if ($status === 'REJECTED') {
            $fields[] = 'rejected_at = :now';
        }
        $statement = $this->connections->connection()->prepare('UPDATE quran_reference_releases SET ' . implode(', ', $fields) . ' WHERE id = :id AND status = :previous AND version = :version');
        $statement->execute([':status' => $status,':now' => self::time($now),':actor' => $actorAccountId,':id' => $release['id'],':previous' => $release['status'],':version' => $release['version']]);
        return $statement->rowCount() === 1;
    }

    /** @param array{id:int,public_id:string,status:string,version:int,release_code:string,release_version:string} $release */
    public function appendEvent(array $release, string $eventType, string $toStatus, int $actorAccountId, ?string $reasonCode, string $correlationId, DateTimeImmutable $now): void
    {
        $databaseEvent = match ($eventType) {
            'STAGE' => 'STAGED', 'VALIDATE' => 'VALIDATED', 'APPROVE' => 'APPROVED', 'ACTIVATE' => 'ACTIVATED', 'REJECT' => 'REJECTED',
            default => throw new \InvalidArgumentException('Qur’an release event is invalid.'),
        };
        $statement = $this->connections->connection()->prepare('INSERT INTO quran_release_events (public_id,release_id,event_type,from_status,to_status,actor_type,actor_account_id,reason_code,correlation_id,occurred_at,created_at) VALUES (:public_id,:release_id,:event_type,:from_status,:to_status,\'ACCOUNT\',:actor,:reason,:correlation,:now,:now)');
        $statement->execute([':public_id' => UuidV7::generate()->toBinary(),':release_id' => $release['id'],':event_type' => $databaseEvent,':from_status' => $release['status'],':to_status' => $toStatus,':actor' => $actorAccountId,':reason' => $reasonCode,':correlation' => $correlationId,':now' => self::time($now)]);
    }

    public function findCompleted(UuidV7 $submissionId, string $fingerprint): ?QuranReleaseTransitionResult
    {
        $statement = $this->connections->connection()->prepare('SELECT operation.request_fingerprint,release.public_id,operation.new_status,operation.version_after FROM quran_release_operations operation INNER JOIN quran_reference_releases release ON release.id = operation.release_id WHERE operation.submission_id = :submission_id FOR UPDATE');
        $statement->execute([':submission_id' => $submissionId->toBinary()]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $row = self::row($row);
        if ($row === null) {
            return null;
        }
        if (!hash_equals(PdoResultReader::string($row, 'request_fingerprint'), $fingerprint)) {
            throw new \DomainException('Qur’an release submission conflicts with a prior request.');
        }
        return new QuranReleaseTransitionResult(UuidV7::fromBinary(PdoResultReader::string($row, 'public_id'))->toString(), PdoResultReader::string($row, 'new_status'), PdoResultReader::integer($row, 'version_after'), true);
    }

    /** @param array{id:int,public_id:string,status:string,version:int,release_code:string,release_version:string} $release */
    public function record(UuidV7 $submissionId, string $fingerprint, array $release, QuranReleaseTransitionCommand $command, ?int $stepUpGrantId, string $auditEventPublicId, DateTimeImmutable $now): void
    {
        $statement = $this->connections->connection()->prepare('INSERT INTO quran_release_operations (public_id,submission_id,request_fingerprint,operation_type,release_id,actor_account_id,previous_status,new_status,step_up_grant_id,audit_event_public_id,version_before,version_after,occurred_at,created_at) VALUES (:public_id,:submission_id,:fingerprint,:operation,:release_id,:actor,:previous,:new,:step_up,:audit_event,:before,:after,:now,:now)');
        $statement->execute([':public_id' => UuidV7::generate()->toBinary(),':submission_id' => $submissionId->toBinary(),':fingerprint' => $fingerprint,':operation' => $command->action->value,':release_id' => $release['id'],':actor' => $command->actor->accountInternalId,':previous' => $release['status'],':new' => $command->action->targetStatus(),':step_up' => $stepUpGrantId,':audit_event' => UuidV7::fromString($auditEventPublicId)->toBinary(),':before' => $release['version'],':after' => $release['version'] + 1,':now' => self::time($now)]);
    }

    private static function time(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    /** @return array<string, mixed>|null */
    private static function row(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        $row = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new \RuntimeException('Qur’an release database row has an invalid column name.');
            }
            $row[$key] = $item;
        }
        return $row;
    }
}
