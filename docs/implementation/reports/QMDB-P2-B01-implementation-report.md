# QMDB-P2-B01 Implementation Report

## Batch result

`IMPLEMENTED_UNVERIFIED — final full quality and engineering-freeze reconciliation pending`

Starting state was `NOT_STARTED`. No P2 production source or migration existed. This report is promoted to `COMPLETE`
only after the committed controlled-extension snapshot, regenerated engineering manifest and all integration gates pass.

## Production implementation

- Added explicit `identity.accounts` and `tenancy.workspaces` modules and composition-root wiring.
- Added RFC 9562 UUIDv7-backed opaque value objects for Workspace, Account, email, phone and credential identities.
- Added closed workspace, account, membership, contact and credential status types.
- Added immutable trusted `TenantContext`; platform context cannot be read as tenant authority.
- Added global Workspace and Account aggregates without conflating Account with the deferred Person profile.
- Added encrypted contact persistence through a libsodium authenticated-encryption boundary.
- Added purpose-bound HMAC-SHA-256 lookup values and redacted password-hash/contact objects.
- Added native-prepared MySQL repositories with optimistic version checks and mandatory tenant context for membership access.

## Migrations and tables

| Migration | Tables |
| --- | --- |
| `20260826010100_create_workspaces` | `workspaces` |
| `20260826010200_create_user_accounts` | `user_accounts` |
| `20260826010300_create_account_security_foundation` | `account_email_addresses`, `account_phone_numbers`, `account_credentials`, `account_status_events` |
| `20260826010400_create_workspace_memberships` | `workspace_memberships` |

All tables use InnoDB, `utf8mb4`, UTC `DATETIME(6)`, internal unsigned relational IDs, opaque `BINARY(16)` public IDs,
explicit checks, indexes and `RESTRICT` foreign keys. Active email/phone HMAC values and active password credentials use
generated nullable unique keys. Memberships carry non-null workspace ownership and a composite `(workspace_id, id)`
candidate key for future tenant-child relationships.

## Normalization decisions

- Email: outer trim, validated ASCII syntax, case-fold local and domain, preserve dots/plus tags, no provider aliases.
- Phone: only already-canonical E.164 with a non-zero country code and 8–15 digits; no country inference or punctuation rewriting.
- Plain contact values are encrypted; only separate purpose-bound keyed hashes are indexed.

These recovery-authorized outcomes close `OD-051` and `OD-052` for the implemented scope. Broader EAI, national parsing
or provider-specific alias behavior requires a new governed decision.

## Executed validation so far

| Gate | Result |
| --- | --- |
| PHP syntax | PASS — 634 files |
| Focused B01 unit/architecture | PASS — 64 tests, 31,153 assertions |
| B01 MySQL repository/migration tests | PASS — 3 tests, 9 assertions |
| Complete MySQL suite | PASS — 16 tests, 86 assertions |
| PHPCS | PASS — 620 files |
| PHPStan | PASS — no errors |
| Migration plan/apply/no-op/status | PASS |
| Latest migration rollback/reapply | PASS |
| Repository policy | PASS — 1,587 checks |

## Security observations

- Public identifiers never establish authority.
- Tenant repository access derives scope only from trusted context.
- Contact ciphertext and lookup keys have separate purposes; neither exposes plaintext through debug output.
- Password hashes are one-way and only exposed through the authentication repository contract.
- Runtime and schema identities are distinct non-root accounts against the isolated Oracle MySQL service.
- Person profiles, roles, permissions, login, sessions, recovery and MFA remain absent from B01.
