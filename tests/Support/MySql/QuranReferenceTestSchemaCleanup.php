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
                // P5/P6 tables must be removed before their Qur'an and
                // geography parents. This is an ordered teardown; it never
                // disables foreign-key checks.
                'competition_p6_operations', 'competition_notification_intents',
                'competition_appeal_events', 'competition_appeal_windows', 'competition_appeals',
                'competition_public_result_consents', 'competition_disqualifications',
                'competition_result_events', 'competition_score_sheet_events', 'competition_score_penalties',
                'competition_result_rows', 'competition_result_runs', 'competition_score_entries',
                'competition_score_sheets', 'competition_tie_break_rules', 'competition_penalty_rules',
                'competition_scoring_criteria', 'competition_scoring_rubrics', 'competition_judge_conflicts',
                'competition_judge_assignment_events', 'competition_judge_assignments',
                'competition_judge_panels', 'competition_judges', 'competition_round_events',
                'competition_round_participants', 'competition_rounds',
                'competition_registration_events', 'competition_registration_reviews',
                'competition_registration_consents', 'competition_registration_eligibility_evidence',
                'competition_roster_entries', 'competition_rosters', 'competition_registrations',
                'competition_edition_events', 'competition_edition_configuration_snapshots',
                'competition_category_capacity_states', 'competition_registration_windows',
                'competition_eligibility_rules', 'competition_category_quran_segments',
                'competition_categories', 'competition_venues', 'competition_edition_organizers',
                'competition_editions', 'competition_program_organizers', 'competition_programs',
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
