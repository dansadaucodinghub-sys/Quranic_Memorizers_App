# Infrastructure, Deployment, and Supply-Chain Requirements

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B03 |
| Document Title | Infrastructure, Deployment, and Supply-Chain Requirements |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Platform Engineering and DevSecOps Governance |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed for governance approval; inherited locked decisions remain binding |
| Related Documents | [B03 index](../P0-B03-non-functional-requirements-index.md); [Traceability](../P0-B03-traceability-matrix.md); [Product constitution](../../project/product-constitution.md) |

## Purpose

Define environment separation, runtime hardening, secret handling, dependency, artifact, deployment, and infrastructure-access obligations.

## Scope

Local, test, staging, production and disaster-recovery environments; Nginx, PHP-FPM, containers, CI/CD, Composer dependencies, artifacts, secrets, operators, databases, backups, and keys.

## Interpretation and control posture

Requirements are implementation-neutral unless QMDB-BL-001 locks a technology. “Approved Baseline” identifies a direct locked-baseline obligation; “Proposed” requires governance approval; “Parameter Pending” is enforceable in structure and measurement but its target remains governed by the parameter register. Derived stores never replace authoritative MySQL records. Business policy and technical enforcement are both identified through traceability; neither creates legal or religious authority.

## Coverage profile

Credential and data separation; production-data restrictions; non-root minimal runtime; resource limits, TLS, origin and DDoS protection; managed secrets and rotation; locked and audited dependencies, SBOM and licenses; reproducible traceable artifacts; promotion, health verification, rollback, JIT access, MFA and separation of duties.

## QMDB-NFR-INF-001 — Separated hardened runtime environments

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-INF-001 |
| Title | Separated hardened runtime environments |
| Quality Attribute | Infrastructure Security |
| Requirement Statement | The QMDB infrastructure boundary shall separate local, test, staging, production and disaster-recovery credentials, secrets, databases, object locations and logs; restrict production-data copying; and harden Nginx, PHP-FPM and containers with TLS, safe headers, non-root/minimal execution, resource limits, health checks, graceful shutdown and origin/edge protection. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Security Critical |
| Applicable System Components | Nginx, PHP-FPM, containers, networks, storage, environments |
| Applicable Actors | Developer, operator, deployment service |
| Stimulus or Trigger | Provision, configure, run, scale, copy data, expose origin, or renew certificate |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Validate environment identity/configuration, deny cross-environment credentials, minimize images/process privileges and writable paths, monitor certificates, constrain requests/resources, and protect origin/WAF/DDoS boundaries. |
| Response Measure | Configuration and network review proves separation, non-root/minimal runtime, error-display off, TLS/headers, health/shutdown, limits, production-data controls and origin exposure. |
| Measurement Source | Configuration policy, container scan, network test, health telemetry |
| Failure Behavior | Fail readiness or deployment; do not fall back to another environment’s credential/data or expose origin directly. |
| Security or Privacy Impact | Reduces environment crossover, runtime escape, leakage and outage. |
| Dependencies | QMDB-BL-001; QMDB-FR-OPS-003 |
| Open Parameter References | QMDB-PAR-027 |
| Related Functional Requirements | QMDB-FR-OPS-003 |
| Related Use Cases | QMDB-UC-062 |
| Related Business Invariants | INV-027; INV-028 |
| Related Threats | QMDB-THR-035; QMDB-THR-037; QMDB-THR-045 |
| Related Controls | QMDB-CTL-021; QMDB-CTL-022 |
| Planned Implementation Phase | P1 — Engineering and Repository Foundation |
| Verification Method | Configuration, network, TLS/header, container, privilege, health and data-copy tests |
| Required Evidence | Environment matrix, scans, runtime identity, network evidence |
| Acceptance Criteria | Production cannot start with development secrets/data paths, root runtime, public private origins or invalid critical configuration. |
| Status | Parameter Pending |

## QMDB-NFR-INF-002 — Managed secrets and privileged infrastructure access

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-INF-002 |
| Title | Managed secrets and privileged infrastructure access |
| Quality Attribute | Infrastructure Security |
| Requirement Statement | The QMDB privileged-operations boundary shall keep secrets in a dedicated management service, separate them by environment/purpose, audit least-privilege access, rotate/revoke and emergency-rotate them, prohibit source/image/client/log exposure, and require individual MFA, just-in-time expiring access, duty separation, review and controlled break-glass. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Security Critical |
| Applicable System Components | Secrets manager, infrastructure, databases, backups, KMS, CI/CD |
| Applicable Actors | Developer, operator, DBA, backup/key custodian, emergency actor |
| Stimulus or Trigger | Secret access/change, privileged session, production support, backup/key access, or compromise |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Issue scoped short-lived access, record attribution and reason, separate key/backup/audit duties, record sessions where appropriate, revoke on expiry/incident, rotate affected material, and review break-glass. |
| Response Measure | Secret scans are clean; grants, MFA/JIT expiry, rotation, revocation, no-shared-account and access-review tests/evidence pass. |
| Measurement Source | Secrets/KMS/IAM audit, CI scans, privileged access logs |
| Failure Behavior | Deny or terminate access and rotate suspected material; no shared/root fallback. |
| Security or Privacy Impact | Prevents secret leakage and unaccountable insider access. |
| Dependencies | QMDB-BL-001; QMDB-FR-OPS-001; QMDB-FR-OPS-002; QMDB-FR-OPS-005 |
| Open Parameter References | QMDB-PAR-027 |
| Related Functional Requirements | QMDB-FR-OPS-001; QMDB-FR-OPS-002; QMDB-FR-OPS-005 |
| Related Use Cases | QMDB-UC-059; QMDB-UC-060 |
| Related Business Invariants | INV-004; INV-030 |
| Related Threats | QMDB-THR-037; QMDB-THR-038; QMDB-THR-046 |
| Related Controls | QMDB-CTL-022; QMDB-CTL-004 |
| Planned Implementation Phase | P2 — Identity, Security, and Tenant Isolation |
| Verification Method | Secret scan; IAM/grant, MFA/JIT, rotation, revocation, access-review and break-glass tests |
| Required Evidence | Secrets inventory metadata, clean scans, grants and review/rotation audit |
| Acceptance Criteria | No secret or privileged production action depends on shared identity, source/image content or unreviewed permanent access. |
| Status | Parameter Pending |

## QMDB-NFR-SUP-001 — Governed dependency and SBOM security

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-SUP-001 |
| Title | Governed dependency and SBOM security |
| Quality Attribute | Supply-Chain Security |
| Requirement Statement | The QMDB dependency boundary shall commit composer.lock, minimize and approve packages/plugins, verify provenance and integrity, run composer audit and vulnerability/abandonment/license reviews, produce an SBOM, prohibit uncontrolled production installation, and operate normal and emergency update processes. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Security Critical |
| Applicable System Components | Composer dependencies, CI/CD, build environment |
| Applicable Actors | Developer, dependency reviewer, Security and Release Governance |
| Stimulus or Trigger | Add/update dependency, advisory, abandonment, license change, or build |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Verify source/lock/integrity/license, scan current advisories, assess reachability/severity, patch or mitigate, update SBOM, and block release under approved severity policy. |
| Response Measure | Each artifact maps to reviewed lock and SBOM; audit/plugin/license/provenance findings have disposition; emergency patch is attributable and tested. |
| Measurement Source | Composer/CI audit, SBOM, provenance and review records |
| Failure Behavior | Block build/promotion or disable affected feature through approved mitigation; never install ad hoc in production. |
| Security or Privacy Impact | Reduces compromised, vulnerable, abandoned or legally incompatible supply chain. |
| Dependencies | QMDB-BL-001; QMDB-FR-OPS-003 |
| Open Parameter References | QMDB-PAR-027 |
| Related Functional Requirements | QMDB-FR-OPS-003 |
| Related Use Cases | QMDB-UC-060 |
| Related Business Invariants | INV-028 |
| Related Threats | QMDB-THR-037 |
| Related Controls | QMDB-CTL-021; QMDB-CTL-025 |
| Planned Implementation Phase | P1 — Engineering and Repository Foundation |
| Verification Method | Lock consistency, provenance, audit, plugin, license, SBOM and emergency-update tests |
| Required Evidence | composer.lock, audit output, SBOM, approvals and remediation evidence |
| Acceptance Criteria | An unapproved, unverifiable, critically vulnerable or uncontrolled dependency cannot enter a promoted artifact. |
| Status | Parameter Pending |

## QMDB-NFR-SUP-002 — Reproducible promoted artifact and secure deployment

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-SUP-002 |
| Title | Reproducible promoted artifact and secure deployment |
| Quality Attribute | Supply-Chain and Deployment Security |
| Requirement Statement | The QMDB delivery boundary shall create reproducible CI artifacts with checksums, build identity/time and dependency manifest, scan/sign where supported, preserve source traceability and rollback artifacts, promote rather than rebuild, and deploy only through approved audited actors with migration sequencing, incremental health verification and rollback triggers. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Operational |
| Applicable System Components | CI/CD, artifact registry, containers, deployment, MySQL migrations |
| Applicable Actors | Build service, Release Manager, deployment actor |
| Stimulus or Trigger | Build, promote, deploy, migrate, health failure, rollback, or emergency deployment |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Attest and retain artifact, verify preconditions/readiness/health, roll out incrementally, monitor, stop/rollback on trigger, audit flags/configuration, and prohibit direct untracked server edits. |
| Response Measure | Rebuild comparison, checksum/source/SBOM trace, promotion identity, deployment approval, health/readiness, rollback and emergency review evidence pass. |
| Measurement Source | Artifact registry, CI attestation, deployment audit |
| Failure Behavior | Stop promotion or restore prior known artifact/schema compatibility; preserve failed evidence for review. |
| Security or Privacy Impact | Prevents artifact substitution, environment drift and untracked deployment. |
| Dependencies | QMDB-BL-001; QMDB-FR-OPS-003 |
| Open Parameter References | QMDB-PAR-027 |
| Related Functional Requirements | QMDB-FR-OPS-003 |
| Related Use Cases | QMDB-UC-060 |
| Related Business Invariants | INV-028; INV-030 |
| Related Threats | QMDB-THR-037; QMDB-THR-039 |
| Related Controls | QMDB-CTL-021; QMDB-CTL-025 |
| Planned Implementation Phase | P12 — Quality, Accessibility, and Production Hardening |
| Verification Method | Reproducibility, checksum/signature, traceability, scan, promotion, deployment and rollback tests |
| Required Evidence | Attestation, artifact hashes, scan, approvals, health and rollback log |
| Acceptance Criteria | The deployed artifact is attributable to reviewed source and is not rebuilt or manually altered between environments. |
| Status | Parameter Pending |

## Change control

Changes require impact review across the [parameter register](../../operations/quality-attribute-parameter-register.md), [threat model](../../security/threat-model.md), [acceptance scenarios](../P0-B03-non-functional-acceptance-scenarios.md), and [traceability matrix](../P0-B03-traceability-matrix.md). Locked ADR-001 through ADR-020 cannot be weakened here.

