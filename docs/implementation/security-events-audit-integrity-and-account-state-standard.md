# Security Events, Audit Integrity, and Account State Standard

## Status and scope

This controlled-extension standard records the executable P2-B09 boundary. It governs the Security Audit ledger,
checkpointing, security-event viewers, and platform account suspension/reactivation. It does not amend frozen P0 or P1
decisions. It did not itself authorize P2-B10 work; the project owner subsequently authorized the bounded B10
hardening extension recorded in `identity-and-tenant-security-hardening-standard.md`.

## Security Audit evidence

Security Audit is authoritative, append-oriented security evidence. It is separate from operational logs, which retain
bounded request, rate-limit, and diagnostic telemetry, and from domain-history records such as account status events.
High-volume failed login attempts remain operational evidence unless an explicit approved event promotion is added.

The ledger has three governed stream types:

- `PLATFORM`: one deterministic platform stream.
- `ACCOUNT`: one deterministic stream per account public identifier.
- `WORKSPACE`: one deterministic stream per workspace public identifier.

`SecurityAuditStreamIdentity` calculates a non-secret SHA-256 stream key from a domain-separated canonical tuple. It is
a deterministic storage identity, never authorization. Public stream/event/checkpoint IDs are UUIDv7 values; internal
database identifiers are not exposed in HTTP projections.

Workspace-scoped temporary privilege, support-access, and break-glass lifecycle events are appended only to the
applicable Workspace stream. Their platform-scoped equivalents are appended only to the Platform stream; account
identities remain indexed structured evidence rather than duplicated stream records.

`SecurityEventCode` is an explicit, code-owned catalog. Severity, outcome, actor kind, and subject kind are also
server-owned enumerations. Browsers cannot supply arbitrary stream types, event codes, severities, actors, subjects, or
metadata keys.

## Metadata and event chain

Event metadata is an allowlisted map of bounded scalars or bounded nested scalar structures. It rejects arbitrary
objects, floats, resources, control characters, invalid UTF-8, unapproved keys, raw request data, contact values,
tokens, credentials, WebAuthn assertions, recovery codes, and account-state justification text.

The serializer sorts object keys, preserves list order, emits compact UTF-8 JSON, and enforces the configured byte
limit. `metadata_hash` is SHA-256 of the exact stored canonical JSON. MySQL preserves the canonical string as validated
`LONGTEXT` rather than normalising it as a JSON value.

Each event is HMAC-SHA-256 over a length-unambiguous canonical JSON integrity input with the
`QMDB-AUDIT-EVENT-V1` domain separator. The input binds stream identity/type, sequence, event ID/code/severity/outcome,
actor/session/workspace/subject identifiers, reason/request/correlation IDs, metadata hash, previous event hash, key
version, and UTC microsecond timestamp. The genesis predecessor is 32 zero bytes. The dedicated, versioned Audit key is
independent of the identity HMAC key and must never be logged.

The recorder requires the caller's active transaction. It locks or creates the stream, assigns the next sequence,
inserts the event, and advances the stream head with an optimistic version predicate. It never begins or commits an
independent transaction. Rolled-back mutations roll back their events; failed event appends fail their mutation.

Events have no update/delete repository method. MySQL triggers reject event, checkpoint, checkpoint-head, and stream
deletion (and reject mutation of events/checkpoints/checkpoint heads). Stream heads are the only mutable Audit records
and may change only as an append progresses.

## Checkpoints and verification

The checkpoint task uses the `qmdb.security_audit.checkpoint` advisory lock and a short transaction. It snapshots ordered
stream heads, derives a heads digest, records total events, and HMAC-chains checkpoint records using
`QMDB-AUDIT-CHECKPOINT-V1`. An unchanged ledger creates no new checkpoint. The scheduler runs only this bounded
checkpoint work; it never schedules a full-ledger scan.

`security:audit:verify` checks deterministic stream keys, sequences, predecessor and event HMACs, metadata digests,
stream heads, checkpoint numbering/HMACs/heads digests, checkpoint head-to-event binding, required indexes, and all
immutability triggers. It detects evidence corruption and reports safe codes; it never repairs, resequences, or
overwrites history.

`SecurityAuditCheckpointPublisher` is an interface for a future external evidence integration. Its envelope contains
only checkpoint hashes/counts/version/timestamp. No publisher is configured, no provider is invented, and no external
witnessing claim is made.

The ledger is **tamper-evident**, keyed, hash-chained, append-oriented, and externally checkpointable. It is not
tamper-proof, a blockchain, non-repudiable, legally certified, or externally witnessed unless a separately governed
provider has actually been configured and operated. A party with both database and integrity-key control may rewrite
history.

## Account-state operations

Only the base-role permissions `platform.accounts.suspend` and `platform.accounts.reactivate` permit state change.
Privileged Access is never an account-state authorization path. Both operations require phishing-resistant step-up and
a one-time, bounded submission identity plus expected account version. No browser retry or blind server retry is
permitted.

Account suspension and reactivation prohibit operating on oneself. Suspension also locks and verifies that another
usable `platform.security_administrator` exists. Under the authoritative transaction, suspension transitions status,
preserves an account-status event, revokes sessions/tenant context, authentication transactions, step-up grants,
WebAuthn ceremonies, recovery challenges, active privileged access, and pending privileged-access requests. It then
appends the account-stream event, stores the confidential operation record, and creates notification intent.

Reactivation requires a suspended account and the same base-role/step-up boundary. It preserves an account status event,
Audit event, operation evidence, and notification intent. It restores no session, tenant selection, step-up, authenticator
state, recovery challenge, or exceptional access; a new login is required.

Administrative justification and optional case reference are retained only in the confidential operation record, never
Audit metadata or HTTP viewer projections. Request failures remain generic and use the platform's request-correlation
handling.

## Views and progressive interaction

Own-account event pages are bounded to the authenticated account's actor/subject events. Platform pages require their
dedicated base-role permission and return safe event columns only: identifiers, code, severity, outcome, stream type,
reason code, and timestamp. They expose neither canonical metadata nor internal IDs, justification, contact values, or
secrets. All evidence/account-state pages are `private, no-store`.

Full-page account-state confirmations are authoritative fallbacks. Where native dialog and Fetch support exists, the
ordinary confirmation link opens the existing single labelled dialog. Focus, Escape, close, failure reference, and
fallback navigation follow the shared progressive-interaction standard. Forms are action-bound CSRF protected, carry
expected version/submission ID, use no automatic retry, and preserve ordinary POST behavior without JavaScript.

## Readiness, retention, and P2-B10 boundary

Configuration fails closed in production-like environments when the current dedicated Audit key is absent or invalid.
Readiness/status pages show bounded checkpoint and aggregate evidence only; full historical verification remains a CLI
and CI operation. Production key custody/rotation, multi-version keyring, retention/legal hold, external checkpoint
provider/frequency/retention, audit disclosure/export policy, and independent verification authority remain governed
open decisions. P2-B10 owns future identity and tenant hardening, not this ledger's automated risk scoring, account
closure/deletion, SIEM, or automated suspension.

## P2-B10 hardening interface

The B10 aggregate verifier consumes the existing read-only audit-control verifier and never creates a checkpoint or
replays full history. Audit integrity remains tamper-evident, not tamper-proof; the adversarial matrix records the
remaining key-custody and external-witness evidence as operational, not resolved source-code risk.

## P3-B02 privacy-safe profile events

Person-profile creation, update, role transition, declared progress and guardianship changes append the existing
Security Audit categories through controlled catalog extensions. Event metadata contains only bounded action context
and opaque references; it excludes Person names, birth dates, contact values and profile form bodies. Notification
content follows the same minimization rule and never establishes profile authority.

## P3-B05 claim and canonicalization audit extension

Pairing lifecycle, Guardian/Platform authorization, claimant acceptance/decline/revocation, record-status assertion,
duplicate reporting/consent/review, blocked resolution, canonicalization, and alias events use the existing
tamper-evident security audit boundary. Metadata contains bounded action, status, permission/authority category and
opaque public reference only. Pairing plaintext/selectors, HMAC values, names, birth dates, comparison material,
guardian identity, review justification, contacts, and idempotency keys are excluded. Audit history evidences the QMDB
workflow but never represents legal identity, legal guardianship, or government verification.
