# QMDB-P2-B09 Implementation Report

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Source baseline | QMDB-BL-001 |
| Product freeze | QMDB-P0-FRZ-001 |
| Engineering freeze | QMDB-P1-FRZ-001 |
| Batch | QMDB-P2-B09 — Security Events, Audit Integrity, and Account State Operations |
| Scope status | COMPLETE — B09 source active, governed Engineering Freeze verified, and B10 absent from current main |
| Date | 2026-08-31 |

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

## Corrected-source executable evidence

The concurrent combined commit `b2c44171a43ca691e334fc76a65f572f6ef4c1bc` is preserved in history and on local
reference `safeguard/qmdb-b09-b10-combined-b2c4417`. Forward corrective commit
`a25771a983f9c556cce01840613f3ddb99fb90a1` removes B10-only route, tenant-repository, aggregate-verifier,
hardening-test, performance and reporting material from current main while retaining B09 and its documented
audit-readiness prerequisites. No history was rewritten and no push occurred.

- Focused B09 unit/domain evidence: **10 tests, 29 assertions passed**.
- Focused B09 bootstrap, console, HTTP and metadata evidence: **42 tests, 169 assertions passed**.
- Final guarded MySQL matrix: **82 tests, 1,457 assertions passed** in **7:31.927**. The runner applied all 28
  migrations and three governed seeds, then restored and verified the canonical schema afterward.
- Command-level verification passed: authorization, Tenant Context, privileged access and audit verification; checkpoint
  `CREATED`/`UNCHANGED`; and a 3/3 successful scheduler run.
- Final clean release source `0ffdfb16e8a6a5312cf217c7ff3475573df120ea` produced
  `qmdb-0.1.0-dev-0ffdfb16e8a6.tar.gz`, is release-eligible, and passed artifact verification including secret scans,
  Gitleaks, Trivy, CLI, English/Arabic/fragment HTTP, security headers and live MySQL readiness. Archive SHA-256:
  `db498fb258376af85b6403b71358698c6f081bc0866fd922af0e3d279e231102`; manifest SHA-256:
  `b9b322f7fccf8085431763296a3a8142d7174ba2758f133819c39ca2e0f432f7`; SBOM SHA-256:
  `c43fc8af00feef3df1a1023892027b1e6946bc65c7cddd41dd55587c2d39df48`.
- The governed Engineering Freeze commit `0ffdfb16e8a6a5312cf217c7ff3475573df120ea` verifies successfully. Its
  final clean-source CI passed 33 stages, including 952 PHP tests / 66,902 assertions, 82 MySQL tests / 1,457
  assertions, 51 frontend tests, product-freeze checks, supply-chain scans and release verification.

## Closeout condition

B09 is complete. The combined commit is retained in history and the correction remains forward-only. B10 is not
implemented on current main, has not been executed or validated as a batch, and is ready for separate owner
authorization only. This report and the project-state transition are dynamic closeout evidence excluded from the
governed release source and Engineering Freeze.

## Open operational decisions

Key custody and rotation, external checkpoint publication, audit retention/legal hold, viewer disclosure/export,
integrity-incident response, account-state policy taxonomy, and production configuration values remain in the project
open-decision and risk registers. B09 does not invent those policies.
