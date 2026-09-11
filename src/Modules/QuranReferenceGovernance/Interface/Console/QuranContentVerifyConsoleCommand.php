<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Interface\Console;

use PDO;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final readonly class QuranContentVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private DatabaseConnectionProvider $connections) {}
    public function name(): ConsoleCommandName { return new ConsoleCommandName('quran:content:verify'); }
    public function description(): string { return 'Verify active canonical content and its optional active search overlay.'; }
    public function execute(ConsoleInput $input, ConsoleOutput $output): int { $input->assertOnlyOptions([]); try { $pdo=$this->connections->connection(); $release=$pdo->query("SELECT r.id,s.ayah_count FROM quran_reference_releases r INNER JOIN quran_release_content_summaries s ON s.release_id=r.id WHERE r.status='ACTIVE' ORDER BY r.id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC); if(!is_array($release)) throw new \RuntimeException('No active canonical release.'); $count=$pdo->prepare('SELECT COUNT(*) FROM quran_ayahs WHERE release_id=:id');$count->execute([':id'=>$release['id']]); if((int)$count->fetchColumn() !== (int)$release['ayah_count']) throw new \RuntimeException('Canonical Ayah count differs from its summary.'); $corpus=$pdo->prepare("SELECT id,ayah_count FROM quran_search_corpora WHERE canonical_release_id=:id AND status='ACTIVE'");$corpus->execute([':id'=>$release['id']]);$corpus=$corpus->fetch(PDO::FETCH_ASSOC); if(is_array($corpus)){ $rows=$pdo->prepare('SELECT COUNT(*) FROM quran_ayah_search_texts WHERE corpus_id=:id');$rows->execute([':id'=>$corpus['id']]);if((int)$rows->fetchColumn() !== (int)$corpus['ayah_count'])throw new \RuntimeException('Search corpus count differs.'); } $output->write("Qur’an content verification: PASS\nCanonical Ayahs: {$release['ayah_count']}\nSearch corpus: ".(is_array($corpus)?'ACTIVE':'NOT INSTALLED')."\n");return 0;}catch(\Throwable $e){$output->write("Qur’an content verification: FAIL\n{$e->getMessage()}\n");return 1;} }
}
