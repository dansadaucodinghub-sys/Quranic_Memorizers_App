# Security Control Catalog

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B03 |
| Document Title | Security Control Catalog |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Security, Privacy and Operational Control Governance |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed for governance approval; locked baseline constraints remain binding |
| Related Documents | [Threat model](threat-model.md); [Security verification](security-verification-matrix.md); [B03 NFR index](../requirements/P0-B03-non-functional-requirements-index.md) |

## Purpose

Define preventive, detective, corrective and recovery controls, accountable ownership, evidence and exception rules.

## Scope and status

Controls are planned requirements, not claims of implementation. Exceptions cannot weaken locked invariants and require owner, risk, expiry and compensating evidence.

## QMDB-CTL-001 — Security governance and risk acceptance

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-001 |
| Control Name | Security governance and risk acceptance |
| Control Domain | Governance |
| Control Objective | Make security decisions attributable and risk-based |
| Control Statement | Security/privacy/safety changes require threat, risk, evidence, independent review and accountable approval. |
| Threats Addressed | QMDB-THR-034; QMDB-THR-043 |
| Assets Protected | All critical assets |
| Preventive, Detective, Corrective, or Recovery | Preventive |
| Enforcement Layers | Governance, architecture, release |
| Control Owner Role | Security Governance |
| Required Evidence | Decision, review, accepted-risk and reassessment evidence |
| Verification Method | Governance review |
| Implementation Phase | P1 |
| Related Requirements | QMDB-NFR-SEC-004; QMDB-NFR-REL-001 |
| Failure Consequence | Unowned systemic risk |
| Exception Process | Documented exception with expiry, compensating controls and owner |
| Status | Proposed |

## QMDB-CTL-002 — Risk-aware authentication and recovery

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-002 |
| Control Name | Risk-aware authentication and recovery |
| Control Domain | Authentication |
| Control Objective | Resist account and recovery takeover |
| Control Statement | Adaptive password hashing, MFA/step-up, passkey-capable design, non-enumeration, bounded attempts and single-use expiring recovery are enforced. |
| Threats Addressed | QMDB-THR-001; QMDB-THR-003; QMDB-THR-004 |
| Assets Protected | Credentials, privileged identity |
| Preventive, Detective, Corrective, or Recovery | Preventive |
| Enforcement Layers | Presentation, application, identity store, notifications |
| Control Owner Role | Identity and Security Governance |
| Required Evidence | Benchmark, assurance matrix, recovery and abuse test |
| Verification Method | Security test |
| Implementation Phase | P2 |
| Related Requirements | QMDB-NFR-IAM-001 |
| Failure Consequence | Account takeover |
| Exception Process | Approved identity-policy exception only; no assurance downgrade |
| Status | Proposed |

## QMDB-CTL-003 — Server-side Session control

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-003 |
| Control Name | Server-side Session control |
| Control Domain | Session management |
| Control Objective | Limit fixation, theft and stale authority |
| Control Statement | Secure HttpOnly SameSite cookies, server-side state, rotation, idle/absolute expiry, inventory and revocation are required. |
| Threats Addressed | QMDB-THR-002 |
| Assets Protected | Sessions, accounts |
| Preventive, Detective, Corrective, or Recovery | Preventive; Corrective |
| Enforcement Layers | Browser, application, Session store |
| Control Owner Role | Identity Operations |
| Required Evidence | Cookie, rotation, expiry and revocation evidence |
| Verification Method | End-to-end security test |
| Implementation Phase | P2 |
| Related Requirements | QMDB-NFR-IAM-002 |
| Failure Consequence | Persistent unauthorized access |
| Exception Process | No URL/local-storage bearer-token exception |
| Status | Proposed |

## QMDB-CTL-004 — Privileged access and separation of duties

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-004 |
| Control Name | Privileged access and separation of duties |
| Control Domain | Authorization |
| Control Objective | Prevent self-approval and unaccountable privilege |
| Control Statement | MFA/step-up, JIT scope/expiry, no shared accounts, no self-approval, multi-person approval where required, restricted support and reviewed break-glass are enforced. |
| Threats Addressed | QMDB-THR-007; QMDB-THR-008; QMDB-THR-045; QMDB-THR-046 |
| Assets Protected | Official records, privileged operations |
| Preventive, Detective, Corrective, or Recovery | Preventive; Detective |
| Enforcement Layers | IAM, policies, audit, infrastructure |
| Control Owner Role | Security and Domain Governance |
| Required Evidence | Capability matrix, approvals, expiry/revocation and audit |
| Verification Method | Authorization test and audit review |
| Implementation Phase | P2 |
| Related Requirements | QMDB-NFR-SEC-001; QMDB-NFR-INF-002 |
| Failure Consequence | Privilege abuse or silent official mutation |
| Exception Process | Emergency exception is time-bound, continuously audited and post-reviewed |
| Status | Proposed |

## QMDB-CTL-005 — Tenant and resource authorization

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-005 |
| Control Name | Tenant and resource authorization |
| Control Domain | Tenant isolation |
| Control Objective | Prevent cross-Workspace access/corruption |
| Control Statement | Trusted Workspace context, RBAC+ABAC+resource policies, composite integrity, tenant-aware jobs/caches/exports and complete mediation are mandatory. |
| Threats Addressed | QMDB-THR-005; QMDB-THR-006; QMDB-THR-008 |
| Assets Protected | Workspace data, memberships |
| Preventive, Detective, Corrective, or Recovery | Preventive; Detective |
| Enforcement Layers | Policies, repositories, MySQL, workers, caches |
| Control Owner Role | Security Architecture |
| Required Evidence | Tenant inventory, denial tests, constraints and denial audit |
| Verification Method | Architecture, authorization and isolation test |
| Implementation Phase | P2 |
| Related Requirements | QMDB-NFR-TEN-001; QMDB-NFR-SEC-001 |
| Failure Consequence | Cross-tenant breach |
| Exception Process | No role-title or public-ID exception |
| Status | Approved Baseline |

## QMDB-CTL-006 — Server-side validation and contextual output

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-006 |
| Control Name | Server-side validation and contextual output |
| Control Domain | Secure coding |
| Control Objective | Prevent injection and unsafe interpretation |
| Control Statement | Typed server validation, native prepared statements, contextual encoding, safe redirects/paths/deserialization/XML and production error redaction are enforced. |
| Threats Addressed | QMDB-THR-029; QMDB-THR-030; QMDB-THR-032; QMDB-THR-033 |
| Assets Protected | Application, database, host files |
| Preventive, Detective, Corrective, or Recovery | Preventive |
| Enforcement Layers | Presentation, application, repositories |
| Control Owner Role | Engineering Security |
| Required Evidence | Static/dynamic and negative-input evidence |
| Verification Method | Code review and security test |
| Implementation Phase | P2 |
| Related Requirements | QMDB-NFR-SEC-002 |
| Failure Consequence | Code/query execution or disclosure |
| Exception Process | No client-only validation exception |
| Status | Proposed |

## QMDB-CTL-007 — Browser, request and outbound boundary protection

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-007 |
| Control Name | Browser, request and outbound boundary protection |
| Control Domain | Secure coding |
| Control Objective | Constrain browser/cross-origin/server-side requests |
| Control Statement | CSRF, CSP, HSTS, framing, CORS, host, method, size, cache, referrer/permissions, request-smuggling and SSRF egress allowlist controls are reviewed. |
| Threats Addressed | QMDB-THR-028; QMDB-THR-030; QMDB-THR-031 |
| Assets Protected | Browser Sessions, internal services |
| Preventive, Detective, Corrective, or Recovery | Preventive; Detective |
| Enforcement Layers | Nginx, application, egress boundary |
| Control Owner Role | Security Engineering |
| Required Evidence | Header/edge/egress configuration and attack tests |
| Verification Method | Configuration and penetration test |
| Implementation Phase | P2 |
| Related Requirements | QMDB-NFR-SEC-002 |
| Failure Consequence | Cross-origin action, internal probing or data leak |
| Exception Process | Documented integration allowlist only |
| Status | Proposed |

## QMDB-CTL-008 — MySQL least privilege and integrity

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-008 |
| Control Name | MySQL least privilege and integrity |
| Control Domain | Database |
| Control Objective | Protect authoritative relational truth |
| Control Statement | InnoDB, strict SQL, utf8mb4, UTC, foreign keys, native prepared statements, separated roles, TLS, exact DECIMAL, primary authoritative reads, protected logs and migration-only changes are enforced. |
| Threats Addressed | QMDB-THR-009; QMDB-THR-010; QMDB-THR-029; QMDB-THR-037 |
| Assets Protected | Scores, results, identities, audit |
| Preventive, Detective, Corrective, or Recovery | Preventive; Detective; Recovery |
| Enforcement Layers | MySQL, PDO, migrations, backup |
| Control Owner Role | Database Architecture and Operations |
| Required Evidence | Grants/config/schema/query/PITR evidence |
| Verification Method | Configuration, constraint and recovery test |
| Implementation Phase | P1 |
| Related Requirements | QMDB-NFR-DAT-001; QMDB-NFR-PER-003 |
| Failure Consequence | Authoritative corruption/loss |
| Exception Process | Emergency DB access is separate, time-bound and audited |
| Status | Approved Baseline |

## QMDB-CTL-009 — Private ACL-restricted Redis

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-009 |
| Control Name | Private ACL-restricted Redis |
| Control Domain | Redis |
| Control Objective | Protect cache/Streams and safe degradation |
| Control Statement | Redis is private, authenticated, ACL-restricted, TLS-capable, tenant-namespaced, bounded, monitored and never authoritative; consumers are idempotent. |
| Threats Addressed | QMDB-THR-038; QMDB-THR-039; QMDB-THR-040 |
| Assets Protected | Cache, live delivery, jobs |
| Preventive, Detective, Corrective, or Recovery | Preventive; Detective; Recovery |
| Enforcement Layers | Network, Redis, workers |
| Control Owner Role | SRE and Security Operations |
| Required Evidence | Network/ACL/TLS/expiry/stream/rebuild evidence |
| Verification Method | Configuration and failure test |
| Implementation Phase | P7 |
| Related Requirements | QMDB-NFR-SEC-003; QMDB-NFR-SCL-001 |
| Failure Consequence | Exposure, tenant mix or lost/duplicate delivery |
| Exception Process | No public exposure or authoritative-cache exception |
| Status | Proposed |

## QMDB-CTL-010 — Authoritative-store and exact-version guard

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-010 |
| Control Name | Authoritative-store and exact-version guard |
| Control Domain | Data integrity |
| Control Objective | Prevent client/projection/external authority |
| Control Statement | Domain commands recompute exact DECIMAL values, check version/state and write only governed authoritative MySQL; projections and AI remain advisory/read-only. |
| Threats Addressed | QMDB-THR-009; QMDB-THR-010; QMDB-THR-011; QMDB-THR-012; QMDB-THR-040 |
| Assets Protected | Rulesets, scores, results |
| Preventive, Detective, Corrective, or Recovery | Preventive; Detective |
| Enforcement Layers | Domain, application, MySQL, API |
| Control Owner Role | Scoring and Records Governance |
| Required Evidence | Golden vectors, concurrency, alternate-path and reconciliation evidence |
| Verification Method | Property, concurrency and architecture test |
| Implementation Phase | P6 |
| Related Requirements | QMDB-NFR-DAT-002 |
| Failure Consequence | Official outcome manipulation |
| Exception Process | No client, AI or integration exception |
| Status | Approved Baseline |

## QMDB-CTL-011 — Scoped replay-safe API/webhook

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-011 |
| Control Name | Scoped replay-safe API/webhook |
| Control Domain | API |
| Control Objective | Constrain integrations and retries |
| Control Statement | OpenAPI contracts, authentication/scopes/tenant, bounded validation, filtering, idempotency, replay/timestamp/signature controls, key lifecycle, audit and suspension are enforced. |
| Threats Addressed | QMDB-THR-026; QMDB-THR-027 |
| Assets Protected | API data, credentials, events |
| Preventive, Detective, Corrective, or Recovery | Preventive; Detective |
| Enforcement Layers | API, webhook worker, audit |
| Control Owner Role | Integration Security |
| Required Evidence | Contract, scope, replay, rotation and suspension evidence |
| Verification Method | API security test |
| Implementation Phase | P12 |
| Related Requirements | QMDB-NFR-API-001 |
| Failure Consequence | Integration breach/duplicate effect |
| Exception Process | No external score-write exception |
| Status | Proposed |

## QMDB-CTL-012 — Private media quarantine and sandbox

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-012 |
| Control Name | Private media quarantine and sandbox |
| Control Domain | Media |
| Control Objective | Prevent malicious or unsafe media publication |
| Control Statement | Short-lived scoped upload, private quarantine, actual-type/malware/codec checks, hash/metadata stripping, sandboxed bounded transcode, opaque keys and consent-gated derivatives are enforced. |
| Threats Addressed | QMDB-THR-018; QMDB-THR-019; QMDB-THR-020; QMDB-THR-021 |
| Assets Protected | Media, workers, Minors |
| Preventive, Detective, Corrective, or Recovery | Preventive; Detective; Corrective |
| Enforcement Layers | Application, object storage, worker, CDN |
| Control Owner Role | Media Security |
| Required Evidence | Malware/type/sandbox/hash/access/consent evidence |
| Verification Method | Media security and penetration test |
| Implementation Phase | P9 |
| Related Requirements | QMDB-NFR-MED-001 |
| Failure Consequence | Malware execution or private disclosure |
| Exception Process | No unscanned publication or public original |
| Status | Proposed |

## QMDB-CTL-013 — Cryptographic key and signing control

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-013 |
| Control Name | Cryptographic key and signing control |
| Control Domain | Cryptography |
| Control Objective | Protect encryption, signatures and verification |
| Control Statement | Approved libraries/RNG, purpose/environment separation, external key custody, rotation/revocation, historical verification and fail-closed signing/verification are mandatory. |
| Threats Addressed | QMDB-THR-013; QMDB-THR-014 |
| Assets Protected | Keys, certificates, signed results |
| Preventive, Detective, Corrective, or Recovery | Preventive; Detective; Corrective |
| Enforcement Layers | KMS abstraction, certificate service, verifier |
| Control Owner Role | Security Key Custody |
| Required Evidence | Crypto vectors, custody, rotation/revocation and absence scans |
| Verification Method | Cryptographic review/test |
| Implementation Phase | P8 |
| Related Requirements | QMDB-NFR-CRY-001 |
| Failure Consequence | System-wide forgery |
| Exception Process | Controlled key ceremony only |
| Status | Proposed |

## QMDB-CTL-014 — Hash-linked audit and external checkpoints

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-014 |
| Control Name | Hash-linked audit and external checkpoints |
| Control Domain | Audit |
| Control Objective | Make significant history tampering evident |
| Control Statement | Attributable append-oriented events are transactionally related to state, hash-linked and externally checkpointed; verification failures alert and restrict sensitive changes. |
| Threats Addressed | QMDB-THR-015; QMDB-THR-016 |
| Assets Protected | Audit, official records |
| Preventive, Detective, Corrective, or Recovery | Detective; Recovery |
| Enforcement Layers | MySQL audit, outbox, checkpoint store |
| Control Owner Role | Audit Governance |
| Required Evidence | Completeness, chain/checkpoint, tamper and export evidence |
| Verification Method | Audit integrity test |
| Implementation Phase | P2 |
| Related Requirements | QMDB-NFR-AUD-001 |
| Failure Consequence | Undetectable insider tampering |
| Exception Process | Corrections use new linked events, not deletion |
| Status | Proposed |

## QMDB-CTL-015 — Privacy minimization and rights control

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-015 |
| Control Name | Privacy minimization and rights control |
| Control Domain | Privacy |
| Control Objective | Limit personal-data use/disclosure |
| Control Statement | Purpose/classification, notices/consent, processing/processor inventory, field allowlists, subject-request control, small-group suppression, holds and qualified review are enforced. |
| Threats Addressed | QMDB-THR-020; QMDB-THR-021; QMDB-THR-046 |
| Assets Protected | Personal and rights data |
| Preventive, Detective, Corrective, or Recovery | Preventive; Detective; Corrective |
| Enforcement Layers | All modules, privacy cases, projections |
| Control Owner Role | Privacy Governance |
| Required Evidence | PIA, classification, public/export and request evidence |
| Verification Method | Privacy review and tests |
| Implementation Phase | P3 |
| Related Requirements | QMDB-NFR-PRI-001; QMDB-NFR-PRI-002; QMDB-NFR-PRI-003; QMDB-NFR-PRI-004 |
| Failure Consequence | Overprocessing or wrong disclosure |
| Exception Process | Qualified case-specific exception with minimization |
| Status | Proposed |

## QMDB-CTL-016 — Guardian, consent and Minor visibility

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-016 |
| Control Name | Guardian, consent and Minor visibility |
| Control Domain | Child safety |
| Control Objective | Prevent unauthorized child action/publication |
| Control Statement | Protective Minor defaults, verified scoped Guardian relationship, purpose/version consent, public-field restrictions, withdrawal purge and age/dispute workflows are enforced. |
| Threats Addressed | QMDB-THR-021; QMDB-THR-022 |
| Assets Protected | Minors, Guardian/consent, media |
| Preventive, Detective, Corrective, or Recovery | Preventive; Corrective |
| Enforcement Layers | People, Guardianship, Media, Community, projections |
| Control Owner Role | Child-Safety Governance |
| Required Evidence | Decision matrix, consent/withdrawal/public tests |
| Verification Method | Child-safety and authorization test |
| Implementation Phase | P3 |
| Related Requirements | QMDB-NFR-CHD-001; QMDB-NFR-CHD-003 |
| Failure Consequence | Child exposure or false authority |
| Exception Process | Uncertainty remains private/restricted |
| Status | Proposed |

## QMDB-CTL-017 — Moderation and interaction safety

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-017 |
| Control Name | Moderation and interaction safety |
| Control Domain | Child safety |
| Control Objective | Limit harassment, flooding and moderator abuse |
| Control Statement | No unrestricted messaging/livestreaming, reportability, block/mute, comment/rate controls, scoped moderation, emergency restriction, appeal and audit are enforced. |
| Threats Addressed | QMDB-THR-023; QMDB-THR-024; QMDB-THR-025 |
| Assets Protected | Users, Minors, community evidence |
| Preventive, Detective, Corrective, or Recovery | Preventive; Detective; Corrective |
| Enforcement Layers | Community, moderation, notifications |
| Control Owner Role | Moderation and Child-Safety Governance |
| Required Evidence | Abuse/report/flood/scope/appeal evidence |
| Verification Method | Abuse and child-safety test |
| Implementation Phase | P10 |
| Related Requirements | QMDB-NFR-CHD-002 |
| Failure Consequence | Social harm or retaliation |
| Exception Process | Immediate restriction is reviewed and appealable |
| Status | Proposed |

## QMDB-CTL-018 — Workload isolation and bounded resilience

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-018 |
| Control Name | Workload isolation and bounded resilience |
| Control Domain | Resilience |
| Control Objective | Protect competition operations from dependency/social failure |
| Control Statement | Tiered pools/queues, timeouts, bounded retry/backoff/jitter, circuit/bulkhead, backpressure, load shedding, health/readiness, outbox and reconciliation are enforced. |
| Threats Addressed | QMDB-THR-039; QMDB-THR-041; QMDB-THR-042; QMDB-THR-043; QMDB-THR-044 |
| Assets Protected | Competition services, queues |
| Preventive, Detective, Corrective, or Recovery | Preventive; Corrective; Recovery |
| Enforcement Layers | Runtime, queues, dependencies |
| Control Owner Role | SRE and Business Continuity |
| Required Evidence | Mixed-load, fault, retry, health and reconciliation evidence |
| Verification Method | Load/fault/recovery test |
| Implementation Phase | P12 |
| Related Requirements | QMDB-NFR-PER-001; QMDB-NFR-RES-001; QMDB-NFR-RES-002 |
| Failure Consequence | Cascading outage or duplicate record |
| Exception Process | Lower tiers degrade first; never fail open |
| Status | Proposed |

## QMDB-CTL-019 — Retention, hold and controlled disposition

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-019 |
| Control Name | Retention, hold and controlled disposition |
| Control Domain | Privacy and records |
| Control Objective | Prevent premature destruction or indefinite silent retention |
| Control Statement | Per-record open decisions, holds, public removal, authoritative disposition, anonymization assessment and approved secure deletion are separated and audited. |
| Threats Addressed | QMDB-THR-015; QMDB-THR-020; QMDB-THR-036 |
| Assets Protected | Personal, official and backup records |
| Preventive, Detective, Corrective, or Recovery | Preventive; Corrective |
| Enforcement Layers | Privacy, records, media, backup |
| Control Owner Role | Privacy and Records Governance |
| Required Evidence | Retention decision, hold, purge and evidence-preservation records |
| Verification Method | Privacy/records review |
| Implementation Phase | P12 |
| Related Requirements | QMDB-NFR-PRI-004; QMDB-NFR-CHD-003 |
| Failure Consequence | Rights failure or evidence loss |
| Exception Process | No invented period; conservative restriction |
| Status | Proposed |

## QMDB-CTL-020 — Telemetry, detection and incident response

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-020 |
| Control Name | Telemetry, detection and incident response |
| Control Domain | Monitoring and incident response |
| Control Objective | Detect and control adverse events |
| Control Statement | Redacted correlated logs/metrics/traces, owned alerts/runbooks, controlled incident states, evidence preservation, containment, recovery and post-review are required. |
| Threats Addressed | QMDB-THR-001 through QMDB-THR-046 |
| Assets Protected | All critical assets |
| Preventive, Detective, Corrective, or Recovery | Detective; Corrective; Recovery |
| Enforcement Layers | All components, monitoring, incident system |
| Control Owner Role | Security Operations and SRE |
| Required Evidence | Telemetry, alert, exercise, incident and corrective-action evidence |
| Verification Method | Synthetic alert and incident exercise |
| Implementation Phase | P12 |
| Related Requirements | QMDB-NFR-OBS-001; QMDB-NFR-OBS-002; QMDB-NFR-OBS-003; QMDB-NFR-INC-001 |
| Failure Consequence | Undetected or unmanaged incident |
| Exception Process | Secondary alert/escalation path; no secret logging |
| Status | Proposed |

## QMDB-CTL-021 — Secure SDLC and supply-chain gates

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-021 |
| Control Name | Secure SDLC and supply-chain gates |
| Control Domain | Supply chain |
| Control Objective | Prevent vulnerable/unreviewed change |
| Control Statement | Threat-informed review, protected branches, static/dependency/secret/container scans, SBOM/provenance, reproducible artifacts, test gates and audited deployment/rollback are required. |
| Threats Addressed | QMDB-THR-034; QMDB-THR-035 |
| Assets Protected | Source, dependencies, artifacts |
| Preventive, Detective, Corrective, or Recovery | Preventive; Detective; Corrective |
| Enforcement Layers | Repository, CI/CD, artifact/deployment |
| Control Owner Role | Engineering and Release Governance |
| Required Evidence | Review, scan, SBOM, attestation, gate and rollback evidence |
| Verification Method | Pipeline and artifact test |
| Implementation Phase | P1 |
| Related Requirements | QMDB-NFR-SEC-004; QMDB-NFR-MNT-001; QMDB-NFR-REL-001; QMDB-NFR-SUP-001; QMDB-NFR-SUP-002 |
| Failure Consequence | Supply-chain compromise |
| Exception Process | Emergency change is attributable and post-reviewed |
| Status | Proposed |

## QMDB-CTL-022 — Secrets, encryption and restricted-data custody

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-022 |
| Control Name | Secrets, encryption and restricted-data custody |
| Control Domain | Secrets and data protection |
| Control Objective | Prevent secret/restricted-data disclosure |
| Control Statement | Dedicated secrets service, encryption, environment/purpose separation, JIT access, rotation/revocation, clean source/image/logs and separate key/backup/audit duties are enforced. |
| Threats Addressed | QMDB-THR-014; QMDB-THR-035; QMDB-THR-036 |
| Assets Protected | Secrets, restricted data, backups |
| Preventive, Detective, Corrective, or Recovery | Preventive; Detective; Corrective |
| Enforcement Layers | KMS/secrets, stores, IAM, CI |
| Control Owner Role | Security Governance |
| Required Evidence | Inventory, scans, grants, access review and rotation evidence |
| Verification Method | Secret scan and access test |
| Implementation Phase | P2 |
| Related Requirements | QMDB-NFR-PRI-002; QMDB-NFR-INF-002 |
| Failure Consequence | Broad compromise |
| Exception Process | Emergency rotation with incident review |
| Status | Proposed |

## QMDB-CTL-023 — Encrypted immutable backup and verified recovery

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-023 |
| Control Name | Encrypted immutable backup and verified recovery |
| Control Domain | Backup and recovery |
| Control Objective | Recover authoritative truth after loss/attack |
| Control Statement | Separate credentials/custody, encrypted integrity-checked immutable copies, PITR, object/config/key/checkpoint recovery, restore tests and reconciliation are required. |
| Threats Addressed | QMDB-THR-036; QMDB-THR-037 |
| Assets Protected | Authoritative data, media, audit |
| Preventive, Detective, Corrective, or Recovery | Preventive; Detective; Recovery |
| Enforcement Layers | Backup, MySQL, object, recovery environment |
| Control Owner Role | Business Continuity |
| Required Evidence | Backup/access/integrity/restore/reconciliation evidence |
| Verification Method | Recovery test |
| Implementation Phase | P12 |
| Related Requirements | QMDB-NFR-DRC-001 |
| Failure Consequence | Unrecoverable loss/ransomware |
| Exception Process | No recovery claim without restore evidence |
| Status | Proposed |

## QMDB-CTL-024 — Performance and capacity protection

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-024 |
| Control Name | Performance and capacity protection |
| Control Domain | Performance |
| Control Objective | Maintain measured capacity without integrity shortcuts |
| Control Statement | SLIs, parameterized targets, workload tiers, query/queue bounds, load/soak tests and evidence-led scaling protect critical paths. |
| Threats Addressed | QMDB-THR-043; QMDB-THR-044 |
| Assets Protected | Competition service capacity |
| Preventive, Detective, Corrective, or Recovery | Preventive; Detective; Corrective |
| Enforcement Layers | Web, MySQL, Redis, queues, CDN |
| Control Owner Role | Capacity Engineering and SRE |
| Required Evidence | Capacity model, SLIs, load/soak/degradation evidence |
| Verification Method | Performance and capacity test |
| Implementation Phase | P13 |
| Related Requirements | QMDB-NFR-PER-001; QMDB-NFR-PER-002; QMDB-NFR-PER-003; QMDB-NFR-SCL-002 |
| Failure Consequence | Peak outage |
| Exception Process | Degrade lower tiers and stage rollout |
| Status | Proposed |

## QMDB-CTL-025 — Manual and automated accessibility assurance

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-025 |
| Control Name | Manual and automated accessibility assurance |
| Control Domain | Accessibility |
| Control Objective | Make critical workflows perceivable and operable |
| Control Statement | WCAG 2.2 AA target, automation plus keyboard/screen-reader/RTL/mobile/low-bandwidth/manual review and critical-action safeguards are release gates. |
| Threats Addressed | QMDB-THR-046 |
| Assets Protected | All user workflows |
| Preventive, Detective, Corrective, or Recovery | Preventive; Detective; Corrective |
| Enforcement Layers | Presentation, documents, media |
| Control Owner Role | Accessibility Governance |
| Required Evidence | Matrix, manual records, defects and retest |
| Verification Method | Accessibility review/test |
| Implementation Phase | P12 |
| Related Requirements | QMDB-NFR-ACC-001; QMDB-NFR-ACC-002; QMDB-NFR-ACC-003; QMDB-NFR-L10-001; QMDB-NFR-L10-002; QMDB-NFR-UXR-001 |
| Failure Consequence | Exclusion or transaction error |
| Exception Process | Equivalent accessible path required |
| Status | Proposed |

## QMDB-CTL-026 — Disaster and incident continuity

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-026 |
| Control Name | Disaster and incident continuity |
| Control Domain | Recovery |
| Control Objective | Restore critical service through approved authority |
| Control Statement | Declaration, DR environment, restoration order, communications, verified reconciliation, monitoring, post-review and actions are exercised. |
| Threats Addressed | QMDB-THR-037; QMDB-THR-043 |
| Assets Protected | Critical services and official records |
| Preventive, Detective, Corrective, or Recovery | Corrective; Recovery |
| Enforcement Layers | DR environment, incident management |
| Control Owner Role | Business Continuity Governance |
| Required Evidence | Exercise, timing, reconciliation, communication and approval evidence |
| Verification Method | DR/incident exercise |
| Implementation Phase | P13 |
| Related Requirements | QMDB-NFR-DRC-002; QMDB-NFR-INC-001 |
| Failure Consequence | Unsafe/prolonged recovery |
| Exception Process | Remain in controlled continuity mode |
| Status | Proposed |

## QMDB-CTL-027 — Signed offline Venue Edge control

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-027 |
| Control Name | Signed offline Venue Edge control |
| Control Domain | Offline security |
| Control Objective | Prevent offline replay/tamper/divergence |
| Control Statement | Registered devices, signed encrypted expiring assignments, local sequence/hash, non-authoritative drafts and central duplicate/order/conflict validation are enforced. |
| Threats Addressed | QMDB-THR-041; QMDB-THR-042 |
| Assets Protected | Offline scores, devices |
| Preventive, Detective, Corrective, or Recovery | Preventive; Detective; Recovery |
| Enforcement Layers | Venue Edge, device, sync API |
| Control Owner Role | Competition and Security Governance |
| Required Evidence | Package/device/event/replay/conflict/reconciliation evidence |
| Verification Method | Offline security/recovery test |
| Implementation Phase | P13 |
| Related Requirements | QMDB-NFR-RES-003 |
| Failure Consequence | Divergent or replayed scores |
| Exception Process | Quarantine conflicts; no auto-overwrite |
| Status | Proposed |

## QMDB-CTL-028 — Canonical Qur’an release governance

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-028 |
| Control Name | Canonical Qur’an release governance |
| Control Domain | Domain integrity |
| Control Objective | Prevent unauthorized canonical alteration |
| Control Statement | Versioned checksummed releases, restricted import/review/activation, independent approval, provenance and no normal-administrator edit are enforced. |
| Threats Addressed | QMDB-THR-017 |
| Assets Protected | Canonical Qur’an text/releases |
| Preventive, Detective, Corrective, or Recovery | Preventive; Detective; Recovery |
| Enforcement Layers | Qur’an reference module, audit |
| Control Owner Role | Qur’an Reference Governance |
| Required Evidence | Source, checksum, approval, diff and rollback evidence |
| Verification Method | Domain integrity and authorization test |
| Implementation Phase | P4 |
| Related Requirements | QMDB-NFR-DAT-002; QMDB-NFR-L10-001 |
| Failure Consequence | Religious/data integrity harm |
| Exception Process | No direct edit; new reviewed release only |
| Status | Approved Baseline |

## QMDB-CTL-029 — Minimal cryptographic certificate verification

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-029 |
| Control Name | Minimal cryptographic certificate verification |
| Control Domain | Certificates |
| Control Objective | Prevent forgery and privacy leakage |
| Control Statement | Signed content hash, key/status validation, revocation/supersession history and minimal allowlisted public output are enforced. |
| Threats Addressed | QMDB-THR-013; QMDB-THR-014 |
| Assets Protected | Certificates, recipient data |
| Preventive, Detective, Corrective, or Recovery | Preventive; Detective; Corrective |
| Enforcement Layers | Certificate service, verifier, public projection |
| Control Owner Role | Certificate Governance |
| Required Evidence | Crypto/status/field/forgery/load evidence |
| Verification Method | Cryptographic and privacy test |
| Implementation Phase | P8 |
| Related Requirements | QMDB-NFR-CRY-001; QMDB-NFR-PRI-003 |
| Failure Consequence | False credential or over-disclosure |
| Exception Process | Return invalid/revoked/unavailable safely |
| Status | Proposed |

## QMDB-CTL-030 — Scoped support access

| Field | Value |
| --- | --- |
| Control ID | QMDB-CTL-030 |
| Control Name | Scoped support access |
| Control Domain | Support access |
| Control Objective | Prevent support browsing/impersonation |
| Control Statement | Support uses ticket-bound, field-minimized, time-limited, approved and audited access without password/MFA bypass, impersonation or silent writes. |
| Threats Addressed | QMDB-THR-045; QMDB-THR-046 |
| Assets Protected | Accounts, personal/official data |
| Preventive, Detective, Corrective, or Recovery | Preventive; Detective; Corrective |
| Enforcement Layers | Support, IAM, policies, audit |
| Control Owner Role | Support and Security Governance |
| Required Evidence | Grant, scope, field, expiry/revocation and audit evidence |
| Verification Method | Authorization and abuse test |
| Implementation Phase | P2 |
| Related Requirements | QMDB-NFR-SEC-001; QMDB-NFR-PRI-002; QMDB-NFR-INF-002 |
| Failure Consequence | Insider disclosure/manipulation |
| Exception Process | Break-glass is separate and reviewed |
| Status | Proposed |

## Critical-threat coverage rule

Every Critical threat maps a preventive control plus QMDB-CTL-020 and/or a corrective/recovery control. Verification failure blocks the affected release unless an authorized, expiring risk acceptance with compensating control is recorded; locked constraints cannot be excepted.

