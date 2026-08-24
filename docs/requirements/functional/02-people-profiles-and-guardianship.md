# People, Profiles, and Guardianship Functional Requirements

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B02 |
| Document Title | People, Profiles, and Guardianship Functional Requirements |
| Document Version | 1.0.0 |
| Status | Baselined; implementation not started |
| Document Owner Role | People and Child-Safety Domain Governance |
| Last Updated | 2026-08-24 |
| Related Documents | [Functional index](../P0-B02-functional-requirements-index.md); [glossary](../../domain/domain-glossary.md); [actors](../../domain/stakeholders-and-actors.md); [open decisions](../../project/open-decisions.md) |

## Purpose

Specify durable Person identity, contextual Profiles, duplicate/merge handling, Guardian Relationships, versioned Consent, Minor transitions, and conservative visibility.

## Scope

People and Guardianship behavior remains distinct from authentication, Registration, Participant Snapshots, and official records. Minor/Guardian legal-policy interpretation depends on OD-011/OD-012; merge authority depends on OD-032.

## Functional requirements

### QMDB-FR-PPL-001 — Create and associate a durable Person

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-PPL-001 | Title | Create and associate a durable Person |
| Requirement Statement | QMDB shall create one durable Person for a real human and associate zero or more authorized User Accounts without making Account existence a prerequisite for participation history. | Rationale | Person and Account lifecycles differ. |
| Priority | Critical | Planned Implementation Phase | P3 — Nigerian Geography, Organizations, and People |
| Primary Actors | Registered Individual; Competition Registrar | Supporting Actors | Data Steward; Guardian |
| Owning Module | People | Related Modules | Identity, Registration, Audit |
| Preconditions | Defined purpose and completed duplicate search. | Trigger | Profile onboarding, Registration, legacy linkage, or Account association. |
| Inputs | Names, aliases, limited identity attributes/evidence status, Account reference, source/provenance. | Validation Rules | Required identity fields; normalization; evidence classification; no automatic high-risk match; one Account association policy per OD-032. |
| Authorization and Scope | Self-link requires authenticated proof; Registrar/Guardian acts only within assigned purpose; merge is separate. | Normal Functional Behavior | Reuse supported match or create Person, record provenance, associate Account without copying credentials. |
| Alternative Behavior | Create a Person without Account; hold an ambiguous match for review. | Failure Behavior | Reject unauthorized link and avoid exposing possible matches. |
| Records Read | Person candidates, Account linkage, evidence/provenance. | Records Created | Person; Account-to-Person association; review case if ambiguous. |
| Records Updated | Person association metadata. | Records Versioned or Superseded | Identity evidence/association history. |
| Audit Requirements | Creator, purpose, source, candidate decision, association and evidence classification. | Domain Events | PersonCreated; AccountAssociatedWithPerson. |
| Notifications | Account holder when a new association is established or challenged. | Privacy and Data Classification | Identity data restricted; public display is separate. |
| Accessibility and Interaction Requirements | Explain Person/Account distinction and duplicate-review status in plain language. | Postconditions | One durable Person anchor exists or request awaits review. |
| Related Business Invariants | INV-003, INV-004 | Related P0-B01 Requirements | QMDB-IDN-001, QMDB-IDN-002 |
| Verification Method | Unit test; integration test; authorization test; manual privacy review. | Acceptance Criteria | The same supported Person can join another Edition without a new Person record. |

### QMDB-FR-PPL-002 — Manage identity evidence, names, display, and duplicates

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-PPL-002 | Title | Manage identity evidence, names, display, and duplicates |
| Requirement Statement | QMDB shall version alternative names, public display name, identity-evidence classification, privacy state, and potential-duplicate indicators without exposing restricted evidence publicly. | Rationale | Names and evidence change while provenance must persist. |
| Priority | High | Planned Implementation Phase | P3 — Nigerian Geography, Organizations, and People |
| Primary Actors | Registered Individual; Data Steward | Supporting Actors | Privacy Officer; Registrar |
| Owning Module | People | Related Modules | Privacy, Search, Records, Audit |
| Preconditions | Existing Person or supported creation workflow. | Trigger | Profile edit, evidence review, duplicate detection, privacy change. |
| Inputs | Name/display values, evidence subject/method/date/status/provenance, visibility. | Validation Rules | Script/length; alias validity; evidence-specific status; restricted fields never public; uniqueness is not assumed from name. |
| Authorization and Scope | Self-edit limited to current mutable Profile fields; evidence classification and duplicate review require scoped steward. | Normal Functional Behavior | Create new Profile/evidence version and refresh policy-approved projection. |
| Alternative Behavior | Hold conflicting evidence or mark a possible duplicate without merging. | Failure Behavior | Reject generic `verified` flag, unsafe visibility, or unsupported evidence claim. |
| Records Read | Person/Profile versions, evidence, privacy policy, duplicates. | Records Created | Evidence review/duplicate indicator. |
| Records Updated | Current Profile/public display pointer. | Records Versioned or Superseded | Names, evidence classification, Profile and Public Profile. |
| Audit Requirements | Actor, field category, before/after version, evidence decision, visibility effect. | Domain Events | PersonProfileChanged; IdentityEvidenceClassified; DuplicatePersonDetected. |
| Notifications | Person/Account on material evidence or visibility change. | Privacy and Data Classification | Identity evidence highly restricted; display name public only by policy. |
| Accessibility and Interaction Requirements | Arabic/Latin name support, pronunciation-independent labels, explicit public preview. | Postconditions | Current data and prior versions remain distinguishable. |
| Related Business Invariants | INV-003, INV-007, INV-027 | Related P0-B01 Requirements | QMDB-IDN-002, QMDB-PRI-001, QMDB-CER-004 |
| Verification Method | Integration test; authorization test; manual privacy review. | Acceptance Criteria | A Profile may change name without rewriting a Participant Snapshot. |

### QMDB-FR-PPL-003 — Maintain contextual Profiles and affiliations

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-PPL-003 | Title | Maintain contextual Profiles and affiliations |
| Requirement Statement | QMDB shall maintain Memorizer, Reciter, Competitor, Judge, Coach, Teacher, and Public Profile contexts and organization/geography affiliations as separate scoped projections of one Person. | Rationale | Context does not redefine durable identity or authority. |
| Priority | High | Planned Implementation Phase | P3 — Nigerian Geography, Organizations, and People |
| Primary Actors | Person; Registrar; Organization Administrator | Supporting Actors | Data Steward; Privacy Officer |
| Owning Module | People | Related Modules | Competitors, Organizations, Geography, Privacy, Search |
| Preconditions | Existing Person and lawful purpose for context. | Trigger | Context creation/update, affiliation claim/review, public visibility request. |
| Inputs | Context attributes, affiliation relationship, dates/scope, provenance, visibility. | Validation Rules | Context-specific fields; organization/geography validity; no Role/Permission inference; conservative Minor visibility. |
| Authorization and Scope | Self/organization edits are field/scope specific; Judge Profile does not authorize judging. | Normal Functional Behavior | Version context, link affiliation, evaluate public projection and conflicts. |
| Alternative Behavior | Display affiliation as pending/ended/disputed with provenance classification. | Failure Behavior | Reject cross-Person, unsupported authority, or private-to-public leakage. |
| Records Read | Person, Profiles, organizations/geography, privacy/Consent. | Records Created | Contextual Profile or affiliation record. |
| Records Updated | Current context/public projection. | Records Versioned or Superseded | Profile and affiliation versions. |
| Audit Requirements | Context, scope, actor, affiliation evidence/status, visibility. | Domain Events | ContextualProfileChanged; AffiliationChanged; PublicProfileChanged. |
| Notifications | Person and organization for reviewed affiliation changes. | Privacy and Data Classification | Context data restricted by default; projection minimized. |
| Accessibility and Interaction Requirements | Context labels must be explicit and not conveyed only by icons/color. | Postconditions | One Person may have multiple independent current/historical contexts. |
| Related Business Invariants | INV-004, INV-007 | Related P0-B01 Requirements | QMDB-IDN-001, QMDB-IDN-003, QMDB-PRI-002 |
| Verification Method | Domain test; authorization test; end-to-end test. | Acceptance Criteria | One Person may be a Coach in one Edition and Judge in another without merged authority. |

### QMDB-FR-PPL-004 — Merge or correct duplicate Person records safely

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-PPL-004 | Title | Merge or correct duplicate Person records safely |
| Requirement Statement | QMDB shall merge or correct duplicate Person records only through reviewed, reversible-or-compensating, provenance-preserving actions that leave historical Participant Snapshots and official records stable. | Rationale | A false merge or history rewrite can corrupt many domains. |
| Priority | Critical | Planned Implementation Phase | P3 — Nigerian Geography, Organizations, and People |
| Primary Actors | Data Steward | Supporting Actors | Privacy Officer; Registrar; Internal Auditor |
| Owning Module | People | Related Modules | Identity, Registration, Records, Audit, Privacy |
| Preconditions | Duplicate case with evidence, impact preview, current versions, no unresolved conflict preventing review. | Trigger | Merge request or correction/reversal request. |
| Inputs | Source/target Person, evidence, field decisions, reason, approvals under OD-032/OD-034. | Validation Rules | Non-self-approval; conflict detection; Account/Guardian/role/record impact; no history deletion. |
| Authorization and Scope | Dedicated steward permission, Step-Up Authentication, approval category; ordinary profile editor denied. | Normal Functional Behavior | Create merge decision/version, redirect current identity references as approved, retain aliases/provenance, never rewrite snapshots. |
| Alternative Behavior | Reject merge and retain linked duplicate-review case; compensate a wrong merge through new versions. | Failure Behavior | Roll back atomically on conflict; preserve both Persons and evidence. |
| Records Read | Both Persons and dependent links/records/conflicts. | Records Created | Merge review/decision and provenance mapping. |
| Records Updated | Current association pointers and duplicate status. | Records Versioned or Superseded | Person/Profile associations and merge/correction history. |
| Audit Requirements | Requester/reviewer, evidence, impact, field choices, approvals, before/after identifiers. | Domain Events | PersonMergeApproved; PersonMergeCorrected. |
| Notifications | Affected Account holders/Guardians where safe; privacy/audit for high-risk correction. | Privacy and Data Classification | Highly restricted identity graph/evidence. |
| Accessibility and Interaction Requirements | Accessible side-by-side comparison, explicit retained-history warning, confirmation summary. | Postconditions | One current durable identity is selected or prior decision is compensated; historical records retain original snapshots. |
| Related Business Invariants | INV-003, INV-007, INV-014, INV-026 | Related P0-B01 Requirements | QMDB-IDN-002, QMDB-CMP-004, QMDB-AUD-003 |
| Verification Method | Integration test; authorization test; audit test; manual privacy review. | Acceptance Criteria | Merge and later correction never mutate an existing Participant Snapshot. |

### QMDB-FR-GUA-001 — Establish a Guardian Relationship

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-GUA-001 | Title | Establish a Guardian Relationship |
| Requirement Statement | QMDB shall create an active Guardian Relationship only after a scoped invitation/request, identity association, approved authority-evidence method, and acceptance/review outcome are recorded. | Rationale | Claimed relationship alone cannot authorize decisions for a Minor. |
| Priority | Critical | Planned Implementation Phase | P3 — Nigerian Geography, Organizations, and People |
| Primary Actors | Guardian | Supporting Actors | Minor where appropriate; Child-Safety Officer; Privacy Officer |
| Owning Module | Guardianship | Related Modules | People, Identity, Privacy, Audit, Notifications |
| Preconditions | Existing Persons; Minor status/policy context; no prohibited relationship conflict. | Trigger | Guardian invitation or relationship request. |
| Inputs | Guardian/Minor references, relationship scope, evidence, method, dates, acceptance. | Validation Rules | Person distinction; approved method OD-012; evidence privacy; duplicate/disputed relationship; scope/time. |
| Authorization and Scope | Requester can propose only; sensitive authority activates through approved review; public IDs grant nothing. | Normal Functional Behavior | Create pending record, notify parties, review evidence, activate/reject with reason. |
| Alternative Behavior | Support multiple active Guardians with explicit scopes; maintain pending/restricted status. | Failure Behavior | Deny unverifiable, conflicted, duplicate, suspended, or cross-scope request; keep Minor private. |
| Records Read | Persons, Minor status, existing relationships, policy/evidence. | Records Created | Guardian Relationship request/version and review. |
| Records Updated | Relationship state. | Records Versioned or Superseded | Every scope, evidence-status, acceptance, dispute, termination change. |
| Audit Requirements | Request, parties, method/status, reviewer, evidence reference, scope, outcome. | Domain Events | GuardianRelationshipRequested; GuardianRelationshipActivated; GuardianRelationshipRejected. |
| Notifications | Guardian, Account/Minor as policy permits, safety role for dispute. | Privacy and Data Classification | Child/relationship evidence highly restricted. |
| Accessibility and Interaction Requirements | Explain scope and evidence need; age-appropriate language; no coercive Consent bundling. | Postconditions | Relationship is pending/rejected/active with explicit scope and provenance. |
| Related Business Invariants | INV-020, INV-027 | Related P0-B01 Requirements | QMDB-PRI-001, QMDB-PRI-002, QMDB-MED-003 |
| Verification Method | Authorization test; integration test; manual privacy review. | Acceptance Criteria | An asserted but unreviewed Guardian cannot grant competition or publication Consent. |

### QMDB-FR-GUA-002 — Manage multiple, suspended, or disputed Guardians

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-GUA-002 | Title | Manage multiple, suspended, or disputed Guardians |
| Requirement Statement | QMDB shall evaluate every Guardian action against the current relationship scope, Account status, dispute state, applicable multi-Guardian policy, and action purpose. | Rationale | Relationships can conflict or lose validity. |
| Priority | Critical | Planned Implementation Phase | P3 — Nigerian Geography, Organizations, and People |
| Primary Actors | Guardian; Child-Safety Officer | Supporting Actors | Privacy Officer; Minor where appropriate |
| Owning Module | Guardianship | Related Modules | Identity, Privacy, Moderation, Audit |
| Preconditions | One or more relationship records exist. | Trigger | Guardian action, disagreement, dispute, Account suspension, relationship scope change. |
| Inputs | Relationship version, action/purpose, other Guardian positions, evidence, emergency flag. | Validation Rules | Current active authority; OD-011/OD-012 policy; no suspended Account authority; disagreement rule; least exposure. |
| Authorization and Scope | Each relationship authorizes only its subjects/purposes/time; override needs Step-Up, reason, evidence, non-self approval under OD-034. | Normal Functional Behavior | Decide allowed/pending/denied, record each position, restrict visibility during unresolved risk, route review. |
| Alternative Behavior | Preserve non-conflicting existing Consents while disputed action remains blocked if policy permits. | Failure Behavior | Fail toward privacy/non-publication; never select one Guardian silently. |
| Records Read | Guardian Relationships, Accounts, Consents, disputes, safety policy. | Records Created | Dispute/review/override request. |
| Records Updated | Relationship restriction/dispute status. | Records Versioned or Superseded | Relationship and override decisions. |
| Audit Requirements | Guardian/action, competing positions, status, restriction, reviewer/approval/reason. | Domain Events | GuardianRelationshipDisputed; GuardianAuthorityRestricted; GuardianDisputeResolved. |
| Notifications | Relevant Guardians and safety/privacy roles with minimized details. | Privacy and Data Classification | Highly restricted family/child-safety data. |
| Accessibility and Interaction Requirements | Clearly distinguish each subject/purpose/status and provide safe escalation instructions. | Postconditions | No disputed/suspended authority can silently authorize a sensitive action. |
| Related Business Invariants | INV-020, INV-024 | Related P0-B01 Requirements | QMDB-ACT-003, QMDB-PRI-002, QMDB-SEC-001 |
| Verification Method | Authorization test; workflow integration test; manual privacy review. | Acceptance Criteria | Guardian disagreement blocks the disputed publication until approved policy resolves it. |

### QMDB-FR-GUA-003 — Create, version, and withdraw Consent

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-GUA-003 | Title | Create, version, and withdraw Consent |
| Requirement Statement | QMDB shall record competition, recording, Public Profile, and Recitation Clip publication Consent separately by subject, purpose, scope, policy version, actor/authority, evidence, grant/refusal, effective time, expiry where required, and withdrawal. | Rationale | Consent is specific and must remain provable over time. |
| Priority | Critical | Planned Implementation Phase | P3 — Nigerian Geography, Organizations, and People |
| Primary Actors | Guardian; Person/Minor where policy permits | Supporting Actors | Registrar; Media Moderator; Privacy Officer |
| Owning Module | Guardianship | Related Modules | Registration, Media, Recitation Clips, Privacy, Audit |
| Preconditions | Verified applicable authority and presented policy/purpose. | Trigger | Consent request, response, policy change, expiry, or withdrawal. |
| Inputs | Subject, purpose, resource/class, scope, policy version, decision, authority, evidence, effective/expiry data. | Validation Rules | Purpose isolation; current authority; understandable notice; no preselection/bundling; version/time; withdrawal authentication. |
| Authorization and Scope | Actor may decide only for self or active Guardian scope; restricted-publication override follows OD-034 and applicable law review. | Normal Functional Behavior | Append Consent version, enforce immediately on dependent capability, retain historical decision. |
| Alternative Behavior | Refusal/absence/expiry keeps dependent action unavailable; later grant creates new version. | Failure Behavior | Deny ambiguous, stale-policy, out-of-scope, suspended-authority, or replayed decision. |
| Records Read | Guardian authority, subject status, notice/purpose, prior Consent, dependent resource. | Records Created | Consent Record/version and evidence reference. |
| Records Updated | Current Consent status pointer and dependent visibility/eligibility gates. | Records Versioned or Superseded | Every Consent decision/change; former versions preserved. |
| Audit Requirements | Subject/purpose/scope, policy, actor/authority, method, time, outcome, dependent effects. | Domain Events | ConsentGranted; ConsentRefused; ConsentWithdrawn; ConsentExpired. |
| Notifications | Subject/Guardian and affected owning workflow. | Privacy and Data Classification | Highly restricted; public views expose no Consent evidence. |
| Accessibility and Interaction Requirements | Plain-language purpose, separate choices, accessible policy comparison, confirmation and withdrawal path. | Postconditions | Dependent actions reflect current applicable Consent while history remains. |
| Related Business Invariants | INV-020, INV-026 | Related P0-B01 Requirements | QMDB-PRI-001, QMDB-MED-003, QMDB-AUD-001 |
| Verification Method | Unit test; authorization test; integration test; accessibility test; manual privacy review. | Acceptance Criteria | Recording Consent does not imply Public Profile or Clip publication Consent. |

### QMDB-FR-GUA-004 — Protect Minors through status transitions and emergencies

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-GUA-004 | Title | Protect Minors through status transitions and emergencies |
| Requirement Statement | QMDB shall apply conservative private defaults when Minor or Guardian status is absent, uncertain, changed, disputed, under emergency restriction, or due for re-evaluation because the Person transitions to adult status. | Rationale | Uncertain authority must not create public child exposure. |
| Priority | Critical | Planned Implementation Phase | P3 — Nigerian Geography, Organizations, and People |
| Primary Actors | Minor/Person; Guardian; Community-Safety Officer | Supporting Actors | Privacy Officer; Security Operator |
| Owning Module | Guardianship | Related Modules | People, Privacy, Media, Recitation Clips, Search, Moderation |
| Preconditions | Person has age/status context or child-safety signal. | Trigger | Status change, missing Guardian, dispute, emergency restriction, transition review. |
| Inputs | Status evidence/classification, effective date, relationships, current Consents/publications, safety reason. | Validation Rules | OD-011 age policy; effective-time handling; no automatic historical Consent rewrite; immediate visibility restriction permitted with review. |
| Authorization and Scope | Safety restriction is minimum necessary and time/review bound; permanent/override decisions require governed authority. | Normal Functional Behavior | Restrict public visibility/interactions, block new dependent actions, schedule review, notify safely, preserve evidence/official history. |
| Alternative Behavior | Adult transition requests self-managed future Consents while historical Guardian decisions remain historically identified. | Failure Behavior | Keep restricted on ambiguity or processing failure; escalate credible safety risk. |
| Records Read | Person status, Guardian Relationships, Consents, profiles/media/posts, moderation/safety cases. | Records Created | Transition/restriction/review record. |
| Records Updated | Current protective policy/visibility and future-authority state. | Records Versioned or Superseded | Status, restrictions, relationship/Consent applicability. |
| Audit Requirements | Trigger/evidence, affected resources, restriction, actor, review/expiry, notifications. | Domain Events | MinorStatusChanged; EmergencyVisibilityRestricted; AdultTransitionReviewOpened. |
| Notifications | Guardian/Person and safety/privacy roles unless unsafe; owning projections receive restriction event. | Privacy and Data Classification | Child-safety data highly restricted; public precise location/contact prohibited. |
| Accessibility and Interaction Requirements | Age-appropriate messages, safe exit/escalation, no disclosure to unauthorized Guardians. | Postconditions | Public/minor-dependent capabilities are safe while historical evidence remains restricted and intact. |
| Related Business Invariants | INV-019, INV-020, INV-026 | Related P0-B01 Requirements | QMDB-PRI-002, QMDB-MED-002, QMDB-MED-003 |
| Verification Method | End-to-end test; authorization test; manual privacy/child-safety review. | Acceptance Criteria | Withdrawal/dispute removes public visibility promptly without deleting historical evidence required by policy. |

## Conservative failure rules

No active verified Guardian means no Guardian-dependent action. Guardian disagreement or dispute blocks only the disputed sensitive purpose while safety controls may restrict more exposure. Account suspension removes Guardian application authority. Adult transition changes future decision authority only after policy review and never rewrites historical Consent or Participant Snapshots.

## Related documents

- [Functional requirements index](../P0-B02-functional-requirements-index.md)
- [Use-case catalog](../P0-B02-use-case-catalog.md)
- [Edge cases](../P0-B02-edge-cases-and-failure-behavior.md)
- [P0-B01 baseline requirements](../P0-B01-requirements.md)
