# QMDB Project State

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Source Baseline | QMDB-BL-001 |
| Product Freeze | QMDB-P0-FRZ-001 |
| Engineering Freeze | QMDB-P1-FRZ-001 |
| Document Version | 3.0.0 |
| Last Updated | 2026-08-26 |
| Status | P0 COMPLETE; P1 COMPLETE; P2 BLOCKED |
| Current Phase | P1 — Engineering and Repository Foundation |
| Current Batch | QMDB-P1-CLOSE — Engineering Foundation Verification |
| Batch Status | DONE |
| Implementation Readiness | P1_COMPLETE |
| P2 Status | BLOCKED_BY_OD_051_OD_052 |

## Authoritative outcome

P1 is complete. All ten implementation batches and the closeout batch satisfy their engineering acceptance criteria.
`QMDB-P1-FRZ-001` governs the resulting repository foundation. The original product and requirements freeze
`QMDB-P0-FRZ-001` remains intact.

P2 is not authorized by this closeout. Its first batch remains blocked by the unresolved email- and phone-normalization
contracts recorded as `OD-051` and `OD-052`. Those decisions belong to identity, security and privacy governance and
were not invented during P1.

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
| Locked PHPUnit suite | PASS — 704 tests, 35,186 assertions, including all 13 MySQL cases without environment skips |
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
| Engineering freeze | PASS — 655 governed files and 3,963 checks; source revision `fece9bd4e4903039fa157487cc975cc963974351` |

Exact final test counts, artifact hashes and source revisions are retained in the P1 closeout evidence set and generated
build reports.

## Deferred evidence that does not reopen P1

| Evidence | Classification | Required point |
| --- | --- | --- |
| Hosted GitHub Actions execution | Operational confirmation; workflows are statically and locally verified | Before merge or published release |
| Manual keyboard, screen-reader, zoom, forced-colour and browser matrix | Release accessibility evidence; automated foundation gates pass | Before an affected production UI release |
| Linux PCNTL signal-delivery rehearsal | Deployment-platform evidence; bounded once mode and fail-closed policy pass | Before enabling continuous production workers |

These items are not represented as executed. They remain fail-closed release/deployment gates in their owning
environments and do not conceal an incomplete P1 source contract.

## P2 entry blockers

1. `OD-051` — approve the canonical email-normalization and test-vector contract.
2. `OD-052` — approve the canonical phone-number normalization and test-vector contract.
3. Rerun the clean hosted CI/release workflow from the controlled revision before merge or release publication.

## State block

Source Baseline: QMDB-BL-001

Frozen Baseline: QMDB-P0-FRZ-001

Engineering Freeze: QMDB-P1-FRZ-001

Current Phase: P1 — Engineering and Repository Foundation

Current Batch: QMDB-P1-CLOSE

P1 Status: COMPLETE

Batch Status: DONE

Implementation Readiness: P1_COMPLETE

P2 Status: BLOCKED_BY_OD_051_OD_052
