# Migration, Seed, and Bootstrap Plan

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B04 |
| Document Title | Migration, Seed, and Bootstrap Plan |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Database Architecture, Release Engineering, and Data Stewardship |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed implementation order; no executable migration or seed payload exists |
| Related Documents | [schema manifest](mysql-logical-schema.yaml); [seed catalog](controlled-taxonomy-and-seed-catalog.yaml); [readiness](13-schema-review-and-implementation-readiness.md) |

## Purpose

Define a dependency-safe future migration, seed-import and production bootstrap sequence with provenance, test, compatibility and recovery gates.

## Scope and prohibition

This document plans migrations; it does not contain executable SQL. Future implementation shall generate reviewed migrations from the approved manifest and ADR conventions, not execute this Markdown/YAML directly.

## Migration principles

- Use one-way migration identities linked to QMDB-MIG groups and manifest IDs.
- Create tables/primary/candidate keys before child foreign keys; add cross-group constraints only after parents exist.
- Use expand/migrate/contract, resumable keyset backfills and verification checkpoints.
- Assess MySQL algorithm/lock, replica lag, disk, rollback/compensation and backup recovery.
- Never roll back an official-data change by deleting history or disable integrity permanently.
- Symbolic score/percentage values and external datasets block dependent executable DDL/seeds.
- Production bootstrap uses individual invitation/Step-Up, not a shared password/unrestricted administrator.

## Ordered migration groups

### QMDB-MIG-001 — Foundation and platform metadata

| Field | Plan |
| --- | --- |
| Tables created | `permissions`; `feature_flags`; `feature_flag_versions`; `configuration_versions`; `operational_announcements`; `processing_purposes`; `privacy_notice_versions` |
| Table IDs | 7 mapped table IDs; exact ordered set in the schema manifest/migration plan |
| Prerequisites | Approved P0-CLOSE baseline and migration framework |
| Constraints added | Create local keys first; add 1 foreign-key relationships after parents exist. |
| Seed dependencies | QMDB-SEED-001; QMDB-SEED-012 |
| Circular-dependency handling | Create nullable workflow links only when domain-valid; add later foreign keys after both tables exist; never disable integrity permanently. |
| Backward compatibility | Use expand/migrate/contract, online-safe DDL evaluation, primary-key pagination and backfill checkpoints. |
| Rollback or compensation | Prefer forward compensating migration; do not roll back by deleting official data. |
| Test requirements | Migration up/down-or-compensate, metadata, constraint, tenant-isolation, fixture, rollback and restore tests. |
| Implementation phase | P2 |

### QMDB-MIG-002 — Workspaces and tenant boundaries

| Field | Plan |
| --- | --- |
| Tables created | `workspaces`; `workspace_settings`; `memberships`; `roles`; `role_permissions`; `membership_roles`; `administrative_scopes`; `scope_grants`; `competition_assignments` |
| Table IDs | 9 mapped table IDs; exact ordered set in the schema manifest/migration plan |
| Prerequisites | QMDB-MIG-001 |
| Constraints added | Create local keys first; add 21 foreign-key relationships after parents exist. |
| Seed dependencies | None before table creation |
| Circular-dependency handling | Create nullable workflow links only when domain-valid; add later foreign keys after both tables exist; never disable integrity permanently. |
| Backward compatibility | Use expand/migrate/contract, online-safe DDL evaluation, primary-key pagination and backfill checkpoints. |
| Rollback or compensation | Prefer forward compensating migration; do not roll back by deleting official data. |
| Test requirements | Migration up/down-or-compensate, metadata, constraint, tenant-isolation, fixture, rollback and restore tests. |
| Implementation phase | P2 |

### QMDB-MIG-003 — Identity, authentication, and sessions

| Field | Plan |
| --- | --- |
| Tables created | `user_accounts`; `account_email_addresses`; `account_phone_numbers`; `account_credentials`; `credential_history`; `auth_sessions`; `devices`; `mfa_methods`; `passkeys`; `verification_challenges`; `recovery_tokens`; `account_status_events`; `authentication_security_events` |
| Table IDs | 13 mapped table IDs; exact ordered set in the schema manifest/migration plan |
| Prerequisites | QMDB-MIG-002 |
| Constraints added | Create local keys first; add 13 foreign-key relationships after parents exist. |
| Seed dependencies | None before table creation |
| Circular-dependency handling | Create nullable workflow links only when domain-valid; add later foreign keys after both tables exist; never disable integrity permanently. |
| Backward compatibility | Use expand/migrate/contract, online-safe DDL evaluation, primary-key pagination and backfill checkpoints. |
| Rollback or compensation | Prefer forward compensating migration; do not roll back by deleting official data. |
| Test requirements | Migration up/down-or-compensate, metadata, constraint, tenant-isolation, fixture, rollback and restore tests. |
| Implementation phase | P2 |

### QMDB-MIG-004 — Authorization and scoped access

| Field | Plan |
| --- | --- |
| Tables created | `approval_requests`; `approval_decisions`; `temporary_privilege_grants` |
| Table IDs | 3 mapped table IDs; exact ordered set in the schema manifest/migration plan |
| Prerequisites | QMDB-MIG-003 |
| Constraints added | Create local keys first; add 6 foreign-key relationships after parents exist. |
| Seed dependencies | None before table creation |
| Circular-dependency handling | Create nullable workflow links only when domain-valid; add later foreign keys after both tables exist; never disable integrity permanently. |
| Backward compatibility | Use expand/migrate/contract, online-safe DDL evaluation, primary-key pagination and backfill checkpoints. |
| Rollback or compensation | Prefer forward compensating migration; do not roll back by deleting official data. |
| Test requirements | Migration up/down-or-compensate, metadata, constraint, tenant-isolation, fixture, rollback and restore tests. |
| Implementation phase | P2 |

### QMDB-MIG-005 — Geography

| Field | Plan |
| --- | --- |
| Tables created | `administrative_area_types`; `administrative_areas`; `administrative_area_aliases`; `administrative_area_history`; `venues`; `venue_areas` |
| Table IDs | 6 mapped table IDs; exact ordered set in the schema manifest/migration plan |
| Prerequisites | QMDB-MIG-004 |
| Constraints added | Create local keys first; add 8 foreign-key relationships after parents exist. |
| Seed dependencies | QMDB-SEED-003; QMDB-SEED-015 |
| Circular-dependency handling | Create nullable workflow links only when domain-valid; add later foreign keys after both tables exist; never disable integrity permanently. |
| Backward compatibility | Use expand/migrate/contract, online-safe DDL evaluation, primary-key pagination and backfill checkpoints. |
| Rollback or compensation | Prefer forward compensating migration; do not roll back by deleting official data. |
| Test requirements | Migration up/down-or-compensate, metadata, constraint, tenant-isolation, fixture, rollback and restore tests. |
| Implementation phase | P3 |

### QMDB-MIG-006 — Organizations

| Field | Plan |
| --- | --- |
| Tables created | `organization_types`; `organizations`; `organization_units`; `organization_relationships`; `organization_coverage_areas`; `organization_verification_cases`; `organization_verification_evidence`; `organization_verification_decisions`; `organization_branding_configurations` |
| Table IDs | 9 mapped table IDs; exact ordered set in the schema manifest/migration plan |
| Prerequisites | QMDB-MIG-005 |
| Constraints added | Create local keys first; add 9 foreign-key relationships after parents exist. |
| Seed dependencies | None before table creation |
| Circular-dependency handling | Create nullable workflow links only when domain-valid; add later foreign keys after both tables exist; never disable integrity permanently. |
| Backward compatibility | Use expand/migrate/contract, online-safe DDL evaluation, primary-key pagination and backfill checkpoints. |
| Rollback or compensation | Prefer forward compensating migration; do not roll back by deleting official data. |
| Test requirements | Migration up/down-or-compensate, metadata, constraint, tenant-isolation, fixture, rollback and restore tests. |
| Implementation phase | P3 |

### QMDB-MIG-007 — People, profiles, guardianship, and consent

| Field | Plan |
| --- | --- |
| Tables created | `persons`; `person_names`; `person_profiles`; `public_profiles`; `memorizer_profiles`; `competitor_profiles`; `judge_profiles`; `coach_profiles`; `teacher_profiles`; `person_affiliations`; `person_geography_representations`; `guardian_relationships`; `consent_records`; `consent_events`; `identity_evidence`; `person_merge_cases`; `person_merge_events` |
| Table IDs | 17 mapped table IDs; exact ordered set in the schema manifest/migration plan |
| Prerequisites | QMDB-MIG-006 |
| Constraints added | Create local keys first; add 23 foreign-key relationships after parents exist. |
| Seed dependencies | None before table creation |
| Circular-dependency handling | Create nullable workflow links only when domain-valid; add later foreign keys after both tables exist; never disable integrity permanently. |
| Backward compatibility | Use expand/migrate/contract, online-safe DDL evaluation, primary-key pagination and backfill checkpoints. |
| Rollback or compensation | Prefer forward compensating migration; do not roll back by deleting official data. |
| Test requirements | Migration up/down-or-compensate, metadata, constraint, tenant-isolation, fixture, rollback and restore tests. |
| Implementation phase | P3 |

### QMDB-MIG-008 — Quran reference governance

| Field | Plan |
| --- | --- |
| Tables created | `quran_text_releases`; `quran_release_sources`; `quran_readings`; `surahs`; `ayahs`; `juz_ranges`; `hizb_ranges`; `rub_ranges`; `page_references`; `passage_ranges`; `tajwid_rule_taxonomy`; `competition_mistake_taxonomy`; `quran_release_approvals`; `quran_release_checksums`; `quran_release_correction_history` |
| Table IDs | 15 mapped table IDs; exact ordered set in the schema manifest/migration plan |
| Prerequisites | QMDB-MIG-007 |
| Constraints added | Create local keys first; add 23 foreign-key relationships after parents exist. |
| Seed dependencies | QMDB-SEED-016 |
| Circular-dependency handling | Create nullable workflow links only when domain-valid; add later foreign keys after both tables exist; never disable integrity permanently. |
| Backward compatibility | Use expand/migrate/contract, online-safe DDL evaluation, primary-key pagination and backfill checkpoints. |
| Rollback or compensation | Prefer forward compensating migration; do not roll back by deleting official data. |
| Test requirements | Migration up/down-or-compensate, metadata, constraint, tenant-isolation, fixture, rollback and restore tests. |
| Implementation phase | P4 |

### QMDB-MIG-009 — Competition configuration and rulesets

| Field | Plan |
| --- | --- |
| Tables created | `competition_series`; `competition_editions`; `competition_organization_relationships`; `competition_administrative_areas`; `competition_venues`; `categories`; `divisions`; `age_bands`; `stages`; `rounds`; `competition_sessions`; `rulesets`; `ruleset_versions`; `scoring_criteria`; `deduction_rules`; `aggregation_rules`; `tie_break_rules`; `disqualification_rules`; `publication_rules`; `appeal_rules` |
| Table IDs | 20 mapped table IDs; exact ordered set in the schema manifest/migration plan |
| Prerequisites | QMDB-MIG-008 |
| Constraints added | Create local keys first; add 45 foreign-key relationships after parents exist. |
| Seed dependencies | None before table creation |
| Circular-dependency handling | Create nullable workflow links only when domain-valid; add later foreign keys after both tables exist; never disable integrity permanently. |
| Backward compatibility | Use expand/migrate/contract, online-safe DDL evaluation, primary-key pagination and backfill checkpoints. |
| Rollback or compensation | Prefer forward compensating migration; do not roll back by deleting official data. |
| Test requirements | Migration up/down-or-compensate, metadata, constraint, tenant-isolation, fixture, rollback and restore tests. |
| Implementation phase | P5 |

### QMDB-MIG-010 — Registration, eligibility, and participant snapshots

| Field | Plan |
| --- | --- |
| Tables created | `registrations`; `registration_categories`; `nominations`; `nomination_authorities`; `eligibility_checks`; `eligibility_evidence`; `registration_decisions`; `participant_snapshots`; `participant_snapshot_affiliations`; `participant_snapshot_geography`; `check_ins`; `waitlist_entries`; `registration_exceptions` |
| Table IDs | 13 mapped table IDs; exact ordered set in the schema manifest/migration plan |
| Prerequisites | QMDB-MIG-009 |
| Constraints added | Create local keys first; add 33 foreign-key relationships after parents exist. |
| Seed dependencies | None before table creation |
| Circular-dependency handling | Create nullable workflow links only when domain-valid; add later foreign keys after both tables exist; never disable integrity permanently. |
| Backward compatibility | Use expand/migrate/contract, online-safe DDL evaluation, primary-key pagination and backfill checkpoints. |
| Rollback or compensation | Prefer forward compensating migration; do not roll back by deleting official data. |
| Test requirements | Migration up/down-or-compensate, metadata, constraint, tenant-isolation, fixture, rollback and restore tests. |
| Implementation phase | P5 |

### QMDB-MIG-011 — Scheduling, judging, and performances

| Field | Plan |
| --- | --- |
| Tables created | `competition_schedules`; `session_schedules`; `draw_orders`; `judge_panels`; `judge_panel_members`; `judge_assignments`; `judge_qualification_evidence`; `conflict_declarations`; `conflict_reviews`; `recusal_records`; `judge_replacements` |
| Table IDs | 11 mapped table IDs; exact ordered set in the schema manifest/migration plan |
| Prerequisites | QMDB-MIG-010 |
| Constraints added | Create local keys first; add 27 foreign-key relationships after parents exist. |
| Seed dependencies | None before table creation |
| Circular-dependency handling | Create nullable workflow links only when domain-valid; add later foreign keys after both tables exist; never disable integrity permanently. |
| Backward compatibility | Use expand/migrate/contract, online-safe DDL evaluation, primary-key pagination and backfill checkpoints. |
| Rollback or compensation | Prefer forward compensating migration; do not roll back by deleting official data. |
| Test requirements | Migration up/down-or-compensate, metadata, constraint, tenant-isolation, fixture, rollback and restore tests. |
| Implementation phase | P6 |

### QMDB-MIG-012 — Score sheets, versions, and aggregation

| Field | Plan |
| --- | --- |
| Tables created | `performances`; `performance_passages`; `performance_events`; `performance_incidents`; `score_sheets`; `score_sheet_versions`; `score_items`; `score_deductions`; `score_mistakes`; `score_signatures`; `score_submission_receipts`; `score_reopening_requests`; `score_reopening_approvals`; `panel_aggregations`; `aggregation_inputs`; `tie_break_evaluations`; `score_anomaly_flags`; `calculation_traces` |
| Table IDs | 18 mapped table IDs; exact ordered set in the schema manifest/migration plan |
| Prerequisites | QMDB-MIG-011 |
| Constraints added | Create local keys first; add 51 foreign-key relationships after parents exist. |
| Seed dependencies | None before table creation |
| Circular-dependency handling | Create nullable workflow links only when domain-valid; add later foreign keys after both tables exist; never disable integrity permanently. |
| Backward compatibility | Use expand/migrate/contract, online-safe DDL evaluation, primary-key pagination and backfill checkpoints. |
| Rollback or compensation | Prefer forward compensating migration; do not roll back by deleting official data. |
| Test requirements | Migration up/down-or-compensate, metadata, constraint, tenant-isolation, fixture, rollback and restore tests. |
| Implementation phase | P6 |

### QMDB-MIG-013 — Results, appeals, and corrections

| Field | Plan |
| --- | --- |
| Tables created | `result_snapshots`; `rankings`; `placements`; `result_approvals`; `result_finalization_bundles`; `result_corrections`; `result_supersession_links`; `appeals`; `appeal_evidence`; `appeal_assignments`; `appeal_reviews`; `appeal_decisions`; `appeal_events` |
| Table IDs | 13 mapped table IDs; exact ordered set in the schema manifest/migration plan |
| Prerequisites | QMDB-MIG-012 |
| Constraints added | Create local keys first; add 30 foreign-key relationships after parents exist. |
| Seed dependencies | None before table creation |
| Circular-dependency handling | Create nullable workflow links only when domain-valid; add later foreign keys after both tables exist; never disable integrity permanently. |
| Backward compatibility | Use expand/migrate/contract, online-safe DDL evaluation, primary-key pagination and backfill checkpoints. |
| Rollback or compensation | Prefer forward compensating migration; do not roll back by deleting official data. |
| Test requirements | Migration up/down-or-compensate, metadata, constraint, tenant-isolation, fixture, rollback and restore tests. |
| Implementation phase | P7 |

### QMDB-MIG-014 — Certificates, records, and provenance

| Field | Plan |
| --- | --- |
| Tables created | `certificate_templates`; `certificate_template_versions`; `certificates`; `certificate_signatures`; `certificate_revocations`; `certificate_supersession_links`; `certificate_verification_events`; `competition_records`; `record_provenance`; `record_evidence`; `record_disputes`; `record_verification_decisions`; `legacy_import_batches`; `legacy_source_files`; `legacy_import_rows`; `legacy_import_validation_issues`; `legacy_import_approvals`; `legacy_import_reconciliation` |
| Table IDs | 18 mapped table IDs; exact ordered set in the schema manifest/migration plan |
| Prerequisites | QMDB-MIG-013 |
| Constraints added | Create local keys first; add 39 foreign-key relationships after parents exist. |
| Seed dependencies | None before table creation |
| Circular-dependency handling | Create nullable workflow links only when domain-valid; add later foreign keys after both tables exist; never disable integrity permanently. |
| Backward compatibility | Use expand/migrate/contract, online-safe DDL evaluation, primary-key pagination and backfill checkpoints. |
| Rollback or compensation | Prefer forward compensating migration; do not roll back by deleting official data. |
| Test requirements | Migration up/down-or-compensate, metadata, constraint, tenant-isolation, fixture, rollback and restore tests. |
| Implementation phase | P8 |

### QMDB-MIG-015 — Media

| Field | Plan |
| --- | --- |
| Tables created | `media_assets`; `media_upload_authorizations`; `media_storage_objects`; `media_variants`; `media_hashes`; `media_consent_links`; `media_processing_jobs`; `media_processing_attempts`; `media_metadata`; `media_publications`; `media_moderation_states`; `media_retention_states`; `media_access_events` |
| Table IDs | 13 mapped table IDs; exact ordered set in the schema manifest/migration plan |
| Prerequisites | QMDB-MIG-014 |
| Constraints added | Create local keys first; add 27 foreign-key relationships after parents exist. |
| Seed dependencies | None before table creation |
| Circular-dependency handling | Create nullable workflow links only when domain-valid; add later foreign keys after both tables exist; never disable integrity permanently. |
| Backward compatibility | Use expand/migrate/contract, online-safe DDL evaluation, primary-key pagination and backfill checkpoints. |
| Rollback or compensation | Prefer forward compensating migration; do not roll back by deleting official data. |
| Test requirements | Migration up/down-or-compensate, metadata, constraint, tenant-isolation, fixture, rollback and restore tests. |
| Implementation phase | P9 |

### QMDB-MIG-016 — Recitation Clips and moderation

| Field | Plan |
| --- | --- |
| Tables created | `recitation_clips`; `social_posts`; `post_media`; `post_quran_tags`; `post_competition_links`; `follows`; `reactions`; `bookmarks`; `comments`; `comment_versions`; `blocks`; `mutes`; `content_reports`; `report_evidence`; `moderation_cases`; `moderation_assignments`; `moderation_actions`; `content_appeals`; `content_appeal_decisions`; `creator_analytics_projections`; `feed_events` |
| Table IDs | 21 mapped table IDs; exact ordered set in the schema manifest/migration plan |
| Prerequisites | QMDB-MIG-015 |
| Constraints added | Create local keys first; add 52 foreign-key relationships after parents exist. |
| Seed dependencies | None before table creation |
| Circular-dependency handling | Create nullable workflow links only when domain-valid; add later foreign keys after both tables exist; never disable integrity permanently. |
| Backward compatibility | Use expand/migrate/contract, online-safe DDL evaluation, primary-key pagination and backfill checkpoints. |
| Rollback or compensation | Prefer forward compensating migration; do not roll back by deleting official data. |
| Test requirements | Migration up/down-or-compensate, metadata, constraint, tenant-isolation, fixture, rollback and restore tests. |
| Implementation phase | P10 |

### QMDB-MIG-017 — Notifications and integrations

| Field | Plan |
| --- | --- |
| Tables created | `notifications`; `notification_preferences`; `notification_templates`; `notification_deliveries`; `notification_delivery_attempts`; `notification_dead_letters`; `api_clients`; `api_client_scopes`; `api_credentials`; `webhook_subscriptions`; `webhook_deliveries`; `webhook_delivery_attempts`; `integration_events`; `external_provider_references` |
| Table IDs | 14 mapped table IDs; exact ordered set in the schema manifest/migration plan |
| Prerequisites | QMDB-MIG-016 |
| Constraints added | Create local keys first; add 21 foreign-key relationships after parents exist. |
| Seed dependencies | None before table creation |
| Circular-dependency handling | Create nullable workflow links only when domain-valid; add later foreign keys after both tables exist; never disable integrity permanently. |
| Backward compatibility | Use expand/migrate/contract, online-safe DDL evaluation, primary-key pagination and backfill checkpoints. |
| Rollback or compensation | Prefer forward compensating migration; do not roll back by deleting official data. |
| Test requirements | Migration up/down-or-compensate, metadata, constraint, tenant-isolation, fixture, rollback and restore tests. |
| Implementation phase | P7 |

### QMDB-MIG-018 — Privacy, support, and security operations

| Field | Plan |
| --- | --- |
| Tables created | `support_access_grants`; `break_glass_grants`; `privacy_requests`; `privacy_request_events`; `privacy_request_assignments`; `retention_policy_records`; `data_holds`; `anonymization_events`; `data_export_deliveries` |
| Table IDs | 9 mapped table IDs; exact ordered set in the schema manifest/migration plan |
| Prerequisites | QMDB-MIG-017 |
| Constraints added | Create local keys first; add 11 foreign-key relationships after parents exist. |
| Seed dependencies | None before table creation |
| Circular-dependency handling | Create nullable workflow links only when domain-valid; add later foreign keys after both tables exist; never disable integrity permanently. |
| Backward compatibility | Use expand/migrate/contract, online-safe DDL evaluation, primary-key pagination and backfill checkpoints. |
| Rollback or compensation | Prefer forward compensating migration; do not roll back by deleting official data. |
| Test requirements | Migration up/down-or-compensate, metadata, constraint, tenant-isolation, fixture, rollback and restore tests. |
| Implementation phase | P2 |

### QMDB-MIG-019 — Audit, outbox, and idempotency

| Field | Plan |
| --- | --- |
| Tables created | `audit_events`; `audit_checkpoints`; `audit_verification_runs`; `outbox_events`; `idempotency_records` |
| Table IDs | 5 mapped table IDs; exact ordered set in the schema manifest/migration plan |
| Prerequisites | QMDB-MIG-018 |
| Constraints added | Create local keys first; add 2 foreign-key relationships after parents exist. |
| Seed dependencies | None before table creation |
| Circular-dependency handling | Create nullable workflow links only when domain-valid; add later foreign keys after both tables exist; never disable integrity permanently. |
| Backward compatibility | Use expand/migrate/contract, online-safe DDL evaluation, primary-key pagination and backfill checkpoints. |
| Rollback or compensation | Prefer forward compensating migration; do not roll back by deleting official data. |
| Test requirements | Migration up/down-or-compensate, metadata, constraint, tenant-isolation, fixture, rollback and restore tests. |
| Implementation phase | P12 |

### QMDB-MIG-020 — Projections, reporting, and offline operations

| Field | Plan |
| --- | --- |
| Tables created | `search_projection_status`; `public_record_projections`; `live_scoreboard_projections`; `reporting_projection_status`; `export_jobs`; `export_artifacts`; `export_access_records`; `scheduled_reports`; `background_job_ledger`; `dead_letter_records`; `offline_assignment_packages`; `offline_submission_events`; `synchronization_batches`; `synchronization_conflicts`; `venue_edge_node_registrations`; `venue_reconciliation_reports` |
| Table IDs | 16 mapped table IDs; exact ordered set in the schema manifest/migration plan |
| Prerequisites | QMDB-MIG-019 |
| Constraints added | Create local keys first; add 24 foreign-key relationships after parents exist. |
| Seed dependencies | None before table creation |
| Circular-dependency handling | Create nullable workflow links only when domain-valid; add later foreign keys after both tables exist; never disable integrity permanently. |
| Backward compatibility | Use expand/migrate/contract, online-safe DDL evaluation, primary-key pagination and backfill checkpoints. |
| Rollback or compensation | Prefer forward compensating migration; do not roll back by deleting official data. |
| Test requirements | Migration up/down-or-compensate, metadata, constraint, tenant-isolation, fixture, rollback and restore tests. |
| Implementation phase | P11 |

## Seed strategy

The machine-readable [controlled taxonomy and seed catalog](controlled-taxonomy-and-seed-catalog.yaml) defines 22 taxonomies and 16 planned seed datasets. Processing Purpose and Privacy Notice Version foundations are in QMDB-MIG-001 because Consent rows capture the exact notice version in QMDB-MIG-007. Every seed contains source authority/version/acquisition/checksum/importer, row/checksum report, mapping exceptions, reviewer decisions, activation and compensation.

| Seed class | Behavior |
| --- | --- |
| System permission codes | Repository-controlled stable codes; no wildcard permission. |
| Baseline role templates | Least-privilege Workspace templates; no unrestricted administrator. |
| Technical lifecycle statuses | Derived from approved B02 state machines; historical codes never repurposed. |
| Governed global taxonomy | Authority-approved/versioned import with historical preservation. |
| Workspace-configurable labels | Presentation only; stable semantics/authorization remain governed. |
| Nigeria geography | Blocked by OD-061; no fabricated codes. |
| Quran data | Blocked by OD-063; exact checksum, technical and dual qualified review; no handwritten seed. |
| Competition mistake/rules | Blocked until qualified authority approves source/version; no invented scoring. |

Seeds are idempotent by dataset ID/version/checksum. A changed checksum under the same version is rejected and investigated. Production values are not silently updated by startup.

## Bootstrap order

1. Verify manifest checksum, migration ledger, MySQL mode/collation, backup and rollback readiness.
2. Create platform context, Processing Purposes/Privacy Notice foundations and configuration version without a universal tenant bypass.
3. Import system permissions and approved role templates.
4. Import Nigeria hierarchy only after OD-061.
5. Import approved Organization/provenance/taxonomy codes.
6. Invite a named initial administrator with strong enrollment and scoped assignment; no shared/default password.
7. Create Audit Chain genesis and external-checkpoint configuration.
8. Register KMS/signing/storage references only; no secrets in MySQL.
9. Import/activate first Quran release only after OD-063.
10. Activate approved feature/configuration versions and conservative defaults.
11. Run metadata, FK/check/index, tenant, seed, authorization, audit/outbox and restore smoke tests.
12. Record actor, hashes, migration/seed versions, results and approval evidence.

## Migration verification evidence

Each group produces review, dry-run timings, lock/algorithm assessment, pre/post metadata, row/orphan counts, constraint checks, exact-type scan, Workspace-negative tests, compensation rehearsal, backup/restore checkpoint and release approval. Long changes have progress telemetry and abort criteria.

## Related documents

- [Schema conventions](03-mysql-schema-conventions.md)
- [Volume, archival and partitioning](12-volume-archival-and-partitioning-strategy.md)
- [Project risk register](../project/risk-register.md)

