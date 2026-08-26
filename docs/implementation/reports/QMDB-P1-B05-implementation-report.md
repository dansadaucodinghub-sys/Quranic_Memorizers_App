# QMDB-P1-B05 Implementation Report

| Field | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Frozen Baseline | QMDB-P0-FRZ-001 |
| Approved Change Request | QMDB-CR-001 |
| Phase | P1 — Engineering and Repository Foundation |
| Batch | QMDB-P1-B05 — MySQL Connection and Transaction Foundation |
| Status | COMPLETE — accepted by QMDB-P1-CLOSE on 2026-08-26 |
| Execution Date | 2026-08-25 |
| Next Batch | QMDB-P1-B06 — NOT READY |

## Prerequisite Verification

- Repository root and dirty working tree were verified; all existing B03/B04 work was preserved.
- P1-B01 through P1-B04 remain formally incomplete because the only active PHP CLI is 8.2.12 while Composer requires PHP `^8.5`.
- Initial `composer quality` passed validation, audit and autoload generation, then stopped at the PHP platform guard when PHPCS started.
- The frozen manifest contains 82 governed files and revalidated with zero mismatches.
- `PDO`, `pdo_mysql`, JSON and Mbstring are installed. Docker CLI 29.6.1/Compose 5.2.0 are installed, but the Docker engine is unavailable.
- The only local server/client is MariaDB 10.4.32 on port 3306; it is an unapproved substitute and is intentionally rejected by the verifier.

## Prerequisite Corrections

- Updated obsolete B01–B03 architecture assertions to recognize only the approved B05 database boundary and new Composer extension requirements.
- Declared the database health dependency on `ReadinessController` explicitly after the first integration run detected restricted-resolver rejection.
- Excluded the MySQL directory from the general Integration suite so it executes once through the dedicated MySQL suite without PHPUnit duplicate-suite warnings.

## Files Created

- 36 production PHP files: `DatabaseFoundationModule`; five typed database-configuration files; ten shared connection/health/transaction contracts and values; and twenty MySQL connection, verification, health, exception, transaction, retry and sleep implementations.
- 17 test PHP files: five MySQL support fixtures, four unit-test files, six dedicated MySQL integration files, and two architecture/security files.
- `compose.mysql-test.yaml`, test-only and secret-injected.
- `docs/project/change-requests/QMDB-CR-001-asynchronous-progressive-interaction.md`.
- `docs/implementation/frontend-interaction-standard.md`.
- This implementation report.

## Files Updated

- Runtime/composition: `ApplicationFactory`, `ApplicationMetadata`, `HttpFoundationModule`, `ReadinessController`.
- Tooling/configuration: `.env.example`, `composer.json`, Composer-generated `composer.lock`, `phpunit.xml.dist`.
- Existing affected tests and architecture policies.
- `README.md`, `docs/project/open-decisions.md`, `docs/project/risk-register.md`, and `docs/project/project-state.md`.

The locked P0 decision register, phase roadmap, P1 backlog and quality-attribute parameter register were not modified because doing so would invalidate QMDB-P0-FRZ-001. The approved post-freeze direction and pending values are instead recorded in QMDB-CR-001 and the non-frozen controlled registers. This source-instruction conflict prevents a complete documentation acceptance result without an approved baseline-change procedure.

## Composer Changes and PHP Extensions

- Added runtime requirements `ext-pdo` and `ext-pdo_mysql`; no database library, ORM, query builder or migration package was added.
- Added `composer test:mysql` and the dedicated PHPUnit MySQL suite.
- Composer regenerated the lock metadata; it was not hand-edited.

## Configuration Variables

`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USERNAME`, `DB_PASSWORD`, `DB_TLS_MODE`, `DB_TLS_CA_FILE`, `DB_CONNECT_TIMEOUT_SECONDS`, `DB_DEADLOCK_MAX_ATTEMPTS`, `DB_DEADLOCK_BASE_DELAY_MS`, and `DB_DEADLOCK_MAX_DELAY_MS` are documented. The password remains a separately resolved `SecretValue` and is absent from `DatabaseConfiguration`, safe arrays, and DSNs.

## Connection Security and Session Invariants

- Native PDO uses exception mode, associative fetches, native prepares, non-stringified fetches, non-persistent connections, bounded connect timeout, and disabled multi-statements.
- Configuration rejects unsafe hosts/database names/control characters/root accounts and production-like disabled TLS. Verified TLS requires a readable CA and encrypted-session evidence; it does not claim guarantees beyond the PDO driver.
- Connections are lazy and shared within one provider instance. Initial creation configures only the current session and verifies MySQL (not MariaDB), InnoDB, UTC, `utf8mb4`, required strict SQL modes, non-root identity, usability and required TLS.
- Failures use stable generic project exceptions; public readiness never exposes PDO messages, credentials, host, database or username.

## Transaction Capabilities and Retry Behavior

- `TransactionManager` returns callback results, commits success, rolls back failures, preserves original non-retryable failures, and cleans state.
- Default isolation is approved `READ COMMITTED`; `REPEATABLE READ` and `SERIALIZABLE` are also typed options. Read-only and read-write modes are explicit.
- Nested calls join through deterministic `qmdb_sp_<depth>` savepoints; incompatible isolation/mode fails before callback execution.
- Only SQLSTATE `40001` and MySQL error 1213 are retryable. Retry is outermost-only, executes the whole callback again, is bounded by the configured maximum, and sleeps through injectable bounded exponential jitter. No final-attempt delay occurs.
- Retried callbacks must contain no irreversible external side effects; no transaction may span user interaction, modal display, upload, external call or SSE stream.

## Database Module and Health Integration

- Five foundation modules now compile: core, application, console, database and HTTP.
- `foundation.database` depends only on core and registers 14 explicit service definitions plus seven typed aliases.
- HTTP explicitly depends on database and injects only `DatabaseHealthCheck` into readiness. Controllers receive neither PDO nor a connection provider.
- Liveness remains MySQL-free. Readiness maps internal health to only `ready`/200 or `not_ready`/503 with `no-store`.

## AJAX and Modal Directive Registration

QMDB-CR-001 and the frontend standard record server-rendered progressive enhancement, native Fetch, controlled partial refresh, accessible bounded modals, nested-modal prohibition, SSE preference, server-authoritative validation/calculation, bounded per-request transactions, duplicate/stale-update protection requirements, and practical non-JavaScript fallbacks. P1-B05 adds no production frontend code.

## Tests Added

- B05 unit test methods: 20 definitions, 32 executed cases after data providers.
- MySQL integration: six files, nine mandatory cases; all skipped because dedicated `QMDB_TEST_DB_*` configuration for approved MySQL LTS is unavailable.
- Architecture: one file/five cases. Security: one file/seven cases.
- Repository-wide PHP 8.2 compatibility result: 378 tests, 11,718 assertions, nine MySQL skips.
- Suite detail: Unit 253/524; non-MySQL Integration 50/175; Architecture 66/11,019; MySQL 9/0 skipped.

## Commands Run and Results

| Command | Exit | Result |
| --- | ---: | --- |
| `php -v` | 0 | PHP 8.2.12; below approved 8.5 |
| `composer --version` | 0 | Composer 2.8.8 |
| `git --version` | 0 | Git 2.49.0.windows.1 |
| `mysql --version` | unavailable on PATH | XAMPP client located separately; MariaDB 10.4.32 |
| `docker --version`; `docker compose version` | 0 | CLI available |
| `docker info` | 1 | Docker Desktop Linux engine unavailable |
| `php -m`; `php --ri pdo_mysql` | 0 | PDO/pdo_mysql enabled; mysqlnd 8.2.12 |
| Initial/final normal `composer quality` | 1 | PHP 8.5 platform guard prevents locked tool subprocesses |
| `composer update --lock --ignore-platform-req=php --no-scripts` | 0 | Lock metadata generated by Composer; no dependency change/advisory |
| `composer validate --strict` | 0 | Valid |
| `composer audit --locked` | 1 then 0 | First final attempt timed out contacting Packagist; retry completed with no advisories |
| `composer dump-autoload --strict-psr --ignore-platform-req=php` | 0 | Strict PSR-4 generation passed for diagnostics |
| PHP syntax loop | 0 | Source/test/entry files passed |
| Direct PHPCS | 0 | 252 files passed after corrections |
| Direct PHPStan maximum level | 0 | No errors |
| Locked PHPUnit 13 | 1 | Requires PHP 8.4.1+ and cannot run here |
| Temporary PHPUnit 11.5.56 compatibility suite | 0 | 378 tests, 11,718 assertions, nine MySQL skips |
| Compatibility `--testsuite MySQL` | 0 with skips | Nine of nine skipped; not accepted as MySQL pass |
| Frozen-manifest checksum script | 0 | 82 entries, zero mismatches |
| `git diff --check` | 0 | Passed at final validation |
| Real PHP 8.2 built-in HTTP smoke | requests completed | Root, liveness and readiness each returned safe bootstrap 500 because the PHP 8.5 runtime guard executes before routing |

## MySQL Integration Environment and HTTP Verification

No acceptable MySQL test environment was available. The local listener is MariaDB and Docker’s engine is stopped. Therefore real connection/session/commit/rollback/savepoint/read-only/readiness tests and healthy/invalid-credential HTTP readiness are not accepted. Compatibility tests prove generic unavailable readiness (503), liveness independence (200), and unchanged root/about/HEAD/OPTIONS/404/405 behavior at object level. Real PHP 8.5 HTTP execution remains blocked by the runtime prerequisite.

## Security Controls and Architecture Boundaries

- Password and DSN disclosure controls, root rejection, TLS fail-closed policy, PDO hardening, strict session verification, deterministic savepoints, bounded narrow retry and minimal readiness are executable and statically tested.
- PDO is confined to the shared connection contract and MySQL infrastructure/tests. Application/domain code and controllers have no PDO, provider or MySQL-infrastructure dependency.
- No ORM, query builder, migration, business table, Redis, outbox, AJAX endpoint, JavaScript, modal or SSE implementation exists.
- The frozen baseline is unchanged: 82 entries, zero mismatches.

## Known Limitations and Open Risks

1. P1-B01 through P1-B04 remain incomplete under the authoritative project state, so B05 prerequisites are not satisfied.
2. PHP 8.5 and locked PHPUnit 13 acceptance are unavailable.
3. Approved MySQL LTS is unavailable; MariaDB is intentionally incompatible and the Docker engine is not running.
4. Real MySQL and real healthy/unavailable/invalid-credential HTTP matrices remain mandatory.
5. Production provider/HA/backup/TLS operations, final timeout/retry values, and frontend protocol details remain controlled open decisions.
6. Required edits to locked P0 planning/decision/parameter files conflict with frozen-integrity requirements and were not made.

## Next Batch

QMDB-P1-B06 is not ready. Provision PHP 8.5 and a disposable non-root MySQL LTS test service, run normal Composer installation/quality, all nine MySQL integration cases, and the complete real HTTP matrix. Resolve any failures and close P1 prerequisites in order before authorizing B06.

## Final acceptance addendum — 2026-08-26

The isolated non-root MySQL 8.4.11 environment and official PHP 8.5 runtime were provisioned without modifying XAMPP.
Connection/session validation, readiness behavior, transactions, deadlock policy, and nested savepoints passed the real
MySQL suite. See [the P1 verification report](../../closeout/p1/02-P1-executable-verification-report.md). Batch status is
`COMPLETE`; the limitations above describe only the earlier attempt.
