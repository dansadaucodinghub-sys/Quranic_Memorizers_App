# Qur’an Memorizer DB

QMDB is the governed Qur’an Memorizer Database platform. This repository contains the bounded QMDB-P1-B02 Core PHP foundation: immutable application metadata, deterministic runtime validation, typed application configuration, safe local environment loading, secret-value boundaries, a UTC clock, and secure runtime-scoped identifiers.

## Project identity

- Application: Qur’an Memorizer DB
- Code: QMDB
- Source baseline: QMDB-BL-001
- Frozen baseline: QMDB-P0-FRZ-001
- Phase: P1 — Engineering and Repository Foundation
- Batch: QMDB-P1-B02
- Development version: 0.1.0-dev

## Prerequisites

- PHP 8.5 or newer
- PHP extensions: JSON and Mbstring
- Composer 2
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
```

`composer quality` runs validation, locked dependency audit, strict PSR-4 validation, PSR-12 checks, maximum-level PHPStan analysis, and all PHPUnit suites in that order.

## CLI smoke command

```powershell
php bin/console app:about
php bin/console help
```

`app:about` displays only safe metadata and typed configuration information. The CLI returns exit code `0` on success, `1` when runtime or configuration bootstrap requirements fail, and `64` for invalid usage.

## HTTP smoke command

```powershell
php -S 127.0.0.1:8080 -t public
```

Then request `http://127.0.0.1:8080/`. The success response is deterministic JSON with `no-store` and `nosniff` headers. Public output does not include the environment, debug state, timezone, configuration source, PHP version, paths, or secrets. Stop the development server after verification.

## Current implementation scope

QMDB-P1-B02 includes typed configuration, controlled local environment loading, an environment-backed bootstrap secrets provider, explicit secret-value protection, a UTC system clock, cryptographically secure runtime opaque identifiers, and safe CLI and HTTP bootstrap integration.

It does not yet include MySQL, sessions, authentication, Redis, cloud secrets providers, HTTP routing or middleware, dependency injection, business modules, workers, view rendering, localization, CI, deployment, or Docker configuration.

## Current implementation status

The B02 source and tests are present, but batch completion requires the full executable validation suite on a conforming PHP 8.5 environment. Consult `docs/project/project-state.md` and `docs/implementation/reports/QMDB-P1-B02-implementation-report.md` for the current verified state and remaining blocker.
