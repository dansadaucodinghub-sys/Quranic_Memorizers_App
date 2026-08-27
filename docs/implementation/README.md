# QMDB Implementation Entry Point

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Source Baseline | QMDB-BL-001 |
| Freeze ID | QMDB-P0-FRZ-001 |
| Batch ID | QMDB-P2-B04 |
| Document Title | Implementation Entry Point |
| Document Version | 1.0.0 |
| Document Status | P1 COMPLETE; P2-B01 THROUGH P2-B04 COMPLETE |
| Document Owner Role | Product, Architecture and Engineering Governance |
| Last Updated | 2026-08-27 |
| Approval Status | Recovery-authorized B01 through B04 accepted after complete executable gates; B05 is next |
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
8. [P2-B01 implementation report](reports/QMDB-P2-B01-implementation-report.md) — workspace, account, credential and tenant-boundary evidence.
9. [Registration, verification and password-authentication standard](account-registration-email-verification-and-password-authentication-standard.md) — implemented P2-B02 security and workflow contract.
10. [P2-B02 implementation report](reports/QMDB-P2-B02-implementation-report.md) — executable B02 evidence and corrections.
11. [Secure session, cookie and device standard](secure-session-cookie-and-device-standard.md) — implemented B03
    lifecycle, cookie, concurrency, and revocation contract.
12. [P2-B03 implementation report](reports/QMDB-P2-B03-implementation-report.md) — executable B03 evidence and corrections.
13. [Account recovery and security-notification standard](account-recovery-and-security-notification-standard.md) —
    implemented B04 recovery, reset, session invalidation and scheduled-delivery contract.
14. [P2-B04 implementation report](reports/QMDB-P2-B04-implementation-report.md) — executable B04 evidence and corrections.
15. [P2 implementation parameter register](../operations/p2-implementation-parameter-register.md) — executable defaults
    and unresolved production approvals without modifying the frozen P0 register.
16. [P2 implementation decisions](../project/p2-implementation-decisions.md) — controlled post-freeze technical decisions.

## Current authorization

P0 remains complete and frozen. P1-B01 through P1-B10 are complete and P1 is closed/frozen. The project-owner recovery
authorization established conservative executable OD-051/OD-052 contracts, after which P2-B01 through P2-B04 passed their
mandatory schema, security, MySQL, frontend, scanner, release and engineering-freeze gates.

## Exact next action

Execute `QMDB-P2-B05 — MFA, Passkeys, Recovery Codes, and Step-Up Authentication` without weakening the completed B02
identity-access, B03 session/device, or B04 recovery and security-notification contracts. Do not start B06 under the
recovery authorization.

## Governance

All work must preserve the [P0 baseline freeze and change-control contract](../closeout/05-baseline-freeze-and-change-control.md),
cite the [P0 freeze manifest](../closeout/qmdb-p0-baseline-freeze.yaml), and respect the
[P1 engineering freeze](../closeout/p1/06-P1-engineering-freeze-and-change-control.md). Project state changes require
objective validation evidence.
