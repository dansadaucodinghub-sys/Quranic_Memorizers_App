<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Seed;

use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

/**
 * Applies the governed metadata-reference correction after the immutable
 * source-registry seed has inserted the initial source records.
 */
final readonly class SeedCorrectQuranMetadataSourceReference implements Seed
{
    public function id(): SeedId
    {
        return new SeedId('20260911120000_correct_quran_metadata_source_reference');
    }

    public function description(): string
    {
        return 'Correct the approved Tanzil metadata source reference after source seeding.';
    }

    public function dependencies(): array
    {
        return [new SeedId('20260911110500_seed_quran_search_corpus_authorization')];
    }

    public function steps(): array
    {
        return [
            new SqlSeedStep(
                new SeedStepId('001_correct_metadata_reference'),
                'Set the approved Tanzil metadata source reference exactly once.',
                "UPDATE quran_reference_sources SET source_reference = 'https://tanzil.net/docs/quran_metadata', "
                . 'version = version + 1, updated_at = UTC_TIMESTAMP(6) '
                . "WHERE source_code = 'TANZIL_QURAN_METADATA_1_0' "
                . "AND source_reference = 'https://tanzil.net/download/'",
            ),
        ];
    }
}
