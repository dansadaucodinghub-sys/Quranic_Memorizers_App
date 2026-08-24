# QMDB Baseline Freeze and Change Control

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Source Baseline | QMDB-BL-001 |
| Freeze ID | QMDB-P0-FRZ-001 |
| Batch ID | QMDB-P0-CLOSE |
| Document Title | Baseline Freeze and Change Control |
| Document Version | 1.0.0 |
| Document Status | APPROVED — baseline governance contract |
| Document Owner Role | Product and Architecture Governance |
| Last Updated | 2026-08-24 |
| Approval Status | Approved under explicit project-owner instruction and successful repository readiness validation |
| Related Documents | [Freeze manifest](qmdb-p0-baseline-freeze.yaml); [closeout report](01-P0-closeout-report.md); [project state](../project/project-state.md); [Definition of Ready and Done](../implementation/definition-of-ready-and-done.md) |

## 1. Purpose

This document defines the authoritative freeze boundary for the completed P0 requirements baseline, the permitted forms of change, the evidence required for approval, and the rule that implementation may not silently diverge from approved requirements.

## Scope

The contract governs every frozen P0 product, functional, non-functional, security, privacy, accessibility, data and implementation-readiness artifact, plus the controlled dynamic ledgers that interpret the freeze. It distinguishes locked/approved authority from open decisions and deferred module policy; none of those deferrals blocks QMDB-P1-B01.

## 2. Freeze decision

QMDB-P0-B01, QMDB-P0-B02, QMDB-P0-B03, QMDB-P0-B04, and QMDB-P0-CLOSE are complete. QMDB-BL-001 is frozen as `READY_WITH_DEFERRED_DECISIONS`. The freeze authorizes only the next approved implementation batch, `QMDB-P1-B01`; it does not authorize later phases, production release, national rollout, or resolution by assumption of a deferred decision.

The machine-readable authority is [qmdb-p0-baseline-freeze.yaml](qmdb-p0-baseline-freeze.yaml). Its SHA-256 inventory covers 82 frozen files. Eight explicitly listed governance ledgers are intentionally dynamic and excluded from their own checksum authority.

## 3. Frozen baseline classes

Baseline material uses the following complete classification vocabulary:

- `LOCKED_ARCHITECTURE` — a constitutional or architectural constraint that implementation may not override.
- `APPROVED_REQUIREMENT` — an approved baseline, functional, non-functional, data, acceptance, control, privacy, security, or operational obligation.
- `APPROVED_TECHNICAL_CONVENTION` — an approved implementation convention, roadmap, readiness contract, or implementation prompt.
- `CONTROLLED_OPEN_DECISION` — an unresolved decision with an owner, conservative assumption, and required resolution phase.
- `CONTROLLED_PARAMETER` — a threshold or configuration value whose source, owner and release gate remain controlled.
- `DEFERRED_MODULE_POLICY` — a product/module policy intentionally deferred until its bounded implementation phase.
- `REFERENCE_EVIDENCE` — supporting catalogs, mappings, diagrams, manifests, or traceability evidence that interpret the approved baseline.
- `DYNAMIC_PROJECT_STATE` — a governed ledger intentionally expected to change as execution advances.

The repository implementation must satisfy the combined authority of these classes. Classification does not weaken an obligation stated in a supporting artifact.

## 4. Dynamic governance files

The following files remain controlled but mutable and are excluded from the checksum set to avoid a circular or operationally stale freeze:

1. `docs/README.md`
2. `docs/project/project-state.md`
3. `docs/project/open-decisions.md`
4. `docs/project/risk-register.md`
5. `docs/closeout/README.md`
6. `docs/implementation/README.md`
7. `docs/closeout/03-contradiction-gap-and-resolution-register.md`
8. `docs/closeout/qmdb-p0-baseline-freeze.yaml`

Changes to these files still require traceable governance. Exclusion means only that their current bytes are not frozen by the manifest.

## 5. Change classes

| Class | Permitted use | Approval | Baseline effect |
| --- | --- | --- | --- |
| `PATCH` | Typographical, link, formatting, or unambiguous editorial repair with no semantic effect | Document owner and reviewer | Update affected file hash; keep baseline ID; increment patch version |
| `MINOR` | Backward-compatible clarification, new trace link, test refinement, or resolved deferred decision that does not invalidate an approved obligation | Product, architecture, and affected control/data owner | Reassess coverage; update hashes; increment minor version |
| `MAJOR` | Changed boundary, requirement meaning, identifier, invariant, authority model, data contract, security/privacy posture, or incompatible implementation contract | Formal change-control board and all affected owners | New baseline version; impact analysis, regression plan, and re-approval required |
| `EMERGENCY` | Urgent security, privacy, safety, legal, or data-integrity correction | Incident authority plus retrospective board approval | Immediate controlled exception; evidence, rollback, and post-incident re-baseline mandatory |

## 6. Required change request record

Every proposed change to a frozen file must provide this explicit contract:

| Required field | Meaning |
| --- | --- |
| Change Request ID | Stable identifier for the governed request |
| Requested Change | Exact proposed replacement or addition |
| Current Baseline | Baseline/freeze/version and current hashes |
| Reason | Evidence-backed business or technical rationale |
| Affected Requirements | All changed or constrained requirement IDs |
| Affected Modules | Modules/components touched |
| Affected Tables | Tables, columns, constraints and indexes touched |
| Security Impact | Threat, control and assurance consequence |
| Privacy Impact | Personal-data, consent, retention and rights consequence |
| Child-Safety Impact | Minor/guardian/safeguarding consequence |
| Accessibility Impact | WCAG, Arabic/RTL, assistive and inclusive-use consequence |
| Migration Impact | Forward, compatibility, data, rollback and recovery effect |
| Compatibility Impact | API, event, schema, client and operational compatibility |
| Testing Impact | Required new/regression evidence |
| Deployment Impact | Environment, release, monitoring and support consequence |
| Rollback Strategy | Safe restoration or compensation plan |
| Approval Authority | Required role-based approvers |
| Decision | Approved, rejected, deferred or superseded result |

The record must additionally include:

1. change-request ID, requester, date, and requested change class;
2. exact affected file paths, sections, requirement/decision/risk/control IDs, and current hashes;
3. business, legal, security, privacy, safety, accessibility, data, operational, and migration rationale;
4. affected phases, batches, modules, aggregates, APIs, migrations, tests, acceptance scenarios, and released behavior;
5. backward-compatibility and tenant-isolation analysis;
6. threat, abuse, consent, minor-safety, audit, retention, and disaster-recovery impact where relevant;
7. proposed replacement wording and updated traceability;
8. verification plan, rollback plan, approvers, approval result, and effective version;
9. replacement hashes and confirmation that the freeze manifest and project state were regenerated; and
10. implementation work items required to adopt the approved change.

## 7. No-silent-drift rule

Implementation code, migrations, tests, seed data, configuration, deployment definitions, and operational procedures must not contradict, weaken, rename, or bypass the frozen baseline without an approved change record. In particular, a coding agent must not silently change the locked architecture, tenant model, authorization model, score-calculation authority, official-record lifecycle, Qur’an data governance, guardian safety controls, media-storage architecture, audit design, or database choice. A discrepancy discovered during implementation is a stop-and-escalate condition for the affected work item. The engineering team must either implement the frozen contract or obtain an approved baseline change before merging.

Deferred decisions must use their recorded conservative assumption only within the scope and phase stated in the open-decisions register. A conservative assumption is not a permanent decision and must not be embedded irreversibly.

## 8. Integrity and verification

SHA-256 is the freeze integrity algorithm. Verification compares every manifest entry against the repository-relative path and fails for a missing file, an unexpected changed hash, a duplicate path, or a checksum entry for a dynamic-exclusion file. The manifest itself is excluded because self-hashing is circular.

After any approved frozen-file change, governance must regenerate all frozen-file hashes, verify the complete inventory, update the baseline version, record the approval, and rerun the P0 traceability and readiness gates. Partial checksum updates are prohibited.

## 9. Enforcement through implementation

- Every implementation backlog item must cite requirement, control, decision, risk, and source-file IDs.
- Every pull request must state which frozen obligations it implements and which tests prove conformance.
- Automated architecture, security, isolation, migration, unit, integration, and acceptance tests must enforce applicable contracts.
- Release approval must fail when a required deferred decision for that release gate remains unresolved.
- Production and national-rollout authorization remain separate gates; P1 readiness is not release readiness.

## 10. Exact next action

Execute only `QMDB-P1-B01 — Core PHP Repository and Runtime Foundation` using [the approved prompt](../implementation/prompts/QMDB-P1-B01-core-php-repository-and-runtime-foundation.md). Do not create domain migrations or implement later P1 batches as part of B01.
