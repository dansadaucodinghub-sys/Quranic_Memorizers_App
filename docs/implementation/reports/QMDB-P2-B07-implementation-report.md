# QMDB-P2-B07 Implementation Report

## Control

| Field | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Product baseline | QMDB-BL-001 |
| Frozen product baseline | QMDB-P0-FRZ-001 |
| Approved change | QMDB-CR-001 |
| Engineering baseline | QMDB-P1-FRZ-001 |
| Batch | QMDB-P2-B07 — Tenant Context, Workspace Switching, and Tenant-Aware Data Access |
| Date | 2026-08-28 |
| Result | COMPLETE |

## Preflight and prerequisite correction

The live B06 ledger, P0 freeze, P1 engineering freeze, authorization catalog, schema/seed status, pinned PHP 8.5.10,
Composer 2.8.8, Node 24.19.0/npm 11, Oracle MySQL 8.4.11, full quality suite, MySQL suite, security scans, SBOM,
licences, release artifact, and 31-stage local CI all passed before B07 modification.

A real prerequisite orchestration defect was reproduced: invoking the standalone destructive MySQL suite left fixture
tables after completion, while the local CI runner restored them in later stages. `composer test:mysql` now delegates
to `tools/Ci/run-mysql-tests.php`, which always performs a guarded `_test`/`_ci` schema reset and restores the schema
ledger, every migration, the governed authorization seed, authorization verification, Tenant Context verification,
and schema verification. The reset guard and no-database-drop policy remain unchanged.

## Implementation

The `tenancy.context` module introduces immutable account/session/workspace/membership context, optimistic context
versioning, account workspace inventory, explicit selection and clearing, invalid-context resolution/clearing,
middleware, readiness, and `tenancy:context:verify`. The HTTP surface is:

```text
GET  /account/workspaces
POST /account/workspaces/switch
POST /account/workspaces/clear
GET  /workspace
```

Selection is stored only on the authenticated session. The B07 migration adds four session fields, two indexes, one
membership candidate key, one composite account/workspace/membership foreign key, and positive/all-or-none checks. It
does not modify an earlier migration, add a table or seed, or couple context changes to session-token rotation.

Authenticated page headers display a safe current/choose workspace control. English, Arabic RTL, ordinary forms,
progressive safe navigation, duplicate-submit protection, no automatic retry, version-only cross-tab signaling, and
private/no-store responses are implemented. No workspace context modal or client authority exists.

Tenant-owned repositories have an explicit marker. Background job context is revalidated at execution, cache keys are
workspace/cache-schema-version namespaced with hashed unbounded material, and future exports require trusted context. These are
contracts only: no production tenant job, durable queue, Redis/persistent cache, or export engine was introduced.

## Security and privacy evidence

- The client workspace public ID is only an account-scoped ACTIVE membership lookup.
- Composite MySQL integrity rejects a membership belonging to another account or workspace.
- Missing/inactive/foreign selections use bounded generic problem codes without existence, role, or permission detail.
- Invalid stored context is cleared and never replaced with the first available workspace.
- Two sessions for one account retain independent selections; the authentication session version remains unchanged.
- Context objects reject serialization and redact account/session identity in debug output.
- Browser storage and workspace authority headers/cookies are absent; broadcast messages contain only type and version.
- Tenant selection grants no authorization or assurance and does not alter the zero-assignment B06 seed.

## Validation evidence

The pinned toolchain executed migration plan/apply/status, schema verification, seed verification, authorization
verification, Tenant Context verification, focused unit/architecture/frontend/MySQL isolation tests, maximum-level
PHPStan, PHPCS, complete PHP/frontend/MySQL suites, scanner/SBOM/licence gates, local CI, release build/verification,
freeze verification, Markdown link verification, and `git diff --check`. Exact final counts and artifact hashes are
retained in generated `build/reports` evidence.

## Deferred evidence assessment

Hosted CI; physical browser/authenticator and manual keyboard/screen-reader/zoom/forced-colour review; production
WebAuthn/key/provider/scheduler configuration; Linux signal rehearsal; Docker/WSL repair; platform-administrator
bootstrap; and support/emergency-access governance remain external or future-batch evidence. None was represented as
executed, and none changes the locally executable B07 source result. P2-B08 owns temporary privileges, support access,
and break-glass controls.

## Project state

P0 and P1 remain complete and frozen. P2-B01 through P2-B07 are complete; P2 remains IN PROGRESS. B07 does not
authorize or implement B08. The next batch is `QMDB-P2-B08 — Temporary Privileges, Support Access, and Break-Glass
Controls`.
