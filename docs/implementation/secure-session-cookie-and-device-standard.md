# Secure Session, Cookie, and Device Standard

## Authority and scope

This standard records the implemented `QMDB-P2-B03` boundary under `QMDB-P0-FRZ-001`, `QMDB-P1-FRZ-001`, and
`QMDB-RECOVERY-RUN-001`. It governs password login, server-side browser sessions, trusted-device records, authentication
middleware, logout, account-security inventory, and remote session/device revocation. It creates no password-recovery,
MFA, passkey, role, permission, workspace-selection, bearer-token, or JWT surface.

## Server-side session authority

- Authentication state is authoritative only in MySQL `user_sessions`; the browser receives an opaque versioned
  UUIDv7 selector plus a 256-bit random secret.
- Only SHA-256 token hashes are persisted. Current and previous hashes are distinct binary columns; raw cookie values,
  secrets, CSRF values, passwords, contact data, and internal database IDs are excluded from output and logs.
- Every authenticated request verifies the token using constant-time comparison and rechecks session, account, and
  device state plus idle and absolute expiry.
- Session rotation uses optimistic versioning. Exactly one parallel request rotates the token; a former token is accepted
  only during the bounded grace period and never receives a cookie that could overwrite the winner.
- Touch writes are throttled and optimistic. Idle expiry may move only up to the immutable absolute expiry.
- The maximum active-session limit is enforced while holding the account row lock. Oldest active sessions are retained
  as history and revoked with reason `SESSION_LIMIT` before the new session commits.

## Device authority

`user_devices` stores a per-account UUIDv7 selector, SHA-256 secret hash, lifecycle status, optimistic version, and
timestamps. A valid active device cookie reuses its record. A missing, malformed, cross-account, or revoked device
cookie creates a new record and cookie after successful authentication; a revoked record is never reactivated.

Remote device revocation is account-scoped, version-checked, and atomic with revocation of every active session on the
target device. The current device cannot be remotely revoked from its own authenticated workflow. The current session
uses logout rather than the remote-session route.

## Cookie policy

Local and test environments use `qmdb_session` and `qmdb_device`. Staging and production use
`__Host-qmdb_session` and `__Host-qmdb_device`. Every cookie is host-only, `Path=/`, `HttpOnly`, and `SameSite=Lax`;
production cookies are `Secure`, and no `Domain` attribute is emitted. The session cookie is non-persistent. The device
cookie uses the governed maximum age. Logout and invalid/expired authentication clear only the session cookie and retain
the device cookie.

## Login and fixation prevention

Login consumes the B02 password-authentication service, its generic eligibility behavior, rate limits, and rehash signal.
Successful login always creates a new session ID and secret, rotates CSRF state, and never upgrades or reuses an incoming
session token. Successful reauthentication revokes the former current session with reason `REAUTHENTICATION`; failed
reauthentication leaves it active. UUIDv7 login submission IDs and a unique database key prevent duplicate successful
submissions from creating multiple sessions.

## HTTP and progressive interaction

The authoritative routes are `/login`, `/logout`, `/account/security/sessions`, and the versioned session/device revoke
confirmation routes below that account-security boundary. All mutations are POST plus action-bound CSRF; GET only renders
forms. Normal redirects and confirmation pages remain the no-JavaScript path.

Enhanced login accepts only a validated same-origin `X-QMDB-Navigate` target. Revocation opens one controlled accessible
modal and accepts only a server-rendered fragment with the required optimistic version. Success replaces only
`#account-security-session-panel` and then closes the modal; failure keeps it open. No mutation is automatically retried,
no nested modal is accepted, and authentication data is never stored in browser storage.

## Readiness and verification

`IdentitySessionReadinessCheck` verifies configuration relationships, cookie-name policy, cryptographic primitives,
token generation, and applied schema without creating a session, performing login, or issuing a cookie. Liveness and
readiness routes bypass session authentication. Completion requires unit, architecture, frontend, HTTP, real MySQL,
parallel rotation/touch/login-limit, migration lifecycle, scanner, release, CI, and controlled-freeze evidence. Hosted
CI and manual assistive-technology/browser review remain publication evidence and are not represented as local execution.

Executable defaults and their unresolved production approvals are recorded in the controlled
[P2 implementation parameter register](../operations/p2-implementation-parameter-register.md); the P0 parameter register
remains frozen.
