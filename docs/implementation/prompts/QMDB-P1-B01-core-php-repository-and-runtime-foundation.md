# QMDB-P1-B01 — Core PHP Repository and Runtime Foundation

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Source Baseline | QMDB-BL-001 |
| Freeze ID | QMDB-P0-FRZ-001 |
| Batch ID | QMDB-P1-B01 |
| Document Title | Core PHP Repository and Runtime Foundation Execution Prompt |
| Document Version | 1.0.0 |
| Document Status | APPROVED / READY TO EXECUTE |
| Document Owner Role | Architecture and Engineering Governance |
| Last Updated | 2026-08-24 |
| Approval Status | Authorized as the next executable batch under successful P0 readiness validation |
| Related Documents | [P1 backlog](../P1-engineering-foundation-backlog.md); [Definition of Ready and Done](../definition-of-ready-and-done.md); [freeze manifest](../../closeout/qmdb-p0-baseline-freeze.yaml); [project state](../../project/project-state.md) |

## Purpose

Direct a repository-aware coding agent to implement and verify the executable Core PHP/Composer foundation for QMDB-P1-B01.

## Scope

Only the bounded repository/runtime foundation described here is authorized. Frozen requirements and locked decisions are authoritative. This prompt is approved implementation guidance; controlled open decisions remain deferred unless their register classification changes. No open decision blocks this batch, and no later P1/domain batch is included.

## Execution directive

Work directly in the current QMDB repository. Act as a senior Core PHP engineer, application architect, secure-coding engineer, test engineer, and repository-aware autonomous agent. Research the current repository, check its real state and dependencies, reason about the frozen constraints, plan the bounded change, implement it, and verify it before reporting completion.

This is the first executable implementation batch. Do not merely propose code or return a tutorial. Preserve all existing documentation and unrelated user changes. Do not rewrite frozen requirements to make an implementation easier.

## Batch identity

| Field | Value |
| --- | --- |
| Project | Qur’an Memorizer DB (QMDB) |
| Baseline | QMDB-BL-001 |
| Freeze | QMDB-P0-FRZ-001 |
| Phase | P1 — Engineering and Repository Foundation |
| Batch | QMDB-P1-B01 |
| Objective | Create the executable Core PHP repository and runtime foundation |
| Starting state | P0 complete; P1-B01 ready; no domain implementation exists |
| Target state | P1-B01 complete and ready for QMDB-P1-B02 |

## Mandatory source baseline

Before modifying any file, read these five files completely:

1. `docs/closeout/qmdb-p0-baseline-freeze.yaml`
2. `docs/implementation/P1-engineering-foundation-backlog.md`
3. `docs/implementation/definition-of-ready-and-done.md`
4. `docs/data/03-mysql-schema-conventions.md`
5. `docs/project/project-state.md`

Use the freeze manifest and the batch-specific references it identifies. Load another P0 file only when necessary to resolve a concrete B01 contract; do not indiscriminately load every P0 document. Verify frozen-file hashes before implementation. If a required source is missing, a frozen hash fails, or repository state contradicts this prompt, stop the affected work and report the exact blocker rather than inventing a replacement.

## Preflight

1. Inspect repository files, Git status if Git metadata exists, installed PHP/Composer versions, PHP extensions and current executable artifacts.
2. Confirm PHP can meet the `^8.5` runtime contract. Do not falsify compatibility if the available runtime is older.
3. Preserve user-owned changes and avoid destructive Git commands.
4. Confirm that B01 has no domain migration, identity, competition, social, scoring, media, notification, search or production-deployment scope.
5. Produce a short internal plan mapping each deliverable to implementation and verification. Continue without unnecessary questions when the repository supplies the answer.

## Architecture contract

- Use Core PHP compatible with PHP 8.5; do not introduce Laravel, Symfony full stack, Slim, Laminas MVC, or another full-stack/microframework.
- Every PHP source file must use `declare(strict_types=1);` unless PHP syntax makes it impossible.
- Use Composer PSR-4 mappings `Qmdb\` to `src/` and `Qmdb\Tests\` to `tests/`.
- Use `public/index.php` as the HTTP front controller and `bin/console` as the CLI entry point.
- Establish `src/Bootstrap/` and `src/Shared/` with small, cohesive runtime/bootstrap abstractions. Do not create empty decorative layers.
- Preserve the future module convention `src/Modules/<Module>/{Domain,Application,Infrastructure,Presentation}` without creating business modules in B01.
- Keep dependencies minimal, explicit and justified. Prefer PHP and small development tools over runtime packages when the standard library is sufficient.
- Use production-safe defaults: debug off by default, generic external errors, no stack traces or environment dumps in HTTP output, no secret logging, bounded input/runtime behavior, and deterministic exit codes.
- Do not connect to or mutate a database in B01. Do not create SQL, a domain schema, migration classes or seed files.
- Do not implement temporary fake authentication, authorization, tenancy or business behavior.

## Mandatory executable output

Create at least the following, adapting only where the frozen conventions require an equivalent:

```text
composer.json
composer.lock
.editorconfig
.gitattributes
.gitignore

public/index.php
bin/console

src/Bootstrap/
src/Shared/

tests/Unit/
tests/Integration/
tests/Architecture/

phpunit.xml or phpunit.xml.dist
phpstan.neon or an approved equivalent

README.md updates
docs/project/project-state.md updates
```

The directories must contain purposeful executable source/tests and must not be empty placeholders. A reasonable minimal design includes:

- a runtime version/extension guard;
- an application bootstrap that can be invoked by both HTTP and CLI;
- a small immutable application identity/about representation;
- a deterministic CLI command `app:about` with documented success and unknown-command behavior;
- a deterministic HTTP smoke response with an explicit content type, safe status, and no environment/secret disclosure;
- a safe top-level exception boundary for both entry points; and
- an architecture test that verifies namespace/path and forbidden business-module conditions.

Do not add a general-purpose service container, event bus, ORM, template engine or router unless the executable B01 behavior truly requires it. These are later-batch decisions.

## Composer contract

Create valid package metadata containing:

- a descriptive package name, description, license declaration appropriate to the repository’s known state, and package type;
- a PHP constraint compatible with PHP 8.5;
- only required PHP extensions that the implemented runtime actually uses;
- minimal development dependencies for PHPUnit, PHPStan and PSR-12-compatible style enforcement;
- PSR-4 production and development autoload mappings;
- scripts for tests, static analysis, style check, strict Composer validation, security audit and a combined quality gate;
- deterministic relevant Composer configuration; and
- a committed `composer.lock` generated from the actual dependency resolution.

Do not install arbitrary packages. Document the concrete purpose of every direct dependency in the root README or Composer metadata. Do not suppress security advisories or loosen quality rules merely to obtain a green result.

## Runtime behavior

### CLI

`php bin/console app:about` must execute without network or database access, identify the application and runtime safely, return exit code 0, and provide deterministic fields suitable for a smoke test. Unknown commands must return a nonzero exit code and concise usage without a stack trace.

### HTTP

The front controller must boot the same runtime path and return a deterministic, production-safe smoke response. Use an appropriate HTTP status and media type. It must not disclose filesystem paths, environment variables, package inventory, stack traces or secrets. The PHP development server is for local verification only and must not be described as production hosting.

### Failure handling

Unsupported PHP/runtime prerequisites must fail early with clear operator-facing CLI diagnostics and safe HTTP output. Do not catch and silently ignore bootstrap failures.

## Required tests

Add executable PHPUnit tests that cover at least:

1. the deterministic runtime/application identity result;
2. CLI `app:about` success and output contract;
3. CLI unknown-command failure and exit code;
4. HTTP/front-controller smoke behavior without starting a long-running server where a direct harness is sufficient;
5. production-safe failure/error output;
6. PSR-4 autoloadability and strict namespace/path mapping;
7. architecture boundaries, including absence of forbidden domain/module implementation; and
8. a negative runtime-prerequisite case through an injectable/testable guard rather than trying to change the real PHP runtime.

Tests must be deterministic, isolated and free from external services, real credentials and order dependence. Do not add empty or always-passing assertions.

## Documentation and state updates

Update the repository root `README.md` with concise prerequisites, setup, executable smoke commands, quality commands, directory responsibilities, dependency purposes and the explicit statement that no business/domain behavior exists yet.

Update `docs/project/project-state.md` only after all completion gates pass. Preserve historical P0 facts and record actual file/test results. The resulting state must be:

```text
Completed Phase:
P0

Current Phase:
P1 — Engineering and Repository Foundation

Completed Batch:
QMDB-P1-B01

Next Batch:
QMDB-P1-B02

Status:
READY FOR NEXT BATCH
```

If a mandatory validation cannot run or fails, do not record B01 as complete or ready for B02. Record the truthful limitation/blocker instead.

## Prohibited scope and shortcuts

- No domain database migrations or logical-schema implementation.
- No identity, tenancy, people, guardian, geography, organization, Qur’an-content, competition, registration, scheduling, judging, scoring, result, appeal, certificate, media, moderation, search, notification, reporting, public-portal or social module.
- No Docker, deployment platform, queue, cache, search service, object-storage provider, mail/SMS provider or observability vendor selection unless the repository already requires a tiny B01 compatibility file; do not infer such a requirement.
- No committed secret, `.env` with credentials, embedded API key, disabled TLS verification, permissive production debug, fake security control or broad exception suppression.
- No editing frozen P0 artifacts. If implementation reveals a semantic defect, invoke baseline change control.
- No TODOs, placeholder classes, empty directories, pseudo-code, truncated code or claiming commands passed without execution.
- Do not execute QMDB-P1-B02 or any later batch.

> A documentation-only response is a batch failure. QMDB-P1-B01 must produce executable PHP source, executable tests, Composer configuration, and a working CLI or HTTP smoke path.

## Validation commands

Run the commands supported by the actual environment, adapting path syntax without weakening the check:

```bash
php -v
php -m
composer --version
composer validate --strict
composer install
composer audit
composer dump-autoload --strict-psr
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/phpcs
php bin/console app:about
php bin/console unknown-command
php -S 127.0.0.1:8080 -t public
git diff --check
```

For the development-server check, start it only long enough to request the smoke path and verify status, headers and body, then stop the exact process you started. If Git metadata is absent, report `git diff --check` as unavailable instead of treating it as passed. Also run syntax checks across all project PHP files and the combined Composer quality script.

Report the exact command, exit status and relevant result. A server that boots is not proof that tests, static analysis, security audit or architecture rules pass.

## Completion gates

B01 is complete only if:

- all mandatory executable artifacts exist and contain real implementation;
- Composer metadata validates strictly and the lock file is current;
- PHP syntax, PHPUnit, PHPStan, style, PSR-4 and architecture gates pass;
- dependency audit passes under the approved policy or an actual advisory is reported as a blocker;
- CLI and HTTP smoke paths behave deterministically and safely;
- no domain implementation or migration was introduced;
- frozen files remain hash-valid;
- root README accurately explains execution and limitations;
- project state truthfully advances to B01 complete/B02 next; and
- modified/created files and all known limitations are fully reported.

## Required final response format

Return a concise, evidence-based report using exactly these top-level sections:

```text
Batch Result
Preflight
Files Created
Files Updated
Dependencies Added
Executable Features
Tests Added
Commands Run
Validation Results
Security Observations
Known Limitations
Project State
```

Under `Batch Result`, state `COMPLETE` only if every completion gate passed; otherwise state `INCOMPLETE` or `BLOCKED` and name the failing gate. Under `Commands Run`, distinguish passed, failed and unavailable commands. Under `Project State`, reproduce the actual ledger state and exact next action. Never report a check as passed unless it was executed successfully in this repository.

## Exact next action

Execute this prompt as QMDB-P1-B01 in a new implementation run. Do not execute it during QMDB-P0-CLOSE and do not combine it with QMDB-P1-B02.
