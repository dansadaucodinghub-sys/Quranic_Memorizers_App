# P2 Data Schema and Tenant-Isolation Verification

## Lifecycle result

The governed isolated Oracle MySQL lifecycle reset the test schema, applied 28 migrations and 3 seeds, executed 83 MySQL tests with 1,659 assertions, then reset, reinstalled, reseeded and verified the canonical schema and ledgers. Migration and seed reruns are no-ops; applied registry checksums are stable.

| Schema property | Verification |
| --- | --- |
| Engine and encoding | InnoDB and `utf8mb4` verified by the schema suite. |
| Timestamp policy | UTC `DATETIME(6)` conventions are enforced by migrations and schema tests. |
| Keys and relationships | UUIDv7 public IDs, internal relational IDs, scoped composite keys and restrictive update/delete constraints are validated by the real-MySQL suite. |
| Sensitive persistence | Password, session, device, verification, recovery, WebAuthn and recovery-code secrets are hash-only; contact and TOTP secret data use encrypted forms and redacted values. |
| Append-only data | Audit and checkpoint tables apply append-only protections and immutable chain semantics. |
| Index and plan evidence | Required indexes and bounded query plans are included in P2 performance and hardening tests. |

## Tenant boundary

Tenant authority originates only from the authenticated server session. There is no automatic first-workspace selection, device restoration, client-authoritative header, workspace cookie or browser-storage authority. Context validity requires an active account, active workspace and active membership; stale/invalid context is rejected and cleared.

The P2 tenant repository verifier confirms the exact closed repository inventory and trusted-context method parameters. MySQL and HTTP tests demonstrate cross-session and cross-workspace denial. Tenant-bound background context and cache-key factories require explicit trusted context. The 49-table canonical schema count, trigger/foreign-key/index counts, and their safe schema digest are generated into the P2 freeze manifest from the isolated database rather than transcribed here.
