# P2 Implementation Decisions

This controlled extension records implementation decisions made after `QMDB-P0-FRZ-001`. It does not edit or
supersede the frozen product decision register. Any conflict is resolved in favor of the frozen baseline and approved
change control.

## P2-ADR-001 — Stateless public-form CSRF and canonical origin

- **Status:** Approved and implemented by QMDB-P2-B02.
- **Context:** Registration and email verification are public mutations that cannot rely on the session foundation owned
  by B03 and must not trust forwarded or Host headers.
- **Decision:** Bind purpose-specific HMAC CSRF tokens to a random host-only HttpOnly `SameSite=Strict` nonce cookie and
  issuance time; production uses `__Host-` and `Secure`. Validate `Origin` when supplied against the configured canonical
  application origin, which also owns verification-link generation.
- **Rationale:** Provides replay-bounded same-origin protection without creating authentication state or deriving trust
  from attacker-controlled request routing data.
- **Consequences:** CSRF state is not a session, tokens are action-specific, and any later session integration must retain
  equivalent-or-stronger origin/cookie controls.
- **Security impact:** Mitigates cross-site mutation, cookie injection and poisoned verification links while keeping token,
  cookie and origin evidence out of logs.
- **Future review conditions:** Change requires B02/B03 compatibility, browser security, deployment-origin and regression
  evidence.

## P2-ADR-002 — Enumeration-resistant password authentication before sessions

- **Status:** Approved and implemented by QMDB-P2-B02.
- **Context:** B02 must provide production password verification for B03 without exposing a temporary login endpoint or
  authentication token.
- **Decision:** Use Argon2id with versioned policy, a process-local Argon2id dummy hash for unknown/ineligible accounts,
  generic invalid results, database-backed HMAC-keyed email/peer throttles, and a verified principal returned only to
  trusted application consumers. B02 creates no session or login route.
- **Rationale:** Equalizes observable authentication work and establishes a safe service boundary without crossing the
  session batch boundary.
- **Consequences:** B03 consumes the verified principal and owns rotation, device and cookie behavior; it must not bypass
  B02 throttling or eligibility checks.
- **Security impact:** Reduces account enumeration, credential stuffing and premature bearer/session exposure.
- **Future review conditions:** Password-policy or algorithm changes require rehash migration, timing, capacity, abuse and
  compatibility evidence.

## P2-ADR-003 — Single-use email-verification challenge lifecycle

- **Status:** Approved and implemented by QMDB-P2-B02.
- **Context:** Email-link scanners must not activate accounts, raw tokens must not be stored, and concurrent POSTs must
  never duplicate activation.
- **Decision:** Store only SHA-256 hashes of 256-bit random tokens; GET renders confirmation only; POST locks and consumes
  the pending challenge in the same transaction that verifies email, activates the account and appends one status event.
  Challenges expire, revoke, enforce bounded attempts, and permit only one pending challenge per email.
- **Rationale:** Separates link possession from state mutation and makes replay/concurrency behavior explicit.
- **Consequences:** Resend revokes the prior pending challenge, email is delivered only after commit, and valid completed
  replay returns a safe completion without a second effect.
- **Security impact:** Mitigates token-database disclosure, scanner activation, brute force and double activation.
- **Future review conditions:** Token format, expiry, attempt limits or delivery-channel changes require threat, privacy,
  concurrency, migration and operational evidence.
