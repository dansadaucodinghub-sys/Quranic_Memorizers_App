# Qur’an Memorizer DB

QMDB is the governed Qur’an Memorizer Database platform. This repository currently contains the bounded QMDB-P1-B01 Core PHP runtime foundation: immutable application metadata, deterministic PHP runtime validation, and minimal testable HTTP and CLI smoke applications.

## Project identity

- Application: Qur’an Memorizer DB
- Code: QMDB
- Source baseline: QMDB-BL-001
- Frozen baseline: QMDB-P0-FRZ-001
- Phase: P1 — Engineering and Repository Foundation
- Batch: QMDB-P1-B01
- Development version: 0.1.0-dev

## Prerequisites

- PHP 8.5 or newer
- PHP extensions: JSON and Mbstring
- Composer 2
- Git for repository-state inspection

The PHP 8.5 requirement is frozen by ADR-003 and is enforced by both Composer and the application runtime validator. Do not bypass it for deployment or completion evidence.

## Installation

From the repository root, run:

```powershell
composer install
```

Composer installs the exact development dependency versions recorded in `composer.lock` and generates the PSR-4 autoloader.

## Quality commands

```powershell
composer validate --strict
composer security:audit
composer autoload:check
composer cs:check
composer analyse
composer test
composer quality
```

`composer quality` runs validation, locked dependency audit, strict PSR-4 validation, PSR-12 checks, maximum-level PHPStan analysis, and the PHPUnit suites in that order.

## CLI smoke command

```powershell
php bin/console app:about
php bin/console help
```

The CLI returns exit code `0` on success, `1` when runtime bootstrap requirements fail, and `64` for invalid usage.

## HTTP smoke command

```powershell
php -S 127.0.0.1:8080 -t public
```

Then request `http://127.0.0.1:8080/`. The success response is deterministic JSON with `no-store` and `nosniff` headers. Stop the development server after verification.

## Current scope

This batch intentionally implements only the repository/runtime foundation. It does not contain environment loading, database connectivity, migrations, routing, dependency injection, authentication, business modules, workers, view rendering, localization, CI, deployment, or Docker configuration.

## Security note

The public HTTP boundary emits a stable generic error and sends only the throwable type to PHP’s error channel. Source code does not read superglobals, execute shell commands, deserialize untrusted values, access a database, load secrets, or depend on a full-stack framework. Configuration and secret abstractions are deferred to QMDB-P1-B02 after B01 passes every required gate on PHP 8.5.

## Current implementation status

The source foundation is present, but batch completion requires the full executable suite on a conforming PHP 8.5 environment. Consult `docs/project/project-state.md` and the B01 implementation report for the current verified state.
