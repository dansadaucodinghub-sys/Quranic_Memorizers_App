# Qur’an Memorizer DB

QMDB is the governed Qur’an Memorizer Database platform. This repository contains the bounded P1 Core PHP engineering
foundation and its `QMDB-P1-CLOSE` evidence. Closeout is currently `NOT_READY`; the engineering freeze is a candidate,
and P2 implementation is not authorized.

## Project identity

- Application: Qur’an Memorizer DB
- Code: QMDB
- Source baseline: QMDB-BL-001
- Frozen baseline: QMDB-P0-FRZ-001
- Phase: P1 — Engineering and Repository Foundation
- Batch: QMDB-P1-CLOSE
- Readiness: NOT_READY
- Engineering freeze: QMDB-P1-FRZ-001 CANDIDATE_NOT_FROZEN
- Development version: 0.1.0-dev

## Prerequisites

- PHP 8.5 or newer
- PHP extensions: JSON, Mbstring, PDO, and PDO MySQL
- Composer 2
- Node.js 24 LTS and npm 11 for frontend verification
- Git for repository-state inspection

The PHP 8.5 requirement is frozen by ADR-003 and is enforced by Composer and the application runtime validator. Do not bypass it for deployment or completion evidence.

## Installation

From the repository root, run:

```powershell
composer install
```

Composer installs the exact dependency versions recorded in `composer.lock` and generates the PSR-4 autoloader.

## Configuration setup

For local development only, create an untracked `.env` from the safe template:

```bash
cp .env.example .env
```

On Windows PowerShell, use:

```powershell
Copy-Item .env.example .env
```

The application also accepts explicit process environment values. Process values always take precedence over local `.env` values:

```bash
APP_ENV=local \
APP_DEBUG=false \
APP_TIMEZONE=UTC \
php bin/console app:about
```

PowerShell equivalent:

```powershell
$env:APP_ENV = 'local'
$env:APP_DEBUG = 'false'
$env:APP_TIMEZONE = 'UTC'
php bin/console app:about
```

## Supported environment values

```text
local
test
staging
production
```

`APP_ENV` is required. `APP_DEBUG` is optional and defaults to `false`. `APP_TIMEZONE` is optional and defaults to `UTC`; any explicitly supplied non-UTC value is rejected because authoritative application time is UTC.

`APP_LOG_LEVEL` is optional and defaults to `info`. Supported values are `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, and `emergency`. Staging and production reject `debug`. The destination, channel, format, and timezone are fixed to `php://stderr`, `qmdb`, JSON lines, and UTC.

## Secure defaults

- Debug mode defaults to disabled and is prohibited in staging and production.
- `.env` loading is permitted only for local development and tests.
- An externally supplied staging or production environment prevents `.env` from being read.
- Process environment values take precedence and are never overwritten by `.env`.
- Real secrets must not be committed or stored in ordinary configuration objects.
- Secret values require an explicit reveal operation and redact debug and JSON output.
- A production secret-provider integration will be selected by a later owning batch; no cloud provider is selected here.

## Quality commands

```powershell
composer validate --strict
composer security:audit
composer autoload:check
composer cs:check
composer analyse
composer test
composer quality
npm ci --ignore-scripts
npm run quality
php tools/ci/run-local-ci.php
```

`composer quality` runs validation, locked dependency audit, strict PSR-4 validation, PSR-12 checks, maximum-level PHPStan analysis, all PHPUnit suites, and the frontend quality gate in that order. The frontend gate performs JavaScript syntax checks, Node tests, and `npm audit`; `jsdom` is development-only and there is no frontend runtime dependency. The local CI entry point also runs repository, frozen-baseline, workflow, link, lockfile, SBOM, licence, release-build, and extracted-artifact gates. Missing MySQL or pinned Linux scanner binaries are reported and cannot count as completion evidence.

## CLI smoke command

```powershell
php bin/console app:about
php bin/console help
php bin/console schedule:list
php bin/console schedule:run
php bin/console worker:run --once
```

`app:about` obtains safe metadata and typed configuration information through the synchronous query bus. The CLI returns exit code `0` on success, `1` when runtime or configuration bootstrap requirements fail, and `64` for invalid usage.

## HTTP smoke command

```powershell
php -S 127.0.0.1:8080 -t public
```

Then verify the production routes:

```text
GET /                      localized HTML home page
GET /system/about          full HTML page or negotiated modal fragment
GET /system/status         full HTML page or negotiated status fragment
GET /health/live           process liveness
GET /health/ready          foundation readiness
GET /api/v1/system/about   system metadata
```

Every `GET` route supports automatic `HEAD`, and every known path supports automatic `OPTIONS`. Unknown paths return `404`; unsupported methods return `405` with `Allow`; unsafe request targets return `400`; unexpected handler failures return a generic `500`. JSON and problem responses use `no-store` and `nosniff`, and public output excludes environment, debug, PHP-version, path, dependency, and secret details. Stop the development server after verification.

## Dependency and module foundation

- `CompiledContainer` implements PSR-11 and resolves only explicit instance, factory, and alias definitions.
- Factories receive a restricted resolver that rejects undeclared dependencies; registrations freeze at compilation.
- Service and alias graphs are validated for missing targets, cycles, ownership, and forbidden cross-module edges before a runtime is returned.
- Modules are registered explicitly and ordered deterministically; there is no reflection autowiring, class scanning, filesystem discovery, or runtime plugin loading.
- Current modules are `foundation.core`, `foundation.application`, `foundation.database`, `foundation.schema`, `foundation.observability`, `foundation.background`, `foundation.http`, and `foundation.console`.
- `foundation.presentation` explicitly owns locale, translation, view, escaping, response, asset, and CSP-nonce services.
- The unrestricted compiled container remains inside `ApplicationFactory`; controllers, handlers, domain objects, and entry points do not receive it.

## Application services

QMDB-P1-B04 provides exact-class synchronous command and query buses plus a synchronous in-process domain-event dispatcher. Production currently registers one query handler, `GetSystemInformationHandler`; command and event maps are intentionally empty. Domain events are not durable, queued, retried, persisted, or written to an outbox in this batch.

The current foundation includes typed application/database/background configuration, controlled local environment loading, secret-value protection, UTC time, secure runtime identifiers, the validated HTTP kernel, dependency injection, module compilation, formal CLI dispatch, bounded worker execution, duplicate-safe fixed-interval scheduling, lazy MySQL connection/session verification, and transactions.

It does not include business tables, repositories, tenant data, sessions, authentication, authorization, CSRF, Redis, a durable job queue, a transactional outbox, state-changing AJAX, SSE, cloud secret providers, or business modules. The only production migration is the global scheduler operational ledger.

## Current implementation status

P1 — Engineering and Repository Foundation is complete and accepted as `QMDB-P1-CLOSE`. The locked PHP 8.5 suite,
isolated MySQL 8.4 LTS matrix, Node.js 24 frontend gates, pinned Windows security scanners, SBOM/licence controls,
deterministic release verification, and P0 frozen-baseline checks pass locally. Hosted CI, manual assistive-technology
coverage, and PCNTL-enabled deployment-host behavior remain explicit operational evidence gates rather than P1 source
blockers. P2-B01 is not authorized until OD-051 and OD-052 are resolved by their qualified owners. Consult
`docs/project/project-state.md` and `docs/closeout/p1/README.md`.

## CI, security, SBOM, and release artifacts

GitHub Actions provides read-only, fork-safe, SHA-pinned repository, PHP, frontend, MySQL, security, and release-artifact
jobs. Security binaries are version/checksum pinned. Release construction installs production Composer dependencies only,
uses an explicit file allowlist, records per-file hashes and source state, and verifies the extracted archive through real
CLI/HTTP execution. See `docs/implementation/ci-build-and-release-standard.md`.

## Presentation foundation

All primary pages are complete server-rendered documents. PHP templates are selected only through an explicit registry, receive one immutable `ViewData` object, and use contextual UTF-8 text/attribute escaping. Trusted HTML is produced only by internal template rendering. Ordinary HTML responses and `text/vnd.qmdb.fragment+html` fragments share authoritative application data.

English is the default locale. Use `?lang=en` or `?lang=ar`; Arabic pages emit `lang="ar"`, `dir="rtl"`, meaningful Arabic interface text, and logical-property CSS. Catalog parity is validated during HTTP bootstrap and tests. Language links use normal navigation and work without JavaScript.

## Themes and accessibility

The interface supports system, light, dark, high-contrast, and emerald-and-gold themes through CSS design tokens. Only the approved non-sensitive `qmdb.theme` value is stored in `localStorage`; account, locale, workspace, token, and cookie data are not stored or read. Layouts include a skip link, semantic landmarks, one global polite live region, visible focus, reduced-motion behavior, narrow-screen reflow, and a single labelled native dialog.

## Progressive interactions

Production JavaScript uses native ES modules, Fetch, AbortController, URL, DOM APIs, and native dialog behavior. System details open in the single modal and the status card refreshes in place when supported. Both retain ordinary fallback links. Requests are same-origin GETs, require the QMDB fragment media type and marker, reject executable markup, cancel superseded reads, ignore stale responses, preserve focus, and show only safe request-ID references on failure.

There is no AJAX mutation, automatic retry, nested modal, polling, SSE, client router, service worker, frontend framework, CSS framework, bundler, external CDN, or external font host in B09.

## MySQL prerequisites and local configuration

- MySQL LTS compatible with the frozen baseline; MariaDB is not accepted by the server verifier.
- PHP `PDO` and `pdo_mysql` extensions.
- A dedicated non-root application identity and a separate disposable test database/user.
- Verified TLS with a readable CA file in staging and production.

Configure `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USERNAME`, `DB_PASSWORD`, `DB_TLS_MODE`, `DB_TLS_CA_FILE`, `DB_CONNECT_TIMEOUT_SECONDS`, `DB_DEADLOCK_MAX_ATTEMPTS`, `DB_DEADLOCK_BASE_DELAY_MS`, and `DB_DEADLOCK_MAX_DELAY_MS`. Keep `DB_PASSWORD` outside source control; the empty `.env.example` value intentionally fails only when a connection is requested.

Integration tests use only `QMDB_TEST_DB_*` variables and never fall back to application credentials. The optional `compose.mysql-test.yaml` is test-only and requires disposable passwords to be injected by the operator.

```powershell
composer test:mysql
composer quality
```

`/health/live` reports PHP-process liveness and never opens MySQL. `/health/ready` lazily verifies the connection, MySQL/InnoDB identity, non-root account, UTC, `utf8mb4`, strict modes, and required transport; it returns only `ready` or `not_ready`.

Transactions default to `READ COMMITTED`, distinguish read-only/read-write operation, use deterministic savepoints for nesting, and retry only verified deadlock/serialization failures through bounded jitter. Retried callbacks must not perform irreversible external side effects. No transaction may span a modal, user think time, upload, external HTTP request, or SSE stream.

Future interfaces follow QMDB-CR-001: server-rendered progressive enhancement, native Fetch, partial region updates, controlled accessible modals, server-authoritative mutations, SSE for one-way live updates, and practical non-JavaScript fallbacks for competition-critical workflows.

## Structured logging

The `foundation.observability` module provides PSR-3/Monolog JSON-line logging to `php://stderr`. Records use UTC timestamps, the fixed `qmdb` channel, stable dot-separated event names, bounded context (depth 5, 50 entries per array, 2,048 characters per string), recursive sensitive-key redaction, and safe object/resource markers. Logging failures are contained by a non-recursive terminal fallback and do not replace successful application behavior.

Operational request logs contain only the server-generated request ID, method, final status, and monotonic duration. Request/response bodies, query strings, authorization and cookie headers, credentials, tokens, identity evidence, and arbitrary object serialization are excluded.

## Request correlation and errors

Every composed HTTP request receives a new 32-character lowercase hexadecimal correlation ID. Inbound request-ID headers are ignored. The same identifier is exposed as `X-Request-ID`, added to problem-details bodies as `request_id`, and used in restricted operational logs. CLI executions receive an independently generated reference used in start, completion, and unexpected-failure events.

Unexpected exceptions are centrally reported with a safe fingerprint and generic public response; exception messages, stack traces, absolute paths, SQL details, and credentials are not disclosed. PHP warnings/notices are converted through the runtime error handler, fatal shutdown errors are safely classified, and pre-autoload/runtime failures use a hardened bootstrap fallback with a public reference.

## HTTP security headers

The production middleware owns and overwrites `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`, `X-Permitted-Cross-Domain-Policies`, `Cross-Origin-Resource-Policy`, `Permissions-Policy`, and a restrictive API/problem-response Content Security Policy. `Strict-Transport-Security` is emitted only for staging/production requests whose URI directly proves HTTPS; forwarded-protocol headers are not trusted. `X-Powered-By` is removed at middleware/emission boundaries.

## AJAX and modal errors

Future same-origin Fetch clients must use `X-Request-ID` or problem `request_id` as the support reference for unexpected failures, never render raw exception content, announce the safe error through an accessible live region, and ignore stale responses. A failed modal mutation stays open, preserves safe user input, restores focus to the error summary or invalid field, and is not blindly retried without idempotency protection.

## Operational logs versus audit records

B07 logs are operational telemetry only. They are not the authoritative business audit ledger and must not be used as proof of user action, score integrity, consent, or regulated record history. Audit persistence remains owned by its later governed batch.

## Console, worker, and scheduler foundation

Console commands implement a typed contract and are registered explicitly; there is no filesystem discovery, reflection autowiring, or client-selected service ID. Help is generated from the ordered registry. Standard output and standard error remain separate, and structured command logs contain the canonical command name rather than option values.

The production job-handler registry is empty and `NullBackgroundJobSource` deliberately returns no work. `worker:run --once` is therefore a safe successful no-op. Continuous workers process one job at a time and stop at configured job, runtime, or memory bounds. PCNTL handles graceful `SIGTERM`/`SIGINT` where available; production-like continuous execution fails closed when PCNTL is required but unavailable. Unknown failures are permanent by default, and only the explicit retryable exception type receives bounded exponential release.

The production scheduled-task registry is empty. `schedule:list` reports that state and `schedule:run` completes with zero due tasks. Future registered tasks use fixed UTC intervals, idempotent handlers, a MySQL `qmdb_scheduled_task_runs` ledger, task/slot uniqueness, short transactional claims, expiring leases, reclaim attempt increments, and optimistic execution/version ownership. This is at-least-once execution; it is not exactly-once delivery.

Safe defaults are controlled by `WORKER_MAX_JOBS`, `WORKER_MAX_RUNTIME_SECONDS`, `WORKER_IDLE_SLEEP_MS`, `WORKER_MAX_MEMORY_MB`, `WORKER_REQUIRE_PCNTL_IN_PRODUCTION`, `SCHEDULER_RUN_LEASE_SECONDS`, and `SCHEDULER_LOCK_TIMEOUT_SECONDS`. Payloads and reservation tokens are excluded from console output and operational logs.

Future asynchronous HTTP commands may return `202 Accepted` only after authentication, authorization, tenant/state validation, and the immediate authoritative transaction commits. A public job reference is neither a reservation token nor an authorization credential. Browser routes cannot run workers or the scheduler, modals cannot hold transactions while background work executes, polling must be bounded, and SSE may project state but must never execute a job.
## Schema ledger and operations

QMDB owns its migration and once-only seed framework. The production manifests are explicit:
`database/migrations.php` and `database/seeds.php`. P1-B06 registers no business migration and no business seed; the
six `qmdb_schema_*` tables are framework metadata installed separately from business migrations.

### Schema management prerequisites

Schema operations require an isolated MySQL test or deployment database, a non-root runtime account, and a separate
non-root schema-operator account. Configure the runtime `DB_*` variables plus:

```dotenv
DB_SCHEMA_USERNAME=qmdb_migrator
DB_SCHEMA_PASSWORD=
DB_SCHEMA_LOCK_TIMEOUT_SECONDS=30
```

The schema password is resolved as a `SecretValue` only when an operational schema command opens its lazy connection.
It is not part of the DSN or safe configuration output. `compose.mysql-test.yaml` provisions separate disposable test
identities when its required `QMDB_TEST_DB_*` and `QMDB_TEST_DB_SCHEMA_*` variables are supplied.

### Schema commands

```powershell
php bin/console db:schema:install
php bin/console db:schema:verify
php bin/console db:migrate:plan
php bin/console db:migrate
php bin/console db:migrate:status
php bin/console db:migrate:rollback --migration=<id> --confirm=<id>
php bin/console db:seed
php bin/console db:seed:status
```

Migrations and seeds are registered explicitly, ordered by declared dependencies, protected by a bounded MySQL named
lock, and checked with immutable SHA-256 checksums. MySQL DDL may implicitly commit, so completed migration steps are
recorded individually; a later failure produces a partial state and blocks later migrations. Resume is permitted only
when all completed-step checksums remain unchanged. Seed DML runs as one transaction and contains no external effects.

Production rollback is prohibited. Local/test rollback requires the migration ID twice, applies only to the latest
eligible reversible migration, and preserves history. Production corrections normally use a new forward or approved
compensating migration.

Migrations and seeds never run automatically from application bootstrap, HTTP, readiness, AJAX, modal, or SSE paths.
Readiness performs read-only runtime-account checks and publicly returns only `ready` or `not_ready`. Future AJAX state
changes must use short bounded application transactions: no transaction remains open during modal display, user think
time, external calls, or an SSE connection.
