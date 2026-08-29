# Tenant Context, Workspace Switching, and Data Access Standard

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Product baseline | QMDB-BL-001 / QMDB-P0-FRZ-001 |
| Engineering baseline | QMDB-P1-FRZ-001 |
| Batch | QMDB-P2-B07 |
| Status | Implemented and executable |
| Last updated | 2026-08-28 |

## Terminology and authority

Authenticated Account Context proves the account, session, device, authentication time, and assurance established by
the session subsystem. Account Workspace Tenant Context is a separate immutable server-resolved value that binds that
account and that exact session to one active workspace membership. Authorization is a third concern: selecting a
workspace grants no role, permission, platform authority, or authentication assurance.

The authoritative selection is stored only in `user_sessions`. New sessions have no selection. A workspace ID in a
URL or form is only a bounded lookup selector; it cannot construct Tenant Context. No workspace cookie,
`X-Workspace-ID`, localStorage, sessionStorage, device-cookie field, mutable singleton, global current workspace, or
first-available fallback is authoritative.

## Session selection and integrity

`20260826011900_add_session_bound_tenant_context` adds nullable `selected_workspace_id`, nullable
`selected_membership_id`, positive `tenant_context_version`, and nullable `tenant_context_selected_at`. The selection
columns and timestamp are all null or all present. The composite foreign key
`(selected_workspace_id, account_id, selected_membership_id)` references the candidate key
`(workspace_id, user_account_id, id)` on `workspace_memberships`; MySQL therefore rejects cross-account and
cross-workspace composition.

Selection and clearing lock the active session, require the caller’s expected Tenant Context version, validate an
ACTIVE workspace and ACTIVE membership for the authenticated account, then increment only
`tenant_context_version`. The authentication session `version`, token hash, rotation state, device cookie, and
assurance are unchanged. A stale expected version returns `TENANT_CONTEXT_STALE`; an ineligible or foreign workspace
returns the non-enumerating `TENANT_CONTEXT_UNAVAILABLE` response.

## Resolution and middleware

Middleware order is authentication, Tenant Context, then routing. `TenantContextMiddleware` reads the authenticated
session’s stored selection, joins the exact account/workspace/membership relationship, and attaches only trusted
values as:

```text
qmdb.tenant_context
qmdb.tenant_context_version
```

Authenticated responses carry `X-QMDB-Tenant-Context-Version`. A normal `/workspace` request without context redirects
to `/account/workspaces?context_required=1`; a fragment request receives `TENANT_CONTEXT_REQUIRED`. If the account, session, workspace,
or membership is no longer active, the stored selection cannot resolve. A structurally present but inactive selection
is cleared atomically, its context version increments, and no replacement workspace is chosen.

## Workspace interaction

The routes are `GET /account/workspaces`, `POST /account/workspaces/switch`,
`POST /account/workspaces/clear`, and `GET /workspace`. Inventory is account-scoped, bounded, private, no-store, and
contains no other account’s workspaces, internal IDs, roles, or permissions. Mutations require authentication,
same-origin CSRF, form media type, a workspace public ID where applicable, and the expected context version.

The UI uses accessible ordinary forms and no modal. Non-JavaScript success uses 303 navigation. Progressive success
uses a same-origin `X-QMDB-Navigate` instruction. Busy state and duplicate-submission protection reuse the shared form
controller; failed mutations are never retried automatically. English is LTR and Arabic is RTL.

Cross-tab signaling uses `BroadcastChannel` only when available. Messages are limited to a fixed type and positive
context version. They contain no workspace name or identifier, membership, role, permission, contact data, or internal
ID and cannot mutate server authority.

## Tenant-aware data access

Tenant-owned repositories implement `TenantScopedRepository`. Their contracts require a trusted Tenant Context and
their SQL contains exact `workspace_id` scope for reads and writes. Public and internal resource IDs are additional
selectors, never substitutes for tenant scope. Cross-workspace reads return not found and cross-workspace updates
affect zero rows. Global account repositories stay explicitly global; account workspace inventory is intentionally
account-scoped so a user can choose among their active memberships.

Workspace authorization still requires active persisted role and permission evidence. Tenant Context selects the
scope in which authorization is evaluated; it does not create evidence. Platform authorization remains platform
scoped.

## Background, cache, and future export boundaries

`AccountTenantBoundBackgroundJob` requires server-owned Account, Workspace, and Membership references. The execution
resolver revalidates all three and returns no context for inactive or mismatched state. No production tenant job is
registered, the null production job source remains, no durable queue is added, and job payloads are not logged.

`TenantScopedCacheKeyFactory` requires trusted Tenant Context, validates a bounded namespace, includes workspace public ID
and an explicit positive cache-schema version, and hashes unbounded resource material. It provides collision prevention only and grants no
authorization. No Redis or persistent cache is introduced.

Future tenant export requests must implement `TenantScopedExportRequest`, retain the trusted context for the complete
operation, and revalidate before deferred execution. B07 introduces no generic export subsystem.

## Logging, privacy, and future boundaries

Logs must not contain workspace names, membership inventories, context objects, session tokens, cookies, or job
payloads. Public responses contain no internal IDs. Tenant context objects reject serialization and redact account and
session identity in debug output.

Workspace creation, provisioning, default selection, organizations, geography, competitions, custom roles, temporary
privileges, support access, and break-glass access remain outside B07. P2-B08 owns temporary, support, and emergency
privilege controls and may not weaken these tenant-resolution rules.
