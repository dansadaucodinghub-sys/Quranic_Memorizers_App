# QMDB-P2-B09 Blocker Resolution and Commit Inventory

| Field | Value |
| --- | --- |
| Batch | QMDB-P2-B09 — Security Events, Audit Integrity, and Account State Operations |
| Combined commit parent | `a958c840c100e4dad333af4a1c824fc369df3592` |
| Combined commit | `b2c44171a43ca691e334fc76a65f572f6ef4c1bc` (`Second Commit`) |
| Starting branch | `main` |
| Starting condition | Combined B09/B10 implementation was committed concurrently; B10 was not authorized for current main |
| Product-freeze drift | None detected |
| Resolution status | Forward separation in progress; B09 validation and governed freeze refresh pending |

## Concurrent Combined Commit Separation

The complete `a958c840..b2c44171` diff contains 201 changed paths. Each path and every mixed hunk was reviewed against
the B09 requirement boundary and the B10 exclusion boundary; the classification totals are 156 B09-only paths, 32
B10-only paths and 13 mixed paths. No unrelated or unsafe source path was retained. The audit-control readiness
port, its report and readiness check, the audit-listing boundedness check, the B09 console test environment, and the
MySQL trigger grant are retained only as documented B09 prerequisite corrections: B09 cannot safely boot, verify
audit controls or install its append-only triggers without them. They are not B10 route, tenancy, aggregate, fuzz or
performance tooling.

| Path | Change Type | Classification | B09 Requirement | B10 Requirement | Final Action |
| --- | --- | --- | --- | --- | --- |
| `.env.example`; migration/seed registries; B09 standards; translations; B09 views; B09 module roots; B09 audit/account-state tests; B09 integration hooks | Added/modified | B09_ONLY | Audit ledger, account state, UI, migrations, seed, transactional integrations and regression evidence | None | Retained from `b2c4417` |
| `README.md` | Modified | MIXED_B09_B10 | B09 scope and next-batch reference | B10 current-batch/authorization claim | Retained B09 wording; removed B10 execution claim |
| `docs/implementation/P2-identity-security-and-tenancy-roadmap.md` | Modified | MIXED_B09_B10 | B09 roadmap record | B10 authorization claim | Retained B09 state; B10 remains planned only |
| `docs/implementation/P2-requirements-to-batches.md` | Modified | MIXED_B09_B10 | B09 requirements map | Future B10 requirements row | Retained B09 mapping and future-only B10 row |
| `docs/implementation/README.md` | Modified | MIXED_B09_B10 | B09 standard/report entry | B10 active document links and execution claim | Retained B09 entries; removed B10 implementation links/claim |
| `docs/project/open-decisions.md` | Modified | B09_ONLY after hunk review | B09 operational decisions | None | Retained |
| `docs/project/p2-implementation-decisions.md` | Modified | MIXED_B09_B10 | P2-ADR-009 B09 audit/account-state decision | P2-ADR-010/011 B10 hardening decisions | Retained ADR-009; removed B10 ADRs |
| `docs/project/project-state.md` | Modified | MIXED_B09_B10 | B09 candidate and closure transition | B10 implementation/completion claims | Corrected to B09 current, B10 not started |
| `docs/project/risk-register.md` | Modified | MIXED_B09_B10 | B09 audit/account-state risk treatment | B10 hardening risk treatment | Retained B09 risks; removed B10 section |
| `src/Bootstrap/ApplicationMetadata.php` and dependent metadata tests | Modified | MIXED_B09_B10 | B09 active batch identity | B10 active batch label | Corrected to `QMDB-P2-B09` |
| `src/Bootstrap/Module/ApplicationHttpModule.php` | Modified | MIXED_B09_B10 | B09 account-state controllers, module edge and bounded audit readiness | B10 route catalog/verifier/CLI wiring | Retained B09 hunks; removed route-security wiring |
| `src/Bootstrap/Module/ConsoleFoundationModule.php` | Modified | MIXED_B09_B10 | B09 audit verification/checkpoint commands | B10 route, tenant and aggregate commands | Retained B09 commands; removed B10 commands/services |
| `tests/Integration/Console/BackgroundConsoleIntegrationTest.php` | Modified | MIXED_B09_B10 / SHARED_PREREQUISITE_CORRECTION | B09 audit key and scheduler failure coverage | B10 batch assertion/test isolation framing | Kept deterministic non-secret B09 test configuration; corrected batch assertion to B09 |
| `tests/Integration/MySql/P2SecurityAuditIntegrationTest.php` | Added | MIXED_B09_B10 / SHARED_PREREQUISITE_CORRECTION | B09 append, rollback, immutability, checkpoint, bounded-listing/index tests | B10 hardening groups and performance loop | Removed B10 groups/performance loop; retained B09 tests |
| `tools/windows/start-qmdb-mysql-test.ps1` | Modified | SHARED_PREREQUISITE_CORRECTION | B09 append-only trigger migration installation | None | Retained minimal `TRIGGER` grant |
| `composer.json`; `phpunit.xml.dist`; recovery-code frontend test; B10 reports/standard; B10 matrices; B10 performance baseline; B10 hardening/route/tenant source; B10 hardening/route/tenant/performance tests; P1 freeze snapshot | Added/modified | B10_ONLY | None | B10 implementation, test-group or stale freeze material | Removed or restored to parent state |

The B10-only set removed from current main includes the route-security catalog/verifier and command, the aggregate
`security:p2:verify` command, the tenant-repository verifier and command, all B10 hardening, route-security,
tenant-verifier and security-performance tests, B10 threat/abuse matrices, B10 hardening reports/standard, the B10
quality-suite configuration, and the B10 performance baseline. The historical source remains recoverable from
`b2c44171a43ca691e334fc76a65f572f6ef4c1bc` and local safeguard branch
`safeguard/qmdb-b09-b10-combined-b2c4417`; no history rewrite, rebase, reset, tag or push is used.

## Included B09 inventory

The B09 implementation commit includes the security-audit and account-state modules; B09 migration and seed
registrations; audit integrations into authoritative P2 security mutations; account-state routes, views and
translations; B09 architecture, HTTP, unit and MySQL tests; and the B09 standards, report, project governance and
configuration documentation required by the batch.

The authoritative new B09 source roots are:

- `src/Modules/SecurityAudit/`
- `src/Modules/IdentityAccountState/`
- `resources/views/pages/account-state-*.php`
- `resources/views/pages/security-audit-*.php`
- `resources/views/fragments/account-state-*.php`
- `resources/views/fragments/security-audit-*.php`
- `tests/Unit/SecurityAudit/`
- `tests/Unit/IdentityAccountState/`

Existing P2 source files changed only to append B09 audit evidence, register B09 modules/routes/commands, enforce
account-state authorization, or test those integrations. Applied migrations and seeds are registered without
altering prior applied definitions.

## Explicit exclusions

The following material is excluded from the B09 commit and retained for later owner-directed handling:

- B10 route-security, tenant-repository and aggregate-hardening source, tests and documentation.
- B10-only Composer/PHPUnit quality-suite changes and frontend recovery-code test adjustment.
- Generated build reports, scanner output, release archives, runtime files, local environment files, database data,
  credentials and private keys.

No secret-bearing or runtime artifact is staged. No P0-frozen file is modified.

## Required closeout sequence

1. Commit the reviewed B09 candidate.
2. Temporarily isolate excluded B10 work from the validation tree.
3. Run the clean committed B09 release and validation gates.
4. Commit final B09 evidence and project-state completion.
5. Generate and commit the engineering-freeze manifest using the repository tool.
6. Verify the freeze and clean tree without remote publication.
