# Account Recovery and Security Notification Standard

## Status and scope

This standard records the implemented `QMDB-P2-B04` boundary under product freeze `QMDB-P0-FRZ-001`, engineering
freeze `QMDB-P1-FRZ-001`, and approved change `QMDB-CR-001`. It covers anonymous password recovery, authoritative
password reset, post-reset session invalidation, durable password-reset-completed notification intents, and scheduled
email delivery. It does not implement MFA, passkeys, recovery codes, authenticated password change, support-assisted
recovery, a notification inbox, or the future tamper-evident audit ledger.

## Recovery request contract

- `GET /forgot-password` renders an ordinary full page; JavaScript may progressively enhance its normal form.
- `POST /forgot-password` requires CSRF, a server-issued idempotency submission ID, an HMAC-protected normalized-email
  bucket, and an HMAC-protected peer bucket.
- The response is deliberately identical for unknown, unverified, suspended, closed, rate-limited, and mail-failure
  cases. It never reveals an account, email-contact, challenge, or delivery state.
- An eligible account has at most one pending challenge. Creating a replacement revokes the former pending challenge
  in the same transaction. The message is sent only after commit.
- Idempotency controls duplicate application submission; rate limiting controls repeated attempts across submission IDs.

## Token and challenge contract

- Recovery tokens contain 256 bits from the operating-system CSPRNG and use unpadded base64url for transport.
- Persistence receives only a binary SHA-256 token hash. Token values redact debug output, reject JSON/PHP
  serialization, have no string conversion, and are forbidden from logs, exceptions, responses other than the reset
  form, browser storage, and security notifications.
- Challenges are account-owned, expire, bound attempts, and transition from `PENDING` to one terminal state:
  `CONSUMED`, `EXPIRED`, or `REVOKED`. Append-only recovery events preserve lifecycle evidence.
- `GET /reset-password/{challengeId}` may validate enough to render the form but never consumes or mutates the
  challenge. Only the CSRF-protected, idempotent POST is authoritative.

## Password reset transaction

Password policy validation and Argon2id hashing occur before the final transaction to bound lock duration. The
transaction then locks the account and challenge, repeats authoritative token/state/expiry/attempt validation, claims
the reset submission, revokes the former active password credential while retaining it as history, inserts the new
active credential, consumes the selected challenge, revokes other pending recovery challenges, appends recovery
events, revokes all active account sessions with reason `PASSWORD_RESET`, and inserts the deduplicated security-
notification intent.

After commit, the response clears the session cookie, preserves the device cookie, rotates the CSRF cookie, and routes
to a signed-out completion page. Reset never creates a user session and never signs the account in automatically.

## Security notification contract

- `account_security_notifications` holds durable delivery intents; `account_security_notification_events` is an
  append-only delivery history. Neither table is the future tamper-evident audit ledger.
- A `PASSWORD_RESET_COMPLETED` intent is inserted atomically with the reset. Its source/account deduplication key
  prevents duplicate intents for the same reset outcome.
- Delivery claims use bounded batches, execution-owned leases, optimistic versions, bounded attempts, and capped
  exponential retry delays. A stale claimant cannot update a newer claim.
- Recipient contact is decrypted only for delivery. SMTP occurs outside database transactions. Failure storage uses a
  bounded classification code, never transport exception text, recipient data, credentials, DSNs, or message bodies.
- The scheduler provides **at-least-once delivery**. Intent deduplication and leases do not constitute an exactly-once
  SMTP guarantee; providers and recipients can still observe a duplicate after an ambiguous transport outcome.
- The completion email contains no recovery token, token hash, session token, password, or password hash.

## Scheduler contract

The production registry contains `identity.security_notifications.deliver`, due every 60 seconds. `schedule:list`
exposes metadata without resolving the delivery graph. `schedule:run` claims the scheduler slot and then resolves the
bounded handler. No browser route can invoke the scheduler or select a service. Production deployment must configure
an external scheduler to execute the CLI command; that operational activation remains a deployment gate.

## Presentation, privacy, and accessibility

Recovery and reset are full-page workflows, never modal workflows. Native Fetch enhancement sends same-origin POST,
CSRF, and idempotency headers; blocks duplicate submission; performs no automatic mutation retry; clears password
fields after failure; safely preserves non-sensitive email input; and keeps the ordinary form fallback. Invalid reset
responses do not retain the token in browser storage or render it into error content.

English and Arabic views preserve semantic headings, associated labels/guidance/errors, focusable summaries, announced
status, keyboard operation, visible focus, reduced-motion and forced-colour behavior, and RTL direction. No positive
`tabindex` is introduced.

## Configuration and operations

Recovery TTL, attempt/window limits, delivery batch size, lease, attempts, and retry delays are typed, bounded startup
configuration. Secrets remain supplied through the existing secrets provider. Operators must monitor scheduler runs,
terminal notification failures, mail-provider availability, recovery-request pressure, and recovery/history retention.
Final retention, CAPTCHA/trusted-proxy policy, provider selection, and future MFA recovery are intentionally deferred to
their owning decisions.

## P2-B09 audit integration

Successful password reset remains a single authoritative transaction and now appends a keyed account audit event
without including a password, password hash, token, contact value, or recovery challenge secret. The durable security
notification is still delivered only after commit by the existing scheduler path.

## P2-B10 abuse-evidence reconciliation

B10 retains the B04 recovery behavior and maps its generic response, one-time hashed challenge, idempotent reset,
session revocation, and safe log boundary to the authentication-abuse matrix. The route inventory additionally keeps
recovery challenge pages no-store and rejects unclassified mutation policy drift.
