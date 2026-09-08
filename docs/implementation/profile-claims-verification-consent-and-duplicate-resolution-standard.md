# Profile Claims, Verification, Consent, and Duplicate Resolution Standard

## Scope and non-claims

`people.identity_resolution` is a private capability for establishing a QMDB Account-to-Person SELF link, recording
bounded QMDB record-status assertions, and resolving an explicitly reported duplicate Person only after consent and
conflict preflight. It is not a government identity, document, biometric, legal identity, legal guardianship, or
public-verification service. It neither creates Workspace membership nor assigns authorization roles.

## Pairing and claim lifecycle

1. An authenticated Account creates a selector-and-secret pairing code.
2. The service persists the selector and a versioned HMAC only; the plaintext code is shown once and is never logged,
   audited, notified, or stored in browser storage.
3. An active Guardian with exact management authority, or a phishing-resistant Platform reviewer performing a bounded
   QMDB record review, authorizes the exact Account and Person.
4. The claimant accepts with multi-factor step-up, or declines. Acceptance creates the active SELF link, lifecycle
   history, audit event, notification intent, and appropriate QMDB record-status assertion atomically.
5. Expiry, revocation, exhausted attempts, and maintenance are terminal or controlled lifecycle transitions. Adult
   acceptance revokes active Guardian profile-management authority; it does not certify a legal relationship.

Pairings are Account-bound, time-bound, one-time, attempt-bound, rate-limited, CSRF-protected, idempotent, and
optimistically versioned. A registry code alone, email, phone number, Account transfer, or Organization affiliation
cannot substitute for pairing and claimant acceptance.

## Verification assertions

`ACCOUNT_CLAIMED`, `GUARDIAN_CONFIRMED`, and `QMDB_RECORD_REVIEWED` are active/revoked historical assertions.
They state only what occurred in the controlled QMDB workflow. They grant no application permission and must not be
rendered as a public badge or interpreted as government identity, biometric verification, legal identity, or legal
guardianship certification. Assertion records use private routes, opaque references, no-store/no-referrer responses,
step-up where required, audit/notification minimization, and revocation history.

## Duplicate case and consent lifecycle

Duplicate cases are reported explicitly by an eligible Account/Guardian or privileged Platform reviewer; the system
does not generate cases from name, date-of-birth, contact, biometric, or fuzzy matching. An open-pair marker prevents
duplicate open cases. Each active Person management authority receives an independently versioned consent requirement.
Missing authority, declined consent, stale authority, or disagreement blocks canonicalization rather than inferring
consent. Case and consent events are append-only.

## Canonicalization controls

Before resolution, the controlled service checks manager consent, canonical target eligibility, alias state, two active
SELF links, demographic/geography/progress/Organization-affiliation conflicts, and bounded affected-record volume.
The canonicalization participants run inside one transaction and must not commit separately. On success the duplicate
source Person is retired, not deleted; an immutable, non-chainable alias maps it to the canonical Person. Names are
not silently overwritten, Account rows are not merged, and Organization-affiliation reassignment occurs only through
the registered participant. Any conflict or failed participant rolls back the entire transaction.

## Access, presentation, and evidence

Private routes use server-side account/relationship/permission checks, default-deny authorization, no-store and
no-referrer policy, CSRF, idempotency, rate limits, bounded error responses, and security audit events with opaque
references only. Server-rendered English/Arabic forms remain functional without JavaScript. Progressive enhancement
uses the existing same-origin fragment policy, accessible confirmation dialogs, focus management, live regions, and
never persists pairing data, profile values, review text, or duplicate comparison data in browser storage.

Release acceptance requires schema/migration/seed checks; authorization, route, tenant, People, Organization,
affiliation, and identity-resolution verifiers; full PHP, frontend, and real Oracle MySQL suites; concurrency and
immutability evidence; scanner/SBOM/licence/release verification; local CI; Composer CI; and P0/P1/P2 freeze checks.
