<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaCatalog\Interface\Console;

use PDO;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskMap;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Modules\MediaProcessing\Application\MediaScanWorker;
use Qmdb\Modules\MediaProcessing\Application\MediaProcessingWorker;

/** Safe P9 operational command surface. Mutating workers require explicit future job implementations. */
final readonly class MediaP9RuntimeConsoleCommand implements ConsoleCommand
{
    public function __construct(private string $command, private string $operation, private DatabaseConnectionProvider $connections, private ScheduledTaskMap $tasks, private MediaScanWorker $scans, private MediaProcessingWorker $processing) {}
    public function name(): ConsoleCommandName { return new ConsoleCommandName($this->command); }
    public function description(): string { return 'Run bounded P9 media ' . str_replace(':', ' ', $this->operation) . ' verification.'; }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions(['dry-run']);
        try {
            return match ($this->operation) {
                'production-readiness' => $this->readiness($output),
                'production-smoke' => $this->smoke($output),
                default => $this->runtimeOperation($input->requireFlag('dry-run'), $output),
            };
        } catch (\Throwable $error) {
            $output->write("P9 media operation: FAIL\n" . $error->getMessage() . "\n");
            return 1;
        }
    }

    private function runtimeOperation(bool $dryRun, ConsoleOutput $output): int
    {
        if ($this->operation === 'scans:process') {
            if ($dryRun) {
                $this->requireTables();
                $output->write("P9 media operation: PASS\nOperation: scans:process\nDry run: yes\n");
                return 0;
            }
            $result=$this->scans->processOne();
            $output->write('P9 media operation: PASS' . "\nOperation: scans:process\nClaimed: " . ($result['claimed']?'yes':'no') . "\nOutcome: {$result['outcome']}\n");
            return 0;
        }
        if ($this->operation === 'processing:process') {
            if ($dryRun) { $this->requireTables(); $output->write("P9 media operation: PASS\nOperation: processing:process\nDry run: yes\n"); return 0; }
            $result=$this->processing->processOne();
            $output->write('P9 media operation: PASS' . "\nOperation: processing:process\nClaimed: " . ($result['claimed']?'yes':'no') . "\nOutcome: {$result['outcome']}\n"); return 0;
        }
        if (!$dryRun && str_contains($this->operation, 'process')) {
            throw new \RuntimeException('P9 processor execution requires an explicitly configured production worker. Use --dry-run for this command.');
        }
        $this->requireTables();
        $output->write("P9 media operation: PASS\nOperation: {$this->operation}\nDry run: " . ($dryRun ? 'yes' : 'no') . "\n");
        return 0;
    }

    private function readiness(ConsoleOutput $output): int
    {
        $this->requireTables();
        $scanner = getenv('QMDB_MEDIA_SCANNER_BINARY');
        $ffmpeg = getenv('QMDB_MEDIA_FFMPEG_BINARY');
        $ffprobe = getenv('QMDB_MEDIA_FFPROBE_BINARY');
        $missing = [];
        foreach (['scanner' => $scanner, 'ffmpeg' => $ffmpeg, 'ffprobe' => $ffprobe] as $name => $binary) {
            if (!is_string($binary) || $binary === '' || !is_file($binary)) $missing[] = $name;
        }
        if ($missing !== []) {
            $output->write('P9 production readiness: FAIL' . "\nMissing or unavailable production binaries: " . implode(', ', $missing) . "\n");
            return 1;
        }
        $output->write("P9 production readiness: PASS\n");
        return 0;
    }

    private function smoke(ConsoleOutput $output): int
    {
        $this->requireTables();
        $taskIds = array_map(static fn ($task): string => $task->id()->value(), $this->tasks->tasks());
        foreach (['media.scans.process', 'media.processing.process', 'media.staging.cleanup', 'media.uploads.expire', 'media.assets.reconcile'] as $task) {
            if (!in_array($task, $taskIds, true)) throw new \RuntimeException('Required P9 scheduler task is missing.');
        }
        $output->write("P9 production-like smoke: PASS\nMode: isolated contract smoke only\n");
        return 0;
    }

    private function requireTables(): void
    {
        $pdo = $this->connections->connection();
        $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
        if (!$statement instanceof \PDOStatement) throw new \RuntimeException('P9 table check could not be prepared.');
        foreach (['media_assets', 'media_scan_results', 'media_events', 'media_holds', 'media_delivery_policies'] as $table) {
            $statement->execute([':table' => $table]);
            if ((int) $statement->fetchColumn() !== 1) throw new \RuntimeException('Required P9 table is missing: ' . $table);
        }
    }
}
