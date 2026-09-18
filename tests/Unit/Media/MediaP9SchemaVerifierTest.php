<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Media;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\MediaCatalog\Interface\Console\CompetitionP9VerifyConsoleCommand;
use Qmdb\Shared\Background\Scheduler\{Schedule, ScheduledTask, ScheduledTaskHandler, ScheduledTaskId, ScheduledTaskMap};
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\BufferedConsoleOutput;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final class MediaP9SchemaVerifierTest extends TestCase
{
    /** @return iterable<string,array{string,int,string}> */
    public static function scenarios(): iterable
    {
        yield 'complete contracts' => ['complete', 0, 'not production closeout'];
        yield 'missing governance table' => ['table', 1, 'media_governance_operations'];
        yield 'missing governance migrations' => ['migration', 1, 'migrations are incomplete'];
        yield 'missing receipt guard' => ['trigger', 1, 'receipt guards are incomplete'];
    }

    #[DataProvider('scenarios')]
    public function testGovernanceContractsAreMandatory(string $scenario, int $exit, string $message): void
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($scenario): PDOStatement {
            $statement = $this->createStub(PDOStatement::class);
            $parameters = [];
            $statement->method('execute')->willReturnCallback(static function (?array $values = null) use (&$parameters): bool {
                $parameters = $values ?? [];
                return true;
            });
            $statement->method('fetchColumn')->willReturnCallback(static function () use ($sql, $scenario, &$parameters): int {
                if (str_contains($sql, 'information_schema.tables')) {
                    return $scenario === 'table' && ($parameters[':table'] ?? null) === 'media_governance_operations' ? 0 : 1;
                }
                if (str_contains($sql, 'qmdb_schema_migrations')) {
                    self::assertStringContainsString('20260917120000_complete_media_governance', $sql);
                    self::assertStringContainsString('20260917121000_extend_media_governance_security', $sql);
                    return $scenario === 'migration' ? 3 : 6;
                }
                if (str_contains($sql, 'information_schema.triggers')) {
                    return $scenario === 'trigger' ? 1 : 2;
                }
                return str_contains($sql, 'authorization_permissions') ? 10 : 1;
            });
            return $statement;
        });
        $provider = $this->createStub(DatabaseConnectionProvider::class);
        $provider->method('connection')->willReturn($pdo);
        $tasks = [];
        foreach (['media.scans.process', 'media.processing.process', 'media.staging.cleanup', 'media.uploads.expire', 'media.assets.reconcile'] as $id) {
            $tasks[] = new ScheduledTask(new ScheduledTaskId($id), 'Synthetic media contract', $this->createStub(Schedule::class), $this->createStub(ScheduledTaskHandler::class), 60, 'media.processing');
        }
        $command = new CompetitionP9VerifyConsoleCommand($provider, new ScheduledTaskMap($tasks));
        $output = new BufferedConsoleOutput();
        self::assertSame($exit, $command->execute(new ConsoleInput(new ConsoleCommandName('competition:p9:verify'), []), $output));
        self::assertStringContainsString($message, $output->standardOutput());
        self::assertStringNotContainsString('P10 modules: 0', $output->standardOutput());
    }
}
