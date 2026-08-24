# Identity, Access, and Tenancy Functional Requirements

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B02 |
| Document Title | Identity, Access, and Tenancy Functional Requirements |
| Document Version | 1.0.0 |
| Status | Baselined; implementation not started |
| Document Owner Role | Identity and Access Architecture |
| Last Updated | 2026-08-24 |
| Related Documents | [Functional index](../P0-B02-functional-requirements-index.md); [actors](../../domain/stakeholders-and-actors.md); [invariants](../../domain/core-modules-and-business-invariants.md); [open decisions](../../project/open-decisions.md) |

## Purpose

Define testable account, authentication, Session, Device, Workspace, tenant-isolation, and contextual-authorization behavior without conflating a Person, User Account, Membership, Role, or Competition Assignment.

## Scope

These requirements govern Account registration through closure, password/passkey/MFA authentication, recovery, browser Sessions, Devices, Workspace lifecycle/context, RBAC+ABAC policies, scoped authority, separation of duties, and exceptional access. Authentication policy details depend on OD-031; exact approval categories depend on OD-034.

## Common controls

All protected actions use server-derived tenant context, CSRF/replay protection where applicable, rate controls, safe non-enumerating errors, UTC microsecond timestamps, Correlation IDs, and accessible mobile/RTL interactions. Security notifications cannot be disabled where specified. Public identifiers never authorize access.

## Functional requirements

### QMDB-FR-IAM-001 — Register and verify an individual Account

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-IAM-001 | Title | Register and verify an individual Account |
| Requirement Statement | QMDB shall create a pending User Account from a valid registration request and activate it only after the configured contact-verification evidence succeeds. | Rationale | Unverified contact channels cannot establish an active Account. |
| Priority | High | Planned Implementation Phase | P2 — Identity, Security, and Tenant Isolation |
| Primary Actors | Registered Individual | Supporting Actors | Notification provider; Background Worker |
| Owning Module | Identity | Related Modules | Notifications, Audit, Security Operations |
| Preconditions | No active Account owns the normalized unique login identifier. | Trigger | Individual submits registration or follows a verification challenge. |
| Inputs | Login identifier, password or passkey intent, locale, policy acknowledgements, verification token. | Validation Rules | Normalize identifiers; validate password policy; expire/single-use tokens; prevent account enumeration and duplicate activation. |
| Authorization and Scope | Public registration is rate-limited; verification token is scoped to Account, purpose, and expiry. | Normal Functional Behavior | Create pending Account, Credential metadata, verification challenge, then activate and record verified channel. |
| Alternative Behavior | Existing pending Account receives a bounded challenge resend; phone verification remains provider-neutral under OD-006/OD-031. | Failure Behavior | Reject malformed, expired, reused, mismatched, or throttled requests without revealing another Account. |
| Records Read | Account uniqueness, policy/version, verification challenge. | Records Created | User Account, verification challenge, Audit Events, notification intent. |
| Records Updated | Verification attempt/status; Account state. | Records Versioned or Superseded | Verification challenge and accepted policy acknowledgement. |
| Audit Requirements | Registration, challenge issuance/resend, verification success/failure, activation, source/risk metadata. | Domain Events | AccountRegistered; AccountVerified; AccountActivated. |
| Notifications | Verification challenge; activation/security notice. | Privacy and Data Classification | Contact and security data are restricted; notification payload is minimized. |
| Accessibility and Interaction Requirements | Accessible validation summary, recovery instructions, language/direction support, resend countdown announced without color alone. | Postconditions | Account is either pending without authenticated access or active with recorded verification evidence. |
| Related Business Invariants | INV-003, INV-027, INV-029 | Related P0-B01 Requirements | QMDB-IDN-001, QMDB-IDN-004, QMDB-SEC-002 |
| Verification Method | Unit test; integration test; security test; accessibility test. | Acceptance Criteria | Duplicate identifiers do not create a second active Account; only an unexpired purpose-bound token activates the Account. |

### QMDB-FR-IAM-002 — Authenticate with a password safely

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-IAM-002 | Title | Authenticate with a password safely |
| Requirement Statement | QMDB shall authenticate an active Account only after server-side password verification and applicable risk, rate, status, and MFA checks succeed. | Rationale | Password possession alone is insufficient when risk or privilege requires stronger proof. |
| Priority | Critical | Planned Implementation Phase | P2 — Identity, Security, and Tenant Isolation |
| Primary Actors | Registered Individual | Supporting Actors | Security Operator; Notification provider |
| Owning Module | Identity | Related Modules | Security Operations, Audit, Notifications |
| Preconditions | Account and password Credential exist and are not disabled. | Trigger | Login submission. |
| Inputs | Normalized login identifier, password, client/risk context, optional MFA response. | Validation Rules | Constant-behavior credential checks; rate/velocity limits; Credential status; Account status; MFA policy; no plaintext logging. |
| Authorization and Scope | Public endpoint creates no authority until successful authentication; post-login scopes are separately evaluated. | Normal Functional Behavior | Verify password, complete required MFA, rotate Session identifier, establish server Session, and record result. |
| Alternative Behavior | Unknown Account and wrong password receive equivalent public response; anomalous attempt may require added challenge. | Failure Behavior | Deny login, apply bounded delay/lock policy, preserve existing safe Sessions unless containment policy requires revocation, and alert on anomaly. |
| Records Read | User Account, password Credential, MFA methods, risk/rate state. | Records Created | Server Session on success; authentication Audit Event. |
| Records Updated | Failed-attempt/risk counters, last successful authentication. | Records Versioned or Superseded | Security/risk assessment evidence where material. |
| Audit Requirements | Success/failure category, Account reference when known, risk outcome, MFA result, Correlation ID; never password. | Domain Events | AccountAuthenticated; LoginAnomalyDetected. |
| Notifications | Mandatory suspicious-login/security notice when policy threshold is met. | Privacy and Data Classification | Authentication and risk data are highly restricted. |
| Accessibility and Interaction Requirements | Password manager support, labeled controls, keyboard completion, non-revealing recovery guidance. | Postconditions | A new rotated Session exists on success; no Session is created on failure. |
| Related Business Invariants | INV-024, INV-027, INV-029 | Related P0-B01 Requirements | QMDB-IDN-004, QMDB-SEC-001, QMDB-SEC-002 |
| Verification Method | Authorization test; integration test; security test. | Acceptance Criteria | Invalid, suspended, throttled, or MFA-incomplete attempts produce no authenticated Session. |

### QMDB-FR-IAM-003 — Register and use passkeys

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-IAM-003 | Title | Register and use passkeys |
| Requirement Statement | QMDB shall register and authenticate passkeys through origin-bound, replay-resistant challenges associated with one active User Account. | Rationale | Passkeys provide phishing-resistant authentication when ceremony and credential lifecycle are correct. |
| Priority | High | Planned Implementation Phase | P2 — Identity, Security, and Tenant Isolation |
| Primary Actors | Registered Individual | Supporting Actors | Security Operator |
| Owning Module | Identity | Related Modules | Audit, Security Operations |
| Preconditions | Registration requires an authenticated recent Session or approved recovery; authentication requires an active passkey Credential. | Trigger | Passkey enrollment or login ceremony. |
| Inputs | Server challenge, origin/RP context, credential identifier, authenticator response, user label. | Validation Rules | Verify origin, RP, challenge, expiry, signature counter/risk signals, uniqueness, Account/Credential status. |
| Authorization and Scope | Enrollment requires Step-Up Authentication unless it is the initial approved Account ceremony. | Normal Functional Behavior | Store public credential material/metadata, never private key; verify response and establish rotated Session. |
| Alternative Behavior | Multiple passkeys may be labeled and individually revoked under OD-031 policy. | Failure Behavior | Deny replay, origin mismatch, unknown/disabled Credential, invalid signature, or suspicious counter behavior and create security evidence. |
| Records Read | Account, challenge, Credential, Session/risk state. | Records Created | Passkey Credential metadata; enrollment/authentication Audit Event. |
| Records Updated | Credential use/risk metadata. | Records Versioned or Superseded | Credential status changes and label history. |
| Audit Requirements | Enrollment, authentication, rename, revocation, anomalies; exclude challenge secrets and private material. | Domain Events | PasskeyRegistered; AccountAuthenticated; CredentialRevoked. |
| Notifications | Mandatory enrollment/revocation security notice. | Privacy and Data Classification | Credential metadata is highly restricted; attestation collection is minimized. |
| Accessibility and Interaction Requirements | Do not require biometric modality; expose alternative approved authentication/recovery with clear platform prompts. | Postconditions | Passkey is independently revocable; success creates a server-side Session. |
| Related Business Invariants | INV-003, INV-029 | Related P0-B01 Requirements | QMDB-IDN-004, QMDB-SEC-002 |
| Verification Method | API contract test; integration test; security test; accessibility test. | Acceptance Criteria | Replayed or wrong-origin ceremonies fail and never create a Session or Credential. |

### QMDB-FR-IAM-004 — Enforce MFA and Step-Up Authentication

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-IAM-004 | Title | Enforce MFA and Step-Up Authentication |
| Requirement Statement | QMDB shall require an approved additional authenticator for privileged login and a fresh Step-Up Authentication result for each policy-classified high-risk action. | Rationale | Sensitive authority requires stronger and recent proof. |
| Priority | Critical | Planned Implementation Phase | P2 — Identity, Security, and Tenant Isolation |
| Primary Actors | Privileged actors | Supporting Actors | Security Operator; Support Officer |
| Owning Module | Identity | Related Modules | Access Control, Security Operations, Audit |
| Preconditions | Active Account has policy-compliant MFA or is in governed enrollment/recovery. | Trigger | Privileged authentication or high-risk action request. |
| Inputs | Authenticator challenge/response, action/resource context, Session, recovery evidence when applicable. | Validation Rules | Approved method; freshness; attempt limits; purpose/action binding; recovery separation; no reusable recovery secret exposure. |
| Authorization and Scope | MFA/step-up proves identity control but never replaces Role, Permission, scope, resource policy, conflict, or approval. | Normal Functional Behavior | Challenge, verify, attach short-lived action-scoped evidence, and continue authorization. |
| Alternative Behavior | Deny high-risk action while permitting lower-risk Account recovery steps according to OD-031. | Failure Behavior | Fail closed, preserve uncommitted business state, rate-limit, and notify/alert when suspicious. |
| Records Read | Account, Session, authenticator, action policy, recovery state. | Records Created | Challenge and Step-Up result evidence. |
| Records Updated | Authenticator status/use metadata. | Records Versioned or Superseded | MFA enrollment/recovery status and action-policy version. |
| Audit Requirements | Enrollment, challenge outcome, recovery, step-up purpose, actor/resource, policy version. | Domain Events | MfaEnrolled; StepUpCompleted; AuthenticationAnomalyDetected. |
| Notifications | Mandatory MFA enrollment/recovery/change security notice. | Privacy and Data Classification | Authenticator/recovery data is highly restricted. |
| Accessibility and Interaction Requirements | Accessible alternatives, adequate timeout warning/extension, copy-paste support where safe, clear recovery. | Postconditions | High-risk action receives current scoped proof or is denied. |
| Related Business Invariants | INV-024, INV-029 | Related P0-B01 Requirements | QMDB-IDN-004, QMDB-ACT-002, QMDB-SEC-002, QMDB-SEC-003 |
| Verification Method | Authorization test; end-to-end test; security test; accessibility test. | Acceptance Criteria | A valid password-only Session cannot perform a step-up-classified action. |

### QMDB-FR-IAM-005 — Govern Account recovery and password change

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-IAM-005 | Title | Govern Account recovery and password change |
| Requirement Statement | QMDB shall complete password reset, password change, MFA recovery, or Account recovery only through a purpose-bound, expiring, replay-resistant workflow with security history and notification. | Rationale | Recovery can bypass normal authentication and is a primary takeover path. |
| Priority | Critical | Planned Implementation Phase | P2 — Identity, Security, and Tenant Isolation |
| Primary Actors | Registered Individual | Supporting Actors | Support Officer; Security Operator; Notification provider |
| Owning Module | Identity | Related Modules | Security Operations, Support, Audit, Notifications |
| Preconditions | Recoverable Account and approved recovery channel/evidence exist; current password required for ordinary change. | Trigger | Change/reset/recovery request. |
| Inputs | Account locator, token/challenge, new Credential, current/recovery proof, risk context. | Validation Rules | Single-use expiry, concurrent-request invalidation, password history/policy, stolen-link risk checks, verified support scope. |
| Authorization and Scope | Recovery grants only recovery capability; Support cannot impersonate or view Credentials. | Normal Functional Behavior | Verify evidence, rotate Credential, revoke/rotate affected Sessions, preserve recovery history, notify Account. |
| Alternative Behavior | High-risk or inaccessible-channel cases enter restricted manual review under OD-031. | Failure Behavior | Deny unverifiable/stale/replayed requests without Account disclosure; retain evidence and alert anomalies. |
| Records Read | Account, Credentials, Sessions, recovery challenges/history, support case. | Records Created | Recovery challenge/case evidence and Audit Events. |
| Records Updated | Credential status, Account recovery status, affected Sessions. | Records Versioned or Superseded | Credential and recovery-attempt history. |
| Audit Requirements | Request, channel/method category, support access, outcome, Session revocation; no secret values. | Domain Events | AccountRecoveryRequested; CredentialChanged; SessionsRevoked. |
| Notifications | Mandatory recovery request/completion/failure security notice through safe channels. | Privacy and Data Classification | Recovery evidence is highly restricted and minimized. |
| Accessibility and Interaction Requirements | Clear non-enumerating steps, recoverable errors, adequate time, alternate contact path where approved. | Postconditions | New Credential is active only on success; old Credential and policy-selected Sessions are unusable. |
| Related Business Invariants | INV-025, INV-029 | Related P0-B01 Requirements | QMDB-IDN-004, QMDB-SEC-002, QMDB-SEC-004 |
| Verification Method | Integration test; concurrency test; security test; manual privacy review. | Acceptance Criteria | Concurrent or replayed reset links cannot produce multiple Credential changes. |

### QMDB-FR-IAM-006 — Control Account suspension, reactivation, and closure

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-IAM-006 | Title | Control Account suspension, reactivation, and closure |
| Requirement Statement | QMDB shall transition a User Account among pending, active, suspended, closure-requested, and closed states only through authorized, reasoned, auditable lifecycle actions. | Rationale | Account access and Person/official history have distinct lifecycles. |
| Priority | High | Planned Implementation Phase | P2 — Identity, Security, and Tenant Isolation |
| Primary Actors | Registered Individual; Security Operator | Supporting Actors | Privacy Officer; Support Officer |
| Owning Module | Identity | Related Modules | Privacy, Security Operations, Access Control, Audit |
| Preconditions | Current Account version and applicable actor authority. | Trigger | User request, security containment, policy decision, or approved reactivation. |
| Inputs | Action, reason, evidence, requested scope, current version, approvals where sensitive. | Validation Rules | Legal/record dependencies; active incidents; self-service eligibility; non-self-approved privileged reactivation. |
| Authorization and Scope | Self closure affects Account access only; security suspension is incident-scoped; reactivation requires governed authority and step-up. | Normal Functional Behavior | Change state, revoke Sessions/authority as required, preserve Person and official records, schedule privacy assessment for closure. |
| Alternative Behavior | Restrict capabilities instead of closing where records/Appeals/incidents require continued controlled identity. | Failure Behavior | Reject stale, unauthorized, or self-approved reactivation and leave state unchanged. |
| Records Read | Account, Sessions, Memberships, incidents, privacy requests, official-record dependencies. | Records Created | Lifecycle decision and closure/privacy case when applicable. |
| Records Updated | Account state, Sessions, Credential status. | Records Versioned or Superseded | Account lifecycle and decision history. |
| Audit Requirements | Initiator, authority, reason, evidence, approvals, state versions, Session/authority effects. | Domain Events | AccountSuspended; AccountReactivated; AccountClosureRequested; AccountClosed. |
| Notifications | Mandatory security/lifecycle notice except where incident policy temporarily defers disclosure. | Privacy and Data Classification | Account data restricted; closure is not automatic erasure of official history. |
| Accessibility and Interaction Requirements | Explain consequence, retained-record distinction, reversal eligibility, and recovery path. | Postconditions | Account capabilities match state; Person and official Record Versions remain intact. |
| Related Business Invariants | INV-003, INV-026 | Related P0-B01 Requirements | QMDB-IDN-001, QMDB-PRI-003, QMDB-AUD-001 |
| Verification Method | Authorization test; integration test; manual privacy review. | Acceptance Criteria | A suspended/closed Account cannot authenticate and closure does not delete a Person or finalized record. |

### QMDB-FR-SES-001 — Manage secure server-side Sessions

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-SES-001 | Title | Manage secure server-side Sessions |
| Requirement Statement | QMDB shall create, rotate, expire, and revoke browser Sessions on the server with secure cookie binding and fixation resistance. | Rationale | Client possession of an old identifier must not preserve unauthorized access. |
| Priority | Critical | Planned Implementation Phase | P2 — Identity, Security, and Tenant Isolation |
| Primary Actors | Registered Individual | Supporting Actors | Security Operator |
| Owning Module | Identity | Related Modules | Security Operations, Audit |
| Preconditions | Successful authentication for creation; current Session for rotation/revocation. | Trigger | Login, privilege/authentication change, timeout, logout, or containment. |
| Inputs | Account, Session identifier, authentication level, device/risk context. | Validation Rules | Cryptographic identifier; idle/absolute expiry; server lookup; secure/HTTP-only/SameSite cookie policy; rotation after auth/privilege change. |
| Authorization and Scope | Session authenticates an Account only; authorization is evaluated per action. | Normal Functional Behavior | Persist Session metadata, set protected cookie, rotate without privilege carryover defects, revoke on logout/expiry. |
| Alternative Behavior | Concurrent Sessions are permitted only within policy and are individually visible/revocable. | Failure Behavior | Unknown/expired/revoked Session is denied and cookie cleared; suspicious reuse is alerted. |
| Records Read | Account, Credential/MFA status, Session/risk policy. | Records Created | Server Session and Audit Event. |
| Records Updated | Session rotation, last-use, expiry, revocation. | Records Versioned or Superseded | Session identifier lineage/status. |
| Audit Requirements | Creation, rotation reason, logout, expiry/revocation, suspicious use; no raw token. | Domain Events | SessionCreated; SessionRotated; SessionTerminated. |
| Notifications | Security notice for remote/bulk or suspicious termination as policy requires. | Privacy and Data Classification | Session identifiers are secrets; metadata is restricted. |
| Accessibility and Interaction Requirements | Timeout warning and extension where safe; clear signed-out recovery. | Postconditions | Only current unexpired server Session identifiers authenticate. |
| Related Business Invariants | INV-027, INV-029 | Related P0-B01 Requirements | QMDB-IDN-004, QMDB-SEC-002 |
| Verification Method | Unit test; integration test; security test. | Acceptance Criteria | Pre-authentication or pre-privilege Session identifiers cannot be reused after rotation. |

### QMDB-FR-SES-002 — Label Devices and terminate active Sessions

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-SES-002 | Title | Label Devices and terminate active Sessions |
| Requirement Statement | QMDB shall let an authenticated Account view safely labeled active Sessions and terminate selected or all other Sessions without exposing raw tokens or precise tracking data. | Rationale | People need control of lost or unfamiliar access. |
| Priority | High | Planned Implementation Phase | P2 — Identity, Security, and Tenant Isolation |
| Primary Actors | Registered Individual | Supporting Actors | Security Operator |
| Owning Module | Identity | Related Modules | Audit, Notifications, Privacy |
| Preconditions | Active Session with recent authentication for bulk termination. | Trigger | Active-session view, Device label update, remote termination. |
| Inputs | Session reference, user Device label, termination scope, current Session choice. | Validation Rules | Account ownership; label length/content; step-up for bulk/high-risk; do not reveal full IP/fingerprint. |
| Authorization and Scope | Account may manage only its Sessions; Security containment uses separate incident policy. | Normal Functional Behavior | Return minimized list, version labels, revoke selected Sessions atomically, and confirm result. |
| Alternative Behavior | Current Session may remain or terminate according to explicit choice and recovery safety. | Failure Behavior | Reject foreign/stale Session reference without existence disclosure; preserve unaffected Sessions. |
| Records Read | Account-owned Sessions and Device labels. | Records Created | Termination Audit Event. |
| Records Updated | Device label and selected Session states. | Records Versioned or Superseded | Device label/security history. |
| Audit Requirements | Sensitive list access, label change, initiator, selected/bulk revocation, outcome. | Domain Events | DeviceLabelChanged; SessionTerminated; SessionsRevoked. |
| Notifications | Security notice for all-other/bulk termination. | Privacy and Data Classification | Device/Session metadata is restricted and minimized. |
| Accessibility and Interaction Requirements | Descriptive device/time labels, keyboard actions, confirmation dialog with focus recovery. | Postconditions | Revoked Sessions cannot authenticate on their next request. |
| Related Business Invariants | INV-027, INV-029 | Related P0-B01 Requirements | QMDB-IDN-004, QMDB-SEC-002 |
| Verification Method | Authorization test; integration test; accessibility test. | Acceptance Criteria | Changing a Session reference cannot expose or terminate another Account’s Session. |

### QMDB-FR-SES-003 — React to privilege and suspicious-Session changes

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-SES-003 | Title | React to privilege and suspicious-Session changes |
| Requirement Statement | QMDB shall revoke, rotate, or downgrade affected Sessions when Account, Membership, Role, Permission, Competition Assignment, MFA, or risk state changes invalidate prior authentication context. | Rationale | Cached Session state must not preserve revoked authority. |
| Priority | Critical | Planned Implementation Phase | P2 — Identity, Security, and Tenant Isolation |
| Primary Actors | Security Operator; organization/competition authority | Supporting Actors | Registered Individual; Background Worker |
| Owning Module | Identity | Related Modules | Access Control, Organizations, Security Operations, Audit |
| Preconditions | Active Session references mutable authority/risk context. | Trigger | Authority revocation/change, Account suspension, MFA recovery, suspicious activity, security containment. |
| Inputs | Change event, affected Account/scope, effective time, risk decision. | Validation Rules | Event authenticity; current versions; idempotency; complete affected-Session resolution. |
| Authorization and Scope | Business module owns authority change; Identity owns Session effect; security containment is incident-scoped. | Normal Functional Behavior | Invalidate cached authorization, revoke/downgrade Sessions, require reauthentication/step-up, publish result. |
| Alternative Behavior | Unaffected personal capabilities may continue only when policy proves isolation from revoked privilege. | Failure Behavior | Fail closed for affected privileged operations; retry idempotently and alert incomplete propagation. |
| Records Read | Sessions, Account, authority/risk change. | Records Created | Session-security action evidence. |
| Records Updated | Session authentication/authorization context and status. | Records Versioned or Superseded | Authority and Session status history. |
| Audit Requirements | Trigger source/version, affected Sessions/scopes, action/outcome, propagation failure. | Domain Events | AuthorityRevoked; SessionsRevoked; ReauthenticationRequired. |
| Notifications | Mandatory notice when safe and not incident-deferred. | Privacy and Data Classification | Security metadata highly restricted. |
| Accessibility and Interaction Requirements | Explain sign-out/re-authentication and preserve non-sensitive safe form input where possible. | Postconditions | No affected Session can use superseded privilege context. |
| Related Business Invariants | INV-004, INV-023, INV-025 | Related P0-B01 Requirements | QMDB-IDN-003, QMDB-ACT-002, QMDB-SEC-001 |
| Verification Method | Integration test; authorization test; security test. | Acceptance Criteria | Suspended Membership access fails immediately despite cached UI or Session state. |

### QMDB-FR-TEN-001 — Govern Workspace lifecycle and Membership

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-TEN-001 | Title | Govern Workspace lifecycle and Membership |
| Requirement Statement | QMDB shall create, activate, suspend, archive, and restore a Workspace and its Memberships only through scoped lifecycle actions that preserve tenant-owned history. | Rationale | Tenant availability and record ownership require explicit state. |
| Priority | Critical | Planned Implementation Phase | P2 — Identity, Security, and Tenant Isolation |
| Primary Actors | Platform Operator; authorized Workspace/Organization Administrator | Supporting Actors | Security Operator; Auditor |
| Owning Module | Workspaces | Related Modules | Organizations, Access Control, Audit, Platform Operations |
| Preconditions | Authorized provisioning/management request and unique Workspace identity. | Trigger | Provisioning, lifecycle, Membership invitation/acceptance/removal action. |
| Inputs | Workspace metadata, state action, reason, Membership subject/scope, approvals. | Validation Rules | State transition; unique identifier; owner/Membership rules; no orphaned tenant data; current version. |
| Authorization and Scope | Provisioning is platform-scoped; administration is Workspace-scoped; suspension/restoration requires Step-Up and separation per OD-034. | Normal Functional Behavior | Persist Workspace/Membership state, enforce access effect, retain owned records, emit lifecycle events. |
| Alternative Behavior | Suspend access without destroying records; archive only when active operations/dependencies permit. | Failure Behavior | Reject unauthorized, stale, or unsafe transition atomically; no tenant data leakage. |
| Records Read | Workspace, Memberships, active competitions, holds/dependencies. | Records Created | Workspace/Membership and lifecycle decisions. |
| Records Updated | Workspace and Membership states. | Records Versioned or Superseded | Workspace settings/state and Membership history. |
| Audit Requirements | Provisioning, state change, Membership invitation/acceptance/removal, reason, approvals. | Domain Events | WorkspaceCreated; WorkspaceSuspended; WorkspaceReactivated; MembershipChanged. |
| Notifications | Affected administrators/members and operations/security where required. | Privacy and Data Classification | Tenant metadata internal; membership personal data restricted. |
| Accessibility and Interaction Requirements | Clear impact/affected-scope summaries and confirmation; state not color-only. | Postconditions | Workspace access matches current state; tenant records retain ownership. |
| Related Business Invariants | INV-001, INV-002, INV-026 | Related P0-B01 Requirements | QMDB-ORG-001, QMDB-ORG-002, QMDB-ACT-002 |
| Verification Method | Authorization test; database constraint test; integration test. | Acceptance Criteria | Workspace suspension blocks tenant operations without deleting tenant-owned records. |

### QMDB-FR-TEN-002 — Resolve and enforce tenant context

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-TEN-002 | Title | Resolve and enforce tenant context |
| Requirement Statement | QMDB shall derive the effective Workspace from authenticated server-side Membership and resource context and reject arbitrary, inactive, or cross-Workspace identifiers and relationships. | Rationale | Client-selected tenant identifiers create cross-tenant access and corruption risk. |
| Priority | Critical | Planned Implementation Phase | P2 — Identity, Security, and Tenant Isolation |
| Primary Actors | Authenticated end user; service actor | Supporting Actors | Security Operator |
| Owning Module | Workspaces | Related Modules | Access Control, Organizations, all tenant-owned modules, Audit |
| Preconditions | Authenticated Account/service principal and requested resource/action. | Trigger | Workspace switch or any tenant-owned read/write. |
| Inputs | Session/service principal, requested Workspace/resource identifier, current Memberships. | Validation Rules | Active Workspace/Membership; resource `workspace_id`; composite parent-child integrity; no trust in client context. |
| Authorization and Scope | Cross-Workspace denied by default; separate governed oversight/Break-Glass policy required. | Normal Functional Behavior | Resolve allowed context, bind transaction/query/cache/job/event, and return scoped result. |
| Alternative Behavior | A user with multiple Memberships explicitly switches only among authorized Workspaces and receives a new scoped context. | Failure Behavior | Deny as not-found/forbidden without cross-tenant existence leakage; rollback relationship write; alert suspicious patterns. |
| Records Read | Workspace, Membership, resource ownership, policy. | Records Created | Context-switch and denial Audit Events where required. |
| Records Updated | Active Session Workspace context only after authorization. | Records Versioned or Superseded | Workspace-context history where security-relevant. |
| Audit Requirements | Switches, privileged cross-Workspace oversight, denials/anomalies, resource/Workspace references. | Domain Events | WorkspaceContextChanged; CrossWorkspaceAccessDenied. |
| Notifications | Security alert for repeated/sensitive cross-tenant attempts. | Privacy and Data Classification | Tenant identity and denied-resource details are internal. |
| Accessibility and Interaction Requirements | Always display active Workspace textually; warn before context switch; preserve direction/keyboard behavior. | Postconditions | All downstream work carries one explicit approved Workspace context. |
| Related Business Invariants | INV-001, INV-002, INV-023, INV-027 | Related P0-B01 Requirements | QMDB-ORG-001, QMDB-ORG-002, QMDB-SEC-001 |
| Verification Method | Authorization test; database constraint test; security test; end-to-end test. | Acceptance Criteria | Changing `workspace_id` or a parent identifier cannot read/write another Workspace. |

### QMDB-FR-AUT-001 — Evaluate contextual resource authorization

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-AUT-001 | Title | Evaluate contextual resource authorization |
| Requirement Statement | QMDB shall deny each protected request unless current Role, Permission, Workspace, Membership, Administrative Scope, Competition Assignment, resource ownership/state, time, conflict, and authentication attributes satisfy an explicit resource policy. | Rationale | Role-only authorization cannot express QMDB authority boundaries. |
| Priority | Critical | Planned Implementation Phase | P2 — Identity, Security, and Tenant Isolation |
| Primary Actors | All authenticated human/service actors | Supporting Actors | Access-Control Governance; Security Operator |
| Owning Module | Access Control | Related Modules | Identity, Workspaces, Organizations, Geography, Competitions, Audit |
| Preconditions | Authenticated principal and normalized action/resource request. | Trigger | Any protected query, command, export, worker action, or integration call. |
| Inputs | Principal, effective roles/permissions, scopes, assignments, resource/version/state, purpose, risk/time. | Validation Rules | Deny by default; active versions; explicit policy; scope intersection; no public-ID or UI-control authority. |
| Authorization and Scope | The policy evaluation is the authorization decision and is bound to action/resource/tenant. | Normal Functional Behavior | Resolve attributes, evaluate policy, provide allow/deny plus safe reason code, and record sensitive decisions. |
| Alternative Behavior | Conditional decision requests Step-Up or independent approval without partially applying the action. | Failure Behavior | Deny missing/stale/ambiguous context; no data existence leak; alert high-risk denial patterns. |
| Records Read | Principal, grants, Memberships, assignments, resource, policy versions. | Records Created | Approval request or Audit Event where applicable. |
| Records Updated | None by evaluation alone. | Records Versioned or Superseded | Policy decisions/versions and grants through owning workflows. |
| Audit Requirements | Sensitive allow/deny, effective role/scope, policy/version, resource, purpose, Correlation ID. | Domain Events | AuthorizationConditionRequired; CrossScopeAccessDenied. |
| Notifications | Approval/step-up request where workflow requires; security alerts for anomalies. | Privacy and Data Classification | Policy/risk details internal; logs minimize resource data. |
| Accessibility and Interaction Requirements | Denial explains permitted recovery without disclosing hidden data; approval/step-up prompts are accessible. | Postconditions | Protected action either has a current explicit allow or performs no business change. |
| Related Business Invariants | INV-001, INV-004, INV-024, INV-025, INV-027 | Related P0-B01 Requirements | QMDB-IDN-003, QMDB-ACT-001, QMDB-ACT-002, QMDB-SEC-001 |
| Verification Method | Unit test; authorization test; security test; architecture test. | Acceptance Criteria | A valid Role outside its assigned scope is denied. |

### QMDB-FR-AUT-002 — Govern grants, revocation, approvals, and exceptional authority

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-AUT-002 | Title | Govern grants, revocation, approvals, and exceptional authority |
| Requirement Statement | QMDB shall version Role, Permission, Administrative Scope, Competition Assignment, time-bound grant, approval, conflict exception, global oversight, support-access, and Break-Glass authority with immediate revocation effects and separation of duties. | Rationale | Sensitive authority must be attributable, bounded, reviewable, and removable. |
| Priority | Critical | Planned Implementation Phase | P2 — Identity, Security, and Tenant Isolation |
| Primary Actors | Authorized administrators; Security Operator | Supporting Actors | Auditor; affected principal; approval-role category |
| Owning Module | Access Control | Related Modules | Identity, Organizations, Competitions, Security Operations, Audit |
| Preconditions | Initiator has grant/approval authority and is not prohibited by conflict/self-approval rules. | Trigger | Assign, approve, revoke, expire, recuse, replace, or activate exceptional access. |
| Inputs | Subject, capability, scopes, duration, reason, evidence, approver(s), current version. | Validation Rules | Least privilege; toxic combination/conflict checks; no self-approval; Step-Up; expiry; active Membership; OD-034 approval dependency. |
| Authorization and Scope | Global oversight is read/act per explicit policy, never unrestricted edit; Break-Glass follows its separate emergency workflow. | Normal Functional Behavior | Create new grant/version, invalidate caches/Sessions as needed, notify, audit, and schedule review/expiry. |
| Alternative Behavior | Narrower grant or independent approval request may be proposed; unresolved conflict causes recusal/replacement. | Failure Behavior | Reject stale, excessive, permanent emergency, self-approved, suspended-subject, or out-of-scope grant and preserve prior state. |
| Records Read | Roles, Permissions, Memberships, scopes, conflicts, approvals, current grants. | Records Created | Grant/approval/conflict/exception/Break-Glass record. |
| Records Updated | Effective authority and affected Session context. | Records Versioned or Superseded | Every grant, scope, assignment, approval, revocation, conflict decision. |
| Audit Requirements | Initiator/approver, effective role, scope, reason/evidence, before/after, step-up, expiry/review, outcome. | Domain Events | AuthorityAssigned; AuthorityRevoked; ConflictDeclared; ExceptionalAccessActivated. |
| Notifications | Subject, approver, security/audit for sensitive/global/emergency changes. | Privacy and Data Classification | Authority and conflict evidence restricted; minimum disclosure to subject. |
| Accessibility and Interaction Requirements | Scope and consequences summarized in text; expiration and approval status announced; no color-only authority state. | Postconditions | Only the current non-expired, non-revoked, policy-compatible grant can authorize future work. |
| Related Business Invariants | INV-004, INV-013, INV-024, INV-025 | Related P0-B01 Requirements | QMDB-ACT-002, QMDB-ACT-003, QMDB-SEC-003, QMDB-SEC-004 |
| Verification Method | Authorization test; integration test; security test; document inspection. | Acceptance Criteria | Revoked/suspended authority is unusable immediately; Break-Glass cannot become permanent normal access. |

## Negative authorization guarantees

- A client cannot select an arbitrary `workspace_id` or create a cross-Workspace relationship.
- A public identifier, QR value, role label, organization title, or national title cannot authorize an action.
- A suspended Membership authorizes nothing; Support access remains case/field/time scoped.
- National coordination does not include Score Sheet or Result editing.
- Break-Glass Access expires automatically and is never a standing Role.

## Related documents

- [Functional requirements index](../P0-B02-functional-requirements-index.md)
- [Permission and capability matrix](../P0-B02-permission-capability-matrix.md)
- [Workflows and state machines](../P0-B02-workflows-and-state-machines.md)
- [P0-B01 baseline requirements](../P0-B01-requirements.md)

