<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Application;

use PDO;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

/** Installs only a fully verified Simple Clean overlay; it never mutates canonical content. */
final readonly class QuranBaselineSearchCorpusInstaller
{
    public function __construct(private string $projectRoot, private DatabaseConnectionProvider $connections, private TanzilSimpleCleanTextParser $parser) {}
    /** @return array{ayahs:int,corpus_sha256:string,alignment_sha256:string,dry_run:bool} */
    public function install(bool $dryRun): array
    {
        $records = $this->parser->parse($this->projectRoot . '/resources/data/quran/tanzil/simple-clean-1.1/quran-simple-clean.txt');
        $this->verifyArtifact();
        $pdo = $this->connections->connection();
        $release = $this->activeRelease($pdo);
        $ayahs = $this->ayahs($pdo, $release['id']);
        $publicId = UuidV7::generate()->toString();
        $result = ['ayahs' => count($records), 'corpus_sha256' => QuranSearchHasher::corpus($records), 'alignment_sha256' => QuranSearchHasher::alignment($release['public_id'], $publicId, $ayahs, $records), 'dry_run' => $dryRun];
        if ($dryRun) { return $result; }
        $pdo->beginTransaction();
        try {
            $existing = $pdo->prepare("SELECT id FROM quran_search_corpora WHERE corpus_code = 'QMDB_QURAN_TANZIL_SIMPLE_CLEAN_1_1' FOR UPDATE"); $existing->execute();
            if ($existing->fetchColumn() !== false) { throw new \DomainException('Baseline search corpus already exists; use content verification.'); }
            $artifact = $this->artifact($pdo);
            $insertCorpus = $pdo->prepare("INSERT INTO quran_search_corpora (public_id,canonical_release_id,search_source_artifact_id,corpus_code,corpus_version,status,ayah_count,simple_text_sha256,alignment_sha256,normalization_policy_version,import_tool_version,imported_at,validated_at,activated_at,created_at,updated_at,version) VALUES (:public,:release,:artifact,'QMDB_QURAN_TANZIL_SIMPLE_CLEAN_1_1','1.0.0','ACTIVE',:count,:corpus,:alignment,'qmdb.quran.search-normalization.v1','qmdb-quran-search-importer/1',UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),1)");
            $insertCorpus->execute([':public'=>UuidV7::fromString($publicId)->toBinary(), ':release'=>$release['id'], ':artifact'=>$artifact, ':count'=>count($records), ':corpus'=>hex2bin($result['corpus_sha256']), ':alignment'=>hex2bin($result['alignment_sha256'])]); $corpus = (int)$pdo->lastInsertId();
            $insert = $pdo->prepare('INSERT INTO quran_ayah_search_texts (public_id,corpus_id,canonical_release_id,ayah_id,surah_number,ayah_number,global_ayah_ordinal,simple_clean_text,text_byte_size,text_sha256,created_at) VALUES (:public,:corpus,:release,:ayah,:surah,:number,:ordinal,:text,:size,:hash,UTC_TIMESTAMP(6))');
            foreach ($records as $index => $record) { $ayah = $ayahs[$index]; $insert->execute([':public'=>UuidV7::generate()->toBinary(),':corpus'=>$corpus,':release'=>$release['id'],':ayah'=>$ayah['id'],':surah'=>$record['surah_number'],':number'=>$record['ayah_number'],':ordinal'=>$ayah['global_ayah_ordinal'],':text'=>$record['text'],':size'=>$record['byte_size'],':hash'=>hex2bin($record['sha256'])]); }
            $validation = $pdo->prepare("INSERT INTO quran_search_corpus_validations (public_id,corpus_id,validator_code,validator_version,result,safe_summary_code,evidence_sha256,executed_by_type,executed_by_account_id,occurred_at,created_at) VALUES (:public,:corpus,'QMDB_QURAN_SEARCH_IMPORT','1','PASS','ALIGNED',:hash,'SYSTEM',NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))"); $validation->execute([':public'=>UuidV7::generate()->toBinary(),':corpus'=>$corpus,':hash'=>hex2bin($result['alignment_sha256'])]);
            foreach ([['CREATED',null,'STAGED'],['IMPORTED','STAGED','VALIDATED'],['VALIDATED','STAGED','VALIDATED'],['ACTIVATED','VALIDATED','ACTIVE']] as [$event,$from,$to]) { $eventInsert=$pdo->prepare('INSERT INTO quran_search_corpus_events (public_id,corpus_id,event_type,from_status,to_status,actor_type,actor_account_id,reason_code,correlation_id,occurred_at,created_at) VALUES (:public,:corpus,:event,:from,:to,\'SYSTEM\',NULL,NULL,:correlation,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))'); $eventInsert->execute([':public'=>UuidV7::generate()->toBinary(),':corpus'=>$corpus,':event'=>$event,':from'=>$from,':to'=>$to,':correlation'=>UuidV7::generate()->toString()]); }
            $pdo->commit();
        } catch (\Throwable $error) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $error; }
        return $result;
    }
    private function verifyArtifact(): void
    {
        $path = $this->projectRoot . '/resources/data/quran/tanzil/search-artifacts.lock.json'; $decoded = file_get_contents($path); $lock = is_string($decoded) ? json_decode($decoded, true, 32, JSON_THROW_ON_ERROR) : null;
        if (!is_array($lock) || ($lock['schema'] ?? '') !== 'qmdb.quran.tanzil-artifacts-lock.v1') { throw new \InvalidArgumentException('Search artifact lock is invalid.'); }
        $stored = $lock['lock_sha256'] ?? null; unset($lock['lock_sha256']);
        if (!is_string($stored) || !hash_equals($stored, hash('sha256', json_encode($lock, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)))) { throw new \InvalidArgumentException('Search artifact lock checksum differs.'); }
        $notice = $lock['notice'] ?? null; if (!is_array($notice) || !is_string($notice['repository_relative_path'] ?? null) || !is_string($notice['sha256'] ?? null)) { throw new \InvalidArgumentException('Search notice lock is invalid.'); }
        $noticePath = $this->projectRoot . '/' . $notice['repository_relative_path']; if (!is_file($noticePath) || !hash_equals($notice['sha256'], hash_file('sha256', $noticePath))) { throw new \InvalidArgumentException('Search notice checksum differs.'); }
        $entry = null; foreach (($lock['artifacts'] ?? []) as $artifact) { if (is_array($artifact) && ($artifact['source_code'] ?? null) === 'TANZIL_SIMPLE_CLEAN_1_1') { $entry = $artifact; break; } }
        if (!is_array($entry) || !is_string($entry['repository_relative_path'] ?? null) || !is_string($entry['sha256'] ?? null)) { throw new \InvalidArgumentException('Search source lock lacks Simple Clean artifact.'); }
        $artifact = $this->projectRoot . '/' . $entry['repository_relative_path']; if (!is_file($artifact) || !hash_equals($entry['sha256'], hash_file('sha256', $artifact))) { throw new \InvalidArgumentException('Search source artifact checksum differs.'); }
    }
    /** @return array{id:int,public_id:string} */
    private function activeRelease(PDO $pdo): array { $row=$pdo->query("SELECT id,BIN_TO_UUID(public_id) public_id FROM quran_reference_releases WHERE status='ACTIVE' ORDER BY id DESC LIMIT 1 FOR UPDATE")->fetch(PDO::FETCH_ASSOC); if (!is_array($row)) { throw new \DomainException('An active canonical release is required.'); } return ['id'=>(int)$row['id'],'public_id'=>(string)$row['public_id']]; }
    /** @return list<array{id:int,public_id:string,surah_number:int,ayah_number:int,global_ayah_ordinal:int,text_sha256:string}> */
    private function ayahs(PDO $pdo, int $release): array { $statement=$pdo->prepare('SELECT id,BIN_TO_UUID(public_id) public_id,surah_number,ayah_number,global_ayah_ordinal,HEX(text_sha256) text_sha256 FROM quran_ayahs WHERE release_id=:release ORDER BY global_ayah_ordinal ASC'); $statement->execute([':release'=>$release]); $rows=$statement->fetchAll(PDO::FETCH_ASSOC); if (!is_array($rows) || $rows === []) { throw new \DomainException('Active canonical release has no Ayah dataset.'); } return array_map(static fn(array $r):array=>['id'=>(int)$r['id'],'public_id'=>(string)$r['public_id'],'surah_number'=>(int)$r['surah_number'],'ayah_number'=>(int)$r['ayah_number'],'global_ayah_ordinal'=>(int)$r['global_ayah_ordinal'],'text_sha256'=>strtolower((string)$r['text_sha256'])],$rows); }
    private function artifact(PDO $pdo): int { $source=$pdo->query("SELECT id FROM quran_reference_sources WHERE source_code='TANZIL_SIMPLE_CLEAN_1_1' AND status='APPROVED'")->fetchColumn(); if ($source===false) { throw new \DomainException('Approved Simple Clean source is missing.'); } $path='resources/data/quran/tanzil/simple-clean-1.1/quran-simple-clean.txt'; $found=$pdo->prepare('SELECT id FROM quran_source_artifacts WHERE source_id=:source AND artifact_code=\'TANZIL_SIMPLE_CLEAN_TEXT_1_1\''); $found->execute([':source'=>$source]); $id=$found->fetchColumn(); if ($id!==false) return (int)$id; $insert=$pdo->prepare("INSERT INTO quran_source_artifacts (public_id,source_id,artifact_code,artifact_role,repository_relative_path,original_filename,media_type,byte_size,sha256,acquisition_profile,acquired_at,status,version,created_at,updated_at) VALUES (:public,:source,'TANZIL_SIMPLE_CLEAN_TEXT_1_1','SEARCH_TEXT',:path,'quran-simple-clean.txt','text/plain',:size,:hash,'QMDB_P4_B03',UTC_TIMESTAMP(6),'VERIFIED',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))"); $full=$this->projectRoot.'/'.$path; $insert->execute([':public'=>UuidV7::generate()->toBinary(),':source'=>$source,':path'=>$path,':size'=>filesize($full),':hash'=>hash_file('sha256',$full,true)]); return (int)$pdo->lastInsertId(); }
}
