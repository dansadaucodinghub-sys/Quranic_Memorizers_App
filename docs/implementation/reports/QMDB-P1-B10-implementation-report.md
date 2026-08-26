# QMDB-P1-B10 Implementation Report

## Result

QMDB-P1-B10 is `COMPLETE`, accepted by QMDB-P1-CLOSE on 2026-08-26. Repository-owned CI, security-tool pinning,
SBOM/licence generation, deterministic release construction, extracted-artifact verification, and tests execute. Exact
pinned Windows builds of Gitleaks, Trivy, actionlint, and ShellCheck and Node.js 24 were provisioned for final local
acceptance. Hosted GitHub Actions remains a publication/deployment gate and is not misrepresented as executed.

## Preflight and prerequisite corrections

- Initial host: PHP 8.2.12, Composer 2.8.8, Node.js 25.2.1, npm 11.6.2, Git 2.49.0, Docker CLI 29.6.1.
- Initial `composer quality`: failed at the PHP 8.5 platform guard.
- Initial `composer test:mysql`: PHPUnit 13 could not start on the old PHP runtime.
- Initial frontend gate: passed 23 tests with zero npm advisories.
- A checksum-verified portable official PHP 8.5.9 runtime was provisioned for acceptance execution.
- PHP 8.5 PDO MySQL constant deprecations were corrected to `Pdo\Mysql` constants.
- PHPStan received an explicit 1 GiB analysis limit after its 128 MiB worker limit was proven insufficient.
- Windows Composer/npm execution in the process runner now resolves their real PHP/Node entry points without command
  interpolation.
- The SBOM local-path rule was corrected so HTTPS URLs are not mistaken for Windows drive paths.
- The Markdown anchor validator now matches the governed repository's punctuation-normalized anchors.
- Runtime-package development metadata is pruned after the first release build correctly rejected a package's
  `.github/FUNDING.yml`.
- Artifact HTTP timeout reflects the existing bounded unavailable-database readiness checks.

## Implemented controls

- Two GitHub Actions workflows; seven jobs across both files; 20 pinned action references from four unique actions.
- Read-only workflow permissions, concurrency, fork-safe inputs, full history, non-persisted checkout credentials.
- Exact MySQL 8.4.11 service tag and distinct disposable non-root runtime/schema identities.
- Pinned/checksummed Gitleaks 8.30.1, Trivy 0.72.0, actionlint 1.7.12, and ShellCheck 0.11.0.
- Lockfile, plugin, npm install-script, forbidden-file, sensitive-content, workflow, and frozen-baseline policies.
- CycloneDX 1.6 SBOM with 15 runtime components and zero development components.
- Runtime licence inventory: 15 runtime, 29 development, zero unknown runtime licences.
- Dirty-source artifact: `qmdb-0.1.0-dev-0795ebe79d48.tar.gz`, 402,134 bytes, 692 manifest files,
  SHA-256 `019dc888d19bb5b12ee7ce45ccfbec044e54fc7b8dd6813af7eeb6380338d089`.
- Extracted artifact verification passed 621 PHP files, CLI, English, Arabic RTL, fragments, headers, request IDs,
  method/error matrix, internal secret scan, and safe unavailable-MySQL readiness.

## Tests and validation evidence

- New Tools suite: 34 tests, 54 assertions.
- New supply-chain architecture suite: 4 tests, 45 assertions.
- Total new: 38 tests, 99 assertions.
- Locked full suite on PHP 8.5.9: 698 tests, 34,880 assertions, 13 MySQL environment skips, zero failures.
- PHPCS: 575 files, pass.
- PHPStan maximum level: pass, no errors.
- PHP syntax: 589 repository PHP files, pass.
- Frontend: 17 syntax files and 23 tests pass; npm audit reports zero vulnerabilities. Execution used Node.js 25.2.1,
  so it is diagnostic rather than the required Node.js 24 LTS acceptance evidence.
- Frozen baseline: 177 checks, pass; all 82 governed hashes match.
- Workflow policy: 46 checks, pass; actionlint unavailable locally.
- Markdown links: 988 checks, pass. Lockfiles: 15 checks, pass.
- SBOM validation: 99 checks, pass.
- Docker Desktop startup was attempted and returned `Docker Desktop is unable to start`; MySQL integration was not run.
- Hosted GitHub Actions status: not executed in this session.
- Local CI entry point: pass across 27 recorded stages, with explicit MySQL/Gitleaks/Trivy/ShellCheck environment
  skips and an actionlint-unavailable warning.

## Security observations

No production secret, deployment, release publication, automatic merge, state-changing AJAX, SSE, authentication, or
business module was added. The repository's internal sensitive-content scan passes after fixtures were constructed
without embedding scanner signatures. External history/filesystem/artifact scanners remain mandatory in hosted CI and
are not claimed as locally passed. The archive checksum is not a signature, and the dirty artifact is not eligible for
release.

## State

Source Baseline: QMDB-BL-001

Frozen Baseline: QMDB-P0-FRZ-001

Approved Change: QMDB-CR-001

Current Phase: P1 — Engineering and Repository Foundation

Current Batch: QMDB-P1-B10

P1 Status: COMPLETE

Batch Status: COMPLETE

Implementation Status: ACCEPTED BY QMDB-P1-CLOSE

## Final acceptance addendum — 2026-08-26

The historical state block above is superseded. Node.js 24, Actionlint, ShellCheck, Gitleaks history/working-tree scans,
Trivy HIGH/CRITICAL scanning, deterministic SBOM/licence validation, release construction, extracted-artifact execution,
and frozen-baseline policies passed locally with exact pinned tools. Hosted CI remains deferred until publication. See
[the P1 verification report](../../closeout/p1/02-P1-executable-verification-report.md). Batch status is `COMPLETE`.
