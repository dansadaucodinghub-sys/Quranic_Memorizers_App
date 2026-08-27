# QMDB-P2-B05 Implementation Report

## Execution identity

| Field | Value |
| --- | --- |
| Project | Qur’an Memorizer DB (`QMDB`) |
| Source / frozen baseline | `QMDB-BL-001` / `QMDB-P0-FRZ-001` |
| Engineering baseline | `QMDB-P1-FRZ-001` |
| Approved change | `QMDB-CR-001` — progressive interaction standard |
| Recovery authorization | `QMDB-RECOVERY-RUN-001` |
| Phase | P2 — Identity, Security, and Tenant Isolation |
| Batch | QMDB-P2-B05 — MFA, Passkeys, Recovery Codes, and Step-Up Authentication |
| Status | COMPLETE |
| Execution date | 2026-08-27 |

## Prerequisites, sequence and freeze impact

P2-B01 through P2-B04 were completed in order before B05 began. P0 product/requirements decisions and the P1
engineering architecture remain frozen. B05 uses their controlled module, DI, MySQL migration, session, HTTP,
presentation, notification, observability, CI, release and freeze extension points. It does not start P2-B06 or add an
authorization-policy implementation. The engineering freeze may be regenerated only for a committed B05 candidate
after every locally executable mandatory gate passes.

## Delivered modules, dependencies and schema

- Added the bounded `identity.multi_factor` module with configuration, domain values, repositories, services,
  readiness, MySQL persistence, WebAuthn/TOTP cryptography and one HTTP controller.
- Added locked runtime dependencies `web-auth/webauthn-lib` 5.3, `spomky-labs/otphp` 11.5 and `endroid/qr-code` 6.1,
  and declared the existing PHP Sodium runtime as mandatory. No provider SDK or biometric library was added.
- Added four ordered reversible migrations: constraint extension (`20260826011200`), authentication transactions and
  policy (`20260826011300`), TOTP/recovery codes (`20260826011400`) and passkeys/WebAuthn (`20260826011500`).
- Added nine InnoDB tables: `account_authentication_transactions`, `account_step_up_grants`, `account_mfa_policies`,
  `account_totp_authenticators`, `account_recovery_code_sets`, `account_recovery_codes`,
  `account_webauthn_user_handles`, `account_passkey_credentials` and `account_webauthn_ceremonies`.
- Extended only controlled enum/check boundaries for session assurance, session revocation, rate-limit scopes and durable
  security-notification types. No business-domain, role, permission, tenant-switching or audit-ledger table was added.

## Authentication assurance and session integration

The server recognizes `PRIMARY`, `MULTI_FACTOR` and `PHISHING_RESISTANT` assurance and the `PASSWORD`, `TOTP`,
`RECOVERY_CODE` and `PASSKEY` methods. Session creation, lookup, rotation and inventory persist the primary/secondary
method and strong-authenticated instant. Password plus TOTP or recovery code creates multi-factor assurance; a verified
passkey creates phishing-resistant assurance. Client input cannot set these values.

Password authentication for an MFA-enabled account does not create a session. It creates an expiring login-MFA
transaction and returns a generic `MFA_REQUIRED` continuation. Successful TOTP, recovery-code or passkey verification
atomically consumes the transaction and then delegates strong session issuance to the existing B03 session service.
Passwordless passkey login uses the same session/cookie lifecycle and never bypasses account-state checks.

The authentication-transaction cookie contains an opaque ID plus high-entropy verifier, is HttpOnly, SameSite=Lax,
host-only, Secure outside loopback development and path-bounded. MySQL stores only the keyed verifier. Malformed,
expired, exhausted, superseded and consumed transactions fail closed and clear or invalidate browser state.

## Step-up and MFA policy

Seven enumerated actions cover TOTP enrollment, passkey registration, MFA enable/disable, recovery-code regeneration and
TOTP/passkey revocation. Grants are account-, session- and action-bound, short-lived and single-use. Enrollment actions
accept primary assurance; destructive/security-state actions require multi-factor assurance and do not offer password
as an eligible method. Grant consumption and the protected mutation share a transaction.

MFA enablement requires at least one active strong authenticator, creates an active policy and issues one recovery-code
set. Disabling MFA, changing factors and detected compromise create durable security-notification intents and apply the
established session-revocation policy. While MFA is enabled, concurrent operations cannot remove the final active
authenticator.

## TOTP and recovery codes

TOTP uses the interoperable SHA-1, 30-second, six-digit profile with a configured one-step drift. Enrollment creates a
temporary authenticator, returns an `otpauth` URI and QR representation only within the enrollment workflow, and
requires a valid code before activation. Secrets are authenticated-encrypted with Sodium using an externally supplied,
versioned key; plaintext is non-serializable and excluded from logs. The last accepted counter advances atomically, so
parallel or replayed assertions cannot both succeed.

Each recovery-code set contains ten codes generated from 16 random bytes with an unambiguous Crockford-style alphabet.
Plaintext appears once and is never persisted, logged, emailed, placed in URLs or browser storage. MySQL stores only
keyed verifiers. Use and regeneration are atomic; old sets are revoked, concurrent double use has one winner, and a
durable notification intent records security-relevant use without containing the code.

## WebAuthn and passkeys

Registration and assertion options are server generated with a 256-bit challenge, exact configured RP ID and allowed
origins, required user verification, discoverable credentials and `none` attestation. The server validates client-data
type/challenge/origin, RP ID hash, authenticator flags, user handle, credential ownership, algorithm, public-key
signature, ceremony purpose/expiry/attempts and one-time consumption. Only credential ID, COSE public key, user handle,
metadata, transports, sign count and lifecycle state are stored; QMDB never receives private passkey keys or biometric
templates.

Registration rejects duplicates. Login supports discoverable passwordless assertions, while MFA login and step-up are
bound to their existing authentication transaction. A monotonic positive counter is advanced atomically. Regression
suspends the credential and creates a security-notification intent; zero-counter authenticators remain supported
without a false clone-resistance claim. A newly generated options request revokes an older pending bound ceremony so a
cancelled attempt cannot block a safe retry.

## Routes, views and progressive interaction

B05 adds 30 routes: MFA login (five), passwordless passkey login (two), step-up (six), security overview (one), TOTP
enrollment/revocation (six), passkey registration/revocation (five), MFA enable/disable (three), and recovery-code
status/regeneration (two). Eleven full pages and ten fragments provide English/Arabic, RTL, accessible and no-JavaScript
fallbacks for every non-WebAuthn action.

Five JavaScript modules provide a bounded WebAuthn codec/client, passkey login, passkey registration, passkey step-up
and explicit recovery-code copy. They accept same-origin server options, perform no authorization decisions, reject
malformed response shapes, do not auto-retry mutations and store no challenge, credential or recovery secret in local
or session storage. Protected management controls are rendered only from server-computed grant state, and every server
mutation independently enforces the grant.

## Notifications, operations and readiness

Nine B05 notification types cover MFA policy, TOTP, passkey and recovery-code changes. They reuse the existing
`identity.security_notifications.deliver` scheduler; B05 adds no HTTP-triggered worker or duplicate provider path.
Notification intent and factor mutation commit atomically, while SMTP remains after commit with the B04 bounded
at-least-once delivery contract.

Readiness verifies typed relationships, Sodium availability/key validity, RP/origin rules and the applied migration
set without creating a session, ceremony, factor or grant. All executable defaults and unresolved production approvals
are recorded in the P2 parameter register. No secret or machine-private path is copied into governed evidence.

## Test and validation scope

Unit tests cover assurance ordering, state/value invariants, configuration failures, cookie parsing, TOTP vectors and
replay, Sodium encryption/tamper detection, recovery-code entropy/normalization/hash behavior, grant/action policy and
WebAuthn counter rules. HTTP tests cover login MFA, passwordless login, step-up, factor management, CSRF/idempotency,
server-rendered eligibility, English/Arabic/RTL, no-JavaScript fallback and bounded progressive failures. Architecture
tests enforce module direction, routes, migrations, configuration, controller boundaries, secrets, browser-storage
prohibitions and future-scope exclusions.

Real MySQL tests cover all nine tables and constraints, persistence/hydration, active-policy/final-factor invariants,
transaction/grant consumption, TOTP replay, recovery-code double use, session assurance, notifications and independent-
process races. Deterministic signed P-256 WebAuthn fixtures cover valid registration/assertion and negative challenge,
origin, RP, user-verification, user-handle, credential, signature, duplicate, replay, counter and suspended/revoked cases.

The completion run used PHP 8.5.10, Composer 2.8.8, Node 24.19.0/npm 11.17.0 and isolated MySQL 8.4.11. PHPCS passed
967 files and maximum-level PHPStan passed. Unit passed 572 tests/1,303 assertions; non-MySQL integration passed
91/420; architecture passed 107/51,329; isolated MySQL passed 51/977; and Node passed syntax for 28 files plus 48 tests
and zero audit vulnerabilities. B05-specific coverage contains 19 unit, six HTTP and 13 MySQL/WebAuthn test methods.

All 16 migrations planned/applied cleanly. The final B05 migration rolled back, reported `ROLLED_BACK`, reapplied,
returned `No pending migrations` on rerun and passed schema-ledger verification. The notification schedule listed and a
real run reported one claimed/one succeeded/zero failed. Repository, workflow, Markdown-link and lockfile policies
passed 2,291, 47, 1,002 and 15 checks. Gitleaks passed history and working tree. Trivy passed HIGH/CRITICAL
vulnerability, secret and misconfiguration scanning with the cached 2026-08-26 database after its fresh network update
timed out; the extended local analysis timeout completed without a finding. SBOM passed 269 checks; runtime licences
reported 52 packages, zero unknown and zero review-required. Deterministic release verification linted 2,590 PHP files
and passed CLI, HTTP, MySQL readiness and artifact secret/security checks. The committed-source release, local CI and
regenerated engineering-freeze results are retained by their generated reports and final project ledger.

The delivered candidate adds or updates 105/28 production PHP files and adds or updates 5/2 production JavaScript
files. It adds one module, four migrations, nine tables, 30 routes, 11 pages, 10 fragments, 75 English plus 75 Arabic
translation entries, nine notification types and no new scheduled task or email-template path.

## Security boundaries and deferred physical evidence

No password, password hash, TOTP secret/ciphertext/key, recovery code/verifier, passkey credential identifier/public key,
challenge, session token, transaction verifier, email address or provider credential is included in this report. B05
makes no device-trust, biometric, attestation-certification, exactly-once notification or tamper-evident audit claim.

Production HTTPS RP/origin approval, managed encryption-key custody/rotation, physical Windows Hello/roaming-key and
cross-device testing, supported-browser coverage, manual keyboard/screen-reader/zoom/forced-colour review, provider and
scheduler operation, assisted/lost-all-factor policy and signature-counter incident response remain explicit release,
deployment or later-batch gates. Hosted CI remains publication evidence. These do not authorize B06 and are not
represented as locally executed.

## Completion state

`QMDB-P2-B05` is complete. `QMDB-RECOVERY-RUN-001` has completed its authorized B01-through-B05 sequence. P2 remains
in progress overall; P2-B06 is next in sequence but is blocked, not started and outside the recovery authorization.
