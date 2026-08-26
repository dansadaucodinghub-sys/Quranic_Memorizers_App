# QMDB Background Execution Standard

## Control and scope

This standard records the executable QMDB-P1-B08 console, worker, and scheduler boundary under QMDB-BL-001, QMDB-P0-FRZ-001, and QMDB-CR-001. It defines foundation mechanics only. Production business jobs, scheduled tasks, durable queue delivery, deployment supervisors, public job status, JavaScript, polling, and SSE remain outside this batch.

## Console command model

Commands implement `ConsoleCommand`, use a validated lowercase colon-separated name, receive immutable typed `ConsoleInput` plus `ConsoleOutput`, and return an explicit integer exit code. `ConsoleCommandRegistry` compiles an ordered immutable map; duplicates fail. Dispatch is exact-name only. The container, class names, service IDs, credentials, payloads, and reservation tokens are not accepted as command selectors.

The production registry contains `app:about`, eight governed schema commands, `schedule:list`, `schedule:run`, and `worker:run`; `help` is generated from that registry. Option names are canonical, duplicate/positional/control-character/secret-like options fail, and each owning command rejects options outside its allowlist. Standard output and error are separate streams.

## Job contracts and delivery

A background job is an in-memory object implementing `BackgroundJob`. Its envelope carries a server-generated opaque job ID, canonical name, correlation ID, UTC creation/availability times, and bounded attempt information. The foundation refuses envelope serialization because no durable payload format, schema version, encryption design, or queue provider is approved.

Handlers are registered by exact job class. Parent/interface fallback and dynamic discovery are prohibited. A successful handler returns `null`, after which the source acknowledges the reservation. Only `RetryableBackgroundJobFailure` is retryable; all other throwables are permanent by default. Retry release uses a bounded exponential delay, and an exhausted retryable job is failed. The source receives safe failure classification/fingerprint metadata, never authority from a client.

`NullBackgroundJobSource` is the production source for B08. It provides a deterministic no-work result and makes no durable-delivery claim. Test-only in-memory sources prove reserve, acknowledge, release, and fail behavior.

## Worker lifecycle

The worker generates a private worker ID and one execution correlation ID, registers signal handling, and processes one reserved job at a time. It checks maximum jobs, monotonic runtime, and memory before each reservation. No-work continuous execution sleeps for at least one millisecond; it does not busy-loop. `--once` reserves at most one job and exits successfully when no work exists.

Supported stop reasons are no-work-once, maximum jobs, maximum runtime, maximum memory, signal, source failure, execution failure, and configuration failure. `SIGTERM` and `SIGINT` request a graceful stop where PCNTL is available. The current job completes before the next loop observes the stop, and prior signal state is restored in `finally`. No forking, daemonization, parallel execution, event loop, shell process, or transaction-across-idle behavior exists.

Production-like continuous mode requires PCNTL when configured. Web execution has no PCNTL dependency. An external approved supervisor will own process restart and deployment topology.

## Scheduler and claim ledger

Scheduled tasks are registered explicitly with a canonical ID, description, fixed UTC interval, handler, bounded lease, and owning module. The production map is empty. Each invocation computes the deterministic current UTC slot and tries to claim `(task_id, scheduled_for)` before invoking the handler.

Migration `20260825000100_create_scheduled_task_runs` creates `qmdb_scheduled_task_runs` in InnoDB. Its composite primary key prevents duplicate task/slot rows; execution IDs are unique; status/scheduled and status/lease indexes support operations. Claim, running, success, and failure transitions use short transactions. The handler executes outside the claim transaction.

An active lease skips duplicate execution. An expired claimed/running lease may be reclaimed with a new execution ID, incremented attempt, and incremented optimistic version. Every terminal update matches task, slot, execution ID, and version, so a stale owner cannot complete reclaimed work. Succeeded and failed slots are terminal in this foundation.

Scheduler delivery is at least once, not exactly once: a process can crash after a side effect but before success persistence. Every scheduled handler must therefore be idempotent and use owning application services. Stored failure data is a bounded safe code, not an exception message or task payload.

## Operational events and privacy

Worker events: `worker.execution.started`, `worker.job.started`, `worker.job.succeeded`, `worker.job.retry_scheduled`, `worker.job.failed`, `worker.source.failed`, `worker.execution.stopping`, and `worker.execution.completed`.

Scheduler events: `scheduler.execution.started`, `scheduler.task.claimed`, `scheduler.task.reclaimed`, `scheduler.task.started`, `scheduler.task.succeeded`, `scheduler.task.failed`, `scheduler.task.skipped`, and `scheduler.execution.completed`.

Events contain only server-generated correlation/worker/job/execution identifiers, canonical names, attempts, counts, stop/claim states, safe failure codes, exception classes/fingerprints, UTC slots, and bounded durations. Job objects, payloads, reservation tokens, exception messages, credentials, handler service IDs, and queue internals are excluded. Operational events are not authoritative business audit records.

## HTTP, modal, polling, and SSE isolation

HTTP routes do not depend on the background module and cannot dispatch worker, scheduler, or migration commands. Future deferred mutations commit immediate authoritative state before returning `202 Accepted`. Public job references are non-authorizing opaque references distinct from internal tokens.

No transaction spans modal display, user think time, worker execution, polling, or SSE. A modal does not wait on a worker or assume completion. Polling is a bounded fallback only. SSE can project authoritative state but neither executes jobs nor owns worker connections.

## Configuration and operations

Default bounds are 100 jobs, 300 seconds, 1,000 ms idle sleep, 128 MiB memory, required production PCNTL, 300-second scheduler lease, and one-second scheduler lock timeout. Values are typed and range-validated. Operators must use the explicit CLI commands and an external scheduler/supervisor; application bootstrap, readiness, and HTTP never auto-run background work.

The MySQL migration must be applied through the governed schema CLI before production tasks are registered. Real scheduler-claim acceptance requires two independent approved MySQL LTS connections. PHP 8.5, locked PHPUnit 13, and approved MySQL evidence remain mandatory for batch completion.
