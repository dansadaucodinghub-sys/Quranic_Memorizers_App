# QMDB Definition of Ready and Definition of Done

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Source Baseline | QMDB-BL-001 |
| Freeze ID | QMDB-P0-FRZ-001 |
| Batch ID | QMDB-P0-CLOSE |
| Document Title | Global Definition of Ready and Definition of Done |
| Document Version | 1.0.0 |
| Document Status | MANDATORY DELIVERY POLICY |
| Document Owner Role | Engineering Quality and Architecture Governance |
| Last Updated | 2026-08-24 |
| Approval Status | Approved for all implementation work under QMDB-P0-FRZ-001 |
| Related Documents | [Roadmap](phase-and-batch-roadmap.md); [P1 backlog](P1-engineering-foundation-backlog.md); [freeze/change control](../closeout/05-baseline-freeze-and-change-control.md) |

## Purpose

Define objective entry and completion gates so implementation cannot advance through assertion, incomplete controls or documentation-only output.

## Scope

This policy applies to P1–P13 phases, batches, stories, migrations, fixes and releases. Frozen requirements and locked decisions are authoritative; this document is an approved delivery convention. Open decisions are non-blocking only within their recorded assumptions and phases.

## 1. Governing principle

Work may begin only when it is sufficiently understood, authorized, safe, and verifiable. Work is done only when executable behavior and objective evidence satisfy the frozen baseline. Compilation, documentation, or a developer assertion alone is not completion.

## 2. Definition of Ready — phase

A phase is ready only when:

1. the preceding phase close gate is approved;
2. all required entry criteria in the roadmap are met;
3. every open decision classified as blocking that phase is resolved and linked to an ADR or approved policy;
4. requirement, control, threat, abuse-case, risk, migration, data, privacy, accessibility, resilience, and operational scopes are identified;
5. required authoritative sources, environments, credentials, vendors, infrastructure, and owner roles are available without committing secrets;
6. migration sequencing, compatibility, rollback, backup, and recovery expectations are approved;
7. the phase verification plan defines functional, negative, authorization, tenant-isolation, security, privacy, accessibility, performance, resilience, and operational evidence as applicable; and
8. no unresolved contradiction with QMDB-BL-001 exists.

## 3. Definition of Ready — batch or work item

A batch or item is ready only when it has:

- a stable ID, objective, scope, explicit exclusions, owner, dependencies, and expected state transition;
- links to every applicable requirement, ADR, open decision, risk, control, acceptance scenario, data aggregate/table, API/event, and source document;
- identified file/component ownership, authorization expectation and tenant scope;
- testable acceptance criteria including error, empty, unauthorized, cross-Workspace, concurrency, replay, recovery, and accessibility behavior where relevant;
- an implementation and verification approach consistent with the modular-monolith boundary;
- reviewed data classification, validation, authorization, audit, idempotency, transaction, retention, and observability needs;
- a safe migration and rollback plan when persistent state changes;
- no unresolved decision marked as a blocker for the item;
- commands that can be run locally and in CI, including prerequisites and expected success conditions; and
- confirmation that the work does not silently alter a frozen requirement.

If any readiness condition is false, the item remains `NOT_READY`; implementation must not hide the gap through a guessed policy, placeholder success, disabled control, or irreversible default.

## 4. Definition of Done — implementation item

An implementation item is done only when:

1. all required source, configuration, migration, test, operational, and narrowly necessary documentation artifacts are present and reviewed;
2. behavior implements the approved contract with no placeholder, TODO, inert UI, fabricated authority, hard-coded secret, debug bypass, or unbounded operation;
3. PHP uses strict types; controllers remain thin; SQL is absent from controllers and templates; server-side validation, authentication, authorization, least privilege, tenant scoping, CSRF/XSS/injection protections, safe errors, secure session/cookie behavior, rate limits, audit and data handling are implemented as applicable;
4. transactions, uniqueness, foreign keys, locking, idempotency, retry, ordering, time, precision, immutable history and concurrency rules are enforced at appropriate layers;
5. unit, integration, contract, migration, architecture, authorization, tenant-isolation, negative, security, accessibility, performance and resilience tests required by the item pass;
6. static analysis, coding style, dependency validation/audit, schema validation and build checks pass at the approved thresholds;
7. logs and metrics are structured, useful and free from prohibited personal data or secrets;
8. migrations are forward-safe, repeatable and rollback/recovery-tested according to their classification;
9. actual commands, versions, results, limitations and modified files are recorded without claiming unexecuted checks;
10. traceability is updated from requirement to code, migration, test and evidence;
11. the change is reviewed by the required engineering and control owners; and
12. project state advances only after the evidence is accepted.

> Documentation-only output is a failure for implementation batches unless the batch explicitly names documentation as its sole deliverable.

## 5. Definition of Done — phase close

A phase closes only when:

- every planned batch is `DONE`, formally removed, or accepted through approved change control;
- all mapped requirements have accepted evidence and there are no unexplained orphan IDs;
- all phase-blocking decisions and risks are resolved, mitigated, or formally accepted by authorized owners;
- threat, abuse, privacy, minor-safety, accessibility, performance, resilience, recovery and operational gates applicable to the phase pass;
- complete regression and cross-module integration suites pass;
- migration rehearsal, backup and rollback evidence exists where data changed;
- release notes, operations/runbook changes and support implications are approved;
- no unresolved critical or high defect violates the release policy; and
- the close report records exact evidence and authorizes the next phase.

## 6. Evidence contract

Evidence must identify the command or procedure, environment, version/commit, timestamp, result, and retained artifact. A pass must be reproducible. A skipped, unavailable, flaky, or manually inferred check is reported as such and cannot be recorded as passed.

Minimum evidence categories are:

| Category | Typical evidence |
| --- | --- |
| Requirements | Requirement-to-code/test mapping and acceptance result |
| Quality | PHPUnit, static analysis, style and architecture output |
| Security | Threat/abuse tests, dependency audit and secure-configuration checks |
| Isolation | Positive and cross-Workspace negative integration tests |
| Data | Migration, constraints, data-quality and rollback/recovery tests |
| Accessibility | Automated plus manual keyboard, screen-reader, contrast, zoom, RTL and reduced-motion review |
| Resilience | retry/idempotency, failure injection, restore and continuity evidence |
| Operations | logs, metrics, health, alerts, runbook rehearsal and ownership |

## 7. State model

`PROPOSED → READY → IN_PROGRESS → IN_REVIEW → VERIFIED → DONE`

Permitted exception states are `BLOCKED`, `REJECTED`, and `SUPERSEDED`. A failed gate returns work to `IN_PROGRESS` or `BLOCKED`; it never advances through a documentation-only declaration. State changes must record actor, time, reason and evidence.

## 8. Change control

Any implementation discovery that requires a frozen semantic change invokes [baseline change control](../closeout/05-baseline-freeze-and-change-control.md). The work item is blocked only to the extent affected; unrelated safe work may proceed when dependency analysis proves separation.

## 9. Incomplete implementation rules

A coding batch is not complete when tests or other available required commands were not run; placeholder methods remain; authorization or tenant filters are omitted; migration constraints are missing; a controller/template executes SQL; a client calculation is trusted as authoritative; an official record is overwritten without its governed lifecycle; error handling exposes secrets; only documentation was produced; or the agent describes code without writing it. Any such condition leaves the item `IN_PROGRESS` or `BLOCKED` with truthful evidence.

## 10. Exact next action

Apply this policy to [QMDB-P1-B01](prompts/QMDB-P1-B01-core-php-repository-and-runtime-foundation.md), the only currently authorized executable batch.
