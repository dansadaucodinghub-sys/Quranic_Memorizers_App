# Privileged Access, Support Access, and Break-Glass Standard

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Product baseline | QMDB-BL-001 / QMDB-P0-FRZ-001 |
| Engineering baseline | QMDB-P1-FRZ-001 |
| Batch | QMDB-P2-B08 |
| Owning module | `security.privileged_access` |
| Status | Implemented and executable |
| Last updated | 2026-08-29 |

## Purpose and hard boundaries

P2-B08 provides exceptional access without changing ordinary role assignments, workspace memberships, the
authenticated account, or a normal Tenant Context. The only access types are `TEMPORARY_PRIVILEGE`,
`SUPPORT_ACCESS`, and `BREAK_GLASS`. Each is represented by a server-owned request and a short-lived,
account- and session-bound activation. No support or administrator impersonation, shared emergency account,
static emergency password, bearer token, JWT, automatic extension, or automatic context restoration exists.

The effective authorization service evaluates active base roles independently, then may add only the exact
permission snapshot held by one active exceptional activation. An expired, ended, revoked, different-account,
different-session, or wrong-workspace activation contributes no permission. Privileged access can never grant
role management, authorization management, privileged-access administration, approval, review, or another
exceptional grant. Those operations use `BaseRoleAuthorizationGuard` and ordinary B06 assignments only.

## Request, approval, activation, and review lifecycle

Requests are self-submitted with a bounded justification, bounded duration, explicit scope, locale, idempotency
submission identifier, exact permission subset, and a short request expiry. Permission snapshots are checked
against 20 seeded active policies; client-supplied permission names are never authority. Approvals are immutable,
action-scoped step-up protected records. A subject/requestor cannot approve their own request.

- Temporary privilege has one independent platform or workspace approval and requires an active membership for
  workspace scope. It has no post-use review by default.
- Support access is workspace-only, creates neither a membership nor a role, is read-only, requires distinct
  approved platform and workspace decisions, and requires phishing-resistant activation assurance. It always
  creates a post-use review.
- Break glass is an atomic, base-role-authorized, phishing-resistant, step-up-protected activation with a required
  incident reference and detailed justification. It has no prior approval, uses the restricted emergency policy,
  is rate limited and short lived, and always creates a post-use review. The subject cannot review it.

One active activation per account and per session is enforced both in application checks and generated-key MySQL
unique constraints. Activation clears the normal selected workspace in the same transaction and increments Tenant
Context version. Expiry is checked synchronously in authorization/context resolution; the scheduler records lifecycle
transition without extending access. Revocation and early end clear the selected context; a prior normal context is
never restored. A normal/privileged context conflict safely revokes the exceptional activation.

## Persistence, notifications, and maintenance

The three B08 migrations create seeded permission policies; immutable request-permission snapshots and approvals;
session-bound activations; append-oriented privileged lifecycle events; and post-use reviews. All records have
explicit status transitions, positive versions, MySQL foreign keys, scope/type checks, and restrictive deletion
rules. `security:privileged-access:verify` checks catalog counts, policy exactness, prohibited policies, uniqueness
indexes, schema/seed state, active expiry, and normal/privileged context conflicts. The readiness contribution is
generic externally and fail-closed internally.

Safe account-security notification intents are generated with deterministic deduplication keys. They contain event
category only; they exclude ticket/incident text, justifications, permission lists, workspace identifiers, contact
values, session values, and authentication evidence. `security.privileged_access.maintain` runs every 60 seconds,
in bounded batches, to expire stale requests and activations, create required reviews, and mark overdue reviews.
It is a scheduler handler, not an HTTP route, a browser feature, or a durable queue.

## Presentation and verification

The server-rendered inventory and request, approval, activation, revocation, and review flows are protected by
authentication, CSRF, idempotency where needed, generic safe errors, and no-store responses. English and Arabic
catalogues are authoritative, with RTL through normal document direction. Confirmation routes retain full-page
form fallbacks; progressive enhanced forms do not blindly retry mutations. A privileged page exposes the exact
active-access state and a direct end-access operation.

Completion evidence requires real MySQL migration/seed/constraint verification, unit and HTTP coverage,
authorization and Tenant Context regressions, scheduler checks, architecture and static analysis, frontend quality,
release verification, and P0/P1 freeze rechecks. P2-B09 owns tamper-evident audit integrity and account-state
operations; this module's lifecycle events are not represented as a B09 audit guarantee.
