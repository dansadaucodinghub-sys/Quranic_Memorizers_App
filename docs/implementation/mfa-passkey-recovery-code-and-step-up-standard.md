# MFA, Passkey, Recovery-Code, and Step-Up Standard

## Control and authority

| Field | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Baselines | QMDB-BL-001; QMDB-P0-FRZ-001; QMDB-P1-FRZ-001 |
| Approved change | QMDB-CR-001 |
| Implementing batch | QMDB-P2-B05 |
| Status | Implemented; production parameters and physical-device evidence remain governed gates |
| Last updated | 2026-08-27 |

This standard records the executable P2-B05 identity boundary. Authentication assurance is an internal security control;
it does not grant a role, permission, workspace, tenant, support, or administrative capability. Later authorization must
independently evaluate its own policy and may require an applicable one-time step-up grant.

## Authentication assurance and sessions

QMDB persists `PRIMARY`, `MULTI_FACTOR`, or `PHISHING_RESISTANT` on each server-side session together with the primary
method, optional secondary method, authentication time, and strong-authentication time. Password-only sessions are
`PASSWORD`/`PRIMARY`; password plus TOTP or a recovery code is `MULTI_FACTOR`; a user-verified passkey used either as
primary or secondary is `PHISHING_RESISTANT`. Existing sessions migrate safely to password `PRIMARY`.

An enabled MFA policy is separate from authenticator history. A password match does not issue a session when MFA is
enabled. Instead it creates a bounded pre-authentication transaction and selector/secret cookie. The raw secret is
returned only in the host-only, HttpOnly, SameSite=Lax cookie; persistence contains only its SHA-256 hash. Production
uses the `__Host-` prefix and `Secure`. Purpose, account, optional session, allowed methods, attempt count, expiry,
optimistic version, and terminal status are enforced under a short transaction. Success clears the transaction cookie
and rotates CSRF; invalid, expired, exhausted, completed, or replayed transactions issue no session.

## Step-up authentication

Step-up actions are a closed enum. A transaction binds the account, existing session, action, eligible methods, maximum
attempts, and expiry. Password can satisfy only `PRIMARY` actions. Strong authenticator-management actions require TOTP,
a recovery code, or a user-verified passkey according to the action’s assurance requirement. The server owns every
continuation URL.

Success creates a server-side grant bound to the account, session, action, assurance, and expiry. A protected mutation
locks and consumes that grant inside the same database transaction as its authoritative change. A grant is one-time;
wrong-action, cross-account, cross-session, expired, concurrent, or replayed consumption fails. Step-up never creates a
second login session.

## TOTP

Secrets use cryptographically secure randomness and Base32 representation. The only persisted form is an
XChaCha20-Poly1305 ciphertext with a unique 24-byte nonce, key version, and account/authenticator-bound additional
authenticated data. Decryption fails for the wrong key, nonce, AAD, or modified ciphertext. Secret, code, plaintext, and
key values are redacted and excluded from ordinary logs and serialization.

Enrollment requires an authenticated account and an action-bound step-up grant. It creates one expiring pending record,
renders a locally generated SVG QR code and manual-secret fallback, and never keeps a transaction open while the user
scans or types. The authenticator becomes active only after a valid six-digit code. QMDB uses a 30-second period and a
bounded drift window. The accepted counter is persisted with optimistic versioning so the same time-step succeeds only
once, including under independent-process concurrency. One active TOTP authenticator is currently supported. Revocation
preserves encrypted history and is prohibited if it would remove the final strong factor while MFA remains enabled.

## Recovery codes

The server generates the configured number of codes from a Crockford-style human-safe alphabet with at least the
configured entropy. Input is case-insensitive and separator-insensitive with controlled ambiguity normalization. The
browser receives plaintext only on the full-page one-time display. Copy enhancement reads visible DOM text, writes once
to the Clipboard API, and uses no Fetch, cookies, local storage, or session storage.

Persistence contains only domain-separated HMAC-SHA-256 values. A code is consumed atomically once; concurrent use has
one winner. Exhaustion changes the set to a terminal state. Regeneration requires strong step-up, revokes the former
active set, creates one replacement set, and displays it once. Disabling MFA revokes the active set. These codes are an
MFA/step-up fallback only and do not replace the B04 password-recovery process.

## WebAuthn and passkeys

QMDB uses `web-auth/webauthn-lib`; it does not implement CBOR, COSE, attestation, or signature verification itself. The
RP ID, RP name, and exact origin allowlist come only from typed configuration—not Host or forwarded headers. Production
origins require HTTPS; HTTP is allowed only for configured loopback development origins. User verification is required,
registration requires a discoverable credential, attestation is `none`, and the configured algorithm list is delegated
to the maintained library.

The account user handle is 32 random bytes, stable per account, and contains no email or account public identifier.
Registration options exclude existing active credentials. QMDB persists the credential identifier, COSE public key,
counter, AAGUID, transports, backup eligibility/state, attestation format, display name, status, and timestamps. It never
receives or stores a passkey private key.

Every ceremony has a fresh random challenge; only the challenge hash is persisted. Purpose, account, session, and
authentication-transaction bindings are checked where applicable. Bound retries revoke a previous pending ceremony
before creating its replacement. Expiry, attempts, optimistic version, terminal consumption, and one-time replay rules
apply. Anonymous discoverable-login ceremonies intentionally have no email or allow-credentials list.

Registration and assertions enforce the exact challenge, exact configured origin, RP ID hash, user presence, required
user verification, user handle, credential ID, signature, status, and ownership through the library. Passkeys support
passwordless login, password-login MFA, and step-up. Successful passkey login stores `PHISHING_RESISTANT` assurance.
Revoked or suspended credentials fail generically.

A zero counter is supported. Backup-eligible multi-device passkeys are not treated as universally monotonic. A
non-backup-eligible non-zero counter regression is a suspected clone signal, not proof: the credential is suspended, the
assertion fails, other sessions may be revoked under the configured response, and a notification intent is created.

## MFA policy and authenticator management

The missing policy row resolves to disabled. Enabling requires an active TOTP authenticator or passkey plus a strong,
one-time `MFA_ENABLE` grant. The operation atomically creates one recovery-code set, enables the policy, revokes other
sessions with `MFA_POLICY_CHANGED`, and creates a notification intent. An enabled policy with no usable method fails
closed and never falls back to password only.

Disabling requires strong step-up, disables policy without deleting authenticator history, revokes recovery codes,
revokes other sessions, and creates a notification intent. TOTP/passkey revocation is account-scoped, expected-version
protected, step-up protected, and serialized through the policy row; concurrent removals cannot leave an enabled policy
without a strong factor.

Notification types cover MFA enable/disable, authenticator add/remove, recovery-code regeneration/use, and passkey
suspension. Intents are created in the owning transaction and delivered by the existing
`identity.security_notifications.deliver` scheduled task with its at-least-once semantics. Messages contain no secret,
code, assertion, challenge, key, session token, or transaction cookie.

## HTTP, progressive interaction, and accessibility

MFA login, step-up, TOTP enrollment, passkey registration, MFA enablement, and one-time recovery-code display are full
server-rendered security workflows. TOTP, password, and recovery-code forms retain ordinary no-JavaScript POST behavior.
Passkeys require native browser WebAuthn and leave password/TOTP fallbacks visible when the API is unavailable.

JSON ceremony endpoints require action-bound CSRF, same-origin validation, same-origin credentials, bounded JSON bodies,
generic problem details, and request IDs. Credential responses exist only in request memory, are sent once, and are not
retried, persisted, cached, logged, or written to browser storage. TOTP/passkey revocations and MFA disablement may use
the single controlled modal, but each has a full-page confirmation. Failure keeps the dialog open; success refreshes only
the approved authentication-security panel and restores focus. Nested dialogs remain prohibited.

English and Arabic catalogs are key-parity checked. Pages use one labelled H1, explicit form labels/instructions,
descriptive QR alternative text, visible non-colour status, escaped passkey names, logical CSS order, RTL, forced-colour
support, reduced-motion support, keyboard controls, and no positive tabindex. Sensitive pages and SVG QR responses use
`private, no-store`; one-time codes additionally use `noindex, nofollow` and `no-referrer`.

## Logging, privacy, and future boundary

Logs may contain request ID, safe operation/result code, bounded duration, and non-secret internal correlation. They must
not contain passwords, TOTP secrets/codes, recovery codes/hashes, challenges, credential responses, authenticator data,
passkey public material, cookies, session/device tokens, cryptographic key material, or raw request bodies. Authenticator
and ceremony data
remain global identity records and do not acquire `workspace_id`.

Organization/role/workspace-enforced MFA, roles, permissions, tenant switching, support-assisted reset, break-glass
reset, conditional UI, metadata services, certification policy, and authoritative audit remain outside B05. Physical
authenticator/browser/assistive-technology evidence is a production release gate and is never inferred from deterministic
library fixtures.
