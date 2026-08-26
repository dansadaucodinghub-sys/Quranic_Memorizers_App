# QMDB-P1-B04 Implementation Report

| Field | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Frozen Baseline | QMDB-P0-FRZ-001 |
| Phase | P1 — Engineering and Repository Foundation |
| Batch | QMDB-P1-B04 — Dependency Injection, Module Registry, and Application Services |
| Status | COMPLETE — accepted by QMDB-P1-CLOSE on 2026-08-26 |
| Execution Date | 2026-08-25 |
| Next Batch | QMDB-P1-B05 — NOT READY |

## Prerequisite Verification

- P1-B01 through P1-B03 source foundations remain present.
- The frozen PHP requirement remains `^8.5`; it was not lowered for this PHP 8.2 host.
- The initial working tree contained the uncommitted B03 closeout and an interrupted partial B04 scaffold. Those changes were preserved and reconciled.
- Initial `git diff --check` passed.
- The initial quality gate was blocked by Composer’s PHP platform guard before PHPUnit could run.

## Prerequisite Corrections

- Preserved the raw request target in `HttpTestFactory` so invalid percent encoding reaches the P1-B03 validator unchanged.
- Added deterministic detection of unterminated quoted dotenv assignments before library parsing, retaining generic error messages with no value disclosure.
- Updated B02 architecture allowlists for the approved SAPI request adapter and the B04 PSR-11 compiled container.

## Files Created

- Dependency injection: `CompiledContainer`, `ContainerBuilder`, definition registry, factories, restricted resolver, typed service references, aliases and safe exception types under `src/Shared/DependencyInjection/`.
- Module foundation: `ModuleId`, `Module`, `ModuleRegistrationContext`, `ModuleRegistry`, and `ModuleCompilation` under `src/Shared/Module/`.
- Application services: command/query registries and synchronous buses, domain-event contracts/registry/dispatcher, restricted handler resolution, and safe messaging exceptions under `src/Shared/Application/` and `src/Shared/Domain/Event/`.
- System information: `GetSystemInformation`, `SystemInformation`, `GetSystemInformationHandler`, and `RuntimeEnvironment`.
- Foundation modules: core, application, HTTP, and console modules under `src/Bootstrap/Module/`.
- Tests: B04 unit, integration, architecture, security, and test-support fixtures under `tests/`.
- This report.

## Files Updated

- `composer.json`, `composer.lock`
- `src/Bootstrap/Application.php`
- `src/Bootstrap/ApplicationFactory.php`
- `src/Bootstrap/ApplicationMetadata.php`
- `src/Bootstrap/Console/ConsoleApplication.php`
- `src/Shared/Configuration/Infrastructure/DotenvEnvironmentLoader.php`
- `src/Shared/Http/Controller/SystemAboutController.php`
- `bin/console`
- `README.md`
- `docs/project/project-state.md`
- `docs/project/risk-register.md`
- Existing B02/B03 integration and architecture tests needed for the prerequisite corrections and B04 boundaries

## Dependencies Added

| Package | Scope | Resolved version | Purpose | New transitive dependencies |
| --- | --- | --- | --- | --- |
| `psr/container` | Runtime | 2.0.2 | PSR-11 container and exception contracts | None |

Composer performed the lock-file update. No third-party container implementation or messaging package was added.

## Container Capabilities

- 36 explicit shared service definitions: 8 instances and 28 factories.
- Three interface aliases.
- Lazy successful factory creation with identity-stable shared instances.
- Unknown-service, invalid-service, circular-dependency, undeclared-dependency, and generic container exceptions implementing PSR-11 contracts where applicable.
- Build-time validation for identifiers, duplicates, collisions, missing dependencies, alias targets/cycles, self/duplicate dependencies, service cycles, module ownership, and forbidden cross-module edges.
- Factories receive `DependencyResolver`, restricted to their declared dependency identifiers; the compiled container and service list are not exposed.
- Compilation freezes registration. The compiled container has no mutation or definition-enumeration API.

## Service Definitions

- Core: metadata, typed configuration, environment-variable boundary, runtime requirements/environment, UTC clock, runtime identifier generator, and secrets provider.
- Application: immutable handler maps, restricted handler resolver, query-backed system information, synchronous buses/dispatcher, and `Application`.
- HTTP: PSR-17 factory, native request construction, response factories, controllers, routes, router, dispatcher, middleware, kernel, emitter, and `HttpRuntime`.
- Console: `ConsoleApplication`.

## Foundation Modules

| Module | Direct dependencies |
| --- | --- |
| `foundation.core` | None |
| `foundation.application` | `foundation.core` |
| `foundation.console` | `foundation.core`, `foundation.application` |
| `foundation.http` | `foundation.core`, `foundation.application` |

Deterministic registration order is core, application, console, HTTP. Independent modules are sorted by canonical module ID. Services retain their owning module, and direct or transitive module access is required for every service/alias edge.

## Application Service Contracts

- Exact-class `Command` and `Query` marker contracts.
- Marker handler contracts with callable `__invoke()` enforcement.
- One handler per command and query; duplicate registrations fail.
- Zero or more ordered handlers per exact domain-event class; duplicate pairs fail.
- Synchronous invocation only, no retries, queues, persistence, transactions, event store, or outbox.
- Non-null command/event handler returns are rejected; query results are returned unchanged.

## Production Handler Registrations

| Type | Count | Registration |
| --- | ---: | --- |
| Command | 0 | None in the foundation |
| Query | 1 | `GetSystemInformation` → `GetSystemInformationHandler` |
| Domain event subscriber | 0 | None in the foundation |

## Executable Integrations

- `SystemAboutController` asks `QueryBus` and publishes only application, name, ready status, phase, batch, and baseline.
- `ConsoleApplication` asks `QueryBus` and renders the approved safe operational fields without container, module-map, service-list, environment-map, or secret output.
- `ApplicationFactory` is the only unrestricted root resolution boundary and returns narrow `Application`, `ConsoleApplication`, or `HttpRuntime` roots.
- `public/index.php` receives only `HttpRuntime`; `bin/console` receives only `ConsoleApplication`.

## Tests Added

Seven B04 test classes add 59 focused cases: 43 unit, 4 integration, 8 architecture, and 4 explicitly security-focused cases. The repository-wide PHP 8.2 compatibility execution completed 325 tests and 9,318 assertions: 221 unit/456 assertions, 50 integration/184 assertions, and 54 architecture/8,678 assertions.

## Commands Run and Results

| Command | Exit | Result |
| --- | ---: | --- |
| `php -v` | 0 | PHP 8.2.12; below approved PHP 8.5 |
| `composer validate --strict` | 0 | `composer.json` and lock metadata valid |
| `composer audit --locked` | 0 | No security advisories found |
| `composer require psr/container` | 0 after retry | Composer resolved and locked `psr/container` 2.0.2; PHP platform requirement ignored only for local dependency diagnostics |
| Normal `composer install` | 1 | Correctly rejected PHP 8.2.12 against root PHP `^8.5` and locked PHPUnit toolchain |
| `composer dump-autoload --strict-psr` | 0 | 2,072 classes generated with no PSR-4 failure |
| PHP syntax loop over `src`, `tests`, `public`, `routes`, `bin/console` | 0 | 199 files passed |
| `php vendor/bin/phpstan analyse ...` | 0 | Maximum-level analysis passed |
| `php vendor/bin/phpcs ...` | 0 | 199 files passed |
| Locked `php vendor/bin/phpunit ...` | 1 | PHPUnit 13 requires PHP 8.4.1+ and did not start on PHP 8.2.12 |
| Temporary PHPUnit 11.5.56 compatibility suite | 0 | 325 tests, 9,318 assertions passed; diagnostic only |
| `composer quality` | 1 | Validation, audit and autoload generation passed; next PHP subprocess stopped at the PHP 8.5 platform guard |
| Programmatic CLI about smoke with supplied PHP 8.5.3 | 0 | Safe P1-B04 output passed |
| Programmatic CLI matrix | 0 | `app:about` 0, `help` 0, unknown command 64 |
| Programmatic HTTP matrix | 0 | Root/live/ready/about 200, HEAD 200, OPTIONS 204, missing 404, method-not-allowed 405 |
| Real PHP 8.2 HTTP failure smoke | 0 | Safe generic 500 problem response; success matrix blocked by PHP version |
| Frozen-manifest SHA-256 check | 0 | 82 entries; zero mismatches |
| `git diff --check` | 0 | Passed after final whitespace correction |

## CLI Verification

The composed `ConsoleApplication` passed `app:about`, help, and unknown-command behavior through the query-backed application with a supplied compatible runtime. Real `bin/console` success execution remains blocked by the intentional PHP 8.5 entry-point guard on this PHP 8.2 host. The unsupported-runtime failure remains safe and non-zero.

## HTTP Verification

The composed `HttpRuntime` passed the full programmatic status matrix and the existing HTTP integration suite under the compatibility runner. A real PHP built-in server returned the expected generic bootstrap 500 on this unsupported runtime. The real success matrix remains mandatory on PHP 8.5 before completion.

## Architecture Boundaries

- Domain has no dependency on Application, HTTP, Bootstrap, Module, or DI layers.
- Application has no PSR container, PSR HTTP, HTTP implementation, or module-registry dependency.
- DI has no HTTP, messaging, domain, database, or Redis dependency.
- Controllers and handlers receive explicit constructor dependencies, never the container.
- No reflection autowiring, setter/property injection, class scanning, filesystem module discovery, dynamic evaluation, or runtime plugins exist.

## Security Controls

- Service and module identifiers are validated; module IDs reject whitespace, traversal, controls, uppercase, and malformed dot syntax.
- Request, route, environment, and message payload data cannot select services, handlers, or modules.
- Restricted factory resolvers prevent hidden dependencies.
- All graph errors occur before a root runtime is returned.
- Exceptions identify only safe service/module/message identifiers and do not serialize payloads or object contents.
- HTTP and CLI system information preserve their separate disclosure boundaries.
- Existing secret redaction and generic HTTP exception handling remain intact.

## Known Limitations

- The only installed PHP CLI is 8.2.12; Composer requires PHP `^8.5` and PHPUnit 13.3.1 requires PHP 8.4.1 or newer.
- Consequently, normal `composer install`, locked PHPUnit 13 execution, combined `composer quality`, real successful `bin/console`, and the real PHP 8.5 HTTP server matrix remain unexecuted.
- The passing PHPUnit 11 compatibility run is valuable regression evidence but is not accepted as the locked toolchain gate.
- Synchronous domain events are intentionally non-durable; durable outbox and queued processing remain later owning batches.

## Open Risks

- QMDB-RSK-064 through QMDB-RSK-067 remain open until the full P1 gates execute on approved PHP 8.5.
- No risk is accepted or closed by compatibility evidence.

## Next Batch

QMDB-P1-B05 is not ready. Provision or select PHP 8.5 with JSON and Mbstring, run normal Composer installation, the locked quality suite, real CLI verification, and the full real HTTP matrix; repair any failures before changing B04 to complete.

## Final acceptance addendum — 2026-08-26

The historical environment limitation above has been resolved. B04 passed the locked PHP 8.5 quality suite and the
compiled-container, module, command/query, event-dispatch, CLI, and HTTP acceptance coverage recorded in
[the P1 verification report](../../closeout/p1/02-P1-executable-verification-report.md). Batch status is `COMPLETE`.
