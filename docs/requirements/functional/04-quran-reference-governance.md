# Qur’an Reference Governance Functional Requirements

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B02 |
| Document Title | Qur’an Reference Governance Functional Requirements |
| Document Version | 1.0.0 |
| Status | Baselined; implementation not started |
| Document Owner Role | Qualified Qur’an Reference Governance |
| Last Updated | 2026-08-24 |
| Related Documents | [Functional index](../P0-B02-functional-requirements-index.md); [glossary](../../domain/domain-glossary.md); [ADR register](../../project/decision-register.md); [open decisions](../../project/open-decisions.md) |

## Purpose

Define import, validation, dual review, activation, use, search, correction, supersession, and preservation of canonical Qur’an Text Releases without inventing canonical source data or religious policy.

## Scope

Qualified source/reviewer appointment and supported Reading or Riwāyah choices remain governance dependencies. Normal administrators never edit canonical text.

## Functional requirements

### QMDB-FR-QRF-001 — Import a structured Qur’an Text Release

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-QRF-001 | Title | Import a structured Qur’an Text Release |
| Requirement Statement | QMDB shall import a proposed Qur’an Text Release with unique identifier, source metadata, Reading or Riwāyah, exact text, Surah/Ayah/Juz/Hizb/Rubʿ/Page mappings, and provenance into a non-active review state. | Rationale | Canonical data must arrive as an identifiable governed release. |
| Priority | Critical | Planned Implementation Phase | P4 — Qur’an Reference and Governance |
| Primary Actors | Qualified Qur’an Reviewer | Supporting Actors | Technical Reviewer; Background Worker |
| Owning Module | Qur’an Reference | Related Modules | Audit, Security Operations |
| Preconditions | Authorized import and approved source package format. | Trigger | Release import submission. |
| Inputs | Identifier/version, source/custody, Reading, canonical units/text/mappings, expected checksum. | Validation Rules | Complete metadata; unique identifier; structure/count/range consistency; `utf8mb4`; no normalization applied to canonical bytes. |
| Authorization and Scope | Dedicated import permission; importer cannot alone activate. | Normal Functional Behavior | Quarantine/import immutable candidate, compute checksum, record technical-validation results. |
| Alternative Behavior | Reject whole import or retain failed candidate for evidence; no partial activation. | Failure Behavior | Checksum, duplicate ID, incomplete source, conflicting counts, or unsupported Reading keeps release inactive. |
| Records Read | Existing releases/identifiers; approved source profiles. | Records Created | Release candidate, canonical units/mappings, checksum, validation report. |
| Records Updated | Import batch status only. | Records Versioned or Superseded | Candidate validation versions; canonical content immutable. |
| Audit Requirements | Importer, source/custody, tool/profile, computed/expected hash, validation outcome. | Domain Events | QuranReleaseImported; QuranReleaseValidationFailed. |
| Notifications | Technical and qualified review queues. | Privacy and Data Classification | Canonical data public after activation; source/review evidence internal. |
| Accessibility and Interaction Requirements | Arabic text rendered without transformation; review supports keyboard, RTL, and text comparison. | Postconditions | Candidate is inactive and fully identifiable for review. |
| Related Business Invariants | INV-017, INV-018 | Related P0-B01 Requirements | QMDB-QRF-001, QMDB-QRF-002, QMDB-QRF-003 |
| Verification Method | Integration test; integrity test; manual Qur’an-data review. | Acceptance Criteria | Mismatched checksum or incomplete metadata cannot reach review-complete/active state. |

### QMDB-FR-QRF-002 — Separate canonical and search-normalized text

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-QRF-002 | Title | Separate canonical and search-normalized text |
| Requirement Statement | QMDB shall derive search-normalized text as a rebuildable projection while preserving exact canonical text and reference mappings unchanged and checksummed. | Rationale | Search transformations must not alter authority. |
| Priority | Critical | Planned Implementation Phase | P4 — Qur’an Reference and Governance |
| Primary Actors | Technical Reviewer | Supporting Actors | Qualified Qur’an Reviewer; Search Worker |
| Owning Module | Qur’an Reference | Related Modules | Search, Audit |
| Preconditions | Imported candidate with canonical checksum. | Trigger | Search-index build or normalization profile update. |
| Inputs | Canonical text/release/version; approved normalization profile/version. | Validation Rules | Deterministic transform; no writes to canonical fields; mappings retain release/unit identity; profile version recorded. |
| Authorization and Scope | Worker reads active/approved candidate only as permitted; normal search administrators cannot edit canonical content. | Normal Functional Behavior | Build separate normalized projection and compare canonical checksum before/after. |
| Alternative Behavior | Maintain multiple language/search profiles keyed by version. | Failure Behavior | Abort projection on hash change, missing mapping, or normalization collision requiring review. |
| Records Read | Canonical release/units/checksum and normalization profile. | Records Created | Search-normalized projection/build report. |
| Records Updated | Projection status/current pointer. | Records Versioned or Superseded | Normalization profile/projection, never canonical release. |
| Audit Requirements | Profile/tool, release, before/after canonical hash, counts, outcome. | Domain Events | QuranSearchProjectionBuilt; QuranIntegrityAlerted. |
| Notifications | Integrity alert to Qur’an and Security Governance. | Privacy and Data Classification | Public text; protected integrity evidence. |
| Accessibility and Interaction Requirements | Search result displays exact canonical text/reference, readable Arabic and correct direction. | Postconditions | Search projection is traceable and canonical checksum is unchanged. |
| Related Business Invariants | INV-018, INV-022 | Related P0-B01 Requirements | QMDB-QRF-001, QMDB-QRF-003, QMDB-ACC-002 |
| Verification Method | Unit test; integrity test; accessibility test; manual Qur’an-data review. | Acceptance Criteria | Reindexing cannot modify a canonical Ayah or its checksum. |

### QMDB-FR-QRF-003 — Perform technical and dual qualified review

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-QRF-003 | Title | Perform technical and dual qualified review |
| Requirement Statement | QMDB shall require completed technical validation and two separately attributable qualified approvals before a Qur’an Text Release can be activated. | Rationale | Canonical accuracy requires technical integrity and qualified separation of duties. |
| Priority | Critical | Planned Implementation Phase | P4 — Qur’an Reference and Governance |
| Primary Actors | Qualified Qur’an Reviewers | Supporting Actors | Technical Reviewer; Internal Auditor |
| Owning Module | Qur’an Reference | Related Modules | Access Control, Audit, Security Operations |
| Preconditions | Inactive candidate passed automated/technical validation. | Trigger | Review submission/approval. |
| Inputs | Release/hash, source, validation report, comparison evidence, reviewer decision/reason. | Validation Rules | Distinct qualified reviewers; no importer-only activation; current hash/version; conflicts declared; Step-Up Authentication. |
| Authorization and Scope | Review permission is release-scoped; exact reviewer authority is governed, not inferred from admin Role. | Normal Functional Behavior | Append reviews, lock reviewed hash, mark activation-ready only when both approvals and technical review pass. |
| Alternative Behavior | Request correction/evidence and return candidate to reviewable state without editing content in place. | Failure Behavior | Deny self/duplicate/conflicted/stale/hash-mismatched approval and alert unauthorized attempt. |
| Records Read | Candidate, validations, source, reviewer authority/conflicts. | Records Created | Technical and qualified review/approval records. |
| Records Updated | Review/activation-readiness status. | Records Versioned or Superseded | Review decisions and candidate correction versions. |
| Audit Requirements | Reviewer identity/authority, conflict, hash, evidence, decision, step-up, time. | Domain Events | QuranReleaseReviewCompleted; QuranReleaseApprovalCompleted. |
| Notifications | Review queue/applicant; security on unauthorized attempt. | Privacy and Data Classification | Review evidence internal; canonical data pending. |
| Accessibility and Interaction Requirements | Accessible comparison, navigation by Surah/Ayah, RTL, no color-only differences. | Postconditions | Candidate is rejected/correction-required or activation-ready with dual approvals. |
| Related Business Invariants | INV-017, INV-018, INV-024 | Related P0-B01 Requirements | QMDB-QRF-001, QMDB-QRF-002, QMDB-AUD-001 |
| Verification Method | Authorization test; integration test; manual Qur’an-data review. | Acceptance Criteria | One reviewer, importer self-approval, or stale hash cannot satisfy activation readiness. |

### QMDB-FR-QRF-004 — Activate and bind approved releases

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-QRF-004 | Title | Activate and bind approved releases |
| Requirement Statement | QMDB shall activate only a dual-approved checksummed Qur’an Text Release and require Competition Rulesets, Passage Assignments, media tags, and public references to identify an active or historically valid release and Reading. | Rationale | Every consumer must identify its canonical source. |
| Priority | Critical | Planned Implementation Phase | P4 — Qur’an Reference and Governance |
| Primary Actors | Qualified Qur’an Governance approver | Supporting Actors | Competition-Rules Governance; Media/Competition users |
| Owning Module | Qur’an Reference | Related Modules | Competitions, Scoring, Scheduling, Media, Search, Audit |
| Preconditions | Candidate is activation-ready and current hash matches approvals. | Trigger | Activation or consumer reference creation. |
| Inputs | Release/version/hash, activation reason/effective time; consumer Passage Range/reference. | Validation Rules | Dual approvals; active status for new use; range exists in same release/Reading; historical records may use superseded release. |
| Authorization and Scope | Activation requires Step-Up and prohibited self-approval; consumers cannot override inactive status. | Normal Functional Behavior | Activate version, publish event, validate/bind consumer reference. |
| Alternative Behavior | Superseded release remains resolvable for historical records but unavailable for new assignments unless policy explicitly permits. | Failure Behavior | Deny inactive/unapproved/unsupported/mismatched release/range with no fallback substitution. |
| Records Read | Release/reviews/hash/status; consumer context. | Records Created | Activation decision or consumer reference. |
| Records Updated | Release current status; consumer binding. | Records Versioned or Superseded | Activation status and consumer reference versions. |
| Audit Requirements | Approver, hash/approval set, effective time; each sensitive binding/change. | Domain Events | QuranTextReleaseActivated; QuranReferenceBound. |
| Notifications | Qur’an/competition/media/search owners on activation/supersession. | Privacy and Data Classification | Canonical/public; activation evidence internal. |
| Accessibility and Interaction Requirements | Public/reference display identifies Reading and reference in readable Arabic/RTL. | Postconditions | New reference is bound to one identified governed release or rejected. |
| Related Business Invariants | INV-011, INV-017, INV-018 | Related P0-B01 Requirements | QMDB-QRF-001, QMDB-QRF-003, QMDB-CMP-005 |
| Verification Method | Authorization test; integration test; manual Qur’an-data review. | Acceptance Criteria | A competition cannot bind an inactive or unapproved release. |

### QMDB-FR-QRF-005 — Correct, supersede, and preserve a release

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-QRF-005 | Title | Correct, supersede, and preserve a release |
| Requirement Statement | QMDB shall correct canonical Qur’an data only by importing and approving a new release version that supersedes, but never removes or mutates, a release referenced by historical records. | Rationale | Historical competition interpretation must remain reproducible. |
| Priority | Critical | Planned Implementation Phase | P4 — Qur’an Reference and Governance |
| Primary Actors | Qualified Qur’an Reviewers | Supporting Actors | Technical Reviewer; Records Steward; Auditor |
| Owning Module | Qur’an Reference | Related Modules | Records, Competitions, Scoring, Audit |
| Preconditions | Correction evidence and affected-release impact assessment. | Trigger | Confirmed correction request or supersession. |
| Inputs | New source package, reason/evidence, predecessor, impact, approvals. | Validation Rules | Full import/checksum/dual review; predecessor immutable; historical dependencies enumerated. |
| Authorization and Scope | Same qualified dual controls as activation; normal admin denied. | Normal Functional Behavior | Create/activate successor, mark predecessor superseded for new use, preserve all references and hashes. |
| Alternative Behavior | Restrict disputed release pending review without deleting it. | Failure Behavior | Reject in-place edit, delete, incomplete impact, or unapproved successor. |
| Records Read | Predecessor/references/results/media and correction evidence. | Records Created | Successor release and correction/supersession decision. |
| Records Updated | Current release pointer/status only. | Records Versioned or Superseded | Release chain, reviews, public status. |
| Audit Requirements | Request/evidence, reviewers, hashes, impact, predecessor/successor, notification. | Domain Events | QuranReleaseSuperseded; QuranReferenceCorrectionPublished. |
| Notifications | All modules with current/future bindings and governance actors. | Privacy and Data Classification | Canonical/public; evidence internal. |
| Accessibility and Interaction Requirements | Historical/current release status expressed textually; references remain copyable intact. | Postconditions | Successor handles new use; historical records still resolve predecessor exactly. |
| Related Business Invariants | INV-017, INV-018, INV-026 | Related P0-B01 Requirements | QMDB-QRF-001, QMDB-QRF-002, QMDB-AUD-003 |
| Verification Method | Integrity test; integration test; manual Qur’an-data review. | Acceptance Criteria | A used release cannot be deleted or edited; its successor is separately identified. |

### QMDB-FR-QRF-006 — Restrict modification and expose safe reference search

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-QRF-006 | Title | Restrict modification and expose safe reference search |
| Requirement Statement | QMDB shall expose active/historical Qur’an reference display and search through read-only projections while denying normal administrators and search/media/competition clients any canonical text modification path. | Rationale | Wide read use must not expand write authority. |
| Priority | Critical | Planned Implementation Phase | P4 — Qur’an Reference and Governance |
| Primary Actors | Public Visitor; authenticated domain users | Supporting Actors | Search Worker; Security Operator |
| Owning Module | Qur’an Reference | Related Modules | Search, Access Control, Security Operations, Audit |
| Preconditions | Active or historically referenced release. | Trigger | Reference browse/search/copy or unauthorized write attempt. |
| Inputs | Search/reference query, release/Reading filters, requested action. | Validation Rules | Approved normalization; exact display from canonical record; read-only contract; rate/tenant/privacy controls for linked records. |
| Authorization and Scope | Canonical read public as approved; governance write route separate and restricted; client identifiers non-authoritative. | Normal Functional Behavior | Search projection, return exact text/reference/release status, preserve direction/copy fidelity. |
| Alternative Behavior | Historical release result clearly labeled; unavailable projection rebuilt from canonical source. | Failure Behavior | Deny/alert modification; fail search without altering canonical data; never substitute a different Reading silently. |
| Records Read | Canonical releases and search projection. | Records Created | Search Audit Event only when sensitive/abusive. |
| Records Updated | Projection telemetry, never canonical data. | Records Versioned or Superseded | Search projection/profile. |
| Audit Requirements | All write attempts; governance reads/exports; integrity/search rebuild. | Domain Events | UnauthorizedQuranModificationAttempted; QuranSearchProjectionRebuilt. |
| Notifications | Immediate security/Qur’an governance alert for modification attempt. | Privacy and Data Classification | Canonical text public; security context restricted. |
| Accessibility and Interaction Requirements | Arabic readability, RTL, mixed references, screen-reader order, intact copy. | Postconditions | Read succeeds from identified release or fails safely; canonical record unchanged. |
| Related Business Invariants | INV-017, INV-018, INV-022, INV-027 | Related P0-B01 Requirements | QMDB-QRF-002, QMDB-ACC-002, QMDB-SEC-001 |
| Verification Method | Authorization test; security test; accessibility test; manual Qur’an-data review. | Acceptance Criteria | Normal administrator write attempt is denied/audited and canonical hash remains unchanged. |

## Related documents

- [Functional requirements index](../P0-B02-functional-requirements-index.md)
- [Workflows and state machines](../P0-B02-workflows-and-state-machines.md)
- [Acceptance scenarios](../P0-B02-acceptance-scenarios.md)
- [P0-B01 baseline requirements](../P0-B01-requirements.md)

