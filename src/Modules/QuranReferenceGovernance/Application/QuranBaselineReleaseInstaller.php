<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Application;

use PDO;
use PDOStatement;
use Qmdb\Modules\QuranReferenceGovernance\Domain\QuranReleaseManifest;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class QuranBaselineReleaseInstaller
{
    public function __construct(
        private string $projectRoot,
        private DatabaseConnectionProvider $connections,
        private TanzilUthmaniTextParser $text,
        private TanzilQuranMetadataParser $metadata,
        private QuranReleaseManifest $manifest,
    ) {
    }

    /** @return array{surahs:int,ayahs:int,canonical_text_sha256:string,dry_run:bool} */
    public function install(bool $dryRun): array
    {
        $text = $this->text->parse($this->projectRoot . '/resources/data/quran/tanzil/uthmani-1.1/quran-uthmani.txt');
        $metadata = $this->metadata->parse($this->projectRoot . '/resources/data/quran/tanzil/metadata-1.0/quran-data.xml');
        $this->validate($text['records'], $metadata['surahs']);
        $result = ['surahs' => count($metadata['surahs']), 'ayahs' => count($text['records']), 'canonical_text_sha256' => $text['canonical_text_sha256'], 'dry_run' => $dryRun];
        if ($dryRun) {
            return $result;
        }
        $pdo = $this->connections->connection();
        $pdo->beginTransaction();
        try {
            $releaseByCode = $pdo->query("SELECT id FROM quran_reference_releases WHERE release_code = 'QMDB_QURAN_TANZIL_UTHMANI_1_1' FOR UPDATE");
            if (!$releaseByCode instanceof PDOStatement) {
                throw new \RuntimeException('Baseline release lookup could not be prepared.');
            }
            $exists = $releaseByCode->fetchColumn();
            if ($exists !== false) {
                throw new \DomainException('Baseline release already exists; use content verification rather than repair.');
            }
            $activeRelease = $pdo->query("SELECT id FROM quran_reference_releases WHERE status = 'ACTIVE' FOR UPDATE");
            if (!$activeRelease instanceof PDOStatement) {
                throw new \RuntimeException('Active release lookup could not be prepared.');
            }
            if ($activeRelease->fetchColumn() !== false) {
                throw new \DomainException('An active Qur’an release already exists.');
            }
            $artifacts = $this->registerArtifacts($pdo);
            $releaseId = $this->insertRelease($pdo, $artifacts, $text['canonical_text_sha256']);
            $surahIds = $this->insertSurahs($pdo, $releaseId, $artifacts['metadata'], $metadata['surahs']);
            $ayahIds = $this->insertAyahs($pdo, $releaseId, $artifacts['text'], $surahIds, $text['records']);
            $counts = $this->insertStructures($pdo, $releaseId, $artifacts['metadata'], $ayahIds, $metadata['markers'], count($text['records']));
            $this->insertSummary($pdo, $releaseId, $artifacts, $result, $metadata['markers'], $counts);
            $this->insertEvent($pdo, $releaseId, 'CREATED', null, 'ACTIVE');
            $this->insertEvent($pdo, $releaseId, 'ACTIVATED', 'APPROVED', 'ACTIVE');
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        return $result;
    }

    /**
     * @param list<array{surah_number:int,ayah_number:int,text:string,byte_size:int,sha256:string}> $text
     * @param list<array{surah_number:int,ayah_count:int,arabic_name:string,transliterated_name:string,english_name:string,revelation_type:string,revelation_order:int,ruku_count:int}> $surahs
     */
    private function validate(array $text, array $surahs): void
    {
        $counts = [];
        foreach ($text as $ayah) {
            $counts[$ayah['surah_number']] = ($counts[$ayah['surah_number']] ?? 0) + 1;
        }
        foreach ($surahs as $surah) {
            if (($counts[$surah['surah_number']] ?? 0) !== $surah['ayah_count']) {
                throw new \InvalidArgumentException('Tanzil text and metadata Ayah counts differ.');
            }
        }
    }

    /** @return array{text:int,metadata:int} */
    private function registerArtifacts(PDO $pdo): array
    {
        $values = [
            'text' => ['TANZIL_UTHMANI_1_1', 'TANZIL_UTHMANI_TEXT_1_1', 'CANONICAL_TEXT', 'resources/data/quran/tanzil/uthmani-1.1/quran-uthmani.txt'],
            'metadata' => ['TANZIL_QURAN_METADATA_1_0', 'TANZIL_QURAN_METADATA_1_0', 'STRUCTURAL_METADATA', 'resources/data/quran/tanzil/metadata-1.0/quran-data.xml'],
        ];
        $ids = ['text' => 0, 'metadata' => 0];
        foreach ($values as $key => [$source, $code, $role, $relative]) {
            $sourceId = $pdo->prepare('SELECT id FROM quran_reference_sources WHERE source_code = :code AND status = \'APPROVED\'');
            if (!$sourceId instanceof PDOStatement) {
                throw new \RuntimeException('Qur’an source lookup could not be prepared.');
            }
            $sourceId->execute([':code' => $source]);
            $id = $sourceId->fetchColumn();
            $hash = hash_file('sha256', $this->projectRoot . '/' . $relative, true);
            if (!is_string($hash)) {
                throw new \RuntimeException('Qur’an artifact checksum could not be calculated.');
            }
            $insert = $pdo->prepare('INSERT INTO quran_source_artifacts (public_id,source_id,artifact_code,artifact_role,repository_relative_path,original_filename,media_type,byte_size,sha256,acquisition_profile,acquired_at,status,version,created_at,updated_at) VALUES (:public,:source,:code,:role,:path,:name,:media,:size,:hash,\'QMDB_P4_B02\',UTC_TIMESTAMP(6),\'VERIFIED\',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))');
            $insert->execute([':public' => UuidV7::generate()->toBinary(), ':source' => $id, ':code' => $code, ':role' => $role, ':path' => $relative, ':name' => basename($relative), ':media' => str_ends_with($relative, '.xml') ? 'application/xml' : 'text/plain', ':size' => filesize($this->projectRoot . '/' . $relative), ':hash' => $hash]);
            $ids[$key] = (int) $pdo->lastInsertId();
        }

        return $ids;
    }

    /** @param array{text:int,metadata:int} $artifacts */
    private function insertRelease(PDO $pdo, array $artifacts, string $textHash): int
    {
        $manifest = $this->manifest->canonicalize(['release_code' => 'QMDB_QURAN_TANZIL_UTHMANI_1_1', 'release_version' => '1.0.0', 'artifacts' => $artifacts]);
        $release = $pdo->prepare("INSERT INTO quran_reference_releases (public_id,release_code,release_version,status,manifest_sha256,manifest_schema_version,validation_policy_version,created_by_type,created_by_account_id,version,created_at,staged_at,validated_at,approved_at,activated_at,updated_at) VALUES (:public,'QMDB_QURAN_TANZIL_UTHMANI_1_1','1.0.0','ACTIVE',:hash,'qmdb.quran.release-manifest.v1','qmdb.quran.validation.v1','SYSTEM',NULL,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        $release->execute([':public' => UuidV7::generate()->toBinary(), ':hash' => hex2bin($manifest['sha256'])]);
        $id = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO quran_release_manifests (public_id,release_id,schema_version,canonical_json,sha256,created_at) VALUES (:public,:release,\'qmdb.quran.release-manifest.v1\',:json,:hash,UTC_TIMESTAMP(6))')->execute([':public' => UuidV7::generate()->toBinary(), ':release' => $id, ':json' => $manifest['canonical_json'], ':hash' => hex2bin($manifest['sha256'])]);
        foreach (['text' => 'CANONICAL_TEXT', 'metadata' => 'STRUCTURAL_METADATA'] as $key => $role) {
            $pdo->prepare('INSERT INTO quran_release_artifacts (public_id,release_id,source_artifact_id,artifact_role,created_at) VALUES (:public,:release,:artifact,:role,UTC_TIMESTAMP(6))')->execute([':public' => UuidV7::generate()->toBinary(), ':release' => $id, ':artifact' => $artifacts[$key], ':role' => $role]);
        }

        return $id;
    }

    /**
     * @param list<array{surah_number:int,ayah_count:int,arabic_name:string,transliterated_name:string,english_name:string,revelation_type:string,revelation_order:int,ruku_count:int}> $surahs
     * @return array<int, int>
     */
    private function insertSurahs(PDO $pdo, int $releaseId, int $artifactId, array $surahs): array
    {
        $statement = $pdo->prepare('INSERT INTO quran_surahs (public_id,release_id,metadata_source_artifact_id,surah_number,ayah_count,first_global_ayah_ordinal,last_global_ayah_ordinal,revelation_order,revelation_type,ruku_count,arabic_name,transliterated_name,english_name,metadata_sha256,created_at) VALUES (:public,:release,:artifact,:number,:count,:first,:last,:order,:type,:rukus,:arabic,:transliterated,:english,:hash,UTC_TIMESTAMP(6))');
        $ids = [];
        $ordinal = 1;
        foreach ($surahs as $surah) {
            $count = $surah['ayah_count'];
            $statement->execute([':public' => UuidV7::generate()->toBinary(), ':release' => $releaseId, ':artifact' => $artifactId, ':number' => $surah['surah_number'], ':count' => $count, ':first' => $ordinal, ':last' => $ordinal + $count - 1, ':order' => $surah['revelation_order'], ':type' => $surah['revelation_type'], ':rukus' => $surah['ruku_count'], ':arabic' => $surah['arabic_name'], ':transliterated' => $surah['transliterated_name'], ':english' => $surah['english_name'], ':hash' => hash('sha256', json_encode($surah, JSON_THROW_ON_ERROR), true)]);
            $ids[$surah['surah_number']] = (int) $pdo->lastInsertId();
            $ordinal += $count;
        }
        return $ids;
    }

    /**
     * @param array<int, int> $surahIds
     * @param list<array{surah_number:int,ayah_number:int,text:string,byte_size:int,sha256:string}> $records
     * @return array<string, int>
     */
    private function insertAyahs(PDO $pdo, int $releaseId, int $artifactId, array $surahIds, array $records): array
    {
        $statement = $pdo->prepare('INSERT INTO quran_ayahs (public_id,release_id,surah_id,canonical_text_source_artifact_id,surah_number,ayah_number,global_ayah_ordinal,canonical_uthmani_text,text_byte_size,text_sha256,created_at) VALUES (:public,:release,:surah,:artifact,:surah_number,:ayah_number,:ordinal,:text,:size,:hash,UTC_TIMESTAMP(6))');
        $ids = [];
        foreach ($records as $index => $ayah) {
            $statement->execute([':public' => UuidV7::generate()->toBinary(), ':release' => $releaseId, ':surah' => $surahIds[$ayah['surah_number']], ':artifact' => $artifactId, ':surah_number' => $ayah['surah_number'], ':ayah_number' => $ayah['ayah_number'], ':ordinal' => $index + 1, ':text' => $ayah['text'], ':size' => $ayah['byte_size'], ':hash' => hex2bin($ayah['sha256'])]);
            $ids[$ayah['surah_number'] . ':' . $ayah['ayah_number']] = (int) $pdo->lastInsertId();
        }
        return $ids;
    }

    /**
     * @param array<string, int> $ayahIds
     * @param list<array{type:string,index:int,surah_number:int,ayah_number:int,sajdah_type:?string}> $markers
     * @return array{JUZ:int,HIZB:int,HIZB_QUARTER:int,MANZIL:int,RUKU:int,MUSHAF_PAGE:int,SAJDAH:int}
     */
    private function insertStructures(PDO $pdo, int $releaseId, int $artifactId, array $ayahIds, array $markers, int $ayahCount): array
    {
        $counts = ['JUZ' => 0,'HIZB' => 0,'HIZB_QUARTER' => 0,'MANZIL' => 0,'RUKU' => 0,'MUSHAF_PAGE' => 0,'SAJDAH' => 0];
        $byType = [];
        foreach ($markers as $marker) {
            $byType[$marker['type']][] = $marker;
        }
        $partition = $pdo->prepare('INSERT INTO quran_partitions (public_id,release_id,metadata_source_artifact_id,partition_type,partition_number,parent_partition_id,start_ayah_id,end_ayah_id,start_global_ayah_ordinal,end_global_ayah_ordinal,source_index,derivation_type,created_at) VALUES (:public,:release,:artifact,:type,:number,NULL,:start,:end,:start_ordinal,:end_ordinal,:index,\'SOURCE_MARKER_RANGE\',UTC_TIMESTAMP(6))');
        foreach (['JUZ','HIZB','HIZB_QUARTER','MANZIL','RUKU','MUSHAF_PAGE'] as $type) {
            foreach ($byType[$type] ?? [] as $position => $marker) {
                $startKey = $marker['surah_number'] . ':' . $marker['ayah_number'];
                $next = ($byType[$type][$position + 1] ?? null);
                $startOrdinal = $this->ordinal($marker['surah_number'], $marker['ayah_number'], $ayahIds);
                $endOrdinal = $next === null ? $ayahCount : $this->ordinal($next['surah_number'], $next['ayah_number'], $ayahIds) - 1;
                $endKey = $this->keyForOrdinal($endOrdinal, $ayahIds);
                $partition->execute([':public' => UuidV7::generate()->toBinary(),':release' => $releaseId,':artifact' => $artifactId,':type' => $type,':number' => $marker['index'],':start' => $ayahIds[$startKey],':end' => $ayahIds[$endKey],':start_ordinal' => $startOrdinal,':end_ordinal' => $endOrdinal,':index' => $marker['index']]);
                $counts[$type]++;
            }
        }
        $sajdah = $pdo->prepare('INSERT INTO quran_sajdah_markers (public_id,release_id,metadata_source_artifact_id,sajdah_index,ayah_id,surah_number,ayah_number,global_ayah_ordinal,source_sajdah_type,created_at) VALUES (:public,:release,:artifact,:index,:ayah,:surah,:number,:ordinal,:type,UTC_TIMESTAMP(6))');
        foreach ($byType['SAJDAH'] ?? [] as $marker) {
            $key = $marker['surah_number'] . ':' . $marker['ayah_number'];
            $sajdah->execute([':public' => UuidV7::generate()->toBinary(),':release' => $releaseId,':artifact' => $artifactId,':index' => $marker['index'],':ayah' => $ayahIds[$key],':surah' => $marker['surah_number'],':number' => $marker['ayah_number'],':ordinal' => $this->ordinal($marker['surah_number'], $marker['ayah_number'], $ayahIds),':type' => $marker['sajdah_type'] ?? 'UNKNOWN']);
            $counts['SAJDAH']++;
        }
        return $counts;
    }
    /** @param array<string, int> $ayahIds */
    private function ordinal(int $surah, int $ayah, array $ayahIds): int
    {
        $key = $surah . ':' . $ayah;
        if (!isset($ayahIds[$key])) {
            throw new \InvalidArgumentException('Metadata marker is not present in canonical text.');
        } return array_search($key, array_keys($ayahIds), true) + 1;
    }
    /** @param array<string, int> $ayahIds */
    private function keyForOrdinal(int $ordinal, array $ayahIds): string
    {
        $keys = array_keys($ayahIds);
        $key = $keys[$ordinal - 1] ?? null;
        if (!is_string($key)) {
            throw new \InvalidArgumentException('Metadata marker range is invalid.');
        } return $key;
    }

    /**
     * @param array{text:int,metadata:int} $artifacts
     * @param array{surahs:int,ayahs:int,canonical_text_sha256:string,dry_run:bool} $result
     * @param list<array{type:string,index:int,surah_number:int,ayah_number:int,sajdah_type:?string}> $markers
     * @param array{JUZ:int,HIZB:int,HIZB_QUARTER:int,MANZIL:int,RUKU:int,MUSHAF_PAGE:int,SAJDAH:int} $counts
     */
    private function insertSummary(PDO $pdo, int $releaseId, array $artifacts, array $result, array $markers, array $counts): void
    {
        $structure = hash('sha256', json_encode($markers, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $combined = hash('sha256', hex2bin($result['canonical_text_sha256']) . hex2bin($structure));
        $statement = $pdo->prepare('INSERT INTO quran_release_content_summaries (public_id,release_id,canonical_text_source_artifact_id,metadata_source_artifact_id,surah_count,ayah_count,juz_count,hizb_count,hizb_quarter_count,manzil_count,ruku_count,mushaf_page_count,sajdah_count,canonical_text_sha256,structure_sha256,combined_content_sha256,canonical_serialization_version,validation_policy_version,import_tool_version,imported_at,created_at) VALUES (:public,:release,:text,:metadata,:surahs,:ayahs,:juz,:hizb,:hizb_quarter,:manzil,:ruku,:mushaf_page,:sajdah,:canonical,:structure,:combined,\'qmdb.quran.canonical-content.v1\',\'qmdb.quran.validation.v1\',\'qmdb-quran-importer/1\',UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))');
        $statement->execute([':public' => UuidV7::generate()->toBinary(), ':release' => $releaseId, ':text' => $artifacts['text'], ':metadata' => $artifacts['metadata'], ':surahs' => $result['surahs'], ':ayahs' => $result['ayahs'], ':canonical' => hex2bin($result['canonical_text_sha256']), ':structure' => hex2bin($structure), ':combined' => hex2bin($combined), ':juz' => $counts['JUZ'],':hizb' => $counts['HIZB'],':hizb_quarter' => $counts['HIZB_QUARTER'],':manzil' => $counts['MANZIL'],':ruku' => $counts['RUKU'],':mushaf_page' => $counts['MUSHAF_PAGE'],':sajdah' => $counts['SAJDAH']]);
    }

    private function insertEvent(PDO $pdo, int $releaseId, string $event, ?string $from, string $to): void
    {
        $pdo->prepare('INSERT INTO quran_release_events (public_id,release_id,event_type,from_status,to_status,actor_type,actor_account_id,correlation_id,occurred_at,created_at) VALUES (:public,:release,:event,:from,:to,\'SYSTEM\',NULL,:correlation,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))')->execute([':public' => UuidV7::generate()->toBinary(), ':release' => $releaseId, ':event' => $event, ':from' => $from, ':to' => $to, ':correlation' => UuidV7::generate()->toString()]);
    }
}
