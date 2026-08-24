# Maintainability, Testability, and Release-Quality Requirements

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B03 |
| Document Title | Maintainability, Testability, and Release-Quality Requirements |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Engineering Quality and Release Governance |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed for governance approval; inherited locked decisions remain binding |
| Related Documents | [B03 index](../P0-B03-non-functional-requirements-index.md); [Traceability](../P0-B03-traceability-matrix.md); [Product constitution](../../project/product-constitution.md) |

## Purpose

Define modular code, safe database change, comprehensive testing, release gates, and change-management obligations.

## Scope

Core PHP modules, MySQL migrations, APIs, browser interfaces, workers, media, integrations, CI/CD evidence, releases, configuration, and emergency changes.

## Interpretation and control posture

Requirements are implementation-neutral unless QMDB-BL-001 locks a technology. “Approved Baseline” identifies a direct locked-baseline obligation; “Proposed” requires governance approval; “Parameter Pending” is enforceable in structure and measurement but its target remains governed by the parameter register. Derived stores never replace authoritative MySQL records. Business policy and technical enforcement are both identified through traceability; neither creates legal or religious authority.

## Coverage profile

Strict typing and module boundaries; thin controllers and dependency direction; versioned migrations and expand-contract safety; unit through recovery testing; positive/negative authorization and tenancy testing; exact scoring and version preservation; security, accessibility and performance gates; attributable reviewed releases and rollback.

## QMDB-NFR-MNT-001 — Maintainable modular Core PHP design

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-MNT-001 |
| Title | Maintainable modular Core PHP design |
| Quality Attribute | Maintainability |
| Requirement Statement | The QMDB code boundary shall use PHP 8.5 strict typing, PSR-4, explicit dependencies and module ownership, thin controllers, no SQL in controllers/templates, no template business logic, no service locator or global mutable state, repository abstractions, explicit transactions, domain events/value objects, configuration validation and enforced dependency direction. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Supporting |
| Applicable System Components | Core PHP modular monolith and tests |
| Applicable Actors | Developer, reviewer, architect |
| Stimulus or Trigger | Code change, module interaction, configuration, deprecation, or architecture review |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Keep Domain/Application independent of Presentation/Infrastructure details, document decisions and errors, deprecate deliberately, and fail invalid configuration before service readiness. |
| Response Measure | Architecture tests and review prove dependency direction, prohibited-pattern absence, module ownership and transaction boundaries. |
| Measurement Source | Static analysis, architecture tests, code review |
| Failure Behavior | Block build/release on boundary or configuration violation. |
| Security or Privacy Impact | Reduces coupling, hidden logic, unsafe SQL and change risk. |
| Dependencies | QMDB-BL-001; QMDB-FR-OPS-003 |
| Open Parameter References | None |
| Related Functional Requirements | QMDB-FR-OPS-003 |
| Related Use Cases | QMDB-UC-020 |
| Related Business Invariants | INV-028 |
| Related Threats | QMDB-THR-037 |
| Related Controls | QMDB-CTL-021 |
| Planned Implementation Phase | P1 — Engineering and Repository Foundation |
| Verification Method | Static analysis; architecture, configuration and code review |
| Required Evidence | Dependency graph, prohibited-pattern report, module ownership and ADRs |
| Acceptance Criteria | No production module violates locked layer direction or places SQL/business rules in presentation code. |
| Status | Approved Baseline |

## QMDB-NFR-MNT-002 — Safe versioned MySQL change

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-MNT-002 |
| Title | Safe versioned MySQL change |
| Quality Attribute | Maintainability |
| Requirement Statement | The QMDB database-change boundary shall use reviewed versioned migrations with forward and rollback/compensating strategy, named constraints and indexes, tenant-integrity review, rehearsal, long-running safeguards, audit, approval, and backward-compatible expand-and-contract sequencing where required. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Business Critical |
| Applicable System Components | MySQL schema, migrations, deployment |
| Applicable Actors | Database developer, reviewer, migration approver, operator |
| Stimulus or Trigger | Schema/data/index change or deployment |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Validate migration against production-like shape, preserve application compatibility and integrity, sequence safely, observe progress, and stop/rollback/compensate on gate failure without manual production edits. |
| Response Measure | Migration lint/dry-run, constraint/index review, rehearsal timing, rollback/compensation and audit evidence pass. |
| Measurement Source | CI migration validation, staging rehearsal, deployment audit |
| Failure Behavior | Stop promotion and preserve prior compatible artifact/schema; execute approved rollback or compensation. |
| Security or Privacy Impact | Prevents corruption, prolonged lock and untracked drift. |
| Dependencies | QMDB-BL-001; QMDB-FR-OPS-003; QMDB-FR-OPS-004 |
| Open Parameter References | QMDB-PAR-027 |
| Related Functional Requirements | QMDB-FR-OPS-003; QMDB-FR-OPS-004 |
| Related Use Cases | QMDB-UC-020 |
| Related Business Invariants | INV-005; INV-028 |
| Related Threats | QMDB-THR-037; QMDB-THR-039 |
| Related Controls | QMDB-CTL-021; QMDB-CTL-025 |
| Planned Implementation Phase | P1 — Engineering and Repository Foundation |
| Verification Method | Migration static review, dry-run, compatibility, lock, rollback and recovery tests |
| Required Evidence | Migration review, rehearsal output, rollback plan, approval and audit |
| Acceptance Criteria | An invalid, unreviewed, non-compatible, integrity-weakening or unrecoverable migration cannot deploy. |
| Status | Parameter Pending |

## QMDB-NFR-TST-001 — Risk-based test evidence

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-TST-001 |
| Title | Risk-based test evidence |
| Quality Attribute | Testability and Quality Assurance |
| Requirement Statement | The QMDB quality boundary shall require unit, integration, MySQL constraint/repository, authorization, tenant, scoring golden/property, API contract, browser E2E, media, queue/idempotency, offline sync, accessibility/RTL, security, performance/soak, backup/restore, DR and audit-chain tests appropriate to each change. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Business Critical |
| Applicable System Components | All code, data, interfaces, workers and operations |
| Applicable Actors | Developer, QA, security, accessibility, performance and recovery testers |
| Stimulus or Trigger | Change, release candidate, incident fix, dependency update, or recovery exercise |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Select tests from traceability, include positive/negative and edge cases, use production-like MySQL for relational behavior, preserve evidence and block on required failures. |
| Response Measure | Every protected use case has positive/negative authorization; every tenant repository has cross-workspace tests; retryable sensitive commands are idempotent; scoring/version/certificate/consent/Qur’an/projection invariants have explicit tests. |
| Measurement Source | CI reports, test artifacts, manual review and exercise records |
| Failure Behavior | Fail the release gate; quarantine flaky or unavailable evidence rather than waive silently. |
| Security or Privacy Impact | Reduces regression and false confidence. |
| Dependencies | QMDB-BL-001; QMDB-FR-SCR-003; QMDB-FR-QRF-004; QMDB-FR-CER-003; QMDB-FR-OFF-002 |
| Open Parameter References | QMDB-PAR-034 |
| Related Functional Requirements | QMDB-FR-SCR-003; QMDB-FR-QRF-004; QMDB-FR-CER-003; QMDB-FR-OFF-002 |
| Related Use Cases | QMDB-UC-036; QMDB-UC-045; QMDB-UC-061 |
| Related Business Invariants | INV-013; INV-015; INV-020; INV-030 |
| Related Threats | QMDB-THR-010; QMDB-THR-018; QMDB-THR-043 |
| Related Controls | QMDB-CTL-025 |
| Planned Implementation Phase | P12 — Quality, Accessibility, and Production Hardening |
| Verification Method | Traceability review and execution of required automated/manual suites |
| Required Evidence | CI evidence, manual accessibility/security/privacy reviews, load/recovery exercise reports |
| Acceptance Criteria | Compilation alone never satisfies release evidence and every critical invariant has a passing relevant test. |
| Status | Parameter Pending |

## QMDB-NFR-REL-001 — Attributable gated change and release

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-REL-001 |
| Title | Attributable gated change and release |
| Quality Attribute | Release and Change Management |
| Requirement Statement | The QMDB release boundary shall require pull request, peer review, protected branch, author/approver separation for sensitive change, attributable reproducible build, required test/security/accessibility/dependency/secret/container/migration/OpenAPI/performance/backup gates, release notes, feature controls, rollback and audited emergency review. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Operational |
| Applicable System Components | Source control, CI/CD, artifacts, configuration, deployment |
| Applicable Actors | Author, reviewer, Release Manager, emergency approver |
| Stimulus or Trigger | Normal or emergency code/configuration/dependency/database release |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Build once in CI, scan and attest, promote the same artifact, require approvals, monitor health, rollback on trigger, version configuration, and review emergency action afterward. |
| Response Measure | Each release has source-to-artifact identity, checksum/SBOM, gate outcomes, approvals, deployment/health/rollback evidence and no silent production edits. |
| Measurement Source | CI/CD and deployment audit |
| Failure Behavior | Stop promotion or rollback; emergency path remains time-bound, attributable and post-reviewed. |
| Security or Privacy Impact | Prevents supply-chain, unreviewed, non-reproducible and irreversible change. |
| Dependencies | QMDB-BL-001; QMDB-FR-OPS-003 |
| Open Parameter References | QMDB-PAR-027; QMDB-PAR-034 |
| Related Functional Requirements | QMDB-FR-OPS-003 |
| Related Use Cases | QMDB-UC-060 |
| Related Business Invariants | INV-028; INV-030 |
| Related Threats | QMDB-THR-037 |
| Related Controls | QMDB-CTL-021; QMDB-CTL-025 |
| Planned Implementation Phase | P12 — Quality, Accessibility, and Production Hardening |
| Verification Method | Release-pipeline, branch/approval, artifact, gate, rollback and emergency exercise |
| Required Evidence | Build attestation, gate report, approvals, deployment log, rollback evidence |
| Acceptance Criteria | A release with a failed mandatory gate, missing attribution, missing rollback or unreviewed emergency change cannot remain promoted. |
| Status | Parameter Pending |

## Change control

Changes require impact review across the [parameter register](../../operations/quality-attribute-parameter-register.md), [threat model](../../security/threat-model.md), [acceptance scenarios](../P0-B03-non-functional-acceptance-scenarios.md), and [traceability matrix](../P0-B03-traceability-matrix.md). Locked ADR-001 through ADR-020 cannot be weakened here.

