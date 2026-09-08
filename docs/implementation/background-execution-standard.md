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
## P2-B04 production scheduled delivery

The production registry now contains `identity.security_notifications.deliver`, scheduled every 60 seconds. Registry
listing is metadata-only and does not eagerly resolve database, encryption, mail, or notification dependencies. The
handler resolves only after the scheduler owns the due execution slot. It claims bounded notification batches with
execution-owned leases and optimistic versions, performs SMTP outside database transactions, and records bounded retry
or terminal outcomes. Delivery is at least once; no exactly-once SMTP claim is made. No HTTP route can run or select the
task. Production deployment must configure and monitor an external CLI scheduler.

## P2-B05 security-notification reuse

B05 adds durable intents for MFA enablement/disablement, TOTP addition/removal, passkey addition/removal/suspension,
recovery-code regeneration and recovery-code use. It does not add a second scheduler, worker or provider path. The
existing `identity.security_notifications.deliver` task owns their bounded at-least-once delivery under the same lease,
retry, localization, redaction and no-HTTP-execution rules established in B04. Factor mutations commit authoritative
state and notification intent atomically; SMTP remains after commit and outside the mutation transaction.

## P2-B07 tenant-bound job foundation

Future tenant jobs implement `AccountTenantBoundBackgroundJob` and carry server-owned Account, Workspace, and
Membership references. The execution resolver revalidates active and exact relational state before returning trusted
context. Inactive or mismatched state resolves no context. B07 registers no production tenant job, does not replace the
null job source, adds no durable queue, and does not log job payloads.

## P2-B08 privileged-access maintenance

P2-B08 registers `security.privileged_access.maintain` at a fixed 60-second UTC interval alongside the existing
notification-delivery task. The handler executes bounded, transactional maintenance only: request expiry, activation
expiry, required-review creation, and overdue-review marking. Authorization and context resolution remain the
authoritative synchronous expiry control, so scheduler delay never extends access. Repeated runs are safe and do not
create a browser, queue, polling, or email-delivery execution path.

## P2-B09 audit checkpoint task

`security.audit.checkpoint` runs at the configured UTC interval. It takes a bounded advisory lock, snapshots current
stream heads, and creates a checkpoint only when the ledger has changed. It performs no full-ledger verification,
does not publish externally without a separately configured publisher, returns normally to the scheduler, and exposes
neither event metadata nor the audit integrity key in scheduler output or logs.

## P2-B10 scheduler verification boundary

B10 validates the registered task inventory and reuses the existing scheduler concurrency, bounded lease, idempotency,
tenant revalidation, and no-payload-logging tests. It adds no worker route, durable queue, persistent cache, external
publisher, or schedule-triggered full audit scan.

## P3-B05 claim-maintenance task

`people.profile_claims.maintain` is a bounded UTC maintenance task. It expires pending pairings and claims according
to their persisted lifecycle, appends the controlled history/audit/notification records, and is idempotent on repeat
runs. It cannot create a claim, grant a SELF link, mutate a duplicate case, extend authority, or disclose pairing
material. The synchronous authorization and acceptance checks remain authoritative, so scheduler delay never turns an
expired, revoked, or unaccepted claim into an active link.
