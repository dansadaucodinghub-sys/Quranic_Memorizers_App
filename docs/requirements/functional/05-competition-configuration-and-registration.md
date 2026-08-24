# Competition Configuration and Registration Functional Requirements

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B02 |
| Document Title | Competition Configuration and Registration Functional Requirements |
| Document Version | 1.0.0 |
| Status | Baselined; implementation not started |
| Document Owner Role | Competition Product and Rules Governance |
| Last Updated | 2026-08-24 |
| Related Documents | [Functional index](../P0-B02-functional-requirements-index.md); [glossary](../../domain/domain-glossary.md); [invariants](../../domain/core-modules-and-business-invariants.md); [open decisions](../../project/open-decisions.md) |

## Purpose

Specify Competition Series/Edition structure, independent competition dimensions, lifecycle, declarative Ruleset Versions, Registration/Nomination/Eligibility, Consent, Participant Snapshots, exceptions, and cancellation effects.

## Scope

No scoring formula, judge quorum, tie-break, appeal authority, or government/religious authority is invented. Those specifics depend on OD-010, OD-021, OD-022, OD-034, and OD-035 where applicable.

## Functional requirements

### QMDB-FR-CMP-001 — Create Series and Edition with separate dimensions

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-CMP-001 | Title | Create Series and Edition with separate dimensions |
| Requirement Statement | QMDB shall create each Competition Edition under exactly one Competition Series with separate Participation Scope, Organizer Type/Organization, Sponsor Organization, Host Organization/Location, Eligibility Geography, and Represented Geography definitions. | Rationale | Recurrence, reach, authority, funding, hosting, eligibility, and representation are different facts. |
| Priority | Critical | Planned Implementation Phase | P5 — Competition Configuration and Registration |
| Primary Actors | Competition Director | Supporting Actors | Organization Administrator; Geography Steward |
| Owning Module | Competitions | Related Modules | Organizations, Geography, Workspaces, Audit |
| Preconditions | Active Workspace, authorized Organizations/areas, Series or Series-creation authority. | Trigger | Create Series or Edition. |
| Inputs | Names, Series, dimensions/relationships, dates, locale/visibility, source Edition when cloning. | Validation Rules | One Series; relationship types/scopes; area validity; tenant integrity; dates; clone copies as new drafts. |
| Authorization and Scope | Edition creation limited to assigned Workspace/Organization; relationship claims require owning workflow acceptance. | Normal Functional Behavior | Create draft Series/Edition and explicit relationship records. |
| Alternative Behavior | Clone configuration references/values as new draft versions without participants, scores, results, certificates, or evidence. | Failure Behavior | Reject missing/merged dimensions, cross-Workspace relation, invalid dates, or unauthorized organization. |
| Records Read | Workspaces, organizations/relationships, geography, source Edition if cloned. | Records Created | Series/Edition draft and dimension relationships. |
| Records Updated | None outside current draft. | Records Versioned or Superseded | Series/Edition configuration versions. |
| Audit Requirements | Creator, source clone, each dimension, Workspace, relationship evidence. | Domain Events | CompetitionSeriesCreated; CompetitionEditionCreated. |
| Notifications | Assigned organizer/review roles. | Privacy and Data Classification | Draft internal; approved public fields later projected. |
| Accessibility and Interaction Requirements | Distinct labels/help for each dimension; date/time direction/local display. | Postconditions | Edition has one Series and independently queryable dimensions. |
| Related Business Invariants | INV-001, INV-005, INV-006 | Related P0-B01 Requirements | QMDB-CMP-001, QMDB-CMP-002, QMDB-ORG-003 |
| Verification Method | Domain test; database constraint test; authorization test. | Acceptance Criteria | A national privately organized State-hosted Edition can use different eligibility/represented geographies. |

### QMDB-FR-CMP-002 — Configure competition hierarchy and venues

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-CMP-002 | Title | Configure competition hierarchy and venues |
| Requirement Statement | QMDB shall version Category, Division, Age Band, Reading or Riwāyah, Stage, Round, Session template, and Venue configuration as distinct components of a Competition Edition. | Rationale | Eligibility, progression, scheduling, and location have different lifecycles. |
| Priority | High | Planned Implementation Phase | P5 — Competition Configuration and Registration |
| Primary Actors | Competition Director | Supporting Actors | Registrar; Rules Governance; Venue Provider |
| Owning Module | Competitions | Related Modules | Scheduling, Geography, Qur’an Reference, Registration |
| Preconditions | Draft/reviewable Edition and approved reference inputs. | Trigger | Structure create/change. |
| Inputs | Hierarchy, criteria references, Reading, order, venue metadata/visibility, effective version. | Validation Rules | Acyclic hierarchy; unique stable IDs; valid Reading/release; Age Band not legal Minor determination; venue privacy/accessibility. |
| Authorization and Scope | Edition configuration assignment; post-publication changes require controlled version/impact review. | Normal Functional Behavior | Persist configuration version and validate complete hierarchy. |
| Alternative Behavior | Cancel/supersede one Category while Edition continues under controlled state. | Failure Behavior | Reject orphaned/duplicate component, invalid reference, or in-place locked-version change. |
| Records Read | Edition, rules/release, areas/venues, prior structure. | Records Created | Component/configuration records. |
| Records Updated | Draft current structure pointer. | Records Versioned or Superseded | Structure/venue components. |
| Audit Requirements | Actor, before/after, reason, impacted registrations/schedule/rules. | Domain Events | CompetitionStructureVersioned; CategoryCancelled. |
| Notifications | Reviewers and affected registrants after published change. | Privacy and Data Classification | Public configuration after approval; venue security detail restricted. |
| Accessibility and Interaction Requirements | Hierarchy and venue details readable without visual diagram; accessible labels/status. | Postconditions | Valid Edition hierarchy version is available for review/registration. |
| Related Business Invariants | INV-005, INV-006, INV-011 | Related P0-B01 Requirements | QMDB-CMP-001, QMDB-CMP-002, QMDB-QRF-003 |
| Verification Method | Unit test; integration test; accessibility test. | Acceptance Criteria | Age Band remains distinct from legal Minor status and Reading binds a governed release. |

### QMDB-FR-CMP-003 — Govern Edition review, approval, and publication

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-CMP-003 | Title | Govern Edition review, approval, and publication |
| Requirement Statement | QMDB shall move an Edition from DRAFT through UNDER_REVIEW, APPROVED, and PUBLISHED only after configuration completeness, active Ruleset binding, organization authority, conflicts, privacy, accessibility, and required approvals pass. | Rationale | Public Registration cannot rely on incomplete configuration. |
| Priority | Critical | Planned Implementation Phase | P5 — Competition Configuration and Registration |
| Primary Actors | Competition Director; Competition approver category | Supporting Actors | Rules/Privacy/Accessibility reviewers |
| Owning Module | Competitions | Related Modules | Scoring, Organizations, Privacy, Access Control, Audit |
| Preconditions | Draft current version and configured structure/dimensions. | Trigger | Submit for review, approve, publish. |
| Inputs | Edition version, readiness evidence, reviewer decisions, publication window/content. | Validation Rules | Non-self approval under OD-034; active Ruleset; authority/current relations; all mandatory public labels; Step-Up for approval/publication. |
| Authorization and Scope | Edition-scoped initiate/review/approve/publish permissions remain separate. | Normal Functional Behavior | Validate, append decisions/state versions, generate public projection and notification. |
| Alternative Behavior | Return to DRAFT with review findings; suspend publication without deleting configuration. | Failure Behavior | Reject stale/self-approved/incomplete/unapproved state; no public projection. |
| Records Read | Edition/configuration, Ruleset, organizations, reviews/conflicts. | Records Created | Review/approval/publication decisions and Read Model. |
| Records Updated | Edition state/current public projection. | Records Versioned or Superseded | Edition state/configuration/publication. |
| Audit Requirements | Readiness checks, initiator/reviewers/approver, step-up, reasons, versions. | Domain Events | CompetitionReviewRequested; CompetitionApproved; CompetitionPublished. |
| Notifications | Organizer/reviewers and public subscribers on publication. | Privacy and Data Classification | Draft/review internal; public projection minimized. |
| Accessibility and Interaction Requirements | Public information meets WCAG/RTL and clearly identifies scope/status/rules summary. | Postconditions | Edition is publishable/public only from approved version. |
| Related Business Invariants | INV-005, INV-006, INV-011, INV-024 | Related P0-B01 Requirements | QMDB-CMP-001, QMDB-CMP-002, QMDB-ACT-002 |
| Verification Method | Authorization test; integration test; accessibility test. | Acceptance Criteria | An incomplete or self-approved Edition cannot publish. |

### QMDB-FR-CMP-004 — Control operational Edition lifecycle

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-CMP-004 | Title | Control operational Edition lifecycle |
| Requirement Statement | QMDB shall enforce authorized non-linear transitions among registration, screening, scheduling, in-progress, scoring-locked, provisional, Appeal, finalized, certified, archived, suspended, and cancelled Edition states. | Rationale | Each state enables and forbids different work. |
| Priority | Critical | Planned Implementation Phase | P5 — Competition Configuration and Registration |
| Primary Actors | Competition Director | Supporting Actors | Registrar; Chief Judge; Appeal/Certificate roles; Operations |
| Owning Module | Competitions | Related Modules | Registration, Scheduling, Scoring, Results, Appeals, Certificates, Audit |
| Preconditions | Current Edition version and transition-specific readiness/authority. | Trigger | Lifecycle command, timeout, suspension/cancellation decision. |
| Inputs | Event, reason, evidence, approvals, effective time, current version. | Validation Rules | Transition table; active holds/Appeals/quorum; no skipping required gates; cancellation effects. |
| Authorization and Scope | State-specific Edition assignment; high-impact transitions require Step-Up/approval under OD-034. | Normal Functional Behavior | Append state version, enforce module gates, publish events/notifications. |
| Alternative Behavior | Suspend from multiple states and resume only to reviewed prior/allowed state; cancel with category/Edition distinction. | Failure Behavior | Reject forbidden/stale/concurrent transition atomically. |
| Records Read | Edition state/readiness, active records/holds/approvals. | Records Created | Transition decision and cancellation/suspension plan. |
| Records Updated | Current Edition state/gates/projections. | Records Versioned or Superseded | Every state transition. |
| Audit Requirements | Actor/scope, before/after, reason/evidence, readiness/approvals, affected records. | Domain Events | CompetitionStateChanged; CompetitionSuspended; CompetitionCancelled; CompetitionArchived. |
| Notifications | Assigned actors/participants/public according to transition/visibility. | Privacy and Data Classification | Operational detail scoped; public status projection clear. |
| Accessibility and Interaction Requirements | State/impact/timestamps textually exposed; time displayed local plus authoritative UTC context. | Postconditions | Module operations accept/deny work according to current state. |
| Related Business Invariants | INV-008, INV-011, INV-026 | Related P0-B01 Requirements | QMDB-CMP-001, QMDB-SCR-007, QMDB-ACC-003 |
| Verification Method | State-machine test; concurrency test; authorization test. | Acceptance Criteria | FINALIZED cannot return to draft; correction uses versioned Results workflow. |

### QMDB-FR-RUL-001 — Define declarative versioned scoring rules

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-RUL-001 | Title | Define declarative versioned scoring rules |
| Requirement Statement | QMDB shall express Ruleset Versions through an approved declarative schema for Scoring Criteria, bounds, Deduction rules, Mistake Types, exact precision, and required fields without executable code or queries. | Rationale | Rules must be reviewable, safe, deterministic, and reproducible. |
| Priority | Critical | Planned Implementation Phase | P5 — Competition Configuration and Registration |
| Primary Actors | Competition-Rules Governance Body | Supporting Actors | Qualified Qur’an Reviewer; Security Reviewer |
| Owning Module | Scoring | Related Modules | Competitions, Qur’an Reference, Security Operations, Audit |
| Preconditions | Ruleset draft authority and approved schema version. | Trigger | Create/edit draft Ruleset Version. |
| Inputs | Criteria/taxonomy refs, bounds, deductions, decimal precision/rounding, schema version. | Validation Rules | Declarative allowlist; exact decimals; referential validity; complete criteria; no PHP/SQL/JS/shell/code payload. |
| Authorization and Scope | Draft editor cannot activate alone; code execution/admin DB privileges absent. | Normal Functional Behavior | Validate/store draft version and deterministic test vectors. |
| Alternative Behavior | Reject unsupported operator and request schema-governance change. | Failure Behavior | Fail closed on executable/unknown construct or invalid numeric/reference rule. |
| Records Read | Rule schema/taxonomies/Qur’an release. | Records Created | Ruleset/Version draft and validation report. |
| Records Updated | Draft only. | Records Versioned or Superseded | Every draft/approved Ruleset Version. |
| Audit Requirements | Author, schema, change set, validation/security findings. | Domain Events | RulesetVersionCreated; RulesetValidationFailed. |
| Notifications | Rules/qualified review queues. | Privacy and Data Classification | Rules generally publishable after approval; drafts internal. |
| Accessibility and Interaction Requirements | Structured rule editor/review supports keyboard, RTL labels, exact values and errors. | Postconditions | Draft is declarative/validatable or rejected. |
| Related Business Invariants | INV-009, INV-011, INV-012, INV-029 | Related P0-B01 Requirements | QMDB-SCR-001, QMDB-SCR-004, QMDB-SCR-005 |
| Verification Method | Unit test; schema/security test; manual domain review. | Acceptance Criteria | Executable payload is rejected and cannot be persisted as an active rule. |

### QMDB-FR-RUL-002 — Define quorum, aggregation, outlier, tie, and outcome rules

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-RUL-002 | Title | Define quorum, aggregation, outlier, tie, and outcome rules |
| Requirement Statement | QMDB shall version Judge count/quorum, Aggregation Method, outlier handling, Tie-Break sequence, Disqualification, publication, and Appeal-rule references within the declarative Ruleset Version. | Rationale | Outcome behavior must be explicit before scoring. |
| Priority | Critical | Planned Implementation Phase | P5 — Competition Configuration and Registration |
| Primary Actors | Competition-Rules Governance Body | Supporting Actors | Chief Judge; Appeal Governance |
| Owning Module | Scoring | Related Modules | Judging, Results, Appeals, Competitions |
| Preconditions | Draft Ruleset Version and approved policy examples OD-010/OD-021. | Trigger | Configure outcome behavior. |
| Inputs | Count/quorum, aggregation/outlier/tie/disqualification/publication/appeal structures. | Validation Rules | Deterministic, finite/ordered, exact decimal, internally consistent, no silent manual fallback. |
| Authorization and Scope | Rules governance; exact unresolved policy remains unactivatable rather than invented. | Normal Functional Behavior | Store/version rule structures and expected test vectors/explanations. |
| Alternative Behavior | Mark Ruleset incomplete until policy owner supplies a governed value. | Failure Behavior | Reject ambiguous cycle, impossible quorum, unresolved tie path, or executable formula. |
| Records Read | Ruleset draft, criteria, policy decisions. | Records Created | Rule structures/test vectors. |
| Records Updated | Draft version only. | Records Versioned or Superseded | Ruleset Version. |
| Audit Requirements | Author/reviewer, each policy structure, change, validation. | Domain Events | RulesetOutcomeRulesConfigured. |
| Notifications | Rules reviewers when complete/incomplete. | Privacy and Data Classification | Internal until approved; public summary later. |
| Accessibility and Interaction Requirements | Ordered rules exposed as semantic lists/tables, not visual ordering alone. | Postconditions | Deterministic rule definition is complete or remains inactive. |
| Related Business Invariants | INV-009, INV-010, INV-011, INV-012 | Related P0-B01 Requirements | QMDB-SCR-002, QMDB-SCR-008 |
| Verification Method | Unit/property test; manual domain review. | Acceptance Criteria | Insufficient quorum and unresolved tie behavior are deterministic and block inappropriate results. |

### QMDB-FR-RUL-003 — Review, activate, lock, supersede, and assign Rulesets

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-RUL-003 | Title | Review, activate, lock, supersede, and assign Rulesets |
| Requirement Statement | QMDB shall activate, lock, supersede, and assign a Ruleset Version only after independent review, validation/test evidence, approval, Step-Up Authentication, and impact checks preserve the version used by every affected Performance. | Rationale | Rule changes cannot silently alter registrations or results. |
| Priority | Critical | Planned Implementation Phase | P5 — Competition Configuration and Registration |
| Primary Actors | Rules Governance approver category | Supporting Actors | Competition Director; Chief Judge; Auditor |
| Owning Module | Scoring | Related Modules | Competitions, Judging, Results, Audit, Access Control |
| Preconditions | Valid draft and current approval authority. | Trigger | Review/activate/lock/supersede/assign/change request. |
| Inputs | Version/hash, test evidence, decision/reason, target Edition scope, impact, predecessor. | Validation Rules | Non-self approval OD-034; current hash; complete rules; active references; Registration-open change impact/notice. |
| Authorization and Scope | Separate author/reviewer/approver; assignment Edition/category scoped; locked version immutable. | Normal Functional Behavior | Append decisions, activate/lock version, bind target, preserve predecessor, notify affected workflows. |
| Alternative Behavior | New Ruleset Version for post-registration change; existing registrations re-evaluated only via approved policy. | Failure Behavior | Reject stale/self-approved/incomplete/in-place locked edit or destructive supersession. |
| Records Read | Version/validation/reviews, Edition/registrations/performances. | Records Created | Approval/activation/assignment/supersession decision. |
| Records Updated | Current Ruleset/Edition binding status. | Records Versioned or Superseded | Ruleset and bindings; predecessors preserved. |
| Audit Requirements | Author/reviewers/approver, step-up, hash/tests, scope, impact/reason. | Domain Events | RulesetVersionActivated; RulesetLocked; RulesetSuperseded; RulesetAssigned. |
| Notifications | Competition actors and affected applicants/judges on material governed change. | Privacy and Data Classification | Rules/provenance internal until published summary. |
| Accessibility and Interaction Requirements | Accessible diff/test results and impact summary. | Postconditions | Every Edition/Performance can resolve its exact immutable Ruleset Version. |
| Related Business Invariants | INV-011, INV-012, INV-024 | Related P0-B01 Requirements | QMDB-SCR-004, QMDB-SCR-005, QMDB-CMP-004 |
| Verification Method | Authorization test; integration test; audit test. | Acceptance Criteria | Locked Ruleset edits fail; successor does not rewrite existing Performance binding. |

### QMDB-FR-REG-001 — Initiate and submit Registration through authorized channels

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-REG-001 | Title | Initiate and submit Registration through authorized channels |
| Requirement Statement | QMDB shall support public application, invitation, Nomination, and Organization-submitted Registration as separately attributed channels with draft, idempotent submission, deadline, and provenance controls. | Rationale | Initiation channel and nomination authority are distinct from eligibility. |
| Priority | High | Planned Implementation Phase | P5 — Competition Configuration and Registration |
| Primary Actors | Competitor; Guardian; authorized nominator/Organization | Supporting Actors | Registrar; Background Worker |
| Owning Module | Registration | Related Modules | People, Organizations, Competitions, Guardianship, Audit |
| Preconditions | Registration open, eligible channel, current Edition/category configuration. | Trigger | Create/save/submit Registration. |
| Inputs | Person/Profile refs, channel/authority, Edition/category, represented facts, evidence/Consent refs, Idempotency Key. | Validation Rules | Deadline/server time; channel authority; required fields; tenant; duplicates; current rules/config; idempotency payload match. |
| Authorization and Scope | Public/self/Guardian/nominator actions limited by subject and Edition; submission is not approval. | Normal Functional Behavior | Save draft, validate, freeze submitted version, issue receipt, start screening. |
| Alternative Behavior | Expired form refreshes safe inputs; late request routes separate exception, never backdates. | Failure Behavior | Reject closed/deadline-race/duplicate/out-of-scope/stale/changed-idempotency request safely. |
| Records Read | Edition/config/rules, Person/Profile, authority, existing registrations/Consent. | Records Created | Registration draft/submission/version/receipt. |
| Records Updated | Draft or current submission status. | Records Versioned or Superseded | Each submission/change; Person/Profile unchanged. |
| Audit Requirements | Channel/actor/authority, deadline/server time, inputs/evidence refs, idempotency/outcome. | Domain Events | RegistrationDrafted; RegistrationSubmitted. |
| Notifications | Submission receipt and Registrar queue. | Privacy and Data Classification | Registration/evidence restricted; public display separate. |
| Accessibility and Interaction Requirements | Required markers/errors/summary, safe input preservation, mobile/low-bandwidth progress. | Postconditions | One submitted version enters screening or request fails with no duplicate. |
| Related Business Invariants | INV-003, INV-006, INV-007, INV-023 | Related P0-B01 Requirements | QMDB-CMP-003, QMDB-CMP-004, QMDB-ACC-003 |
| Verification Method | End-to-end test; concurrency/idempotency test; accessibility test. | Acceptance Criteria | Deadline race uses server timestamp and duplicate retry returns the same Registration outcome. |

### QMDB-FR-REG-002 — Review eligibility and request evidence

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-REG-002 | Title | Review eligibility and request evidence |
| Requirement Statement | QMDB shall version Eligibility Checks, evidence requests/responses, waitlist, approval, rejection, withdrawal, and pre-competition Disqualification decisions against the identified criteria and Registration version. | Rationale | Eligibility is an explainable review, not a generic flag. |
| Priority | Critical | Planned Implementation Phase | P5 — Competition Configuration and Registration |
| Primary Actors | Competition Registrar | Supporting Actors | Competitor; Guardian; Organization/nominator |
| Owning Module | Eligibility | Related Modules | Registration, Competitions, Geography, Organizations, Guardianship, Audit |
| Preconditions | Submitted Registration and active criteria/reviewer assignment. | Trigger | Screening or evidence/decision action. |
| Inputs | Registration version, criteria/policy, evidence, reviewer reason/outcome. | Validation Rules | Current criteria; evidence provenance/expiry; conflict; geography/organization/Consent; no unexplained verification boolean. |
| Authorization and Scope | Reviewer Edition/category/Workspace scoped; exception/self-related decision prohibited by conflict policy. | Normal Functional Behavior | Evaluate each criterion, request evidence or append decision/reasons, update status and notify. |
| Alternative Behavior | Waitlist/withdraw/re-review through new version; evidence invalidation reopens review without deleting prior decision. | Failure Behavior | Reject stale/conflicted/unsupported decision; keep applicant in safe current state. |
| Records Read | Registration/snapshot candidate, criteria, evidence, relationships/Consent. | Records Created | Eligibility Check/version, evidence request, decision. |
| Records Updated | Registration screening/current status. | Records Versioned or Superseded | Eligibility/evidence/decision history. |
| Audit Requirements | Reviewer/scope/conflict, criteria/version, evidence refs, reasons, outcome. | Domain Events | EligibilityEvidenceRequested; EligibilityDecisionRecorded. |
| Notifications | Applicant/Guardian/nominator and Registrar. | Privacy and Data Classification | Eligibility/identity evidence highly restricted. |
| Accessibility and Interaction Requirements | Reasons and recovery/evidence steps accessible; status not color-only. | Postconditions | Decision is criterion/version/provenance traceable. |
| Related Business Invariants | INV-006, INV-007 | Related P0-B01 Requirements | QMDB-CMP-003, QMDB-PRI-001, QMDB-AUD-001 |
| Verification Method | Unit test; authorization test; integration test; manual privacy review. | Acceptance Criteria | Nomination alone never yields an approved eligibility status. |

### QMDB-FR-REG-003 — Resolve duplicates, categories, nomination, and representation changes

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-REG-003 | Title | Resolve duplicates, categories, nomination, and representation changes |
| Requirement Statement | QMDB shall detect duplicate Registration attempts and govern multiple-category eligibility, category moves, nomination withdrawal, and represented Organization/Geography changes through reviewed versions rather than duplicate or overwritten participation. | Rationale | These changes affect eligibility and historical facts. |
| Priority | High | Planned Implementation Phase | P5 — Competition Configuration and Registration |
| Primary Actors | Registrar; Competitor; nominating Organization | Supporting Actors | Data Steward; Guardian |
| Owning Module | Registration | Related Modules | People, Eligibility, Organizations, Competitions, Audit |
| Preconditions | Existing Registration/candidate match and current Edition rules. | Trigger | Duplicate/change/withdrawal detection or request. |
| Inputs | Person/Edition/category, change reason/evidence, nomination/representation refs, requested effective state. | Validation Rules | Person identity; Edition duplicate key; rule compatibility; authority; deadline/state; snapshot milestone. |
| Authorization and Scope | Applicant/nominator may request/withdraw own scoped input; Registrar reviews; post-snapshot correction uses history-preserving workflow. | Normal Functional Behavior | Link duplicate request to existing record, version approved change, re-run eligibility, notify affected actors. |
| Alternative Behavior | Allow multiple categories only when identified Ruleset/Edition policy permits. | Failure Behavior | Reject hidden duplicate, unauthorized representation, or in-place historical change. |
| Records Read | Person/Registrations, rules, nominations, affiliations, snapshots. | Records Created | Duplicate/change review and new eligibility check. |
| Records Updated | Current Registration category/nomination/representation status. | Records Versioned or Superseded | Registration/Nomination/representation/eligibility history. |
| Audit Requirements | Match basis, requester/reviewer, before/after, evidence, outcome. | Domain Events | DuplicateRegistrationDetected; RegistrationCategoryChanged; NominationWithdrawn. |
| Notifications | Applicant/Guardian/nominator/Registrar. | Privacy and Data Classification | Restricted; duplicate candidates not exposed. |
| Accessibility and Interaction Requirements | Explain existing-record resolution and affected eligibility clearly. | Postconditions | One authoritative Registration per governed uniqueness scope remains. |
| Related Business Invariants | INV-003, INV-006, INV-007 | Related P0-B01 Requirements | QMDB-IDN-002, QMDB-CMP-003, QMDB-CMP-004 |
| Verification Method | Integration test; concurrency test; authorization test. | Acceptance Criteria | Second submission links/returns conflict and cannot create a second accepted Participant. |

### QMDB-FR-REG-004 — Gate Registration by Consent and create Participant Snapshot

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-REG-004 | Title | Gate Registration by Consent and create Participant Snapshot |
| Requirement Statement | QMDB shall create a milestone-specific Participant Snapshot only after Registration acceptance, eligibility, required Guardian/competition/media Consent, and current source facts are validated and copied without linking future Profile edits as mutable history. | Rationale | Official participation needs stable facts and child-safety gates. |
| Priority | Critical | Planned Implementation Phase | P5 — Competition Configuration and Registration |
| Primary Actors | Competition Registrar | Supporting Actors | Competitor; Guardian; Privacy Officer |
| Owning Module | Registration | Related Modules | People, Eligibility, Guardianship, Privacy, Records, Audit |
| Preconditions | Accepted eligibility and required current Consents. | Trigger | Approved milestone (acceptance/Check-In/Performance/Result) snapshot command. |
| Inputs | Person/Profile/Registration versions, representation, age/category, organization/geography, Consent refs, milestone. | Validation Rules | Current versions; explicit fields/provenance; tenant; Consent subject/purpose; no public projection data substitution. |
| Authorization and Scope | Registrar/milestone service in Edition scope; ordinary Profile editor denied snapshot edits. | Normal Functional Behavior | Copy approved historical facts into immutable snapshot version, hash/reference source versions. |
| Alternative Behavior | Block milestone and request Consent/evidence correction; later creates new identified snapshot only where policy permits. | Failure Behavior | Deny missing/withdrawn/ambiguous Consent, stale source, tenant mismatch, or direct update. |
| Records Read | Person/Profile, Registration/Eligibility, Guardian/Consent, affiliations. | Records Created | Participant Snapshot and provenance/hash. |
| Records Updated | Registration milestone/snapshot reference. | Records Versioned or Superseded | Snapshots only through governed new version; prior stable. |
| Audit Requirements | Actor/milestone, source versions, fields/classification, Consent, hash/outcome. | Domain Events | ParticipantSnapshotCreated. |
| Notifications | Participant/Guardian and event operations on blocked/created status. | Privacy and Data Classification | Snapshot restricted; minimized public competitor display is separate. |
| Accessibility and Interaction Requirements | Review summary differentiates current Profile, Registration, Snapshot, public display. | Postconditions | Stable historical snapshot exists or milestone is blocked. |
| Related Business Invariants | INV-007, INV-020, INV-026 | Related P0-B01 Requirements | QMDB-CMP-004, QMDB-PRI-001, QMDB-MED-003 |
| Verification Method | Integration test; authorization test; data-integrity test. | Acceptance Criteria | Subsequent name/organization Profile edits do not change snapshot bytes/version. |

### QMDB-FR-REG-005 — Control exceptions, cancellations, and Registration history

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-REG-005 | Title | Control exceptions, cancellations, and Registration history |
| Requirement Statement | QMDB shall process late Registration, post-deadline approval, Edition/category cancellation, and other Registration exceptions through reasoned, time-stamped, approval-controlled versions that preserve the original deadline, request, and decision history. | Rationale | Exceptions must not be hidden through backdating or deletion. |
| Priority | Critical | Planned Implementation Phase | P5 — Competition Configuration and Registration |
| Primary Actors | Competition Registrar; Competition Director | Supporting Actors | approval-role category; Competitor/Guardian |
| Owning Module | Registration | Related Modules | Competitions, Access Control, Audit, Notifications |
| Preconditions | Existing Edition/Registration and applicable exception/cancellation policy. | Trigger | Late exception request or Edition/category cancellation. |
| Inputs | Server timestamp, reason/evidence, affected scope, approvals, refund/fee policy dependency if any. | Validation Rules | Current version; non-self approval OD-034; deadline retained; conflict; affected-record inventory; no payment assumption. |
| Authorization and Scope | Exception Edition/category scoped; cancellation authority distinct from Registration review. | Normal Functional Behavior | Append request/approval/state changes, preserve history, prevent further invalid processing, notify/reconcile affected records. |
| Alternative Behavior | Reject exception; cancel Category while unaffected Edition continues; withdraw invitation/Registration per state. | Failure Behavior | Reject backdate, stale/self-approved/unsupported exception, or destructive cancellation. |
| Records Read | Edition/category state, deadline, registrations/schedules/fees dependencies. | Records Created | Exception/cancellation decision and impact record. |
| Records Updated | Registration/Category/Edition operational statuses. | Records Versioned or Superseded | Exception/cancellation and affected Registration history. |
| Audit Requirements | Original/request server times, reason/evidence, initiator/approver, affected records/outcome. | Domain Events | LateRegistrationExceptionDecided; RegistrationCancelled; CategoryCancelled. |
| Notifications | Affected applicants/Guardians/nominators/operations. | Privacy and Data Classification | Restricted applicant data; public cancellation status minimized. |
| Accessibility and Interaction Requirements | Clear deadline/timezone, impact, next steps, and non-color status. | Postconditions | Exception/cancellation is transparent and no invalid future action proceeds. |
| Related Business Invariants | INV-007, INV-024, INV-026 | Related P0-B01 Requirements | QMDB-CMP-003, QMDB-CMP-007, QMDB-AUD-001 |
| Verification Method | Authorization test; state-machine test; audit inspection. | Acceptance Criteria | Late approval records both the missed deadline and separate approved exception; cancellation never deletes Registration history. |

## Record-truth separation

Current Person/Profile information, Registration input, Eligibility evidence/decision, Participant Snapshot, and public competitor display are different records. A Ruleset change after Registration opens creates a new version and impact workflow; it never silently reinterprets accepted records.

## Related documents

- [Functional requirements index](../P0-B02-functional-requirements-index.md)
- [Scheduling, judging, and scoring](06-scheduling-judging-and-scoring.md)
- [Workflows and state machines](../P0-B02-workflows-and-state-machines.md)
- [P0-B01 baseline requirements](../P0-B01-requirements.md)

