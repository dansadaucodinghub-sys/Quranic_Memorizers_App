<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\CompetitionLive;

use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\CompetitionLive\Interface\Console\CompetitionP7ProductionReadinessConsoleCommand;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskMap;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Configuration\ConfigurationSource;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Input\ConsoleOption;
use Qmdb\Shared\Console\Input\ConsoleOptionName;
use Qmdb\Shared\Console\Output\BufferedConsoleOutput;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use RuntimeException;

final class CompetitionP7ProductionReadinessConsoleCommandTest extends TestCase
{
    public function testStrictModeFailsClosedBeforeAnyDatabaseAccessOutsideProductionLikeConfiguration(): void
    {
        $command = new CompetitionP7ProductionReadinessConsoleCommand(
            new ApplicationConfiguration(
                ApplicationEnvironment::LOCAL,
                false,
                new DateTimeZone('UTC'),
                ConfigurationSource::PROCESS,
            ),
            new class implements DatabaseConnectionProvider {
                public function connection(): PDO
                {
                    throw new RuntimeException('Database must not be contacted for an invalid strict profile.');
                }
            },
            new ScheduledTaskMap([]),
        );
        $output = new BufferedConsoleOutput();

        $exit = $command->execute(
            new ConsoleInput(new ConsoleCommandName('competition:p7:production-readiness:verify'), [
                new ConsoleOption(new ConsoleOptionName('production-like'), null),
            ]),
            $output,
        );

        self::assertSame(1, $exit);
        self::assertStringContainsString(
            'Strict verification requires staging or production APP_ENV.',
            $output->standardOutput(),
        );
    }
}
