# QMDB-P0-B01 Baseline Requirements

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Document version | 1.0.0 |
| Status | Baselined requirements; implementation not started |
| Current phase | P0 — Product Constitution and System Requirements |
| Last updated | 2026-08-24 |
| Document owner role | Product and Requirements Governance |
| Approval status | QMDB-P0-B01 baseline established; formal organizational approval pending |

## Purpose

This document converts the product constitution, boundaries, canonical domain language, actors, module ownership, and business invariants into uniquely identified and objectively verifiable obligations.

## Scope and conventions

Priority “Must” means the obligation is required by QMDB-BL-001; it does not assert implementation. The implementation phase remains unassigned until the post-P0-B02 roadmap decision (OD-030). Verification methods describe evidence later work must produce. Each statement has one primary obligation and uses “shall.”

## Product constitution requirements

### QMDB-CON-001 — Governed national ecosystem

- **Requirement statement:** QMDB shall operate as one governed ecosystem for competition administration, participation, human judging, official records, achievement history, verifiable certificates, moderated recitation content, and oversight across approved national, State/FCT, LGA/Area Council, school, mosque, and organization contexts.
- **Rationale:** Connected capabilities need one product purpose and canonical source of truth.
- **Primary actors:** Product Governance; all platform actors.
- **Affected modules:** All modules.
- **Priority:** Must.
- **Verification method:** Constitution review and capability-to-module/actor traceability inspection.
- **Related business invariants:** INV-001, INV-003, INV-021.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

### QMDB-CON-002 — Constitutional principles

- **Requirement statement:** Every QMDB design and change shall demonstrate conformance with trust before engagement, competition integrity, privacy and child safety by design, accessibility, historical accuracy, transparent correction, least privilege, tenant isolation, evidence-based records, human-authoritative judging, low-bandwidth use, national scalability, and respectful Qur’anic presentation.
- **Rationale:** Product principles must govern trade-offs rather than remain aspirational prose.
- **Primary actors:** Product, Domain, Security, Privacy, Child-Safety, Accessibility, and Technical Change Governance.
- **Affected modules:** All modules.
- **Priority:** Must.
- **Verification method:** Architecture/change-review checklist with requirement and test evidence for every applicable principle.
- **Related business invariants:** INV-007, INV-014, INV-017, INV-020, INV-030.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

### QMDB-CON-003 — Human-authoritative judging

- **Requirement statement:** QMDB shall treat qualified human judging as authoritative and label automated recitation, pronunciation, tajwīd, speech, or memorization analysis as advisory unless a later formally approved policy changes that boundary.
- **Rationale:** Official religious competition outcomes must not be silently delegated to automation.
- **Primary actors:** Judges; Chief Judges; Competition-Rules Governance Body; Qualified Qur’an Reviewers.
- **Affected modules:** Judging, Scoring, Qur’an Reference, Results, Integrations.
- **Priority:** Must.
- **Verification method:** Policy, UI-label, API-contract, and authorization tests proving advisory outputs cannot finalize or modify official outcomes.
- **Related business invariants:** INV-010, INV-028.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

### QMDB-CON-004 — Initial exclusions and governed deferrals

- **Requirement statement:** The initial QMDB release shall exclude unrestricted direct messaging, unmoderated user livestreaming, automated official scoring decisions, executable administrator formulas, public Minor precise locations or identity documents, mandatory blockchain/cryptocurrency, premature microservices, and replacement of qualified authorities, while leaving payments, sponsorships, monetization, and international expansion subject to recorded future decisions.
- **Rationale:** Explicit boundaries prevent unsafe or unauthorized scope expansion.
- **Primary actors:** Product Governance; Architecture Governance; Child-Safety Governance.
- **Affected modules:** Recitation Clips, Media, Scoring, Integrations, Privacy, Platform Operations.
- **Priority:** Must.
- **Verification method:** Release-scope review, route/capability inventory, architecture test, and open-decision trace inspection.
- **Related business invariants:** INV-017, INV-020, INV-021, INV-028.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

## System-boundary requirements

### QMDB-BND-001 — Owned capability boundary

- **Requirement statement:** QMDB shall own the authoritative domain workflows and records for registration, snapshots, assignments, scoring, result versions, appeals, certificates, Consent, audit, moderation, and the approved projections derived from them.
- **Rationale:** Ownership must be explicit before data and API design.
- **Primary actors:** Domain Governance; module owners.
- **Affected modules:** Registration, Judging, Scoring, Results, Appeals, Certificates, Guardianship, Audit, Moderation, Records.
- **Priority:** Must.
- **Verification method:** Module ownership review and contract tests that route writes only through the owning module.
- **Related business invariants:** INV-007, INV-008, INV-014, INV-016, INV-021.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

### QMDB-BND-002 — External provider boundary

- **Requirement statement:** Every external provider shall be limited to its approved transport, storage, scanning, processing, key-protection, monitoring, identity-evidence, attestation, or reporting function without becoming authoritative for official competition scores.
- **Rationale:** Outsourcing a technical function must not outsource domain truth.
- **Primary actors:** Authorized Integration Clients; Platform Operators; Security Operators.
- **Affected modules:** Integrations, Notifications, Media, Security Operations, Platform Operations, Scoring, Results.
- **Priority:** Must.
- **Verification method:** Data-flow review, service-principal scope inspection, API negative tests, and database/network access review.
- **Related business invariants:** INV-028, INV-029.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

### QMDB-BND-003 — Centralized governed architecture

- **Requirement statement:** QMDB shall preserve one governed source of truth while permitting redundant application processes, database topology, workers, caches, and delivery infrastructure without relying on one physical server or unrestricted administrator.
- **Rationale:** Central governance and operational distribution are compatible and necessary.
- **Primary actors:** Architecture Governance; Platform Operators; Internal Auditors.
- **Affected modules:** Platform Operations, Workspaces, Audit, all authoritative modules.
- **Priority:** Must.
- **Verification method:** Deployment architecture review, failure-domain analysis, authority matrix review, and source-of-truth mapping.
- **Related business invariants:** INV-022, INV-024, INV-030.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

### QMDB-BND-004 — Trust-boundary controls

- **Requirement statement:** Every crossing among public clients, authenticated users, privileged actors, the Core PHP application, MySQL, Redis, workers, private object storage, CDN delivery, external integrations, operators, and Break-Glass Access shall enforce authenticated service/user identity where applicable, least privilege, validation, scoped data, and auditable outcomes.
- **Rationale:** Trust changes at each boundary and cannot be assumed transitively.
- **Primary actors:** Security Operators; Platform Operators; all authenticated actors and service principals.
- **Affected modules:** Identity, Access Control, Integrations, Audit, Platform Operations, Security Operations, Media.
- **Priority:** Must.
- **Verification method:** Threat model, trust-boundary review, integration/security tests, and privileged-access audit inspection.
- **Related business invariants:** INV-019, INV-024, INV-027, INV-029.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

## Identity requirements

### QMDB-IDN-001 — Identity concept separation

- **Requirement statement:** QMDB shall represent Person, User Account, Profile, Public Profile, Competitor Profile, Judge Profile, Membership, Role, Permission, Administrative Scope, and Competition Assignment as distinct concepts with independent lifecycles.
- **Rationale:** Conflation creates duplicate people and unintended authority.
- **Primary actors:** Registered Individuals; Identity and Access administrators.
- **Affected modules:** People, Identity, Access Control, Organizations, Judging.
- **Priority:** Must.
- **Verification method:** Domain/schema review and architecture tests for separate types, ownership, and lifecycle operations.
- **Related business invariants:** INV-003, INV-004.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

### QMDB-IDN-002 — Durable Person identity

- **Requirement statement:** QMDB shall reuse one durable Person identity wherever reasonably supported by evidence rather than create a new Person solely for a new Competition Edition or contextual role.
- **Rationale:** Achievement history and conflict checks depend on continuity.
- **Primary actors:** Registrars; Registered Individuals; Data Stewards.
- **Affected modules:** People, Competitors, Registration, Records, Audit.
- **Priority:** Must.
- **Verification method:** Duplicate-detection/merge workflow tests, cross-edition participation test, and audit/provenance inspection.
- **Related business invariants:** INV-003, INV-007.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

### QMDB-IDN-003 — Contextual multi-role authority

- **Requirement statement:** QMDB shall allow one Person to hold multiple roles while constraining each capability by Workspace, organization, geography, Competition Edition, category, round, panel, Session, resource, time, conflict state, and policy as applicable.
- **Rationale:** Roles are contextual, not permanent person classifications.
- **Primary actors:** All privileged and competition actors.
- **Affected modules:** Access Control, Organizations, People, Competitions, Judging, Appeals.
- **Priority:** Must.
- **Verification method:** Authorization matrix tests covering allowed combinations, scope expiry, cross-role conflicts, and denied out-of-scope access.
- **Related business invariants:** INV-004, INV-024.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

### QMDB-IDN-004 — Authentication object separation

- **Requirement statement:** QMDB shall manage Credentials, Sessions, and Devices as separate security objects linked to a User Account, with independent enrollment, rotation, revocation, expiry, and audit behavior.
- **Rationale:** Security state needs precise lifecycle control.
- **Primary actors:** Registered Individuals; Security Operators; Support Officers.
- **Affected modules:** Identity, Security Operations, Audit, Notifications.
- **Priority:** Must.
- **Verification method:** Unit/integration tests for credential rotation, session revocation, device removal, recovery, and Audit Events.
- **Related business invariants:** INV-003, INV-029.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

### QMDB-IDN-005 — Public identifiers are non-authoritative

- **Requirement statement:** QMDB shall evaluate protected reads and actions using authenticated context and resource policy rather than grant access from a public identifier, QR value, route parameter, cache key, or Idempotency Key.
- **Rationale:** Observable identifiers can be guessed, copied, and replayed.
- **Primary actors:** Public Visitors; Registered Individuals; Authorized Integration Clients.
- **Affected modules:** Identity, Access Control, Certificates, Records, Presentation-facing modules.
- **Priority:** Must.
- **Verification method:** IDOR/BOLA negative tests, unauthenticated replay tests, and safe not-found/denial response review.
- **Related business invariants:** INV-027.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

## Actor requirements

### QMDB-ACT-001 — Actor-specific capability policy

- **Requirement statement:** Every human, organization, oversight, integration, and worker actor shall have explicit permitted capability areas, restrictions, sensitive actions, authentication expectation, scope, data sensitivity, and Audit Event requirements.
- **Rationale:** Generic actors create ambiguous and excessive privilege.
- **Primary actors:** Access-Control Governance; all catalogue actors.
- **Affected modules:** Access Control, Identity, Audit, all business modules.
- **Priority:** Must.
- **Verification method:** Actor catalogue coverage review and actor-to-side/permission matrix tests.
- **Related business invariants:** INV-004, INV-025, INV-028.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

### QMDB-ACT-002 — No normal unrestricted administrator

- **Requirement statement:** QMDB shall operate privileged capabilities through named, scoped, least-privilege roles without providing one normal operational role with unrestricted tenant, business, security, privacy, audit, and infrastructure authority.
- **Rationale:** Separation of duties limits abuse and error impact.
- **Primary actors:** Organization Administrators; Coordinators; Security/Platform Operators; Auditors.
- **Affected modules:** Access Control, Workspaces, Security Operations, Platform Operations, Audit, Privacy.
- **Priority:** Must.
- **Verification method:** Role catalogue inspection, toxic-permission-combination tests, and cross-workspace negative tests.
- **Related business invariants:** INV-024, INV-025.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

### QMDB-ACT-003 — Conflict lifecycle

- **Requirement statement:** QMDB shall support conflict declaration, authorized review, recusal, replacement, exception authorization, and preserved Audit Events for actors whose relationships may affect competition, Appeal, moderation, or governance impartiality.
- **Rationale:** Hidden conflicts undermine trustworthy decisions.
- **Primary actors:** Judges; Chief Judges; Appeal Reviewers; organizers; Coaches; Guardians; Teachers; Competitors; governance actors.
- **Affected modules:** Judging, Appeals, Competitions, Access Control, Moderation, Audit.
- **Priority:** Must.
- **Verification method:** Workflow and authorization tests for declaration, recusal access removal, replacement, exception approval, and immutable history.
- **Related business invariants:** INV-004, INV-013, INV-016.
- **Future implementation phase:** P0-B02 detailed specification; final conflict policy unassigned.

### QMDB-ACT-004 — Service-actor identity

- **Requirement statement:** Every Background Worker, Media Processing Worker, and Authorized Integration Client shall use a distinct scoped workload identity instead of a human Session, shared account, or unbounded service credential.
- **Rationale:** Machine actions need attributable, revocable least privilege.
- **Primary actors:** Platform Operators; Security Operators; service actors.
- **Affected modules:** Identity, Access Control, Integrations, Media, Platform Operations, Audit.
- **Priority:** Must.
- **Verification method:** Credential inventory review, workload authorization tests, expiry/rotation tests, and service Audit Event inspection.
- **Related business invariants:** INV-023, INV-028, INV-029.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

## Geography requirements

### QMDB-GEO-001 — Version-aware administrative hierarchy

- **Requirement statement:** QMDB shall represent Country, State, Federal Capital Territory, Local Government Area, Area Council, Ward, Community, aliases, parent relationships, and historical validity through an extensible Administrative Area hierarchy rather than only fixed State/LGA fields.
- **Rationale:** Nigerian hierarchy and future change require typed, historical relationships.
- **Primary actors:** Geography/Data Stewards; Coordinators; Organizers.
- **Affected modules:** Geography, Organizations, Competitions, Registration, Reporting.
- **Priority:** Must.
- **Verification method:** Domain/schema tests for each area type, FCT/Area Council path, aliases, effective dates, and historical lookup.
- **Related business invariants:** INV-006.
- **Future implementation phase:** P0-B02 detailed specification; authoritative seed source unassigned.

### QMDB-GEO-002 — Geographic-purpose separation

- **Requirement statement:** QMDB shall store Host Location, Eligibility Geography, Represented Geography, Organization Coverage Area, and Administrative Scope as separately named relationships with their own authority and historical context.
- **Rationale:** A place of hosting, eligibility, representation, coverage, and authority are different facts.
- **Primary actors:** Organizers; Registrars; Coordinators; Organization Administrators.
- **Affected modules:** Geography, Organizations, Competitions, Registration, Eligibility, Scheduling, Access Control.
- **Priority:** Must.
- **Verification method:** Domain/schema review and scenario tests for a nationally scoped, privately organized, State-hosted, nomination-restricted Edition.
- **Related business invariants:** INV-006.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

## Organization and Workspace requirements

### QMDB-ORG-001 — Explicit Workspace ownership

- **Requirement statement:** Every tenant-owned QMDB record shall contain a non-null `workspace_id` identifying exactly one owning Workspace.
- **Rationale:** Tenant ownership must be explicit in storage and policy.
- **Primary actors:** Workspace Administrators; module owners; Platform Operators.
- **Affected modules:** Workspaces and every tenant-owned module.
- **Priority:** Must.
- **Verification method:** Schema architecture test, fixture validation, repository query tests, and missing-context negative tests.
- **Related business invariants:** INV-001.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

### QMDB-ORG-002 — Workspace-aware relationship integrity

- **Requirement statement:** Every applicable tenant-owned parent-child relationship shall use composite Workspace-aware relational constraints that reject a child referencing a parent in another Workspace.
- **Rationale:** Application predicates alone do not prevent cross-tenant corruption.
- **Primary actors:** Database/Domain Architects; module owners.
- **Affected modules:** Workspaces, Organizations, and all tenant-owned relational modules.
- **Priority:** Must.
- **Verification method:** Schema constraint inspection and transaction tests attempting cross-Workspace references for each relationship class.
- **Related business invariants:** INV-002.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

### QMDB-ORG-003 — Distinct organization relationships

- **Requirement statement:** QMDB shall record organizing, sponsoring, hosting, nominating, venue-providing, judge-supplying, and historical-record-attesting relationships independently with actor, organization, scope, time, status, method, and provenance as applicable.
- **Rationale:** One relationship must not silently imply another.
- **Primary actors:** Organizations; Organizers; Registrars; Governance actors.
- **Affected modules:** Organizations, Competitions, Registration, Judging, Records, Audit.
- **Priority:** Must.
- **Verification method:** Domain relationship tests and authorization tests proving each relation grants only its approved capabilities.
- **Related business invariants:** INV-004, INV-006.
- **Future implementation phase:** P0-B02 detailed specification; organization-verification authority OD-009.

## Competition requirements

### QMDB-CMP-001 — Competition hierarchy

- **Requirement statement:** QMDB shall model Competition Series, Competition Edition, Category, Division, Round, Session, Performance, Score Sheets, and Result as separate linked concepts with each Edition belonging to exactly one Series.
- **Rationale:** Recurrence, structure, operation, judging, and outcome have distinct lifecycles.
- **Primary actors:** Competition Directors; Registrars; Judges; public result consumers.
- **Affected modules:** Competitions, Scheduling, Judging, Scoring, Results.
- **Priority:** Must.
- **Verification method:** Domain/schema cardinality tests and end-to-end lifecycle trace review.
- **Related business invariants:** INV-005, INV-011.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

### QMDB-CMP-002 — Competition-dimension separation

- **Requirement statement:** Every Competition Edition shall record Competition Scope, Organizer Type, Organizer Organization, Host Location, Eligibility Geography, Represented Geography, Series, Category, Division, Round, and Session without deriving one dimension solely from another.
- **Rationale:** Scope, authority, place, eligibility, representation, and structure are independent.
- **Primary actors:** Competition Directors; Registrars; Coordinators.
- **Affected modules:** Competitions, Organizations, Geography, Registration, Eligibility, Scheduling.
- **Priority:** Must.
- **Verification method:** Scenario-based domain/API tests using differing scope, organizer, host, eligibility, and representation values.
- **Related business invariants:** INV-006.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

### QMDB-CMP-003 — Registration, Nomination, and eligibility separation

- **Requirement statement:** QMDB shall preserve Registration, Nomination, Eligibility Check, acceptance status, and Check-In as separate versioned facts with actor, method, time, scope, reason, and evidence where applicable.
- **Rationale:** A nomination or presence does not itself establish eligibility or participation acceptance.
- **Primary actors:** Competitors; Guardians; Registrars; nominating Organizations.
- **Affected modules:** Registration, Eligibility, Organizations, Guardianship, Audit.
- **Priority:** Must.
- **Verification method:** Workflow tests for nominated/not-registered, registered/ineligible, accepted/not-checked-in, withdrawal, and reassessment cases.
- **Related business invariants:** INV-007.
- **Future implementation phase:** P0-B02 detailed specification; eligibility policy unassigned.

### QMDB-CMP-004 — Historical Participant Snapshot

- **Requirement statement:** QMDB shall capture an identified Participant Snapshot at approved registration, Check-In, Performance, or Result milestones in a form that prevents later current Profile changes from silently rewriting that snapshot.
- **Rationale:** Official records must reflect facts used at the event time.
- **Primary actors:** Competitors; Guardians; Registrars; Record Stewards.
- **Affected modules:** Registration, People, Judging, Results, Records, Audit.
- **Priority:** Must.
- **Verification method:** Integration test updating a Profile after snapshot creation and confirming unchanged historical data/version/provenance.
- **Related business invariants:** INV-007.
- **Future implementation phase:** P0-B02 detailed specification; snapshot milestones to be detailed.

### QMDB-CMP-005 — Controlled schedule and assignment versions

- **Requirement statement:** QMDB shall version Session schedules, Draw Orders, Passage Assignments, Judge Panel assignments, and approved changes with release state, reason, actor, time, and Audit Events.
- **Rationale:** Event order and advance information affect fairness and operations.
- **Primary actors:** Competition Directors; Registrars; Judges; Competitors.
- **Affected modules:** Scheduling, Competitions, Judging, Qur’an Reference, Notifications, Audit.
- **Priority:** Must.
- **Verification method:** Version/concurrency tests, concealed-passage authorization tests, and change-history inspection.
- **Related business invariants:** INV-011, INV-024.
- **Future implementation phase:** P0-B02 detailed specification; release policy unassigned.

### QMDB-CMP-006 — Judge Panel assignment integrity

- **Requirement statement:** QMDB shall permit judging only when the Judge has a current Competition Assignment to the applicable Edition, Category/Division, Round, panel, Session or Performance, time window, and resolved conflict state required by policy.
- **Rationale:** Judge Profile or organization title alone cannot authorize scoring.
- **Primary actors:** Judges; Chief Judges; Competition Directors.
- **Affected modules:** Judging, Access Control, Competitions, Scheduling, Audit.
- **Priority:** Must.
- **Verification method:** Authorization matrix tests for valid, expired, recused, replaced, wrong-panel, wrong-round, and wrong-Workspace assignments.
- **Related business invariants:** INV-004, INV-013.
- **Future implementation phase:** P0-B02 detailed specification; conflict policy unassigned.

### QMDB-CMP-007 — Live competition projection

- **Requirement statement:** QMDB shall expose live updates through sequenced, clearly labeled Read Models that can be reconciled or rebuilt from authoritative records and cannot be used to finalize a Result.
- **Rationale:** Live delivery may be delayed, duplicated, or temporarily stale.
- **Primary actors:** Public Visitors; Competitors; Organizers; Platform Operators.
- **Affected modules:** Results, Search, Platform Operations, Notifications.
- **Priority:** Must.
- **Verification method:** Projection rebuild, duplicate/out-of-order/missing-sequence tests, stale-state UI test, and source-of-truth architecture test.
- **Related business invariants:** INV-022, INV-023.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

## Scoring and result-integrity requirements

### QMDB-SCR-001 — Exact official arithmetic

- **Requirement statement:** Every official Score Item, Deduction, Judge Total, Aggregated Score, comparison, and ranking calculation shall use approved exact decimal precision and rounding rules without `FLOAT` or `DOUBLE` storage or arithmetic.
- **Rationale:** Binary floating-point variance can change rankings.
- **Primary actors:** Judges; Chief Judges; Competition-Rules Governance Body.
- **Affected modules:** Scoring, Results, Reporting.
- **Priority:** Must.
- **Verification method:** Schema scan, architecture test, boundary/property tests, and approved calculation vectors.
- **Related business invariants:** INV-009.
- **Future implementation phase:** P0-B02 detailed specification; precision/rules await OD-010.

### QMDB-SCR-002 — Server-authoritative calculation

- **Requirement statement:** QMDB shall recalculate and validate every authoritative score, aggregation, tie break, Ranking, and Placement on the server from identified inputs and a Ruleset Version.
- **Rationale:** Clients and integrations are untrusted and may calculate differently.
- **Primary actors:** Judges; Chief Judges; Result consumers.
- **Affected modules:** Scoring, Results, Access Control.
- **Priority:** Must.
- **Verification method:** Tampered-client tests, independent deterministic recomputation, and integration tests across supported clients.
- **Related business invariants:** INV-010, INV-011, INV-028.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

### QMDB-SCR-003 — Submitted Score Sheet preservation

- **Requirement statement:** QMDB shall preserve each submitted Score Sheet as an identifiable version and reject in-place modification outside the governed Score Reopening workflow.
- **Rationale:** A judge submission is official evidence.
- **Primary actors:** Judges; Chief Judges; Auditors.
- **Affected modules:** Scoring, Judging, Audit.
- **Priority:** Must.
- **Verification method:** Repository/API negative test for direct update and workflow test confirming predecessor/successor versions and Audit Events.
- **Related business invariants:** INV-008, INV-013.
- **Future implementation phase:** P0-B02 detailed specification; reopening authority unassigned.

### QMDB-SCR-004 — Ruleset Version integrity

- **Requirement statement:** QMDB shall bind every Performance to its approved immutable Ruleset Version, requiring a new approved version rather than an in-place change after locking.
- **Rationale:** Historical score calculation must remain reproducible.
- **Primary actors:** Competition-Rules Governance Body; Judges; Auditors.
- **Affected modules:** Scoring, Competitions, Judging, Results, Audit.
- **Priority:** Must.
- **Verification method:** Immutability tests, missing-version finalization test, checksum/version inspection, and reproducibility vectors.
- **Related business invariants:** INV-011, INV-012.
- **Future implementation phase:** P0-B02 detailed specification; examples await OD-010.

### QMDB-SCR-005 — Non-executable rules

- **Requirement statement:** QMDB shall accept competition scoring configuration only through an approved versioned declarative model that cannot execute administrator-supplied PHP, SQL, JavaScript, shell commands, or arbitrary scripts.
- **Rationale:** Executable configuration creates code-execution and integrity risks.
- **Primary actors:** Competition-Rules Governance Body; Competition Directors; Security Operators.
- **Affected modules:** Scoring, Competitions, Security Operations.
- **Priority:** Must.
- **Verification method:** Schema/grammar validation tests, forbidden-payload security tests, and architecture review of the evaluator.
- **Related business invariants:** INV-012, INV-029.
- **Future implementation phase:** P0-B02 detailed specification; declarative vocabulary awaits OD-010.

### QMDB-SCR-006 — Auditable Score Reopening

- **Requirement statement:** Every Score Reopening shall record the current version, reason, requester, authorizing actor/approvals, conflict status, evidence, Step-Up Authentication result where required, time, and resulting Score Sheet Version.
- **Rationale:** Reopening is a sensitive exception, not an edit switch.
- **Primary actors:** Judges; Chief Judges; authorized reviewers; Auditors.
- **Affected modules:** Scoring, Judging, Access Control, Identity, Audit.
- **Priority:** Must.
- **Verification method:** Workflow/authorization tests for approved, denied, conflicted, expired, stale-version, and missing-evidence cases.
- **Related business invariants:** INV-008, INV-013, INV-024.
- **Future implementation phase:** P0-B02 detailed specification; approval matrix unassigned.

### QMDB-SCR-007 — Versioned results and Appeals

- **Requirement statement:** QMDB shall keep Provisional Results, Final Results, Corrected Results, Appeals, Appeal Decisions, and supersession relationships as distinct versioned records so neither correction nor Appeal rewrites the original score submission or result.
- **Rationale:** Official record truth requires transparent state and remedy history.
- **Primary actors:** Competitors; Chief Judges; Appeal Reviewers; Certificate Officers; Auditors.
- **Affected modules:** Results, Appeals, Scoring, Records, Certificates, Audit.
- **Priority:** Must.
- **Verification method:** End-to-end finalization/appeal/correction tests and version/provenance/Audit Event inspection.
- **Related business invariants:** INV-014, INV-016, INV-026.
- **Future implementation phase:** P0-B02 detailed specification; appeal authority OD-021.

### QMDB-SCR-008 — Governed ranking outcomes

- **Requirement statement:** QMDB shall derive Aggregated Score, Ranking, Placement, tie-break outcome, and Disqualification treatment from identified declarative rules with provisional/final status and decision basis available to authorized reviewers.
- **Rationale:** Outcome interpretation must be deterministic and reviewable.
- **Primary actors:** Chief Judges; Appeal Reviewers; Competitors; public result consumers.
- **Affected modules:** Scoring, Results, Appeals, Reporting.
- **Priority:** Must.
- **Verification method:** Approved calculation-vector tests, tie/disqualification scenarios, explanation review, and status-label accessibility tests.
- **Related business invariants:** INV-009, INV-010, INV-011.
- **Future implementation phase:** P0-B02 detailed specification; rule examples await OD-010.

## Qur’an-reference requirements

### QMDB-QRF-001 — Versioned checksummed text releases

- **Requirement statement:** Every canonical Qur’an Text Release shall have an immutable release identifier, Reading context, source/provenance, checksum, qualified review evidence, activation state, and supersession relationship where applicable.
- **Rationale:** Text and reference consumers must identify and detect change.
- **Primary actors:** Qualified Qur’an Reviewers; Security Operators; Auditors.
- **Affected modules:** Qur’an Reference, Audit, Security Operations.
- **Priority:** Must.
- **Verification method:** Import/activation tests for missing/mismatched checksum or approval and integrity revalidation tests.
- **Related business invariants:** INV-018.
- **Future implementation phase:** P0-B02 detailed specification; release source/authority unassigned.

### QMDB-QRF-002 — Restricted canonical text changes

- **Requirement statement:** QMDB shall reserve canonical Qur’an text release activation or supersession for separately authorized qualified review, denying normal administrators any edit capability.
- **Rationale:** Administrative convenience cannot override textual integrity.
- **Primary actors:** Qualified Qur’an Reviewers; Organization Administrators; Security Operators.
- **Affected modules:** Qur’an Reference, Access Control, Audit.
- **Priority:** Must.
- **Verification method:** Role/permission negative tests, direct endpoint/repository tests, and audit/alert inspection.
- **Related business invariants:** INV-017, INV-018.
- **Future implementation phase:** P0-B02 detailed specification; governance authority unassigned.

### QMDB-QRF-003 — Canonical reference distinction

- **Requirement statement:** QMDB shall model Reading, Surah, Ayah, Juz, Hizb, Rubʿ, Page Reference, Passage Range, Tajwīd Rule Taxonomy, and Competition Mistake Taxonomy as distinct version-linked concepts without treating a page or competition taxonomy as canonical text identity.
- **Rationale:** Reference, presentation, religious taxonomy, and competition classification have different meanings.
- **Primary actors:** Qualified Qur’an Reviewers; Competition-Rules Governance Body; Judges.
- **Affected modules:** Qur’an Reference, Scoring, Scheduling, Judging.
- **Priority:** Must.
- **Verification method:** Domain/schema relationship review and passage/range validation tests against identified releases.
- **Related business invariants:** INV-011, INV-018.
- **Future implementation phase:** P0-B02 detailed specification; content governance unassigned.

## Media requirements

### QMDB-MED-001 — Media storage separation

- **Requirement statement:** QMDB shall store audio and video object bytes in private S3-compatible object storage while MySQL retains Media Asset metadata, ownership, Consent links, integrity hashes, lifecycle states, and object references rather than large media BLOBs.
- **Rationale:** Media scale and security require separate storage responsibilities.
- **Primary actors:** Reciters; Media Workers; Platform Operators.
- **Affected modules:** Media, Platform Operations, Privacy.
- **Priority:** Must.
- **Verification method:** Schema architecture scan, object-storage integration tests, and database payload-size/type review.
- **Related business invariants:** INV-019, INV-030.
- **Future implementation phase:** P0-B02 detailed specification; provider OD-003.

### QMDB-MED-002 — Private-by-default media

- **Requirement statement:** Every uploaded Media Asset shall remain quarantined or private until integrity, malware, purpose, ownership, policy, Consent, moderation, and publication checks applicable to its class have passed.
- **Rationale:** Upload does not imply safe or authorized publication.
- **Primary actors:** Reciters; Guardians; Media Moderators; Media Processing Workers.
- **Affected modules:** Media, Privacy, Guardianship, Moderation, Platform Operations.
- **Priority:** Must.
- **Verification method:** Storage-policy test, unauthorized URL/CDN test, scan-failure workflow, publication-gate tests, and Audit Event review.
- **Related business invariants:** INV-019, INV-020.
- **Future implementation phase:** P0-B02 detailed specification; retention/provider decisions open.

### QMDB-MED-003 — Minor media publication gate

- **Requirement statement:** QMDB shall block public delivery of Media Assets depicting or belonging to a Minor unless current approved Guardian/Consent, purpose, privacy, moderation, and child-safety policy decisions authorize that specific publication scope.
- **Rationale:** Minor media exposure requires conservative, purpose-specific control.
- **Primary actors:** Guardians; Minors; Media Moderators; Privacy Officers.
- **Affected modules:** Guardianship, Media, Recitation Clips, Privacy, Moderation.
- **Priority:** Must.
- **Verification method:** Child-safety end-to-end tests for absent, withdrawn, expired, changed-policy, mismatched-purpose, and approved Consent.
- **Related business invariants:** INV-020.
- **Future implementation phase:** P0-B02 detailed specification; OD-011/OD-012.

### QMDB-MED-004 — Evidence and derivative integrity

- **Requirement statement:** QMDB shall preserve an Evidence Master separately from each Media Derivative and record source/derivative hashes, processing profile, worker identity, time, outcome, and chain of custody without allowing derivative generation to mutate the master.
- **Rationale:** Evidence and public delivery have different integrity requirements.
- **Primary actors:** Judges; Media Processing Workers; Moderators; Auditors.
- **Affected modules:** Media, Judging, Audit, Platform Operations.
- **Priority:** Must.
- **Verification method:** Hash-before/after tests, immutable-source storage tests, failed-job tests, and provenance inspection.
- **Related business invariants:** INV-019, INV-029.
- **Future implementation phase:** P0-B02 detailed specification; processing topology unassigned.

## Recitation Clips and community requirements

### QMDB-SOC-001 — Moderated community lifecycle

- **Requirement statement:** QMDB shall subject Recitation Clips, Social Posts, Comments, Reactions, Follows, reports, Moderation Cases, Moderation Actions, and Content Appeals to versioned community, privacy, abuse-prevention, and moderation policy.
- **Rationale:** Structured safeguards are required before community publication and interaction.
- **Primary actors:** Reciters; Registered Individuals; Media Moderators; Community-Safety Officers.
- **Affected modules:** Recitation Clips, Moderation, Media, Privacy, Guardianship, Audit.
- **Priority:** Must.
- **Verification method:** Moderation lifecycle tests, abuse/rate tests, policy-version inspection, and content-appeal authorization tests.
- **Related business invariants:** INV-020, INV-021.
- **Future implementation phase:** P0-B02 detailed specification; moderation targets OD-020.

### QMDB-SOC-002 — Social/official-record isolation

- **Requirement statement:** QMDB shall prevent follows, reactions, bookmarks, comments, view counts, reports, moderation actions, feed ranking, and other social signals from altering Score Sheets, Results, Competition Records, eligibility, or certificate truth.
- **Rationale:** Engagement cannot influence official competition outcomes.
- **Primary actors:** Community users; Moderators; Judges; Auditors.
- **Affected modules:** Recitation Clips, Moderation, Scoring, Results, Records, Certificates.
- **Priority:** Must.
- **Verification method:** Architecture dependency tests, forbidden-write integration tests, and social-event replay tests.
- **Related business invariants:** INV-021.
- **Future implementation phase:** P0-B02 detailed specification; recommendation policy OD-025.

## Certificate and record requirements

### QMDB-CER-001 — Cryptographically verifiable representation

- **Requirement statement:** Each issued Certificate shall identify the represented record and version, use canonical signed content and key identification, and support public verification without exposing sensitive data or granting authorization.
- **Rationale:** Verifiers need authenticity and lifecycle status with minimal disclosure.
- **Primary actors:** Certificate Officers; External Verification Consumers; Security Operators.
- **Affected modules:** Certificates, Records, Results, Security Operations, Privacy.
- **Priority:** Must.
- **Verification method:** Signature test vectors, altered-content/key-rotation tests, QR/payload privacy review, and authorization negative tests.
- **Related business invariants:** INV-027.
- **Future implementation phase:** P0-B02 detailed specification; key custody OD-017.

### QMDB-CER-002 — Certificate lifecycle history

- **Requirement statement:** QMDB shall preserve Certificate issuance, reissue, revocation, supersession, signer/key identifier, reason, authority, time, and predecessor/successor history without hard deletion through normal workflows.
- **Rationale:** A verifier must distinguish current, revoked, and superseded artifacts.
- **Primary actors:** Certificate Officers; Auditors; External Verification Consumers.
- **Affected modules:** Certificates, Records, Audit.
- **Priority:** Must.
- **Verification method:** Lifecycle integration tests and public verification tests for current, revoked, superseded, altered, and unknown artifacts.
- **Related business invariants:** INV-015, INV-026.
- **Future implementation phase:** P0-B02 detailed specification; retention OD-014.

### QMDB-CER-003 — Official-record historical preservation

- **Requirement statement:** QMDB shall prevent normal hard deletion of finalized Competition Records, Result versions, Score Sheet versions, Appeal records, Certificate records, and their required provenance while routing qualified disposition needs through governed privacy/records policy.
- **Rationale:** Official history and dependent verification must remain interpretable.
- **Primary actors:** Record Stewards; Privacy Officers; Auditors; Platform Operators.
- **Affected modules:** Records, Results, Scoring, Appeals, Certificates, Privacy, Audit.
- **Priority:** Must.
- **Verification method:** Route/use-case inventory, database-privilege review, hard-delete negative tests, and governed disposition workflow tests.
- **Related business invariants:** INV-008, INV-014, INV-015, INV-016, INV-026.
- **Future implementation phase:** P0-B02 detailed specification; retention OD-014.

### QMDB-CER-004 — Provenance-classified records

- **Requirement statement:** Every published historical or native Competition Record shall identify its Verification Classification and the subject, authority, method, date, scope, status, and evidence/provenance supporting that classification.
- **Rationale:** An unexplained verification label cannot communicate record trust.
- **Primary actors:** Record Stewards; authorized Organizations; External Verification Consumers; Auditors.
- **Affected modules:** Records, Organizations, Audit, Search, Privacy.
- **Priority:** Must.
- **Verification method:** Record-classification schema/contract review and scenario tests for Native Verified, Organizer Verified, Legacy Supported, Legacy Unverified, and Disputed records.
- **Related business invariants:** INV-014, INV-027.
- **Future implementation phase:** P0-B02 detailed specification; legacy authority OD-019.

## Security requirements

### QMDB-SEC-001 — Deny-by-default authorization

- **Requirement statement:** Every protected action and record access shall be denied unless current RBAC Permission, ABAC attributes, explicit resource policy, Workspace context, and applicable administrative or competition scope authorize it.
- **Rationale:** Authentication and role names alone are insufficient.
- **Primary actors:** All authenticated actors and service principals.
- **Affected modules:** Access Control, Identity, Workspaces, all protected modules.
- **Priority:** Must.
- **Verification method:** Policy unit tests and cross-role, cross-resource, cross-Workspace, stale-assignment, and unauthenticated negative tests.
- **Related business invariants:** INV-001, INV-002, INV-004, INV-027.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

### QMDB-SEC-002 — Strong privileged authentication and secret isolation

- **Requirement statement:** QMDB shall apply stronger authentication and secret-isolation controls to privileged activity, keeping passwords, private keys, provider secrets, and tokens out of normal application records, client output, error messages, and logs.
- **Rationale:** Privileged takeover and secret leakage have high systemic impact.
- **Primary actors:** All privileged actors; Security and Platform Operators.
- **Affected modules:** Identity, Security Operations, Platform Operations, Integrations, Audit.
- **Priority:** Must.
- **Verification method:** MFA/Step-Up workflow tests, secret scanner, log/output redaction tests, and secrets-access review.
- **Related business invariants:** INV-024, INV-029.
- **Future implementation phase:** P0-B02 detailed specification; authentication standard unassigned.

### QMDB-SEC-003 — Controlled Break-Glass Access

- **Requirement statement:** Every Break-Glass Access activation shall require a declared incident, specific justification, strong fresh authentication, minimum resource scope, short expiry, independent approval where practicable, alerting, complete Audit Events, and retrospective review.
- **Rationale:** Emergency capability must not become standing administration.
- **Primary actors:** Break-Glass Administrators; Security Operators; Internal Auditors.
- **Affected modules:** Security Operations, Identity, Access Control, Audit, Platform Operations.
- **Priority:** Must.
- **Verification method:** End-to-end activation/expiry/review tests and denied missing-prerequisite/self-extension scenarios.
- **Related business invariants:** INV-024.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

### QMDB-SEC-004 — Minimum support access

- **Requirement statement:** Every Support Officer access to non-public data shall be bound to a named case, purpose, approved field set, Workspace/resource scope, time limit, and Audit Events, with no impersonation or secret visibility.
- **Rationale:** Support must resolve issues without broad surveillance capability.
- **Primary actors:** Support Officers; Privacy Officers; Security Operators.
- **Affected modules:** Access Control, Identity, Privacy, Audit, support-facing use cases.
- **Priority:** Must.
- **Verification method:** Field-level authorization tests, expiry tests, unrelated-case negative tests, and support Audit Event review.
- **Related business invariants:** INV-025, INV-029.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

### QMDB-SEC-005 — No direct external score writes

- **Requirement statement:** QMDB shall expose no database privilege, API operation, event consumer, import, or integration path that permits an external provider or Authorized Integration Client to directly create or change authoritative Score Items, Judge Totals, Aggregated Scores, or Results.
- **Rationale:** Official score authority stays inside governed human and server workflows.
- **Primary actors:** Authorized Integration Clients; Security Operators; Judges.
- **Affected modules:** Integrations, Access Control, Scoring, Results, Audit.
- **Priority:** Must.
- **Verification method:** API/contract inventory, service-principal/database-grant review, penetration tests, and prohibited event-payload tests.
- **Related business invariants:** INV-028.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

## Privacy and child-safety requirements

### QMDB-PRI-001 — Purpose-bound data and Consent

- **Requirement statement:** QMDB shall collect, use, disclose, retain, and publish personal data only under a recorded purpose, data classification, minimum field set, applicable policy version, authority, and versioned Consent or other approved basis.
- **Rationale:** Privacy requires explainable decisions across the lifecycle.
- **Primary actors:** Registered Individuals; Guardians; Privacy Officers; module owners.
- **Affected modules:** Privacy, Guardianship, People, Media, Records, all personal-data modules.
- **Priority:** Must.
- **Verification method:** Data-flow/field inventory, policy decision tests, Consent version/withdrawal tests, and privacy audit.
- **Related business invariants:** INV-019, INV-020, INV-025.
- **Future implementation phase:** P0-B02 detailed specification; retention/legal interpretations open.

### QMDB-PRI-002 — Conservative Minor defaults

- **Requirement statement:** QMDB shall keep a Minor’s profile, contact information, precise location, identity documents, recordings, interactions, and search discoverability non-public by default and disclose only an approved minimum under current Guardian, Consent, purpose, moderation, and child-safety policy.
- **Rationale:** Default exposure creates disproportionate and potentially lasting harm.
- **Primary actors:** Minors; Guardians; Privacy Officers; Community-Safety Officers.
- **Affected modules:** People, Guardianship, Privacy, Media, Recitation Clips, Search, Moderation.
- **Priority:** Must.
- **Verification method:** Default-state and field-level public API/UI tests across absent/uncertain/changed Minor and Consent states.
- **Related business invariants:** INV-019, INV-020, INV-027.
- **Future implementation phase:** P0-B02 detailed specification; OD-011/OD-012/OD-016.

### QMDB-PRI-003 — Governed retention and disposition

- **Requirement statement:** QMDB shall prevent automated production retention, deletion, anonymization, archival, residency, or cross-border rules from taking effect until the qualified owner approves record-class policy, legal holds, provenance effects, security/privacy impact, and verification evidence.
- **Rationale:** Unapproved retention can either overexpose data or erase required history.
- **Primary actors:** Privacy Officers; Record Stewards; Platform Operators; qualified Nigerian Privacy and Compliance Reviewers.
- **Affected modules:** Privacy, Records, Media, Audit, Platform Operations, Integrations.
- **Priority:** Must.
- **Verification method:** Configuration/release gate review and negative tests for missing policy version or legal-hold check.
- **Related business invariants:** INV-015, INV-026.
- **Future implementation phase:** P0-B02 detailed specification; OD-013–OD-015/OD-018.

## Accessibility requirements

### QMDB-ACC-001 — WCAG 2.2 Level AA foundation

- **Requirement statement:** All critical and public QMDB workflows shall target WCAG 2.2 Level AA with keyboard operation, screen-reader semantics, visible focus, high-contrast support, reduced-motion support, usable touch targets, and status not communicated by color alone.
- **Rationale:** Access to competition and public records must not depend on one device or sense.
- **Primary actors:** All human actors.
- **Affected modules:** Presentation across all modules.
- **Priority:** Must.
- **Verification method:** Automated accessibility scan plus manual keyboard, screen-reader, contrast, zoom, reduced-motion, touch-target, and non-color status review.
- **Related business invariants:** None; cross-cutting quality obligation.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

### QMDB-ACC-002 — Arabic and bidirectional interfaces

- **Requirement statement:** QMDB shall support readable Arabic script and full component, navigation, form, table, dialog, icon-direction, focus-order, and mixed-content behavior in both LTR and RTL layouts.
- **Rationale:** Translation without layout and typography support is insufficient.
- **Primary actors:** Arabic-speaking users; Judges; public audiences.
- **Affected modules:** Presentation, Qur’an Reference, Notifications, Reporting, Certificates.
- **Priority:** Must.
- **Verification method:** Visual/regression and assistive-technology tests in English/Arabic, LTR/RTL, narrow/wide layouts, and mixed numeric/reference content.
- **Related business invariants:** INV-018.
- **Future implementation phase:** P0-B02 detailed specification; additional languages OD-008.

### QMDB-ACC-003 — Mobile and low-bandwidth critical workflows

- **Requirement statement:** QMDB shall make authentication, Registration, Consent, Check-In, judging, live status, result verification, reporting of unsafe content, and recovery errors usable on mobile and constrained networks with explicit pending, retry, stale, synchronized, provisional, and final states and no dark patterns.
- **Rationale:** Critical event and public use cannot assume desktop or stable broadband.
- **Primary actors:** Registered Individuals; Guardians; Competitors; Judges; Organizers; Public Visitors.
- **Affected modules:** Presentation, Identity, Registration, Guardianship, Judging, Results, Moderation, Platform Operations.
- **Priority:** Must.
- **Verification method:** Responsive/device tests, network throttling/interruption tests, duplicate-submit tests, state-label review, and usability recovery scenarios.
- **Related business invariants:** INV-010, INV-022, INV-023.
- **Future implementation phase:** P0-B02 detailed specification; offline behavior to be detailed.

## Operations and resilience requirements

### QMDB-OPS-001 — Transactional event publication

- **Requirement statement:** Every business event required for live delivery, notification, projection, or asynchronous processing shall be persisted in a Transactional Outbox with its authoritative state change and consumed idempotently through Redis Streams using event identity, tenant context, correlation, retry, and completion evidence.
- **Rationale:** State and event publication must not diverge under failure or replay.
- **Primary actors:** Background Workers; Platform Operators; module owners.
- **Affected modules:** Platform Operations, Audit, Integrations, Notifications, and all event-publishing modules.
- **Priority:** Must.
- **Verification method:** Transaction rollback, publisher crash, duplicate delivery, out-of-order, retry, dead-letter, and consumer concurrency tests.
- **Related business invariants:** INV-023.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

### QMDB-OPS-002 — Critical workload isolation

- **Requirement statement:** QMDB shall reserve and protect capacity for Judge authentication, Score Sheet submission, official calculation, Result finalization, certificate verification, and competition operations by isolating and throttling social feeds, recommendations, analytics, and media processing through separate priorities, queues, pools, quotas, back-pressure, or circuit breakers as applicable.
- **Rationale:** Engagement spikes must not compromise live competition integrity.
- **Primary actors:** Judges; Organizers; Platform Operators; Public Verification Consumers.
- **Affected modules:** Platform Operations, Identity, Scoring, Results, Certificates, Media, Recitation Clips, Reporting.
- **Priority:** Must.
- **Verification method:** Workload-isolation architecture review, saturation/load tests, queue/back-pressure tests, and service telemetry inspection.
- **Related business invariants:** INV-030.
- **Future implementation phase:** P0-B02 detailed specification; SLO OD-024.

### QMDB-OPS-003 — Recoverable authoritative records

- **Requirement statement:** QMDB shall maintain monitored backups, point-in-time recovery capability, integrity checks, documented restore procedures, environment isolation, and recurring restore tests for authoritative records and required object metadata/evidence according to approved recovery objectives.
- **Rationale:** Trust requires demonstrated recovery, not backup creation alone.
- **Primary actors:** Platform Operators; Security Operators; Internal Auditors.
- **Affected modules:** Platform Operations, Audit, Media, MySQL-backed modules.
- **Priority:** Must.
- **Verification method:** Restore exercise with reconciliation, integrity/hash checks, access review, and recorded achieved recovery metrics.
- **Related business invariants:** INV-018, INV-019, INV-026.
- **Future implementation phase:** P0-B02 detailed specification; numerical objectives OD-023.

### QMDB-OPS-004 — Observable controlled operations

- **Requirement statement:** QMDB shall monitor application, database, Redis, outbox, worker, object-storage, media-processing, integration, security, capacity, and backup states with actionable alerts, Correlation IDs, minimized telemetry, and controlled operator response.
- **Rationale:** Failures and tampering indicators must be detected and investigated promptly.
- **Primary actors:** Platform Operators; Security Operators; Internal Auditors.
- **Affected modules:** Platform Operations, Security Operations, Audit, Integrations, Media.
- **Priority:** Must.
- **Verification method:** Alert-injection tests, correlation tracing, redaction tests, runbook exercise, and monitoring-coverage review.
- **Related business invariants:** INV-023, INV-029, INV-030.
- **Future implementation phase:** P0-B02 detailed specification; provider/SLO decisions open.

## Audit and provenance requirements

### QMDB-AUD-001 — Significant-action Audit Events

- **Requirement statement:** QMDB shall record append-oriented Audit Events for authentication, authorization-sensitive access, assignments, conflicts, Consent, evidence access, score submissions/reopening, results/finalization/correction, Appeals, certificates, moderation, privacy, integrations, operational changes, and Break-Glass actions with actor/service, action, subject, scope, time, correlation, outcome, reason, and version as applicable.
- **Rationale:** Investigation and accountability require consistent evidence.
- **Primary actors:** Internal Auditors; Security/Privacy Officers; all privileged actors.
- **Affected modules:** Audit and all event-producing modules.
- **Priority:** Must.
- **Verification method:** Audit-coverage matrix, use-case integration tests, required-field validation, and secret/sensitive-data redaction tests.
- **Related business invariants:** INV-008, INV-013, INV-014, INV-016, INV-024, INV-029.
- **Future implementation phase:** P0-B02 detailed specification; retention OD-014.

### QMDB-AUD-002 — Tamper-evident audit integrity

- **Requirement statement:** QMDB shall protect Audit Events with restricted append access, tamper-evident Audit Chains and independently protected Audit Checkpoints, validation alerts, tested recovery, and governed retention without claiming absolute immutability.
- **Rationale:** Audit evidence must reveal unauthorized alteration and remain recoverable.
- **Primary actors:** Internal Auditors; Security Operators; Platform Operators.
- **Affected modules:** Audit, Security Operations, Platform Operations.
- **Priority:** Must.
- **Verification method:** Chain/checkpoint validation tests, altered/missing-event detection, privilege review, and restore/reconciliation exercise.
- **Related business invariants:** INV-018, INV-026, INV-029.
- **Future implementation phase:** P0-B02 detailed specification; key custody/retention open.

### QMDB-AUD-003 — End-to-end provenance and correlation

- **Requirement statement:** QMDB shall carry non-secret Correlation IDs, Record Versions, Content Hashes, actor/service identity, Workspace context, and provenance references through synchronous and asynchronous workflows so authorized reviewers can trace each public representation or business outcome to its authoritative inputs.
- **Rationale:** Cross-module and asynchronous operations otherwise lose causality.
- **Primary actors:** Auditors; Support Officers; Platform/Security Operators; Record Stewards.
- **Affected modules:** Audit, Records, Platform Operations, Integrations, and all authoritative/event modules.
- **Priority:** Must.
- **Verification method:** End-to-end trace tests from request through outbox/worker/projection, orphan-correlation checks, and public-to-source provenance review.
- **Related business invariants:** INV-011, INV-014, INV-018, INV-023.
- **Future implementation phase:** P0-B02 detailed specification; implementation phase unassigned.

## Requirement governance

Changes to an obligation require impact review against the [Product Constitution](../project/product-constitution.md), [Locked Decision Register](../project/decision-register.md), [Business Invariants](../domain/core-modules-and-business-invariants.md#business-invariants), actor/capability boundaries, privacy/security risks, and the [Traceability Matrix](P0-B01-traceability-matrix.md). A requirement cannot be marked implemented or verified without the stated evidence.

## Related documents

- [Documentation index](../README.md)
- [Product constitution](../project/product-constitution.md)
- [System boundaries](../project/system-boundaries.md)
- [Domain glossary](../domain/domain-glossary.md)
- [Stakeholders and actors](../domain/stakeholders-and-actors.md)
- [Platform sides and capabilities](../domain/platform-sides-and-capabilities.md)
- [Core modules and business invariants](../domain/core-modules-and-business-invariants.md)
- [Traceability matrix](P0-B01-traceability-matrix.md)
