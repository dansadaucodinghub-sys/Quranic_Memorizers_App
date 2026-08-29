# QMDB CI, Build, Security, and Release-Artifact Standard

## Control identity

- Batch: QMDB-P1-B10
- Source baseline: QMDB-BL-001
- Frozen baseline: QMDB-P0-FRZ-001
- Approved change: QMDB-CR-001
- CI provider: GitHub Actions
- Runtime baselines: PHP 8.5, Node.js 24 LTS, MySQL 8.4.11

## Pipeline contract

The pull-request and push pipeline is read-only, requires no production secret, fetches complete history without
persisting checkout credentials, and uses only actions pinned to immutable 40-character commit SHAs. Required gates do
not use `continue-on-error`. `pull_request_target`, deployment, release publication, automatic merge, mutable action
tags, and mutable container tags are prohibited.

The main workflow separates repository policy, PHP quality, frontend quality, MySQL integration, security scanning,
and verified release-artifact construction. The artifact job depends on every other required job. The independent
manual/tag workflow builds and uploads evidence only; it does not publish a release or deploy the application.

## Dependency and tool integrity

Composer and npm install only from committed lockfiles. Composer plugins are denied unless explicitly approved.
Frontend installation uses `npm ci --ignore-scripts`; install scripts are not trusted by default. Dependabot may propose
reviewable lockfile updates, but automatic merge is absent.

`tools/security/tool-versions.json` pins Gitleaks, Trivy, actionlint, and ShellCheck. The Linux installer downloads fixed
release assets and verifies their SHA-256 values before extraction or execution. Gitleaks covers Git history, the
working tree, staging, and extracted artifacts. Trivy covers vulnerabilities, secrets, and misconfiguration. Scanner
findings fail the hosted gate; broad allowlists and vulnerability ignores are prohibited.

## Repository-owned verification

The `tools/ci` entry points validate repository paths, sensitive content, frozen hashes, workflow security, internal
Markdown links, generated lockfiles, and PHP syntax. `php tools/ci/run-local-ci.php` executes the same available gates
in deterministic order and writes `build/reports/local-ci.json`. `--ci` makes external security tools mandatory. Local
absence of MySQL or pinned Linux scanner binaries is recorded as skipped and cannot be used as completion evidence.

## SBOM and licences

The production SBOM is deterministic CycloneDX 1.6 JSON. It includes the application component, frozen-baseline ID,
approved change, source revision, source timestamp, and the 15 production Composer packages from `composer.lock`.
Composer development packages and npm development packages are excluded. The runtime licence inventory is generated
in JSON and Markdown; an unknown runtime licence blocks artifact construction. SHA-256 files accompany generated
inventories. A checksum provides integrity comparison, not identity, signing, or provenance attestation.

## Release construction

Release construction uses an isolated stage, `composer install --no-dev --classmap-authoritative`, platform checks,
and an explicit application allowlist. Development-only metadata carried by runtime packages is pruned. Tests, tools,
CI files, documentation, Node modules, frontend development metadata, environment files, private keys, dumps,
archives, symlinks, and development dependencies are rejected.

`SOURCE_DATE_EPOCH` is derived from the source commit. Archive entries are byte-sorted; UID/GID are zero; timestamps
and modes are normalized; the USTAR archive is generated without host-specific paths. The manifest records each file's
SHA-256, size, and mode plus source and governance metadata. A dirty source is truthfully marked non-release-eligible.

## Extracted-artifact verification

Verification checks the external archive checksum, safe extraction paths and types, exact manifest coverage, per-file
hashes/sizes/modes, required runtime files, file policy, SBOM, licence inventory, internal secret scan, Composer
platform requirements, every PHP file, CLI commands, English HTML, Arabic RTL HTML, negotiated fragments, HTTP method
and error behavior, security headers, request IDs, and readiness behavior. When disposable MySQL variables exist,
readiness must be healthy; without them, a safe non-disclosing `503` is required. Hosted CI additionally requires
Gitleaks and Trivy over the extracted artifact before upload.

## Operating commands

```powershell
composer install
npm ci --ignore-scripts
composer quality
composer test:mysql
php tools/ci/run-local-ci.php
php tools/sbom/generate-production-sbom.php
php tools/sbom/validate-sbom.php
php tools/sbom/generate-runtime-licences.php
php tools/build/build-release.php
php tools/build/verify-release.php
```

On Linux x86-64 CI, install scanners first with `bash tools/security/install-tools.sh`. Production credentials must
never be supplied to these workflows. Release publication, signing, attestations, and deployment require later,
separately approved controls.

## P2-B06 authorization regression gates

The repository and extracted release must include the `security.authorization` module, its three explicitly registered
migrations, its one explicitly registered production seed, the `security:authorization:verify` command, and the shared
security-event email templates. The release must exclude tests, account/workspace fixtures, assignments, private paths,
environment files, and secrets.

Required authorization evidence includes exact catalog counts and checksums; deny-by-default, unknown/wrong-scope and
assurance decisions; active account/workspace/membership/role/permission enforcement; safe generic 403 mapping; seed
idempotency and drift detection; rollback/reapply; cross-workspace relational denial; delegation subset checks;
transactional step-up/notification behavior; and independent-process duplicate-assignment, last-administrator,
last-owner, and grant-consumption races. The complete legacy MFA, session, recovery, tenant, frontend, scheduler,
security-scan, SBOM/licence, and artifact suites remain mandatory.

An intentionally pre-B06 schema must report generic `not_ready`. A fully migrated and seeded schema must pass both
readiness and `security:authorization:verify`. Missing MySQL or skipped concurrency tests are not acceptable B06
completion evidence.

MySQL integration fixtures intentionally exercise destructive schema lifecycles. When MySQL is configured, local CI
must therefore reset only the explicitly named `_test`/`_ci` database after the MySQL suite, reinstall the schema ledger,
apply all migrations and the governed seed, and pass `security:authorization:verify` before release verification. The
reset refuses non-test environments and database names and never drops a database. This prevents release readiness
from depending on fixture execution order or stale tables.

## P2-B07 Tenant Context regression gates

The release must include `tenancy.context`, its explicitly registered migration, four workspace routes, selection and
current-workspace views, Tenant Context JavaScript, tenant-bound job/cache/export contracts, and the
`tenancy:context:verify` command. Tests and synthetic account/workspace/membership data remain excluded.

Mandatory evidence covers composite account/workspace/membership integrity, all-or-none selection, positive context
version, authorized selection, stale-version denial, invalid-state clearing, cross-session isolation, cross-account
non-enumeration, tenant-scoped SQL, background revalidation, cache separation, CSRF, safe navigation, English/Arabic
accessibility, no client workspace authority, and existing identity/authorization regressions. Standalone
`composer test:mysql` uses the guarded lifecycle wrapper to restore migrations, the governed authorization seed,
authorization and Tenant Context verification, and schema verification after destructive fixtures. Local CI repeats
the guarded canonical rebuild and then explicitly runs authorization verification, Tenant Context verification, and
schema verification before security, SBOM, licence, and release stages.
