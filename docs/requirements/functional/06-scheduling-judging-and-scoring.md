# Scheduling, Judging, and Scoring Functional Requirements

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B02 |
| Document Title | Scheduling, Judging, and Scoring Functional Requirements |
| Document Version | 1.0.0 |
| Status | Baselined; implementation not started |
| Document Owner Role | Competition Integrity and Scoring Governance |
| Last Updated | 2026-08-24 |
| Related Documents | [Functional index](../P0-B02-functional-requirements-index.md); [invariants](../../domain/core-modules-and-business-invariants.md); [ADR register](../../project/decision-register.md); [open decisions](../../project/open-decisions.md) |

## Purpose

Specify schedule, Draw Order, Check-In, Judge Panel/Conflict, Performance, Passage Assignment, Score Sheet, exact calculation, concurrency, quorum, aggregation, locking, and controlled reopening behavior.

## Scope

Official rule values remain OD-010 dependencies. Human Judges are authoritative; the server calculates exact official totals. Offline score behavior links to OD-033 and the offline requirements in document 10.

## Functional requirements

### QMDB-FR-SCH-001 — Build and publish conflict-checked schedules

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-SCH-001 | Title | Build and publish conflict-checked schedules |
| Requirement Statement | QMDB shall version Venue and Session schedules only after participant, Judge, Venue, Edition, Round, availability, capacity, and time conflicts are evaluated. | Rationale | Invalid schedules disrupt fairness and operations. |
| Priority | High | Planned Implementation Phase | P5 — Competition Configuration and Registration |
| Primary Actors | Competition Director | Supporting Actors | Registrar; Judges; Venue Provider |
| Owning Module | Scheduling | Related Modules | Competitions, Registration, Judging, Geography, Notifications |
| Preconditions | Approved roster/structure and available Venues/Judges. | Trigger | Schedule create/publish/change. |
| Inputs | Sessions, resources, assignments, UTC/local times, availability, reason/version. | Validation Rules | No incompatible overlap; timezone/DST interpretation; Venue status/capacity/privacy; state/version. |
| Authorization and Scope | Edition scheduling assignment; publication/change after publish may require approval/Step-Up by impact. | Normal Functional Behavior | Validate, version, publish projection, notify affected actors. |
| Alternative Behavior | Hold conflict for reviewed exception; propose alternate Session/Venue. | Failure Behavior | Reject unresolved conflict/stale version; keep prior published schedule. |
| Records Read | Edition/Rounds/roster, Venues, availability, assignments. | Records Created | Schedule version/conflict report. |
| Records Updated | Current published schedule pointer. | Records Versioned or Superseded | Schedules and changes; prior retained. |
| Audit Requirements | Actor, inputs/conflicts, override/approval, before/after, UTC time. | Domain Events | SchedulePublished; ScheduleChanged. |
| Notifications | Participants/Guardians/Judges/operations on publish/change. | Privacy and Data Classification | Public timing/venue minimized; availability/private location restricted. |
| Accessibility and Interaction Requirements | Local/UTC clarity, accessible calendar/list, RTL, no color-only conflicts. | Postconditions | Current schedule is valid/published or unchanged. |
| Related Business Invariants | INV-006, INV-024 | Related P0-B01 Requirements | QMDB-CMP-005, QMDB-ACC-003 |
| Verification Method | Unit test; integration test; accessibility test. | Acceptance Criteria | Overlapping Judge/Participant/Venue assignments are rejected or explicitly governed. |

### QMDB-FR-SCH-002 — Generate and change Draw Order

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-SCH-002 | Title | Generate and change Draw Order |
| Requirement Statement | QMDB shall generate a reproducible Draw Order for eligible assigned Participants and version every approved manual change with method, seed/reference, reason, actor, and notification. | Rationale | Appearance order affects fairness. |
| Priority | High | Planned Implementation Phase | P5 — Competition Configuration and Registration |
| Primary Actors | Competition Director | Supporting Actors | Registrar; Auditor |
| Owning Module | Scheduling | Related Modules | Registration, Competitions, Audit, Notifications |
| Preconditions | Frozen eligible roster and approved draw method. | Trigger | Generate/publish/amend Draw Order. |
| Inputs | Roster version, method/seed, Session/Round, amendment reason/approval. | Validation Rules | Each Participant once; roster/version/current state; deterministic replay; amendment scope. |
| Authorization and Scope | Generate/publish scoped; post-publication amendment requires Step-Up and approval per OD-034. | Normal Functional Behavior | Produce order/version, preserve evidence, publish and notify. |
| Alternative Behavior | Regenerate only before publication under policy; post-publication creates superseding version. | Failure Behavior | Reject missing/duplicate Participant, stale roster, unauthorized or silent reorder. |
| Records Read | Roster/schedule/method. | Records Created | Draw Order/version and calculation evidence. |
| Records Updated | Current published order pointer. | Records Versioned or Superseded | Draw Order; predecessor retained. |
| Audit Requirements | Method/seed, roster hash, actor/approval/reason, versions. | Domain Events | DrawOrderGenerated; DrawOrderChanged. |
| Notifications | Participants/Guardians/Judges/operations. | Privacy and Data Classification | Public order by policy; internal generation evidence. |
| Accessibility and Interaction Requirements | Ordered semantic list/table and clear changed-state announcement. | Postconditions | One reproducible current order exists. |
| Related Business Invariants | INV-007, INV-024 | Related P0-B01 Requirements | QMDB-CMP-005, QMDB-AUD-003 |
| Verification Method | Unit test; integration test; audit inspection. | Acceptance Criteria | Same roster/method/seed reproduces order and amendments retain prior version. |

### QMDB-FR-SCH-003 — Manage Check-In, lateness, absence, withdrawal, and transfer

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-SCH-003 | Title | Manage Check-In, lateness, absence, withdrawal, and transfer |
| Requirement Statement | QMDB shall version Check-In opening/completion, late arrival, no-show, venue withdrawal, Session transfer, and Session closure against the accepted Participant Snapshot and approved event policy. | Rationale | Event-day status must be attributable and not inferred from Registration. |
| Priority | Critical | Planned Implementation Phase | P5 — Competition Configuration and Registration |
| Primary Actors | Competition Registrar | Supporting Actors | Competitor; Guardian; Competition Director |
| Owning Module | Scheduling | Related Modules | Registration, Competitions, Judging, Notifications, Audit |
| Preconditions | Accepted Participant assigned to Session; Check-In window/state. | Trigger | Event-day status action. |
| Inputs | Participant/Snapshot/Session, method/time, identity confirmation, reason/evidence, transfer target. | Validation Rules | Assignment; server time; duplicate/idempotency; target capacity/conflict; policy/approval for exception. |
| Authorization and Scope | Registrar Session-scoped; participant cannot self-confirm official Check-In unless policy explicitly permits method. | Normal Functional Behavior | Append status, associate Snapshot, update readiness, notify downstream. |
| Alternative Behavior | Late/transfer/withdrawal exception creates reviewed state; no-show after window. | Failure Behavior | Reject wrong Session/tenant/Participant/stale/closed state; preserve prior status. |
| Records Read | Registration/Snapshot, schedule, policy, current Check-In. | Records Created | Check-In/status/exception version. |
| Records Updated | Participant Session readiness. | Records Versioned or Superseded | Check-In/transfer/withdrawal history. |
| Audit Requirements | Actor/method, server time, identity/Snapshot, reason/approval, before/after. | Domain Events | ParticipantCheckedIn; ParticipantLate; ParticipantNoShow; ParticipantTransferred. |
| Notifications | Participant/Guardian, Session/Judge operations. | Privacy and Data Classification | Restricted operational/identity data. |
| Accessibility and Interaction Requirements | Fast large-target workflow, offline status, accessible scan/manual fallback and error recovery. | Postconditions | Event-day status is current, versioned, and visible to authorized operations. |
| Related Business Invariants | INV-007, INV-023 | Related P0-B01 Requirements | QMDB-CMP-003, QMDB-CMP-004, QMDB-ACC-003 |
| Verification Method | End-to-end test; authorization test; offline/recovery test. | Acceptance Criteria | Wrong-Session Check-In and duplicate retries do not create a second official status. |

### QMDB-FR-SCH-004 — Close and reconcile a Session

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-SCH-004 | Title | Close and reconcile a Session |
| Requirement Statement | QMDB shall close a Session only after assigned Participants, Check-In outcomes, Judge assignments, Performances, incidents, and required Score Sheets are reconciled or explicitly held. | Rationale | Closure must expose missing operational work. |
| Priority | High | Planned Implementation Phase | P6 — Judging and Deterministic Scoring |
| Primary Actors | Competition Director; Chief Judge | Supporting Actors | Registrar; Platform Operator |
| Owning Module | Scheduling | Related Modules | Judging, Scoring, Registration, Audit |
| Preconditions | Session active/completable and current version. | Trigger | Session closure request/timeout. |
| Inputs | Reconciliation report, unresolved holds, reason/approval. | Validation Rules | All assignments/outcomes; quorum/Score Sheet state; incidents; no active Performance. |
| Authorization and Scope | Session-scoped dual operational/judging confirmation where policy requires. | Normal Functional Behavior | Produce reconciliation, close or hold, publish status. |
| Alternative Behavior | Close with documented hold only under approved policy; reopen via versioned exception. | Failure Behavior | Reject missing/unresolved prerequisites or concurrent stale close. |
| Records Read | Session, roster/check-in, panel, performances, Score Sheets/incidents. | Records Created | Reconciliation/closure decision. |
| Records Updated | Session state. | Records Versioned or Superseded | Session closure/hold versions. |
| Audit Requirements | Readiness, missing items, actors/approvals, reason/outcome. | Domain Events | SessionClosed; SessionClosureHeld. |
| Notifications | Competition/judging/operations actors. | Privacy and Data Classification | Internal operational data. |
| Accessibility and Interaction Requirements | Missing-item summary navigable and non-color-only. | Postconditions | Session closed with complete evidence or remains held. |
| Related Business Invariants | INV-008, INV-011 | Related P0-B01 Requirements | QMDB-CMP-005, QMDB-AUD-001 |
| Verification Method | Integration test; state-machine test. | Acceptance Criteria | An open Performance or missing required Score Sheet prevents ordinary closure. |

### QMDB-FR-JDG-001 — Invite, qualify, and assign Judges

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-JDG-001 | Title | Invite, qualify, and assign Judges |
| Requirement Statement | QMDB shall create a Judge or Chief Judge Competition Assignment only after invitation/acceptance, qualification-evidence review, active Account/Membership, scope/availability, and conflict checks succeed. | Rationale | Judge Profile or title does not authorize scoring. |
| Priority | Critical | Planned Implementation Phase | P6 — Judging and Deterministic Scoring |
| Primary Actors | Competition Director; Judge | Supporting Actors | Chief Judge; qualification reviewer |
| Owning Module | Judging | Related Modules | People, Access Control, Scheduling, Audit, Notifications |
| Preconditions | Approved Edition/Panel need and candidate Judge Profile. | Trigger | Invite/accept/assign/designate. |
| Inputs | Person, evidence status, Edition/category/round/panel/Session/time, chief designation. | Validation Rules | Qualification; availability; tenant/scope; active identity; toxic role/conflict; panel needs. |
| Authorization and Scope | Assignment authority distinct from judging; Judge accepts; designation scope explicit. | Normal Functional Behavior | Version invitation/acceptance/evidence/assignment and grant scoped capability. |
| Alternative Behavior | Pending evidence or restricted assignment; decline/expire invitation. | Failure Behavior | Reject self-assignment, wrong Workspace, conflict, invalid evidence, duplicate/incompatible scope. |
| Records Read | Judge Profile/evidence, Memberships/grants, schedule/conflicts/panel. | Records Created | Invitation, review, Competition Assignment. |
| Records Updated | Panel roster/current assignment status. | Records Versioned or Superseded | Invitation/evidence/assignment/designation. |
| Audit Requirements | Inviter/reviewer, evidence classification, scope, conflict, acceptance, grants. | Domain Events | JudgeInvited; JudgeAssigned; ChiefJudgeDesignated. |
| Notifications | Judge and competition operations. | Privacy and Data Classification | Qualification evidence restricted; public roster approved separately. |
| Accessibility and Interaction Requirements | Scope/time/rules clearly summarized; accessible accept/decline/conflict actions. | Postconditions | Judge has exact assignment or no scoring authority. |
| Related Business Invariants | INV-004, INV-011 | Related P0-B01 Requirements | QMDB-CMP-006, QMDB-ACT-001, QMDB-ACT-003 |
| Verification Method | Authorization test; integration test. | Acceptance Criteria | Accepted invitation without assignment cannot submit scores. |

### QMDB-FR-JDG-002 — Declare, review, and resolve Judge conflicts

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-JDG-002 | Title | Declare, review, and resolve Judge conflicts |
| Requirement Statement | QMDB shall record conflict declarations and govern review, recusal, replacement, exception, assignment suspension, and completion without deleting former assignments or Score Sheets. | Rationale | Conflict handling must be transparent and history-preserving. |
| Priority | Critical | Planned Implementation Phase | P6 — Judging and Deterministic Scoring |
| Primary Actors | Judge; Conflict Reviewer | Supporting Actors | Chief Judge; Competition Director; Auditor |
| Owning Module | Judging | Related Modules | Access Control, Scoring, Appeals, Audit |
| Preconditions | Judge candidate/assignment and conflict policy context. | Trigger | Declaration, discovery, review, recusal/replacement/exception. |
| Inputs | Relationship/type, scope/time, evidence, reviewer decision, replacement/exception. | Validation Rules | Reviewer independence; declaration completeness; Score/Result impact; no silent reassignment; OD-034. |
| Authorization and Scope | Judge declares; independent reviewer decides; exception requires Step-Up/approval and expiry/review. | Normal Functional Behavior | Version conflict, suspend access as necessary, recuse/replace or authorize limited exception, trigger impact review. |
| Alternative Behavior | Late-discovered conflict after scoring places Score/Result on hold and opens governed review. | Failure Behavior | Unresolved conflict blocks official score submission/finalization; retain evidence. |
| Records Read | Assignment, relationships, Score Sheets/Results, policy. | Records Created | Conflict/review/recusal/replacement/exception/impact case. |
| Records Updated | Assignment/access and affected hold state. | Records Versioned or Superseded | Conflict and assignment history. |
| Audit Requirements | Declarer/reviewer, scope/evidence, action, approvals, score/result impacts. | Domain Events | JudgeConflictDeclared; JudgeRecused; JudgeReplaced; ScoreImpactReviewOpened. |
| Notifications | Judge/Chief Judge/operations/affected reviewers. | Privacy and Data Classification | Conflict/evidence restricted; public disclosure governed. |
| Accessibility and Interaction Requirements | Confidential accessible form and explicit access/assignment consequences. | Postconditions | Conflict resolved/excepted with current authority, or scoring remains blocked. |
| Related Business Invariants | INV-004, INV-008, INV-013 | Related P0-B01 Requirements | QMDB-ACT-003, QMDB-CMP-006, QMDB-SCR-007 |
| Verification Method | Workflow test; authorization test; audit inspection. | Acceptance Criteria | Unresolved conflict prevents official submission; late conflict preserves original Score Sheet. |

### QMDB-FR-JDG-003 — Open and conduct a Performance

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-JDG-003 | Title | Open and conduct a Performance |
| Requirement Statement | QMDB shall open, start, pause where approved, complete, and close a Performance only for the assigned checked-in Participant Snapshot, Session, Judge Panel, Ruleset Version, and Passage Assignment. | Rationale | A Performance is the stable scoring/evidence anchor. |
| Priority | Critical | Planned Implementation Phase | P6 — Judging and Deterministic Scoring |
| Primary Actors | Chief Judge; assigned event operator | Supporting Actors | Judges; Registrar; Participant |
| Owning Module | Judging | Related Modules | Scheduling, Registration, Scoring, Qur’an Reference, Media, Audit |
| Preconditions | In-progress Edition/Session, Check-In, panel/quorum readiness. | Trigger | Performance lifecycle action. |
| Inputs | Participant/Snapshot, Session/panel, Passage Range/release, Ruleset, time/reason. | Validation Rules | Assignment/tenant/current versions; approved active/historical release; passage secrecy; transition policy. |
| Authorization and Scope | Performance/panel-scoped actors; participant cannot open official Performance. | Normal Functional Behavior | Confirm identity/Snapshot, assign/reveal passage at authorized time, version start/pause/complete/close. |
| Alternative Behavior | Policy-approved pause/resume; hold before closure. | Failure Behavior | Reject wrong Participant/Session/panel/rules/release/state; preserve current state. |
| Records Read | Snapshot/Check-In, schedule/panel, rules/release/passage. | Records Created | Performance and passage assignment/version. |
| Records Updated | Performance state/timestamps. | Records Versioned or Superseded | Every lifecycle/passage change. |
| Audit Requirements | Actor, identity method, source versions, passage reveal, state/reason. | Domain Events | PerformanceOpened; PassageAssigned; PerformanceStarted; PerformanceCompleted; PerformanceClosed. |
| Notifications | Panel/operations; participant where appropriate. | Privacy and Data Classification | Passage restricted until release; Participant/evidence restricted. |
| Accessibility and Interaction Requirements | Concentration-first UI, large targets, keyboard, clear timer/state without motion dependence. | Postconditions | Performance has stable context ready for scoring or remains safely held. |
| Related Business Invariants | INV-007, INV-011, INV-018 | Related P0-B01 Requirements | QMDB-CMP-005, QMDB-CMP-006, QMDB-QRF-003 |
| Verification Method | End-to-end test; authorization test; state-machine test. | Acceptance Criteria | Wrong-Session Participant or inactive release cannot open an official Performance. |

### QMDB-FR-JDG-004 — Handle Performance interruption and evidence

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-JDG-004 | Title | Handle Performance interruption and evidence |
| Requirement Statement | QMDB shall version technical incidents, interruptions, approved pauses, rescheduling, evidence-media associations, and closure decisions without silently replacing the original Performance. | Rationale | Failures can affect fairness and evidence. |
| Priority | High | Planned Implementation Phase | P6 — Judging and Deterministic Scoring |
| Primary Actors | Chief Judge; Competition Director | Supporting Actors | Platform/Media Operator; Participant |
| Owning Module | Judging | Related Modules | Scheduling, Media, Scoring, Audit, Notifications |
| Preconditions | Open/in-progress Performance or discovered incident. | Trigger | Interruption/incident/reschedule/evidence/close. |
| Inputs | Incident type/time/evidence, affected interval, decision/reason, new Session/Performance relation. | Validation Rules | Current state; authority; policy; evidence hash/chain; no duplicate completed Performance; impacts on Score Sheets. |
| Authorization and Scope | Incident record by assigned actor; reschedule/void/exception approval per OD-034. | Normal Functional Behavior | Preserve original state/evidence, hold scoring, record decision, create linked rescheduled Performance if approved. |
| Alternative Behavior | Resume same Performance if policy permits and evidence continuity is valid. | Failure Behavior | Keep held; deny unapproved overwrite/reschedule or unverifiable evidence association. |
| Records Read | Performance/schedule/scores/media/policy. | Records Created | Incident/decision/rescheduled Performance linkage. |
| Records Updated | Performance hold/closure state. | Records Versioned or Superseded | Incident/Performance versions; originals preserved. |
| Audit Requirements | Reporter/decision actors, timeline/evidence, impacts, approvals. | Domain Events | PerformanceInterrupted; TechnicalIncidentRecorded; PerformanceRescheduled. |
| Notifications | Panel/participant/Guardian/operations. | Privacy and Data Classification | Incident/evidence restricted; public status minimized. |
| Accessibility and Interaction Requirements | Clear recovery status and next action; avoid auto-playing evidence. | Postconditions | Scoring proceeds only from approved valid Performance context. |
| Related Business Invariants | INV-007, INV-008, INV-019 | Related P0-B01 Requirements | QMDB-CMP-005, QMDB-MED-004, QMDB-AUD-001 |
| Verification Method | Integration test; recovery test; audit inspection. | Acceptance Criteria | Reschedule creates a linked new Performance and preserves the interrupted one. |

### QMDB-FR-SCR-001 — Create and validate a Judge-specific Score Sheet

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-SCR-001 | Title | Create and validate a Judge-specific Score Sheet |
| Requirement Statement | QMDB shall create one Judge-specific Score Sheet per assigned Performance context and validate each Criterion, Deduction, Mistake Type, required field, exact precision, and allowed bound against the bound Ruleset Version. | Rationale | Invalid or cross-assignment inputs cannot become official evidence. |
| Priority | Critical | Planned Implementation Phase | P6 — Judging and Deterministic Scoring |
| Primary Actors | Judge | Supporting Actors | Chief Judge |
| Owning Module | Scoring | Related Modules | Judging, Qur’an Reference, Audit |
| Preconditions | Open/completed scoreable Performance and active assignment without unresolved conflict. | Trigger | Score Sheet creation/entry. |
| Inputs | Performance/Judge/rules versions, Criterion/Deduction/Mistake entries. | Validation Rules | Tenant/panel/Session/assignment; exact DECIMAL; bounds; required criteria; taxonomy/rules match. |
| Authorization and Scope | Judge can edit only own draft for assigned Performance; peers/clients/integrations denied. | Normal Functional Behavior | Create sheet from rules, accept valid entries, calculate non-authoritative preview/server draft total. |
| Alternative Behavior | Flag anomaly or save partial draft while identifying missing required entries. | Failure Behavior | Reject out-of-range/invalid/missing-on-submit/wrong-scope/rules mismatch without changing prior values. |
| Records Read | Performance, assignment/conflict, Ruleset/taxonomy, existing sheet. | Records Created | Score Sheet draft and Score Items. |
| Records Updated | Judge-owned draft only. | Records Versioned or Superseded | Draft revisions per policy; submitted versions separately immutable. |
| Audit Requirements | Creation, sensitive reads, validation failures/anomalies; draft audit per policy. | Domain Events | ScoreSheetCreated; ScoreDraftSaved; ScoreAnomalyFlagged. |
| Notifications | Judge validation; Chief Judge anomaly only when policy permits. | Privacy and Data Classification | Scores/evidence highly restricted until publication. |
| Accessibility and Interaction Requirements | Keyboard-first criterion order, large controls, exact numeric semantics, errors linked to fields. | Postconditions | Valid owned draft exists or no change occurred. |
| Related Business Invariants | INV-008, INV-009, INV-011 | Related P0-B01 Requirements | QMDB-SCR-001, QMDB-CMP-006, QMDB-ACC-003 |
| Verification Method | Unit test; authorization test; boundary/property test; accessibility test. | Acceptance Criteria | Wrong-panel Judge and out-of-range Criterion are rejected. |

### QMDB-FR-SCR-002 — Autosave drafts with concurrency protection

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-SCR-002 | Title | Autosave drafts with concurrency protection |
| Requirement Statement | QMDB shall autosave a Judge’s Score Sheet draft idempotently using current Record Version and row-level concurrency checks without treating browser/offline state as authoritative. | Rationale | Network interruption and multiple tabs must not overwrite newer work. |
| Priority | High | Planned Implementation Phase | P6 — Judging and Deterministic Scoring |
| Primary Actors | Judge | Supporting Actors | Platform Operations |
| Owning Module | Scoring | Related Modules | Judging, Audit, Offline Resilience |
| Preconditions | Authorized owned draft and current version. | Trigger | Explicit/autosave request. |
| Inputs | Draft fields, expected version, Idempotency Key, client sequence/status. | Validation Rules | Scope/rules/bounds; expected version; identical idempotent payload; server timestamp; no submitted/locked edit. |
| Authorization and Scope | Own assigned draft only. | Normal Functional Behavior | Transactionally save valid changes, increment version, return server state/receipt. |
| Alternative Behavior | Duplicate identical request returns prior result; stale client receives conflict/current safe summary. | Failure Behavior | Reject mismatched idempotency payload/stale/locked/wrong-scope update; preserve server version. |
| Records Read | Score Sheet/version, assignment/rules. | Records Created | Idempotency/concurrency receipt. |
| Records Updated | Draft version. | Records Versioned or Superseded | Draft history as configured. |
| Audit Requirements | Conflicts, suspicious duplicates, rules/scope denials; correlation/sequence. | Domain Events | ScoreDraftSaved; ScoreDraftConflictDetected. |
| Notifications | In-interface saved/conflict/connection status. | Privacy and Data Classification | Restricted score data; local cache encrypted/protected where offline approved. |
| Accessibility and Interaction Requirements | Announce saved/pending/conflict without stealing focus; preserve safe input. | Postconditions | Server holds exactly one current draft version. |
| Related Business Invariants | INV-008, INV-010, INV-023 | Related P0-B01 Requirements | QMDB-SCR-003, QMDB-ACC-003, QMDB-OPS-001 |
| Verification Method | Concurrency test; integration test; accessibility test. | Acceptance Criteria | Stale tab cannot overwrite current draft and duplicate key never creates duplicate effect. |

### QMDB-FR-SCR-003 — Submit, calculate, receipt, lock, and sign Score Sheets

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-SCR-003 | Title | Submit, calculate, receipt, lock, and sign Score Sheets |
| Requirement Statement | QMDB shall submit and lock a complete Score Sheet idempotently only after Step-Up Authentication, current assignment/conflict/rules/state/version checks, server exact-decimal calculation, and creation of an integrity receipt/signature record. | Rationale | Submission establishes official Judge evidence. |
| Priority | Critical | Planned Implementation Phase | P6 — Judging and Deterministic Scoring |
| Primary Actors | Judge | Supporting Actors | Chief Judge; Security Operator |
| Owning Module | Scoring | Related Modules | Identity, Judging, Audit, Security Operations |
| Preconditions | Complete current draft and scoreable Performance/state. | Trigger | Review-and-submit command. |
| Inputs | Sheet/version, Idempotency Key, Step-Up result, client preview, confirmation. | Validation Rules | All criteria; exact bounds; server total; assignment/panel/tenant; no conflict; Ruleset version; fresh step-up; concurrency. |
| Authorization and Scope | Judge submits only own assigned sheet; external/peer/client total cannot authorize. | Normal Functional Behavior | Transactionally calculate, append submitted/locked version, hash/sign receipt, outbox event, return server total/receipt. |
| Alternative Behavior | Client-total mismatch shows server result and requires confirmation/resolution; identical retry returns receipt. | Failure Behavior | Reject missing/quorum-independent validation, stale/duplicate-different-payload, conflict, wrong scope/rules/state; no partial lock. |
| Records Read | Draft, assignment/conflict, Performance/Ruleset, step-up/idempotency. | Records Created | Submitted Score Sheet Version, Judge Total, receipt/hash/signature, Outbox Event. |
| Records Updated | Sheet current state to submitted/locked. | Records Versioned or Superseded | Submitted version immutable; draft predecessor retained. |
| Audit Requirements | Judge/scope, step-up, rules/sheet versions, exact calculation trace/hash, idempotency/outcome. | Domain Events | ScoreSheetSubmitted; ScoreSheetLocked. |
| Notifications | Judge submission receipt; Chief Judge completion status. | Privacy and Data Classification | Highly restricted official scoring data. |
| Accessibility and Interaction Requirements | Review summary, explicit final action, server/client difference, receipt and recoverable errors accessible. | Postconditions | Exactly one immutable submitted version/receipt exists or submission failed atomically. |
| Related Business Invariants | INV-008, INV-009, INV-010, INV-011, INV-023 | Related P0-B01 Requirements | QMDB-SCR-001, QMDB-SCR-002, QMDB-SCR-003, QMDB-SCR-006 |
| Verification Method | End-to-end test; concurrency test; authorization/security test; exact arithmetic test. | Acceptance Criteria | Manipulated client total is non-authoritative and retry after lost acknowledgement returns one official submission. |

### QMDB-FR-SCR-004 — Evaluate quorum and aggregate panel scores

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-SCR-004 | Title | Evaluate quorum and aggregate panel scores |
| Requirement Statement | QMDB shall calculate quorum, approved outlier handling, Aggregated Score, Tie-Break outcome, anomaly flags, and calculation trace on the server from locked Score Sheet Versions under one Ruleset Version. | Rationale | Panel outcome must be deterministic and reproducible. |
| Priority | Critical | Planned Implementation Phase | P6 — Judging and Deterministic Scoring |
| Primary Actors | Chief Judge | Supporting Actors | Background Worker; Auditor |
| Owning Module | Scoring | Related Modules | Judging, Results, Audit, Platform Operations |
| Preconditions | Performance complete, scoring locked/ready, required sheets or governed absence state. | Trigger | Aggregation request/event. |
| Inputs | Performance/Ruleset, locked sheets/totals, panel/quorum, Idempotency Key. | Validation Rules | Same tenant/performance/rules; exact decimal; required quorum; valid current sheet versions; deterministic ordering/tie rules. |
| Authorization and Scope | Server worker/Chief Judge trigger within assignment; result ownership remains Results. | Normal Functional Behavior | Validate inputs, compute trace/hash, persist aggregate candidate and outbox event idempotently. |
| Alternative Behavior | Hold for missing sheet/quorum/anomaly/Ruleset issue; Chief Judge review cannot silently edit inputs. | Failure Behavior | No aggregate on insufficient quorum/mismatch/stale/worker crash; retry from authoritative inputs. |
| Records Read | Panel/assignments, locked sheets, Ruleset. | Records Created | Aggregation calculation/trace/anomaly/receipt. |
| Records Updated | Performance scoring readiness. | Records Versioned or Superseded | Aggregation attempts/results by input-version set. |
| Audit Requirements | Trigger, input IDs/versions/hashes, rules, quorum/outlier/tie trace, outcome. | Domain Events | PanelAggregationCompleted; AggregationHeld. |
| Notifications | Chief Judge/operations on completion/hold/anomaly. | Privacy and Data Classification | Restricted until authorized Result projection. |
| Accessibility and Interaction Requirements | Calculation explanation uses semantic tables/text and exact values. | Postconditions | Deterministic aggregate candidate exists or explicit hold remains. |
| Related Business Invariants | INV-009, INV-010, INV-011, INV-022, INV-023 | Related P0-B01 Requirements | QMDB-SCR-002, QMDB-SCR-008, QMDB-OPS-001 |
| Verification Method | Unit/property test; integration/recovery test; audit inspection. | Acceptance Criteria | Missing quorum prevents aggregation and worker retry produces one identical outcome. |

### QMDB-FR-SCR-005 — Reopen and resubmit a Score Sheet under control

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-SCR-005 | Title | Reopen and resubmit a Score Sheet under control |
| Requirement Statement | QMDB shall reopen, void, resubmit, or supersede a submitted Score Sheet only through a reasoned evidence-backed approval workflow that creates a new version and preserves every former version, signature, and calculation trace. | Rationale | Locked Judge evidence cannot be edited in place. |
| Priority | Critical | Planned Implementation Phase | P6 — Judging and Deterministic Scoring |
| Primary Actors | Chief Judge or correction initiator; Judge | Supporting Actors | approval-role category; Appeal Reviewer; Auditor |
| Owning Module | Scoring | Related Modules | Judging, Results, Appeals, Access Control, Audit |
| Preconditions | Submitted sheet/current version and policy-permitted reopening point. | Trigger | Reopening request/decision or approved remedy. |
| Inputs | Sheet/version, reason/evidence, requester/approver/conflicts, scope/expiry, Step-Up results. | Validation Rules | Non-self approval OD-034; current version; Result/Appeal state; conflicts; evidence; no direct update; assigned Judge/resubmission rules. |
| Authorization and Scope | Separate request/approval; Judge edits only newly opened draft; post-finalization requires Result correction/Appeal policy. | Normal Functional Behavior | Append request/decision, create new draft version linked to predecessor, time-limit, resubmit/lock as new version, recompute downstream. |
| Alternative Behavior | Reject/expire request; void a version only with reason and successor/current-state clarity. | Failure Behavior | Deny direct locked update, stale/self-approved/conflicted/out-of-window request; preserve all records. |
| Records Read | Sheet versions/signatures, Performance/Ruleset, Result/Appeal, assignment/conflict. | Records Created | Reopening request/approval/new Sheet Version/impact case. |
| Records Updated | Current sheet state and downstream hold. | Records Versioned or Superseded | All sheets, decisions, aggregates/results through owning workflows. |
| Audit Requirements | Requester/approver, step-up/conflict, reason/evidence, before/after, expiry, recalculation. | Domain Events | ScoreReopeningRequested; ScoreSheetReopened; ScoreSheetResubmitted; ScoreSheetSuperseded. |
| Notifications | Judge/Chief Judge, Result/Appeal/Certificate owners where affected. | Privacy and Data Classification | Highly restricted official evidence. |
| Accessibility and Interaction Requirements | Display immutable former/current versions and reason; accessible diff and expiry. | Postconditions | Request denied/expired or a new traceable locked version becomes current; old unchanged. |
| Related Business Invariants | INV-008, INV-013, INV-014, INV-016, INV-024 | Related P0-B01 Requirements | QMDB-SCR-003, QMDB-SCR-006, QMDB-SCR-007 |
| Verification Method | Authorization test; state-machine test; end-to-end/audit test. | Acceptance Criteria | Locked row update fails; approved reopening preserves predecessor and triggers controlled recalculation. |

### QMDB-FR-SCR-006 — Fail scoring safely under scope, state, concurrency, and network errors

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-SCR-006 | Title | Fail scoring safely under scope, state, concurrency, and network errors |
| Requirement Statement | QMDB shall reject or safely reconcile scoring attempts involving wrong tenant/panel/Session/Participant, inactive state, stale version, invalid criterion/deduction, Ruleset mismatch, unresolved conflict, duplicate/nonmatching idempotency, concurrent finalization, or out-of-order offline sequence. | Rationale | Failure must protect official score integrity first. |
| Priority | Critical | Planned Implementation Phase | P6 — Judging and Deterministic Scoring |
| Primary Actors | Judge; Chief Judge; offline scoring client | Supporting Actors | Security/Platform Operators |
| Owning Module | Scoring | Related Modules | Access Control, Offline Resilience, Security Operations, Audit |
| Preconditions | Any scoring command/synchronization. | Trigger | Validation/concurrency/network/replay failure. |
| Inputs | Command payload/version/idempotency/sequence/context and authoritative current state. | Validation Rules | All locked score controls; payload-hash match; sequence; commit receipt; server time/Ruleset. |
| Authorization and Scope | Deny by default; offline package grants only signed assigned scope and never finalization authority. | Normal Functional Behavior | Commit once or return prior receipt; on conflict return safe current version/reconciliation path. |
| Alternative Behavior | Queue valid deferred command for ordered reconciliation; hold aggregation. | Failure Behavior | Roll back uncommitted changes, never guess/merge Score Items, alert tampering/cross-tenant attempts, retain evidence. |
| Records Read | Command receipt/idempotency, assignment/sheet/rules/state/sequence. | Records Created | Rejection/reconciliation/security evidence. |
| Records Updated | None unless one authorized idempotent command commits. | Records Versioned or Superseded | Reconciliation decisions; official versions preserved. |
| Audit Requirements | Failure category/context/versions/hashes/sequence, actor/device, security escalation. | Domain Events | ScoreSubmissionRejected; ScoreSyncConflictDetected; ScoreTamperingSuspected. |
| Notifications | Judge actionable recovery; Chief Judge/security/operations on material issue. | Privacy and Data Classification | Highly restricted; errors disclose minimum data. |
| Accessibility and Interaction Requirements | Preserve safe input, announce pending/committed/conflict, specific recovery without repeated submit pressure. | Postconditions | At most one valid official effect exists; failure state is traceable/recoverable. |
| Related Business Invariants | INV-002, INV-008, INV-009, INV-010, INV-011, INV-023, INV-028 | Related P0-B01 Requirements | QMDB-SEC-001, QMDB-OPS-001, QMDB-ACC-003 |
| Verification Method | Negative authorization/security test; concurrency test; recovery/offline test. | Acceptance Criteria | Failure after commit and retry returns one receipt; out-of-order offline data cannot overwrite newer official state. |

## Authoritative calculation rule

Every official numeric value is server-calculated using exact decimal arithmetic under one identified Ruleset Version. Client totals are previews. Missing quorum, unresolved conflict, stale versions, invalid data, or uncertain offline order cause a hold or denial—never a guessed Result.

## Related documents

- [Functional requirements index](../P0-B02-functional-requirements-index.md)
- [Competition configuration and Registration](05-competition-configuration-and-registration.md)
- [Results, Appeals, Certificates, and Records](07-results-appeals-certificates-and-records.md)
- [P0-B01 baseline requirements](../P0-B01-requirements.md)

