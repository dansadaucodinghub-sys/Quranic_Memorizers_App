# QMDB Project State

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Source Baseline | QMDB-BL-001 |
| Frozen Baseline | QMDB-P0-FRZ-001 |
| Document Version | 2.2.0 |
| Status | P0 COMPLETE; P1 BLOCKED; QMDB-P1-B01 and QMDB-P1-B02 INCOMPLETE |
| Current Phase | P1 — Engineering and Repository Foundation |
| Current Batch | QMDB-P1-B02 — Configuration, Environment, and Secrets Abstractions |
| Last Updated | 2026-08-24 |
| Document Owner Role | Product, Architecture and Engineering Governance |
| Approval Status | B02 executable source and diagnostics are present; completion and B03 readiness are blocked pending B01 closure and mandatory PHP 8.5 validation evidence |

## Purpose

Record the repository-visible phase and batch state, frozen counts, executable implementation evidence, controlled risks, validation blockers and exact next action without treating partial or diagnostic evidence as completion.

## Scope

QMDB-P0-B01 through QMDB-P0-CLOSE remain complete as documentation-as-code under the frozen baseline. QMDB-P1-B01 created the Core PHP repository/runtime foundation but remains incomplete. QMDB-P1-B02 now contains the typed configuration, controlled environment loading, secret-value protection, UTC clock, secure runtime-identifier and bootstrap-integration source. B02 is also incomplete because its prerequisite and mandatory executable gates have not passed on the approved runtime. No MySQL, session, authentication, Redis, router/middleware, cloud secrets provider or domain module has been implemented.

## Authoritative transition

| Field | Current value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Source Baseline | QMDB-BL-001 |
| Frozen Baseline | QMDB-P0-FRZ-001 |
| Completed Phase | P0 — Product Constitution and System Requirements |
| Completed Batches | QMDB-P0-B01; QMDB-P0-B02; QMDB-P0-B03; QMDB-P0-B04; QMDB-P0-CLOSE |
| Phase P0 Status | COMPLETE |
| Current Phase | P1 — Engineering and Repository Foundation |
| Current Batch | QMDB-P1-B02 — Configuration, Environment, and Secrets Abstractions |
| Phase P1 Status | BLOCKED |
| Batch Status | INCOMPLETE |
| Implementation Status | BLOCKED |
| Prerequisite Batch | QMDB-P1-B01 — INCOMPLETE |
| Next Batch | QMDB-P1-B03 — NOT READY / NOT AUTHORIZED |
| Next Action | Resolve the listed QMDB-P1-B02 blockers |
| Transition Date | 2026-08-24 |
| Approval Basis | Executable source and diagnostics exist, but the frozen Definition of Done prohibits completion without conforming runtime, PHPUnit, real-smoke, Git and integrity evidence |

## Controlled baseline counts

| Measure | Count |
| --- | ---: |
| Repository documentation files under `docs/` | 92 |
| Markdown files under `docs/` | 89 |
| YAML files under `docs/` | 3 |
| Frozen baseline files | 82 |
| P0 requirements | 263 |
| P0-B01 baseline requirements | 68 |
| P0-B02 functional requirements | 113 |
| P0-B03 non-functional requirements | 50 |
| P0-B04 data requirements | 32 |
| Use cases | 65 |
| User journeys | 24 |
| State machines | 29 |
| Acceptance scenarios | 111 |
| Threats | 46 |
| Controls | 30 |
| Frozen P0 risks | 63 |
| Current risks | 65 |
| Logical tables | 250 |
| Historical decision records | 70 |
| Resolved decisions | 5 |
| Open decisions | 65 |
| Deferred decisions | 65 |

## QMDB-P1-B02 implementation counts

| Measure | Count/status |
| --- | ---: |
| Production PHP files added | 21 |
| Production PHP files updated | 6 |
| Total production PHP files | 33 |
| Test PHP files added | 13 |
| Unit tests defined | 75 |
| Integration tests defined | 36 |
| Architecture tests defined | 28 |
| Total tests defined | 139 |
| Runtime dependencies added | 1 direct; 5 transitive |
| Configuration variables | 3 |
| Configuration violation codes | 7 |
| B02 security-focused tests | 98 defined B02 cases |
| Validation findings resolved | 10 |
| Remaining environment limitations | 2 |
| Executable domain tables/migrations | 0 |
| Domain modules implemented | 0 |

No PHPUnit assertion count is recorded because PHPUnit did not execute.

## Application foundation state

- Composer requires PHP `^8.5`, JSON, Mbstring and `vlucas/phpdotenv` `^5.6`; the real lock resolves phpdotenv v5.6.4.
- Immutable typed configuration supports local, test, staging and production, requires `APP_ENV`, defaults debug to disabled and enforces UTC.
- Process environment values take precedence. Local-file loading is confined to local/test use and is prohibited for production-like execution.
- Native environment access is confined to `DotenvEnvironmentLoader`; source does not mutate the process environment, and the protected environment-variable collection rejects serialization.
- Configuration violations use seven stable, value-free codes and aggregate safely.
- Secret names are conservative immutable values; secret values require explicit reveal, redact debug/JSON output and prohibit serialization.
- The environment-backed provider is a bootstrap adapter only; no cloud provider or production KMS is selected.
- `SystemClock` returns UTC `DateTimeImmutable`. Runtime identifiers use 16 secure random bytes encoded as 32 lowercase hexadecimal characters and are not domain public identifiers.
- The application composition root supplies typed configuration to safe CLI and HTTP bootstrap paths without becoming a service locator.
- CLI output includes only safe configuration information. HTTP output excludes environment, debug state, timezone, configuration source, PHP version, paths and secret data.
- No framework, database, Redis, router, middleware, session, authentication, worker, domain module or provider-specific integration exists.

## Validation state

| Gate | Result |
| --- | --- |
| P0 frozen baseline preflight | PASS — 82 entries, zero mismatches |
| Composer dependency update | PASS — phpdotenv v5.6.4 and lock updated by Composer |
| Composer validation | PASS |
| Dependency advisory substep | PASS during combined quality; standalone refresh intermittently timed out |
| Strict PSR-4 generation | PASS — 1,894 classes |
| PHP syntax diagnostic | PASS — 51 PHP files on available PHP 8.2.12 |
| PSR-12 diagnostic | PASS — 51/51 files after resolving four findings; non-conforming runtime diagnostic only |
| PHPStan maximum-level diagnostic | PASS after resolving six findings; non-conforming runtime diagnostic only |
| Targeted B02 object smoke | PASS with injected deterministic PHP 8.5 evaluator inputs; diagnostic only |
| Normal Composer install | BLOCKED — PHP 8.2.12 does not satisfy root `^8.5` or locked test tools |
| PHPUnit unit suite | BLOCKED before execution |
| PHPUnit integration suite | BLOCKED before execution |
| PHPUnit architecture suite | BLOCKED before execution |
| PHPUnit security coverage | BLOCKED before execution |
| Combined Composer quality gate | BLOCKED — one run reached PHP platform enforcement before PHPCS; the final retry stopped earlier on advisory-service DNS timeout |
| Successful CLI `app:about` smoke | BLOCKED; unsupported-runtime failure path is safe |
| CLI configuration-failure smoke | BLOCKED; runtime fails before configuration evaluation |
| Real HTTP 200 server smoke | BLOCKED; unsupported-runtime generic 500 path and cleanup verified |
| Real HTTP configuration-failure smoke | BLOCKED; runtime fails before configuration evaluation |
| Dotenv precedence and production prohibition | PASS in targeted isolated object smoke; locked tests not executed |
| Git status/diff/tracked-secret validation | PASS — B02 commit is clean; only `.env.example` is tracked among dotenv paths |
| QMDB-P1-B01 completion | INCOMPLETE |
| QMDB-P1-B02 completion | INCOMPLETE |

No test or gate blocked by PHP 8.5 or Git availability is represented as passed. Diagnostic PHPCS, PHPStan and targeted object results are not substituted for the mandatory conforming run.

## Decision state

- Frozen architecture decisions and the frozen decision register were not modified.
- B02 implements the prompt-authorized technical conventions: phpdotenv for local parsing, process precedence, production-like dotenv prohibition, required `APP_ENV`, UTC enforcement, debug prohibition in staging/production, explicit reveal-only secret values and 128-bit runtime identifiers.
- Cloud secrets provider, production KMS, key custody, MySQL credentials, session secret strategy, provider credentials and final domain public-identifier strategy remain open.

## Risk state

The 63 P0 risks remain open. QMDB-RSK-064 remains open because the active PHP runtime is below ADR-003 and now blocks conforming evidence for B01 and B02. QMDB-RSK-065 records that B02 security controls have source and targeted-smoke evidence but not their mandatory locked PHPUnit evidence. Neither risk is accepted, closed or falsely marked mitigated.

## Baseline integrity

The [freeze manifest](../closeout/qmdb-p0-baseline-freeze.yaml) continues to govern 82 frozen paths with lowercase SHA-256 values. B02 changes only executable, test, report and excluded dynamic governance files. Any semantic frozen-file change still requires approved change control and checksum regeneration.

## Implementation evidence

1. [QMDB-P1-B02 implementation report](../implementation/reports/QMDB-P1-B02-implementation-report.md)
2. [QMDB-P1-B01 implementation report](../implementation/reports/QMDB-P1-B01-implementation-report.md)
3. Root `composer.json`, `composer.lock`, `.env.example` and `README.md`
4. `src/Shared/Configuration/`, `src/Shared/Security/Secrets/`, `src/Shared/Time/` and `src/Shared/Identifier/`
5. `src/Bootstrap/ApplicationFactory.php`, `bin/console` and `public/index.php`
6. Unit, integration and architecture suites under `tests/`
7. [Risk register](risk-register.md)

## Blockers

1. The only active PHP CLI is 8.2.12; the frozen baseline requires PHP 8.5.
2. Packagist advisory refresh is intermittent, although a combined audit substep reported no advisory.

The Docker client is present but its Linux engine is unavailable as an alternate PHP 8.5 validation path; this is not treated as a separate mandatory technology requirement.

## Exact next action

Provision or select an approved PHP 8.5 CLI with JSON and Mbstring, then rerun normal Composer install, every quality command, all PHPUnit suites, successful and secure-failure CLI/HTTP smokes, Git secret/diff checks and the frozen-manifest check. Repair any executable defect found. Only after B01 and B02 pass every mandatory gate may QMDB-P1-B03 be marked ready.

Batch Status:
INCOMPLETE

Implementation Status:
BLOCKED

Next Action:
Resolve the listed QMDB-P1-B02 blockers
