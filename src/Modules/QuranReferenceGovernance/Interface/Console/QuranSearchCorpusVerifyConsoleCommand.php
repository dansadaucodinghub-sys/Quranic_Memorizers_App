<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Interface\Console;

use PDO;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranSearchHasher;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final readonly class QuranSearchCorpusVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private DatabaseConnectionProvider $connections) {}
    public function name(): ConsoleCommandName { return new ConsoleCommandName('quran:search-corpus:verify'); }
    public function description(): string { return 'Verify active Simple Clean corpus cardinality and deterministic checksums.'; }
    public function execute(ConsoleInput $input, ConsoleOutput $output): int { $input->assertOnlyOptions([]);try{$pdo=$this->connections->connection();$corpus=$pdo->query("SELECT id,ayah_count,HEX(simple_text_sha256) corpus_hash FROM quran_search_corpora WHERE status='ACTIVE' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);if(!is_array($corpus))throw new \RuntimeException('No active search corpus.');$rows=$pdo->prepare('SELECT surah_number,ayah_number,simple_clean_text,text_byte_size,HEX(text_sha256) sha256 FROM quran_ayah_search_texts WHERE corpus_id=:corpus ORDER BY global_ayah_ordinal');$rows->execute([':corpus'=>$corpus['id']]);$rows=$rows->fetchAll(PDO::FETCH_ASSOC);if(!is_array($rows)||(int)$corpus['ayah_count']!==count($rows))throw new \RuntimeException('Search corpus cardinality differs.');$records=[];foreach($rows as $row){$text=(string)$row['simple_clean_text'];if(strlen($text)!==(int)$row['text_byte_size']||!hash_equals(strtolower((string)$row['sha256']),QuranSearchHasher::row((int)$row['surah_number'],(int)$row['ayah_number'],$text)))throw new \RuntimeException('Search row checksum differs.');$records[]=['surah_number'=>(int)$row['surah_number'],'ayah_number'=>(int)$row['ayah_number'],'text'=>$text,'byte_size'=>strlen($text),'sha256'=>strtolower((string)$row['sha256'])];}if(!hash_equals(strtolower((string)$corpus['corpus_hash']),QuranSearchHasher::corpus($records)))throw new \RuntimeException('Search corpus checksum differs.');$output->write('Qur’an search corpus verification: PASS'."\nRows: ".count($records)."\n");return 0;}catch(\Throwable $e){$output->write("Qur’an search corpus verification: FAIL\n{$e->getMessage()}\n");return 1;}}
}
