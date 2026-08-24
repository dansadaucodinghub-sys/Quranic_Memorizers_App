# Media, Recitation Clips, and Moderation Functional Requirements

**Document ID:** QMDB-P0-B02-FR-08  
**Version:** 1.0.0  
**Status:** Approved batch baseline  
**Baseline:** QMDB-BL-001  
**Owning phase:** P0; implementation phases P9 and P10

## Purpose and scope

This specification governs media from private upload through evidence retention and approved derivatives, child-aware Recitation Clips and social interactions, and accountable moderation. Authoritative competition records remain separate from social projections.

## Common controls

- Media is private and quarantined by default; file extensions are never trusted as type evidence.
- Evidence Masters remain private. Public delivery uses an approved derivative and short-lived authorization where the object is not public.
- Consent withdrawal stops future optional publication, subject to a recorded official-evidence or hold assessment.
- Minor profiles and content use the most protective valid policy; no unrestricted direct messaging or unmoderated user livestreaming is authorized.
- Media and social workloads must not consume capacity reserved for authoritative scoring.

### QMDB-FR-MED-001 — Authorize and quarantine media uploads

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-MED-001 | Title | Authorize and quarantine media uploads |
| Requirement Statement | The platform shall issue short-lived, purpose-bound upload authorization only to an actor permitted for the target workspace and record. | Rationale | Prevents confused-deputy uploads and premature exposure. |
| Priority | Critical | Planned Implementation Phase | P9 — Audio and Video Evidence |
| Primary Actors | Authorized uploader | Supporting Actors | Media service; workspace policy |
| Owning Module | Media and Evidence | Related Modules | Identity and Access; Platform Operations |
| Preconditions | Authenticated actor; active scoped membership; declared purpose. | Trigger | Actor requests an upload slot. |
| Inputs | Workspace, target record, declared purpose, expected type and size. | Validation Rules | Reject inactive scope, unsupported purpose, excessive quota, unsafe filename, and expired authorization. |
| Authorization and Scope | Tenant and record scope are server-derived; public actors cannot select private targets. | Normal Functional Behavior | Create a private upload intent and return a short-lived constrained upload permission. |
| Alternative Behavior | An authorized retry receives a fresh permission without reusing the expired credential. | Failure Behavior | Deny safely; reveal no storage path or cross-workspace information. |
| Records Read | Membership, quota, target record, policy. | Records Created | Upload intent and correlation record. |
| Records Updated | Quota reservation. | Records Versioned or Superseded | Superseded permissions remain expired and auditable. |
| Audit Requirements | Actor, purpose, scope, target, limit, device, request correlation. | Domain Events | QMDB-EVT-034 |
| Notifications | Uploader and security operations on anomalous denial. | Privacy and Data Classification | Restricted until processing; child-linked media is Sensitive Personal Data. |
| Accessibility and Interaction Requirements | Keyboard-operable picker, explicit limits, progress text, resumable-error guidance. | Postconditions | A private, expiring upload intent exists or no state changes. |
| Related Business Invariants | INV-001, INV-005, INV-016, INV-019 | Related P0-B01 Requirements | QMDB-SEC-001, QMDB-SEC-003, QMDB-DATA-006 |
| Verification Method | API authorization, tenant-isolation, expiry, and quota tests. | Acceptance Criteria | An unauthorized, expired, or cross-workspace upload cannot create or expose media. |

### QMDB-FR-MED-002 — Validate, quarantine, and process media

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-MED-002 | Title | Validate, quarantine, and process media |
| Requirement Statement | The platform shall keep every uploaded media object in private quarantine until content-type detection, limit checks, malware scanning, metadata handling, hashing, and required processing succeed. | Rationale | Prevents malicious or malformed content from reaching users. |
| Priority | Critical | Planned Implementation Phase | P9 — Audio and Video Evidence |
| Primary Actors | Media worker | Supporting Actors | Uploader; moderator; security operator |
| Owning Module | Media and Evidence | Related Modules | Security Operations; Moderation |
| Preconditions | A committed upload object and matching upload intent exist. | Trigger | Storage completion is reported. |
| Inputs | Bytes, detected type, size, duration, codec, metadata, content hash. | Validation Rules | Trust detected bytes rather than extension; enforce policy limits; strip nonessential metadata; reject malware and malformed content. |
| Authorization and Scope | Workers use least-privilege object access; quarantine is never publicly addressable. | Normal Functional Behavior | Record inspection results, hash, sanitized metadata, derivatives, and processing status. |
| Alternative Behavior | Retry transient processing idempotently; route uncertain content to manual review. | Failure Behavior | Keep quarantined; create a security event for threats; never publish partial derivatives. |
| Records Read | Upload intent, private object, processing policy. | Records Created | Immutable inspection and processing attempts; derivative records. |
| Records Updated | Media status and retry counter. | Records Versioned or Superseded | Each reprocessing attempt is retained; approved derivative versions supersede earlier ones. |
| Audit Requirements | Detection evidence, scanner version, hash, worker, outcome, reason. | Domain Events | QMDB-EVT-035, QMDB-EVT-036, QMDB-EVT-037, QMDB-EVT-038 |
| Notifications | Uploader on terminal outcome; security team on threat. | Privacy and Data Classification | Restricted evidence; extracted location/device metadata is removed unless explicitly required. |
| Accessibility and Interaction Requirements | Nonvisual status, progress announcement, retry state, caption workflow entry point. | Postconditions | Media is SAFE_PRIVATE, REJECTED, or MANUAL_REVIEW with evidence. |
| Related Business Invariants | INV-005, INV-016, INV-019, INV-023 | Related P0-B01 Requirements | QMDB-SEC-003, QMDB-SEC-005, QMDB-DATA-006 |
| Verification Method | Malware fixtures, type-spoof tests, processing integration and failure tests. | Acceptance Criteria | Spoofed or malicious media remains inaccessible and processing retries do not duplicate derivatives. |

### QMDB-FR-MED-003 — Govern media access and derivatives

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-MED-003 | Title | Govern media access and derivatives |
| Requirement Statement | The platform shall serve media only through policy-checked, short-lived access to the least-sensitive approved derivative. | Rationale | Protects private originals, evidence masters, minors, and restricted records. |
| Priority | Critical | Planned Implementation Phase | P9 — Audio and Video Evidence |
| Primary Actors | Authorized viewer | Supporting Actors | Media service; consent service |
| Owning Module | Media and Evidence | Related Modules | People and Guardianship; Competition Records |
| Preconditions | Approved media exists and viewer context is known. | Trigger | Viewer requests media. |
| Inputs | Media ID, requested purpose, actor or public context. | Validation Rules | Re-evaluate visibility, consent, hold, age, publication status, record dispute, and derivative class on every authorization. |
| Authorization and Scope | Evidence Masters and private originals are never public; signed access is bound to object, purpose, and expiry. | Normal Functional Behavior | Return an approved derivative or a non-enumerating denial. |
| Alternative Behavior | Provide lower-bandwidth derivative when allowed; use accessible fallback when media cannot play. | Failure Behavior | Deny without revealing object existence; revoke future access after consent or policy change. |
| Records Read | Media, derivative, consent, hold, publication, actor scope. | Records Created | Access grant where policy requires tracking. |
| Records Updated | Access count and last-access security telemetry only. | Records Versioned or Superseded | Revoked grants expire; historical publication decisions remain recorded. |
| Audit Requirements | Actor/public context, media, derivative, decision basis, expiry. | Domain Events | QMDB-EVT-039 |
| Notifications | Owner/guardian when material visibility changes. | Privacy and Data Classification | Private original Restricted; public derivative Public only after approval; minors remain Sensitive. |
| Accessibility and Interaction Requirements | Captions/transcripts where applicable, player keyboard support, no autoplay, low-bandwidth alternative. | Postconditions | Only the authorized derivative is returned for a bounded duration. |
| Related Business Invariants | INV-005, INV-013, INV-016, INV-019 | Related P0-B01 Requirements | QMDB-SEC-003, QMDB-PRIV-003, QMDB-UX-002 |
| Verification Method | Access-control, URL leakage, consent withdrawal, and cache tests. | Acceptance Criteria | A public URL cannot retrieve a private original or an unapproved minor derivative. |

### QMDB-FR-MED-004 — Publish, unpublish, retain, and hold media

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-MED-004 | Title | Publish, unpublish, retain, and hold media |
| Requirement Statement | The platform shall require verified ownership, valid consent, publication approval, retention status, and preservation of evidence subject to a hold before publishing media. | Rationale | Balances publication with consent and official-evidence obligations. |
| Priority | High | Planned Implementation Phase | P9 — Audio and Video Evidence |
| Primary Actors | Media publisher | Supporting Actors | Owner; guardian; moderator; records officer |
| Owning Module | Media and Evidence | Related Modules | Moderation; People and Guardianship; Competition Records |
| Preconditions | Media is safe, ownership resolved, and publication target is valid. | Trigger | Authorized actor requests publication or status change. |
| Inputs | Media, approved derivative, visibility, purpose, consent, reason, hold status. | Validation Rules | Validate consent at decision time; prohibit deletion under hold; unpublish on withdrawal unless a documented retention exception applies. |
| Authorization and Scope | Publisher needs scoped capability; restricted-minor release requires separate approval, step-up authentication, and no self-approval (OD-034). | Normal Functional Behavior | Publish only the approved derivative; preserve original privately; apply retention and archival policy. |
| Alternative Behavior | Unpublish or restrict future access while preserving required official evidence and publication history. | Failure Behavior | Hold or dispute blocks destructive deletion; ambiguous authority defaults to private. |
| Records Read | Media, ownership, consent, moderation, hold, result dispute. | Records Created | Publication decision, retention action, hold link. |
| Records Updated | Visibility and retention status. | Records Versioned or Superseded | Corrections create a new decision version; former public status is historically discoverable to authorized reviewers. |
| Audit Requirements | Decision, reason, approver, consent version, hold, affected URLs. | Domain Events | QMDB-EVT-039, QMDB-EVT-040 |
| Notifications | Owner, guardian, moderators, and affected official-record custodian. | Privacy and Data Classification | Public derivative classification changes only by approved decision; evidence remains Restricted. |
| Accessibility and Interaction Requirements | Clear status labels, confirmation for irreversible effects, non-color state cues. | Postconditions | Media is published, restricted, archived, or held with provenance. |
| Related Business Invariants | INV-005, INV-013, INV-019, INV-023 | Related P0-B01 Requirements | QMDB-PRIV-003, QMDB-SEC-005, QMDB-GOV-003 |
| Verification Method | Authorization, consent-race, hold, cache-purge, and audit tests. | Acceptance Criteria | Consent withdrawal prevents new public access while required evidence remains privately preserved. |

### QMDB-FR-MED-005 — Recover media processing and capacity failures

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-MED-005 | Title | Recover media processing and capacity failures |
| Requirement Statement | The platform shall isolate media-processing and social-load failures from authoritative competition and scoring services. | Rationale | Protects the competition core under overload or subsystem failure. |
| Priority | Critical | Planned Implementation Phase | P9 — Audio and Video Evidence |
| Primary Actors | Platform operator | Supporting Actors | Media worker; venue operator |
| Owning Module | Media and Evidence | Related Modules | Platform Operations; Scoring |
| Preconditions | Dependency health and workload telemetry are available. | Trigger | A timeout, queue overload, storage failure, or capacity threshold occurs. |
| Inputs | Job, retry count, health signal, media correlation, service budget. | Validation Rules | Use bounded exponential retry, circuit breaking, idempotent jobs, dead-letter review, and capacity shedding. |
| Authorization and Scope | Operations access is audited; recovery cannot bypass media safety gates. | Normal Functional Behavior | Pause nonessential derivatives and social delivery while official score intake remains available. |
| Alternative Behavior | Offer retry and static/status information; resume from recorded checkpoint. | Failure Behavior | Never mark unprocessed media safe; never consume scoring capacity reserved by policy. |
| Records Read | Processing attempts, queue health, service budget. | Records Created | Dead-letter item and incident when thresholds are reached. |
| Records Updated | Job state, circuit state, service health. | Records Versioned or Superseded | Recovery attempts and operator interventions are retained. |
| Audit Requirements | Failure, dependency, retry, decision, incident correlation. | Domain Events | QMDB-EVT-044 |
| Notifications | Operators and uploader for terminal processing failure. | Privacy and Data Classification | Telemetry minimizes personal payload; logs do not contain media bytes or private URLs. |
| Accessibility and Interaction Requirements | Status is textually announced; low-bandwidth and no-media paths remain usable. | Postconditions | Failure is contained and recoverable without score loss. |
| Related Business Invariants | INV-015, INV-016, INV-023, INV-027 | Related P0-B01 Requirements | QMDB-OPS-002, QMDB-SEC-004, QMDB-SEC-005 |
| Verification Method | Load, chaos, queue, and scoring-isolation tests. | Acceptance Criteria | Media overload degrades media features without blocking official score submission. |

### QMDB-FR-SOC-001 — Create and publish Recitation Clips

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-SOC-001 | Title | Create and publish Recitation Clips |
| Requirement Statement | The platform shall publish a Recitation Clip only after its media, caption, passage tags, record links, visibility, consent, and moderation state satisfy current policy. | Rationale | Provides a governed recitation-sharing projection without weakening official records. |
| Priority | High | Planned Implementation Phase | P10 — Recitation Clips and Community Safety |
| Primary Actors | Reciter | Supporting Actors | Guardian; moderator; media worker |
| Owning Module | Community and Recitation Clips | Related Modules | Media and Evidence; People and Guardianship; Qur'an Reference Governance |
| Preconditions | Creator has an eligible profile and safe processed media. | Trigger | Creator saves a draft or requests publication. |
| Inputs | Media, caption, Qur'an passage tag, record link, cover, visibility. | Validation Rules | Validate release-based passage reference, caption limits, ownership, visibility, moderation, and link authorization. |
| Authorization and Scope | Creator controls own draft; publication is capability-checked; linked official data remains read-only. | Normal Functional Behavior | Save draft, process dependencies, approve where required, and publish a projection. |
| Alternative Behavior | Limit distribution, reject with reason, remove, restore after appeal, or detach an invalid record link. | Failure Behavior | Remain draft/restricted on missing consent or uncertain policy; do not fabricate official association. |
| Records Read | Profile, media, Qur'an release, consent, competition record. | Records Created | Clip draft, link snapshot, publication decision. |
| Records Updated | Clip state, derivative, visibility, metrics projection. | Records Versioned or Superseded | Edits version publication-relevant fields; restored content records prior removal. |
| Audit Requirements | Creator, approver, consent, media hash, passage reference, visibility, moderation basis. | Domain Events | QMDB-EVT-038 |
| Notifications | Creator, guardian, followers within privacy policy. | Privacy and Data Classification | Caption/profile may be Personal; minor content is Sensitive and limited by default. |
| Accessibility and Interaction Requirements | Accessible editor, captions, direction-aware Arabic tags, progress and validation summary. | Postconditions | Clip is draft, limited, published, rejected, removed, or restored with provenance. |
| Related Business Invariants | INV-002, INV-005, INV-012, INV-013, INV-017 | Related P0-B01 Requirements | QMDB-DATA-004, QMDB-PRIV-003, QMDB-UX-001 |
| Verification Method | Workflow, privacy, guardian, reference-link and moderation tests. | Acceptance Criteria | A clip cannot become public with unsafe media, invalid passage reference, or missing required approval. |

### QMDB-FR-SOC-002 — Enforce child-aware Clip and interaction controls

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-SOC-002 | Title | Enforce child-aware Clip and interaction controls |
| Requirement Statement | The platform shall apply private-or-limited defaults, guardian oversight where required, location minimization, restricted comments, and no unrestricted direct messaging to minor accounts and content. | Rationale | Makes child safety a functional default rather than an optional setting. |
| Priority | Critical | Planned Implementation Phase | P10 — Recitation Clips and Community Safety |
| Primary Actors | Minor reciter | Supporting Actors | Guardian; moderator; privacy officer |
| Owning Module | Community and Recitation Clips | Related Modules | People and Guardianship; Moderation; Privacy |
| Preconditions | Age/minor status and effective guardian/consent records are resolved. | Trigger | A minor creates, publishes, or receives interaction on a Clip. |
| Inputs | Age status, guardian authority, consent, visibility, interaction policy. | Validation Rules | Prevent public precise location/contact disclosure, unrestricted messaging, unapproved publication, and policy-prohibited interactions. |
| Authorization and Scope | Server policy overrides client settings; guardian access is relationship- and scope-bound; emergency visibility restriction is immediate. | Normal Functional Behavior | Default to private/limited, expose report/block controls, and provide guardian publication history. |
| Alternative Behavior | Adult-age transition triggers review rather than automatic broadening; disputed guardianship freezes consent-dependent change. | Failure Behavior | Restrict visibility and interactions on uncertainty; alert child-safety moderation on unsafe patterns. |
| Records Read | Person, guardian, consent, content, blocks, interaction policy. | Records Created | Child-safety policy evaluation and required approval request. |
| Records Updated | Visibility, comment/follower controls, emergency restriction. | Records Versioned or Superseded | Policy changes and approval history remain versioned. |
| Audit Requirements | Policy version, age basis, guardian, approval, restriction reason. | Domain Events | QMDB-EVT-039, QMDB-EVT-040 |
| Notifications | Guardian, creator, child-safety moderators for urgent restriction. | Privacy and Data Classification | Sensitive Personal Data; no public contact or precise location. |
| Accessibility and Interaction Requirements | Plain safety explanations, accessible reporting, no interaction dependent solely on hover or color. | Postconditions | Minor content and interactions remain within the most protective valid policy. |
| Related Business Invariants | INV-005, INV-010, INV-013, INV-019 | Related P0-B01 Requirements | QMDB-PRIV-003, QMDB-GOV-003, QMDB-SEC-003 |
| Verification Method | Age-boundary, guardianship dispute, privacy, block, and publication tests. | Acceptance Criteria | A minor cannot bypass guardian or safety controls by manipulating visibility or client requests. |

### QMDB-FR-SOC-003 — Govern social interactions

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-SOC-003 | Title | Govern social interactions |
| Requirement Statement | The platform shall authorize, deduplicate, rate-limit, and moderate follows, reactions, bookmarks, comments, controlled shares, reports, blocks, and mutes according to visibility and relationship policy. | Rationale | Supports community participation without exposing private records or enabling abuse. |
| Priority | High | Planned Implementation Phase | P10 — Recitation Clips and Community Safety |
| Primary Actors | Community member | Supporting Actors | Content owner; moderator |
| Owning Module | Community and Recitation Clips | Related Modules | Moderation; Identity and Access |
| Preconditions | Actor and target content are eligible for the requested interaction. | Trigger | Actor performs an interaction. |
| Inputs | Actor, content, interaction type, text where applicable, idempotency key. | Validation Rules | Enforce block/mute, rate, comment, visibility, minor, text safety, and duplicate constraints. |
| Authorization and Scope | No interaction crosses a block or hidden workspace boundary; report identity is protected from reported users. | Normal Functional Behavior | Apply reversible interaction state; queue comments or reports for moderation as required. |
| Alternative Behavior | Remove own comment where allowed; moderator may restrict; controlled share exposes only approved public derivative. | Failure Behavior | Reject non-enumeratingly, retain abuse evidence, and throttle floods. |
| Records Read | Actor relationship, content visibility, existing interaction, block/mute. | Records Created | Interaction or report record. |
| Records Updated | Counters and feed projections asynchronously. | Records Versioned or Superseded | Removed/restored comments preserve moderation history; counters are rebuildable projections. |
| Audit Requirements | Actor, target, type, moderation action, correlation, rate decision. | Domain Events | QMDB-EVT-039 |
| Notifications | Content owner for allowed interactions; moderators for reports; security for flooding. | Privacy and Data Classification | Bookmarks, blocks, reports and reporter identity are Restricted. |
| Accessibility and Interaction Requirements | Keyboard actions, accessible names, confirmation for block/report, live counter changes not essential. | Postconditions | Authorized interaction state is recorded once and projections update asynchronously. |
| Related Business Invariants | INV-001, INV-005, INV-015, INV-017, INV-027 | Related P0-B01 Requirements | QMDB-SEC-003, QMDB-SEC-004, QMDB-UX-002 |
| Verification Method | Block, visibility, rate-limit, idempotency and overload tests. | Acceptance Criteria | A blocked actor cannot interact where policy prohibits and report flooding cannot impair scoring. |

### QMDB-FR-MOD-001 — Create and triage moderation cases

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-MOD-001 | Title | Create and triage moderation cases |
| Requirement Statement | The platform shall convert eligible content reports and automated safety signals into deduplicated, prioritized moderation cases with protected reporter evidence. | Rationale | Creates an accountable safety workflow without exposing reporters. |
| Priority | Critical | Planned Implementation Phase | P10 — Recitation Clips and Community Safety |
| Primary Actors | Reporter or safety detector | Supporting Actors | Moderator; child-safety or security specialist |
| Owning Module | Moderation and Safety | Related Modules | Community and Recitation Clips; Media and Evidence; Security Operations; People and Guardianship |
| Preconditions | Reportable content or safety signal exists. | Trigger | Report is submitted or detector raises a signal. |
| Inputs | Category, content, evidence, reporter context, urgency. | Validation Rules | Validate category and evidence; deduplicate related reports; prioritize child-safety/security indicators; rate-limit abuse. |
| Authorization and Scope | Reporter identity is hidden from subject; conflicted moderators cannot decide; workspace/global scope is explicit. | Normal Functional Behavior | Acknowledge report, create or associate a case, preserve evidence, assign priority and reviewer. |
| Alternative Behavior | Escalate urgent cases, temporarily restrict content, or reject abusive reports with protected reasoning. | Failure Behavior | If queues overload, retain intake, apply risk-based restriction, and shed nonessential views. |
| Records Read | Content, account, reports, blocks, previous cases. | Records Created | Report, case, evidence snapshot, assignment. |
| Records Updated | Content temporary restriction and case priority. | Records Versioned or Superseded | Case events append; source evidence is held according to policy. |
| Audit Requirements | Reporter protection, signal, priority, assignment, conflict checks. | Domain Events | QMDB-EVT-039 |
| Notifications | Reporter acknowledgment; assigned team; urgent security/child-safety escalation. | Privacy and Data Classification | Reporter and child-safety evidence Highly Restricted. |
| Accessibility and Interaction Requirements | Accessible reporting form, immediate receipt, safe-exit behavior, no mandatory media playback. | Postconditions | A protected, prioritized case exists or the report has a documented safe rejection. |
| Related Business Invariants | INV-005, INV-019, INV-023, INV-028 | Related P0-B01 Requirements | QMDB-GOV-003, QMDB-SEC-005, QMDB-PRIV-003 |
| Verification Method | Integration test; authorization test; security test; manual privacy review. | Acceptance Criteria | Urgent safety reports remain actionable under overload without revealing the reporter. |

### QMDB-FR-MOD-002 — Decide, notify, appeal, and restore moderation action

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-MOD-002 | Title | Decide, notify, appeal, and restore moderation action |
| Requirement Statement | The platform shall require a reasoned, scoped, auditable moderation decision and an independent appeal path for eligible actions. | Rationale | Prevents silent or arbitrary restriction and preserves reversible evidence. |
| Priority | Critical | Planned Implementation Phase | P10 — Recitation Clips and Community Safety |
| Primary Actors | Moderator | Supporting Actors | Content owner; appeal reviewer; security or child-safety specialist |
| Owning Module | Moderation and Safety | Related Modules | Community and Recitation Clips; Media and Evidence; Security Operations; Appeals |
| Preconditions | Assigned case, preserved evidence, and conflict-cleared reviewer exist. | Trigger | Reviewer records a decision or an eligible subject appeals. |
| Inputs | Case, evidence, policy version, action, duration, reason, appeal. | Validation Rules | Validate action proportionality, scope, duration, approval for permanent action, conflict separation, and appeal eligibility. |
| Authorization and Scope | Reviewer needs case scope; self-review of own action is prohibited; permanent/high-risk action may require approval (OD-034). | Normal Functional Behavior | Apply limitation/removal/account restriction, notify subject safely, and open appeal window when applicable. |
| Alternative Behavior | Restore, narrow, extend, escalate, or uphold after independent review. | Failure Behavior | Keep protective restriction during material safety uncertainty; do not delete evidence; notify operations on failure. |
| Records Read | Case, content/account state, policy, evidence, prior decisions. | Records Created | Decision, notice, appeal, review decision. |
| Records Updated | Content/account restriction and visibility. | Records Versioned or Superseded | All decisions append; restoration supersedes but does not erase former action. |
| Audit Requirements | Reviewer, effective role, scope, reason, evidence, policy, approval, timestamps. | Domain Events | QMDB-EVT-040 |
| Notifications | Subject unless unsafe; reporter only as policy permits; operations on escalation. | Privacy and Data Classification | Case/evidence Highly Restricted; notice minimizes reporter and child information. |
| Accessibility and Interaction Requirements | Decision communicated in plain language; keyboard appeal; accessible deadlines and error recovery. | Postconditions | Action is enforced and appealable where applicable; restoration is traceable. |
| Related Business Invariants | INV-005, INV-014, INV-019, INV-023, INV-028 | Related P0-B01 Requirements | QMDB-GOV-003, QMDB-SEC-005, QMDB-UX-001 |
| Verification Method | Authorization test; integration test; security test; accessibility test. | Acceptance Criteria | A conflicted moderator cannot decide and restoration preserves the original action history. |

## Related documents

- [Functional requirements index](../P0-B02-functional-requirements-index.md)
- [People, profiles, and guardianship](02-people-profiles-and-guardianship.md)
- [Event and notification catalog](../P0-B02-event-and-notification-catalog.md)
- [P0-B01 baseline requirements](../P0-B01-requirements.md)
