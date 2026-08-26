# QMDB-P1-B06 Implementation Report

| Field | Actual result |
| --- | --- |
| Project | Qur'an Memorizer DB |
| Frozen Baseline | QMDB-P0-FRZ-001 |
| Approved Change Request | QMDB-CR-001 — Asynchronous Progressive Interaction and Modal UX Standard |
| Phase | P1 — Engineering and Repository Foundation |
| Batch | QMDB-P1-B06 — Migration, Seeder, and Schema Ledger Foundation |
| Status | COMPLETE — accepted by QMDB-P1-CLOSE on 2026-08-26 |
| Execution Date | 2026-08-25 |

> **Final acceptance — 2026-08-26:** The blocker narrative below is retained as historical evidence and is superseded.
> The schema install/plan/migrate/status/seed/verify/rollback/reapply matrix passed against isolated MySQL 8.4.11 with
> separate non-root identities. See [the P1 verification report](../../closeout/p1/02-P1-executable-verification-report.md).
> Batch status is `COMPLETE`.

## Prerequisite Verification

P1-B01 through P1-B05 remain formally incomplete. The active CLI is PHP 8.2.12 while Composer requires PHP `^8.5`;
locked PHPUnit 13.3.1 requires PHP 8.4.1 or later. The local client/service is MariaDB 10.4.32 and is not an approved
MySQL substitute. Docker CLI 29.6.1 and Compose 5.2.0 are installed, but the Docker engine is unavailable. The frozen
manifest contains 82 entries and revalidated with zero mismatches. Existing B03-B05 working-tree changes were preserved.

## Prerequisite Corrections

- Updated superseded B05 architecture assertions to recognize the authorized explicit B06 manifest and schema boundary.
- Kept `app:about` and HTTP runtime construction independent of schema-operator credentials through lazy schema command
  resolution.
- Added the schema dependency to the HTTP and console module graph and corrected their declared service dependencies.

## Files Created

- `src/Bootstrap/Module/SchemaFoundationModule.php`.
- 59 PHP files under `src/Shared/Schema/`: checksum, configuration, connection, console, exception, health, lock,
  metadata, migration, runner, seed, state, and status contracts/implementations.
- `database/migrations.php`, `database/seeds.php`, `database/migrations/README.md`, and
  `database/seeds/README.md`.
- Schema test support: `TestMigration.php`, `TestSeed.php`, `RecordingSchemaMutationLock.php`,
  `NoOpSchemaMetadataInstallation.php`, `PdoSchemaConnectionProvider.php`, `InMemorySchemaStateRepository.php`, and
  `SchemaMySqlIntegrationTestCase.php`.
- Unit tests: `IdentifierAndSqlPolicyTest.php`, `RegistryChecksumPlannerTest.php`,
  `SchemaConfigurationAndConsoleTest.php`, `SchemaHealthCheckTest.php`, and `RunnerOrchestrationTest.php`.
- MySQL integration test: `SchemaMetadataAndLockIntegrationTest.php`.
- Architecture test: `SchemaFoundationArchitectureTest.php`.
- This report.

## Files Updated

- Runtime/composition: `.env.example`, `src/Bootstrap/ApplicationFactory.php`,
  `src/Bootstrap/ApplicationMetadata.php`, `src/Bootstrap/Console/ConsoleApplication.php`,
  `src/Bootstrap/Module/ConsoleFoundationModule.php`, `src/Bootstrap/Module/HttpFoundationModule.php`, and
  `src/Shared/Http/Controller/ReadinessController.php`.
- Test/quality infrastructure: `compose.mysql-test.yaml`, `phpcs.xml.dist`, `phpstan.neon.dist`, and the B05
  architecture/integration/metadata tests whose assertions were superseded by B06.
- Documentation/state: `README.md`, `docs/project/open-decisions.md`, `docs/project/risk-register.md`, and
  `docs/project/project-state.md`.

## Composer Changes

No package was added or removed, and B06 did not hand-edit `composer.lock`. The existing PHP requirement remains `^8.5`.
The explicit manifests contain no production class, so no additional PSR-4 mapping is necessary.

## Configuration Changes

Added `DB_SCHEMA_USERNAME`, `DB_SCHEMA_PASSWORD`, and `DB_SCHEMA_LOCK_TIMEOUT_SECONDS`. Schema identity is separately
validated, cannot equal the runtime identity, cannot be root, and is resolved only by schema commands. The password is
retrieved as `SecretValue` at the PDO boundary.

## Metadata Tables

Six static InnoDB/`utf8mb4` tables are defined: `qmdb_schema_meta`, `qmdb_schema_migrations`,
`qmdb_schema_migration_steps`, `qmdb_schema_migration_events`, `qmdb_schema_seeds`, and
`qmdb_schema_seed_events`. Ledger version is `1`. Installation is locked and idempotent when clean; incomplete,
incompatible, or structurally invalid existing metadata is rejected rather than automatically repaired.

## Migration Capabilities

The project-owned framework provides canonical IDs, ordered immutable SQL steps, explicit dependency registration,
cycle/missing-dependency rejection, deterministic topological planning, aggregate and per-step drift detection, binary
ledger checksums, batch allocation, safe events/failure codes, applied no-op behavior, partial-state preservation,
unchanged-step resumption, later-migration blocking, and controlled rollback.

## Seed Capabilities

The project-owned once-only seed framework provides canonical IDs, explicit dependency ordering, DML-only statements,
deterministic checksums, drift blocking, batches/events, applied no-op behavior, and all-step transaction rollback. No
seed rollback command exists.

## Locking Strategy

Schema mutation uses `GET_LOCK`/`RELEASE_LOCK` through native prepared statements. The bounded lock name is a fixed
prefix plus a truncated SHA-256 hash of logical database scope and contains no raw host, database, username, or secret.
Lock handles prevent double release, and all mutation services release in `finally`.

## Checksum Strategy

SHA-256 covers stable identifiers, descriptions, dependencies, reversibility, ordered step checksums, normalized line
endings, exact SQL, and canonically keyed scalar/null parameters. Stored applied checksums are never overwritten to
repair drift. Binary ledger values are 32 bytes; safe hexadecimal display is 64 lowercase characters.

## DDL Atomicity Limitations

MySQL DDL can commit implicitly. Migrations therefore do not claim transaction-wide atomicity: each successful up step
is recorded independently, later failure becomes `PARTIAL`, and resumption requires all recorded step checksums to
remain unchanged. Production corrections should normally use forward or approved compensating migrations.

## Rollback Policy

Rollback is limited to `local` and `test`, requires matching `--migration` and `--confirm`, and accepts only the latest
applied reversible migration with matching aggregate/step checksums, no applied dependent, and no unresolved failure.
`staging`/`production` have no bypass. History is retained; rollback failure becomes `ROLLBACK_FAILED` and blocks work.

## Schema Health Integration

Readiness combines runtime MySQL health with read-only runtime-account inspection of metadata and migration state. It
does not resolve schema credentials, acquire the mutation lock, install metadata, migrate, seed, or expose internal
details. Public output remains exactly `ready` or `not_ready`; liveness remains database-independent.

## CLI Commands

Eight commands are explicitly delegated: `db:schema:install`, `db:schema:verify`, `db:migrate:plan`, `db:migrate`,
`db:migrate:status`, `db:migrate:rollback`, `db:seed`, and `db:seed:status`. Unknown/malformed options return safe usage
output. No arbitrary SQL, path, class, registry, DSN, credentials, parameters, or exception trace is accepted/displayed.

## Tests Added

- 14 PHP test/support files.
- 43 new B06 unit test methods, including data-provider cases and nine runner orchestration methods.
- Three B06 architecture methods and two mandatory MySQL schema/locking methods.
- Compatibility suite after B06: 446 cases, 14,512 assertions, 11 explicit MySQL skips.
- Unit: 316 cases / 642 assertions. Non-MySQL integration: 50 / 175. Architecture: 69 / 13,695.

## Commands Run and Command Results

| Command | Exit/result |
| --- | --- |
| `php -v` | 0; PHP 8.2.12, below required 8.5 |
| `composer --version` | 0; Composer 2.8.8 |
| `git --version` | 0; Git 2.49.0.windows.1 |
| `mysql --version` | unavailable on `PATH`; XAMPP client identifies MariaDB 10.4.32 |
| `docker --version`; `docker compose version` | 0; Docker 29.6.1 / Compose 5.2.0 |
| `docker info` | 1; Docker engine pipe unavailable |
| `docker compose -f compose.mysql-test.yaml config --quiet` | 0 with disposable validation-only variables |
| `php -m` | 0; PDO and `pdo_mysql` present |
| `composer validate --strict` | 0 |
| `composer install --no-interaction` | 2; PHP/platform incompatibility |
| `composer audit --locked` | 0; no advisories |
| `composer dump-autoload --strict-psr` | 0 |
| diagnostic autoload with `--ignore-platform-req=php` | 0; enabled local PHP 8.2 diagnostics only |
| `composer cs:check` | 0; 328 files in the final pass |
| `composer analyse` | 0; maximum level, no errors |
| locked `composer test` / `composer test:mysql` | 1; PHPUnit 13 requires PHP 8.4.1+ |
| locked `composer quality` | 255; Composer platform guard blocks tool bootstrap on PHP 8.2 |
| PHPUnit 11.5.56 compatibility suite | 0; 446 tests, 14,512 assertions, 11 MySQL skips |
| Unit compatibility suite | 0; 316 tests, 642 assertions |
| Integration compatibility suite | 0; 50 tests, 175 assertions |
| Architecture compatibility suite | 0; 69 tests, 13,695 assertions |
| MySQL compatibility suite | 0 with 11 skips; no dedicated environment |
| PHP syntax sweep | 0; 328 files, zero failures |
| object-level `app:about` with supplied 8.5 runtime facts | 0; no schema credentials required |
| all eight real `bin/console db:*` commands | 1 each; blocked by actual PHP 8.2 runtime guard |
| real HTTP root/live/ready/about/HEAD/OPTIONS/404/405 matrix | HTTP 500; blocked before routing by PHP 8.5 guard |
| frozen checksum verification | 82 entries, zero mismatches |
| `git diff --check` | 0 in recorded passes |

## MySQL Integration Environment

`compose.mysql-test.yaml` now describes a local-only MySQL 8.4 test service plus separate non-root runtime and schema
identities. Its static configuration validates, but the Docker engine is unavailable. The local MariaDB service is
deliberately rejected. Consequently all 11 MySQL cases skipped and no metadata, advisory-lock, migration, rollback,
seed, schema CLI, or healthy-readiness claim is made.

## HTTP Verification

The PHP 8.2 built-in server was started on a dedicated local port, all root/live/ready/about/HEAD/OPTIONS/404/405 probes
returned bootstrap HTTP 500 due to the intentional PHP 8.5 runtime requirement, and the server was terminated. Object
and integration tests validate the composed HTTP boundaries, but they do not replace the required real PHP 8.5/MySQL
matrix.

## Security Controls

Runtime/schema credentials are separated; root, identity reuse, multi-statements, arbitrary SQL/path/class selection,
user-management SQL, global/FK settings, and production rollback are rejected. Failures preserve previous exceptions
internally but expose only stable safe codes. Events omit parameter values. Readiness omits IDs, tables, connection
details, SQL, and traces. No HTTP/AJAX schema endpoint, JavaScript, modal, SSE, ORM, or third-party migration package was
introduced.

## Architecture Boundaries

`foundation.schema` depends on core, application, and database. HTTP depends on schema only for read-only health;
console depends on schema for lazy explicit command delegation. Domain and generic application contracts do not depend
on schema infrastructure. The unrestricted container remains confined to the composition root. Production migration
and seed registration is explicit and empty; directory scanning/reflection discovery does not exist.

## Known Limitations

- PHP 8.5 is unavailable, so locked tool and real entry-point acceptance cannot run.
- The isolated approved MySQL schema test environment is unavailable, so every real schema gate remains unverified.
- P1-B01 through P1-B05 remain incomplete, preventing a valid B06 completion transition.
- Production lock timing, deployment orchestration, approvals, backup checkpoints, online/long-running DDL, and
  multi-region topology remain governed open decisions.

## Open Risks

Real MySQL behavior for the six-table DDL, structural verifier, advisory lock concurrency, implicit-commit partial
recovery, drift persistence, rollback, transactional seed failure, runtime grants, and readiness remains open until the
mandatory environment executes the suite. PHP 8.5 runtime and locked PHPUnit evidence also remain open.

## Decision Register

The frozen decision register was not modified. Implemented B06 decisions are recorded here and in executable source:
project-owned explicit migration/seed registries, timestamp IDs, ordered steps, immutable SHA-256 drift blocking, named
locks, metadata/business separation, per-step DDL truth, controlled resume, transactional seeds, local/test-only
rollback, forward production correction, separate schema credentials, read-only readiness, CLI-only mutation, and no
AJAX/modal/SSE transaction boundary. Unresolved operations were added to the dynamic open-decision register.

## Next Batch

QMDB-P1-B07 is not authorized. First provision PHP 8.5 and the isolated MySQL schema environment, run all locked and
real integration/CLI/HTTP gates, repair any failure, and close P1-B01 through P1-B06 in dependency order.
