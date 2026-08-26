# QMDB-P2-B01 Implementation Report

## Batch result

`COMPLETE — all mandatory batch, integration, security, release and freeze gates passed`

Starting state was `NOT_STARTED`. No P2 production source or migration existed. The production implementation is
committed, the controlled-extension engineering manifest is reconciled, and every mandatory B01 gate passes.

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

## Executed validation

| Gate | Result |
| --- | --- |
| PHP syntax | PASS — 634 files |
| Complete PHP quality | PASS — PHPCS 620 files; PHPStan no errors; PHPUnit 731 tests/37,005 assertions |
| Focused B01 unit/architecture | PASS |
| B01 MySQL repository/migration tests | PASS — 3 tests, 9 assertions |
| Complete MySQL suite | PASS — 16 tests, 86 assertions |
| Frontend compatibility | PASS — 17 JavaScript files; 23 tests; zero npm vulnerabilities |
| Migration plan/apply/no-op/status | PASS |
| Latest migration rollback/reapply | PASS |
| Repository/frozen/workflow/link/lock policies | PASS — 1,677/177/47/992/15 checks |
| Gitleaks | PASS — history and working tree, 19 commits, no leaks |
| Trivy | PASS — HIGH/CRITICAL vulnerability, secret and misconfiguration scan |
| SBOM/licences | PASS — 99 SBOM checks; 15 runtime packages; zero unknown/review items |
| Release artifact | PASS — reproducible, release eligible, Gitleaks/Trivy and CLI/HTTP/MySQL verification pass |
| Local CI | PASS — 26 recorded stages, no skipped mandatory B01 gate |
| P1 engineering freeze | PASS — 706 governed files, 4,271 checks |

## Gate corrections made

- Normalized PDO associative rows at the persistence boundary so typed mappers cannot receive numeric-keyed data.
- Replaced dynamic contact-table SQL construction with fixed prepared statements.
- Replaced stale P1 tests with exact allowlists for the two B01 modules and five authorized migrations.
- Added checksum-pinned native Windows Actionlint, Gitleaks and Trivy tooling because WSL was unavailable.
- Made Gitleaks generated-root exclusions cross-platform and added a regression test for the Windows scanner contract.

## Completion state

`QMDB-P2-B01` is complete. `QMDB-P2-B02` is the next executable recovery batch. B03 through B05 remain sequentially
blocked by their immediate predecessor, and B06 remains outside the authorized recovery scope.

## Security observations

- Public identifiers never establish authority.
- Tenant repository access derives scope only from trusted context.
- Contact ciphertext and lookup keys have separate purposes; neither exposes plaintext through debug output.
- Password hashes are one-way and only exposed through the authentication repository contract.
- Runtime and schema identities are distinct non-root accounts against the isolated Oracle MySQL service.
- Person profiles, roles, permissions, login, sessions, recovery and MFA remain absent from B01.
