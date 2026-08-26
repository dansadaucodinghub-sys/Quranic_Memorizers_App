# QMDB-P2-B03 Implementation Report

## Batch result

`COMPLETE — secure server-side sessions, devices, login, logout, revocation, progressive interaction, concurrency, and
readiness controls implemented; mandatory local validation and controlled freeze passed`

## Scope delivered

- Added the explicit `identity.sessions` module, typed configuration, two reversible MySQL migrations, persistence
  contracts, native prepared-statement repository, application services, authentication middleware, controllers, routes,
  localized views, and readiness probe.
- Added opaque selector-secret session/device cookies, hash-only server persistence, state/expiry enforcement, throttled
  touch, optimistic rotation with previous-token grace, account-serialized session limits, and fixation prevention.
- Added POST/CSRF logout, authenticated session/device inventory, account-scoped optimistic remote revocation, ordinary
  confirmation pages, controlled modal enhancement, panel-only refresh, and safe same-origin enhanced navigation.
- Preserved the B02 generic password-authentication boundary and added successful Argon2id rehash persistence.

## Migrations and tables

| Migration | Table | Purpose |
| --- | --- | --- |
| `20260826010700_create_user_devices` | `user_devices` | Account-owned device selector, hash, lifecycle, version, and timestamps |
| `20260826010800_create_user_sessions` | `user_sessions` | Account/device session selector, current/previous hashes, lifecycle, TTL, rotation, revocation, and login idempotency |

Both use InnoDB, binary UUID/hash columns, constrained status/reason values, composite account/device ownership, explicit
indexes, UTC-compatible `DATETIME(6)`, optimistic versions, and `RESTRICT` foreign keys. Neither table contains
`workspace_id`; B04 owns authenticated Tenant Context.

## Defects corrected during validation

- Supplied complete full-page view data during enhanced login success; page-or-fragment negotiation renders both
  representations and previously produced a 500 with an empty full-page contract.
- Removed a protected `Cache-Control` override from anonymous fragment rejection; the JSON response factory already owns
  `no-store`, and the duplicate override previously produced a 500 instead of the required 401.
- Moved duplicate-login-submission PDO classification into MySQL persistence so application services remain independent
  of PDO infrastructure.
- Updated older B02 test schema fixtures and architecture allowlists to include the authorized B03 migrations, module,
  configuration, persistence, and routes without disabling foreign keys.
- Tightened previous-token configuration so grace must be strictly shorter than the rotation interval.

## Executable evidence

- Security unit tests cover random token size, independent token families, redaction, non-serialization, canonical cookie
  parsing, malformed input, exact local/production cookie policy, separate `Set-Cookie` headers, and unsafe TTL rejection.
- MySQL/HTTP tests cover schema constraints, generic invalid login, valid login/inventory/logout, hash-only persistence,
  rotation/grace expiry, replay, account/device state, idle expiry, malformed cookies, readiness-safe auth bypass,
  fixation, failed reauthentication, inactive account states, password rehash, ownership isolation, and optimistic remote
  session/device revocation.
- Independent child processes prove parallel rotation, concurrent touch, and concurrent login-limit serialization.
- Frontend tests cover safe/unsafe navigation, optimistic revocation fragments, panel-only replacement, success-only modal
  close, failure preservation, focus restoration, and no automatic retry.

## Security boundary

No session/device secret, cookie, token hash, password/hash, CSRF token, login submission ID, email, raw peer address,
request body, or internal database ID is displayed or stored in browser storage. Current resources cannot be remotely
revoked, cross-account targets are denied, and authentication revalidates account/device/session state on every request.

## Validation summary

The committed B03 candidate passed PHP 8.5 style/static analysis, the full PHPUnit and isolated MySQL suites, Node 24
frontend syntax/tests/audit, migration rollback/reapply, repository/workflow/link/lock policy, Gitleaks, Trivy, SBOM,
licence, deterministic release, complete local CI, frozen P0 verification, and the regenerated P1 controlled-extension
freeze. Exact counts, hashes, revision, and command output are retained in generated reports and the recovery handoff.

## Completion state

`QMDB-P2-B03` is complete. The next executable recovery batch is `QMDB-P2-B04 — Account Recovery and Security
Notifications`. B05 remains blocked by its immediate sequential prerequisite, and B06 remains outside the authorized
recovery scope.
