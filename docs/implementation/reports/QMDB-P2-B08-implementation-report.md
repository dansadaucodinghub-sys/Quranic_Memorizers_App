# QMDB-P2-B08 Implementation Report

## Control

| Field | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Product baseline | QMDB-BL-001 |
| Frozen product baseline | QMDB-P0-FRZ-001 |
| Approved change | QMDB-CR-001 |
| Engineering baseline | QMDB-P1-FRZ-001 |
| Batch | QMDB-P2-B08 — Temporary Privileges, Support Access, and Break-Glass Controls |
| Date | 2026-08-29 |
| Result | COMPLETE |

## Delivered controls

`security.privileged_access` is a registered Core PHP module. It adds fixed, seeded authorization catalog extensions,
three registered migrations, exact policy-backed request permission snapshots, immutable approvals and lifecycle events,
session-bound activations, post-use reviews, authorization decoration, contextual tenant isolation, security-notification
intents, scheduled maintenance, a generic readiness contribution, a verifier command, CSRF-protected routes, and
accessible server-rendered inventory/request/approval/review controls.

The module implements all three bounded access types: `TEMPORARY_PRIVILEGE`, `SUPPORT_ACCESS`, and `BREAK_GLASS`.
No role or membership is created by privileged activation. The effective authorization path always evaluates normal base
roles first and only then an active, unexpired, same-account, same-session, exact-scope, exact-policy activation.
Privileged mappings cannot authorize authorization assignment or privileged-access administration.

Support activation requires phishing-resistant assurance, distinct approved platform and workspace decisions, an ACTIVE
target workspace, no subject membership creation, and an unresolved-overdue-review check. Break-glass is one atomic
transaction with phishing-resistant step-up, no prior approval, a bounded incident reference, rate limits, immediate
activation, mandatory review, and no shared emergency account. Temporary Workspace access revalidates its ordinary
active membership. A normal selected workspace that conflicts with an active exceptional context is cleared and the
exceptional activation is invalidated in the same safe transaction.

## Data and catalog

- Migrations: `20260826012000_extend_privileged_access_security_catalog`,
  `20260826012100_create_privileged_access_request_foundation`, and
  `20260826012200_create_privileged_access_activation_foundation`.
- Tables: permission policies, requests, request permissions, approvals, activations, append-only events, and reviews.
- Seed: 17 permission definitions, 2 roles, 46 role-permission mappings, and 20 ACTIVE exact permission policies.
- Durable authorization catalog after seed: 27 permissions, 9 roles, and 73 role-permission mappings.
- One active privileged activation per account and per session is enforced with MySQL unique generated-key constraints.

## Security and operational behavior

- Request submission is idempotent only when the complete safe request fingerprint matches; replay with different
  content is rejected.
- Approval, activation, revocation and review use ordinary base-role authorization and action-bound step-up grants.
- Support and break-glass use mandatory independent post-use review; overdue review blocks new access of the same type.
- Expiry is authoritative in context and authorization queries even before scheduled persistence runs.
- The 60-second `security.privileged_access.maintain` task is registered alongside notification delivery and performs
  no browser interaction or direct email delivery.
- `security:privileged-access:verify` and the readiness contributor reject migration, seed, catalog, policy,
  active-expiry, activation-uniqueness, and normal/privileged tenant-context drift.

## Local validation evidence

The pinned PHP 8.5.10/MySQL 8.4.11 toolchain rebuilt the disposable test schema, applied all 23 migrations and both
seeds, and passed privileged-access, authorization, and Tenant Context verification. Targeted MySQL tests pass for
the B08 policy catalog and constraints. The complete PHP, MySQL, frontend, release and engineering-freeze gates are
recorded in the final batch ledger after their clean-source execution.

## Deferred evidence assessment

Hosted CI, production scheduler/provider operation, physical WebAuthn authenticator and supported-browser testing,
manual screen-reader/keyboard/zoom/forced-colour testing, and production support-governance review remain external
operational evidence. They were not represented as locally executed. They do not weaken the executable migration,
policy, authorization, session-binding, review, scheduler, or verifier controls delivered in B08.

## Project state

P0 and P1 remain frozen and complete. P2-B01 through P2-B08 are complete. P2 remains in progress; the next authorized
batch is `QMDB-P2-B09 — Security Events, Audit Integrity, and Account State Operations`.
