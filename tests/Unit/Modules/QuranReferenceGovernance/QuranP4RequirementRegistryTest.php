<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\QuranReferenceGovernance;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\QuranReferenceGovernance\Configuration\QuranP4RequirementRegistry;

final class QuranP4RequirementRegistryTest extends TestCase
{
    public function testItMapsEveryFixedP4RequirementExactlyOnce(): void
    {
        $registry = new QuranP4RequirementRegistry();
        $registry->assertValid();
        $entries = $registry->entries();

        self::assertCount(24, $entries);
        self::assertSame(
            ['QMDB-P4-B01' => 8, 'QMDB-P4-B02' => 8, 'QMDB-P4-B03' => 8],
            array_count_values(array_column($entries, 'batch')),
        );
        self::assertSame(range(1, 24), array_map(
            static fn (string $id): int => (int) substr($id, -3),
            array_keys($entries),
        ));
    }

    public function testItContainsOnlyExecutableEvidenceFields(): void
    {
        foreach ((new QuranP4RequirementRegistry())->entries() as $entry) {
            self::assertSame(
                ['batch', 'verifier', 'capability', 'test_group', 'release_inclusion'],
                array_keys($entry),
            );
            self::assertStringStartsWith('quran:', $entry['verifier']);
        }
    }
}
