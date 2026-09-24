<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Media;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\MediaProcessing\Application\MediaMaintenanceWorker;
use Qmdb\Modules\MediaProcessing\Application\MediaProcessingWorker;
use Qmdb\Modules\MediaProcessing\Application\MediaRuntimeScheduledTask;
use Qmdb\Modules\MediaProcessing\Application\MediaScanWorker;
use Qmdb\Shared\Background\Scheduler\ScheduledExecutionSlot;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskExecutionContext;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskId;
use Qmdb\Shared\Observability\Correlation\CorrelationId;

final class MediaRuntimeScheduledTaskTest extends TestCase
{
    public function testScanWorkerDiagnosticResultDoesNotEscapeTheSchedulerHandler(): void
    {
        $scan = $this->createMock(MediaScanWorker::class);
        $scan->expects(self::once())->method('processOne')->willReturn(['claimed' => false, 'outcome' => 'NO_WORK']);
        $task = new MediaRuntimeScheduledTask(
            $scan,
            $this->createStub(MediaProcessingWorker::class),
            $this->createStub(MediaMaintenanceWorker::class),
        );

        self::assertNull($task($this->context('media.scans.process')));
    }

    public function testProcessingWorkerDiagnosticResultDoesNotEscapeTheSchedulerHandler(): void
    {
        $processing = $this->createMock(MediaProcessingWorker::class);
        $processing->expects(self::once())->method('processOne')
            ->willReturn(['claimed' => false, 'outcome' => 'NO_WORK']);
        $task = new MediaRuntimeScheduledTask(
            $this->createStub(MediaScanWorker::class),
            $processing,
            $this->createStub(MediaMaintenanceWorker::class),
        );

        self::assertNull($task($this->context('media.processing.process')));
    }

    #[DataProvider('maintenanceTasks')]
    public function testMaintenanceDiagnosticResultDoesNotEscapeTheSchedulerHandler(
        string $taskId,
        string $operation,
    ): void {
        $maintenance = $this->createMock(MediaMaintenanceWorker::class);
        $maintenance->expects(self::once())->method('run')->with($operation, false)
            ->willReturn(['examined' => 0, 'changed' => 0, 'dry_run' => false]);
        $task = new MediaRuntimeScheduledTask(
            $this->createStub(MediaScanWorker::class),
            $this->createStub(MediaProcessingWorker::class),
            $maintenance,
        );

        self::assertNull($task($this->context($taskId)));
    }

    /** @return iterable<string, array{string,string}> */
    public static function maintenanceTasks(): iterable
    {
        yield 'staging cleanup' => ['media.staging.cleanup', 'staging:cleanup'];
        yield 'upload expiry' => ['media.uploads.expire', 'uploads:expire'];
        yield 'asset reconciliation' => ['media.assets.reconcile', 'assets:reconcile'];
    }

    private function context(string $taskId): ScheduledTaskExecutionContext
    {
        $now = new DateTimeImmutable('2026-09-23T00:00:00+00:00', new DateTimeZone('UTC'));
        return new ScheduledTaskExecutionContext(
            new ScheduledExecutionSlot(new ScheduledTaskId($taskId), $now),
            str_repeat('e', 32),
            new CorrelationId(str_repeat('c', 32)),
            1,
            $now,
            $now->modify('+120 seconds'),
        );
    }
}
