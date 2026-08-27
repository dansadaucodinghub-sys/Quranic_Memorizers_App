# QMDB Project State

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Source Baseline | QMDB-BL-001 |
| Product Freeze | QMDB-P0-FRZ-001 |
| Engineering Freeze | QMDB-P1-FRZ-001 |
| Document Version | 3.5.0 |
| Last Updated | 2026-08-27 |
| Status | P0 COMPLETE; P1 COMPLETE; P2 RECOVERY SCOPE COMPLETE |
| Current Phase | P2 — Identity, Security, and Tenant Isolation |
| Current Batch | QMDB-P2-B05 — MFA, Passkeys, Recovery Codes, and Step-Up Authentication |
| Batch Status | QMDB-P2-B05 COMPLETE |
| Implementation Readiness | RECOVERY_SCOPE_COMPLETE — B06 NOT AUTHORIZED |
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
| Last Fully Completed Batch | QMDB-P2-B05 |
| Current Executable Batch | NONE — B06 requires new authorization |
| Sequence Rule | B01 → B02 → B03 → B04 → B05; no batch advances before its mandatory gates pass |
| P2-B06 Status | BLOCKED — outside recovery scope |

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
| Corrections made | Stale metadata assertions; frozen-document write removed; recovery alphabet; PDO placeholder; concurrency winners; server-authoritative grant UI; stale WebAuthn ceremony retry; OpenSSL fixture configuration; Gitleaks prose false positive; Trivy timeout handling |
| Remaining environment limitations | Hosted CI; production HTTPS RP/origin and key custody; physical authenticator/browser/AT matrix; production provider/scheduler; assisted/lost-factor process; Docker/WSL host repair |

## Deferred evidence that does not reopen P1

| Evidence | Classification | Required point |
| --- | --- | --- |
| Hosted GitHub Actions execution | Operational confirmation; workflows are statically and locally verified | Before merge or published release |
| Manual keyboard, screen-reader, zoom, forced-colour and browser matrix | Release accessibility evidence; automated foundation gates pass | Before an affected production UI release |
| Linux PCNTL signal-delivery rehearsal | Deployment-platform evidence; bounded once mode and fail-closed policy pass | Before enabling continuous production workers |

These items are not represented as executed. They remain fail-closed release/deployment gates in their owning
environments and do not conceal an incomplete P1 source contract.

## Historical P2 entry blockers and recovery disposition

1. `OD-051` — resolved for recovery execution with a conservative ASCII email contract and executable vectors.
2. `OD-052` — resolved for recovery execution with strict canonical E.164 input and executable vectors.
3. Hosted CI/release execution remains required before merge or publication; it does not block local recovery work.

## State block

Source Baseline: QMDB-BL-001

Frozen Baseline: QMDB-P0-FRZ-001

Engineering Freeze: QMDB-P1-FRZ-001

Recovery Run: QMDB-RECOVERY-RUN-001

Current Phase: P2 — Identity, Security, and Tenant Isolation

Last Completed Batch: QMDB-P2-B05

Current Executable Batch: NONE

P1 Status: COMPLETE

P2 Status: IN PROGRESS

Recovery Status: COMPLETE

Batch Status: QMDB-P2-B05 COMPLETE

Implementation Status: RECOVERY SCOPE COMPLETE — NEXT BATCH NOT AUTHORIZED

P2-B06 Status: BLOCKED
