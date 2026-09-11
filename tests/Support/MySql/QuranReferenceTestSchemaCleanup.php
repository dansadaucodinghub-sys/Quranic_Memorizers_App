<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\MySql;

use PDO;

/**
 * Keeps legacy P2 fixtures isolated after the global Qur'an reference schema
 * was added to the serial shared MySQL test database.
 */
final class QuranReferenceTestSchemaCleanup
{
    public static function dropDependentTables(PDO $connection): void
    {
        foreach (
            [
                'quran_search_corpus_validation_operations',
                'quran_search_corpus_events',
                'quran_search_corpus_validations',
                'quran_ayah_search_texts',
                'quran_search_corpora',
                'quran_release_operations',
                'quran_release_events',
                'quran_release_validations',
                'quran_release_content_summaries',
                'quran_sajdah_markers',
                'quran_partitions',
                'quran_ayahs',
                'quran_surahs',
                'quran_release_manifests',
                'quran_release_artifacts',
                'quran_source_artifacts',
                'quran_reference_releases',
                'quran_reference_sources',
            ] as $table
        ) {
            $connection->exec('DROP TABLE IF EXISTS ' . $table);
        }
    }
}
