<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Qmdb\Bootstrap\ApplicationMetadata;
use ReflectionClass;

final class ApplicationMetadataTest extends TestCase
{
    public function testCurrentMetadataExposesTheApprovedIdentity(): void
    {
        $metadata = ApplicationMetadata::current();

        self::assertSame('Qur’an Memorizer DB', $metadata->applicationName());
        self::assertSame('QMDB', $metadata->applicationCode());
        self::assertSame('QMDB-P0-FRZ-001', $metadata->frozenBaseline());
        self::assertSame('P2', $metadata->currentPhase());
        self::assertSame('QMDB-P2-B09', $metadata->currentBatch());
        self::assertSame('0.1.0-dev', $metadata->developmentVersion());
        self::assertSame(
            [
                'application_name' => 'Qur’an Memorizer DB',
                'application_code' => 'QMDB',
                'frozen_baseline' => 'QMDB-P0-FRZ-001',
                'current_phase' => 'P2',
                'current_batch' => 'QMDB-P2-B09',
                'development_version' => '0.1.0-dev',
            ],
            $metadata->toArray(),
        );
        self::assertSame(
            [
                'Application Name: Qur’an Memorizer DB',
                'Application Code: QMDB',
                'Frozen Baseline: QMDB-P0-FRZ-001',
                'Current Phase: P2',
                'Current Batch: QMDB-P2-B09',
                'Development Version: 0.1.0-dev',
            ],
            $metadata->toCliLines(),
        );
    }

    public function testMetadataIsImmutable(): void
    {
        $reflection = new ReflectionClass(ApplicationMetadata::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());

        foreach ($reflection->getProperties() as $property) {
            self::assertTrue($property->isReadOnly());
        }
    }
}
