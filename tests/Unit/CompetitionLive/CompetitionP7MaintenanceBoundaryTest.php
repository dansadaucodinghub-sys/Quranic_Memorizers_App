<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\CompetitionLive;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\CompetitionAppealAdjudication\Infrastructure\Persistence\CompetitionAppealMaintenanceService;
use Qmdb\Modules\CompetitionLive\Infrastructure\Persistence\CompetitionP7LiveMaintenanceService;
use Qmdb\Modules\CompetitionPublication\Infrastructure\Persistence\CompetitionResultPublicationProjectionService;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Time\Clock;
use RuntimeException;

#[Group('P7FaultInjection')]
#[Group('P7Performance')]
final class CompetitionP7MaintenanceBoundaryTest extends TestCase
{
    public function testLiveOutboxMaintenanceRejectsUnboundedWorkBeforeOpeningADatabaseConnection(): void
    {
        $service = new CompetitionP7LiveMaintenanceService($this->unreachableDatabase(), $this->clock());

        $this->expectException(\InvalidArgumentException::class);
        $service->project(501);
    }

    public function testPublicationProjectionMaintenanceRejectsUnboundedWorkBeforeOpeningADatabaseConnection(): void
    {
        $service = new CompetitionResultPublicationProjectionService($this->unreachableDatabase(), $this->clock());

        $this->expectException(\InvalidArgumentException::class);
        $service->process(0);
    }

    public function testAppealMaintenanceRejectsUnboundedWorkBeforeOpeningADatabaseConnection(): void
    {
        $service = new CompetitionAppealMaintenanceService($this->unreachableDatabase());

        $this->expectException(\InvalidArgumentException::class);
        $service->reconcile(501);
    }

    public function testCanonicalProjectionPayloadRejectsFloatingPointAndControlCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        \Qmdb\Modules\CompetitionLive\Infrastructure\Persistence\CanonicalJson::encode([
            'sequence' => 1.0,
            'unsafe' => "\x1f",
        ]);
    }

    private function unreachableDatabase(): DatabaseConnectionProvider
    {
        return new class implements DatabaseConnectionProvider {
            public function connection(): PDO
            {
                throw new RuntimeException('The database must not be contacted for a rejected workload bound.');
            }
        };
    }

    private function clock(): Clock
    {
        return new class implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-09-13T00:00:00+00:00');
            }
        };
    }
}
