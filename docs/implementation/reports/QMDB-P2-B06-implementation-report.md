# QMDB-P2-B06 Implementation Report

## Execution identity

| Field | Value |
| --- | --- |
| Project | Qur’an Memorizer DB (`QMDB`) |
| Product baseline | `QMDB-BL-001` / frozen `QMDB-P0-FRZ-001` |
| Engineering baseline | `QMDB-P1-FRZ-001` |
| Recovery run | `QMDB-RECOVERY-RUN-001` — COMPLETE |
| Approved change | `QMDB-CR-001` — progressive interaction standard |
| Phase | P2 — Identity, Security, and Tenant Isolation |
| Batch | QMDB-P2-B06 — Roles, Permissions, and Scoped Authorization |
| Status | COMPLETE |
| Execution date | 2026-08-28 |

## Prerequisite verification and deferred evidence

P0 and P1 freeze verifiers passed before implementation, P2-B01 through P2-B05 were confirmed complete, the initial
working tree was clean, and the portable PHP 8.5, Node 24 and isolated Oracle MySQL 8.4 toolchain was used. The completed
recovery run remains unchanged. B06 used only established module, DI, migration, session assurance, `TenantContext`,
notification, readiness, observability, CI and release extension points.

Hosted CI; physical authenticators and supported browsers; manual keyboard, screen-reader, zoom and forced-colour
testing; production HTTPS WebAuthn RP/origin approval; managed MFA key custody; production scheduler/provider operation;
assisted/lost-factor policy; Docker/WSL repair; and Linux-only ShellCheck/PCNTL evidence remain explicitly unexecuted.
They are release, deployment or owning-policy evidence and do not weaken B05 or block the locally executable B06 gates.

## Engineering-freeze impact and prerequisite corrections

The P1 architecture and freeze identity remain unchanged. B06 files are controlled engineering extensions and the
freeze candidate is regenerated only from committed governed source after all executable checks pass. Frozen product,
requirements, traceability and decision-register artifacts were not modified.

Two prompt paths required controlled correction: the canonical frozen session document is
`secure-session-cookie-and-device-standard.md`, not the nonexistent alternate filename, and post-freeze decisions belong
in `p2-implementation-decisions.md`, not the locked P0 decision register. A fresh database also requires the governed
`db:schema:install` command before the requested migration plan can query its ledger. The first Windows reset met a
released InnoDB redo-file handle; the stopped server and exact runtime-owned target were verified and the reset rerun.

## Files, module, migrations, seed and tables

The candidate adds 75 production PHP files and updates 15. It adds the bounded `security.authorization` module, three
ordered reversible migrations, one deterministic production seed and five InnoDB tables:

- `authorization_permissions`
- `authorization_roles`
- `authorization_role_permissions`
- `platform_role_assignments`
- `workspace_role_assignments`

The migrations are:

- `20260826011600_create_authorization_catalog_foundation`
- `20260826011700_create_platform_role_assignment_foundation`
- `20260826011800_create_workspace_role_assignment_foundation`

The seed is `20260826020100_seed_foundational_authorization_catalog`. It is checksum governed, idempotent and creates no
platform or workspace role assignment.

## Permission catalog

The explicit catalog contains ten permissions. No wildcard, implicit prefix expansion or client-defined permission is
accepted.

| Scope | Permission | Required assurance |
| --- | --- | --- |
| Platform | `platform.authorization.view` | MULTI_FACTOR |
| Platform | `platform.authorization.assign` | PHISHING_RESISTANT |
| Platform | `platform.security.view` | MULTI_FACTOR |
| Workspace | `workspace.authorization.view` | PRIMARY |
| Workspace | `workspace.authorization.assign` | MULTI_FACTOR |
| Workspace | `workspace.memberships.view` | PRIMARY |
| Workspace | `workspace.memberships.manage` | MULTI_FACTOR |
| Workspace | `workspace.security.view` | MULTI_FACTOR |
| Workspace | `workspace.settings.view` | PRIMARY |
| Workspace | `workspace.settings.manage` | MULTI_FACTOR |

## Role catalog and mappings

The catalog contains two platform roles and five workspace roles with 27 explicit same-scope mappings:

- Platform: `platform.security_administrator`, `platform.authorization_auditor`.
- Workspace: `workspace.owner`, `workspace.administrator`, `workspace.security_manager`,
  `workspace.membership_manager`, `workspace.viewer`.

Roles are immutable system definitions in B06. Role inheritance, custom roles, explicit-deny rows and runtime catalog
mutation are absent. Platform roles cannot be assigned in workspace scope and workspace roles cannot be assigned in
platform scope.

## Authorization scope, assurance and decision model

Authorization denies by default. An allow requires an exact registered and persisted permission, matching scope, active
permission, active account, active role, active assignment, sufficient server-derived assurance, and—at workspace
scope—an active workspace and active membership in the trusted `TenantContext`. Public identifiers, authentication or
assurance alone grant nothing. Missing, unknown, retired, cross-scope, cross-workspace or inconsistent evidence returns
a typed deny reason and never falls back to allow.

The application-owned authorization guard converts denied decisions into one safe exception. HTTP middleware returns a
generic 403 problem response without revealing the missing role, permission, assignment or tenant relationship.
Structured operational logs retain only bounded identifiers and decision reasons and do not claim to be the future
tamper-evident audit ledger.

## Platform and workspace privilege administration

Platform and workspace assignment/revocation services use account- and tenant-qualified repositories, transactions,
row locks, optimistic versions and active-assignment uniqueness. Four action-bound step-up actions were added. Platform
mutations require phishing-resistant step-up; workspace mutations require multi-factor step-up. Grant consumption,
assignment history and notification intent commit in the same transaction.

Delegation is limited to a subset of permissions the actor currently possesses. The check runs before mutation and is
repeated with current locking reads inside the transaction, so concurrent authority revocation cannot become a
time-of-check/time-of-use elevation. Revocations preserve history. Locked counts protect the final usable platform
security administrator and final workspace owner under concurrent attempts.

Four role-change notification types reuse `identity.security_notifications.deliver`; no second scheduler or delivery
path was added. The existing delivery renderer was corrected so non-password security types no longer render the
password-reset template. Delivery remains bounded and at least once; provider operation is a deployment concern.

## Readiness and CLI verification

Readiness compares the governed catalog with the applied migration/seed ledger and persisted codes, IDs, scopes,
statuses, assurance levels and mappings. It is fail closed on a missing seed or drift and requires no runtime schema
mutation privilege. `security:authorization:verify` reports only bounded catalog/assignment counts and exits nonzero on
inconsistency. The clean result is 10 permissions, seven roles, 27 mappings and zero assignments.

## Tests added

B06 adds 30 dedicated test methods across eight test files: 12 unit, 11 MySQL integration and seven architecture methods.
Coverage includes code grammar and wildcard rejection, deterministic catalogs, cross-scope mappings, default deny,
account/workspace/membership/role/permission states, assurance, tenant isolation, persistence constraints, seed drift,
administration atomicity, notification rollback, delegation subsets, protected final roles and independent-process
concurrency. The delegation race verifies that authority revoked after the initial check is denied by the locked
transactional recheck without assignment, notification or step-up consumption.

Existing bootstrap, HTTP, CLI, readiness, notification, schema and B01–B05 fixtures were updated only where B06 adds a
governed module, migration dependency, foreign-key child table, notification type or fail-closed readiness condition.

## Commands and results

The executable matrix included tool/version/module inspection; Git status and whitespace checks; Composer install,
strict validation, audit, strict PSR autoload, platform requirements, PHPCS, maximum-level PHPStan and PHPUnit; npm clean
install, syntax, tests, audit and quality; schema install, migration plan/apply/status/rollback/reapply, seed/status/no-op
rerun and authorization verification; scheduler list/run; repository/freeze/workflow/link/lock checks; Gitleaks and
Trivy; SBOM/licence generation; release build/verification; direct local CI and `composer ci`; and final Git validation.

The final completion evidence records PHP 8.5.10, Composer 2.8.8, Node 24.19.0, npm 11.17.0 and MySQL 8.4.11. PHPCS and
maximum-level PHPStan pass. JavaScript syntax passes for 28 files, all 48 frontend tests pass and npm reports zero
vulnerabilities. The clean schema applies 19 migrations, reverses and reapplies all three B06 migrations, applies the
seed once, returns a no-op on rerun and verifies the exact 10/7/27/0/0 catalog.

During validation, executable evidence corrected catalog UUID checksum encoding, readiness schema-privilege ownership,
invalid structured event names, inherited fixture foreign-key teardown order, a stale recovery readiness expectation,
P-256 coordinate padding, deterministic module ordering and the MySQL isolation-level delegation race. No suppression,
wildcard, default allow, bypass or automatic catalog repair was introduced.

The first B06 local-CI closeout run exposed one orchestration defect after all substantive suites passed: destructive
MySQL fixtures left the ledger and table state unsuitable for the later release-readiness probe. Local CI now performs
a test-environment/name-guarded table reset, schema install, migration, seed and authorization verification immediately
after MySQL tests. A regression test enforces that ordering; production databases and database-level drop operations
are prohibited.

## Security controls, known limitations and open risks

The implementation provides explicit least privilege, exact scopes, server-authoritative state, assurance checks,
single-use step-up, locked delegation revalidation, relational tenant constraints, optimistic mutation versions,
protected continuity roles, historical revocation, atomic notification intent, generic 403 handling and fail-closed
catalog readiness.

Production platform-administrator bootstrap remains intentionally absent and blocks production authorization
activation. Custom roles, role retirement, explicit denies, inheritance, delegated-admin policy, approvals/dual control,
owner transfer, support/break-glass, decision caching/analytics, retention/escalation and privilege-review cadence remain
open. Geography, organization, competition, judging, certificate, media and social authority remain with their owning
modules. Workspace switching and tenant-aware data access belong to B07.

## Completion state and next batch

`QMDB-P2-B06` is complete after its executable quality, MySQL lifecycle, concurrency, security, supply-chain, release,
CI and engineering-freeze gates. P2 remains IN PROGRESS. The next batch is `QMDB-P2-B07 — Tenant Context, Workspace
Switching, and Tenant-Aware Data Access`; B06 completion does not implement or independently authorize B07.
