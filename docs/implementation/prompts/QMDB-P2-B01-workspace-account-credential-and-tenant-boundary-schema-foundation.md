# QMDB-P2-B01 — Workspace, Account, Credential, and Tenant Boundary Schema Foundation

## Execution identity

Act as the senior Core PHP 8.5 engineer, MySQL data engineer, identity-domain engineer, tenant-isolation security
engineer, migration engineer and autonomous repository-aware coding agent for Qur’an Memorizer DB.

This prompt is not authorized for execution until project state says P2 is ready and every `BLOCKS_P2_B01` decision is
resolved. When authorized, implement production code directly in the repository. A documentation-only response is a
batch failure.

## Preflight and authoritative sources

Read completely before modification:

```text
docs/closeout/qmdb-p0-baseline-freeze.yaml
docs/closeout/p1/qmdb-p1-engineering-freeze.yaml
docs/closeout/p1/05-P2-readiness-assessment.md
docs/implementation/P2-identity-security-and-tenancy-roadmap.md
docs/implementation/P2-requirements-to-batches.md
docs/data/mysql-logical-schema.yaml
docs/data/09-tenant-isolation-data-model.md
docs/data/03-mysql-schema-conventions.md
docs/implementation/definition-of-ready-and-done.md
docs/project/project-state.md
docs/project/open-decisions.md
```

Verify that P1 is complete, `QMDB-P1-FRZ-001` is approved rather than a candidate, P2 is ready, OD-051 and OD-052 are
resolved, the working tree is understood, and an approved isolated MySQL 8.4 service is available. Stop as blocked if
any prerequisite fails; do not invent normalization or security policy.

## Mandatory production implementation

Implement:

- Workspace, UserAccount, AccountEmailId, AccountPhoneId and CredentialId value objects using approved opaque public IDs.
- WorkspaceStatus, AccountStatus, CredentialType and AccountEmailStatus closed types.
- Identity and Tenancy foundation modules with explicit service ownership and dependencies.
- Immutable trusted `TenantContext` contract plus only the explicitly justified system/null context.
- Domain entities and repository contracts that keep User Account distinct from Person.
- MySQL migrations, repositories and mappings using the existing migration/transaction foundations.
- UTC timestamps, `utf8mb4`, InnoDB, internal `BIGINT UNSIGNED` keys and approved UUIDv7 `BINARY(16)` public IDs.
- Real MySQL migration, constraint, repository, tenant-isolation, architecture and security tests.

Implement only the approved foundational tables and exact names from the logical schema:

```text
workspaces
user_accounts
account_email_addresses
account_phone_numbers
account_credentials
account_status_events
```

No placeholder repository, in-memory substitute for acceptance, generic `array` domain model, raw SQL concatenation,
silent exception swallowing or documentation-only scaffold is acceptable.

## Schema and integrity rules

- Use exact data types, nullability, unique constraints, check constraints, indexes and foreign keys from the approved schema.
- Use migration and step IDs, checksums, advisory locking and schema ledger behavior already established in P1.
- Migration rerun must be a no-op; drift or partial application must fail closed.
- Runtime and schema users are distinct non-root identities. Runtime code must not receive DDL authority.
- Native prepares stay enabled; emulation, persistence and multi-statements stay disabled.
- Contact normalization and keyed lookup behavior must implement the approved OD-051/052 decisions and test vectors exactly.
- Password material is stored only as approved one-way hashes; no plaintext or recoverable password encryption column.
- Credential algorithm/version metadata is explicit, and repositories do not expose hashes without an approved use case.
- Sensitive objects redact debug, log and serialization output.

## Tenant boundary

- Workspaces are globally governed; tenant-owned records carry non-null `workspace_id`.
- Cross-workspace parent/child relationships use composite workspace-aware integrity where applicable.
- Client input, public IDs, routes and hidden fields never establish tenant authority.
- Tenant-owned repository methods require an explicit trusted `TenantContext`.
- Cross-workspace reads, writes and relationships fail without revealing another tenant’s existence.
- Cache/job/export contracts introduced by this batch must carry trusted context or remain absent.

## Explicit exclusions

Do not create Person profiles, Organizations, Geography, guardian relationships, memberships, roles, permissions,
sessions, registration/login/recovery endpoints, MFA, passkeys, account forms, account modals, state-changing Fetch,
CSRF behavior, Redis, SSE, business audit records or any P3+ capability.

This is a schema/domain/repository batch. Later browser mutations must follow QMDB-CR-001, but B01 introduces none.

## Mandatory testing

Cover positive, negative, boundary, duplicate, nullability, invalid-state, rollback, stale-version, cross-workspace,
repository-scope, migration-rerun, drift and least-privilege behavior. Real MySQL evidence is mandatory; skipped MySQL
tests prevent completion.

Run and report actual exit codes:

```text
composer install
composer validate --strict
composer audit --locked
composer dump-autoload --strict-psr
composer quality
composer test:mysql
npm ci --ignore-scripts
npm run quality
php bin/console db:migrate:plan
php bin/console db:migrate
php bin/console db:migrate:status
php tools/ci/run-local-ci.php
git diff --check
```

Reverify the P0 and approved P1 freeze manifests. Update the engineering manifest through governed change control after
all source and documentation edits. Do not suppress tests, weaken policies, broaden ignores or claim skipped gates pass.

## Deliverables and completion

Report preflight, corrections, every created/updated file, migrations, constraints/indexes, module ownership, repository
contracts, tenant controls, tests/counts/assertions, commands/exit codes, security observations, limitations and exact
project state. Update dynamic reports, risks, decisions and state without modifying frozen baselines.

Only after every mandatory gate passes may state become:

```text
Completed Phase: P1 — Engineering and Repository Foundation
Current Phase: P2 — Identity, Security, and Tenant Isolation
Completed Batch: QMDB-P2-B01
Next Batch: QMDB-P2-B02
Status: READY FOR NEXT BATCH
```
