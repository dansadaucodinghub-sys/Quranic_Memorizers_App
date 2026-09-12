<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class CompetitionP6SchedulerArchitectureTest extends TestCase
{
    public function testP6RegistersTheClosedMaintenanceTaskSet(): void
    {
        $module = file_get_contents(dirname(__DIR__, 2) . '/src/Bootstrap/Module/CompetitionResultsModule.php');
        self::assertIsString($module);
        foreach (['competition.rounds.process', 'competition.score_sheets.remind', 'competition.appeal_windows.process', 'competition.score_sheets.reconcile', 'competition.results.reconcile'] as $task) {
            self::assertStringContainsString("'" . $task . "'", $module);
        }
        self::assertStringContainsString('CompetitionP6ScheduledMaintenanceTask::class', $module);
    }

    public function testP6MaintenanceCommandsAndProcessorAreNotP7Surfaces(): void
    {
        $console = file_get_contents(dirname(__DIR__, 2) . '/src/Bootstrap/Module/ConsoleFoundationModule.php');
        $processor = file_get_contents(dirname(__DIR__, 2) . '/src/Modules/CompetitionResults/Infrastructure/Persistence/CompetitionP6MaintenanceService.php');
        self::assertIsString($console);
        self::assertIsString($processor);
        foreach (['competition:rounds:process', 'competition:score-sheets:remind', 'competition:appeal-windows:process', 'competition:score-sheets:reconcile', 'competition:results:reconcile'] as $command) {
            self::assertStringContainsString("'" . $command . "'", $console);
        }
        self::assertStringNotContainsString('certificate', strtolower($processor));
        self::assertStringNotContainsString('payment', strtolower($processor));
    }
}
