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
        // P7 deliberately creates a mutually-referencing publication and
        // package pair. Test isolation must dismantle that one directed link
        // before dropping either table; foreign-key enforcement remains on.
        $linkExists = $connection->prepare(
            "SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='competition_result_publications' AND constraint_name='fk_p7_publication_package' AND constraint_type='FOREIGN KEY'",
        );
        $linkExists->execute();
        if ((int) $linkExists->fetchColumn() === 1) {
            $connection->exec('ALTER TABLE competition_result_publications DROP FOREIGN KEY fk_p7_publication_package');
        }

        foreach (
            [
                // P9 children precede identity, tenancy, and person parent teardown.
                'media_consent_reviews', 'media_governance_operations', 'media_operation_receipts',
                'media_scan_results', 'media_events', 'media_holds', 'media_delivery_policies',
                'media_variants', 'media_processing_jobs', 'media_upload_parts',
                'media_upload_sessions', 'media_assets',
                // Downstream P8 tables must be removed before the P7 result
                // publication tables they reference. This remains an ordered
                // teardown; foreign-key checks are never disabled.
                'record_passport_share_entries', 'record_passport_shares',
                'record_passport_consents', 'record_passport_events',
                'record_passport_entries', 'record_passports',
                'legacy_record_import_events', 'legacy_records',
                'legacy_record_import_rows', 'legacy_record_import_batches',
                'trusted_archive_holds', 'trusted_archive_events',
                'trusted_archive_artifacts', 'trusted_archive_records',
                'trusted_archive_streams',
                'certificate_operations', 'certificate_issuance_jobs',
                'certificate_events', 'certificate_artifacts', 'certificates',
                'certificate_number_sequences', 'certificate_signing_keys',
                'certificate_templates',
                // P5/P6 tables must be removed before their Qur'an and
                // geography parents.
                'competition_p7_operations', 'competition_p7_outbox_messages',
                'competition_appeal_correction_authorizations', 'competition_appeal_decisions',
                'competition_appeal_reviewer_conflicts', 'competition_appeal_review_assignments',
                'competition_result_publication_projection_heads',
                'competition_result_publication_projections',
                'competition_result_publication_holds', 'competition_result_publication_events',
                'competition_result_packages', 'competition_result_publications',
                'competition_live_delivery_offsets', 'competition_live_projection_snapshots',
                'competition_live_projection_streams', 'competition_live_operations',
                'competition_live_events', 'competition_live_participant_states', 'competition_live_sessions',
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
