# QMDB Contradiction, Gap, and Resolution Register

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Source Baseline | QMDB-BL-001 |
| Freeze ID | QMDB-P0-FRZ-001 |
| Batch ID | QMDB-P0-CLOSE |
| Document Title | Contradiction, Gap, and Resolution Register |
| Document Version | 1.0.0 |
| Document Status | Controlled dynamic closeout record |
| Document Owner Role | Architecture and Requirements Assurance |
| Last Updated | 2026-08-24 |
| Approval Status | Resolutions approved; deferred records retain named owner roles and release gates |
| Related Documents | [Closeout report](01-P0-closeout-report.md); [open decisions](../project/open-decisions.md); [risk register](../project/risk-register.md) |

## Purpose

Preserve every material closeout issue, the authority used to resolve it, and any bounded later dependency without silently deleting historical evidence.

## Scope

RESOLVED records are closed by this batch. DEFERRED_WITH_OWNER records do not block P1-B01 and block only the stated later gate. No record is ACCEPTED_RISK or BLOCKING.

## Register

| Gap ID | Type | Severity | Title | Source A | Source B or Missing Source | Description | Affected Requirements | Affected Modules | Affected Tables | Affected Risks | Resolution | Resolution Authority | Status | Blocks P1-B01 | Blocks Later Phase | Verification |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-GAP-001 | TERMINOLOGY_DRIFT | MEDIUM | Module labels listed as aggregates | P0-B04 data requirements | QMDB-AGG-001–038 | Identity and Authentication and Competition Configuration were module/group labels, not canonical aggregate records. | QMDB-DR-001–005; QMDB-DR-012; QMDB-DR-028–032 | Identity; Competition Configuration; cross-cutting | No table/column change | QMDB-RSK-060 | Removed only non-canonical labels and corrected trace summaries. | Canonical aggregate model and correction policy | RESOLVED | No | No | Aggregate membership/reference scan passed. |
| QMDB-GAP-002 | MISSING_PHASE_MAPPING | HIGH | B01 roadmap assignment remained open | B01 future-phase fields | Missing consolidated implementation map / OD-030 | Baseline requirements could not drive implementation batches directly. | 68 B01 requirements | All | Phase-dependent | QMDB-RSK-022; QMDB-RSK-061 | Created P1–P13 roadmap and 263-row primary mapping; resolved OD-030. | Current project-owner instruction and dependency model | RESOLVED | No | No | Every approved requirement has one primary phase/batch and verification route. |
| QMDB-GAP-003 | UNRESOLVED_TECHNICAL_DECISION | HIGH | Public/internal key convention blocked migration foundation | OD-047; OD-048 | ADR-030; ADR-031 were proposed | P1 could not form stable identifier contracts. | QMDB-DR-003; QMDB-DR-004 | Shared/Data | All logical tables | QMDB-RSK-035; QMDB-RSK-060 | Approved BIGINT UNSIGNED internal keys and UUIDv7/BINARY(16) public IDs. | Locked MySQL baseline and reversible pre-data convention | RESOLVED | No | No | ADR and open-decision statuses/reference checks passed. |
| QMDB-GAP-004 | UNRESOLVED_TECHNICAL_DECISION | HIGH | Isolation and retry convention blocked database foundation | OD-056; OD-057 | ADR-037 proposed; retry ADR missing | Transaction behavior was not final enough for P1-B05. | QMDB-DR-031; QMDB-NFR-DAT-001; QMDB-NFR-SCL-001 | Database/Shared | Authoritative mutable and queue tables | QMDB-RSK-048; QMDB-RSK-049 | Approved READ COMMITTED plus explicit locks/version/idempotency and ADR-053 bounded retry. | MySQL baseline and B04 concurrency model | RESOLVED | No | No | Decision, risk and P1-B05 contracts reconcile. |
| QMDB-GAP-005 | UNRESOLVED_TECHNICAL_DECISION | HIGH | Repository and namespace convention absent | P1-B01 readiness gate | Missing ADR | PSR-4 paths and module/layer boundaries were not deterministic. | QMDB-BND-001; QMDB-NFR-MNT-001 | Engineering foundation | Not applicable | QMDB-RSK-022; QMDB-RSK-023 | Added and approved ADR-054. | Locked Core PHP modular-monolith baseline | RESOLVED | No | No | P1 prompt and backlog use the same paths/namespaces. |
| QMDB-GAP-006 | MISSING_ACCEPTANCE_TEST | HIGH | Foundation quality-tool convention absent | P1-B01 readiness gate | Missing ADR | Documentation-only completion could not be mechanically rejected. | QMDB-CON-002; QMDB-NFR-MNT-001; QMDB-NFR-SUP-001 | Engineering foundation | Not applicable | QMDB-RSK-022; QMDB-RSK-023 | Added ADR-055 and executable PHPUnit/PHPStan/Composer/architecture gates. | B03 maintainability/supply-chain requirements | RESOLVED | No | No | P1 prompt requires source, tests and actual command results. |
| QMDB-GAP-007 | MISSING_PHASE_MAPPING | HIGH | Open decisions lacked blocker classifications | Open-decision register | Missing closeout classification | Earlier work could not distinguish P1 blockers from later release decisions. | Cross-cutting | All | Decision-dependent | All linked risks | Added keyed classification for all 70 records; resolved five; deferred 65. | P0-CLOSE decision-classification contract | RESOLVED | No | No | All records have category, blocker, owner, phase and impacts; zero BLOCKS_P1_B01. |
| QMDB-GAP-008 | MISSING_OWNER | HIGH | B04 risks lacked complete readiness/release fields | B04 risk extension | Missing level/owner/release/blocker supplement | Controls existed but production gate posture was incomplete. | QMDB-DR-001–032 | All data modules | All affected tables | QMDB-RSK-033–063 | Added keyed readiness classification for all 63 risks. | P0-CLOSE risk-finalization contract | RESOLVED | No | No | Every risk has controls, residual posture, owner, phase, trigger and gate flags. |
| QMDB-GAP-009 | UNRESOLVED_POLICY_DECISION | HIGH | Legal/privacy and child-policy authority remains external | OD-011–018; OD-041; OD-058 | Qualified authority decision | P0 cannot invent lawful basis, Minor/Guardian interpretation, transfer, rights or retention rules. | Privacy/child-safety NFRs and affected FR/DR | People; Privacy; Media; Operations | Classified personal/minor data | QMDB-RSK-008–010; QMDB-RSK-027–028; QMDB-RSK-058–059 | Preserve conservative denial/minimization and named P3/P9/P12 release gates. | Privacy/Child-Safety Governance with qualified review | DEFERRED_WITH_OWNER | No | P3/P9/P12 | Classification table contains owner, phase, assumption and impact. |
| QMDB-GAP-010 | UNRESOLVED_POLICY_DECISION | HIGH | Domain authorities and governed sources remain unappointed | OD-009; OD-010; OD-017; OD-019; OD-021–022; OD-061–066 | Qualified domain/governance authority | Organization, Qur’an, scoring, geography, certificate and appeal truth cannot be fabricated. | Affected domain FR/DR | P3–P8/P13 domains | Seed and official-record tables | QMDB-RSK-003–007; QMDB-RSK-038–044; QMDB-RSK-054; QMDB-RSK-061–062 | Keep seed/activation/issuance disabled until the named phase decision closes. | Named domain governance roles | DEFERRED_WITH_OWNER | No | P3–P8/P13 | Seed catalog and open-decision gates fail closed. |
| QMDB-GAP-011 | UNRESOLVED_POLICY_DECISION | MEDIUM | Providers and production operational values remain unselected | OD-001–007; OD-023–024; OD-036–040; OD-043; OD-046; OD-070 | Procurement and evidence | Foundation must remain vendor-neutral and cannot invent SLO/RTO/RPO/capacity values. | Operations/integration NFRs | Platform; Notifications; Media; Security | Provider metadata only | QMDB-RSK-016–025; QMDB-RSK-032; QMDB-RSK-050; QMDB-RSK-059 | Preserve adapters, parameters and affected P9/P12/P13 release gates. | Platform/Product/Continuity/Security Governance | DEFERRED_WITH_OWNER | No | P9/P12/P13 | Provider-neutral roadmap and parameter register validated. |
| QMDB-GAP-012 | UNRESOLVED_TECHNICAL_DECISION | MEDIUM | Later module precision, normalization and extraction choices remain open | OD-049–055; OD-059–060; OD-067–069 | Later implementation evidence | Values are unnecessary for P1-B01 and premature without domain/workload evidence. | QMDB-DR-005; QMDB-DR-011; QMDB-DR-015; QMDB-DR-022; QMDB-DR-032 | P2/P4/P6/P11/P12 | Affected logical tables/projections | QMDB-RSK-037–040; QMDB-RSK-045; QMDB-RSK-050; QMDB-RSK-056 | Preserve symbolic types, conservative primary reads and no premature extraction/partitioning. | Data/Identity/Qur’an/Scoring/Platform Governance | DEFERRED_WITH_OWNER | No | P2/P4/P6/P11/P12 | Manifest retains explicit open-decision references and safe defaults. |

## Summary

| Status | Count |
| --- | ---: |
| RESOLVED | 8 |
| DEFERRED_WITH_OWNER | 4 |
| ACCEPTED_RISK | 0 |
| BLOCKING | 0 |

## Exact next action

QMDB-P1-B01 may proceed. Before any later phase crosses a referenced release gate, its DEFERRED_WITH_OWNER records must be closed or reclassified through frozen-baseline change control.

