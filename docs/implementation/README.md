# QMDB Implementation Entry Point

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Source Baseline | QMDB-BL-001 |
| Freeze ID | QMDB-P0-FRZ-001 |
| Batch ID | QMDB-P3-B02 |
| Document Title | Implementation Entry Point |
| Document Version | 1.5.0 |
| Document Status | P1 COMPLETE / FROZEN; P2 COMPLETE / FROZEN; P3-B01 COMPLETE; P3-B02 COMPLETE |
| Document Owner Role | Product, Architecture and Engineering Governance |
| Last Updated | 2026-09-01 |
| Approval Status | P3-B02 is complete under the separately authorized private Person-profile scope |
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
17. [MFA, passkey, recovery-code and step-up standard](mfa-passkey-recovery-code-and-step-up-standard.md) — implemented
    B05 assurance, authenticator, ceremony, recovery and privileged-mutation contract.
18. [P2-B05 implementation report](reports/QMDB-P2-B05-implementation-report.md) — B05 scope, security boundaries,
    executable evidence and explicit physical/deployment gates.
19. [Roles, permissions, and scoped authorization standard](roles-permissions-and-scoped-authorization-standard.md) —
    implemented B06 catalog, decision, assignment, delegation, readiness and concurrency contract.
20. [P2-B06 implementation report](reports/QMDB-P2-B06-implementation-report.md) — B06 implementation, schema,
    security, regression, release and closeout evidence.
21. [Tenant Context, workspace switching, and data access standard](tenant-context-workspace-switching-and-data-access-standard.md)
    — B07 session authority, relational integrity, HTTP, frontend, repository, job, cache, and export boundaries.
22. [P2-B07 implementation report](reports/QMDB-P2-B07-implementation-report.md) — B07 implementation, prerequisite
    correction, schema, isolation, regression, release, and closeout evidence.
23. [Temporary privilege, support-access, and break-glass standard](privileged-access-temporary-support-and-break-glass-standard.md)
    — B08 controlled exceptional-access contract.
24. [P2-B08 implementation report](reports/QMDB-P2-B08-implementation-report.md) — B08 executable evidence.
25. [Security events, audit integrity, and account-state standard](security-events-audit-integrity-and-account-state-standard.md)
    — B09 tamper-evident audit and account-state contract.
26. [P2-B09 implementation report](reports/QMDB-P2-B09-implementation-report.md) — B09 implementation and closeout state.
27. [Identity and tenant security hardening standard](identity-and-tenant-security-hardening-standard.md) — B10 route,
    repository, threat, and adversarial verification boundary.
28. [P2-B10 historical recovery](reports/QMDB-P2-B10-historical-recovery.md) — selective recovery and reconciliation record.
29. [P2 threat-model reconciliation](../security/P2-threat-model-reconciliation.md) — B10 control-to-evidence record.
30. [P2 route-security matrix](../security/P2-route-security-matrix.md) — closed production route inventory.
31. [P2-B10 security defect register](reports/QMDB-P2-B10-security-defect-register.md) — discovered hardening defects and disposition.
32. [Nigerian administrative geography standard](nigerian-administrative-geography-standard.md) — P3-B01 data,
    public-read, caching, accessibility, and future-release boundary.
33. [Nigeria dataset provenance](../data/nigeria-administrative-geography-provenance.md) — source, checksum,
    canonicalization, and known limitations for the first governed release.
34. [P3-B01 implementation report](reports/QMDB-P3-B01-implementation-report.md) — executable delivery,
    public-interface, performance, phase-control, and validation evidence.
35. [P3-B02 requirements-to-batches record](p3-b02-requirements-to-batches.md) — bounded requirement coverage,
    evidence and exclusions for the private Person-profile foundation.
36. [P3-B02 implementation report](reports/QMDB-P3-B02-implementation-report.md) — delivery, security boundaries,
    deferred policy and validation evidence.
37. [Person-profile standard](person-memorizer-reciter-competitor-and-guardian-profile-standard.md) — implemented
    Person, role, guardianship, privacy and interface boundary.

## Current authorization

P0, P1 and P2 remain frozen. `QMDB-P3-OPEN-B01` authorized the completed Nigeria administrative geography registry;
`QMDB-P3-B02-EXEC` authorized the completed bounded private Person-profile foundation. Organizations, consent,
public discovery, competition and tenant-scoped domain capabilities remain unstarted.

## Exact next action

Do not begin P3-B03 without separate authorization. Preserve the P3-B01 public-id/dataset provenance contracts and
the P3-B02 private-profile, minor-safety and no-public-discovery boundaries.

## Governance

All work must preserve the [P0 baseline freeze and change-control contract](../closeout/05-baseline-freeze-and-change-control.md),
cite the [P0 freeze manifest](../closeout/qmdb-p0-baseline-freeze.yaml), and respect the
[P1 engineering freeze](../closeout/p1/06-P1-engineering-freeze-and-change-control.md). Project state changes require
objective validation evidence.
