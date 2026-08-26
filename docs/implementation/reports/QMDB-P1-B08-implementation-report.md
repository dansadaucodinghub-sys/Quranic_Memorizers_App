# QMDB-P1-B08 Implementation Report

## Project

Qur’an Memorizer DB (`QMDB`)

## Frozen Baseline

`QMDB-P0-FRZ-001` over source baseline `QMDB-BL-001`. The 82 frozen paths remain unchanged.

## Approved Change Request

`QMDB-CR-001 — Asynchronous Progressive Interaction and Modal UX Standard` remains active.

## Phase

P1 — Engineering and Repository Foundation

## Batch

QMDB-P1-B08 — CLI, Scheduler, Worker, and Background Execution Foundation

## Status

`COMPLETE` — accepted by QMDB-P1-CLOSE on 2026-08-26. Worker-once, scheduler, duplicate-safe claims, failure exits,
and the scheduler ledger passed on PHP 8.5 and isolated MySQL 8.4.11. The host does not provide PCNTL/POSIX signals,
so continuous-worker deployment retains a fail-closed deployment gate; this does not block the portable P1 foundation.
See [the P1 verification report](../../closeout/p1/02-P1-executable-verification-report.md).

## Execution Date

2026-08-25

## Prerequisite Verification

- The repository root and existing uncommitted B03-B07 implementation were preserved; no reset, stash, revert, or commit occurred.
- P1-B01 through P1-B07 remain formally incomplete in the current project ledger.
- Existing typed configuration, UTC/monotonic clocks, PSR-11/module composition, application buses, structured logging/correlation, MySQL transactions, schema ledger, CLI, and HTTP foundations were inspected and reused.
- Active tools: PHP 8.2.12, Composer 2.8.8, Git 2.49.0.windows.1. PDO and PDO MySQL exist; PCNTL/POSIX do not.
- `mysql` is not on PATH. XAMPP MariaDB 10.4.32 is unapproved. Docker CLI/Compose exist but the engine is unavailable.
- Initial normal quality/MySQL gates were blocked by the frozen PHP 8.5 and locked PHPUnit 13 requirements.

## Prerequisite Corrections

1. Refactored the existing console application behind the formal registry/parser/dispatcher while preserving its compatible constructor, safe observer behavior, runtime validation, and existing result contract.
2. Migrated `app:about` and all eight schema operations into explicit console-command adapters without moving schema authority into HTTP.
3. Extended application/module composition and affected architecture/metadata tests for the new acyclic `foundation.background` dependency.
4. Extended the approved database-boundary architecture check to admit only scheduler infrastructure persistence.
5. During maximum-level analysis, strengthened stream resource revalidation, parser capture handling, console observer nullability, and MySQL scheduler row hydration.

## Files Created

### Production source

- `src/Bootstrap/Module/BackgroundExecutionFoundationModule.php`
- `src/Shared/Time/UtcDateTime.php`
- `src/Shared/Console/Command/AppAboutConsoleCommand.php`
- `src/Shared/Console/Command/ConsoleCommand.php`
- `src/Shared/Console/Command/ConsoleCommandDispatcher.php`
- `src/Shared/Console/Command/ConsoleCommandMap.php`
- `src/Shared/Console/Command/ConsoleCommandName.php`
- `src/Shared/Console/Command/ConsoleCommandRegistry.php`
- `src/Shared/Console/Command/SchemaConsoleCommand.php`
- `src/Shared/Console/Input/ConsoleInput.php`
- `src/Shared/Console/Input/ConsoleInputParser.php`
- `src/Shared/Console/Input/ConsoleOption.php`
- `src/Shared/Console/Input/ConsoleOptionName.php`
- `src/Shared/Console/Output/BufferedConsoleOutput.php`
- `src/Shared/Console/Output/ConsoleOutput.php`
- `src/Shared/Console/Output/StreamConsoleOutput.php`
- `src/Shared/Background/Configuration/BackgroundExecutionConfiguration.php`
- `src/Shared/Background/Configuration/BackgroundExecutionConfigurationFactory.php`
- `src/Shared/Background/Console/ScheduleListConsoleCommand.php`
- `src/Shared/Background/Console/ScheduleRunConsoleCommand.php`
- `src/Shared/Background/Console/WorkerRunConsoleCommand.php`
- `src/Shared/Background/Job/BackgroundJob.php`
- `src/Shared/Background/Job/BackgroundJobEnvelope.php`
- `src/Shared/Background/Job/BackgroundJobExecutionContext.php`
- `src/Shared/Background/Job/BackgroundJobExecutionOutcome.php`
- `src/Shared/Background/Job/BackgroundJobExecutionResult.php`
- `src/Shared/Background/Job/BackgroundJobExecutor.php`
- `src/Shared/Background/Job/BackgroundJobFailure.php`
- `src/Shared/Background/Job/BackgroundJobFailureClassifier.php`
- `src/Shared/Background/Job/BackgroundJobFailureCode.php`
- `src/Shared/Background/Job/BackgroundJobHandler.php`
- `src/Shared/Background/Job/BackgroundJobHandlerMap.php`
- `src/Shared/Background/Job/BackgroundJobHandlerRegistry.php`
- `src/Shared/Background/Job/BackgroundJobId.php`
- `src/Shared/Background/Job/BackgroundJobIdGenerator.php`
- `src/Shared/Background/Job/BackgroundJobName.php`
- `src/Shared/Background/Job/ConservativeBackgroundJobFailureClassifier.php`
- `src/Shared/Background/Job/JobReservationToken.php`
- `src/Shared/Background/Job/PermanentBackgroundJobFailure.php`
- `src/Shared/Background/Job/ReservedBackgroundJob.php`
- `src/Shared/Background/Job/RetryableBackgroundJobFailure.php`
- `src/Shared/Background/Scheduler/FixedIntervalSchedule.php`
- `src/Shared/Background/Scheduler/Infrastructure/MySqlScheduledTaskRunRepository.php`
- `src/Shared/Background/Scheduler/Migration/CreateScheduledTaskRunsMigration.php`
- `src/Shared/Background/Scheduler/Schedule.php`
- `src/Shared/Background/Scheduler/ScheduledExecutionSlot.php`
- `src/Shared/Background/Scheduler/ScheduledTask.php`
- `src/Shared/Background/Scheduler/ScheduledTaskClaimDisposition.php`
- `src/Shared/Background/Scheduler/ScheduledTaskExecutionContext.php`
- `src/Shared/Background/Scheduler/ScheduledTaskExecutionOutcome.php`
- `src/Shared/Background/Scheduler/ScheduledTaskExecutionResult.php`
- `src/Shared/Background/Scheduler/ScheduledTaskHandler.php`
- `src/Shared/Background/Scheduler/ScheduledTaskId.php`
- `src/Shared/Background/Scheduler/ScheduledTaskMap.php`
- `src/Shared/Background/Scheduler/ScheduledTaskRegistry.php`
- `src/Shared/Background/Scheduler/ScheduledTaskRunClaim.php`
- `src/Shared/Background/Scheduler/ScheduledTaskRunRecord.php`
- `src/Shared/Background/Scheduler/ScheduledTaskRunRepository.php`
- `src/Shared/Background/Scheduler/ScheduledTaskRunStatus.php`
- `src/Shared/Background/Scheduler/Scheduler.php`
- `src/Shared/Background/Scheduler/SchedulerExecutionId.php`
- `src/Shared/Background/Scheduler/SchedulerRunResult.php`
- `src/Shared/Background/Source/BackgroundJobSource.php`
- `src/Shared/Background/Source/NullBackgroundJobSource.php`
- `src/Shared/Background/Worker/BackgroundWorker.php`
- `src/Shared/Background/Worker/BackgroundWorkerIdentity.php`
- `src/Shared/Background/Worker/BackgroundWorkerIdentityGenerator.php`
- `src/Shared/Background/Worker/BackgroundWorkerOptions.php`
- `src/Shared/Background/Worker/BackgroundWorkerResult.php`
- `src/Shared/Background/Worker/BackgroundWorkerStopReason.php`
- `src/Shared/Background/Worker/MemoryUsageProvider.php`
- `src/Shared/Background/Worker/NativeMemoryUsageProvider.php`
- `src/Shared/Background/Worker/NullWorkerSignalController.php`
- `src/Shared/Background/Worker/PcntlWorkerSignalController.php`
- `src/Shared/Background/Worker/WorkerSignalController.php`
- `src/Shared/Background/Worker/WorkerStopController.php`

### Test and support source

- `tests/Architecture/BackgroundExecutionArchitectureTest.php`
- `tests/Integration/Console/BackgroundConsoleIntegrationTest.php`
- `tests/Integration/MySql/SchedulerClaimIntegrationTest.php`
- `tests/Integration/MySql/SchedulerLedgerIntegrationTest.php`
- `tests/Unit/Shared/Background/BackgroundConfigurationTest.php`
- `tests/Unit/Shared/Background/BackgroundJobContractTest.php`
- `tests/Unit/Shared/Background/BackgroundJobExecutionTest.php`
- `tests/Unit/Shared/Background/BackgroundWorkerTest.php`
- `tests/Unit/Shared/Background/ScheduledTaskContractTest.php`
- `tests/Unit/Shared/Background/SchedulerTest.php`
- `tests/Unit/Shared/Console/FormalConsoleFoundationTest.php`
- `tests/Support/Background/ChildBackgroundJob.php`
- `tests/Support/Background/FakeMemoryUsageProvider.php`
- `tests/Support/Background/FakeSignalController.php`
- `tests/Support/Background/InMemoryBackgroundJobSource.php`
- `tests/Support/Background/InMemoryScheduledTaskRunRepository.php`
- `tests/Support/Background/ParentBackgroundJob.php`
- `tests/Support/Background/RecordingSleeper.php`
- `tests/Support/Background/SecondaryBackgroundJob.php`
- `tests/Support/Background/SequenceClock.php`
- `tests/Support/Background/SequenceRuntimeIdentifierGenerator.php`
- `tests/Support/Background/TestBackgroundJob.php`
- `tests/Support/Background/TestBackgroundJobHandler.php`
- `tests/Support/Background/TestScheduledTaskHandler.php`

### Documentation

- `docs/implementation/background-execution-standard.md`
- `docs/implementation/reports/QMDB-P1-B08-implementation-report.md`

## Files Updated

- `.env.example`
- `README.md`
- `database/migrations.php`
- `docs/implementation/frontend-interaction-standard.md`
- `docs/project/open-decisions.md`
- `docs/project/project-state.md`
- `docs/project/risk-register.md`
- `src/Bootstrap/ApplicationFactory.php`
- `src/Bootstrap/ApplicationMetadata.php`
- `src/Bootstrap/Console/ConsoleApplication.php`
- `src/Bootstrap/Module/ConsoleFoundationModule.php`
- `src/Shared/Configuration/ConfigurationViolation.php`
- `tests/Architecture/ConfigurationArchitectureTest.php`
- `tests/Architecture/DatabaseArchitectureTest.php`
- `tests/Architecture/SchemaFoundationArchitectureTest.php`
- `tests/Architecture/SourceArchitectureTest.php`
- `tests/Integration/Bootstrap/ApplicationFactoryTest.php`
- `tests/Integration/Bootstrap/FoundationCompilationTest.php`
- `tests/Integration/Console/Observability/ConsoleObservabilityIntegrationTest.php`
- `tests/Integration/ConsoleApplicationTest.php`
- `tests/Integration/Http/HttpRoutesTest.php`
- `tests/Support/ApplicationTestFactory.php`
- `tests/Unit/ApplicationMetadataTest.php`
- `tests/Unit/Shared/Application/SystemInformationTest.php`

The frozen roadmap, backlog, decision register, and quality-attribute parameter register were not updated because the baseline freeze outranks the B08 update request. The conflict is recorded here and in dynamic state.

## Composer Changes

No dependency was added or removed for B08. `composer.json` and `composer.lock` contain preserved prior-batch work and were not changed by this batch. A diagnostic install with `--ignore-platform-req=php` completed without package changes; normal install remains correctly blocked on PHP 8.2.12.

## Configuration Changes

Added typed/range-validated defaults for maximum jobs, runtime, idle sleep, memory, production PCNTL requirement, scheduler lease, and scheduler lock timeout. No secret, queue name, handler ID, or class name is configurable through worker options.

## Console Command Foundation

Commands use validated names, explicit deterministic registration, immutable typed input/options, per-command option allowlists, separate output/error abstractions, exact dispatch, generated help, and safe exit codes. Existing about/schema behavior is migrated rather than duplicated.

## Background Job Contracts

The foundation supplies opaque IDs/names, UTC envelopes, protected reservation tokens, execution context, exact class-to-handler maps, source reserve/ack/release/fail contracts, null production source, conservative failure classification, and synchronous one-handler execution. Payload serialization is intentionally prohibited.

## Worker Capabilities

One job at a time; once mode; maximum jobs/runtime/memory; controlled idle sleep; processed/failed counters; typed results; source/execution failure stops; no busy loop, transaction, fork, daemon, process manager, or event loop.

## Signal Capabilities

PCNTL supports asynchronous SIGTERM/SIGINT stop requests and handler restoration when present. Null support preserves web/once execution. Production-like continuous mode fails closed when configured PCNTL is unavailable. This Windows host has no real PCNTL/POSIX signal evidence.

## Scheduler Capabilities

Explicit ordered task registration, fixed UTC interval slots, per-task bounded leases, short transactional claims, active/succeeded/failed skips, expired-lease reclaim, attempt increments, execution ownership, optimistic versions, safe terminal status, and continued independent task iteration.

## Scheduler Ledger Migration

Migration `20260825000100_create_scheduled_task_runs` creates the global InnoDB `qmdb_scheduled_task_runs` table with composite task/slot primary key, unique execution ID, status/scheduled and status/lease indexes, checks, timestamps, duration, safe failure code, and optimistic version.

## MySQL Claim Semantics

The repository uses `INSERT IGNORE` for first ownership, `SELECT ... FOR UPDATE` for duplicate/reclaim decisions, version-checked reclaim, and task/slot/execution/version predicates for terminal updates. Handlers execute outside claim transactions. Two-connection tests are implemented but skipped because approved test credentials/service are unavailable.

## Delivery Guarantees

Scheduler execution is at least once, not exactly once; handlers must be idempotent. The null production job source makes no durable job-delivery guarantee. A future durable adapter must define reservation/recovery semantics before production handlers register.

## Production Registries

Production job handlers: 0. Production scheduled tasks: 0. Production jobs: 0. Production job source: deterministic no-work implementation.

## AJAX and Background Boundary

Future deferred requests authenticate, authorize, scope, validate, and commit immediate state before returning an optional `202 Accepted`. Public job references are not reservation/authorization tokens. Modals do not hold transactions or wait for workers; polling is bounded; SSE projects state but does not execute work. No endpoint/UI was added.

## Tests Added

Seven unit files (46 defined methods; 83 executed cases/156 assertions), one formal-console integration file (3 methods; 12 cases/47 assertions), two real-MySQL integration files (2 cases, both skipped without the environment), one architecture/security file (5 cases/196 assertions), and thirteen support fixtures were added. The focused B08 set contains 102 cases; 100 passed and 2 MySQL cases skipped. The final repository compatibility run executed 641 cases and 21,977 assertions with 13 expected MySQL skips.

## Commands Run

| Command | Exit | Actual result |
| --- | ---: | --- |
| `php -v` | 0 | PHP 8.2.12; below frozen 8.5 |
| `composer --version` | 0 | Composer 2.8.8 |
| `git --version` | 0 | Git 2.49.0.windows.1 |
| `mysql --version` | unavailable | Not on PATH; XAMPP alternative identifies unapproved MariaDB 10.4.32 |
| `docker --version`; `docker compose version` | 0 | CLI available; engine unavailable |
| `php -m` | 0 | PDO/PDO MySQL present; PCNTL/POSIX absent |
| `composer validate --strict` | 0 | Valid |
| `composer audit --locked` | 0 | No advisories |
| `composer dump-autoload --strict-psr` | 0 | Optimized autoload generated |
| `composer install --no-interaction` | 1 | Correct PHP/platform failure |
| `composer install --ignore-platform-req=php` | 0 | Diagnostic only; no package changes |
| `composer cs:check` | 0 | PSR-12 pass through direct compatible invocation |
| `composer analyse` | 0 | Maximum-level pass through direct compatible invocation |
| locked `composer test` / `composer test:mysql` | 1 / 1 | PHPUnit 13 cannot run on PHP 8.2.12 |
| `composer quality` | 255 | Stops at frozen Composer PHP platform guard |
| PHPUnit 11 compatibility suite | 0 | Pass with expected MySQL skips |
| PHP syntax sweep | 0 | Pass |
| composed supplied-runtime CLI suite | 0 | Help/about/schedule/worker paths pass |
| real `bin/console` matrix | 1 each | Safe intentional PHP 8.5 runtime rejection |
| `git diff --check` | 0 | Pass |
| frozen baseline checksum recheck | 0 | 82 entries, zero mismatches |

## Command Results

Composer validation/audit/autoload, compatible PSR-12/PHPStan/PHPUnit, programmatic CLI, architecture/security, and source syntax pass. Locked PHP 8.5/PHPUnit 13, approved MySQL, real signal, successful real CLI, and complete real HTTP acceptance remain unavailable.

## Real CLI Verification

The actual PHP 8.2 entrypoint fails closed before dispatch with a generic runtime-requirement message and non-zero exit. The composed application supplied with approved PHP 8.5 runtime facts successfully executes help, about, empty schedule list/run, worker once, and bounded worker behavior. The latter is compatibility evidence, not a real PHP 8.5 entrypoint pass.

## Signal Verification

Fake-signal lifecycle tests prove registration, stop request, after-current-job loop behavior, and restoration. Real PCNTL signal delivery is unavailable on this Windows PHP build and remains mandatory on the deployment target.

## MySQL Verification

The two scheduler MySQL tests are implemented and included in `composer test:mysql`; they skip without dedicated `QMDB_TEST_DB_*` and schema credentials. No local MariaDB result is accepted as MySQL LTS evidence.

## HTTP Regression Verification

The compatibility HTTP suites remain green and architecture tests prove no background route/dependency. Real successful PHP 8.5 server regression remains blocked by the runtime gate.

## Security Controls

Exact command/job/task registration, canonical names, client-option allowlists, protected non-serializable reservation tokens, payload-free logs, safe fingerprints/codes, explicit retryability, bounded worker resources, server-generated correlation/worker/execution IDs, scheduler uniqueness/lease/version ownership, HTTP isolation, and frozen-baseline integrity are enforced in source/tests.

## Architecture Boundaries

`foundation.console` depends on `foundation.background`; `foundation.http` does not. Background application contracts do not receive the container. MySQL code is confined to scheduler infrastructure. There is no discovery, Redis/queue/cron/process package, fork, daemon, shell execution, business job/task, frontend asset, modal, SSE, or job-status endpoint.

## Known Limitations

1. PHP 8.5 and locked PHPUnit 13 acceptance are unavailable.
2. P1-B01 through P1-B07 remain incomplete.
3. Approved isolated MySQL LTS migration/claim evidence is unavailable.
4. PCNTL/POSIX and real signal verification are unavailable on this host.
5. Real successful CLI/HTTP entrypoints cannot pass until the runtime is provisioned.

Intentionally deferred durable queue/Redis, business jobs/tasks, public job progress, JavaScript, modals, polling, SSE, and deployment supervisors are batch boundaries rather than implementation defects.

## Open Risks

The dynamic risk register records resource exhaustion, signal loss, leakage, blind retry, duplicate/reclaimed work, stale owners, non-idempotency, crash-after-side-effect, false exactly-once assumptions, browser execution, missing production adapter, unapplied migration, and frontend responsibility risks. Acceptance remains gated by conforming environments.

## Next Batch

QMDB-P1-B09 — View Rendering, Localization, RTL, Theme, and Progressive AJAX/Modal Foundation is not ready or authorized. Resolve the B08 and prerequisite blockers first.

## Final acceptance addendum — 2026-08-26

The historical prerequisite and environment statements above are superseded. Batch status is `COMPLETE`.
