# QMDB Requirement Coverage and Traceability Summary

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Source Baseline | QMDB-BL-001 |
| Freeze ID | QMDB-P0-FRZ-001 |
| Batch ID | QMDB-P0-CLOSE |
| Document Title | Requirement Coverage and Traceability Summary |
| Document Version | 1.0.0 |
| Document Status | Approved consolidated coverage |
| Document Owner Role | Requirements and Quality Assurance |
| Last Updated | 2026-08-24 |
| Approval Status | Coverage verified under QMDB-P0-FRZ-001 |
| Related Documents | [Implementation map](../implementation/requirements-to-implementation-map.md); [B01 traceability](../requirements/P0-B01-traceability-matrix.md); [B02 traceability](../requirements/P0-B02-traceability-matrix.md); [B03 traceability](../requirements/P0-B03-traceability-matrix.md); [B04 traceability](../requirements/P0-B04-traceability-matrix.md) |

## Purpose

Consolidate P0 requirement/catalog counts, orphan analysis, cross-cutting coverage and primary implementation routing.

## Scope

Source traceability matrices retain detailed relationships. The implementation map adds exactly one primary phase/batch for each of 263 approved requirements without duplicating or weakening source obligations.

## Controlled counts

| Catalog | Actual count |
| --- | ---: |
| P0-B01 Requirements | 68 |
| P0-B02 Functional Requirements | 113 |
| P0-B03 Non-Functional Requirements | 50 |
| P0-B04 Data Requirements | 32 |
| Total P0 Requirements | 263 |
| Use Cases | 65 |
| User Journeys | 24 |
| State Machines | 29 |
| Capabilities | 32 |
| Events | 47 |
| Notifications | 29 |
| Audit Event Categories | 15 |
| Edge Cases | 80 |
| Functional Acceptance Scenarios | 48 |
| Non-Functional Acceptance Scenarios | 63 |
| Threats | 46 |
| Abuse Cases | 34 |
| Controls | 30 |
| Risks | 63 |
| Quality Parameters | 34 |
| Data Quality Rules | 24 |
| Logical Tables | 250 |
| Relationships | 469 |
| Constraints | 785 |
| Indexes | 658 |

## Orphan analysis

| Test | Result | Evidence |
| --- | --- | --- |
| Requirement without owning module/component | 0 | Source metadata plus consolidated implementation map. |
| Requirement without implementation phase | 0 | B01 phase gap resolved; all 263 mappings have a primary phase. |
| Critical requirement without acceptance/verification | 0 | FR/NFR/DR acceptance fields and B01 verification/downstream traceability. |
| Sensitive functional requirement without NFR coverage | 0 | B02/B03 traceability and cross-cutting coverage below. |
| Requirement without traceability entry | 0 | Four source matrices plus 263-row implementation map. |
| Threat without controls | 0 | All 46 threat rows carry planned controls; 42 Critical threats reviewed. |
| Critical risk without mitigation/owner/gate | 0 | Risk register closeout supplement covers all 63 risks. |
| Control without verification | 0 | All 30 controls appear in the security verification matrix. |
| State machine without storage | 0 | All 29 state-machine IDs appear in B04 readiness storage mapping. |
| Table without requirement/aggregate/migration | 0 | All 250 tables map to requirements, one aggregate and one migration group. |
| Tenant table without tenant controls | 0 | All 158 tenant tables have Workspace ownership/FK/key/index obligations. |
| Sensitive table without classification | 0 | All 250 table handling records exist; 103 sensitive tables map QMDB-DR-029. |
| Index without purpose/access path | 0 | All 658 indexes carry query/FK/uniqueness purpose; 47 principal patterns are catalogued. |
| Open decision without owner/resolution phase | 0 | All 65 open decisions have keyed closeout classification. |

## Cross-cutting coverage

| Concern | Authoritative coverage |
| --- | --- |
| Tenant isolation | INV-001/002; tenant FR/NFR; QMDB-DR-002/003; 158 tables and 172 composite relationships. |
| Authentication | Identity FRs; credential/MFA/session NFRs; QMDB-CTL-002–004 and negative tests. |
| Authorization | Deny-by-default RBAC plus ABAC/resource policy, scoped exceptional access and QMDB-CTL-004/005/008. |
| Child safety | Minor-safety matrix, privacy NFRs, safe defaults, moderation and QMDB-CTL-015–017. |
| Guardian consent | Guardian/Consent FRs, evidence/version/withdrawal lifecycles and QMDB-CTL-015–017. |
| Canonical Qur’an integrity | QRF requirements; QMDB-DR-011; checksummed releases; QMDB-CTL-028. |
| Scoring integrity | Exact-decimal, server-authoritative scoring; immutable Score Sheet versions and QMDB-CTL-010/014. |
| Result corrections | Governed appeal, correction, supersession and publication evidence; QMDB-CTL-014/029. |
| Certificates | Signature reference, verification, revocation and supersession models; QMDB-CTL-013/029. |
| Media privacy | Private object storage, quarantine, consent-gated derivatives and no MySQL BLOB. |
| Moderation | Moderation/report/takedown/appeal lifecycles and separation-of-duties controls. |
| Accessibility | WCAG 2.2 AA target with automated and manual assistive-technology verification. |
| Arabic and RTL | Full directionality, mixed content, localization and Arabic-reference integrity checks. |
| Low-bandwidth operation | Mobile/reflow, bounded payload and degraded/interrupted-network behavior. |
| Audit integrity | Append-oriented audit, checkpoints, correlation and QMDB-CTL-020. |
| Transactional outbox | Same-transaction durable outbox, claimed delivery and replay-safe evidence. |
| Idempotency | Stable request/event keys, uniqueness, bounded retries and duplicate rejection/replay. |
| Backup and recovery | Continuity requirements, restore evidence, QMDB-MIG ordering and QMDB-CTL-023/026. |
| Offline venue mode | Edge packages, integrity/expiry, conflict/reconciliation behavior and QMDB-CTL-027. |
| Workload isolation | Five service tiers; competition-critical work remains above social/media/analytics. |

## Implementation mapping result

- All 263 approved requirements occur exactly once in the [requirements-to-implementation map](../implementation/requirements-to-implementation-map.md).
- All 14 P1-primary requirements map to a defined QMDB-P1-B01–B10 batch.
- Later requirements use one controlled `QMDB-P#-BACKLOG` identifier and the applicable `QMDB-P#-CLOSE` verification batch until their phase is decomposed.
- Each row includes an owning module/component, phase, batch, verification route, migration group/scope and security-control mapping.
- Refinement may split a phase backlog but may not change a requirement’s meaning or primary authority without freeze change control.

## Exact next action

Use the implementation map to execute QMDB-P1-B01, then refine only the next eligible phase backlog after dependencies and decisions satisfy the global Definition of Ready.
