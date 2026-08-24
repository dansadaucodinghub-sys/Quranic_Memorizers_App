# P0-B02 Functional Requirements Index

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project code | QMDB |
| Baseline | QMDB-BL-001 |
| Batch | QMDB-P0-B02 |
| Document version | 1.0.0 |
| Status | Complete functional-requirement baseline |
| Last updated | 2026-08-24 |
| Owner role | Product and Architecture Governance |

## Purpose

This index is the single navigation and counting surface for the detailed functional obligations in QMDB-P0-B02. The domain files remain authoritative for full behavior; this index records each requirement exactly once.

## Requirement-writing standard

Every requirement has one primary obligation, uses `shall`, has a stable `QMDB-FR-<FAMILY>-###` identifier, and records rationale, priority, planned phase, actors, module ownership, lifecycle behavior, records, audit/events/notifications, privacy, accessibility, invariants, P0-B01 sources, verification, and testable acceptance criteria. Requirements do not resolve open religious, legal, authority, vendor, retention-duration, or numerical-service policy.

## Identifier families

| Family | Owning domain | Count |
| --- | --- | ---: |
| `QMDB-FR-APL-###` | Appeals | 3 |
| `QMDB-FR-AUD-###` | Audit and Integrity | 2 |
| `QMDB-FR-AUT-###` | Access Control | 2 |
| `QMDB-FR-CER-###` | Certificates | 4 |
| `QMDB-FR-CMP-###` | Competitions | 4 |
| `QMDB-FR-GEO-###` | Geography | 3 |
| `QMDB-FR-GUA-###` | Guardianship | 4 |
| `QMDB-FR-IAM-###` | Identity | 6 |
| `QMDB-FR-IMP-###` | Records | 1 |
| `QMDB-FR-INT-###` | Integrations | 3 |
| `QMDB-FR-JDG-###` | Judging | 4 |
| `QMDB-FR-LIV-###` | Results | 2 |
| `QMDB-FR-MED-###` | Media and Evidence | 5 |
| `QMDB-FR-MOD-###` | Moderation and Safety | 2 |
| `QMDB-FR-NTF-###` | Notifications | 2 |
| `QMDB-FR-OFF-###` | Offline and Venue Resilience | 2 |
| `QMDB-FR-OPS-###` | Platform Operations | 5 |
| `QMDB-FR-ORG-###` | Organizations | 4 |
| `QMDB-FR-PPL-###` | People | 4 |
| `QMDB-FR-PRI-###` | Privacy and Data Governance | 4 |
| `QMDB-FR-QRF-###` | Qur’an Reference | 6 |
| `QMDB-FR-REC-###` | Records | 1 |
| `QMDB-FR-REG-###` | Registration | 5 |
| `QMDB-FR-RPT-###` | Reporting and Analytics | 3 |
| `QMDB-FR-RSL-###` | Results | 4 |
| `QMDB-FR-RUL-###` | Scoring | 3 |
| `QMDB-FR-SCH-###` | Scheduling | 4 |
| `QMDB-FR-SCR-###` | Scoring | 6 |
| `QMDB-FR-SEC-###` | Security Operations | 2 |
| `QMDB-FR-SES-###` | Identity | 3 |
| `QMDB-FR-SOC-###` | Community and Recitation Clips | 3 |
| `QMDB-FR-SRH-###` | Search and Discovery | 3 |
| `QMDB-FR-TEN-###` | Workspaces | 2 |
| `QMDB-FR-UXA-###` | Experience and Accessibility | 2 |

## Priority definitions

| Priority | Meaning |
| --- | --- |
| Critical | Required to protect authority, official-record integrity, tenant isolation, privacy, child safety, Qur’an integrity, audit evidence, or core operational continuity. |
| High | Required for a planned usable workflow or significant control. |
| Medium | Valuable planned behavior that may follow the core gate without weakening it. |
| Deferred | Explicitly outside the first implementation sequence and not silently promised. |

## Implementation-phase definitions

| Phase | Definition |
| --- | --- |
| P1 | Engineering and Repository Foundation |
| P2 | Identity, Security, and Tenant Isolation |
| P3 | Nigerian Geography, Organizations, and People |
| P4 | Qur’an Reference and Governance |
| P5 | Competition Configuration and Registration |
| P6 | Judging and Deterministic Scoring |
| P7 | Live Competition, Results, and Appeals |
| P8 | Certificates, Record Passport, and Trusted Archive |
| P9 | Audio and Video Evidence |
| P10 | Recitation Clips and Community Safety |
| P11 | Search, Analytics, and National Reporting |
| P12 | Quality, Accessibility, and Production Hardening |
| P13 | Pilot, Offline Venue Mode, and National Rollout |

## Verification-method definitions

| Method family | Evidence expectation |
| --- | --- |
| Document/manual review | Document inspection or qualified domain, privacy, or Qur’an-data review against named sources. |
| Static/unit/schema | Architecture, unit/property, database-constraint, serialization, or configuration-schema evidence. |
| Boundary/integration | Authorization, integration, API-contract, concurrency, event, storage, or tenant-isolation evidence. |
| User/system | End-to-end, accessibility, security, load, recovery, or operational-exercise evidence. |

Compilation alone is not sufficient evidence for functional acceptance.

## Requirement counts by domain

| Document | Requirements |
| --- | ---: |
| [01-identity-access-and-tenancy.md](functional/01-identity-access-and-tenancy.md) | 13 |
| [02-people-profiles-and-guardianship.md](functional/02-people-profiles-and-guardianship.md) | 8 |
| [03-geography-and-organizations.md](functional/03-geography-and-organizations.md) | 7 |
| [04-quran-reference-governance.md](functional/04-quran-reference-governance.md) | 6 |
| [05-competition-configuration-and-registration.md](functional/05-competition-configuration-and-registration.md) | 12 |
| [06-scheduling-judging-and-scoring.md](functional/06-scheduling-judging-and-scoring.md) | 14 |
| [07-results-appeals-certificates-and-records.md](functional/07-results-appeals-certificates-and-records.md) | 15 |
| [08-media-recitation-clips-and-moderation.md](functional/08-media-recitation-clips-and-moderation.md) | 10 |
| [09-search-notifications-and-reporting.md](functional/09-search-notifications-and-reporting.md) | 8 |
| [10-privacy-audit-security-operations-and-integrations.md](functional/10-privacy-audit-security-operations-and-integrations.md) | 20 |
| **Total** | **113** |

## Requirement counts by priority

| Priority | Count |
| --- | ---: |
| Critical | 84 |
| High | 29 |
| **Total** | **113** |

## Requirement counts by planned phase

| Phase | Count |
| --- | ---: |
| P1 — Engineering and Repository Foundation | 1 |
| P2 — Identity, Security, and Tenant Isolation | 18 |
| P3 — Nigerian Geography, Organizations, and People | 16 |
| P4 — Qur’an Reference and Governance | 6 |
| P5 — Competition Configuration and Registration | 15 |
| P6 — Judging and Deterministic Scoring | 11 |
| P7 — Live Competition, Results, and Appeals | 9 |
| P8 — Certificates, Record Passport, and Trusted Archive | 6 |
| P9 — Audio and Video Evidence | 5 |
| P10 — Recitation Clips and Community Safety | 5 |
| P11 — Search, Analytics, and National Reporting | 6 |
| P12 — Quality, Accessibility, and Production Hardening | 13 |
| P13 — Pilot, Offline Venue Mode, and National Rollout | 2 |
| **Total** | **113** |

## Functional requirement documents

- [01-identity-access-and-tenancy.md](functional/01-identity-access-and-tenancy.md) — 13 requirements.
- [02-people-profiles-and-guardianship.md](functional/02-people-profiles-and-guardianship.md) — 8 requirements.
- [03-geography-and-organizations.md](functional/03-geography-and-organizations.md) — 7 requirements.
- [04-quran-reference-governance.md](functional/04-quran-reference-governance.md) — 6 requirements.
- [05-competition-configuration-and-registration.md](functional/05-competition-configuration-and-registration.md) — 12 requirements.
- [06-scheduling-judging-and-scoring.md](functional/06-scheduling-judging-and-scoring.md) — 14 requirements.
- [07-results-appeals-certificates-and-records.md](functional/07-results-appeals-certificates-and-records.md) — 15 requirements.
- [08-media-recitation-clips-and-moderation.md](functional/08-media-recitation-clips-and-moderation.md) — 10 requirements.
- [09-search-notifications-and-reporting.md](functional/09-search-notifications-and-reporting.md) — 8 requirements.
- [10-privacy-audit-security-operations-and-integrations.md](functional/10-privacy-audit-security-operations-and-integrations.md) — 20 requirements.

## Master summary

| Requirement ID | Title | Priority | Owning Module | Primary Actor | Planned Phase | Verification Method | Status |
| --- | --- | --- | --- | --- | --- | --- | --- |
| [QMDB-FR-IAM-001](functional/01-identity-access-and-tenancy.md) | Register and verify an individual Account | High | Identity | Registered Individual | P2 — Identity, Security, and Tenant Isolation | Unit test; integration test; security test; accessibility test. | Specified |
| [QMDB-FR-IAM-002](functional/01-identity-access-and-tenancy.md) | Authenticate with a password safely | Critical | Identity | Registered Individual | P2 — Identity, Security, and Tenant Isolation | Authorization test; integration test; security test. | Specified |
| [QMDB-FR-IAM-003](functional/01-identity-access-and-tenancy.md) | Register and use passkeys | High | Identity | Registered Individual | P2 — Identity, Security, and Tenant Isolation | API contract test; integration test; security test; accessibility test. | Specified |
| [QMDB-FR-IAM-004](functional/01-identity-access-and-tenancy.md) | Enforce MFA and Step-Up Authentication | Critical | Identity | Privileged actors | P2 — Identity, Security, and Tenant Isolation | Authorization test; end-to-end test; security test; accessibility test. | Specified |
| [QMDB-FR-IAM-005](functional/01-identity-access-and-tenancy.md) | Govern Account recovery and password change | Critical | Identity | Registered Individual | P2 — Identity, Security, and Tenant Isolation | Integration test; concurrency test; security test; manual privacy review. | Specified |
| [QMDB-FR-IAM-006](functional/01-identity-access-and-tenancy.md) | Control Account suspension, reactivation, and closure | High | Identity | Registered Individual; Security Operator | P2 — Identity, Security, and Tenant Isolation | Authorization test; integration test; manual privacy review. | Specified |
| [QMDB-FR-SES-001](functional/01-identity-access-and-tenancy.md) | Manage secure server-side Sessions | Critical | Identity | Registered Individual | P2 — Identity, Security, and Tenant Isolation | Unit test; integration test; security test. | Specified |
| [QMDB-FR-SES-002](functional/01-identity-access-and-tenancy.md) | Label Devices and terminate active Sessions | High | Identity | Registered Individual | P2 — Identity, Security, and Tenant Isolation | Authorization test; integration test; accessibility test. | Specified |
| [QMDB-FR-SES-003](functional/01-identity-access-and-tenancy.md) | React to privilege and suspicious-Session changes | Critical | Identity | Security Operator; organization/competition authority | P2 — Identity, Security, and Tenant Isolation | Integration test; authorization test; security test. | Specified |
| [QMDB-FR-TEN-001](functional/01-identity-access-and-tenancy.md) | Govern Workspace lifecycle and Membership | Critical | Workspaces | Platform Operator; authorized Workspace/Organization Administrator | P2 — Identity, Security, and Tenant Isolation | Authorization test; database constraint test; integration test. | Specified |
| [QMDB-FR-TEN-002](functional/01-identity-access-and-tenancy.md) | Resolve and enforce tenant context | Critical | Workspaces | Authenticated end user; service actor | P2 — Identity, Security, and Tenant Isolation | Authorization test; database constraint test; security test; end-to-end test. | Specified |
| [QMDB-FR-AUT-001](functional/01-identity-access-and-tenancy.md) | Evaluate contextual resource authorization | Critical | Access Control | All authenticated human/service actors | P2 — Identity, Security, and Tenant Isolation | Unit test; authorization test; security test; architecture test. | Specified |
| [QMDB-FR-AUT-002](functional/01-identity-access-and-tenancy.md) | Govern grants, revocation, approvals, and exceptional authority | Critical | Access Control | Authorized administrators; Security Operator | P2 — Identity, Security, and Tenant Isolation | Authorization test; integration test; security test; document inspection. | Specified |
| [QMDB-FR-PPL-001](functional/02-people-profiles-and-guardianship.md) | Create and associate a durable Person | Critical | People | Registered Individual; Competition Registrar | P3 — Nigerian Geography, Organizations, and People | Unit test; integration test; authorization test; manual privacy review. | Specified |
| [QMDB-FR-PPL-002](functional/02-people-profiles-and-guardianship.md) | Manage identity evidence, names, display, and duplicates | High | People | Registered Individual; Data Steward | P3 — Nigerian Geography, Organizations, and People | Integration test; authorization test; manual privacy review. | Specified |
| [QMDB-FR-PPL-003](functional/02-people-profiles-and-guardianship.md) | Maintain contextual Profiles and affiliations | High | People | Person; Registrar; Organization Administrator | P3 — Nigerian Geography, Organizations, and People | Domain test; authorization test; end-to-end test. | Specified |
| [QMDB-FR-PPL-004](functional/02-people-profiles-and-guardianship.md) | Merge or correct duplicate Person records safely | Critical | People | Data Steward | P3 — Nigerian Geography, Organizations, and People | Integration test; authorization test; audit test; manual privacy review. | Specified |
| [QMDB-FR-GUA-001](functional/02-people-profiles-and-guardianship.md) | Establish a Guardian Relationship | Critical | Guardianship | Guardian | P3 — Nigerian Geography, Organizations, and People | Authorization test; integration test; manual privacy review. | Specified |
| [QMDB-FR-GUA-002](functional/02-people-profiles-and-guardianship.md) | Manage multiple, suspended, or disputed Guardians | Critical | Guardianship | Guardian; Child-Safety Officer | P3 — Nigerian Geography, Organizations, and People | Authorization test; workflow integration test; manual privacy review. | Specified |
| [QMDB-FR-GUA-003](functional/02-people-profiles-and-guardianship.md) | Create, version, and withdraw Consent | Critical | Guardianship | Guardian; Person/Minor where policy permits | P3 — Nigerian Geography, Organizations, and People | Unit test; authorization test; integration test; accessibility test; manual privacy review. | Specified |
| [QMDB-FR-GUA-004](functional/02-people-profiles-and-guardianship.md) | Protect Minors through status transitions and emergencies | Critical | Guardianship | Minor/Person; Guardian; Community-Safety Officer | P3 — Nigerian Geography, Organizations, and People | End-to-end test; authorization test; manual privacy/child-safety review. | Specified |
| [QMDB-FR-GEO-001](functional/03-geography-and-organizations.md) | Maintain the Administrative Area hierarchy | High | Geography | Geography Data Steward | P3 — Nigerian Geography, Organizations, and People | Unit test; database constraint test; manual domain review. | Specified |
| [QMDB-FR-GEO-002](functional/03-geography-and-organizations.md) | Govern names, codes, validity, deactivation, and replacement | High | Geography | Geography Data Steward | P3 — Nigerian Geography, Organizations, and People | Integration test; database constraint test; document inspection. | Specified |
| [QMDB-FR-GEO-003](functional/03-geography-and-organizations.md) | Search, report, and authorize by geography | High | Geography | Coordinator; Registrar; Public Visitor | P3 — Nigerian Geography, Organizations, and People | Authorization test; integration test; accessibility test. | Specified |
| [QMDB-FR-ORG-001](functional/03-geography-and-organizations.md) | Create Organization, Units, Workspace, and coverage | High | Organizations | Organization Administrator | P3 — Nigerian Geography, Organizations, and People | Database constraint test; authorization test; accessibility test. | Specified |
| [QMDB-FR-ORG-002](functional/03-geography-and-organizations.md) | Record distinct Organization relationships | Critical | Organizations | Organization Administrator; Competition Director | P3 — Nigerian Geography, Organizations, and People | Domain test; authorization test; integration test. | Specified |
| [QMDB-FR-ORG-003](functional/03-geography-and-organizations.md) | Review Organization verification | Critical | Organizations | Organization Administrator; Organization Verification Reviewer | P3 — Nigerian Geography, Organizations, and People | Authorization test; workflow integration test; manual domain/privacy review. | Specified |
| [QMDB-FR-ORG-004](functional/03-geography-and-organizations.md) | Govern Membership, delegation, change, suspension, closure, and merge | Critical | Organizations | Organization Administrator; Organization Governance | P3 — Nigerian Geography, Organizations, and People | Authorization test; integration test; concurrency test. | Specified |
| [QMDB-FR-QRF-001](functional/04-quran-reference-governance.md) | Import a structured Qur’an Text Release | Critical | Qur’an Reference | Qualified Qur’an Reviewer | P4 — Qur’an Reference and Governance | Integration test; integrity test; manual Qur’an-data review. | Specified |
| [QMDB-FR-QRF-002](functional/04-quran-reference-governance.md) | Separate canonical and search-normalized text | Critical | Qur’an Reference | Technical Reviewer | P4 — Qur’an Reference and Governance | Unit test; integrity test; accessibility test; manual Qur’an-data review. | Specified |
| [QMDB-FR-QRF-003](functional/04-quran-reference-governance.md) | Perform technical and dual qualified review | Critical | Qur’an Reference | Qualified Qur’an Reviewers | P4 — Qur’an Reference and Governance | Authorization test; integration test; manual Qur’an-data review. | Specified |
| [QMDB-FR-QRF-004](functional/04-quran-reference-governance.md) | Activate and bind approved releases | Critical | Qur’an Reference | Qualified Qur’an Governance approver | P4 — Qur’an Reference and Governance | Authorization test; integration test; manual Qur’an-data review. | Specified |
| [QMDB-FR-QRF-005](functional/04-quran-reference-governance.md) | Correct, supersede, and preserve a release | Critical | Qur’an Reference | Qualified Qur’an Reviewers | P4 — Qur’an Reference and Governance | Integrity test; integration test; manual Qur’an-data review. | Specified |
| [QMDB-FR-QRF-006](functional/04-quran-reference-governance.md) | Restrict modification and expose safe reference search | Critical | Qur’an Reference | Public Visitor; authenticated domain users | P4 — Qur’an Reference and Governance | Authorization test; security test; accessibility test; manual Qur’an-data review. | Specified |
| [QMDB-FR-CMP-001](functional/05-competition-configuration-and-registration.md) | Create Series and Edition with separate dimensions | Critical | Competitions | Competition Director | P5 — Competition Configuration and Registration | Domain test; database constraint test; authorization test. | Specified |
| [QMDB-FR-CMP-002](functional/05-competition-configuration-and-registration.md) | Configure competition hierarchy and venues | High | Competitions | Competition Director | P5 — Competition Configuration and Registration | Unit test; integration test; accessibility test. | Specified |
| [QMDB-FR-CMP-003](functional/05-competition-configuration-and-registration.md) | Govern Edition review, approval, and publication | Critical | Competitions | Competition Director; Competition approver category | P5 — Competition Configuration and Registration | Authorization test; integration test; accessibility test. | Specified |
| [QMDB-FR-CMP-004](functional/05-competition-configuration-and-registration.md) | Control operational Edition lifecycle | Critical | Competitions | Competition Director | P5 — Competition Configuration and Registration | State-machine test; concurrency test; authorization test. | Specified |
| [QMDB-FR-RUL-001](functional/05-competition-configuration-and-registration.md) | Define declarative versioned scoring rules | Critical | Scoring | Competition-Rules Governance Body | P5 — Competition Configuration and Registration | Unit test; schema/security test; manual domain review. | Specified |
| [QMDB-FR-RUL-002](functional/05-competition-configuration-and-registration.md) | Define quorum, aggregation, outlier, tie, and outcome rules | Critical | Scoring | Competition-Rules Governance Body | P5 — Competition Configuration and Registration | Unit/property test; manual domain review. | Specified |
| [QMDB-FR-RUL-003](functional/05-competition-configuration-and-registration.md) | Review, activate, lock, supersede, and assign Rulesets | Critical | Scoring | Rules Governance approver category | P5 — Competition Configuration and Registration | Authorization test; integration test; audit test. | Specified |
| [QMDB-FR-REG-001](functional/05-competition-configuration-and-registration.md) | Initiate and submit Registration through authorized channels | High | Registration | Competitor; Guardian; authorized nominator/Organization | P5 — Competition Configuration and Registration | End-to-end test; concurrency/idempotency test; accessibility test. | Specified |
| [QMDB-FR-REG-002](functional/05-competition-configuration-and-registration.md) | Review eligibility and request evidence | Critical | Eligibility | Competition Registrar | P5 — Competition Configuration and Registration | Unit test; authorization test; integration test; manual privacy review. | Specified |
| [QMDB-FR-REG-003](functional/05-competition-configuration-and-registration.md) | Resolve duplicates, categories, nomination, and representation changes | High | Registration | Registrar; Competitor; nominating Organization | P5 — Competition Configuration and Registration | Integration test; concurrency test; authorization test. | Specified |
| [QMDB-FR-REG-004](functional/05-competition-configuration-and-registration.md) | Gate Registration by Consent and create Participant Snapshot | Critical | Registration | Competition Registrar | P5 — Competition Configuration and Registration | Integration test; authorization test; data-integrity test. | Specified |
| [QMDB-FR-REG-005](functional/05-competition-configuration-and-registration.md) | Control exceptions, cancellations, and Registration history | Critical | Registration | Competition Registrar; Competition Director | P5 — Competition Configuration and Registration | Authorization test; state-machine test; audit inspection. | Specified |
| [QMDB-FR-SCH-001](functional/06-scheduling-judging-and-scoring.md) | Build and publish conflict-checked schedules | High | Scheduling | Competition Director | P5 — Competition Configuration and Registration | Unit test; integration test; accessibility test. | Specified |
| [QMDB-FR-SCH-002](functional/06-scheduling-judging-and-scoring.md) | Generate and change Draw Order | High | Scheduling | Competition Director | P5 — Competition Configuration and Registration | Unit test; integration test; audit inspection. | Specified |
| [QMDB-FR-SCH-003](functional/06-scheduling-judging-and-scoring.md) | Manage Check-In, lateness, absence, withdrawal, and transfer | Critical | Scheduling | Competition Registrar | P5 — Competition Configuration and Registration | End-to-end test; authorization test; offline/recovery test. | Specified |
| [QMDB-FR-SCH-004](functional/06-scheduling-judging-and-scoring.md) | Close and reconcile a Session | High | Scheduling | Competition Director; Chief Judge | P6 — Judging and Deterministic Scoring | Integration test; state-machine test. | Specified |
| [QMDB-FR-JDG-001](functional/06-scheduling-judging-and-scoring.md) | Invite, qualify, and assign Judges | Critical | Judging | Competition Director; Judge | P6 — Judging and Deterministic Scoring | Authorization test; integration test. | Specified |
| [QMDB-FR-JDG-002](functional/06-scheduling-judging-and-scoring.md) | Declare, review, and resolve Judge conflicts | Critical | Judging | Judge; Conflict Reviewer | P6 — Judging and Deterministic Scoring | Workflow test; authorization test; audit inspection. | Specified |
| [QMDB-FR-JDG-003](functional/06-scheduling-judging-and-scoring.md) | Open and conduct a Performance | Critical | Judging | Chief Judge; assigned event operator | P6 — Judging and Deterministic Scoring | End-to-end test; authorization test; state-machine test. | Specified |
| [QMDB-FR-JDG-004](functional/06-scheduling-judging-and-scoring.md) | Handle Performance interruption and evidence | High | Judging | Chief Judge; Competition Director | P6 — Judging and Deterministic Scoring | Integration test; recovery test; audit inspection. | Specified |
| [QMDB-FR-SCR-001](functional/06-scheduling-judging-and-scoring.md) | Create and validate a Judge-specific Score Sheet | Critical | Scoring | Judge | P6 — Judging and Deterministic Scoring | Unit test; authorization test; boundary/property test; accessibility test. | Specified |
| [QMDB-FR-SCR-002](functional/06-scheduling-judging-and-scoring.md) | Autosave drafts with concurrency protection | High | Scoring | Judge | P6 — Judging and Deterministic Scoring | Concurrency test; integration test; accessibility test. | Specified |
| [QMDB-FR-SCR-003](functional/06-scheduling-judging-and-scoring.md) | Submit, calculate, receipt, lock, and sign Score Sheets | Critical | Scoring | Judge | P6 — Judging and Deterministic Scoring | End-to-end test; concurrency test; authorization/security test; exact arithmetic test. | Specified |
| [QMDB-FR-SCR-004](functional/06-scheduling-judging-and-scoring.md) | Evaluate quorum and aggregate panel scores | Critical | Scoring | Chief Judge | P6 — Judging and Deterministic Scoring | Unit/property test; integration/recovery test; audit inspection. | Specified |
| [QMDB-FR-SCR-005](functional/06-scheduling-judging-and-scoring.md) | Reopen and resubmit a Score Sheet under control | Critical | Scoring | Chief Judge or correction initiator; Judge | P6 — Judging and Deterministic Scoring | Authorization test; state-machine test; end-to-end/audit test. | Specified |
| [QMDB-FR-SCR-006](functional/06-scheduling-judging-and-scoring.md) | Fail scoring safely under scope, state, concurrency, and network errors | Critical | Scoring | Judge; Chief Judge; offline scoring client | P6 — Judging and Deterministic Scoring | Negative authorization/security test; concurrency test; recovery/offline test. | Specified |
| [QMDB-FR-LIV-001](functional/07-results-appeals-certificates-and-records.md) | Publish sequenced live projections | High | Results | Public Visitor | P7 — Live Competition, Results, and Appeals | Integration test; recovery/load/accessibility test. | Specified |
| [QMDB-FR-LIV-002](functional/07-results-appeals-certificates-and-records.md) | Reconnect, pause, and recover live delivery | High | Platform Operations | Public Visitor; venue user | P7 — Live Competition, Results, and Appeals | End-to-end test; recovery test; accessibility test. | Specified |
| [QMDB-FR-RSL-001](functional/07-results-appeals-certificates-and-records.md) | Calculate ranking, placement, ties, and disqualification | Critical | Results | Chief Judge | P7 — Live Competition, Results, and Appeals | Unit/property test; integration test. | Specified |
| [QMDB-FR-RSL-002](functional/07-results-appeals-certificates-and-records.md) | Publish and hold Provisional Results | Critical | Results | Chief Judge; Result Publisher | P7 — Live Competition, Results, and Appeals | Authorization test; end-to-end/accessibility test. | Specified |
| [QMDB-FR-RSL-003](functional/07-results-appeals-certificates-and-records.md) | Finalize and package Results | Critical | Results | Result Finalizer category | P7 — Live Competition, Results, and Appeals | End-to-end test; authorization/concurrency test; integrity test. | Specified |
| [QMDB-FR-RSL-004](functional/07-results-appeals-certificates-and-records.md) | Correct, supersede, withdraw, or archive Results | Critical | Results | Result Correction initiator/approver categories | P7 — Live Competition, Results, and Appeals | Authorization test; integration/end-to-end test; audit inspection. | Specified |
| [QMDB-FR-APL-001](functional/07-results-appeals-certificates-and-records.md) | Accept eligible Appeals within governed time | Critical | Appeals | Competitor/Guardian/authorized appellant | P7 — Live Competition, Results, and Appeals | Boundary-time test; authorization/idempotency test; accessibility test. | Specified |
| [QMDB-FR-APL-002](functional/07-results-appeals-certificates-and-records.md) | Assign and conduct independent Appeal review | Critical | Appeals | Appeal Reviewer | P7 — Live Competition, Results, and Appeals | Authorization test; workflow test; manual domain/privacy review. | Specified |
| [QMDB-FR-APL-003](functional/07-results-appeals-certificates-and-records.md) | Decide and close an Appeal without rewriting sources | Critical | Appeals | Appeal Reviewer/decision authority category | P7 — Live Competition, Results, and Appeals | Integration test; authorization test; audit inspection. | Specified |
| [QMDB-FR-CER-001](functional/07-results-appeals-certificates-and-records.md) | Determine Certificate eligibility and recipient snapshot | Critical | Certificates | Certificate Officer | P8 — Certificates, Record Passport, and Trusted Archive | Integration test; authorization/accessibility test. | Specified |
| [QMDB-FR-CER-002](functional/07-results-appeals-certificates-and-records.md) | Generate, sign, issue, and deliver a Certificate | Critical | Certificates | Certificate Officer | P8 — Certificates, Record Passport, and Trusted Archive | Cryptographic/integration test; authorization/security/accessibility test. | Specified |
| [QMDB-FR-CER-003](functional/07-results-appeals-certificates-and-records.md) | Verify Certificates privately and continuously | Critical | Certificates | External Verification Consumer | P8 — Certificates, Record Passport, and Trusted Archive | Cryptographic/security/end-to-end/accessibility test. | Specified |
| [QMDB-FR-CER-004](functional/07-results-appeals-certificates-and-records.md) | Reissue, correct, supersede, revoke, and rotate keys | Critical | Certificates | Certificate Officer; Security key custodian | P8 — Certificates, Record Passport, and Trusted Archive | Authorization/integration/cryptographic test; audit inspection. | Specified |
| [QMDB-FR-REC-001](functional/07-results-appeals-certificates-and-records.md) | Build durable records and provenance projections | High | Records | Records Steward; Memorizer | P8 — Certificates, Record Passport, and Trusted Archive | Integration/authorization test; manual domain/privacy review. | Specified |
| [QMDB-FR-IMP-001](functional/07-results-appeals-certificates-and-records.md) | Import and reconcile legacy records | High | Records | Records Importer; Records Reviewer | P8 — Certificates, Record Passport, and Trusted Archive | Integration/security/idempotency test; manual domain/privacy review. | Specified |
| [QMDB-FR-MED-001](functional/08-media-recitation-clips-and-moderation.md) | Authorize and quarantine media uploads  | Critical | Media and Evidence | Authorized uploader | P9 — Audio and Video Evidence | API authorization, tenant-isolation, expiry, and quota tests. | Specified |
| [QMDB-FR-MED-002](functional/08-media-recitation-clips-and-moderation.md) | Validate, quarantine, and process media  | Critical | Media and Evidence | Media worker | P9 — Audio and Video Evidence | Malware fixtures, type-spoof tests, processing integration and failure tests. | Specified |
| [QMDB-FR-MED-003](functional/08-media-recitation-clips-and-moderation.md) | Govern media access and derivatives  | Critical | Media and Evidence | Authorized viewer | P9 — Audio and Video Evidence | Access-control, URL leakage, consent withdrawal, and cache tests. | Specified |
| [QMDB-FR-MED-004](functional/08-media-recitation-clips-and-moderation.md) | Publish, unpublish, retain, and hold media  | High | Media and Evidence | Media publisher | P9 — Audio and Video Evidence | Authorization, consent-race, hold, cache-purge, and audit tests. | Specified |
| [QMDB-FR-MED-005](functional/08-media-recitation-clips-and-moderation.md) | Recover media processing and capacity failures  | Critical | Media and Evidence | Platform operator | P9 — Audio and Video Evidence | Load, chaos, queue, and scoring-isolation tests. | Specified |
| [QMDB-FR-SOC-001](functional/08-media-recitation-clips-and-moderation.md) | Create and publish Recitation Clips  | High | Community and Recitation Clips | Reciter | P10 — Recitation Clips and Community Safety | Workflow, privacy, guardian, reference-link and moderation tests. | Specified |
| [QMDB-FR-SOC-002](functional/08-media-recitation-clips-and-moderation.md) | Enforce child-aware Clip and interaction controls  | Critical | Community and Recitation Clips | Minor reciter | P10 — Recitation Clips and Community Safety | Age-boundary, guardianship dispute, privacy, block, and publication tests. | Specified |
| [QMDB-FR-SOC-003](functional/08-media-recitation-clips-and-moderation.md) | Govern social interactions  | High | Community and Recitation Clips | Community member | P10 — Recitation Clips and Community Safety | Block, visibility, rate-limit, idempotency and overload tests. | Specified |
| [QMDB-FR-MOD-001](functional/08-media-recitation-clips-and-moderation.md) | Create and triage moderation cases | Critical | Moderation and Safety | Reporter or safety detector | P10 — Recitation Clips and Community Safety | Integration test; authorization test; security test; manual privacy review. | Specified |
| [QMDB-FR-MOD-002](functional/08-media-recitation-clips-and-moderation.md) | Decide, notify, appeal, and restore moderation action | Critical | Moderation and Safety | Moderator | P10 — Recitation Clips and Community Safety | Authorization test; integration test; security test; accessibility test. | Specified |
| [QMDB-FR-SRH-001](functional/09-search-notifications-and-reporting.md) | Search public and authorized records  | Critical | Search and Discovery | Public visitor or authorized user | P11 — Search, Analytics, and National Reporting | Tenant isolation, minor privacy, status, filter, pagination, Arabic-search, and leakage tests. | Specified |
| [QMDB-FR-SRH-002](functional/09-search-notifications-and-reporting.md) | Maintain privacy-aware search projections  | Critical | Search and Discovery | Search indexer | P11 — Search, Analytics, and National Reporting | Out-of-order, duplicate, rebuild, lag, and privacy-removal tests. | Specified |
| [QMDB-FR-SRH-003](functional/09-search-notifications-and-reporting.md) | Represent record provenance and status in discovery  | High | Search and Discovery | Public visitor or authorized analyst | P11 — Search, Analytics, and National Reporting | Status-label, stale-projection, supersession and accessibility tests. | Specified |
| [QMDB-FR-NTF-001](functional/09-search-notifications-and-reporting.md) | Create and deliver policy-governed notifications  | High | Notifications | Affected user | P12 — Quality, Accessibility, and Production Hardening | Dedupe, preference, mandatory override, retry, dead-letter, privacy and accessibility tests. | Specified |
| [QMDB-FR-NTF-002](functional/09-search-notifications-and-reporting.md) | Expose notification status and recover delivery failures  | High | Notifications | Notification recipient | P12 — Quality, Accessibility, and Production Hardening | Ownership, pagination, retry, support-access, and transaction-isolation tests. | Specified |
| [QMDB-FR-RPT-001](functional/09-search-notifications-and-reporting.md) | Produce scope-safe operational and aggregate reports  | Critical | Reporting and Analytics | Authorized coordinator or organization officer | P11 — Search, Analytics, and National Reporting | Scope, small-group, freshness, load, accessibility and national-oversight tests. | Specified |
| [QMDB-FR-RPT-002](functional/09-search-notifications-and-reporting.md) | Generate controlled exports  | Critical | Reporting and Analytics | Authorized exporter | P11 — Search, Analytics, and National Reporting | Authorization-race, approval, step-up, expiry, field-minimization and audit tests. | Specified |
| [QMDB-FR-RPT-003](functional/09-search-notifications-and-reporting.md) | Protect core operations from reporting and analytics load  | Critical | Reporting and Analytics | Platform operator | P11 — Search, Analytics, and National Reporting | Load, replica-lag, queue, failure, and scoring-isolation tests. | Specified |
| [QMDB-FR-PRI-001](functional/10-privacy-audit-security-operations-and-integrations.md) | Version privacy notices, purposes, and consent  | Critical | Privacy and Data Governance | Data subject or guardian | P2 — Identity, Security, and Tenant Isolation | Authorization, version, withdrawal, guardian and privacy-review tests. | Specified |
| [QMDB-FR-PRI-002](functional/10-privacy-audit-security-operations-and-integrations.md) | Manage verified personal-data requests  | Critical | Privacy and Data Governance | Data subject or authorized guardian | P12 — Quality, Accessibility, and Production Hardening | End-to-end, identity, guardian, disclosure, deadline and accessibility tests. | Specified |
| [QMDB-FR-PRI-003](functional/10-privacy-audit-security-operations-and-integrations.md) | Assess closure, deletion, anonymization, retention, and holds  | Critical | Privacy and Data Governance | Privacy officer | P12 — Quality, Accessibility, and Production Hardening | Approval, hold, deletion-scope, recovery, audit and manual privacy review. | Specified |
| [QMDB-FR-PRI-004](functional/10-privacy-audit-security-operations-and-integrations.md) | Minimize profiles and public disclosure  | Critical | Privacy and Data Governance | Person or guardian | P3 — Nigerian Geography, Organizations, and People | Field-leakage, minor, cache/index, export and accessibility tests. | Specified |
| [QMDB-FR-AUD-001](functional/10-privacy-audit-security-operations-and-integrations.md) | Create tamper-evident audit events  | Critical | Audit and Integrity | System or authorized actor | P2 — Identity, Security, and Tenant Isolation | Transaction/outbox, redaction, chain, denial and failure tests. | Specified |
| [QMDB-FR-AUD-002](functional/10-privacy-audit-security-operations-and-integrations.md) | Verify, inspect, and export audit evidence  | Critical | Audit and Integrity | Audit reviewer | P12 — Quality, Accessibility, and Production Hardening | Chain-tamper, scope, step-up, approval, export-expiry and accessibility tests. | Specified |
| [QMDB-FR-SEC-001](functional/10-privacy-audit-security-operations-and-integrations.md) | Detect and triage security events  | Critical | Security Operations | Security operator | P2 — Identity, Security, and Tenant Isolation | Detection, dedupe, severity, scope, evidence and alert tests. | Specified |
| [QMDB-FR-SEC-002](functional/10-privacy-audit-security-operations-and-integrations.md) | Contain, recover, and review security incidents  | Critical | Security Operations | Security incident commander | P12 — Quality, Accessibility, and Production Hardening | Tabletop, authorization, containment, restore, notification and post-review tests. | Specified |
| [QMDB-FR-OPS-001](functional/10-privacy-audit-security-operations-and-integrations.md) | Control temporary support access  | Critical | Platform Operations | Support operator | P2 — Identity, Security, and Tenant Isolation | No-impersonation, scope, expiry, revocation, audit and accessibility tests. | Specified |
| [QMDB-FR-OPS-002](functional/10-privacy-audit-security-operations-and-integrations.md) | Control emergency break-glass access  | Critical | Platform Operations | Authorized emergency operator | P2 — Identity, Security, and Tenant Isolation | Step-up, expiry-mid-action, excluded-action, notification, continuous-audit and review tests. | Specified |
| [QMDB-FR-OPS-003](functional/10-privacy-audit-security-operations-and-integrations.md) | Version configuration and operate service health  | Critical | Platform Operations | Platform operator | P1 — Engineering and Repository Foundation | Schema, authorization, rollback, health, queue, load and operational exercises. | Specified |
| [QMDB-FR-OPS-004](functional/10-privacy-audit-security-operations-and-integrations.md) | Back up, restore, and reconcile authoritative services  | Critical | Platform Operations | Platform recovery operator | P12 — Quality, Accessibility, and Production Hardening | Restore exercise, chain, replay/idempotency, tenant, projection and recovery tests. | Specified |
| [QMDB-FR-OPS-005](functional/10-privacy-audit-security-operations-and-integrations.md) | Rotate keys and secrets safely  | Critical | Platform Operations | Security key custodian | P12 — Quality, Accessibility, and Production Hardening | Rotation, compromise, rollback, historical verification and secret-leak tests. | Specified |
| [QMDB-FR-INT-001](functional/10-privacy-audit-security-operations-and-integrations.md) | Register and govern API clients  | Critical | Integrations | Integration administrator | P12 — Quality, Accessibility, and Production Hardening | Client auth, cross-tenant, scope, rate, rotation and revocation tests. | Specified |
| [QMDB-FR-INT-002](functional/10-privacy-audit-security-operations-and-integrations.md) | Deliver signed, replay-safe webhooks  | High | Integrations | Subscribed integration | P12 — Quality, Accessibility, and Production Hardening | Signature, replay, duplicate, retry, dead-letter, suspension and privacy tests. | Specified |
| [QMDB-FR-INT-003](functional/10-privacy-audit-security-operations-and-integrations.md) | Protect authoritative writes from integrations  | Critical | Integrations | Integration client | P12 — Quality, Accessibility, and Production Hardening | API contract, alternate-route, bulk, cross-tenant and security tests. | Specified |
| [QMDB-FR-OFF-001](functional/10-privacy-audit-security-operations-and-integrations.md) | Create signed offline assignment packages and local drafts  | Critical | Offline and Venue Resilience | Venue operator or assigned judge | P13 — Pilot, Offline Venue Mode, and National Rollout | Package signature, device, expiry, scope, encryption, clock and offline accessibility tests. | Specified |
| [QMDB-FR-OFF-002](functional/10-privacy-audit-security-operations-and-integrations.md) | Synchronize and reconcile offline submissions  | Critical | Offline and Venue Resilience | Venue synchronization operator | P13 — Pilot, Offline Venue Mode, and National Rollout | Out-of-order, duplicate, stale, conflict, network-ack, tamper and recovery tests. | Specified |
| [QMDB-FR-UXA-001](functional/10-privacy-audit-security-operations-and-integrations.md) | Provide accessible, internationalized functional interaction  | Critical | Experience and Accessibility | Any platform user | P12 — Quality, Accessibility, and Production Hardening | Automated accessibility plus manual keyboard, screen-reader, RTL, contrast, zoom, and reduced-motion tests. | Specified |
| [QMDB-FR-UXA-002](functional/10-privacy-audit-security-operations-and-integrations.md) | Handle time, connectivity, and recoverable interaction consistently  | Critical | Experience and Accessibility | Any platform user | P12 — Quality, Accessibility, and Production Hardening | Boundary, timezone, clock-skew, network-ack, retry, progressive-enhancement and accessibility tests. | Specified |

## Coverage summary

The 113 requirements cover identity and tenancy; people and guardianship; geography and organizations; Qur’an reference governance; competition configuration and registration; scheduling, judging, and exact scoring; live results, appeals, certificates, and trusted records; media and community safety; search, notifications, and reporting; and privacy, audit, security, operations, integrations, accessibility, time, and offline resilience. Every P1–P13 implementation phase has mapped obligations.

## Known open policy dependencies

The [Open-Decisions Register](../project/open-decisions.md) governs unresolved organization recognition, Minor/Guardian rules, Ruleset examples, appeals authority, public-profile fields, retention, signing-key custody, moderation policy, rollout governance, authentication/recovery policy, Person merge authority, offline reconciliation authority, separation of duties, and appeal fees. Requirements apply conservative safe behavior until a qualified owner records a resolution.

## Change-control rules

- Requirement identifiers are stable and are never reassigned.
- An obligation change requires owner review, document-version history, traceability and acceptance updates, and open-decision/ADR impact review.
- Locked ADR-001–ADR-020 and INV-001–INV-030 cannot be weakened through requirement edits.
- A governed open-decision resolution may refine behavior only after affected requirements and tests are updated.

## Citation by implementation work

Every later issue, design, migration, API, implementation change, test, runbook, deployment gate, and acceptance report shall cite all applicable `QMDB-FR-...` identifiers. Evidence should additionally cite the acceptance scenario and capability when available.

## Related documents

- [Use-case catalog](P0-B02-use-case-catalog.md)
- [Workflows and state machines](P0-B02-workflows-and-state-machines.md)
- [Traceability matrix](P0-B02-traceability-matrix.md)
- [P0-B01 requirements](P0-B01-requirements.md)
