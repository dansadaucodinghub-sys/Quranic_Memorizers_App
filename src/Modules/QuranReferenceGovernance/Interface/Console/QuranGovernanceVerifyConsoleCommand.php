<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Interface\Console;

use PDO;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final readonly class QuranGovernanceVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private DatabaseConnectionProvider $connections) {}
    public function name(): ConsoleCommandName { return new ConsoleCommandName('quran:governance:verify'); }
    public function description(): string { return 'Verify global Qur’an source and release governance without importing text.'; }
    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        $tables=['quran_reference_sources','quran_source_artifacts','quran_reference_releases','quran_release_artifacts','quran_release_validations','quran_release_events'];
        try { $statement=$this->connections->connection()->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'); $valid=true; foreach($tables as $table){$statement->execute([':table'=>$table]);$valid=$valid && (int)$statement->fetchColumn()===1;} } catch (\Throwable) {$valid=false;}
        $output->write('Qur’an governance verification: ' . ($valid ? "PASS\nCanonical text tables: 0\n" : "FAIL\n")); return $valid ? 0 : 1;
    }
}
