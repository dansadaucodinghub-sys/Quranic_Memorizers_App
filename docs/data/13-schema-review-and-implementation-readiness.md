# Schema Review and Implementation Readiness

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B04 |
| Document Title | Schema Review and Implementation Readiness |
| Document Version | 1.0.0 |
| Document Status | Controlled draft readiness assessment |
| Document Owner Role | Architecture, Security, Data, and Release Governance |
| Last Updated | 2026-08-24 |
| Approval Status | B04 documentation complete; P0-CLOSE must decide baseline readiness |
| Related Documents | [schema manifest](mysql-logical-schema.yaml); [traceability](../requirements/P0-B04-traceability-matrix.md); [project state](../project/project-state.md) |

## Purpose

Assess whether the logical data architecture covers approved requirements, identify implementation blockers/dependencies, and define the evidence P0-CLOSE must examine before freezing the baseline.

## Scope and conclusion

The logical model is complete as a P0-B04 controlled draft. It is **not yet an approved executable schema**. P1 cannot generate final migrations until the P1-blocking identifier/isolation/retry conventions and the proposed B04 ADRs are approved or safely parameterized at P0-CLOSE. Later domain modules remain gated by their listed policy/domain/provider decisions.

## Readiness assessment

| Review Area | Result | Evidence and boundary |
| --- | --- | --- |
| Requirement coverage | PASS | 32 Data Requirements map B01/B02/B03 authority and all 250 tables. |
| Aggregate completeness | PASS | 38 aggregates, 166 entities and 32 Value Objects have stable IDs and ownership. |
| Table/column completeness | PASS | 250 tables and 1909 columns occur once in dictionary and YAML. |
| Relationship/constraint completeness | PASS | 469 relationships and 785 constraints are catalogued; executable DDL review remains future work. |
| Tenant isolation | PASS | 158 tenant tables have non-null Workspace ownership; 172 tenant-to-tenant relationships are composite Workspace-aware. |
| Official record integrity | PASS | Participant Snapshot, Score Sheet Version, Result and Certificate histories are immutable/supersedable with RESTRICT deletion. |
| Versioning/lifecycle | PASS | Every table has lifecycle, versioning, deletion/archive and audit policy. |
| Quran data integrity | CONDITIONAL | Release/source/Reading/checksum/approval/supersession model is complete; source/collation/normalization decisions remain open. |
| Minor-data protection | PASS | Guardian/Consent/media/public projection boundaries preserve protective defaults and withdrawal propagation. |
| Certificate integrity | CONDITIONAL | Result/template/hash/signature/revocation/supersession lineage is complete; serial/code/custody decisions remain open. |
| Media separation | PASS | Only metadata/object references/hashes are in MySQL; no media binary column exists. |
| Audit/outbox/idempotency | PASS | Append-oriented canonical audit/checkpoint, transactional outbox and scoped idempotency models exist. |
| Query access paths | PASS | 47 principal patterns and 658 purpose-driven indexes are catalogued; later EXPLAIN evidence required. |
| Migration ordering | PASS | 20 dependency-ordered groups cover all tables with compatibility/compensation/tests. |
| Seed strategy | CONDITIONAL | 22 taxonomies and 16 seed datasets have provenance gates; authoritative geography/Quran/policy data is intentionally absent. |
| Classification/lineage | PASS | Every table has owner/classification/handling; 10 lineage paths and 24 data-quality rules exist. |
| Open decisions | CONDITIONAL | P1 blockers and later policy/domain/provider dependencies are explicitly classified below. |
| Implementation code | NOT STARTED | Correct for P0-B04: no PHP, SQL, migrations or infrastructure were generated. |

## State-machine storage validation

| State Machine | Aggregate | Authoritative State Table | Historical/Transition Evidence | Storage Rule |
| --- | --- | --- | --- | --- |
| QMDB-SM-001 | User Account | `user_accounts` | account_status_events; audit_events | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-002 | Workspace | `workspaces` | audit_events; outbox_events | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-003 | Organization Verification | `organization_verification_cases` | organization_verification_decisions; audit_events | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-004 | Guardian Relationship | `guardian_relationships` | consent_events; audit_events | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-005 | Consent Record | `consent_records` | consent_events; audit_events | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-006 | Quran Text Release | `quran_text_releases` | quran_release_approvals; quran_release_correction_history | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-007 | Competition Edition | `competition_editions` | audit_events; outbox_events | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-008 | Ruleset Version | `ruleset_versions` | quran_release_approvals; audit_events | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-009 | Registration | `registrations` | registration_decisions; audit_events | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-010 | Eligibility Review | `eligibility_checks` | registration_decisions; audit_events | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-011 | Participant Check-In | `check_ins` | audit_events; outbox_events | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-012 | Judge Assignment | `judge_assignments` | judge_replacements; recusal_records; audit_events | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-013 | Conflict Declaration | `conflict_declarations` | conflict_reviews; recusal_records | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-014 | Performance | `performances` | performance_events; performance_incidents | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-015 | Score Sheet | `score_sheets` | score_sheet_versions; reopening requests/approvals; audit_events | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-016 | Result | `result_snapshots` | result_corrections; result_supersession_links; audit_events | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-017 | Appeal | `appeals` | appeal_events; appeal_decisions; audit_events | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-018 | Certificate | `certificates` | certificate_revocations; supersession links; verification events | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-019 | Legacy Import Batch | `legacy_import_batches` | validation issues; approvals; reconciliation | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-020 | Media Asset | `media_assets` | processing attempts; moderation/publication/retention states | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-021 | Recitation Clip | `recitation_clips` | social_posts; moderation_actions; audit_events | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-022 | Moderation Case | `moderation_cases` | moderation_actions; assignments; audit_events | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-023 | Content Appeal | `content_appeals` | content_appeal_decisions; audit_events | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-024 | Privacy Request | `privacy_requests` | privacy_request_events; assignments; data_export_deliveries | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-025 | Support Access Grant | `support_access_grants` | audit_events; authentication_security_events | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-026 | Break-Glass Grant | `break_glass_grants` | audit_events; authentication_security_events | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-027 | Outbox Event | `outbox_events` | dead_letter_records; integration_events | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-028 | Notification Delivery | `notification_deliveries` | notification_delivery_attempts; notification_dead_letters | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |
| QMDB-SM-029 | Offline Submission Package | `offline_assignment_packages` | offline_submission_events; synchronization batches/conflicts; reconciliation | Current state is explicit; transitions are authorized/audited; terminal states require governed reopening, supersession, revocation or successor—not silent mutation. |

All 29 B02 state machines can represent pending/active/suspended/cancelled/expired/terminal states as applicable, authoritative occurrence/timeout times, version checks and immutable transition evidence. Exact allowed status codes remain synchronized with the B02 machines and controlled taxonomy; generic labels cannot add transitions.

## Unresolved-issue classification

| Decision(s) | Issue | Classification | Decision Owner | Conservative implementation boundary |
| --- | --- | --- | --- | --- |
| OD-047 | Public identifier convention | BLOCKS P1 | Architecture and Database Governance | UUIDv7 candidate is parameterized; do not emit public-ID DDL/library contract. |
| OD-048 | Internal key strategy | BLOCKS P1 | Database Architecture | BIGINT UNSIGNED is proposed; P1 migration generator waits for approval. |
| OD-056 | Default transaction isolation | BLOCKS P1 | Database Architecture and Security | Critical operations are explicit, but connection baseline must be approved before repository framework. |
| OD-057 | Deadlock retry policy | BLOCKS P1 | Database Architecture and SRE | No unbounded/silent retry; framework contract requires approved classes/backoff/telemetry. |
| OD-051 | Email normalization | BLOCKS LATER MODULE | Identity, Security and Privacy Governance | Lookup hash/active-key shape is reserved; normalization test vectors required before P2. |
| OD-052 | Phone normalization | BLOCKS LATER MODULE | Identity, Security and Privacy Governance | Lookup hash/active-key shape is reserved; numbering/format test vectors required before P2. |
| OD-058 | Field-level encryption selection | BLOCKS LATER MODULE | Privacy and Security Governance | Restricted fields remain private; executable columns/envelopes wait for field inventory/key design. |
| OD-062 | Initial permission taxonomy | BLOCKS LATER MODULE | Security and Product Governance | Capability-derived seed is not populated until reviewed before P2. |
| OD-061 | Nigeria geography source | DOMAIN REVIEW DEPENDENCY | Geography Governance | No area codes/rows are seeded before P3. |
| OD-054; OD-055; OD-063 | Canonical/search Arabic and Quran source | DOMAIN REVIEW DEPENDENCY | Quran Reference Governance | No production Quran DDL/data activation until qualified source/collation/normalization review before P4. |
| OD-049; OD-050 | Score and percentage precision/scale | BLOCKS LATER MODULE | Scoring and Competition-Rules Governance | Symbolic exact DECIMAL parameters block P6 executable scoring DDL. |
| OD-065; OD-066; OD-017; OD-040 | Certificate identifiers and key custody | BLOCKS LATER MODULE | Certificate and Security Governance | Certificate DDL/issuance waits until P8 formats and custody are approved. |
| OD-013; OD-014; OD-037; OD-041 | Retention, disposition and qualified privacy review | LEGAL REVIEW DEPENDENCY | Privacy and Records Governance | Preserve/restrict by default; do not automate destructive disposition. |
| OD-002; OD-059 | MySQL/replica topology | PROVIDER DEPENDENCY | Database Architecture and Platform Operations | Vendor-neutral schema remains valid; authoritative-read and failover configuration wait. |
| OD-067; OD-068 | Analytics/search extraction boundary | NON-BLOCKING | Architecture and Privacy Governance | Use MySQL/rebuildable projections initially; extract only with evidence. |
| OD-069 | Partitioning threshold | NON-BLOCKING | Database Capacity Governance | No initial user partitioning. |
| OD-070 | Projection rebuild objectives | POLICY DEPENDENCY | Product Operations and Continuity Governance | Rebuild procedures exist; numeric objectives remain open. |

## P1-blocking decisions

P0-CLOSE must resolve or formally parameterize OD-047, OD-048, OD-056 and OD-057 and approve/supersede proposed ADR-030 through ADR-052 before final migration-generation conventions are frozen. The current draft is intentionally deterministic except for these governed gates. No engineer may substitute an arbitrary UUID library, key width, isolation level or retry loop.

## Implementation prerequisites

1. P0-CLOSE validates source hierarchy, IDs, Markdown/YAML synchronization, all state-machine storage, open decisions and risk treatment.
2. Governance approves or explicitly defers each proposed B04 ADR without altering locked ADR-001–ADR-020.
3. P1 establishes Core PHP layering, migration framework, schema-manifest checksum/generation rules, test harness and secure environment configuration.
4. P1 obtains the approved MySQL LTS patch/provider capability matrix and validates SQL mode, collation availability, online DDL, backup/restore and replication semantics.
5. Every symbolic logical parameter becomes an approved typed value before dependent executable migration generation.
6. Each module reviews exact table/column/relationship subset, classification, threat controls, query patterns, migration order, seed authority and negative tests.
7. Migration CI parses the manifest, checks identifier/DDL length, keys/FKs/checks/indexes, `workspace_id`, exact decimal, forbidden BLOB/float, secrets and lifecycle deletion.
8. Representative data/load/concurrency tests verify access plans, lock scope, deadlock behavior, queue claims, replay/idempotency, projection rebuild and restore.
9. Qualified Quran, Nigerian geography, privacy/child-safety, records and Certificate authorities approve their dependent sources/policies before activation.

## Required implementation evidence

- Approved ADR/open-decision record and schema-manifest checksum.
- Generated migration review showing manifest-to-DDL traceability.
- MySQL metadata export proving engine, charset/collation, columns, keys, FKs, checks and indexes.
- Constraint/negative tests for every Workspace-aware relationship and official-record transition.
- Exact-decimal property/calculation tests and no FLOAT/DOUBLE/REAL schema scan.
- Seed source/version/checksum/import/review/activation reports.
- Authorization, privacy, Minor/Guardian/Consent and public-field tests.
- Concurrency/deadlock/idempotency/offline replay tests.
- Audit-chain/checkpoint and Outbox replay/reconciliation tests.
- Query-plan/load/slow-query and write-amplification evidence.
- Backup/PITR/object/checkpoint restore and projection rebuild reconciliation.

## Risk posture

No residual risk is accepted by this document. Data risks in the project register remain Open until their controls and evidence exist. Particularly material P1/P2 risks are missing tenant columns/composite keys, migration cycles, nullable active-identity uniqueness, collation mismatch, silent version overwrite, unbounded JSON, stale authoritative replica reads and long-running production DDL.

## P0-CLOSE review checklist

- Recount and cross-parse every ID family.
- Parse both YAML files and compare all table/column/FK/index/requirement/open-decision references.
- Confirm every one of 29 B02 state machines has an authoritative table/status/history/audit representation.
- Resolve/parameterize P1 blockers and classify every remaining dependency.
- Confirm no authoritative source or taxonomy value was fabricated.
- Confirm no production code/DDL/infrastructure entered P0.
- Freeze document versions/checksums and record formal approval/rejection evidence.
- Mark P0 complete only if the closeout criteria pass; B04 itself does not make that decision.

## Related documents

- [Open decisions](../project/open-decisions.md)
- [Risk register](../project/risk-register.md)
- [Migration plan](11-migration-seed-and-bootstrap-plan.md)
- [Project state](../project/project-state.md)
