<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Interface\Console;

use PDO;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final readonly class QuranP4CloseoutVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private DatabaseConnectionProvider $connections) {}
    public function name(): ConsoleCommandName { return new ConsoleCommandName('quran:p4:closeout:verify'); }
    public function description(): string { return 'Verify P4 runtime readiness without changing P4 formal closeout state.'; }
    public function execute(ConsoleInput $input, ConsoleOutput $output): int { $input->assertOnlyOptions([]);try{$pdo=$this->connections->connection();$checks=['quran_reference_sources'=>3,'quran_reference_releases'=>1,'quran_surahs'=>1,'quran_ayahs'=>1,'quran_search_corpora'=>1,'quran_ayah_search_texts'=>1];foreach($checks as $table=>$minimum){$count=(int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();if($count<$minimum)throw new \RuntimeException("P4 runtime table is not ready: {$table}");}$output->write("Qur’an P4 closeout readiness: PASS\nFormal closeout: NOT PERFORMED\n");return 0;}catch(\Throwable $e){$output->write("Qur’an P4 closeout readiness: FAIL\n{$e->getMessage()}\n");return 1;}}
}
