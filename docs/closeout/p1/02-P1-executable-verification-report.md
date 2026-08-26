# QMDB P1 Executable Verification Report

## Accepted environment

| Component | Version |
| --- | --- |
| PHP | 8.5.10 NTS x64 |
| Composer | 2.8.8 |
| Node.js | 24.19.0 |
| npm | 11.6.2 |
| MySQL | 8.4.11 Community Server |
| PHPUnit | 13.3.1 |
| actionlint | 1.7.12 |
| Gitleaks | 8.30.1 |
| ShellCheck | 0.11.0 |
| Trivy | 0.72.0 |

PHP and MySQL archives were fetched from their official distribution endpoints and checksum-verified before use. The
isolated runtimes did not replace XAMPP or modify its MariaDB service.

## Quality and policy gates

| Gate | Result |
| --- | --- |
| Composer strict validation, audit, platform and autoload | PASS |
| PHPCS | PASS |
| PHPStan maximum level | PASS |
| Locked PHPUnit suite with MySQL configured | PASS; no environment-driven MySQL skips |
| JavaScript syntax | PASS — 17 files |
| Frontend tests | PASS — 23 tests |
| npm audit | PASS — zero vulnerabilities |
| Repository policy | PASS |
| P0 frozen baseline | PASS — 82 entries and 177 checks |
| Workflow policy plus actionlint | PASS |
| Markdown links | PASS |
| Lockfiles | PASS |
| `git diff --check` | PASS |

## MySQL and schema gates

- All 13 dedicated MySQL integration cases pass.
- Schema metadata install and structural verification pass.
- Migration plan, apply, status, confirmed rollback, reapply, and final verification pass.
- Seed status and no-op seed execution pass.
- Scheduler two-connection claim, lease, reclaim, optimistic ownership, and terminal-state behavior pass.
- HTTP readiness returns 200 against the verified MySQL/schema state.

## Runtime smoke gates

- CLI: help, about, schema commands, scheduler run, worker once, and invalid-option exit behavior pass.
- HTTP: English, Arabic RTL, fragments, liveness, readiness, API, 404 and 405 pass over a real PHP socket.
- Request IDs, problem details, content types, security headers and absence of `X-Powered-By` pass.
- Release extraction: PHP lint, CLI, presentation, HTTP method matrix, internal secret scan and MySQL readiness pass.

## Security and supply-chain gates

| Gate | Result |
| --- | --- |
| Gitleaks full Git history | PASS — no leaks |
| Gitleaks governed working tree | PASS — no leaks |
| ShellCheck security scripts | PASS |
| Trivy filesystem | PASS — zero HIGH/CRITICAL vulnerabilities, secrets or misconfigurations |
| CycloneDX 1.6 SBOM | PASS — 15 runtime components and 99 validation checks |
| Runtime licence inventory | PASS — 15 runtime, 29 development, zero unknown/review runtime licences |
| Deterministic release build and verification | PASS |

Generated reports and release artifacts under `build/` are intentionally excluded from the engineering freeze.
