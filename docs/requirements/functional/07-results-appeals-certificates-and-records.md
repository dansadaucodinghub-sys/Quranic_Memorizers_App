# Results, Appeals, Certificates, and Records Functional Requirements

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B02 |
| Document Title | Results, Appeals, Certificates, and Records Functional Requirements |
| Document Version | 1.0.0 |
| Status | Baselined; implementation not started |
| Document Owner Role | Results and Trusted Records Governance |
| Last Updated | 2026-08-24 |
| Related Documents | [Functional index](../P0-B02-functional-requirements-index.md); [invariants](../../domain/core-modules-and-business-invariants.md); [open decisions](../../project/open-decisions.md) |

## Purpose

Specify sequenced live projections, Results, Appeals, corrections, certificates, public verification, durable records/provenance, and legacy imports.

## Scope

Appeal authority/fees depend on OD-021/OD-035; certificate key custody on OD-017; legacy authority on OD-019. No payment or authority is assumed.

## Functional requirements

### QMDB-FR-LIV-001 — Publish sequenced live projections

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-LIV-001 | Title | Publish sequenced live projections |
| Requirement Statement | QMDB shall publish live competition updates as status-labeled, monotonically sequenced Read Model events derived through the Transactional Outbox rather than as authoritative score records. | Rationale | Public delivery can lag/replay and must remain rebuildable. |
| Priority | High | Planned Implementation Phase | P7 — Live Competition, Results, and Appeals |
| Primary Actors | Public Visitor | Supporting Actors | Background Worker; Platform Operator |
| Owning Module | Results | Related Modules | Platform Operations, Search, Notifications, Audit |
| Preconditions | Committed publishable authoritative change and policy-approved fields. | Trigger | Outbox event for schedule/performance/provisional/final status. |
| Inputs | Event/version/sequence/Workspace/public fields/status/time. | Validation Rules | Privacy/minor policy; contiguous scoped sequence; idempotency; authoritative version reference. |
| Authorization and Scope | Public only for approved projection; private event streams separately authenticated. | Normal Functional Behavior | Build projection, assign sequence, deliver by SSE, cache safely. |
| Alternative Behavior | Mark stale/paused and serve last verified snapshot with time/version. | Failure Behavior | Do not synthesize missing events or expose source/private data. |
| Records Read | Authoritative result/schedule state and privacy policy. | Records Created | Read Model/live event. |
| Records Updated | Projection cursor/status. | Records Versioned or Superseded | Projection versions are rebuildable. |
| Audit Requirements | Publisher/version/sequence, privacy decision, pause/resume anomalies. | Domain Events | PublicLiveUpdatePublished. |
| Notifications | None distinct from live event unless configured. | Privacy and Data Classification | Public minimized projection. |
| Accessibility and Interaction Requirements | Screen-reader live-region throttling, text status, reduced motion, low bandwidth. | Postconditions | Projection identifies source version/sequence and authority class. |
| Related Business Invariants | INV-022, INV-023, INV-030 | Related P0-B01 Requirements | QMDB-CMP-007, QMDB-OPS-001, QMDB-ACC-003 |
| Verification Method | Integration test; recovery/load/accessibility test. | Acceptance Criteria | Duplicate/out-of-order events do not duplicate or regress the public projection. |

### QMDB-FR-LIV-002 — Reconnect, pause, and recover live delivery

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-LIV-002 | Title | Reconnect, pause, and recover live delivery |
| Requirement Statement | QMDB shall recover an interrupted live client from its last accepted sequence or a versioned static snapshot and visibly indicate stale, reconnecting, paused, provisional, and final states. | Rationale | Connectivity failure must not misrepresent current Result state. |
| Priority | High | Planned Implementation Phase | P7 — Live Competition, Results, and Appeals |
| Primary Actors | Public Visitor; venue user | Supporting Actors | Platform Operator |
| Owning Module | Platform Operations | Related Modules | Results, Search |
| Preconditions | Client has no/last sequence and public stream/snapshot. | Trigger | Connect/reconnect/gap/pause. |
| Inputs | Last sequence, stream scope, snapshot version, connection status. | Validation Rules | Sequence availability; snapshot signature/version; no private fields; bounded replay. |
| Authorization and Scope | Public stream limited to published scope. | Normal Functional Behavior | Replay missing events or replace with verified snapshot, then resume. |
| Alternative Behavior | Maintain labeled static snapshot during pause/outage. | Failure Behavior | Show unavailable/stale with last update; never display guessed/current claim. |
| Records Read | Live event log/projection/snapshot. | Records Created | Connection/recovery telemetry. |
| Records Updated | Client/projection cursor only. | Records Versioned or Superseded | Static snapshot/projection versions. |
| Audit Requirements | Material sequence gaps, operational pause/resume and snapshot issuance. | Domain Events | LiveDeliveryPaused; LiveDeliveryRecovered. |
| Notifications | Operational status announcement for material outage. | Privacy and Data Classification | Public projection; connection telemetry minimized. |
| Accessibility and Interaction Requirements | Status announced without rapid repetition/color; manual retry and low-data snapshot. | Postconditions | Client is current to sequence or clearly stale/unavailable. |
| Related Business Invariants | INV-022, INV-023, INV-030 | Related P0-B01 Requirements | QMDB-CMP-007, QMDB-OPS-002, QMDB-OPS-004 |
| Verification Method | End-to-end test; recovery test; accessibility test. | Acceptance Criteria | Reconnection catches up without duplicate rows and final status supersedes cached provisional state. |

### QMDB-FR-RSL-001 — Calculate ranking, placement, ties, and disqualification

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-RSL-001 | Title | Calculate ranking, placement, ties, and disqualification |
| Requirement Statement | QMDB shall derive Ranking, Placement, tie resolution, and Disqualification treatment from exact Aggregated Scores and the bound Ruleset Version with a reproducible calculation trace. | Rationale | Outcomes cannot depend on undocumented manual interpretation. |
| Priority | Critical | Planned Implementation Phase | P7 — Live Competition, Results, and Appeals |
| Primary Actors | Chief Judge | Supporting Actors | Results Worker; Auditor |
| Owning Module | Results | Related Modules | Scoring, Competitions, Audit |
| Preconditions | Valid aggregate candidates and Ruleset/quorum. | Trigger | Result calculation. |
| Inputs | Aggregates/versions, rule/tie/disqualification data, Participant scope. | Validation Rules | Exact decimals; same result scope/rules; deterministic order; approved disqualification decision. |
| Authorization and Scope | Server calculation; Chief Judge reviews but cannot edit inputs/result directly. | Normal Functional Behavior | Calculate candidate result bundle/trace/hash and readiness. |
| Alternative Behavior | Hold unresolved tie/anomaly/disqualification review. | Failure Behavior | No ranking on missing/mismatched inputs or unresolved rule path. |
| Records Read | Aggregates, Ruleset, Participants, decisions. | Records Created | Result calculation candidate/trace. |
| Records Updated | Readiness state. | Records Versioned or Superseded | Each input-version result calculation. |
| Audit Requirements | Input IDs/hashes, rule trace, server calculation, holds/outcome. | Domain Events | ResultCalculated; ResultCalculationHeld. |
| Notifications | Chief Judge/results reviewers. | Privacy and Data Classification | Restricted until publication. |
| Accessibility and Interaction Requirements | Exact result explanation in semantic table/text. | Postconditions | Reproducible candidate exists or hold is explicit. |
| Related Business Invariants | INV-009, INV-010, INV-011 | Related P0-B01 Requirements | QMDB-SCR-002, QMDB-SCR-008 |
| Verification Method | Unit/property test; integration test. | Acceptance Criteria | Same inputs/rules produce identical ordering and unresolved tie blocks placement. |

### QMDB-FR-RSL-002 — Publish and hold Provisional Results

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-RSL-002 | Title | Publish and hold Provisional Results |
| Requirement Statement | QMDB shall publish a Provisional Result only from an approved calculation version and clearly label its scope, Ruleset, publication time, Appeal status/window, hold state, and non-final authority. | Rationale | Public audiences must distinguish provisional from final. |
| Priority | Critical | Planned Implementation Phase | P7 — Live Competition, Results, and Appeals |
| Primary Actors | Chief Judge; Result Publisher | Supporting Actors | Competition Director; Public Visitor |
| Owning Module | Results | Related Modules | Appeals, Search, Notifications, Audit |
| Preconditions | Calculation ready and Edition in provisional-capable state. | Trigger | Publish/hold/unhold provisional result. |
| Inputs | Result version, approvals, public fields, Appeal window, hold reason. | Validation Rules | Current version/quorum; conflicts/holds; privacy; non-self approval OD-034; state. |
| Authorization and Scope | Publication separate from calculation; Step-Up and Edition scope. | Normal Functional Behavior | Append provisional/version state, build public projection, open Appeal window, notify. |
| Alternative Behavior | Place/release hold with reason and new version; unpublish projection without deleting record. | Failure Behavior | Reject missing approval/quorum/open integrity issue/stale version; no partial public state. |
| Records Read | Candidate/readiness/approvals/privacy. | Records Created | Provisional Result Version/public projection/Appeal window. |
| Records Updated | Edition/Result publication status. | Records Versioned or Superseded | Provisional/hold/public projection. |
| Audit Requirements | Publisher/approver, step-up, version/hash, hold/privacy decision. | Domain Events | ProvisionalResultPublished; ResultHeld. |
| Notifications | Participants/Guardians/organizers/subscribers. | Privacy and Data Classification | Public minimized; detailed scores controlled. |
| Accessibility and Interaction Requirements | “Provisional” textual heading/status, timestamp, non-color indicators. | Postconditions | Clearly provisional projection exists or prior state remains. |
| Related Business Invariants | INV-014, INV-022, INV-024 | Related P0-B01 Requirements | QMDB-SCR-007, QMDB-CMP-007, QMDB-ACC-001 |
| Verification Method | Authorization test; end-to-end/accessibility test. | Acceptance Criteria | Provisional result never appears as final in UI/API/export. |

### QMDB-FR-RSL-003 — Finalize and package Results

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-RSL-003 | Title | Finalize and package Results |
| Requirement Statement | QMDB shall finalize a Result only after required Score Sheets/quorum, calculation, conflict/anomaly review, Appeal-window/open-Appeal checks, independent approvals, and Step-Up Authentication produce a signed/versioned Final-Result bundle. | Rationale | Finalization is a high-impact multi-evidence decision. |
| Priority | Critical | Planned Implementation Phase | P7 — Live Competition, Results, and Appeals |
| Primary Actors | Result Finalizer category | Supporting Actors | Chief Judge; Appeal Reviewer; Auditor |
| Owning Module | Results | Related Modules | Scoring, Appeals, Certificates, Records, Audit |
| Preconditions | Provisional Result current and finalization readiness. | Trigger | Finalize request. |
| Inputs | Result/input versions, approvals, Appeal/conflict/hold status, Step-Up, reason. | Validation Rules | No open Appeal/hold; quorum; current immutable inputs; non-self multi-approval OD-034; exact trace/hash. |
| Authorization and Scope | Separate scoped finalizer/approver; Chief Judge alone not unrestricted. | Normal Functional Behavior | Transactionally append Final Result/bundle/hash/signature metadata, lock state, outbox events. |
| Alternative Behavior | Hold readiness and request missing sheet/evidence/Appeal decision. | Failure Behavior | Reject concurrent/stale/incomplete/open-Appeal/self-approved attempt atomically. |
| Records Read | Scores/aggregates/provisional/Appeals/conflicts/approvals. | Records Created | Final Result Version/bundle/signature and outbox. |
| Records Updated | Current Result/Edition state. | Records Versioned or Superseded | Finalization and public projection; provisional preserved. |
| Audit Requirements | Full input/version/hash trace, actors/approvals/step-up, readiness checks/outcome. | Domain Events | ResultFinalized. |
| Notifications | Participants/Guardians/organizers/certificate/records/public projection. | Privacy and Data Classification | Authoritative restricted bundle; minimized public projection. |
| Accessibility and Interaction Requirements | Final status/time/authority explicit; accessible approval/readiness summary. | Postconditions | Immutable Final Result Version exists or no state change. |
| Related Business Invariants | INV-008, INV-011, INV-014, INV-016, INV-024, INV-026 | Related P0-B01 Requirements | QMDB-SCR-007, QMDB-CER-003, QMDB-AUD-001 |
| Verification Method | End-to-end test; authorization/concurrency test; integrity test. | Acceptance Criteria | Open Appeal or missing approval blocks finalization and simultaneous requests create one version. |

### QMDB-FR-RSL-004 — Correct, supersede, withdraw, or archive Results

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-RSL-004 | Title | Correct, supersede, withdraw, or archive Results |
| Requirement Statement | QMDB shall correct, supersede, withdraw, or archive a Result only by adding an approved version/state with reason, evidence, prior-version link, public status, certificate impact, and complete audit history. | Rationale | Final results cannot be edited or erased silently. |
| Priority | Critical | Planned Implementation Phase | P7 — Live Competition, Results, and Appeals |
| Primary Actors | Result Correction initiator/approver categories | Supporting Actors | Appeal Reviewer; Certificate Officer; Records Steward |
| Owning Module | Results | Related Modules | Appeals, Certificates, Records, Search, Audit |
| Preconditions | Existing Result and approved correction/withdrawal basis. | Trigger | Appeal remedy, error correction, withdrawal/archive request. |
| Inputs | Prior version, corrected inputs/result, reason/evidence, approvals/Step-Up, impact. | Validation Rules | Non-self approval OD-034; current version; recalculation; certificate/ranking/public impact; no delete. |
| Authorization and Scope | Sensitive correction separated from requester/score editor; archive does not change truth. | Normal Functional Behavior | Append corrected/state version, supersede prior current use, refresh projections, open certificate/record review. |
| Alternative Behavior | Hold/dispute without correction while evidence is reviewed. | Failure Behavior | Reject in-place/stale/unapproved/destructive action; preserve former public status history. |
| Records Read | Result versions, evidence/Appeal, certificates/records/projections. | Records Created | Correction/withdrawal/archive decision and impact cases. |
| Records Updated | Current Result pointer/status/projections. | Records Versioned or Superseded | Results/rankings/public projections; prior preserved. |
| Audit Requirements | Requester/approvers, reason/evidence, before/after trace/hash, impacts/notifications. | Domain Events | ResultCorrected; ResultSuperseded; ResultWithdrawn; ResultArchived. |
| Notifications | Participants/Guardians/organizers/public/certificate/records roles. | Privacy and Data Classification | Detailed evidence restricted; public change reason/status governed. |
| Accessibility and Interaction Requirements | Current/former status and correction reason/date clearly navigable. | Postconditions | New current version/state exists and all prior versions remain. |
| Related Business Invariants | INV-014, INV-015, INV-016, INV-026 | Related P0-B01 Requirements | QMDB-SCR-007, QMDB-CER-002, QMDB-CER-003 |
| Verification Method | Authorization test; integration/end-to-end test; audit inspection. | Acceptance Criteria | Correction changes ranking through a new Result Version and triggers certificate review. |

### QMDB-FR-APL-001 — Accept eligible Appeals within governed time

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-APL-001 | Title | Accept eligible Appeals within governed time |
| Requirement Statement | QMDB shall accept an Appeal only for an eligible appellant, appealable identified record/version, server-evaluated window, required reason/evidence, and policy-compliant fee state without assuming payment functionality. | Rationale | Appeals need objective scope/deadline and cannot silently depend on payments. |
| Priority | Critical | Planned Implementation Phase | P7 — Live Competition, Results, and Appeals |
| Primary Actors | Competitor/Guardian/authorized appellant | Supporting Actors | Appeal Registrar; Notification provider |
| Owning Module | Appeals | Related Modules | Results, Registration, Identity, Notifications, Audit |
| Preconditions | Appealable decision/version and open window. | Trigger | Appeal submission/withdrawal. |
| Inputs | Appellant authority, challenged version, grounds/evidence, Idempotency Key, server time, fee-policy status OD-035. | Validation Rules | Eligibility; exact deadline/timezone; authority; evidence; duplicate/idempotency; no payment assumption. |
| Authorization and Scope | Personal/Guardian/authorized Organization scope; public identifier insufficient. | Normal Functional Behavior | Create immutable Appeal submission/version/acknowledgement and place challenged outcome under applicable hold. |
| Alternative Behavior | Record fee-policy dependency/pending state; allow withdrawal through new state. | Failure Behavior | Reject late/ineligible/duplicate-different-payload/unauthorized Appeal with reason and no source mutation. |
| Records Read | Result/decision, actor authority, window/policy, prior Appeals. | Records Created | Appeal/version/acknowledgement/evidence links. |
| Records Updated | Appeal/current status; Result hold if policy. | Records Versioned or Superseded | Appeal submissions/withdrawals. |
| Audit Requirements | Appellant/authority, source version, server time/window, evidence, fee-policy status, outcome. | Domain Events | AppealSubmitted; AppealWithdrawn. |
| Notifications | Appellant, Appeal queue, Result owner. | Privacy and Data Classification | Appeal/evidence restricted; public status minimized. |
| Accessibility and Interaction Requirements | Deadline local/server clarity, accessible evidence upload, receipt and error recovery. | Postconditions | Appeal is acknowledged/pending or safely rejected; original unchanged. |
| Related Business Invariants | INV-016, INV-023, INV-027 | Related P0-B01 Requirements | QMDB-SCR-007, QMDB-ACT-003, QMDB-ACC-003 |
| Verification Method | Boundary-time test; authorization/idempotency test; accessibility test. | Acceptance Criteria | Submission at boundary uses server time; late Appeal cannot alter Result. |

### QMDB-FR-APL-002 — Assign and conduct independent Appeal review

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-APL-002 | Title | Assign and conduct independent Appeal review |
| Requirement Statement | QMDB shall assign an Appeal Reviewer within explicit scope only after conflict review, with preserved requests for evidence, review/hearing sessions, findings, and source Record Versions. | Rationale | Review independence and evidence chain support legitimacy. |
| Priority | Critical | Planned Implementation Phase | P7 — Live Competition, Results, and Appeals |
| Primary Actors | Appeal Reviewer | Supporting Actors | Appeal Registrar; appellant; Judges/record owners |
| Owning Module | Appeals | Related Modules | Access Control, Scoring, Results, Audit, Notifications |
| Preconditions | Open Appeal and approved reviewer-authority policy OD-021. | Trigger | Assign/review/request evidence/hearing. |
| Inputs | Reviewer/scope, conflict, evidence requests/responses, session notes, policy/version. | Validation Rules | Independence/conflict; assignment; evidence custody; minimum access; hearing permission; version stability. |
| Authorization and Scope | Appeal-specific/time-bound; conflicted/original decision actor denied unless providing evidence only. | Normal Functional Behavior | Version assignment/conflict/review, collect evidence, preserve procedural history. |
| Alternative Behavior | Recuse/replace; decide on written review when hearing not permitted/needed. | Failure Behavior | Block review on unresolved conflict/expired assignment or source mutation attempt. |
| Records Read | Appeal/source versions/evidence/assignments/conflicts. | Records Created | Reviewer assignment, conflict/review session/evidence request. |
| Records Updated | Appeal review status. | Records Versioned or Superseded | Assignments/conflicts/review evidence. |
| Audit Requirements | Access, assignment/recusal, evidence requests, session, conflict, actions. | Domain Events | AppealReviewerAssigned; AppealEvidenceRequested; AppealReviewCompleted. |
| Notifications | Appellant/reviewer and evidence providers. | Privacy and Data Classification | Restricted scores/identity/evidence; purpose-limited. |
| Accessibility and Interaction Requirements | Accessible case timeline/evidence, hearing accommodation, clear status. | Postconditions | Independent documented review is ready for decision or safely reassigned. |
| Related Business Invariants | INV-004, INV-016, INV-025 | Related P0-B01 Requirements | QMDB-ACT-003, QMDB-SEC-004, QMDB-AUD-001 |
| Verification Method | Authorization test; workflow test; manual domain/privacy review. | Acceptance Criteria | Conflicted reviewer cannot access decision controls and replacement history is preserved. |

### QMDB-FR-APL-003 — Decide and close an Appeal without rewriting sources

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-APL-003 | Title | Decide and close an Appeal without rewriting sources |
| Requirement Statement | QMDB shall record an Appeal Decision—uphold, reject, withdraw, or authorize recalculation/correction—as a new version that triggers owning-module remedies without directly editing the challenged submission or Result. | Rationale | Appeal outcome and source truth are separate records. |
| Priority | Critical | Planned Implementation Phase | P7 — Live Competition, Results, and Appeals |
| Primary Actors | Appeal Reviewer/decision authority category | Supporting Actors | Chief Judge; Result Correction approver; appellant |
| Owning Module | Appeals | Related Modules | Scoring, Results, Certificates, Records, Audit |
| Preconditions | Review complete, no unresolved reviewer conflict, current Appeal version. | Trigger | Decision/closure command. |
| Inputs | Findings, outcome/remedy, reason/evidence, approvals, Step-Up, source versions. | Validation Rules | Authority OD-021/OD-034; current versions; remedy supported; no direct source edit; required approvals. |
| Authorization and Scope | Decision scope Appeal-specific; remedy executed only by owning module. | Normal Functional Behavior | Append decision/version, emit remedy event, update Appeal state, notify; later close after remedy status. |
| Alternative Behavior | Request more evidence or record withdrawal before decision. | Failure Behavior | Reject stale/self-approved/conflicted/unsupported direct-edit remedy; keep Appeal open/held. |
| Records Read | Appeal/review/source/evidence/approvals. | Records Created | Appeal Decision/remedy instruction. |
| Records Updated | Appeal state/result hold. | Records Versioned or Superseded | Appeal/decision; source only through later module workflow. |
| Audit Requirements | Reviewer/authority/conflict, evidence/reason, source versions, remedy/approvals/outcome. | Domain Events | AppealDecided; AppealRemedyAuthorized; AppealClosed. |
| Notifications | Appellant/Guardians, source owners, certificate/record roles as affected. | Privacy and Data Classification | Decision summary public only by policy; detail restricted. |
| Accessibility and Interaction Requirements | Plain-language outcome/reason/next steps and accessible decision history. | Postconditions | Decision is immutable/current; remedy is queued or Appeal closes, source unchanged until governed action. |
| Related Business Invariants | INV-013, INV-014, INV-016 | Related P0-B01 Requirements | QMDB-SCR-006, QMDB-SCR-007, QMDB-AUD-003 |
| Verification Method | Integration test; authorization test; audit inspection. | Acceptance Criteria | Appeal decision cannot update a Score Sheet row directly. |

### QMDB-FR-CER-001 — Determine Certificate eligibility and recipient snapshot

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-CER-001 | Title | Determine Certificate eligibility and recipient snapshot |
| Requirement Statement | QMDB shall mark Certificate eligibility only from an identified eligible Final Result/Record Version and create a stable minimized recipient snapshot under an approved Certificate Template Version. | Rationale | Provisional/current Profile data must not become certificate truth silently. |
| Priority | Critical | Planned Implementation Phase | P8 — Certificates, Record Passport, and Trusted Archive |
| Primary Actors | Certificate Officer | Supporting Actors | Results/Records Steward; Privacy Officer |
| Owning Module | Certificates | Related Modules | Results, Records, People, Privacy, Audit |
| Preconditions | Final non-held Result/Record and active template policy. | Trigger | Eligibility/generation request. |
| Inputs | Result/Record version, recipient source/snapshot, template, language/branding. | Validation Rules | Final status; no open correction/Appeal; template active/accessibility; minimized fields; current eligibility. |
| Authorization and Scope | Officer record/Edition scoped; cannot change source Result. | Normal Functional Behavior | Evaluate eligibility and create recipient snapshot/generation-ready state. |
| Alternative Behavior | Hold pending correction/template/privacy review. | Failure Behavior | Reject provisional/revoked/ineligible/mismatched source or unsafe data. |
| Records Read | Final Result/Record, Participant Snapshot/Profile, template/privacy. | Records Created | Certificate eligibility and recipient snapshot. |
| Records Updated | Certificate workflow status. | Records Versioned or Superseded | Eligibility/snapshot/template binding. |
| Audit Requirements | Source version, actor, eligibility checks, fields/template, privacy. | Domain Events | CertificateEligibilityConfirmed. |
| Notifications | Certificate queue/recipient when eligible. | Privacy and Data Classification | Recipient snapshot restricted; public certificate minimized. |
| Accessibility and Interaction Requirements | Accessible template and correct Arabic/RTL/mixed content. | Postconditions | Generation-ready snapshot is bound to final source or denied. |
| Related Business Invariants | INV-007, INV-014, INV-026 | Related P0-B01 Requirements | QMDB-CER-001, QMDB-CER-003, QMDB-ACC-002 |
| Verification Method | Integration test; authorization/accessibility test. | Acceptance Criteria | Provisional Result cannot generate a Certificate. |

### QMDB-FR-CER-002 — Generate, sign, issue, and deliver a Certificate

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-CER-002 | Title | Generate, sign, issue, and deliver a Certificate |
| Requirement Statement | QMDB shall generate and issue a Certificate with unique serial, canonical document hash, Digital Signature, signing-key reference, safe QR verification reference, source/version identifiers, and immutable issuance record. | Rationale | Issuance must be independently verifiable and tamper-evident. |
| Priority | Critical | Planned Implementation Phase | P8 — Certificates, Record Passport, and Trusted Archive |
| Primary Actors | Certificate Officer | Supporting Actors | Signing service; Security Operator; Notification provider |
| Owning Module | Certificates | Related Modules | Security Operations, Records, Notifications, Audit |
| Preconditions | Eligible snapshot/source/template and available approved signing key OD-017. | Trigger | Generate/sign/issue/deliver. |
| Inputs | Source/snapshot/template versions, serial request, key ID, canonicalization profile, delivery channel. | Validation Rules | Unique serial; deterministic bytes/hash; active protected key; dual control where policy; QR has no sensitive data/auth. |
| Authorization and Scope | Step-Up and scoped issuance; key material inaccessible to Officer; signing service purpose-bound. | Normal Functional Behavior | Render, hash, sign, persist issue record, publish safe verification projection, deliver. |
| Alternative Behavior | Hold generation if key unavailable; retry delivery without reissuing. | Failure Behavior | Reject hash/signature/key/source mismatch; no unsigned “issued” state. |
| Records Read | Eligibility/source/snapshot/template/key metadata. | Records Created | Certificate Version/serial/hash/signature/issuance/verification projection/delivery intent. |
| Records Updated | Workflow/delivery state. | Records Versioned or Superseded | Certificate/issuance/delivery versions. |
| Audit Requirements | Officer/approvals/step-up, source/template/key IDs, hash/signature outcome, delivery. | Domain Events | CertificateGenerated; CertificateIssued. |
| Notifications | Recipient issuance/delivery; security on signing anomaly. | Privacy and Data Classification | Public verification minimized; delivery/contact restricted; key secret external. |
| Accessibility and Interaction Requirements | Tagged/accessible document where format permits; RTL, readable QR alternative text/reference. | Postconditions | Issued certificate verifies or remains unissued with evidence. |
| Related Business Invariants | INV-015, INV-027, INV-029 | Related P0-B01 Requirements | QMDB-CER-001, QMDB-SEC-002, QMDB-AUD-003 |
| Verification Method | Cryptographic/integration test; authorization/security/accessibility test. | Acceptance Criteria | Altered document hash/signature fails and QR discloses no private data. |

### QMDB-FR-CER-003 — Verify Certificates privately and continuously

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-CER-003 | Title | Verify Certificates privately and continuously |
| Requirement Statement | QMDB shall verify a Certificate’s serial/reference, canonical hash/signature, key status, source/version, and lifecycle state while returning only policy-approved public facts and an explicit current, revoked, superseded, unknown, altered, or unavailable status. | Rationale | Verification must detect forgery without exposing source data. |
| Priority | Critical | Planned Implementation Phase | P8 — Certificates, Record Passport, and Trusted Archive |
| Primary Actors | External Verification Consumer | Supporting Actors | Security/Platform Operator |
| Owning Module | Certificates | Related Modules | Records, Security Operations, Platform Operations, Audit |
| Preconditions | Verification reference/document and service/snapshot. | Trigger | Public verification request. |
| Inputs | Serial/reference, optional document hash/signature, rate/risk context. | Validation Rules | Safe lookup; signature/key/revocation; no authorization from token; rate/enumeration controls. |
| Authorization and Scope | Public minimized single verification; bulk contract separately scoped. | Normal Functional Behavior | Validate cryptography/lifecycle, return status/source summary/time. |
| Alternative Behavior | Serve signed static verification snapshot during approved outage with freshness label. | Failure Behavior | Hash mismatch fails; service unavailable returns unavailable, never valid; no existence leak beyond policy. |
| Records Read | Certificate/source/key-status/projection. | Records Created | Verification/security telemetry as policy. |
| Records Updated | None authoritative. | Records Versioned or Superseded | Verification projection/snapshot. |
| Audit Requirements | Abuse/high-volume/alteration anomalies; minimized ordinary logs. | Domain Events | CertificateVerificationFailed; CertificateForgerySuspected. |
| Notifications | Security alert on anomaly; no routine recipient notice unless policy. | Privacy and Data Classification | Public minimized result; query telemetry restricted. |
| Accessibility and Interaction Requirements | Text status/reason/freshness, keyboard/RTL/mobile, no color-only validity. | Postconditions | Consumer receives truthful current status or unavailable. |
| Related Business Invariants | INV-015, INV-022, INV-027 | Related P0-B01 Requirements | QMDB-CER-001, QMDB-CER-002, QMDB-OPS-003 |
| Verification Method | Cryptographic/security/end-to-end/accessibility test. | Acceptance Criteria | Copied QR on altered document cannot validate the altered hash. |

### QMDB-FR-CER-004 — Reissue, correct, supersede, revoke, and rotate keys

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-CER-004 | Title | Reissue, correct, supersede, revoke, and rotate keys |
| Requirement Statement | QMDB shall reissue, correct, supersede, or revoke a Certificate and rotate signing keys through new versioned records that preserve old artifacts and historical verification status. | Rationale | Lifecycle changes must remain visible after downloads. |
| Priority | Critical | Planned Implementation Phase | P8 — Certificates, Record Passport, and Trusted Archive |
| Primary Actors | Certificate Officer; Security key custodian | Supporting Actors | Result/Records Steward; Auditor |
| Owning Module | Certificates | Related Modules | Results, Records, Security Operations, Audit, Notifications |
| Preconditions | Existing Certificate/source and approved reason/authority/key policy. | Trigger | Source correction, certificate error, revocation, key rotation/compromise. |
| Inputs | Prior certificate/source, reason/evidence, action, approvals/Step-Up, successor/key ID. | Validation Rules | Non-self approval OD-034; source impact; key custody OD-017; no delete; current version. |
| Authorization and Scope | Officer lifecycle authority separated from key custody/source correction. | Normal Functional Behavior | Append action/version, link predecessor/successor, update verification status, notify/reissue where allowed. |
| Alternative Behavior | Bulk review/revocation on compromised key using governed incident plan. | Failure Behavior | Reject silent replacement/stale/self-approved/untraceable action; retain current state. |
| Records Read | Certificate/source/key/status/approvals. | Records Created | Reissue/correction/supersession/revocation/key-impact record. |
| Records Updated | Current certificate/verification status. | Records Versioned or Superseded | All certificate/key-status history. |
| Audit Requirements | Initiator/approver/key custodian, reason/evidence, before/after, affected serials/notifications. | Domain Events | CertificateReissued; CertificateSuperseded; CertificateRevoked; CertificateKeyRotated. |
| Notifications | Recipient/verification consumers as feasible; security/operations. | Privacy and Data Classification | Public lifecycle status; detailed reason/evidence restricted. |
| Accessibility and Interaction Requirements | Verification shows current/former state and replacement path clearly. | Postconditions | Historical artifact remains discoverable with truthful status. |
| Related Business Invariants | INV-014, INV-015, INV-026 | Related P0-B01 Requirements | QMDB-CER-002, QMDB-CER-003, QMDB-AUD-001 |
| Verification Method | Authorization/integration/cryptographic test; audit inspection. | Acceptance Criteria | Revoked downloaded Certificate verifies as revoked and history is not deleted. |

### QMDB-FR-REC-001 — Build durable records and provenance projections

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-REC-001 | Title | Build durable records and provenance projections |
| Requirement Statement | QMDB shall create Competition Records, Competition Record Passports, and Memorizer Passports from identified snapshots/results/evidence with Native Verified, Organizer Verified, Legacy Supported, Legacy Unverified, Disputed, Superseded, or Revoked provenance classifications and controlled visibility. | Rationale | Longitudinal history requires explicit evidence quality and version truth. |
| Priority | High | Planned Implementation Phase | P8 — Certificates, Record Passport, and Trusted Archive |
| Primary Actors | Records Steward; Memorizer | Supporting Actors | Organization verifier; Privacy Officer; Public Visitor |
| Owning Module | Records | Related Modules | Results, Registration, People, Organizations, Privacy, Search, Audit |
| Preconditions | Source record/version/provenance and visibility policy. | Trigger | Finalization/import/classification/correction/publication. |
| Inputs | Source versions, subject, authority/method/date/scope/status/evidence, visibility, predecessor. | Validation Rules | Classification-specific evidence; source priority/conflict; no generic boolean; Consent/privacy/minor; no hard delete. |
| Authorization and Scope | Steward/classifier scoped; public projection minimized; Person can challenge but not self-attest official truth. | Normal Functional Behavior | Append record/classification/version, build Passports/public/restricted projections, link evidence. |
| Alternative Behavior | Mark disputed/restricted/pending evidence without erasing prior status. | Failure Behavior | Reject unsupported stronger classification, unsafe public fields, or source overwrite. |
| Records Read | Source snapshots/results/evidence/organizations/privacy. | Records Created | Competition Record/version/provenance/classification/projections. |
| Records Updated | Current classification/visibility/passport index. | Records Versioned or Superseded | Records/classifications/corrections/projections. |
| Audit Requirements | Classifier/authority/method/evidence/source, visibility, change/reason. | Domain Events | CompetitionRecordCreated; RecordClassificationChanged; RecordDisputed. |
| Notifications | Subject/organization and public index on governed change. | Privacy and Data Classification | Official detail restricted; public projection minimized. |
| Accessibility and Interaction Requirements | Explain classification/source/status without specialist knowledge; RTL/low bandwidth. | Postconditions | Durable record is source/version/provenance traceable. |
| Related Business Invariants | INV-007, INV-014, INV-026, INV-027 | Related P0-B01 Requirements | QMDB-CER-003, QMDB-CER-004, QMDB-AUD-003 |
| Verification Method | Integration/authorization test; manual domain/privacy review. | Acceptance Criteria | Legacy Unverified cannot display as Native Verified and correction preserves predecessor. |

### QMDB-FR-IMP-001 — Import and reconcile legacy records

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-IMP-001 | Title | Import and reconcile legacy records |
| Requirement Statement | QMDB shall process each legacy import through a checksummed Import Batch with source Organization/file, mapping version, validation, duplicate/conflict review, provenance classification, approval/rejection, partial outcome, pre-publication rollback, and post-publication correction history. | Rationale | Legacy data is untrusted input and cannot overwrite native truth. |
| Priority | High | Planned Implementation Phase | P8 — Certificates, Record Passport, and Trusted Archive |
| Primary Actors | Records Importer; Records Reviewer | Supporting Actors | Source Organization; Security/Privacy Officer |
| Owning Module | Records | Related Modules | Organizations, People, Integrations, Audit, Security Operations |
| Preconditions | Authorized source/import, isolated batch, approved mapping/classification authority OD-019/OD-034. | Trigger | Upload/validate/review/publish/rollback/reconcile batch. |
| Inputs | File/hash/source, mapping, records/evidence, reviewer decisions. | Validation Rules | Actual type/size/malware; checksum; schema; tenant; duplicates/native conflicts; evidence/classification; no executable content. |
| Authorization and Scope | Import/review/publish separated; Step-Up/approval for publication; source Organization cannot self-upgrade classification. | Normal Functional Behavior | Quarantine, parse/validate, stage, classify/review, publish accepted records idempotently, reconcile. |
| Alternative Behavior | Partial import with item outcomes; rollback all staged/unpublished writes; post-publication correction creates versions. | Failure Behavior | Reject/quarantine malformed/malicious/hash-changed/unsupported/conflicting data; never overwrite Native Verified record. |
| Records Read | Source Organization, existing Persons/Records, mappings/policy. | Records Created | Import Batch/items/staging/validation/classification/reconciliation. |
| Records Updated | Batch/item states; current record projections after approval. | Records Versioned or Superseded | Imported Records and post-publication corrections. |
| Audit Requirements | File/hash/source, mapping/tool, item decisions, reviewers/approvals, duplicates/conflicts/rollback. | Domain Events | LegacyImportValidated; LegacyImportPublished; LegacyImportRejected; LegacyImportReconciled. |
| Notifications | Importer/reviewer/source and affected subjects per policy. | Privacy and Data Classification | Imported personal/evidence data restricted; malware quarantined. |
| Accessibility and Interaction Requirements | Accessible item/error summary and exportable reconciliation report. | Postconditions | Each item has explicit outcome/provenance; native records unchanged unless separately corrected. |
| Related Business Invariants | INV-003, INV-014, INV-023, INV-026, INV-029 | Related P0-B01 Requirements | QMDB-CER-003, QMDB-CER-004, QMDB-SEC-005 |
| Verification Method | Integration/security/idempotency test; manual domain/privacy review. | Acceptance Criteria | Unsupported legacy evidence stays Legacy Unverified and native conflict is held, not overwritten. |

## Related documents

- [Functional requirements index](../P0-B02-functional-requirements-index.md)
- [Scheduling, judging, and scoring](06-scheduling-judging-and-scoring.md)
- [Workflows and state machines](../P0-B02-workflows-and-state-machines.md)
- [P0-B01 baseline requirements](../P0-B01-requirements.md)
