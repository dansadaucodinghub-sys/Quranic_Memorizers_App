# QMDB Project State

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Source Baseline | QMDB-BL-001 |
| Product Freeze | QMDB-P0-FRZ-001 |
| Approved Change | QMDB-CR-001 — Asynchronous Progressive Interaction and Modal UX Standard |
| Engineering Freeze | QMDB-P1-FRZ-001 |
| Document Version | 3.8.0 |
| Last Updated | 2026-08-29 |
| Status | P0 COMPLETE; P1 COMPLETE; P2-B01 through P2-B08 COMPLETE |
| Current Phase | P2 — Identity, Security, and Tenant Isolation |
| Current Batch | QMDB-P2-B08 — Temporary Privileges, Support Access, and Break-Glass Controls |
| Batch Status | QMDB-P2-B08 COMPLETE |
| Implementation Readiness | READY FOR NEXT BATCH — QMDB-P2-B09 is next, not implemented here |
| P2 Status | IN PROGRESS |

## Authoritative outcome

P1 is complete. All ten implementation batches and the closeout batch satisfy their engineering acceptance criteria.
`QMDB-P1-FRZ-001` governs the resulting repository foundation. The original product and requirements freeze
`QMDB-P0-FRZ-001` remains intact.

`QMDB-RECOVERY-RUN-001` is an explicit project-owner authorization that supersedes the prior P2 execution block. It
requires toolchain recovery, repository-truth verification, conservative resolution of `OD-051`/`OD-052`, and strict
sequential execution of P2-B01 through P2-B05. P1 remains frozen; P2 changes use its controlled extension points.

## Recovery execution ledger

| Field | Current value |
| --- | --- |
| Recovery Run | QMDB-RECOVERY-RUN-001 |
| Recovery Status | COMPLETE |
| Last Fully Completed Batch | QMDB-P2-B08 |
| Next Batch | QMDB-P2-B09 — Security Events, Audit Integrity, and Account State Operations |
| Sequence Rule | B01 → B02 → B03 → B04 → B05 → B06 → B07 → B08; B09 is not implemented by B08 |
| P2-B08 Status | COMPLETE |

## P1 batch ledger

| Batch | Title | Status | Primary evidence |
| --- | --- | --- | --- |
| QMDB-P1-B01 | Core PHP Repository and Runtime Foundation | DONE | PHP 8.5 runtime, guarded entry points, Composer and full quality gates |
| QMDB-P1-B02 | Configuration, Environment, and Secrets Abstractions | DONE | typed configuration, secret redaction, UTC and identifier tests |
| QMDB-P1-B03 | HTTP Kernel, Routing, Request, Response, and Middleware | DONE | PSR-7/15 tests and real socket HTTP matrix |
| QMDB-P1-B04 | Dependency Injection, Module Registry, and Application Services | DONE | compilation, graph, messaging and architecture tests |
| QMDB-P1-B05 | MySQL Connection and Transaction Foundation | DONE | MySQL 8.4.11 connection/session/transaction/readiness tests |
| QMDB-P1-B06 | Migration, Seeder, and Schema Ledger Foundation | DONE | install, plan, migrate, seed, verify, rollback and reapply rehearsal |
| QMDB-P1-B07 | Error, Logging, Correlation, and Secure HTTP Foundation | DONE | redaction, safe failure, correlation and security-header tests |
| QMDB-P1-B08 | CLI, Scheduler, Worker, and Background Execution Foundation | DONE | bounded worker/scheduler tests and MySQL lease-ownership tests |
| QMDB-P1-B09 | View, Localization, RTL, Theme, and Progressive Interaction Foundation | DONE | Node 24 frontend tests plus English/Arabic/fragment HTTP checks |
| QMDB-P1-B10 | CI Quality Gates and Engineering Verification | DONE | repository/CI policy, scanners, SBOM, licences and artifact verification |
| QMDB-P1-CLOSE | Engineering Foundation Verification | DONE | consolidated evidence and engineering-freeze verification |

## Acceptance environment

| Component | Accepted environment/result |
| --- | --- |
| PHP | Official Windows PHP 8.5.10 NTS x64; JSON, Mbstring, PDO MySQL and SQLite test drivers loaded |
| Composer | 2.8.8; locked install, strict validation, audit and optimized PSR-4 generation pass |
| Node.js/npm | Node.js 24.19.0 and npm 11.6.2; engine contract satisfied |
| Database | Isolated MySQL Community Server 8.4.11 on a dedicated local port; XAMPP MariaDB unchanged |
| Runtime identity | Separate non-root runtime and schema identities with database-scoped grants |
| Security tools | actionlint 1.7.12, Gitleaks 8.30.1, ShellCheck 0.11.0 and Trivy 0.72.0 |

## Final verification ledger

| Gate | Outcome |
| --- | --- |
| Frozen P0 baseline | PASS — 82 governed entries; 177 verifier checks |
| Composer validation/audit/platform | PASS |
| PHPCS and maximum-level PHPStan | PASS |
| Locked PHPUnit suite | PASS — 705 tests, 35,263 assertions, including all 13 MySQL cases without environment skips |
| Frontend syntax/tests/audit | PASS — 17 JavaScript files, 23 tests, zero npm vulnerabilities |
| MySQL schema lifecycle | PASS — install, verify, plan, migrate, seed, rollback and reapply |
| Database boundary | PASS — seven InnoDB foundation tables and no domain/business tables |
| HTTP socket matrix | PASS — English, Arabic RTL, fragment, API, liveness, readiness, 404 and 405 |
| CLI/background matrix | PASS — help, about, schedule, worker-once and invalid-option behavior |
| Repository/workflow/link/lock policies | PASS |
| Actionlint/ShellCheck | PASS |
| Gitleaks history and working tree | PASS — no leaks |
| Trivy filesystem scan | PASS — zero HIGH/CRITICAL vulnerabilities, secrets or misconfigurations |
| CycloneDX SBOM | PASS — 15 runtime components; 99 validation checks |
| Runtime licences | PASS — zero unknown or review-required runtime licences |
| Release artifact | PASS — deterministic build, extracted-file lint, CLI/HTTP and MySQL readiness verification |
| Engineering freeze | PASS — 656 governed files and 3,969 approval checks; source revision `dc3574b50ebdb3282025f243efa3f6c66921e14c` |

Exact final test counts, artifact hashes and source revisions are retained in the P1 closeout evidence set and generated
build reports.

## P2 recovery batch ledger

| Batch | Status | Evidence |
| --- | --- | --- |
| QMDB-P2-B01 | COMPLETE | Identity/tenancy source, four migrations, 731-test quality suite, 16-test MySQL suite, scanners, release and 4,271 freeze checks pass |
| QMDB-P2-B02 | COMPLETE | Registration, verification, password authentication, two migrations, parallel concurrency, frontend, scanner, release and freeze gates pass |
| QMDB-P2-B03 | COMPLETE | Secure server-side sessions/devices, login/logout, rotation, expiry, concurrency, inventory, revocation, frontend, scanner, release and freeze gates pass |
| QMDB-P2-B04 | COMPLETE | Account recovery/reset, session invalidation, durable security notifications, scheduler, concurrency, frontend, scanner, release and freeze gates pass |
| QMDB-P2-B05 | COMPLETE | MFA, TOTP, recovery codes, passkeys, passwordless login, assurance, action-scoped step-up, concurrency, frontend, scanner, release and freeze gates pass |
| QMDB-P2-B06 | COMPLETE | Deny-by-default platform/workspace authorization, explicit seeded catalog, assurance-aware decisions, atomic step-up-protected administration, delegation and concurrency controls pass |
| QMDB-P2-B07 | COMPLETE | Session-authoritative Tenant Context, explicit workspace switching/clearing, composite membership integrity, tenant-scoped boundaries, stale-version and cross-session isolation controls pass |
| QMDB-P2-B08 | COMPLETE | Time-bounded temporary privileges, dual-approved support access, atomic break-glass, session-bound authorization, post-use review, expiry/revocation and scheduler controls pass |

## QMDB-P2-B04 delivery and verification ledger

| Measure | Actual result |
| --- | --- |
| Production PHP files added / updated | 74 / 23 |
| Modules / migrations / tables added | 2 / 3 / 4 |
| Routes added | 6 |
| Pages / fragments / email templates | 4 / 4 / 4 |
| Translation keys added | 34 English + 34 Arabic |
| JavaScript modules updated | 1 shared policy module |
| Production scheduled tasks | 1 (`identity.security_notifications.deliver`) |
| B04 unit tests | 13 |
| B04 MySQL integration tests | 5; includes a two-process reset race |
| B04 HTTP/scheduler scenarios | Generic EN/AR/fallback/progressive recovery; atomic reset; scheduled delivery and no-duplicate rerun |
| Frontend tests | 41 total; 2 B04-specific additions |
| Architecture tests | 6 B04-specific assertions groups plus updated foundation allowlists |
| Validation failures resolved | Hydration typing, reset idempotency race, readiness ownership, scheduler dependency resolution, Sodium runtime, Composer process ceiling, portable nested-tool resolution, scanner cache boundary |
| Remaining environment limitations | Hosted CI, manual browser/AT matrix, production scheduler/provider configuration, and Docker/WSL host repair remain external gates |

## QMDB-P2-B05 delivery and verification ledger

| Measure | Actual result |
| --- | --- |
| Production PHP files added / updated | 105 / 28 |
| Production JavaScript files added / updated | 5 / 2 |
| Modules / migrations / tables added | 1 / 4 / 9 |
| Routes / pages / fragments added | 30 / 11 / 10 |
| Translation entries added | 75 English + 75 Arabic |
| Security-notification types added / scheduled tasks added | 9 / 0; existing B04 delivery task reused |
| Unit suite | PASS — 572 tests, 1,303 assertions; 19 B05-specific test methods |
| Non-MySQL integration suite | PASS — 91 tests, 420 assertions; 6 B05 HTTP methods |
| Architecture suite | PASS — 107 tests, 51,329 assertions |
| MySQL suite | PASS — 51 tests, 977 assertions; 13 B05 MySQL/WebAuthn methods and independent-process races |
| Frontend suite | PASS — 28 files syntax-valid, 48 tests, zero npm vulnerabilities |
| Migration lifecycle | PASS — 16 applied; B05 rollback/reapply, idempotent rerun and schema verification |
| Security and supply chain | PASS — Gitleaks, Trivy, Composer audit/platform, 269 SBOM checks, 52 runtime licences with zero unknown/review |
| Corrections made | Stale metadata assertions; frozen-document write removed; recovery alphabet; PDO placeholder; concurrency winners; server-authoritative grant UI; stale WebAuthn ceremony retry; OpenSSL fixture configuration; Gitleaks prose false positive; Trivy timeout handling; Composer CI process ceiling |
| Remaining environment limitations | Hosted CI; production HTTPS RP/origin and key custody; physical authenticator/browser/AT matrix; production provider/scheduler; assisted/lost-factor process; Docker/WSL host repair |

## QMDB-P2-B06 delivery and verification ledger

| Measure | Actual result |
| --- | --- |
| Production PHP files added / updated | 75 / 15 |
| Modules / migrations / seeds / tables added | 1 / 3 / 1 / 5 |
| Permissions / roles / role-permission mappings seeded | 10 / 7 / 27; zero role assignments |
| Step-up actions / security-notification types added | 4 / 4; existing B04 delivery task reused |
| B06 unit test methods | 12 across catalog, decision, delegation and readiness contracts |
| B06 MySQL integration test methods | 11 across catalog, constraints, decisions, administration and concurrency |
| Complete PHP suite | PASS — 901 tests, 57,778 assertions |
| Complete MySQL suite | PASS — 62 tests, 1,138 assertions on MySQL 8.4.11 |
| Frontend suite | PASS — 28 files syntax-valid, 48 tests, zero npm vulnerabilities |
| Seed tests | Exact 10/7/27/0/0 catalog, checksum, fail-closed drift and idempotent no-op rerun |
| Constraint tests | Duplicate code/ID, invalid scope, cross-scope mapping, cross-workspace assignment and assignment uniqueness |
| Authorization decision tests | Unknown, inactive, wrong-scope, cross-workspace and insufficient-assurance decisions deny |
| Delegation tests | Permission-subset validation plus locked concurrent authority-revocation recheck |
| Concurrency tests | Duplicate platform/workspace assignment, protected administrator/owner revocation, single-use step-up and delegation revocation race |
| Architecture/security tests | 7 dedicated architecture methods plus safe generic-403, metadata, notification and boundary regressions |
| Validation failures resolved | Catalog UUID checksum encoding; readiness schema ownership; invalid structured event names; inherited fixture foreign keys; stale readiness expectation; P-256 coordinate padding; repeatable-read delegation recheck; post-MySQL CI schema restoration |
| Repository / workflow / links / lockfiles | PASS — 2,719 / 47 / 1,004 / 15 checks; P0 freeze 177 checks |
| Security and supply chain | PASS — Gitleaks, Trivy, 269 SBOM checks, 52 runtime licences with zero unknown/review; SBOM SHA-256 `1c970d4b72320c2f3629ab788479c71f3708bb3c598095f515f72db39cdd1054` |
| Release verification | PASS — 2,890 files, 2,665 PHP lints, readiness 200, archive SHA-256 `ad65401fdf41491932c2311a4366b28e856aabfd3f34321c4f2909639a550aba`, manifest SHA-256 `53b88a1737aa6793d64acc684ddae251caa62022c8ce8726175e66f8e71a1fb8` |
| Local CI | PASS — direct runner 31/31 and independent `composer ci` 31/31 |
| Engineering freeze | PASS — 1,216 governed files, 7,333 checks, source revision `2ec3e82b7a01b1a08c45be404a144ec7d56706ef` |
| Deferred evidence items | 9 inherited non-blocking environment/operations items remain explicitly unexecuted |
| Remaining environment limitations | Hosted CI; physical authenticator/browser/AT evidence; production WebAuthn/key/scheduler/provider configuration; assisted-factor policy; Docker/WSL and Linux-only evidence; platform-administrator bootstrap |

## QMDB-P2-B07 delivery and verification ledger

| Measure | Actual result |
| --- | --- |
| Module / migration / new table | `tenancy.context` / 1 / 0; four columns extend `user_sessions` |
| Composite integrity | Session workspace/account/membership FK to exact membership candidate key; all-or-none and positive-version checks |
| Routes | `GET /account/workspaces`, `POST /account/workspaces/switch`, `POST /account/workspaces/clear`, `GET /workspace` |
| Authority | Server session only; no workspace cookie/header/browser storage/default selection |
| Isolation | Account-scoped inventory, per-session selection, stale optimistic version, inactive-state clearing, generic unavailable response |
| Tenant-aware foundations | Repository marker, account-bound job resolver, tenant cache-key factory, future export marker; no production job/cache/export engine |
| Prerequisite correction | Standalone MySQL test command now restores schema, seed, authorization, Tenant Context, and schema verification |
| Deferred evidence | Hosted CI and manual browser/assistive-technology evidence remain external release gates; no mandatory local source gate is waived |
| Production PHP files added / updated | 58 / 34, including 53 new and 27 updated files under `src/` |
| Views / JavaScript module | 4 server-rendered views / 1 tenant-context controller |
| B07-specific test methods | 31 direct methods across unit, architecture, frontend, HTTP and MySQL suites, plus inherited regressions |
| Complete PHP suite | PASS — 932 tests, 60,501 assertions |
| Complete MySQL suite | PASS — 76 tests, 1,352 assertions on MySQL 8.4.11; no skips; canonical 37-table pre/post restoration |
| Frontend suite | PASS — 30 files syntax-valid, 51 tests, zero npm vulnerabilities on Node 24.19.0 |
| Repository / workflows / links / lockfiles | PASS — 2,863 / 47 / 1,006 / 15 checks; P0 freeze 177 checks |
| Static quality | PASS — PHPCS over 1,118 governed files and maximum-level PHPStan |
| Security and supply chain | PASS — Gitleaks over 42 commits, Trivy, 269 SBOM checks, 52 runtime licences with zero unknown/review |
| Release verification | PASS — 2,948 files verified, 2,722 PHP lints, readiness 200, archive SHA-256 `2175e83d67202c7419200eb5fdc91440eb1e02b26847cdbb68cb451ed42afe48` |
| Local CI | PASS — direct runner 33/33 and independent `composer ci` 33/33 |
| Engineering freeze | PASS — 1,287 governed files, 7,759 checks, source revision `071bd63d6ad5fbea66611cfa0a9946bef2fb40f8` |
| Verified release source | Clean revision `1b652fbd17214fead17474957b5cc68cd5c56257`; artifact marked release-eligible |

## Deferred evidence that does not reopen P1

| Evidence | Classification | Required point |
| --- | --- | --- |
| Hosted GitHub Actions execution | Operational confirmation; workflows are statically and locally verified | Before merge or published release |
| Physical authenticator and supported-browser testing | High-assurance release evidence; deterministic fixtures pass | Before affected production authentication release |
| Manual keyboard, screen-reader, zoom and forced-colour testing | Release accessibility evidence; automated foundation gates pass | Before an affected production UI release |
| Production HTTPS WebAuthn RP ID and origin approval | Deployment security configuration | Before production passkey activation |
| Managed MFA encryption-key custody and rotation | Production secret-management evidence | Before production MFA activation |
| Production scheduler and notification-provider operations | Deployment delivery evidence | Before production notification activation |
| Assisted or lost-all-factor recovery policy | Security/product governance | Before enforced MFA rollout |
| Docker/WSL host repair | Optional host-environment recovery | Before relying on that host path |
| Linux PCNTL signal-delivery rehearsal | Deployment-platform evidence; bounded once mode and fail-closed policy pass | Before enabling continuous production workers |

These items are not represented as executed. They remain fail-closed release/deployment gates in their owning
environments and do not conceal an incomplete P1 source contract.

## Historical P2 entry blockers and recovery disposition

1. `OD-051` — resolved for recovery execution with a conservative ASCII email contract and executable vectors.
2. `OD-052` — resolved for recovery execution with strict canonical E.164 input and executable vectors.
3. Hosted CI/release execution remains required before merge or publication; it does not block local recovery work.

## State block

Project: Qur’an Memorizer DB

Source Product Baseline: QMDB-BL-001

Frozen Product Baseline: QMDB-P0-FRZ-001

Approved Post-Freeze Change: QMDB-CR-001 — Asynchronous Progressive Interaction and Modal UX Standard

Frozen Engineering Baseline: QMDB-P1-FRZ-001

Recovery Run: QMDB-RECOVERY-RUN-001 — COMPLETE

Completed Phase: P1 — Engineering and Repository Foundation

Current Phase: P2 — Identity, Security, and Tenant Isolation

Completed P2 Batches:

- QMDB-P2-B01
- QMDB-P2-B02
- QMDB-P2-B03
- QMDB-P2-B04
- QMDB-P2-B05
- QMDB-P2-B06
- QMDB-P2-B07
- QMDB-P2-B08

Completed Batch: QMDB-P2-B08 — Temporary Privileges, Support Access, and Break-Glass Controls

Next Batch: QMDB-P2-B09 — Security Events, Audit Integrity, and Account State Operations

P1 Status: COMPLETE

P2 Status: IN PROGRESS

Batch Status: COMPLETE

Implementation Status: READY FOR NEXT BATCH
