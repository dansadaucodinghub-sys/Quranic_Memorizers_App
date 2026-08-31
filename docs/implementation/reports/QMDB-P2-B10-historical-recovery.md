# QMDB-P2-B10 Historical Recovery Record

| Field | Value |
| --- | --- |
| Batch | QMDB-P2-B10 — Identity and Tenant Security Hardening |
| Current B09 base | `60827ee017291783974d291612767005c3d81d9c` |
| Historical combined commit | `b2c44171a43ca691e334fc76a65f572f6ef4c1bc` |
| Safeguard reference | `safeguard/qmdb-b09-b10-combined-b2c4417` |
| Recovery method | Selective worktree recovery and manual forward reconciliation only |
| History rewrite | None |

## Preconditions verified

Before recovery, `main` was clean at `60827ee`. The frozen product baseline passed 177 checks, the B09 engineering
freeze verifier passed 8,725 checks, all B09 structural verifiers passed, `composer quality` passed 952 tests and
66,902 assertions, and the isolated MySQL suite passed 82 tests and 1,457 assertions after resetting, migrating,
seeding, and restoring its dedicated schema. These checks prove the B09-complete source was not replaced by the
historical mixed commit.

## Path and hunk classification

The earlier B09 concurrent-commit report reviewed the original `a958c840..b2c44171` range as 156 B09-only paths,
32 B10-only paths, and 13 mixed paths. This B10 recovery re-reviewed the B10-only and mixed material against the
current B09-complete source.

| Path or path set | Historical source | Classification | Current equivalent | Action | Validation |
| --- | --- | --- | --- | --- | --- |
| `src/Shared/Http/Routing/Security/*` | `b2c4417` | REUSABLE_B10 | Absent at B09 head | Restored, wired into current application HTTP module | 88-route verifier and focused route tests pass |
| `src/Modules/TenancyContext/*TenantRepositorySecurity*` | `b2c4417` | REUSABLE_B10 | Absent at B09 head | Restored and registered by current tenancy module | Repository verifier reports 2 repositories, 9 methods, 3 explicit global contracts |
| `src/Bootstrap/Security/*`, `P2SecurityHardeningVerifyConsoleCommand` | `b2c4417` | REUSABLE_B10 | Absent at B09 head | Restored and composed with current B09 authorization, tenant, privileged-access, and audit verifiers | `security:p2:verify` passes all six bounded components |
| B10 architecture, HTTP route, and in-memory performance tests | `b2c4417` | REUSABLE_B10 | Absent at B09 head | Restored | Focused recovery run: 69 tests, 641 assertions pass |
| B10 matrices, hardening standard, performance baseline, implementation/defect reports | `b2c4417` | DOCUMENTATION_ONLY | Absent at B09 head | Recovered only as templates and rewritten against current B09 evidence | Link and final documentation gates required |
| `composer.json`, `phpunit.xml.dist` | `b2c4417` | MIXED_WITH_OLD_B09 | B09 quality commands remain current | Reapplied only the named non-MySQL `Quality` suite and its explicit Composer target | Composer/quality validation required |
| `ApplicationMetadata` and dependent bootstrap/HTTP/console/system tests | `b2c4417` | MIXED_WITH_OLD_B09 | B09 batch metadata was correct at recovery start | Reapplied only the B10 current-batch value and matching assertions | Focused compile and console checks pass |
| `ApplicationHttpModule`, `ConsoleFoundationModule`, `TenancyContextModule` | `b2c4417` | MIXED_WITH_OLD_B09 | B09 audit, account-state, and tenancy corrections remain active | Manually added only B10 service/command registrations; no B09 hunk was overwritten | Autoload, bounded console verifiers, and focused tests pass |
| `P2SecurityAuditIntegrationTest` | `b2c4417` | MIXED_WITH_OLD_B09 | B09 audit integration remains active | Added B10 groups and bounded 100-query performance tripwire only | Full MySQL suite required |
| Recovery-code frontend test | `b2c4417` | MIXED_WITH_OLD_B09 | B09 UI test remains active | Replaced environment-global assertion with controller source-sink boundary check | Focused Node test passes |
| Historical B09 status, stale frozen manifest, generated validation counts, and claims that B09 remained incomplete | `b2c4417` | STALE_B10 / INCOMPATIBLE_WITH_FINAL_B09 | B09 final evidence at `60827ee` | Rejected; no historical status or freeze material restored | B09 baseline/freeze checks pass |
| Existing B09 audit-control readiness and scheduler prerequisites | Current `main` | ALREADY_PRESENT | Retained B09 source | Not recovered as B10 work | B09 audit and aggregate verifiers pass |

## Result

No combined commit was cherry-picked, reverted, merged, reset, or otherwise restored wholesale. The B09 complete
baseline remains forward-only, and no B09 regression was reintroduced during recovery. Historical B10 source is used
only where it compiles and verifies against the current B09 module graph; stale claims and generated freeze material
are replaced by current evidence.
