# QMDB-P3-B04 Implementation Report

## Outcome

`QMDB-P3-B04 — Organization Memberships, Staff, Leadership, and Person Affiliations` is implemented as a private, consent-based, tenant-scoped affiliation capability. It does not create Workspace membership, grant authorization roles, expose a public directory, or introduce B05 claim, verification, or duplicate-resolution behavior.

## Delivered production capability

- Tenant-owned affiliation aggregate with opaque `QMA-` references, optimistic versions, immutable lifecycle history, and an open-affiliation uniqueness marker.
- Twelve governed affiliation roles with compatible Person-profile checks, role-assignment history, primary-role rules, Organization-unit support, and leadership step-up enforcement.
- Explicit request, Guardian-authorized response, decline, withdrawal, expiry, expiry-notification, and retention-safe roster behavior.
- Closed authorization catalog, private account and Workspace routes, CSRF, idempotency, account/peer rate limits, audit events, security-notification intents, and scheduled maintenance.
- Composite Workspace/Organization foreign keys, tenant-scoped persistence predicates, MySQL triggers, and Organization/Unit retirement guards.
- Server-rendered progressive pages, private fragment refresh, controlled modals, keyboard/focus/live-region behavior, and no-JavaScript form submission paths.

## Corrective work discovered during verification

- Replaced unsupported `UUID_FROM_BIN` reads with `BIN_TO_UUID` in the affiliation persistence path; the actual MySQL test identity exposed the production-read defect.
- Added a forward-only notification-domain migration for `ORGANIZATION_AFFILIATION_EXPIRED`; no applied migration was altered.
- Made schema-ledger retry writes reconcile description and checksum atomically, then restored the B03 seed boundary so its established checksum remains reproducible.
- Restored the inherited B03 Organization module source to repository style so the project-wide quality gate covers the live code rather than excluding compacted source.
- Repaired inherited MySQL fixture teardown order and catalog expectations so P2 isolation tests rebuild cleanly after the new Organization and affiliation foreign-key graph is present.

## Validation evidence

| Gate | Result |
| --- | --- |
| B04 unit, architecture, bootstrap, route-policy and P2-freeze tests | PASS — 20 tests, 415 assertions |
| Expanded B04 architecture regression | PASS — 70 tests, 54,958 assertions |
| Real MySQL affiliation integration and concurrency tests | PASS — 4 tests, 18 assertions |
| Schema rebuild, migrate, seed, schema verification, affiliation verification, authorization verification and route verification | PASS — routes: 148/148 classified; mutations: 68/68 CSRF-protected; authorization: 40 permissions, 10 roles, 110 mappings |
| Frontend syntax, tests and dependency audit | PASS — 34 JavaScript files; 54 frontend tests; zero npm audit vulnerabilities |
| Repository-wide PHP code style | PASS — 1,435 files |

The committed release baseline adds final Engineering Freeze, P2 controlled-extension, full PHPUnit, MySQL, frontend, release-build, and release-artifact verification evidence.

## Handoff

The next authorized work is `QMDB-P3-B05`. It begins only from the committed, clean B04 release baseline. No B05 implementation is included in this report.
