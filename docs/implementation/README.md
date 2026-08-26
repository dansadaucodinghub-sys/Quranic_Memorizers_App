# QMDB Implementation Entry Point

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Source Baseline | QMDB-BL-001 |
| Freeze ID | QMDB-P0-FRZ-001 |
| Batch ID | QMDB-P1-CLOSE |
| Document Title | Implementation Entry Point |
| Document Version | 1.0.0 |
| Document Status | P1 COMPLETE — P2-B01 BLOCKED BY OD-051 AND OD-052 |
| Document Owner Role | Product, Architecture and Engineering Governance |
| Last Updated | 2026-08-26 |
| Approval Status | QMDB-P1-CLOSE accepted after executable engineering verification; P2-B01 is not authorized |
| Related Documents | [Closeout entry point](../closeout/README.md); [freeze manifest](../closeout/qmdb-p0-baseline-freeze.yaml); [project state](../project/project-state.md) |

## Purpose

This directory converts the frozen P0 requirements baseline into governed implementation work. It does not contain application code and does not itself authorize production release.

## Scope

The artifacts cover phased implementation routing, readiness/completion policy, the P1 backlog and the first executable prompt. Frozen requirements and locked architecture are authoritative; roadmap/backlog text is approved implementation guidance. Open decisions remain controlled, but none blocks B01. Later-phase and release blockers must close at their recorded gates.

## Authoritative implementation documents

1. [Phase and batch roadmap](phase-and-batch-roadmap.md) — implementation sequence from P1 through P13.
2. [Requirements-to-implementation map](requirements-to-implementation-map.md) — one implementation disposition for every approved P0 requirement.
3. [Definition of Ready and Done](definition-of-ready-and-done.md) — mandatory entry, completion, evidence, and state-transition rules.
4. [P1 engineering foundation backlog](P1-engineering-foundation-backlog.md) — executable ordering for QMDB-P1-B01 through QMDB-P1-CLOSE.
5. [QMDB-P1-B01 implementation prompt](prompts/QMDB-P1-B01-core-php-repository-and-runtime-foundation.md) — preserved executable batch specification.
6. [CI, build, security, and release-artifact standard](ci-build-and-release-standard.md) — executable B10 pipeline and artifact contract.
7. [P1 closeout evidence](../closeout/p1/README.md) — final batch ledger, executable evidence, risk disposition, freeze, and P2 readiness decision.

## Current authorization

P0 remains complete and frozen. P1-B01 through P1-B10 are complete and P1 is closed. Deferred operational evidence is
listed in the P1 closeout package and remains binding at its assigned deployment or release gate. OD-051 and OD-052 are
the explicit `BLOCKS_P2_B01` decisions; no P2 identity schema or authentication implementation is authorized until the
qualified owners approve those normalization contracts.

## Exact next action

Resolve OD-051 and OD-052 through Identity, Security, and Privacy Governance, record approved email and phone
normalization/test-vector decisions, then rerun the P2-B01 readiness gate. Do not invent those policy decisions in code.

## Governance

All work must preserve the [P0 baseline freeze and change-control contract](../closeout/05-baseline-freeze-and-change-control.md),
cite the [P0 freeze manifest](../closeout/qmdb-p0-baseline-freeze.yaml), and respect the
[P1 engineering freeze](../closeout/p1/06-P1-engineering-freeze-and-change-control.md). Project state changes require
objective validation evidence.
