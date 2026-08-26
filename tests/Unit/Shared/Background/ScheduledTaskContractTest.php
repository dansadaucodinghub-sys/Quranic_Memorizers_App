<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Background;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Background\Scheduler\FixedIntervalSchedule;
use Qmdb\Shared\Background\Scheduler\ScheduledTask;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskId;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskMap;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRegistry;
use Qmdb\Tests\Support\Background\TestScheduledTaskHandler;

final class ScheduledTaskContractTest extends TestCase
{
    #[DataProvider('invalidTaskIds')]
    public function testTaskIdRejectsUnsafeOrNonCanonicalSyntax(string $id): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ScheduledTaskId($id);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidTaskIds(): iterable
    {
        yield 'path' => ['../task'];
        yield 'class' => ['App\\Task'];
        yield 'uppercase' => ['Test.Task'];
        yield 'single segment' => ['task'];
    }

    public function testTaskMapIsDeterministicAndRejectsDuplicates(): void
    {
        $first = $this->task('zeta.task');
        $second = $this->task('alpha.task');
        $map = new ScheduledTaskMap([$first, $second]);

        self::assertSame(['alpha.task', 'zeta.task'], array_map(
            static fn (ScheduledTask $task): string => $task->id()->value(),
            $map->tasks(),
        ));

        $this->expectException(InvalidArgumentException::class);
        new ScheduledTaskMap([$first, $first]);
    }

    #[DataProvider('invalidLeases')]
    public function testTaskLeaseIsBounded(int $lease): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->task('test.task', $lease);
    }

    /** @return iterable<string, array{int}> */
    public static function invalidLeases(): iterable
    {
        yield 'zero' => [0];
        yield 'excessive' => [86_401];
    }

    public function testRegistryFreezesAndEmptyRegistryIsValid(): void
    {
        self::assertSame(0, (new ScheduledTaskRegistry())->build()->count());
        $registry = new ScheduledTaskRegistry();
        $registry->register($this->task('test.task'))->build();

        $this->expectException(LogicException::class);
        $registry->register($this->task('other.task'));
    }

    private function task(string $id, int $lease = 30): ScheduledTask
    {
        return new ScheduledTask(
            new ScheduledTaskId($id),
            'A registered deterministic test task.',
            new FixedIntervalSchedule(60),
            new TestScheduledTaskHandler(),
            $lease,
            'test.module',
        );
    }
}
