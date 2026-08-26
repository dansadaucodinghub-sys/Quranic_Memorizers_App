# Account Registration, Email Verification, and Password Authentication Standard

## Authority and scope

This standard records the implemented `QMDB-P2-B02` boundary under `QMDB-P0-FRZ-001`, `QMDB-P1-FRZ-001`, and
`QMDB-CR-001`. It governs public registration, email verification/resend, password hashing and password-authentication
services. It creates no login route, session, authentication cookie, device, recovery mechanism, role, permission, MFA
factor, passkey, bearer token, JWT, or account dashboard.

## Password boundary

- Plaintext passwords use a non-string-convertible, redacted value and are neither normalized nor trimmed.
- Policy validates confirmation equality, minimum length, maximum byte count, NUL rejection, and non-whitespace content.
- Hashing and verification use Argon2id only; unavailable Argon2id fails closed.
- Hash metadata is versioned and verification reports when a rehash is required.
- Unknown or ineligible accounts execute the same password-verification boundary against a process-local Argon2id dummy
  hash and return the same generic invalid-credentials outcome.
- Authentication is rate limited by purpose-separated HMAC email and direct-peer fingerprints. A verified result contains
  only the internal persistence reference required by the next trusted service and the opaque account ID.

## Registration transaction

Password policy and Argon2id hashing complete before the transaction. The short transaction claims a UUIDv7 submission
ID bound to an HMAC request fingerprint, checks the historical keyed email lookup, and atomically creates the pending
account, initial status event, encrypted unverified email, active password credential, pending verification challenge,
and completed idempotency record. A reused key with a different fingerprint is a conflict. Duplicate or historical email
submissions receive the same generic accepted result and never reassign identity.

SMTP runs only after commit. Delivery failure is reduced to a bounded operation event without recipient, token, token
hash, password, CSRF value, raw peer address, mailer credential, or request body, and never rolls back authoritative data.

## Verification and resend transaction

- Tokens contain 256 bits from `random_bytes()` and only SHA-256 token hashes are stored.
- GET displays a confirmation form and never consumes a token. POST requires action-, time- and cookie-bound CSRF.
- Verification locks the challenge, applies expiration and bounded-attempt rules, consumes it once, verifies the email,
  activates the pending account, and appends exactly one activation event in one transaction.
- A replay of an already completed valid challenge is a safe completed result with no repeated effect.
- Resend is enumeration-resistant, independently rate limited and idempotent. It revokes the prior pending challenge and
  creates at most one new pending challenge per email before post-commit mail delivery.

## CSRF and origin boundary

CSRF state is a random host-only HttpOnly `SameSite=Strict` nonce cookie, not an authenticated session. Production uses
the `__Host-` prefix and `Secure`. HMAC tokens bind the nonce, one closed action, and issuance time. When `Origin` is
present it must match the configured canonical application origin; forwarded headers and browser Host values never
choose the origin or email link base.

## Abuse prevention and privacy

All registration, verification, resend, and password-authentication attempts use database-backed serialized buckets.
Bucket keys are purpose-separated HMAC-SHA-256 values. MySQL stores no raw email, peer address, password, CSRF value, or
verification token in throttling/idempotency records. Retry-After exposes a bounded delay only, never bucket identity or
account existence.

## HTTP and progressive interaction

The authoritative routes are `/register`, `/register/accepted`, `/verify-email/resend`,
`/verify-email/{challengeId}`, and `/verify-email/completed`. Forms work with ordinary POST/redirect/get when JavaScript
is absent. The progressive client intercepts only explicitly marked same-origin POST forms, sends CSRF and idempotency
headers, URL-encodes the body, blocks duplicate submission, accepts only the governed fragment protocol, and never
retries automatically. It preserves only safe email input, clears passwords after failure, announces status, focuses the
error/completion target, and exposes only validated request references for unexpected failure.

Registration and verification are full pages, never modals. Static resend/completed routes precede the challenge route.
English and Arabic share the same services and validation; Arabic pages and email use `lang="ar"` and RTL direction.

## Persistence and concurrency

`identity_idempotency_records`, `account_email_verification_challenges`, and `identity_rate_limit_buckets` are explicit
InnoDB migrations. Unique keys enforce submission identity, token-hash uniqueness and one pending challenge per email.
Transactions acquire deterministic row locks for concurrent idempotency, rate-limit and challenge consumption. Native
prepared statements use one unique named placeholder per bound value occurrence.

## Verification obligations

Completion requires PHP unit/architecture/HTTP tests, isolated MySQL migration and parallel-process concurrency tests,
frontend syntax/tests/audit, dependency audit, P0/P1 freeze validation, secret and filesystem scans, SBOM/licence gates,
deterministic release verification and complete local CI. Hosted CI and manual keyboard/screen-reader/zoom/forced-colour
review remain publication evidence and must not be represented as locally executed.
