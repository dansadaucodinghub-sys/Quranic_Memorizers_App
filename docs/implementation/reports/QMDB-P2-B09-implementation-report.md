# QMDB-P2-B09 Implementation Report

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Source baseline | QMDB-BL-001 |
| Product freeze | QMDB-P0-FRZ-001 |
| Engineering freeze | QMDB-P1-FRZ-001 |
| Batch | QMDB-P2-B09 — Security Events, Audit Integrity, and Account State Operations |
| Scope status | Implementation complete; formal closeout blocked by the required committed engineering-freeze verification |
| Date | 2026-08-29 |

## Delivered scope

- `SecurityAudit` implements deterministic `PLATFORM`, `ACCOUNT`, and `WORKSPACE` streams, canonical allowlisted
  metadata, SHA-256 metadata digests, versioned HMAC-SHA-256 event chains, per-stream sequence heads, append-only
  database rules, checkpoint chains, a verifier, a checkpoint CLI, and a bounded scheduled checkpoint task.
- `IdentityAccountState` implements authorized suspension and reactivation with base-role authorization,
  phishing-resistant step-up, self-operation prevention, final usable platform-security-administrator protection,
  optimistic account-version enforcement, idempotency, transactional notification intent, and durable account-state
  history.
- Suspension transactionally revokes sessions, authentication transactions, step-up grants, pending WebAuthn
  ceremonies, recovery challenges, active privileged access, and pending privileged-access requests. Reactivation
  restores none of them.
- The ledger captures the authoritative prior security mutations: password reset; session/device revocation; MFA,
  passkey, recovery-code and step-up changes; role assignment/revocation; and temporary, support, and break-glass
  lifecycle events. Workspace exceptional-access events are routed to workspace streams; platform events remain on the
  platform stream.
- Migrations `20260826012300` through `20260826012700` and seed `20260826020300` add the audit/account-state schema
  and extend the catalog to 32 permissions, 9 roles, and 83 role-permission mappings.
- Authorized account and platform security-event pages, account-state confirmation pages, English/Arabic text, RTL
  rendering, normal full-page form handling, and progressive no-retry mutation behavior are present.

## Integrity boundary

The ledger is keyed, tamper-evident, append-oriented, and externally checkpointable. It is not described as
tamper-proof, externally witnessed, or immune to an administrator who controls both MySQL and the integrity key.
No external checkpoint publisher is configured.

## Executable evidence

- Focused B09 architecture, bootstrap, console, HTTP, metadata, and configuration regression set: **79 tests, 44,547
  assertions passed** on PHP 8.5.10.
- Final isolated MySQL schema and concurrency matrix: **81 tests, 1,448 assertions passed**. It rebuilt all 28 migrations
  and all three authorization catalogs before tests, then restored and verified the canonical schema afterward.
- The final `composer ci` invocation passed repository policy (3,027 checks), frozen-baseline verification (177 checks),
  workflow validation (47 checks), Markdown links (1,010 checks), lockfile checks (15 checks), Composer validation,
  audit, autoload, platform requirements, PHP syntax (1,351 files), PHPCS, and PHPStan.
- Its locked PHPUnit stage ran 949 tests with 66,581 assertions and had exactly one failure: the required
  `EngineeringFreezeTest` rejection of uncommitted governed B09 paths. Because that stage failed, the CI invocation
  correctly stopped before its downstream frontend, security-scanner, SBOM, licence, release, and final aggregate
  stages; those stages must not be reported as executed in this closeout state.

## Closeout condition

The batch instruction explicitly prohibits this agent from making a Git commit. The existing engineering-freeze verifier
correctly rejects every uncommitted governed path, and the locked PHPUnit suite invokes that verifier. The generated
candidate therefore remains blocked by the cumulative governed implementation changes in the working tree, including
B09. The implementation is deliberately not recorded as `COMPLETE` and QMDB-P2-B10 is not authorized. After the owner
reviews and commits the intended governed change set, regenerate and verify the engineering freeze and run final local
CI/`composer ci` once more; then update the project state from `CLOSEOUT BLOCKED` to `COMPLETE`.

## Open operational decisions

Key custody and rotation, external checkpoint publication, audit retention/legal hold, viewer disclosure/export,
integrity-incident response, account-state policy taxonomy, and production configuration values remain in the project
open-decision and risk registers. B09 does not invent those policies.
