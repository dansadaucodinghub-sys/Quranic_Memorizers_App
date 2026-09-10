<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Interface\Console;

use PDO;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final readonly class QuranSourcesVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private DatabaseConnectionProvider $connections) {}
    public function name(): ConsoleCommandName { return new ConsoleCommandName('quran:sources:verify'); }
    public function description(): string { return 'Verify approved Tanzil source definitions without reading source content.'; }
    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        $sql = "SELECT source_code,source_version,content_role,attribution_required,verbatim_only,runtime_download_allowed,status FROM quran_reference_sources ORDER BY source_code";
        try { $rows = $this->connections->connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC); } catch (\Throwable) { $rows = []; }
        $expected=['TANZIL_QURAN_METADATA_1_0'=>['1.0','STRUCTURAL_METADATA'],'TANZIL_SIMPLE_CLEAN_1_1'=>['1.1','SEARCH_TEXT'],'TANZIL_UTHMANI_1_1'=>['1.1','CANONICAL_TEXT']]; $valid=count($rows)===3;
        foreach ($rows as $row) $valid = $valid && isset($expected[$row['source_code']]) && $expected[$row['source_code']]===[$row['source_version'],$row['content_role']] && (int)$row['attribution_required']===1 && (int)$row['verbatim_only']===1 && (int)$row['runtime_download_allowed']===0 && $row['status']==='APPROVED';
        $output->write('Qur’an source verification: ' . ($valid ? "PASS\nApproved source count: 3\n" : "FAIL\n")); return $valid ? 0 : 1;
    }
}
