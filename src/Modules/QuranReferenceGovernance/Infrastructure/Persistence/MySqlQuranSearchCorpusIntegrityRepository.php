<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranSearchCorpusIntegrityRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlQuranSearchCorpusIntegrityRepository implements QuranSearchCorpusIntegrityRepository
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function findForRelease(UuidV7 $releasePublicId): ?array
    {
        return $this->corpus($releasePublicId, false);
    }

    public function lockForValidation(UuidV7 $releasePublicId): ?array
    {
        return $this->corpus($releasePublicId, true);
    }

    public function alignedRows(int $corpusInternalId): array
    {
        $statement = $this->statement($this->connections->connection()->prepare('SELECT BIN_TO_UUID(a.public_id) AS ayah_public_id, t.surah_number, t.ayah_number, t.global_ayah_ordinal, t.simple_clean_text, LOWER(HEX(t.text_sha256)) AS text_sha256, LOWER(HEX(a.text_sha256)) AS canonical_text_sha256 FROM quran_ayah_search_texts t INNER JOIN quran_ayahs a ON a.id = t.ayah_id AND a.release_id = t.canonical_release_id WHERE t.corpus_id = :corpus_id ORDER BY t.global_ayah_ordinal ASC, t.id ASC'));
        $statement->execute([':corpus_id' => $corpusInternalId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $records = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \RuntimeException('Qur’an corpus validation row is invalid.');
            }
            $records[] = $this->record($row);
        }
        return $records;
    }

    public function completed(UuidV7 $submissionId, string $fingerprint): ?array
    {
        $statement = $this->statement($this->connections->connection()->prepare('SELECT operation.request_fingerprint, BIN_TO_UUID(corpus.public_id) AS corpus_public_id, operation.expected_version FROM quran_search_corpus_validation_operations operation INNER JOIN quran_search_corpora corpus ON corpus.id = operation.corpus_id WHERE operation.submission_id = :submission_id FOR UPDATE'));
        $statement->execute([':submission_id' => $submissionId->toBinary()]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $record = $this->record($row);
        if (!hash_equals($this->string($record, 'request_fingerprint'), $fingerprint)) {
            throw new \DomainException('Qur’an corpus validation submission conflicts with a prior request.');
        }

        return ['corpus_public_id' => $this->string($record, 'corpus_public_id'), 'expected_version' => $this->integer($record, 'expected_version')];
    }

    public function appendValidation(int $corpusInternalId, int $accountInternalId, string $evidenceSha256, DateTimeImmutable $occurredAt): string
    {
        $publicId = UuidV7::generate();
        $statement = $this->statement($this->connections->connection()->prepare("INSERT INTO quran_search_corpus_validations (public_id,corpus_id,validator_code,validator_version,result,safe_summary_code,evidence_sha256,executed_by_type,executed_by_account_id,occurred_at,created_at) VALUES (:public_id,:corpus_id,'QMDB_QURAN_SEARCH_CORPUS_INTEGRITY','1','PASS','ALIGNED',:evidence,'ACCOUNT',:account_id,:occurred_at,:occurred_at)"));
        $statement->execute([':public_id' => $publicId->toBinary(), ':corpus_id' => $corpusInternalId, ':evidence' => $this->checksum($evidenceSha256), ':account_id' => $accountInternalId, ':occurred_at' => self::time($occurredAt)]);

        return $publicId->toString();
    }

    public function recordOperation(UuidV7 $submissionId, string $fingerprint, int $corpusInternalId, AuthenticatedAccountContext $actor, int $expectedVersion, string $validationPublicId, string $auditEventPublicId, int $stepUpGrantInternalId, DateTimeImmutable $occurredAt): void
    {
        $statement = $this->statement($this->connections->connection()->prepare('INSERT INTO quran_search_corpus_validation_operations (public_id,submission_id,request_fingerprint,corpus_id,actor_account_id,expected_version,validation_public_id,audit_event_public_id,step_up_grant_id,occurred_at,created_at) VALUES (:public_id,:submission_id,:fingerprint,:corpus_id,:actor_id,:expected_version,:validation_id,:audit_id,:step_up_id,:occurred_at,:occurred_at)'));
        $statement->execute([':public_id' => UuidV7::generate()->toBinary(), ':submission_id' => $submissionId->toBinary(), ':fingerprint' => $fingerprint, ':corpus_id' => $corpusInternalId, ':actor_id' => $actor->accountInternalId, ':expected_version' => $expectedVersion, ':validation_id' => UuidV7::fromString($validationPublicId)->toBinary(), ':audit_id' => UuidV7::fromString($auditEventPublicId)->toBinary(), ':step_up_id' => $stepUpGrantInternalId, ':occurred_at' => self::time($occurredAt)]);
    }

    /** @return array<string, int|string>|null */
    private function corpus(UuidV7 $releasePublicId, bool $forUpdate): ?array
    {
        $statement = $this->statement($this->connections->connection()->prepare('SELECT c.id, BIN_TO_UUID(c.public_id) AS public_id, c.status, c.version, c.ayah_count, LOWER(HEX(c.simple_text_sha256)) AS simple_text_sha256, LOWER(HEX(c.alignment_sha256)) AS alignment_sha256, c.corpus_code, c.corpus_version, c.normalization_policy_version, c.import_tool_version, BIN_TO_UUID(r.public_id) AS release_public_id, r.release_code, r.release_version, source.source_code, source.source_version, artifact.original_filename, artifact.byte_size AS artifact_byte_size, LOWER(HEX(artifact.sha256)) AS artifact_sha256, validation.result AS last_validation_result, validation.occurred_at AS last_validation_at FROM quran_search_corpora c INNER JOIN quran_reference_releases r ON r.id = c.canonical_release_id INNER JOIN quran_source_artifacts artifact ON artifact.id = c.search_source_artifact_id INNER JOIN quran_reference_sources source ON source.id = artifact.source_id LEFT JOIN quran_search_corpus_validations validation ON validation.id = (SELECT v.id FROM quran_search_corpus_validations v WHERE v.corpus_id = c.id ORDER BY v.occurred_at DESC, v.id DESC LIMIT 1) WHERE r.public_id = :release_public_id AND c.status = \'ACTIVE\' ORDER BY c.id DESC LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '')));
        $statement->execute([':release_public_id' => $releasePublicId->toBinary()]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->record($row) : null;
    }

    private static function time(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    private function statement(\PDOStatement|false $statement): \PDOStatement
    {
        if (!$statement instanceof \PDOStatement) {
            throw new \RuntimeException('Qur’an corpus query preparation failed.');
        } return $statement;
    }
    /**
     * @param array<mixed, mixed> $row
     * @return array<string, int|string>
     */
    private function record(array $row): array
    {
        $record = [];
        foreach ($row as $field => $value) {
            if (!is_string($field) || (!is_int($value) && !is_string($value))) {
                throw new \RuntimeException('Qur’an corpus query value is invalid.');
            } $record[$field] = $value;
        } return $record;
    }
    /** @param array<string, int|string> $row */
    private function string(array $row, string $field): string
    {
        $value = $row[$field] ?? null;
        if (!is_string($value)) {
            throw new \RuntimeException('Qur’an corpus query value is invalid.');
        } return $value;
    }
    /** @param array<string, int|string> $row */
    private function integer(array $row, string $field): int
    {
        $value = $row[$field] ?? null;
        if (!is_int($value) && !is_string($value)) {
            throw new \RuntimeException('Qur’an corpus query value is invalid.');
        } return (int) $value;
    }
    private function checksum(string $hexadecimal): string
    {
        $value = hex2bin($hexadecimal);
        if ($value === false) {
            throw new \InvalidArgumentException('Qur’an checksum is invalid.');
        } return $value;
    }
}
