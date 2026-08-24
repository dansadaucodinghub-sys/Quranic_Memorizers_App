# Security and Zero-Trust Requirements

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B03 |
| Document Title | Security and Zero-Trust Requirements |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Security Architecture and Identity Governance |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed for governance approval; inherited locked decisions remain binding |
| Related Documents | [B03 index](../P0-B03-non-functional-requirements-index.md); [Traceability](../P0-B03-traceability-matrix.md); [Product constitution](../../project/product-constitution.md) |

## Purpose

Define implementation-neutral security, identity, tenancy, browser, database, Redis, API, media, and cryptographic obligations.

## Scope

All QMDB trust boundaries, privileged and ordinary identities, authoritative stores, projections, integrations, media workers, and operational access.

## Interpretation and control posture

Requirements are implementation-neutral unless QMDB-BL-001 locks a technology. “Approved Baseline” identifies a direct locked-baseline obligation; “Proposed” requires governance approval; “Parameter Pending” is enforceable in structure and measurement but its target remains governed by the parameter register. Derived stores never replace authoritative MySQL records. Business policy and technical enforcement are both identified through traceability; neither creates legal or religious authority.

## Coverage profile

Deny by default; least privilege; explicit authorization; separation of duties; adaptive password hashing; MFA; passkeys; step-up and recovery; server-side sessions; secure cookies; RBAC, ABAC, resource and tenant policies; input/output/browser defenses; MySQL and Redis hardening; API and webhook protection; private media processing; approved cryptography; independent verification.

## QMDB-NFR-SEC-001 — Deny-by-default complete mediation

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-SEC-001 |
| Title | Deny-by-default complete mediation |
| Quality Attribute | Security |
| Requirement Statement | The QMDB application boundary shall deny every operation until authenticated identity, current authority, resource state, tenant, geography, conflict, time-window, and step-up policies explicitly permit it. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Security Critical |
| Applicable System Components | Presentation, Application, Domain, authorization policies |
| Applicable Actors | All human and service actors |
| Stimulus or Trigger | Any protected request or policy-context change |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Re-evaluate the complete policy at every boundary and record significant denial without revealing protected facts. |
| Response Measure | All protected routes and commands have positive and negative policy tests; denied requests produce no protected state change. |
| Measurement Source | Authorization test telemetry and Audit Events |
| Failure Behavior | Deny safely, invalidate stale authority, and present a non-enumerating error. |
| Security or Privacy Impact | Prevents horizontal/vertical escalation and cross-scope disclosure. |
| Dependencies | QMDB-BL-001; QMDB-FR-IAM-004; QMDB-FR-TEN-001; QMDB-FR-OPS-002 |
| Open Parameter References | None |
| Related Functional Requirements | QMDB-FR-IAM-004; QMDB-FR-TEN-001; QMDB-FR-OPS-002 |
| Related Use Cases | QMDB-UC-009; QMDB-UC-060 |
| Related Business Invariants | INV-001; INV-002; INV-005 |
| Related Threats | QMDB-THR-007; QMDB-THR-009 |
| Related Controls | QMDB-CTL-004; QMDB-CTL-005 |
| Planned Implementation Phase | P2 — Identity, Security, and Tenant Isolation |
| Verification Method | Architecture review; authorization and tenant-isolation tests |
| Required Evidence | Route/command inventory, policy test report, denied-action audit sample |
| Acceptance Criteria | No protected operation succeeds with missing, stale, conflicting, or out-of-scope authority. |
| Status | Approved Baseline |

## QMDB-NFR-IAM-001 — Risk-aware authentication and recovery

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-IAM-001 |
| Title | Risk-aware authentication and recovery |
| Quality Attribute | Authentication Security |
| Requirement Statement | The QMDB identity boundary shall use an approved adaptive password-hashing library, parameter re-evaluation, privileged MFA, passkey-capable contracts, step-up controls, abuse-resistant recovery, and non-enumerating responses. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Security Critical |
| Applicable System Components | Identity and Access, Notifications, Security Operations |
| Applicable Actors | Account holder, privileged actor, recovery operator |
| Stimulus or Trigger | Authentication, recovery, MFA reset, anomaly, or sensitive-action request |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Authenticate at the required assurance, rotate credentials or factors under control, rate-limit abuse, notify the subject, and revoke affected Sessions when risk requires. |
| Response Measure | Hash benchmark and assurance parameters resolve through QMDB-PAR-024, QMDB-PAR-026 and QMDB-PAR-027; all recovery tokens are single-use and expire by policy. |
| Measurement Source | Authentication metrics, recovery audit, benchmark evidence |
| Failure Behavior | Reject or hold the attempt without disclosing account existence or downgrading assurance. |
| Security or Privacy Impact | Reduces account takeover, credential stuffing, reset fraud, and privileged compromise. |
| Dependencies | QMDB-BL-001; QMDB-FR-IAM-003; QMDB-FR-IAM-004; QMDB-FR-IAM-005; QMDB-FR-IAM-006 |
| Open Parameter References | QMDB-PAR-024; QMDB-PAR-026; QMDB-PAR-027 |
| Related Functional Requirements | QMDB-FR-IAM-003; QMDB-FR-IAM-004; QMDB-FR-IAM-005; QMDB-FR-IAM-006 |
| Related Use Cases | QMDB-UC-003; QMDB-UC-004; QMDB-UC-005 |
| Related Business Invariants | INV-003; INV-004 |
| Related Threats | QMDB-THR-001; QMDB-THR-003; QMDB-THR-004 |
| Related Controls | QMDB-CTL-002; QMDB-CTL-020 |
| Planned Implementation Phase | P2 — Identity, Security, and Tenant Isolation |
| Verification Method | Authentication security tests; recovery abuse exercise; configuration review |
| Required Evidence | Hash benchmark, MFA/step-up matrix, token reuse/expiry tests, anomaly alerts |
| Acceptance Criteria | Privileged access cannot bypass MFA/step-up and a consumed or expired recovery token never authenticates. |
| Status | Parameter Pending |

## QMDB-NFR-IAM-002 — Server-side revocable sessions

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-IAM-002 |
| Title | Server-side revocable sessions |
| Quality Attribute | Session Security |
| Requirement Statement | The QMDB session boundary shall keep browser Sessions server-side, use Secure, HttpOnly and approved SameSite cookies, rotate on authentication and privilege change, expose device inventory, and support immediate revocation without URL or local-storage bearer tokens. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Security Critical |
| Applicable System Components | Identity and Access, browser presentation, session store |
| Applicable Actors | Authenticated user, security operator |
| Stimulus or Trigger | Session creation, privilege change, idle/absolute limit, suspicious use, or revocation |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Rotate or invalidate the Session, remove continued authority, and notify/audit sensitive termination without logging identifiers. |
| Response Measure | Idle and absolute limits resolve through QMDB-PAR-024 and QMDB-PAR-025; revoked Sessions fail on the next mediated request. |
| Measurement Source | Session store, security logs, end-to-end tests |
| Failure Behavior | Terminate safely and require fresh authentication; never continue on unavailable revocation state for privileged actions. |
| Security or Privacy Impact | Limits fixation, theft persistence, URL leakage, and stale privilege. |
| Dependencies | QMDB-BL-001; QMDB-FR-SES-001; QMDB-FR-SES-002; QMDB-FR-SES-003 |
| Open Parameter References | QMDB-PAR-024; QMDB-PAR-025 |
| Related Functional Requirements | QMDB-FR-SES-001; QMDB-FR-SES-002; QMDB-FR-SES-003 |
| Related Use Cases | QMDB-UC-006 |
| Related Business Invariants | INV-003; INV-004 |
| Related Threats | QMDB-THR-002 |
| Related Controls | QMDB-CTL-003; QMDB-CTL-020 |
| Planned Implementation Phase | P2 — Identity, Security, and Tenant Isolation |
| Verification Method | Cookie inspection; fixation, rotation, expiry, and revocation tests |
| Required Evidence | Browser evidence, session-store evidence, negative test report |
| Acceptance Criteria | A revoked, expired, pre-rotation, URL-injected, or local-storage token cannot authorize a request. |
| Status | Parameter Pending |

## QMDB-NFR-TEN-001 — Workspace-aware authorization integrity

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-TEN-001 |
| Title | Workspace-aware authorization integrity |
| Quality Attribute | Tenant Isolation |
| Requirement Statement | The QMDB tenancy boundary shall derive Workspace context from trusted membership and scope, enforce workspace_id on tenant-owned access, validate composite Workspace relationships, and use tenant-aware cache, job, export, and search keys. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Security Critical |
| Applicable System Components | All repositories, policies, caches, queues, exports, search projections |
| Applicable Actors | Workspace member, administrator, service actor |
| Stimulus or Trigger | Any tenant-owned read, write, job, projection, relationship, or export |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Apply Workspace scope at repository and relationship boundaries and reject missing, arbitrary, mismatched, or stale context. |
| Response Measure | Every tenant-owned repository and job has cross-workspace denial tests and every relevant relational design has composite integrity evidence. |
| Measurement Source | Repository tests, MySQL constraint tests, cache/job inspection |
| Failure Behavior | Reject before access or mutation; emit scoped security/audit evidence. |
| Security or Privacy Impact | Prevents cross-tenant leakage and corruption. |
| Dependencies | QMDB-BL-001; QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-RPT-002 |
| Open Parameter References | None |
| Related Functional Requirements | QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-RPT-002 |
| Related Use Cases | QMDB-UC-008; QMDB-UC-056; QMDB-UC-057 |
| Related Business Invariants | INV-005; INV-006 |
| Related Threats | QMDB-THR-005; QMDB-THR-006 |
| Related Controls | QMDB-CTL-005; QMDB-CTL-010 |
| Planned Implementation Phase | P2 — Identity, Security, and Tenant Isolation |
| Verification Method | Architecture, repository, authorization, cache-key, queue, and constraint tests |
| Required Evidence | Tenant-owned inventory and cross-workspace test report |
| Acceptance Criteria | Changing a public or Workspace identifier never expands access or creates a cross-Workspace relationship. |
| Status | Approved Baseline |

## QMDB-NFR-SEC-002 — Input, output, and browser defense

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-SEC-002 |
| Title | Input, output, and browser defense |
| Quality Attribute | Application Security |
| Requirement Statement | The QMDB web boundary shall validate server-side, encode by output context, use native prepared statements, protect state changes against CSRF, restrict methods, origins, hosts, redirects, paths and request sizes, and apply reviewed browser and edge security headers. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Security Critical |
| Applicable System Components | Nginx, Presentation, Application, PDO repositories, browser |
| Applicable Actors | Public visitor, authenticated user, API client |
| Stimulus or Trigger | Untrusted request, upload, URL, header, serialized data, XML, or rendered value |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Accept only schema-valid input, perform context-safe output, reject injection and traversal paths, and redact production errors. |
| Response Measure | Security tests cover SQL injection, XSS, CSRF, command/path/SSRF/XXE/deserialization risks, smuggling boundaries, CSP, HSTS, Referrer, Permissions, CORS, cache, framing, redirect, host and size controls. |
| Measurement Source | DAST/SAST, browser header capture, API tests |
| Failure Behavior | Reject with a stable safe error and no stack trace, protected state change, outbound request, or unsafe reflection. |
| Security or Privacy Impact | Reduces injection, browser compromise, data leakage, and edge ambiguity. |
| Dependencies | QMDB-BL-001; QMDB-FR-IAM-001; QMDB-FR-INT-001; QMDB-FR-MED-001 |
| Open Parameter References | QMDB-PAR-026 |
| Related Functional Requirements | QMDB-FR-IAM-001; QMDB-FR-INT-001; QMDB-FR-MED-001 |
| Related Use Cases | QMDB-UC-001; QMDB-UC-024; QMDB-UC-048 |
| Related Business Invariants | INV-001; INV-027 |
| Related Threats | QMDB-THR-030; QMDB-THR-031; QMDB-THR-032; QMDB-THR-033; QMDB-THR-034; QMDB-THR-035 |
| Related Controls | QMDB-CTL-006; QMDB-CTL-007 |
| Planned Implementation Phase | P2 — Identity, Security, and Tenant Isolation |
| Verification Method | Static analysis; dynamic security; header/configuration and negative-input tests |
| Required Evidence | Scanner reports, header baseline, PDO configuration, negative test evidence |
| Acceptance Criteria | Representative injection, cross-origin, traversal and malformed requests cannot change query/code structure or leak protected content. |
| Status | Parameter Pending |

## QMDB-NFR-DAT-001 — MySQL authoritative-store security

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-DAT-001 |
| Title | MySQL authoritative-store security |
| Quality Attribute | Data Integrity |
| Requirement Statement | The QMDB MySQL boundary shall use InnoDB, strict SQL modes, utf8mb4, UTC microseconds, native PDO prepared statements, exact DECIMAL scoring, enabled foreign keys, workspace-aware integrity, TLS, least-privilege separated roles, migration-only changes, protected logs, and tested point-in-time recovery. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Security Critical |
| Applicable System Components | MySQL, PDO repositories, migration and backup operations |
| Applicable Actors | Runtime service, migration, reporting, backup, emergency operator |
| Stimulus or Trigger | Connection, transaction, schema change, authoritative read, backup, replication, or recovery |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Enforce constraints and role separation, use primary reads for authoritative transitions, observe replica lag and locks, and preserve binary-log/PITR evidence without media BLOBs. |
| Response Measure | Configuration and schema reviews prove every listed baseline; restore and role tests pass; authoritative score columns contain no floating type. |
| Measurement Source | MySQL configuration, grants, schema metadata, query/restore telemetry |
| Failure Behavior | Fail closed for writes requiring unavailable primary or uncertain authority; retry deadlocks only through bounded idempotent policy. |
| Security or Privacy Impact | Protects official records from injection, privilege abuse, inconsistency, lag and unrecoverable loss. |
| Dependencies | QMDB-BL-001; QMDB-FR-SCR-003; QMDB-FR-RSL-003; QMDB-FR-OPS-004 |
| Open Parameter References | QMDB-PAR-028; QMDB-PAR-017; QMDB-PAR-018 |
| Related Functional Requirements | QMDB-FR-SCR-003; QMDB-FR-RSL-003; QMDB-FR-OPS-004 |
| Related Use Cases | QMDB-UC-036; QMDB-UC-042 |
| Related Business Invariants | INV-008; INV-015; INV-016 |
| Related Threats | QMDB-THR-036; QMDB-THR-039 |
| Related Controls | QMDB-CTL-008; QMDB-CTL-023 |
| Planned Implementation Phase | P1 — Engineering and Repository Foundation |
| Verification Method | Configuration/grant/schema review; constraint, concurrency, replica-lag and restore tests |
| Required Evidence | SQL-mode and grants export, schema inspection, PITR exercise evidence |
| Acceptance Criteria | The application cannot use root, disable integrity ordinarily, store media BLOBs, use floating official scores, or finalize from stale replica data. |
| Status | Approved Baseline |

## QMDB-NFR-SEC-003 — Redis least-privilege and safe degradation

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-SEC-003 |
| Title | Redis least-privilege and safe degradation |
| Quality Attribute | Redis Security |
| Requirement Statement | The QMDB Redis boundary shall remain private, authenticated and ACL-restricted, use TLS where supported, minimize sensitive values, namespace keys by Workspace and purpose, bound expiry and stream retention, and treat cache and stream delivery as non-authoritative. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Operational |
| Applicable System Components | Redis cache, Redis Streams, outbox consumers, live projections |
| Applicable Actors | Application and worker service identities |
| Stimulus or Trigger | Cache access, event publication/consumption, expiry, trimming, backlog, or Redis outage |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Authorize least-privilege commands and keys, enforce idempotent consumers and dead-letter handling, expose backlog health, and degrade without changing official truth. |
| Response Measure | No public network route exists; ACL/TLS/configuration evidence passes; duplicate and outage tests preserve authoritative records. |
| Measurement Source | Redis configuration, ACL evidence, queue metrics, recovery tests |
| Failure Behavior | Bypass optional cache, pause or use controlled fallback delivery, and rebuild from MySQL/outbox without accepting cache as truth. |
| Security or Privacy Impact | Prevents public exposure, tenant mixing, event loss/duplication, and false authoritative state. |
| Dependencies | QMDB-BL-001; QMDB-FR-OPS-003; QMDB-FR-LIV-002; QMDB-FR-AUD-001 |
| Open Parameter References | QMDB-PAR-029 |
| Related Functional Requirements | QMDB-FR-OPS-003; QMDB-FR-LIV-002; QMDB-FR-AUD-001 |
| Related Use Cases | QMDB-UC-062 |
| Related Business Invariants | INV-007; INV-021 |
| Related Threats | QMDB-THR-040; QMDB-THR-041; QMDB-THR-042 |
| Related Controls | QMDB-CTL-009; QMDB-CTL-018 |
| Planned Implementation Phase | P7 — Live Competition, Results, and Appeals |
| Verification Method | Network/ACL/configuration review; duplicate, backlog, trim and outage tests |
| Required Evidence | Network policy, ACL, TLS decision, stream metrics and reconciliation evidence |
| Acceptance Criteria | Redis loss, stale cache, or duplicate delivery cannot mutate or misrepresent authoritative scores. |
| Status | Parameter Pending |

## QMDB-NFR-API-001 — Scoped replay-safe APIs and webhooks

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-API-001 |
| Title | Scoped replay-safe APIs and webhooks |
| Quality Attribute | API Security |
| Requirement Statement | The QMDB integration boundary shall expose versioned OpenAPI 3.1 contracts with explicit client authentication, scopes and tenant context, bounded validated requests, filtered paginated responses, idempotency and replay controls, stable errors, correlation, key rotation, signed timestamped webhooks, delivery audit, and client suspension. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Security Critical |
| Applicable System Components | API, integrations, webhook workers, audit |
| Applicable Actors | API client, webhook subscriber, integration administrator |
| Stimulus or Trigger | API call, key lifecycle change, webhook event, retry, replay, or abuse threshold |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Authenticate and authorize each request, hash or encrypt credentials as appropriate, reject sensitive URL data and replays, deduplicate retries, audit delivery, and suspend unsafe clients. |
| Response Measure | Contract, scope, tenant, size, pagination, rate, idempotency, signature, timestamp, replay, rotation and suspension tests pass. |
| Measurement Source | Gateway/API metrics, OpenAPI validation, webhook ledger |
| Failure Behavior | Reject or quarantine safely; retries remain bounded and no external request directly writes official scores. |
| Security or Privacy Impact | Prevents credential theft, replay, overreach, leakage and authoritative-score bypass. |
| Dependencies | QMDB-BL-001; QMDB-FR-INT-001; QMDB-FR-INT-002; QMDB-FR-INT-003 |
| Open Parameter References | QMDB-PAR-026; QMDB-PAR-029 |
| Related Functional Requirements | QMDB-FR-INT-001; QMDB-FR-INT-002; QMDB-FR-INT-003 |
| Related Use Cases | QMDB-UC-057 |
| Related Business Invariants | INV-002; INV-015; INV-027 |
| Related Threats | QMDB-THR-028; QMDB-THR-029 |
| Related Controls | QMDB-CTL-011; QMDB-CTL-020 |
| Planned Implementation Phase | P12 — Quality, Accessibility, and Production Hardening |
| Verification Method | OpenAPI contract; API authorization, idempotency, replay and webhook security tests |
| Required Evidence | Validated contract, key-lifecycle audit, webhook test report |
| Acceptance Criteria | A duplicated, stale, unscoped, oversized, suspended, or unauthorized integration request fails safely and cannot write an official score. |
| Status | Parameter Pending |

## QMDB-NFR-MED-001 — Quarantined isolated media processing

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-MED-001 |
| Title | Quarantined isolated media processing |
| Quality Attribute | Media Security |
| Requirement Statement | The QMDB media boundary shall authorize short-lived uploads into private quarantine, distrust extensions, detect actual type and malware, validate codecs, hash content, strip metadata, transcode in sandboxed least-privilege workers with bounded resources, and publish only consent-approved derivatives through protected origins. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Safety Critical |
| Applicable System Components | Media application, object storage, FFmpeg workers, CDN, moderation |
| Applicable Actors | Authorized uploader, media worker, reviewer, approved viewer |
| Stimulus or Trigger | Upload, scan, transcode, retry, access, publication, withdrawal, or security report |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Quarantine originals, use opaque keys, keep workers free of database credentials, limit remote imports and retries, preserve evidence integrity, and revoke/purge publication when authority changes. |
| Response Measure | Upload permission, spoof, malware, sandbox, resource, hash, metadata, signed-access, CDN-origin, consent and minor tests pass. |
| Measurement Source | Media pipeline events, object metadata, scan/transcode evidence |
| Failure Behavior | Keep content quarantined or restricted; never publish on incomplete validation, consent, safety, or processing state. |
| Security or Privacy Impact | Reduces malware, worker escape, private-media disclosure, minor harm, and evidence corruption. |
| Dependencies | QMDB-BL-001; QMDB-FR-MED-001; QMDB-FR-MED-002; QMDB-FR-MED-003; QMDB-FR-MED-004 |
| Open Parameter References | QMDB-PAR-011; QMDB-PAR-023; QMDB-PAR-029 |
| Related Functional Requirements | QMDB-FR-MED-001; QMDB-FR-MED-002; QMDB-FR-MED-003; QMDB-FR-MED-004 |
| Related Use Cases | QMDB-UC-048; QMDB-UC-049; QMDB-UC-050 |
| Related Business Invariants | INV-023; INV-024; INV-025 |
| Related Threats | QMDB-THR-019; QMDB-THR-020; QMDB-THR-021; QMDB-THR-022 |
| Related Controls | QMDB-CTL-012; QMDB-CTL-016 |
| Planned Implementation Phase | P9 — Audio and Video Evidence |
| Verification Method | Media security integration, malware, access, sandbox and consent-race tests |
| Required Evidence | Object policy, malware/codec fixtures, worker profile, hash and publication audit |
| Acceptance Criteria | Spoofed, malicious, unconsented, restricted, over-limit, or failed media remains non-public and isolated. |
| Status | Parameter Pending |

## QMDB-NFR-CRY-001 — Managed cryptography and signing custody

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-CRY-001 |
| Title | Managed cryptography and signing custody |
| Quality Attribute | Cryptography |
| Requirement Statement | The QMDB cryptographic boundary shall use approved maintained libraries and random generators, prohibit custom algorithms, separate encryption and signing keys by purpose and environment, keep private keys outside source, logs and the application database, support identifiers, rotation and revocation, and fail closed on verification failure. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Security Critical |
| Applicable System Components | Key management abstraction, certificates, result bundles, audit checkpoints, TLS, restricted fields |
| Applicable Actors | Security key custodian, certificate service, verifier |
| Stimulus or Trigger | Encrypt, sign, verify, rotate, revoke, restore, or detect compromise |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Use governed key custody and field-level protection for selected restricted data, preserve historical verification, generate content hashes and checkpoints, and stop issuance or trust when required keys are unavailable or invalid. |
| Response Measure | Cryptographic tests validate signature/hash, entropy source, key separation, rotation/revocation, historical verification and absence from prohibited stores. |
| Measurement Source | KMS audit, key inventory, verification telemetry |
| Failure Behavior | Stop issuance or sensitive processing, flag verification failure, preserve evidence, and alert without exposing key material. |
| Security or Privacy Impact | Prevents forgery, undetected alteration, key reuse and secret disclosure. |
| Dependencies | QMDB-BL-001; QMDB-FR-CER-002; QMDB-FR-CER-003; QMDB-FR-CER-004; QMDB-FR-OPS-005 |
| Open Parameter References | QMDB-PAR-031 |
| Related Functional Requirements | QMDB-FR-CER-002; QMDB-FR-CER-003; QMDB-FR-CER-004; QMDB-FR-OPS-005 |
| Related Use Cases | QMDB-UC-044; QMDB-UC-045; QMDB-UC-046 |
| Related Business Invariants | INV-018; INV-019; INV-030 |
| Related Threats | QMDB-THR-015; QMDB-THR-016; QMDB-THR-017 |
| Related Controls | QMDB-CTL-013; QMDB-CTL-022 |
| Planned Implementation Phase | P8 — Certificates, Record Passport, and Trusted Archive |
| Verification Method | Cryptographic design review; signature, rotation, revocation, entropy and secret-location tests |
| Required Evidence | Key ceremony design, custody matrix, crypto test vectors, KMS audit |
| Acceptance Criteria | Absent, revoked, mismatched, weak, or unverifiable key state prevents issuance or trust and raises controlled evidence. |
| Status | Parameter Pending |

## QMDB-NFR-DAT-002 — Authoritative-versus-projection integrity

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-DAT-002 |
| Title | Authoritative-versus-projection integrity |
| Quality Attribute | Data Integrity |
| Requirement Statement | The QMDB data boundary shall identify authoritative records explicitly and prevent browser state, Redis, CDN, search indexes, analytics, public identifiers, client calculations, AI output, or third-party systems from becoming authoritative score or record writers. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Competition Critical |
| Applicable System Components | Domain services, MySQL, projections, integrations, clients |
| Applicable Actors | All actors and automated consumers |
| Stimulus or Trigger | Read-model mismatch, client manipulation, projection delay, external write, or AI suggestion |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Recompute and validate on the server against authoritative versioned data, reject alternate write paths, label stale/provisional projections, and reconcile derived stores. |
| Response Measure | Architecture and end-to-end tests demonstrate all authoritative commands terminate in governed server-side transitions and all projections are rebuildable. |
| Measurement Source | Architecture tests, provenance traces, reconciliation reports |
| Failure Behavior | Reject mutation; display controlled stale/unavailable status instead of promoting derived data. |
| Security or Privacy Impact | Protects result, certificate, Qur’an and audit truth. |
| Dependencies | QMDB-BL-001; QMDB-FR-SCR-003; QMDB-FR-RSL-003; QMDB-FR-SRH-002; QMDB-FR-INT-003 |
| Open Parameter References | None |
| Related Functional Requirements | QMDB-FR-SCR-003; QMDB-FR-RSL-003; QMDB-FR-SRH-002; QMDB-FR-INT-003 |
| Related Use Cases | QMDB-UC-036; QMDB-UC-042; QMDB-UC-061 |
| Related Business Invariants | INV-015; INV-016; INV-020; INV-021 |
| Related Threats | QMDB-THR-010; QMDB-THR-011; QMDB-THR-012 |
| Related Controls | QMDB-CTL-010; QMDB-CTL-014 |
| Planned Implementation Phase | P6 — Judging and Deterministic Scoring |
| Verification Method | Architecture, alternate-path, client-tampering, projection-rebuild and reconciliation tests |
| Required Evidence | Data-authority map, API traces, negative test and rebuild evidence |
| Acceptance Criteria | Manipulated client or projection values never alter authoritative records or masquerade as current truth. |
| Status | Approved Baseline |

## QMDB-NFR-SEC-004 — Independent security verification

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-SEC-004 |
| Title | Independent security verification |
| Quality Attribute | Security Assurance |
| Requirement Statement | The QMDB release boundary shall require risk-based independent review and penetration testing of national-rollout security, tenant isolation, privileged access, scoring, media, certificates, offline operation, and recovery before affected production approval. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | High |
| Criticality | Security Critical |
| Applicable System Components | Architecture, application, infrastructure, operations |
| Applicable Actors | Independent assessor, Security Governance, Release Governance |
| Stimulus or Trigger | Release candidate, material boundary change, national rollout, or serious incident |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Define scoped testing, preserve evidence, triage findings, block release under severity policy, and verify remediation independently. |
| Response Measure | Approved scope and provider decision, test evidence, finding disposition and retest records exist for each governed release. |
| Measurement Source | Security verification matrix and release evidence |
| Failure Behavior | Block promotion where required assurance or accepted-risk authority is absent. |
| Security or Privacy Impact | Reduces systemic control blind spots and false assurance. |
| Dependencies | QMDB-BL-001; QMDB-FR-SEC-002; QMDB-FR-OPS-003 |
| Open Parameter References | QMDB-PAR-027 |
| Related Functional Requirements | QMDB-FR-SEC-002; QMDB-FR-OPS-003 |
| Related Use Cases | QMDB-UC-060 |
| Related Business Invariants | INV-028; INV-030 |
| Related Threats | QMDB-THR-037; QMDB-THR-046 |
| Related Controls | QMDB-CTL-021; QMDB-CTL-025 |
| Planned Implementation Phase | P12 — Quality, Accessibility, and Production Hardening |
| Verification Method | Independent architecture review and penetration test |
| Required Evidence | Signed scope, assessor independence, findings, approvals and retest evidence |
| Acceptance Criteria | A national rollout cannot pass its security gate without the approved independent evidence or explicit governed risk acceptance. |
| Status | Proposed |

## Change control

Changes require impact review across the [parameter register](../../operations/quality-attribute-parameter-register.md), [threat model](../../security/threat-model.md), [acceptance scenarios](../P0-B03-non-functional-acceptance-scenarios.md), and [traceability matrix](../P0-B03-traceability-matrix.md). Locked ADR-001 through ADR-020 cannot be weakened here.
