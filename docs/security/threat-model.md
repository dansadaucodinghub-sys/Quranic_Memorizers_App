# Threat Model

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B03 |
| Document Title | Threat Model |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Security Architecture and Threat Modelling |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed for governance approval; locked baseline constraints remain binding |
| Related Documents | [System boundaries](../project/system-boundaries.md); [Control catalog](security-control-catalog.md); [Risk register](../project/risk-register.md) |

## Purpose

Identify assets, actors, entry points, trust boundaries, STRIDE-class threats, planned controls, detection, response and residual risk for QMDB.

## Scope

Public website; authenticated, Memorizer/Competitor, Guardian, Judge, Organizer, geography administration, moderation, privacy, security and operations sides; Core PHP, MySQL, Redis, object/CDN, media/background workers, notification providers, APIs/webhooks, Venue Edge/offline devices, certificate verification and audit checkpoints.

## Method and risk posture

STRIDE is used as a completeness lens: spoofing, tampering, repudiation, information disclosure, denial of service and elevation of privilege. Criticality is qualitative. Planned controls are not implementation evidence; residual risk remains subject to verification and accountable acceptance.

## Assets

| Asset group | Included assets | Integrity/confidentiality posture |
| --- | --- | --- |
| Identity and authority | User identities, credentials, Sessions, Guardian relationships, consent, memberships, assignments | Restricted; explicit scoped authority and lifecycle |
| Competition truth | Configuration, Rulesets, Participant Snapshots, Judge assignments, Scores, Results, Appeals | Authoritative MySQL, exact/versioned, no silent mutation |
| Trusted references/records | Qur’an releases, Certificates, signing keys, organization verification, legacy provenance | Restricted governance, hashes/signatures, history |
| Media and safety | Evidence masters, Minor media, derivatives, moderation evidence, privacy requests | Private quarantine/restricted by default |
| Operations | Audit, backups, configuration, secrets, live projections, outbox/queues | Audit/backup/secrets separated; projections non-authoritative |

## Threat actors

Anonymous attacker; compromised user; malicious competitor/Guardian/Judge/Organizer; over-privileged administrator; support/platform insider; compromised integration; malicious upload source; bot/credential-stuffing network; database/supply-chain/ransomware actor; social abuser; child-safety offender; and accidental operator error.

## STRIDE threat analysis

| Threat ID | Title | Threat Actor | Target Asset | Entry Point | Trust Boundary | Prerequisites | Attack or Failure Description | Potential Impact | Criticality | Affected Requirements | Existing Planned Controls | Detection | Response | Residual Risk | Risk Record | Planned Mitigation Phase | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-THR-001 | Account takeover | Credential-stuffing attacker | Credentials; account | Login/recovery | Internet to Identity | Known/reused credential or social engineering | Attacker gains account authority. | Unauthorized access, fraud, privacy loss | Critical | QMDB-NFR-IAM-001 | QMDB-CTL-002; QMDB-CTL-020 | Auth anomaly/Session audit | Contain account, revoke Sessions/factors, investigate | High pending parameterized policy | QMDB-RSK-002 | P2 | Proposed |
| QMDB-THR-002 | Session theft | Anonymous attacker | Session; account | Cookie/browser/device | Browser to QMDB | Malware, XSS, network/device compromise | Stolen Session is replayed. | Persistent unauthorized access | Critical | QMDB-NFR-IAM-002 | QMDB-CTL-003; QMDB-CTL-020 | Session anomaly/inventory | Revoke/rotate and notify | Moderate | QMDB-RSK-002 | P2 | Proposed |
| QMDB-THR-003 | Password reset abuse | Anonymous attacker | Account; recovery channel | Recovery endpoint | Internet to Identity | Target identifier/channel knowledge | Attacker enumerates or captures reset. | Account takeover and harassment | Critical | QMDB-NFR-IAM-001 | QMDB-CTL-002; QMDB-CTL-020 | Recovery rate/anomaly audit | Invalidate token, contain account | Moderate | QMDB-RSK-002 | P2 | Proposed |
| QMDB-THR-004 | MFA reset abuse | Compromised user or insider | MFA; privileged account | Factor-recovery workflow | User/support to Identity | Weak evidence or collusion | Factor reset bypasses assurance. | Privileged compromise | Critical | QMDB-NFR-IAM-001 | QMDB-CTL-002; QMDB-CTL-004; QMDB-CTL-020 | Reset approval/anomaly | Hold reset, revoke Sessions, review | High | QMDB-RSK-002 | P2 | Proposed |
| QMDB-THR-005 | Cross-Workspace data access | Compromised user | Tenant records | Repository/API/export | Workspace trust boundary | Valid account in another Workspace | Workspace identifier/context is manipulated. | Cross-tenant privacy breach | Critical | QMDB-NFR-TEN-001 | QMDB-CTL-005; QMDB-CTL-020 | Cross-tenant denial alerts | Contain account/client and assess breach | Moderate | QMDB-RSK-001 | P2 | Proposed |
| QMDB-THR-006 | Insecure direct object reference | Authenticated attacker | Private resource | Public/resource ID endpoint | Presentation to policy/resource | Guessable identifier | Public ID is used as authority. | Record disclosure or mutation | Critical | QMDB-NFR-SEC-001; QMDB-NFR-TEN-001 | QMDB-CTL-005; QMDB-CTL-020 | Denied resource audit | Revoke Session and investigate enumeration | Moderate | QMDB-RSK-001 | P2 | Proposed |
| QMDB-THR-007 | Role escalation | Compromised user | Roles; official records | Membership/role admin | User to privileged policy | Missing approval or vertical check | Actor grants or invokes higher role. | Systemic unauthorized action | Critical | QMDB-NFR-SEC-001 | QMDB-CTL-004; QMDB-CTL-020 | Privilege-change alert/audit | Revoke grant, restore affected records | High | QMDB-RSK-002 | P2 | Proposed |
| QMDB-THR-008 | Scope escalation | Malicious organizer | Geography/competition scope | Assignment/admin API | Scoped authority boundary | Broad/missing resource policy | Valid role is applied beyond scope. | Cross-organization/result impact | Critical | QMDB-NFR-SEC-001; QMDB-NFR-TEN-001 | QMDB-CTL-004; QMDB-CTL-005; QMDB-CTL-020 | Scope denial/change alert | Suspend assignment and reconcile | Moderate | QMDB-RSK-001 | P2 | Proposed |
| QMDB-THR-009 | Unauthorized score submission | Malicious or compromised Judge | Score sheet; result | Scoring command | Judge to scoring domain | Missing assignment/conflict/state check | Unassigned actor submits score. | Competition integrity failure | Critical | QMDB-NFR-SEC-001; QMDB-NFR-DAT-002 | QMDB-CTL-005; QMDB-CTL-010; QMDB-CTL-020 | Score denial/audit | Block, contain account, inspect panel | Moderate | QMDB-RSK-003 | P6 | Proposed |
| QMDB-THR-010 | Score modification | Judge or organizer | Submitted score | Score update/reopen | Scoring state boundary | Silent update path or concurrency flaw | Submitted exact score is overwritten. | Outcome tampering | Critical | QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 | QMDB-CTL-008; QMDB-CTL-010; QMDB-CTL-014 | Version/audit reconciliation | Restore version, suspend actor, recalculate | Moderate | QMDB-RSK-003 | P6 | Proposed |
| QMDB-THR-011 | Ruleset manipulation | Malicious organizer | Ruleset version | Ruleset approval/import | Competition governance boundary | Executable/unapproved change | Rules change affects scoring secretly. | Systemic unfair results | Critical | QMDB-NFR-DAT-002 | QMDB-CTL-010; QMDB-CTL-014; QMDB-CTL-020 | Checksum/version approval alerts | Freeze competition, restore approved version | Moderate | QMDB-RSK-003 | P5 | Proposed |
| QMDB-THR-012 | Result manipulation | Over-privileged administrator | Final result | Finalization/correction | Approval and record-state boundary | Self-approval or silent mutation | Result is finalized/corrected without authority. | National trust and certificate harm | Critical | QMDB-NFR-SEC-001; QMDB-NFR-DAT-002 | QMDB-CTL-004; QMDB-CTL-010; QMDB-CTL-014 | Result/audit/certificate reconciliation | Withdraw projection, restore/supersede, investigate | High | QMDB-RSK-004 | P7 | Proposed |
| QMDB-THR-013 | Certificate forgery | Anonymous attacker | Certificate | Public document/QR | Public verification boundary | Copied QR or fabricated artifact | Counterfeit appears authentic. | Fraud and reputational harm | Critical | QMDB-NFR-CRY-001 | QMDB-CTL-013; QMDB-CTL-029; QMDB-CTL-020 | Verification failures/spike | Mark invalid, investigate campaign | Moderate | QMDB-RSK-005 | P8 | Proposed |
| QMDB-THR-014 | Signing-key compromise | Insider or supply-chain attacker | Signing private key | KMS/signing service | Application to key custody | Secret exposure or excessive access | Attacker signs false artifacts. | System-wide authenticity loss | Critical | QMDB-NFR-CRY-001; QMDB-NFR-INF-002 | QMDB-CTL-013; QMDB-CTL-022; QMDB-CTL-020 | KMS/access/signature anomaly | Revoke/rotate, halt issuance, publish trust update | High | QMDB-RSK-006 | P8 | Proposed |
| QMDB-THR-015 | Audit deletion | Privileged insider | Audit events | Database/storage admin | Application to audit store | Excessive DB access | Evidence rows/checkpoints are deleted. | Accountability loss | Critical | QMDB-NFR-AUD-001 | QMDB-CTL-014; QMDB-CTL-019; QMDB-CTL-020 | Chain/checkpoint failure | Preserve external evidence, contain access, recover | Moderate | QMDB-RSK-015 | P12 | Proposed |
| QMDB-THR-016 | Complete audit-chain rewrite | Platform operator insider | Audit history/checkpoints | DB and checkpoint storage | Separate custody boundary | Control of all copies/keys | History and hashes are recomputed. | False clean history | Critical | QMDB-NFR-AUD-001 | QMDB-CTL-014; QMDB-CTL-020; QMDB-CTL-023 | External checkpoint mismatch | Declare incident, recover, independent investigation | High | QMDB-RSK-015 | P12 | Proposed |
| QMDB-THR-017 | Canonical Qur’an text alteration | Malicious administrator | Qur’an Text Release | Import/activation/storage | Restricted domain governance | Bypassed approval/checksum | Canonical reference is changed. | Religious, scoring and trust harm | Critical | QMDB-NFR-DAT-002; QMDB-NFR-L10-001 | QMDB-CTL-028; QMDB-CTL-014; QMDB-CTL-020 | Checksum/diff/activation audit | Withdraw release, restore prior, qualified review | Moderate | QMDB-RSK-007 | P4 | Proposed |
| QMDB-THR-018 | Malicious media upload | Malicious upload source | Worker/object store/users | Upload endpoint | Internet to quarantine | Upload authority or endpoint abuse | Malware/polyglot/spoofed content enters pipeline. | Execution or harmful publication | Critical | QMDB-NFR-MED-001 | QMDB-CTL-012; QMDB-CTL-020 | Malware/type/resource alerts | Quarantine, block source, investigate | Moderate | QMDB-RSK-011 | P9 | Proposed |
| QMDB-THR-019 | Media-worker escape | Malicious upload source | Worker host/secrets/network | FFmpeg/transcode | Quarantine to isolated worker | Parser exploit | Media code escapes sandbox. | Infrastructure/data compromise | Critical | QMDB-NFR-MED-001; QMDB-NFR-INF-001 | QMDB-CTL-012; QMDB-CTL-020; QMDB-CTL-021 | Runtime/container/network anomaly | Kill/isolate workers, rotate secrets, rebuild | High | QMDB-RSK-011 | P9 | Proposed |
| QMDB-THR-020 | Private-media disclosure | Anonymous attacker or insider | Evidence/minor media | Object URL/CDN/support | Private store to delivery | Guessable URL, cache, broad support | Restricted original becomes public. | Privacy/safety harm | Critical | QMDB-NFR-MED-001; QMDB-NFR-PRI-002 | QMDB-CTL-012; QMDB-CTL-015; QMDB-CTL-020 | Access/CDN leak detection | Revoke/purge, contain, notify assessment | Moderate | QMDB-RSK-009 | P9 | Proposed |
| QMDB-THR-021 | Minor location disclosure | Social abuser | Minor identity/location | Profile/search/result/media | Private to public projection | Overbroad field or inference | Precise/identifying location is exposed. | Physical child-safety risk | Critical | QMDB-NFR-PRI-003; QMDB-NFR-CHD-002 | QMDB-CTL-015; QMDB-CTL-016; QMDB-CTL-020 | Public field/privacy scan | Remove/purge and safety escalation | Moderate | QMDB-RSK-008 | P10 | Proposed |
| QMDB-THR-022 | Guardian-consent bypass | Compromised Guardian/organizer | Consent/minor media | Publication/competition command | Guardian/Minor policy boundary | Missing/stale/disputed consent check | Sensitive action proceeds without valid authority. | Child privacy/safety harm | Critical | QMDB-NFR-CHD-001; QMDB-NFR-CHD-003 | QMDB-CTL-016; QMDB-CTL-020 | Consent/publication audit | Restrict visibility/action, investigate relationship | Moderate | QMDB-RSK-010 | P3 | Proposed |
| QMDB-THR-023 | Social harassment | Social abuser | Users/Minors | Comments/interaction/reporting | Public/community boundary | Interaction access | Target is harassed or groomed. | Safety and dignity harm | High | QMDB-NFR-CHD-002 | QMDB-CTL-017; QMDB-CTL-020 | Reports/moderation signals | Block/restrict, preserve evidence, safety response | Moderate | QMDB-RSK-012 | P10 | Proposed |
| QMDB-THR-024 | Report flooding | Bot network | Moderation capacity | Report endpoint | Public to moderation | Automated identities/network | False reports exhaust reviewers. | Real safety reports delayed | High | QMDB-NFR-CHD-002 | QMDB-CTL-017; QMDB-CTL-018; QMDB-CTL-020 | Rate/backlog/anomaly | Throttle/deduplicate while preserving credible reports | Moderate | QMDB-RSK-012 | P10 | Proposed |
| QMDB-THR-025 | Moderator abuse | Malicious moderator | Content/users/evidence | Moderation decision | Moderator to case/content | Overbroad scope or retaliation | Moderator hides/restores/punishes improperly. | Safety, fairness, privacy harm | Critical | QMDB-NFR-CHD-002 | QMDB-CTL-004; QMDB-CTL-017; QMDB-CTL-020 | Decision patterns/appeals/audit | Suspend scope, reverse through case, review | Moderate | QMDB-RSK-013 | P10 | Proposed |
| QMDB-THR-026 | API key theft | External attacker | API credential/data | Client storage/API | Client to integration boundary | Secret leakage | Stolen client accesses scoped API. | Data disclosure/abuse | Critical | QMDB-NFR-API-001; QMDB-NFR-INF-002 | QMDB-CTL-011; QMDB-CTL-022; QMDB-CTL-020 | Client anomaly/rate | Revoke/rotate/suspend and investigate | Moderate | QMDB-RSK-022 | P12 | Proposed |
| QMDB-THR-027 | Webhook replay | Compromised integration | Webhook effect | Webhook receiver | External integration boundary | Captured signed event | Valid old delivery is replayed. | Duplicate action/confusion | High | QMDB-NFR-API-001 | QMDB-CTL-011; QMDB-CTL-020 | Timestamp/idempotency audit | Reject replay, rotate on compromise | Low | QMDB-RSK-022 | P12 | Proposed |
| QMDB-THR-028 | Server-side request forgery | Anonymous attacker | Internal network/metadata | URL/import/webhook input | Application egress | User-controlled outbound target | Server probes internal services. | Secret/infrastructure compromise | Critical | QMDB-NFR-SEC-002 | QMDB-CTL-007; QMDB-CTL-020 | Egress/blocked-target logs | Block egress, contain service, rotate exposed secrets | Moderate | QMDB-RSK-022 | P2 | Proposed |
| QMDB-THR-029 | SQL injection | Anonymous/authenticated attacker | MySQL data | Forms/API/query filters | Input to PDO/MySQL | Unsafe query composition | Input changes SQL structure. | Mass disclosure/corruption | Critical | QMDB-NFR-SEC-002; QMDB-NFR-DAT-001 | QMDB-CTL-006; QMDB-CTL-008; QMDB-CTL-020 | WAF/app/DB anomaly | Block, investigate, restore if needed | Low with enforced prepared statements | QMDB-RSK-001 | P2 | Proposed |
| QMDB-THR-030 | Cross-site scripting | Malicious user | Browser Session/data | Rendered content | Stored/reflected content to browser | Unsafe output/context | Script executes in another browser. | Session theft/unauthorized action | Critical | QMDB-NFR-SEC-002 | QMDB-CTL-006; QMDB-CTL-007; QMDB-CTL-020 | CSP reports/security tests | Remove content, revoke Sessions, patch | Moderate | QMDB-RSK-002 | P2 | Proposed |
| QMDB-THR-031 | Cross-site request forgery | External site attacker | Authenticated action | State-changing endpoint | Browser origin to QMDB | Victim Session | Victim browser submits unwanted action. | Unauthorized mutation | Critical | QMDB-NFR-SEC-002 | QMDB-CTL-007; QMDB-CTL-020 | CSRF rejection audit | Reject, review affected action | Low | QMDB-RSK-002 | P2 | Proposed |
| QMDB-THR-032 | Path traversal | Anonymous attacker | Host/object files | Path/file endpoint | Input to filesystem/object key | Unsafe path normalization | Input escapes allowed namespace. | Secret/code/data disclosure | Critical | QMDB-NFR-SEC-002 | QMDB-CTL-006; QMDB-CTL-020 | Blocked path/file audit | Contain endpoint, rotate exposed secrets | Low | QMDB-RSK-022 | P2 | Proposed |
| QMDB-THR-033 | Command injection | Malicious uploader/user | Worker/host | Process invocation | Application to OS/FFmpeg | Untrusted shell composition | Input alters command execution. | Host compromise | Critical | QMDB-NFR-SEC-002; QMDB-NFR-MED-001 | QMDB-CTL-006; QMDB-CTL-012; QMDB-CTL-020 | Runtime/process anomaly | Kill/isolate, rotate, rebuild | Low with no shell composition | QMDB-RSK-011 | P9 | Proposed |
| QMDB-THR-034 | Dependency compromise | Supply-chain attacker | Source/build/runtime | Composer/package/CI | External package to build | Compromised maintainer/registry | Malicious code enters artifact. | Systemic compromise | Critical | QMDB-NFR-SUP-001; QMDB-NFR-SUP-002 | QMDB-CTL-021; QMDB-CTL-020 | Audit/provenance/runtime signals | Block/rollback, patch, incident review | Moderate | QMDB-RSK-022 | P1 | Proposed |
| QMDB-THR-035 | Secret leakage | Developer/attacker | Credentials/keys | Source/image/log/config | Engineering to runtime custody | Accidental commit/log or CI breach | Secret becomes accessible. | Privilege/data compromise | Critical | QMDB-NFR-INF-002 | QMDB-CTL-022; QMDB-CTL-020; QMDB-CTL-021 | Secret scan/access anomaly | Revoke/rotate, purge exposure, investigate | Moderate | QMDB-RSK-006 | P1 | Proposed |
| QMDB-THR-036 | Backup theft | Insider/external attacker | Backup | Backup store/transfer | Production to backup custody | Stolen credential/storage | Attacker copies backup. | Bulk historical disclosure | Critical | QMDB-NFR-DRC-001 | QMDB-CTL-022; QMDB-CTL-023; QMDB-CTL-020 | Backup access/integrity alert | Revoke, contain, assess notification | Moderate | QMDB-RSK-019 | P12 | Proposed |
| QMDB-THR-037 | Ransomware | Ransomware actor | Production, backups, artifacts | Endpoint/admin/supply chain | Operations boundary | Compromised privileged host | Data/services encrypted/destroyed. | National outage/data loss | Critical | QMDB-NFR-DRC-001; QMDB-NFR-DRC-002 | QMDB-CTL-021; QMDB-CTL-023; QMDB-CTL-026; QMDB-CTL-020 | Endpoint/backup/service anomalies | Isolate, declare disaster, recover immutable copies | High | QMDB-RSK-021 | P12 | Proposed |
| QMDB-THR-038 | Redis exposure | Anonymous attacker | Cache/Streams | Redis network port | Network to Redis | Public route/default ACL | Attacker reads/writes Redis. | Leakage, queue/cache manipulation | Critical | QMDB-NFR-SEC-003 | QMDB-CTL-009; QMDB-CTL-020 | Network/ACL/command anomaly | Isolate, rotate, rebuild and reconcile | Low when private/ACL | QMDB-RSK-017 | P7 | Proposed |
| QMDB-THR-039 | Queue poisoning | Malicious integration/upload | Workers/queue | API/outbox/queue payload | Producer to consumer | Weak validation/poison retry | Message crashes or controls consumer. | Backlog/starvation/unsafe action | High | QMDB-NFR-SCL-001; QMDB-NFR-RES-002 | QMDB-CTL-009; QMDB-CTL-018; QMDB-CTL-020 | Failure/dead-letter/age metrics | Quarantine poison, pause consumer, fix/replay | Moderate | QMDB-RSK-018 | P7 | Proposed |
| QMDB-THR-040 | Duplicate event processing | Failure/retry condition | Official records/notifications | Outbox/consumer | Delivery boundary | At-least-once delivery | Same event is processed repeatedly. | Duplicate business effect | Critical | QMDB-NFR-SEC-003; QMDB-NFR-SCL-001 | QMDB-CTL-009; QMDB-CTL-010; QMDB-CTL-020 | Idempotency/reconciliation | Suppress duplicate and reconcile | Low | QMDB-RSK-018 | P7 | Proposed |
| QMDB-THR-041 | Offline-event replay | Malicious Judge/device holder | Offline scores | Synchronization API | Venue to central | Captured signed event/package | Old event is resubmitted. | Duplicate/altered score | Critical | QMDB-NFR-RES-003 | QMDB-CTL-027; QMDB-CTL-020 | Sequence/idempotency conflict | Reject/quarantine and investigate device | Moderate | QMDB-RSK-026 | P13 | Proposed |
| QMDB-THR-042 | Venue Edge Node compromise | Attacker/operator | Packages, local drafts, device keys | Edge node/device | Central to venue boundary | Stolen/exploited device | Attacker alters/reads local work. | Score/privacy compromise | Critical | QMDB-NFR-RES-003 | QMDB-CTL-027; QMDB-CTL-022; QMDB-CTL-020 | Tamper/device/sync anomaly | Revoke device/package, quarantine events | High | QMDB-RSK-026 | P13 | Proposed |
| QMDB-THR-043 | Competition denial of service | Bot network/ransomware actor | Tier 1 services | Public/auth/API/network | Internet/dependency to runtime | Traffic or dependency failure | Critical operations are exhausted. | Live competition outage | Critical | QMDB-NFR-PER-001; QMDB-NFR-RES-001 | QMDB-CTL-018; QMDB-CTL-024; QMDB-CTL-026; QMDB-CTL-020 | Rate/latency/saturation alerts | Shed lower tiers, edge mitigation, continuity | High pending capacity approval | QMDB-RSK-024 | P13 | Proposed |
| QMDB-THR-044 | Social workload starvation of scoring | Bot/social users | Scoring capacity | Feed/comments/media | Shared resource tier | Unisolated workload | Social traffic consumes critical resources. | Score delays/outage | Critical | QMDB-NFR-PER-001 | QMDB-CTL-018; QMDB-CTL-024; QMDB-CTL-020 | Tier SLI/resource comparison | Throttle/disable social and restore scoring | Moderate | QMDB-RSK-023 | P10 | Proposed |
| QMDB-THR-045 | Insider break-glass misuse | Privileged insider | All restricted assets | Emergency access | Operations to protected systems | Emergency grant abuse/collusion | Emergency authority is used outside incident. | Bulk disclosure or official mutation | Critical | QMDB-NFR-SEC-001; QMDB-NFR-INF-002 | QMDB-CTL-004; QMDB-CTL-020; QMDB-CTL-030 | Activation/action/review alerts | Terminate grant, preserve evidence, incident review | High | QMDB-RSK-015 | P2 | Proposed |
| QMDB-THR-046 | Support insider browsing and inaccessible controls | Support insider or accidental operator | Personal/official data; critical UI | Support access/user interface | Support/presentation boundary | Overbroad fields or unusable safeguard | Insider browses data or user cannot safely operate control. | Privacy breach or critical user error | Critical | QMDB-NFR-SEC-001; QMDB-NFR-ACC-001; QMDB-NFR-PRI-002 | QMDB-CTL-025; QMDB-CTL-030; QMDB-CTL-020 | Support/a11y audit and complaints | Revoke access, contain, correct/retest interface | Moderate | QMDB-RSK-014 | P12 | Proposed |

## Attack trees

### 1. Alter an official competition result

```mermaid
flowchart TD
    G1["Goal: Alter an official competition result"]
    G1 --> P1_1["Compromise Judge account"]
    G1 --> P1_2["Bypass assignment/state"]
    G1 --> P1_3["Modify submitted score"]
    G1 --> P1_4["Manipulate Ruleset"]
    G1 --> P1_5["Abuse finalization/correction"]
    G1 --> P1_6["Erase audit"]
``

Primary control set: QMDB-CTL-002; QMDB-CTL-004; QMDB-CTL-010; QMDB-CTL-014.

### 2. Access another Workspace’s data

```mermaid
flowchart TD
    G2["Goal: Access another Workspace’s data"]
    G2 --> P2_1["Manipulate workspace_id"]
    G2 --> P2_2["Guess public resource ID"]
    G2 --> P2_3["Use stale membership"]
    G2 --> P2_4["Poison cache/job context"]
    G2 --> P2_5["Over-scope export"]
``

Primary control set: QMDB-CTL-005; QMDB-CTL-008; QMDB-CTL-009; QMDB-CTL-020.

### 3. Forge a certificate

```mermaid
flowchart TD
    G3["Goal: Forge a certificate"]
    G3 --> P3_1["Copy QR onto counterfeit"]
    G3 --> P3_2["Alter payload"]
    G3 --> P3_3["Steal signing key"]
    G3 --> P3_4["Serve stale/revoked status"]
    G3 --> P3_5["Compromise verification endpoint"]
``

Primary control set: QMDB-CTL-013; QMDB-CTL-029; QMDB-CTL-020.

### 4. Publish restricted Minor media

```mermaid
flowchart TD
    G4["Goal: Publish restricted Minor media"]
    G4 --> P4_1["Bypass Minor status"]
    G4 --> P4_2["Forge Guardian relation"]
    G4 --> P4_3["Reuse stale consent"]
    G4 --> P4_4["Abuse publisher"]
    G4 --> P4_5["Leak private object/CDN"]
``

Primary control set: QMDB-CTL-012; QMDB-CTL-016; QMDB-CTL-017; QMDB-CTL-020.

### 5. Alter canonical Qur’an reference data

```mermaid
flowchart TD
    G5["Goal: Alter canonical Qur’an reference data"]
    G5 --> P5_1["Gain import authority"]
    G5 --> P5_2["Bypass independent approval"]
    G5 --> P5_3["Replace artifact after review"]
    G5 --> P5_4["Rewrite checksum/audit"]
    G5 --> P5_5["Misrender public text"]
``

Primary control set: QMDB-CTL-004; QMDB-CTL-014; QMDB-CTL-028.

### 6. Take over a privileged Judge account

```mermaid
flowchart TD
    G6["Goal: Take over a privileged Judge account"]
    G6 --> P6_1["Credential stuffing"]
    G6 --> P6_2["Reset abuse"]
    G6 --> P6_3["MFA reset fraud"]
    G6 --> P6_4["Session theft"]
    G6 --> P6_5["Support impersonation"]
``

Primary control set: QMDB-CTL-002; QMDB-CTL-003; QMDB-CTL-004; QMDB-CTL-020.

### 7. Erase or rewrite audit evidence

```mermaid
flowchart TD
    G7["Goal: Erase or rewrite audit evidence"]
    G7 --> P7_1["Delete DB events"]
    G7 --> P7_2["Control backup"]
    G7 --> P7_3["Recompute chain"]
    G7 --> P7_4["Replace checkpoint"]
    G7 --> P7_5["Suppress alerts"]
``

Primary control set: QMDB-CTL-014; QMDB-CTL-020; QMDB-CTL-023.

### 8. Disrupt national live competition operations

```mermaid
flowchart TD
    G8["Goal: Disrupt national live competition operations"]
    G8 --> P8_1["Flood public edge"]
    G8 --> P8_2["Starve scoring with social load"]
    G8 --> P8_3["Crash queues with poison"]
    G8 --> P8_4["Disable MySQL/Redis"]
    G8 --> P8_5["Compromise venue/DR"]
``

Primary control set: QMDB-CTL-018; QMDB-CTL-023; QMDB-CTL-024; QMDB-CTL-026; QMDB-CTL-027.

## Validation and change control

Every critical threat has at least one preventive control plus QMDB-CTL-020 and/or a recovery control; every threat maps a risk and phase. A material boundary, asset, actor, data-flow, provider, offline or cryptographic change reopens modelling.

