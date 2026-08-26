# QMDB-P1-B03 Implementation Report

## Batch Result

| Field | Actual result |
| --- | --- |
| Project | Qur’an Memorizer DB (QMDB) |
| Frozen Baseline | QMDB-P0-FRZ-001 |
| Phase | P1 — Engineering and Repository Foundation |
| Batch | QMDB-P1-B03 — HTTP Kernel, Routing, Request, Response, and Middleware |
| Status | COMPLETE — accepted by QMDB-P1-CLOSE on 2026-08-26 |
| Execution Date | 2026-08-24 |
| Implementation Result | The bounded HTTP source, route composition, safe entry point, tests and diagnostic verification are present; mandatory acceptance remains unavailable |
| Next Batch | QMDB-P1-B04 is NOT READY / NOT AUTHORIZED |

## Preflight

- Repository root: `C:\xampp\htdocs\Quranic_Memorizers_App`.
- Active tools: PHP 8.2.12, Composer 2.8.8 and Git 2.49.0.windows.1.
- Git worktree was clean at B03 start; the preceding commit was `0795ebe feat: add P1-B02 configuration foundation`.
- The frozen manifest passed before modification with 82 entries and zero SHA-256 mismatches.
- The B01 and B02 source/report/state evidence was inspected. Both prerequisite batches remain incomplete because the approved PHP 8.5 validation environment is unavailable.
- Initial `composer quality` passed validation, audit and strict autoload generation, then failed at the intentional PHP `^8.5` platform guard before PHPCS.
- Work continued only for B03 tasks unaffected by the known environment and prerequisite blockers, as permitted by the frozen Definition of Ready/Done. No completion claim is made.

## Prerequisite Corrections

No prerequisite defect was changed to bypass the environmental blocker. B02 configuration, secret, clock and identifier behavior was preserved. The legacy B01/B02 non-PSR HTTP bootstrap response/application and its dedicated integration test were removed after the PSR-7/PSR-15 runtime replaced that internal path; configuration architecture coverage was redirected to the production system-about controller.

## Files Created

Sixty files are created when this report is included:

- `routes/web.php`.
- `src/Bootstrap/Http/HttpRuntime.php`.
- 34 production files under `src/Shared/Http/` covering contracts, controllers, kernel, messages, middleware, request creation/validation, response emission and routing.
- 18 test classes: 11 unit, 6 integration and 1 architecture.
- 5 focused HTTP test-support classes under `tests/Support/Http/`.
- `docs/implementation/reports/QMDB-P1-B03-implementation-report.md`.

## Files Updated

- `README.md` — documents the B03 scope, routes and response semantics.
- `composer.json` and `composer.lock` — add only the six approved HTTP packages.
- `phpcs.xml.dist` and `phpstan.neon.dist` — include production route definitions in source-quality scope.
- `public/index.php` — delegates through the composed HTTP runtime while retaining a pre-autoload, production-safe unsupported-runtime fallback.
- `src/Bootstrap/ApplicationFactory.php` — deterministically composes the complete HTTP runtime.
- `src/Bootstrap/ApplicationMetadata.php` — identifies QMDB-P1-B03.
- `docs/project/project-state.md` and `docs/project/risk-register.md` — record truthful blocked state and QMDB-RSK-066.
- Existing metadata, console, application-factory and architecture tests were updated for the B03 batch and HTTP boundary.
- Removed as superseded: `src/Bootstrap/Http/BootstrapHttpApplication.php`, `src/Bootstrap/Http/BootstrapHttpResponse.php` and `tests/Integration/BootstrapHttpApplicationTest.php`.

No frozen P0 file, decision-register entry or open-decision entry was changed.

## Dependencies Added

| Package | Resolved version | Purpose |
| --- | --- | --- |
| `psr/http-message` | 2.0 | PSR-7 request/response contracts |
| `psr/http-factory` | 1.1.0 | PSR-17 factory contracts |
| `psr/http-server-handler` | 1.0.2 | PSR-15 request-handler contract |
| `psr/http-server-middleware` | 1.0.2 | PSR-15 middleware contract |
| `nyholm/psr7` | 1.8.2 | PSR-7/PSR-17 implementation |
| `nyholm/psr7-server` | 1.1.0 | Trusted SAPI-to-PSR-7 request construction |

No router, full-stack framework, dependency-injection container or response-emitter package was added. Composer resolved and wrote the lock using `--ignore-platform-req=php` only because the local interpreter is below the immutable baseline; committed configuration still requires PHP `^8.5` and normal Composer autoloading fails closed on PHP 8.2.

## Executable HTTP Foundation

- `NativeServerRequestFactory` is the sole source boundary that reads SAPI request state. It strips forwarded metadata pending a later trusted-proxy policy.
- `RequestTargetValidator` rejects malformed percent encoding, encoded slash/backslash, literal backslash, controls, NUL, dot segments, invalid UTF-8, missing leading slash and unsupported target forms before matching.
- Immutable route definitions enforce conservative names/patterns, unique methods, unique names and deterministic ambiguity rejection.
- The router implements static/specific precedence, parameter extraction, single decoding, automatic GET-to-HEAD fallback, explicit HEAD precedence, automatic OPTIONS and stable `Allow` ordering.
- The PSR-15 pipeline uses immutable request-local dispatch state; the reusable kernel has no shared cursor.
- Controllers receive PSR-7 server requests, return PSR-7 responses and use response factories without SAPI emission.
- JSON and problem-details responses use deterministic fields, UTF-8, `no-store` and `nosniff`; required safe headers cannot be overridden by caller input.
- Unexpected throwables cross one outer exception boundary and produce generic `500 INTERNAL_SERVER_ERROR` output without message, trace, path, configuration or secret data.
- The SAPI emitter validates header lines, preserves multiple values, emits bounded 8192-byte chunks, rewinds seekable bodies, restores their cursor best-effort and suppresses HEAD/1xx/204/304 bodies.
- Both normal and pre-autoload SAPI paths remove PHP's `X-Powered-By` disclosure header, and the emitter drops any response attempt to reintroduce it.
- Production routes are `GET /`, `GET /health/live`, `GET /health/ready` and `GET /api/v1/system/about`.

## Tests Added

| Suite | New test classes | New test methods | Repository total methods | Execution result |
| --- | ---: | ---: | ---: | --- |
| Unit | 11 | 48 | 123 | BLOCKED before PHPUnit execution |
| Integration | 6 | 20 | 43 | BLOCKED before PHPUnit execution |
| Architecture | 1 | 14 | 42 | BLOCKED before PHPUnit execution |
| Total | 18 | 82 | 208 | No PHPUnit assertion count claimed |

Data providers expand the method count into additional route grammar, method and request-target cases. Security-focused coverage is distributed across unit, integration and architecture suites and includes encoded separators, invalid encoding/UTF-8, dot segments, route-regex safety, ambiguity rejection, generic errors, header injection, forwarded/method-override non-trust, query isolation, single decoding, middleware state isolation, HEAD suppression and OPTIONS non-dispatch behavior.

## Commands Run

| Command or check | Exit | Actual result |
| --- | ---: | --- |
| `php -v` | 0 | PHP 8.2.12; below frozen PHP 8.5 baseline |
| `composer --version` | 0 | Composer 2.8.8 |
| `git --version` | 0 | Git 2.49.0.windows.1 |
| Git status/diff preflight | 0 | Clean worktree before B03 |
| Frozen-manifest SHA-256 preflight | 0 | 82 entries; zero mismatches |
| Initial `composer quality` | 1 | Validation, audit and strict autoload passed; platform guard stopped PHPCS |
| `composer require ... --ignore-platform-req=php --no-scripts` | 0 | Six approved packages resolved, installed and locked; no advisories |
| PHP lint over project PHP | 0 | All inspected PHP files parse on PHP 8.2.12 |
| `composer validate --strict` | 0 | Composer configuration and lock are valid |
| `composer audit --locked --no-interaction` | 0 | No security vulnerability advisories found |
| Diagnostic strict autoload with PHP-only platform override | 0 | 1,977 classes generated; PSR-4 strict check passed |
| Direct PHPCS diagnostic | 0 | 107/107 files pass PSR-12 |
| Direct PHPStan diagnostic | 0 | Maximum-level analysis reports no errors |
| `php vendor/bin/phpunit --configuration phpunit.xml.dist` | 1 | PHPUnit requires PHP 8.4.1 or newer and cannot start on PHP 8.2.12 |
| Targeted HTTP object smoke | 0 | Four 200 routes plus HEAD, 204 OPTIONS, 400, 404 and 405 behaved as specified |
| Final `composer quality` | 1 | Validate, audit and autoload passed; normal PHP 8.5 platform guard stopped PHPCS with exit 255 |
| `composer check-platform-reqs --lock` | 1 | Available extensions pass; PHP requirement fails on 8.2.12 |
| `php bin/console app:about` | 1 | Safe `PHP_VERSION_TOO_LOW` rejection |
| Built-in server on `127.0.0.1:18083` | Request 0; acceptance blocked | `/` and `/health/live` returned safe HTTP 500 `BOOTSTRAP_FAILURE`; an observed `X-Powered-By` disclosure was removed and the corrected response was reverified; server stopped |
| Final frozen-manifest SHA-256 check | 0 | 82 entries; zero mismatches |
| `git diff --check` | 0 | No whitespace errors |

## Validation Results

Source syntax, Composer validation/audit, strict PSR-4 diagnostics, PSR-12, maximum-level PHPStan, targeted object behavior, safe unsupported-runtime HTTP behavior, Git diff checks and frozen-baseline integrity pass. The locked PHPUnit suites, normal combined quality gate, successful CLI path and real PHP 8.5 HTTP matrix are unavailable and therefore do not pass.

## Security Observations

- Public problem responses expose stable status/title/code fields only.
- The invalid raw target, route list, controller names, exception details, filesystem paths, configuration values, environment/debug state, dependency versions and secret-like values are absent from public responses.
- Route definitions are application-owned and escaped before regular-expression compilation; client input is never compiled as a pattern.
- Forwarded headers and method-override headers do not control routing or effective methods.
- Query strings do not influence route selection, and route parameters are decoded exactly once after encoded separator rejection.
- Controllers, kernel and middleware do not emit output, access databases or read SAPI globals. Only the named native request factory and SAPI emitter have those narrow boundary responsibilities.
- QMDB-RSK-066 remains open until conforming tests and real HTTP verification complete.

## Known Limitations

1. QMDB-P1-B01 and QMDB-P1-B02 are incomplete prerequisites.
2. The active PHP CLI is 8.2.12; the approved baseline is PHP 8.5.
3. PHPUnit 13.3.1 cannot execute on the active runtime.
4. Successful real CLI/HTTP behavior cannot cross the intentional runtime guard, so only injected object behavior and the generic unsupported-runtime server path were verified.
5. Readiness intentionally checks only successful foundation construction; database, Redis and external-service health belong to later batches.
6. Authentication, authorization, sessions, CSRF, CORS, trusted proxies, request identifiers and structured logging are outside B03.

## Project State

Source Baseline: QMDB-BL-001

Frozen Baseline: QMDB-P0-FRZ-001

Current Phase: P1 — Engineering and Repository Foundation

Current Batch: QMDB-P1-B03

P1 Status: COMPLETE

Batch Status: COMPLETE

Implementation Status: ACCEPTED BY QMDB-P1-CLOSE

## Final acceptance addendum — 2026-08-26

The state block above records the original B03 execution checkpoint and is superseded by this addendum. The HTTP
kernel, routing, request/response, middleware, negative-path, and real-socket matrix passed on PHP 8.5 during closeout.
See [the P1 verification report](../../closeout/p1/02-P1-executable-verification-report.md). Batch status is `COMPLETE`.
