# QMDB-P1-CLOSE Engineering Closeout Report

## Outcome

`P1 COMPLETE — QMDB-P1-FRZ-001 APPROVED`

All ten P1 batches are implemented, verified, and closed. The foundation remains a Core PHP modular monolith with
MySQL/InnoDB, explicit composition, server-rendered progressive enhancement, secure operational boundaries, and no
business/domain implementation beyond the permitted scheduler operational ledger.

P2 remains `NOT_READY` because `OD-051` and `OD-052` are unresolved. P1 closeout does not invent those identity
normalization rules.

## Batch closure

| Batch | Status | Acceptance evidence |
| --- | --- | --- |
| QMDB-P1-B01 | DONE | Official PHP 8.5.10, locked install, full quality, CLI and HTTP gates |
| QMDB-P1-B02 | DONE | configuration precedence, fail-closed production dotenv, redaction, UTC and secure IDs |
| QMDB-P1-B03 | DONE | PSR-7/15 kernel, deterministic routes, middleware and real HTTP matrix |
| QMDB-P1-B04 | DONE | explicit PSR-11 container, module graph, command/query/event contracts |
| QMDB-P1-B05 | DONE | MySQL 8.4.11 session, connection, readiness and transaction integration |
| QMDB-P1-B06 | DONE | schema metadata, locks, migration/seed lifecycle, rollback and reapply |
| QMDB-P1-B07 | DONE | secure errors, structured redacted logs, correlation and headers |
| QMDB-P1-B08 | DONE | bounded CLI/worker/scheduler lifecycle and MySQL claim ownership |
| QMDB-P1-B09 | DONE | secure views, English/Arabic RTL, themes, fragments and native Fetch behavior |
| QMDB-P1-B10 | DONE | CI policy, dependency assurance, scanners, SBOM, licences and release artifact |

## Acceptance corrections discovered during closeout

1. Enabled PDO SQLite only in the isolated PHP acceptance runtime so SQLite-backed orchestration tests could execute.
2. Replaced repeated named PDO placeholders in scheduler statements; native MySQL prepares require unique placeholders.
3. Normalized two aggregate assertions to integer value semantics across PDO driver return types.
4. Replaced invalid actionlint `-color=never` usage with supported `-no-color` and added regression coverage.
5. Documented one intentional ShellCheck `SC2016` cross-language quoting exception at the exact line.
6. Bounded Gitleaks generated-directory exclusions and added exact false-positive rules for synthetic test markers and
   frozen ER relationship identifiers.
7. Corrected Windows npm resolution in the shell-free process runner so a wrapper earlier on PATH cannot hide the real
   Node installation's npm CLI; added an executable regression test.
8. Corrected scheduler integration cleanup so destructive DDL tests restore and assert the pre-test ledger-table state
   instead of leaving a migrated disposable database inconsistent with its migration ledger.

## Scope integrity

- P0 frozen content revalidated with all 82 governed hashes intact.
- Seven MySQL foundation tables exist in the disposable acceptance database: six `qmdb_schema_*` ledgers and
  `qmdb_scheduled_task_runs`.
- No account, tenant, participant, competition, scoring, result, appeal, certificate, media, consent, or audit-business
  table was introduced.
- Authentication, authorization, sessions, CSRF, business modules, durable queues, Redis, SSE and production deployment
  remain outside P1.

## Deferred operational evidence

Hosted GitHub Actions execution, manual assistive-technology/browser review, and Linux PCNTL signal delivery remain
explicit pre-merge, pre-release, or pre-worker-deployment gates. The implemented foundation fails closed where those
capabilities are unavailable; none is claimed as executed in this Windows closeout.
