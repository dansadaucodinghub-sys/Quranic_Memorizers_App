# QMDB Implementation Entry Point

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Source Baseline | QMDB-BL-001 |
| Freeze ID | QMDB-P0-FRZ-001 |
| Batch ID | QMDB-P0-CLOSE |
| Document Title | Implementation Entry Point |
| Document Version | 1.0.0 |
| Document Status | P1 READY WITH DEFERRED DECISIONS |
| Document Owner Role | Product, Architecture and Engineering Governance |
| Last Updated | 2026-08-24 |
| Approval Status | QMDB-P1-B01 authorized under explicit project-owner instruction and successful readiness validation |
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
5. [QMDB-P1-B01 implementation prompt](prompts/QMDB-P1-B01-core-php-repository-and-runtime-foundation.md) — the only implementation batch currently authorized.

## Current authorization

P0 is complete and frozen with status `READY_WITH_DEFERRED_DECISIONS`. There are no decisions that block QMDB-P1-B01. Deferred decisions remain binding release gates for their assigned phases and must not be resolved by undocumented implementation choices.

## Exact next action

Execute `QMDB-P1-B01 — Core PHP Repository and Runtime Foundation`. B01 may establish repository/runtime foundations only; it must not create QMDB domain migrations, implement business modules, or advance later batches.

## Governance

All work must preserve the [baseline freeze and change-control contract](../closeout/05-baseline-freeze-and-change-control.md), cite the [freeze manifest](../closeout/qmdb-p0-baseline-freeze.yaml), and update [project state](../project/project-state.md) only after objective validation evidence exists.
