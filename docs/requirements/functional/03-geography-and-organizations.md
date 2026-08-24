# Geography and Organizations Functional Requirements

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B02 |
| Document Title | Geography and Organizations Functional Requirements |
| Document Version | 1.0.0 |
| Status | Baselined; implementation not started |
| Document Owner Role | Geography and Organization Domain Governance |
| Last Updated | 2026-08-24 |
| Related Documents | [Functional index](../P0-B02-functional-requirements-index.md); [glossary](../../domain/domain-glossary.md); [open decisions](../../project/open-decisions.md) |

## Purpose

Specify extensible Nigerian Administrative Area behavior and Organization identity, relationships, recognition, Membership, branding, suspension, change, and historical preservation.

## Scope

No official code dataset or recognition authority is invented. Official-source and Organization verification authority remain governed by OD-009/OD-022; branding cannot override accessibility.

## Functional requirements

### QMDB-FR-GEO-001 — Maintain the Administrative Area hierarchy

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-GEO-001 | Title | Maintain the Administrative Area hierarchy |
| Requirement Statement | QMDB shall represent Nigeria, State, Federal Capital Territory, Local Government Area, Area Council, Ward, and Community as typed parent-child Administrative Areas with historical validity. | Rationale | State/LGA-only fields cannot represent FCT or change. |
| Priority | High | Planned Implementation Phase | P3 — Nigerian Geography, Organizations, and People |
| Primary Actors | Geography Data Steward | Supporting Actors | Qualified administrative-data reviewer; Auditor |
| Owning Module | Geography | Related Modules | Organizations, Registration, Reporting, Access Control |
| Preconditions | Approved source/provenance and authorized geography steward. | Trigger | Area import/create/change review. |
| Inputs | Type, parent, names, validity dates, provenance, source code when available. | Validation Rules | Permitted parent/type; no cycles; temporal consistency; no fabricated code; unique source identity within validity. |
| Authorization and Scope | Separately scoped reference-data permission and Step-Up for activation. | Normal Functional Behavior | Create immutable version, validate hierarchy, activate after review. |
| Alternative Behavior | Hold source conflict pending review; support future Country hierarchies without changing Nigeria history. | Failure Behavior | Reject cycle, invalid parent/type/date, unsupported source, or duplicate current identity. |
| Records Read | Current/historical areas and source metadata. | Records Created | Administrative Area/version and review. |
| Records Updated | Current-version pointer/status. | Records Versioned or Superseded | Every name/type/parent/validity change. |
| Audit Requirements | Source, steward/reviewer, change set, validation, activation. | Domain Events | AdministrativeAreaVersionPublished. |
| Notifications | Geography governance on conflicts/material changes. | Privacy and Data Classification | Public reference data; review provenance internal. |
| Accessibility and Interaction Requirements | Hierarchy works without map; readable textual paths in LTR/RTL. | Postconditions | Acyclic versioned hierarchy is queryable for current/historical dates. |
| Related Business Invariants | INV-006 | Related P0-B01 Requirements | QMDB-GEO-001 |
| Verification Method | Unit test; database constraint test; manual domain review. | Acceptance Criteria | FCT→Area Council and State→LGA paths coexist without conflation. |

### QMDB-FR-GEO-002 — Govern names, codes, validity, deactivation, and replacement

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-GEO-002 | Title | Govern names, codes, validity, deactivation, and replacement |
| Requirement Statement | QMDB shall version Administrative Area official/alternative names, sourced codes, effective dates, deactivation, and replacement relationships without rewriting historical references. | Rationale | Administrative naming and boundaries change. |
| Priority | High | Planned Implementation Phase | P3 — Nigerian Geography, Organizations, and People |
| Primary Actors | Geography Data Steward | Supporting Actors | Reporting users; Auditor |
| Owning Module | Geography | Related Modules | Organizations, Records, Reporting, Search |
| Preconditions | Existing area/version or approved new source. | Trigger | Rename, boundary/status change, replacement. |
| Inputs | Names/codes, source, effective interval, predecessor/successor, reason. | Validation Rules | Source-specific code; non-overlapping current versions; replacement validity; historical dependencies. |
| Authorization and Scope | Geography governance only; Organization administrators cannot edit global areas. | Normal Functional Behavior | Append version/replacement, retain former version, update current search projection. |
| Alternative Behavior | Store alias without replacing official name; mark inactive without successor. | Failure Behavior | Reject destructive delete, unsupported code, temporal overlap, or history orphaning. |
| Records Read | Area versions/dependencies. | Records Created | New version/replacement relationship. |
| Records Updated | Current status pointer/search projection. | Records Versioned or Superseded | Area names/codes/validity/status. |
| Audit Requirements | Source, reason, actor/reviewer, effective dates, impacted projections. | Domain Events | AdministrativeAreaRenamed; AdministrativeAreaDeactivated; AdministrativeAreaReplaced. |
| Notifications | Affected data stewards/administrators for material changes. | Privacy and Data Classification | Public reference; change evidence internal. |
| Accessibility and Interaction Requirements | Show former/current names and validity textually. | Postconditions | Historical records still resolve the version valid when created. |
| Related Business Invariants | INV-006, INV-007 | Related P0-B01 Requirements | QMDB-GEO-001, QMDB-AUD-003 |
| Verification Method | Integration test; database constraint test; document inspection. | Acceptance Criteria | Deactivating an LGA never changes its name in an older Participant Snapshot. |

### QMDB-FR-GEO-003 — Search, report, and authorize by geography

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-GEO-003 | Title | Search, report, and authorize by geography |
| Requirement Statement | QMDB shall resolve geography search, filters, reports, and Administrative Scope against the applicable typed area version and date while keeping Host, Eligibility, Representation, Coverage, and authority purposes separate. | Rationale | Geographic match does not establish every relationship or authority. |
| Priority | High | Planned Implementation Phase | P3 — Nigerian Geography, Organizations, and People |
| Primary Actors | Coordinator; Registrar; Public Visitor | Supporting Actors | Reporting Worker |
| Owning Module | Geography | Related Modules | Access Control, Search, Reporting, Competitions, Organizations |
| Preconditions | Active or historically relevant Administrative Area versions. | Trigger | Search/filter/report/policy evaluation. |
| Inputs | Query, area/type/date, purpose, authorized scope. | Validation Rules | Normalize aliases; retain type/path; scope intersection; historical-date resolution; privacy filters. |
| Authorization and Scope | Public search sees public areas; administrative detail requires explicit scope; area title alone grants no authority. | Normal Functional Behavior | Resolve candidate/version/path and return purpose-specific results. |
| Alternative Behavior | Inactive area remains selectable for historical reporting with status label. | Failure Behavior | Deny out-of-scope query/export and avoid leaking restricted tenant detail. |
| Records Read | Area hierarchy/versions, scopes, purpose-specific relationships. | Records Created | None except export/query audit where sensitive. |
| Records Updated | Read Model/query telemetry only. | Records Versioned or Superseded | Search projection versions. |
| Audit Requirements | Sensitive/bulk geographic query, scope and export. | Domain Events | GeographyProjectionUpdated. |
| Notifications | None normally; alert repeated cross-scope attempts. | Privacy and Data Classification | Public areas; scoped reports may include sensitive aggregates. |
| Accessibility and Interaction Requirements | Hierarchical text alternatives, keyboard filters, no map-only selection. | Postconditions | Result identifies exact area type/version and purpose. |
| Related Business Invariants | INV-006, INV-027 | Related P0-B01 Requirements | QMDB-GEO-002, QMDB-SEC-001 |
| Verification Method | Authorization test; integration test; accessibility test. | Acceptance Criteria | Host Location cannot be reused automatically as Eligibility Geography or Administrative Scope. |

### QMDB-FR-ORG-001 — Create Organization, Units, Workspace, and coverage

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-ORG-001 | Title | Create Organization, Units, Workspace, and coverage |
| Requirement Statement | QMDB shall create an Organization with type, owning Workspace, optional Organization Units, and separately evidenced Organization Coverage Areas through a scoped onboarding workflow. | Rationale | Organization identity, tenancy, subdivision, and reach are distinct. |
| Priority | High | Planned Implementation Phase | P3 — Nigerian Geography, Organizations, and People |
| Primary Actors | Organization Administrator | Supporting Actors | Platform Operator; Organization Governance |
| Owning Module | Organizations | Related Modules | Workspaces, Geography, People, Access Control, Audit |
| Preconditions | Active Workspace/onboarding authority and duplicate search. | Trigger | Organization onboarding or Unit/coverage request. |
| Inputs | Names/type, Workspace, Units, coverage claims, source/provenance, branding request. | Validation Rules | Workspace ownership; parent cycles; area validity; duplicate/name claims; branding/accessibility policy. |
| Authorization and Scope | Creator receives no implicit verification, global Role, or authority beyond approved Membership. | Normal Functional Behavior | Create Organization/Unit/coverage records in pending/unverified state and route review if needed. |
| Alternative Behavior | Link to existing Organization or hold possible duplicate. | Failure Behavior | Reject cross-Workspace parent/Unit, invalid coverage, or inaccessible branding. |
| Records Read | Workspace, organizations, areas, membership/policy. | Records Created | Organization, Unit, coverage relationship, onboarding review. |
| Records Updated | Workspace organization index. | Records Versioned or Superseded | Names/type/coverage/branding and Unit structure. |
| Audit Requirements | Creator, Workspace, source, duplicate outcome, coverage/Unit changes. | Domain Events | OrganizationCreated; OrganizationUnitCreated; CoverageAreaChanged. |
| Notifications | Organization applicant/administrators and review authority. | Privacy and Data Classification | Organization public fields separate from member/evidence data. |
| Accessibility and Interaction Requirements | Branding preview cannot reduce contrast, semantics, focus, or direction support. | Postconditions | Organization has one Workspace ownership boundary and explicit status. |
| Related Business Invariants | INV-001, INV-002, INV-006 | Related P0-B01 Requirements | QMDB-ORG-001, QMDB-ORG-002, QMDB-ORG-003 |
| Verification Method | Database constraint test; authorization test; accessibility test. | Acceptance Criteria | A Unit cannot reference an Organization in another Workspace. |

### QMDB-FR-ORG-002 — Record distinct Organization relationships

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-ORG-002 | Title | Record distinct Organization relationships |
| Requirement Statement | QMDB shall record Organizer, Sponsor, Host, Venue Provider, Nomination Authority, Judge Provider, historical-record verifier, school affiliation, and mosque affiliation as independent scoped and time-bounded relationships. | Rationale | One relationship does not imply another. |
| Priority | Critical | Planned Implementation Phase | P3 — Nigerian Geography, Organizations, and People |
| Primary Actors | Organization Administrator; Competition Director | Supporting Actors | Registrar; Records Steward |
| Owning Module | Organizations | Related Modules | Competitions, Registration, Judging, Records, Audit |
| Preconditions | Existing Organizations and authorized relationship workflow. | Trigger | Relationship proposal/acceptance/change/end. |
| Inputs | Relationship type, parties, Competition/Person scope, time, evidence, authority/status. | Validation Rules | Type-specific cardinality; Workspace policy; authority; conflicts; dates; provenance. |
| Authorization and Scope | Each relationship grants only policy-mapped capabilities; title/public status grants none. | Normal Functional Behavior | Create/version relationship and make it available to owning modules. |
| Alternative Behavior | Pending/disputed/ended status remains visible to authorized reviewers. | Failure Behavior | Reject inferred/bundled, cross-scope, stale, or unsupported relationship. |
| Records Read | Organizations, Workspaces, Competition/Person context, existing relations. | Records Created | Organization relationship/provenance. |
| Records Updated | Current relationship status. | Records Versioned or Superseded | Relationship, scope, evidence, time/status. |
| Audit Requirements | Proposer/acceptor, relation/scope, evidence, conflicts, status changes. | Domain Events | OrganizationRelationshipChanged. |
| Notifications | Affected organizations and owning workflow. | Privacy and Data Classification | Public relation summary by policy; evidence restricted. |
| Accessibility and Interaction Requirements | Use explicit relation labels; never one generic organizer badge. | Postconditions | Relationship facts and authority remain separately queryable. |
| Related Business Invariants | INV-004, INV-006 | Related P0-B01 Requirements | QMDB-ORG-003, QMDB-CMP-002 |
| Verification Method | Domain test; authorization test; integration test. | Acceptance Criteria | Sponsor status cannot nominate, host, or edit an Edition unless separately authorized. |

### QMDB-FR-ORG-003 — Review Organization verification

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-ORG-003 | Title | Review Organization verification |
| Requirement Statement | QMDB shall process Organization verification through application, evidence submission, scoped independent review, approval/rejection, suspension, revocation, expiry or re-review states with explicit subject, authority, method, date, scope, status, and provenance. | Rationale | An unexplained verification flag is unsafe. |
| Priority | Critical | Planned Implementation Phase | P3 — Nigerian Geography, Organizations, and People |
| Primary Actors | Organization Administrator; Organization Verification Reviewer | Supporting Actors | Auditor; Privacy Officer |
| Owning Module | Organizations | Related Modules | Access Control, Records, Audit, Notifications |
| Preconditions | Existing Organization and authority policy OD-009/OD-034. | Trigger | Application, review, expiry, material change, suspension/revocation. |
| Inputs | Claim scope, evidence, method, reviewer decision, reason, effective/expiry dates. | Validation Rules | Evidence completeness/classification; reviewer scope/conflict; non-self approval; Step-Up; current version. |
| Authorization and Scope | Applicant cannot approve; verification applies only to recorded scope and never grants score authority. | Normal Functional Behavior | Version application/review, set classification/status, preserve evidence/history, schedule re-review. |
| Alternative Behavior | Request evidence or approve narrower scope; emergency suspend pending review. | Failure Behavior | Reject incomplete, conflicted, unauthorized, expired, or stale decision without deleting history. |
| Records Read | Organization versions/relations/evidence/reviewer authority. | Records Created | Verification application/review/decision. |
| Records Updated | Current verification status and permitted projections. | Records Versioned or Superseded | Every application/evidence/decision/status. |
| Audit Requirements | Applicant/reviewer, scope/method/evidence, step-up, reason, before/after, expiry. | Domain Events | OrganizationVerificationRequested; OrganizationVerificationDecided; OrganizationVerificationSuspended. |
| Notifications | Applicant, relevant administrators, audit/security for revocation. | Privacy and Data Classification | Evidence restricted; public projection exposes only approved classification/scope/status. |
| Accessibility and Interaction Requirements | Explain exact subject/scope/status and evidence corrections accessibly. | Postconditions | Current status is classified and historically traceable. |
| Related Business Invariants | INV-004, INV-026 | Related P0-B01 Requirements | QMDB-CER-004, QMDB-ACT-003, QMDB-AUD-001 |
| Verification Method | Authorization test; workflow integration test; manual domain/privacy review. | Acceptance Criteria | Applicant cannot self-approve and revoked status does not erase earlier historical authority. |

### QMDB-FR-ORG-004 — Govern Membership, delegation, change, suspension, closure, and merge

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-ORG-004 | Title | Govern Membership, delegation, change, suspension, closure, and merge |
| Requirement Statement | QMDB shall version Organization Membership invitations/removals, administrator delegation, name/coverage/parent changes, suspension, closure, and merge while revoking invalid authority immediately and preserving historical organization references. | Rationale | Current organization state must not rewrite past records or leave stale access. |
| Priority | Critical | Planned Implementation Phase | P3 — Nigerian Geography, Organizations, and People |
| Primary Actors | Organization Administrator; Organization Governance | Supporting Actors | Security Operator; Data Steward; Auditor |
| Owning Module | Organizations | Related Modules | Access Control, Identity, Competitions, Records, Audit |
| Preconditions | Existing Organization/current version and scoped authority. | Trigger | Membership/delegation or Organization lifecycle/change action. |
| Inputs | Subject/action, scope/time, change values, reason/evidence, merge target, approvals. | Validation Rules | Active Membership; delegation limits; last-admin safety; historical dependencies; duplicate/merge review; current version. |
| Authorization and Scope | No self-expansion; suspension/merge/closure and sensitive delegation require Step-Up and approval under OD-034. | Normal Functional Behavior | Append version, invalidate affected authority/Sessions, preserve old names/relations/records, notify. |
| Alternative Behavior | Suspend instead of close; restrict verified capability while active competition continuity is reviewed. | Failure Behavior | Reject orphaning, cross-Workspace merge, conflicted/self-approved, stale, or destructive history change. |
| Records Read | Organization/Member/grant/history, active competitions, records/holds. | Records Created | Change/merge/lifecycle decision and mapping. |
| Records Updated | Current Organization/Membership/grant state. | Records Versioned or Superseded | All changed Organization/Membership/relationship fields. |
| Audit Requirements | Initiator/approver, reason/evidence, before/after, affected authority/records, outcome. | Domain Events | MembershipChanged; OrganizationRenamed; OrganizationSuspended; OrganizationMerged; OrganizationClosed. |
| Notifications | Members, affected competition/record owners, security/operations where material. | Privacy and Data Classification | Membership restricted; historical organization facts preserved. |
| Accessibility and Interaction Requirements | Impact preview, clear historical/current labels, safe bulk-member confirmation. | Postconditions | Current access matches state; historical records retain the Organization version valid when created. |
| Related Business Invariants | INV-004, INV-007, INV-026 | Related P0-B01 Requirements | QMDB-ACT-002, QMDB-ORG-003, QMDB-CER-003 |
| Verification Method | Authorization test; integration test; concurrency test. | Acceptance Criteria | Removing an administrator invalidates authority without changing historical organizer facts. |

## Related documents

- [Functional requirements index](../P0-B02-functional-requirements-index.md)
- [Permission and capability matrix](../P0-B02-permission-capability-matrix.md)
- [Workflows and state machines](../P0-B02-workflows-and-state-machines.md)
- [P0-B01 baseline requirements](../P0-B01-requirements.md)

