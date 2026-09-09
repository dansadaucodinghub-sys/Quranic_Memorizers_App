# QMDB-TRIVY-DB-RECOVERY-001 Report

## Recovery Result

**RECOVERY COMPLETE.** The repaired P3 release artifact passed clean-commit verification at
`06450fae88f92a4ea90c42e0321a40e1b7377939`. The repository no longer allows an artifact scan to initiate an
uncontrolled Trivy DB download or to use an unvalidated cache.

## Starting State

At `37daad4c61a4ef8bfa4900cb5c5e762577eecc38`, the P3 source and freeze checks were present, but release verification
failed at the inline Trivy invocation. The old report was from 2 September and could not establish a current scan.

## Root Cause

The release verifier and platform wrappers called Trivy directly without a repository-owned cache path, refresh phase,
metadata validation, registry fallback, atomic publication, or differentiated diagnostics. A transient OCI database
acquisition failure therefore appeared as the generic `Trivy artifact scan failed` message.

## Trivy Database Recovery

The pinned `trivy.exe` 0.72.0 successfully acquired DB schema `2` from the approved mirror on 9 September 2026.
Metadata records `UpdatedAt` `2026-09-09T07:06:00Z` and `NextUpdate` `2026-09-10T07:06:00Z`. The controlled scanner
stores the validated database only in ignored `var/cache/trivy`; the cache is never committed or release-packaged.

## Scan Result

The extracted P3 release artifact was scanned with the validated database and `--skip-db-update`. The controlled
wrapper exited `0`; its JSON report contains package inventory only and no HIGH/CRITICAL vulnerability, secret, or
misconfiguration finding. This is direct local evidence, not a substitute for the final clean-commit release verifier.

## Repository Changes

- Added validated Trivy DB acquisition, approved-registry fallback, atomic publication, explicit error categories and
  report parsing under `tools/Security/`.
- Routed Windows, Linux and release-artifact scans through the same scanner.
- Ignored and engineering-freeze-excluded `var/cache/`.
- Added fake-process regression tests for fresh cache, fallback, malformed/stale/future/schema/empty cache, failed
  candidate non-replacement, Windows paths containing spaces, scan report validity and HIGH/CRITICAL findings.

## Git Governance

The untracked advisory lock `.git/qmdb-trivy-db-recovery.lock` records branch `main`, expected head
`37daad4c61a4ef8bfa4900cb5c5e762577eecc38`, start time and recovery identity. It is excluded from commits and must
remain until final release/freeze validation finishes.

## Validation

Focused recovery checks pass: PHPUnit `22 tests, 147 assertions`; PHPCS with warnings disabled passes; PHPStan reports
no errors. The real controlled extracted-artifact Trivy scan passes with exit `0`. The clean release verifier reports
3,350 files, 3,120 PHP files linted, Gitleaks and Trivy pass, CLI/HTTP pass, MySQL readiness `200`, archive SHA-256
`cc8d9a640664cf05bab0fb9df80c9f1b908129a35ec3b9c01ccf1c51a6e3fba5`, and `release_eligible=true`.

## P3 Final State

P3 release verification is complete. P0 and P2 frozen-baseline checks pass; the Engineering and P3 manifests are
forward-refreshed after this evidence commit, without rewriting the P0 or P2 historical manifests.

## P4-B01 Resumption

P4-B01 may begin only after the recorded Engineering/P3 freeze refresh and verification. P4-B02 remains out of scope.

## Deferred Operational Evidence

Hosted CI execution, independent penetration testing, production registry availability monitoring, and production
release execution remain external evidence. They are not represented as completed locally.

## Residual Risks

Approved OCI registries can still suffer a simultaneous outage; this now fails closed with category `20`. A stale,
malformed, schema-incompatible, unreadable, or future-dated cache fails closed rather than creating a false pass.

## Final Project State

The authoritative transition is P3 `COMPLETE / FROZEN` after the final freeze commit. P4-B01 is authorized next;
P4-B02 is not authorized.
