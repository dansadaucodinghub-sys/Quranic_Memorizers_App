# QMDB Project State

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Source Baseline | QMDB-BL-001 |
| Frozen Baseline | QMDB-P0-FRZ-001 |
| Document Version | 2.1.0 |
| Status | P0 COMPLETE; P1 BLOCKED; QMDB-P1-B01 INCOMPLETE |
| Current Phase | P1 — Engineering and Repository Foundation |
| Current Batch | QMDB-P1-B01 — Core PHP Repository and Runtime Foundation |
| Last Updated | 2026-08-24 |
| Document Owner Role | Product, Architecture and Engineering Governance |
| Approval Status | QMDB-P1-B01 source foundation implemented; completion and QMDB-P1-B02 readiness blocked pending mandatory PHP 8.5 validation |

## Purpose

Record the repository-visible phase/batch state, frozen counts, executable implementation evidence, controlled decisions and risks, validation blockers, and exact next action.

## Scope

QMDB-P0-B01 through QMDB-P0-CLOSE remain complete as documentation-as-code under the frozen baseline. QMDB-P1-B01 has created the executable Composer, metadata, runtime-validation, CLI/HTTP smoke and engineering-quality source foundation. The batch is not complete because the active PHP 8.2.12 runtime cannot execute the mandatory PHP 8.5 validation suite. No executable SQL/domain migration, production infrastructure or domain module has been implemented. P1 implementation is not production or national-rollout approval.

## Authoritative transition

| Field | Current value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Source Baseline | QMDB-BL-001 |
| Frozen Baseline | QMDB-P0-FRZ-001 |
| Completed Phase | P0 — Product Constitution and System Requirements |
| Completed Batches | QMDB-P0-B01; QMDB-P0-B02; QMDB-P0-B03; QMDB-P0-B04; QMDB-P0-CLOSE |
| Phase P0 Status | COMPLETE |
| Current Phase | P1 — Engineering and Repository Foundation |
| Current Batch | QMDB-P1-B01 — Core PHP Repository and Runtime Foundation |
| Phase P1 Status | BLOCKED |
| Batch Status | INCOMPLETE |
| Implementation Status | BLOCKED |
| Next Batch | QMDB-P1-B02 — NOT READY / NOT AUTHORIZED |
| Next Action | Resolve the listed QMDB-P1-B01 blockers |
| Transition Date | 2026-08-24 |
| Approval Basis | Executable source and diagnostics exist, but the frozen Definition of Done prohibits completion without conforming runtime evidence |

## Controlled baseline counts

| Measure | Count |
| --- | ---: |
| Repository documentation files under `docs/` | 91 |
| Markdown files under `docs/` | 88 |
| YAML files under `docs/` | 3 |
| Frozen baseline files | 82 |
| Excluded dynamic governance files | 8 |
| P0 requirements | 263 |
| P0-B01 baseline requirements | 68 |
| P0-B02 functional requirements | 113 |
| P0-B03 non-functional requirements | 50 |
| P0-B04 data requirements | 32 |
| Use cases | 65 |
| User journeys | 24 |
| State machines | 29 |
| Acceptance scenarios | 111 |
| Threats | 46 |
| Controls | 30 |
| Frozen P0 risks | 63 |
| Current risks | 64 |
| Logical tables | 250 |
| Corrections | 1 |
| Historical decision records | 70 |
| Resolved decisions | 5 |
| Open decisions | 65 |
| Deferred decisions | 65 |
| Decisions blocking QMDB-P1-B01 | 0 |
| Environment risks blocking QMDB-P1-B01 | 1 |
| Resolved closeout gaps | 8 |
| Remaining deferred gaps | 4 |

## QMDB-P1-B01 implementation counts

| Measure | Count/status |
| --- | ---: |
| Files created | 27 |
| Existing dynamic files updated | 2 |
| Production PHP files | 12 |
| Test files | 5 |
| Unit tests defined | 9 |
| Integration tests defined | 20 |
| Architecture tests defined | 12 |
| Total tests defined | 41 |
| Composer third-party runtime dependencies | 0 |
| Composer platform requirements | 3 |
| Composer direct development dependencies | 3 |
| Composer quality commands | 7 |
| Static-analysis failures resolved | 10 |
| Remaining environment limitations | 2 |
| Executable domain tables/migrations | 0 |
| Domain modules implemented | 0 |

## Application foundation state

- Composer project `quran-memorizer/qmdb` requires PHP `^8.5`, JSON and Mbstring; the real lock file resolves stable PHPUnit 13.3.1, PHPStan 2.2.9 and PHP_CodeSniffer 4.0.4.
- ADR-054 namespace mappings are implemented as `Qmdb\` to `src/` and `Qmdb\Tests\` to `tests/`.
- Immutable application metadata records QMDB-P0-FRZ-001, P1, QMDB-P1-B01 and version 0.1.0-dev.
- Runtime discovery, pure evaluation, structured violations and CLI-safe formatting are implemented without environment/configuration loading.
- The CLI supports `app:about`, `help`, `--help` and `-h`, plus shared exit codes 0, 1 and 64.
- The HTTP object returns deterministic safe JSON, immutable status/headers/body, `no-store`, `nosniff`, and generic 500 content.
- Entry points guard runtime compatibility before Composer autoloading so an unsupported platform does not leak Composer paths or non-JSON public errors.
- No full-stack framework, database, router, container, secret loader, authentication code, domain module, worker, view/localization layer, CI or Docker configuration is present.

## Validation state

| Gate | Result |
| --- | --- |
| P0 frozen baseline preflight and final recheck | PASS — 82 entries, 0 mismatches in both checks |
| Composer validation | PASS |
| Locked dependency audit | PASS — no advisories reported |
| Strict PSR-4 generation | PASS |
| PHP syntax diagnostic | PASS — 17 files on available PHP 8.2.12 |
| PSR-12 diagnostic | PASS — 17/17 files; non-conforming runtime diagnostic only |
| PHPStan maximum-level diagnostic | PASS after resolving 10 findings; non-conforming runtime diagnostic only |
| Normal Composer install | BLOCKED — PHP 8.2.12 does not satisfy `^8.5` |
| PHPUnit unit suite | BLOCKED before execution |
| PHPUnit integration suite | BLOCKED before execution |
| PHPUnit architecture suite | BLOCKED before execution |
| Combined Composer quality gate | BLOCKED at platform enforcement |
| Successful CLI `app:about` smoke | BLOCKED; unsupported-runtime failure path passes safely |
| HTTP object success smoke | PASS with injected deterministic PHP 8.5 evaluator inputs; diagnostic only |
| Real HTTP 200 server smoke | BLOCKED; unsupported-runtime generic 500 path and server cleanup pass |
| Git status/diff validation | BLOCKED — directory is not a Git working tree |
| QMDB-P1-B01 completion | INCOMPLETE |

No test or gate blocked by PHP 8.5 availability is represented as passed. No PHPUnit assertion count is recorded because PHPUnit did not execute.

## Decision state

- ADR-001 through ADR-020 remain locked and unchanged in meaning/status.
- ADR-021 through ADR-041 and ADR-045 through ADR-055 remain approved technical conventions for P1 implementation.
- ADR-042 through ADR-044 remain proposed/deferred for qualified Arabic, identity, security and privacy validation.
- OD-030, OD-047, OD-048, OD-056 and OD-057 remain resolved.
- Sixty-five open decisions retain their owners, gates and conservative assumptions.
- No open decision is `BLOCKS_P1_B01`; the active blocker is the newly recorded execution-environment risk.
- The frozen decision register was not modified during B01.

## Risk state

The 63 P0 risks remain open. QMDB-RSK-064 is added to the dynamic risk register because the active PHP runtime is below ADR-003 and prevents mandatory completion evidence. QMDB-RSK-064 is `BLOCKS_P1_B01 = YES` and remains open until an approved PHP 8.5 environment passes normal Composer install, the complete quality suite, PHPUnit, successful CLI/HTTP smokes and final integrity checks. No risk is accepted or falsely closed.

## Baseline integrity

The [freeze manifest](../closeout/qmdb-p0-baseline-freeze.yaml) continues to govern 82 lexicographically ordered frozen paths with lowercase SHA-256 values. The B01 implementation modifies only new executable/report files and excluded dynamic governance files. Any semantic frozen-file change still requires the approved change-control process and checksum regeneration.

## Implementation evidence

1. [QMDB-P1-B01 implementation report](../implementation/reports/QMDB-P1-B01-implementation-report.md)
2. Root `composer.json` and real `composer.lock`
3. Root `README.md`
4. `src/Bootstrap/` executable foundation
5. `bin/console` and `public/index.php`
6. Unit, integration and architecture suites under `tests/`
7. PHPUnit, PHPStan and PHP_CodeSniffer configuration
8. [P1 engineering foundation backlog](../implementation/P1-engineering-foundation-backlog.md)
9. [Definition of Ready and Done](../implementation/definition-of-ready-and-done.md)
10. [QMDB-P1-B01 implementation prompt](../implementation/prompts/QMDB-P1-B01-core-php-repository-and-runtime-foundation.md)

## Blockers

1. The only active PHP CLI is 8.2.12; the frozen baseline requires PHP 8.5.
2. The supplied directory has no Git metadata, so working-tree and diff gates cannot run.

The Docker client is installed but its Linux engine is not available, and WSL did not expose a usable alternate runtime. No runtime, system package or container image was installed as part of this batch.

## Exact next action

Provision or select an approved PHP 8.5 CLI with JSON and Mbstring, ensure the repository is available as a Git working tree when Git evidence is mandatory, then rerun normal Composer install, every quality command, all PHPUnit suites, the successful CLI/HTTP smokes, Git checks and the frozen-manifest check. Repair any executable defect found. Only after every gate passes may QMDB-P1-B01 be marked COMPLETE and QMDB-P1-B02 become ready.

Batch Status:
INCOMPLETE

Implementation Status:
BLOCKED

Next Action:
Resolve the listed QMDB-P1-B01 blockers
