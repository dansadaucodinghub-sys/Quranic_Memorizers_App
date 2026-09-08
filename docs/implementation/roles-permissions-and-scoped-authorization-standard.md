# Roles, Permissions, and Scoped Authorization Standard

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Product baseline | QMDB-BL-001 / QMDB-P0-FRZ-001 |
| Engineering baseline | QMDB-P1-FRZ-001 |
| Batch | QMDB-P2-B06 |
| Owning module | `security.authorization` |
| Status | Implemented and executable |
| Last updated | 2026-08-28 |

## Purpose and boundary

This standard governs the first production authorization foundation. Authentication establishes an account and session;
authentication assurance describes how strongly that session was authenticated. Neither grants a permission. A role
assignment associates an active account or active workspace membership with one explicit role. An authorization
decision evaluates one explicit permission in one exact scope.

The B06 scope is limited to platform and workspace authorization. Geography, organization, competition, judging,
certificate, media, temporary privilege, support access, break-glass access, custom roles, browser workspace switching,
and business-resource policies remain in their owning batches. Future scopes require typed relational models and policy
adapters; untyped or polymorphic scope identifiers are prohibited.

## Canonical terminology and identifiers

- `PermissionCode` is a validated immutable code such as `workspace.memberships.manage`; wildcard syntax is rejected.
- `RoleCode` is a validated immutable system-role code. There is no inheritance or dynamic policy expression.
- `PLATFORM` applies to account-level platform assignments only.
- `WORKSPACE` applies to membership assignments inside one trusted workspace only.
- Public permission, role, assignment, account, and workspace IDs are locators, never authorization evidence.
- Internal database identifiers remain persistence details and are never accepted from public clients as authority.

## Controlled catalog

`AuthorizationCatalogRegistry::foundational()` is the code-owned catalog. The production seed
`20260826020100_seed_foundational_authorization_catalog` creates exactly 10 permission definitions, 7 system roles, and
27 explicit same-scope mappings. It creates no account or membership assignment. Catalog rows use fixed UUIDv7 public
IDs, positive versions, status and scope checks, immutable codes, and a checksum-governed seed.

Permissions:

```text
platform.authorization.view
platform.authorization.assign
platform.security.view

workspace.authorization.view
workspace.authorization.assign
workspace.memberships.view
workspace.memberships.manage
workspace.security.view
workspace.settings.view
workspace.settings.manage
```

System roles:

```text
platform.security_administrator
platform.authorization_auditor

workspace.owner
workspace.administrator
workspace.security_manager
workspace.membership_manager
workspace.viewer
```

Catalog mutation outside a controlled migration/seed and governance review is prohibited. Readiness and
`security:authorization:verify` detect missing or unexpected definitions, mappings, statuses, assurance values,
orphan mappings, duplicate active assignments, and protected-role invariant failure. Verification never repairs or seeds
data.

## Assurance requirements

Every permission declares a minimum server-issued assurance level. `platform.authorization.assign` requires
`PHISHING_RESISTANT`; other platform permissions require `MULTI_FACTOR`. Workspace view-only permissions use the
catalog's explicit `PRIMARY` requirements; workspace assignment, membership management, security, and settings
management require `MULTI_FACTOR`. Stronger assurance may satisfy a lower requirement, but assurance without an active
mapped assignment grants nothing.

Role mutations additionally consume an action-bound B05 step-up grant in the same transaction:

```text
AUTHORIZATION_PLATFORM_ROLE_ASSIGN   PHISHING_RESISTANT
AUTHORIZATION_PLATFORM_ROLE_REVOKE   PHISHING_RESISTANT
AUTHORIZATION_WORKSPACE_ROLE_ASSIGN  MULTI_FACTOR
AUTHORIZATION_WORKSPACE_ROLE_REVOKE  MULTI_FACTOR
```

The grant is bound to the actor account, current session, exact action, assurance, status, and expiry. Wrong-action,
expired, consumed, or another-session grants fail closed.

## Decision model

`RoleBasedAuthorizationService` denies by default and returns a typed internal decision. A platform allow requires an
active account, active permission with platform scope, sufficient assurance, an active platform assignment, an active
platform role, and an explicit role-permission mapping. A workspace allow additionally requires a trusted non-system
`TenantContext`, an active workspace, and an active membership for that account in the exact workspace.

Unknown permissions, wrong scope, inactive state, missing or revoked assignment, retired role or permission, and
insufficient assurance always deny. Workspace A evidence is never queried or interpreted through Workspace B context.
No authorization decision is cached in B06.

`AuthorizationGuard` converts a denied internal decision to `AuthorizationDeniedException`. The shared exception
middleware maps only this safe exception contract to a generic 403 problem response with the request ID and normal
security headers. Permission codes, role codes, workspace IDs, internal reason codes, exception messages, and stack
traces are excluded from the public response.

## Assignment lifecycle and integrity

Platform assignments bind an active account to a platform role. Workspace assignments bind an active membership to a
workspace role and store the exact workspace relation. Both use UUIDv7 public IDs, `ACTIVE`/`REVOKED` status, optimistic
version, actor kind, controlled reason code, assignment/revocation timestamps, and correlation ID. Revocation updates
the existing record; historical assignments are not deleted.

MySQL enforces role/scope alignment, valid lifecycle fields, positive versions, foreign keys, one active assignment for
each subject-role pair, and no cascade deletion. Workspace assignments use a composite `(workspace_id, membership_id)`
foreign key so a membership from another workspace cannot be attached even if an application predicate is defective.
Repositories require `TenantContext` for every workspace lookup or mutation.

Supported actor kinds are `ACCOUNT` and `SYSTEM`. Account-driven commands cannot select system-only bootstrap reason
codes. Controlled reason codes record security administration, security response, membership state change, role
retirement, or the two system initialization purposes. Initial platform-administrator bootstrap remains an unresolved
production operational decision; no unrestricted bootstrap route, role, or seed assignment exists. Initial workspace
owner creation belongs to controlled workspace provisioning.

## Privilege-escalation controls

The actor must already possess the assignment permission and every active permission mapped to the target role.
Delegation is revalidated inside the mutation transaction after locking the actor's current assignments. Revoked actor
authority cannot authorize a later write. The target account, workspace, membership, role, and scope are revalidated;
database uniqueness resolves duplicate races.

Revoking `platform.security_administrator` locks and counts all usable active platform administrators and refuses to
remove the last one. Revoking `workspace.owner` performs the equivalent exact-workspace check over active memberships
and accounts. Independent-process tests prove concurrent revocations leave one usable protected principal.

## Notifications and operational logging

Successful assignment and revocation atomically create one durable notification intent for the affected account using
the existing `identity.security_notifications.deliver` scheduled task. Types are
`PLATFORM_ROLE_ASSIGNED`, `PLATFORM_ROLE_REVOKED`, `WORKSPACE_ROLE_ASSIGNED`, and `WORKSPACE_ROLE_REVOKED`.
Deduplication binds event type, assignment public ID, and account public ID. Intent failure rolls back the privilege
mutation and step-up consumption. Transport remains at least once; exactly-once SMTP is not claimed.

English and Arabic messages identify only the safe event category and remediation guidance. They contain no permission
list, role code, workspace identifier, internal ID, contact value outside the mail boundary, session/device token,
factor secret, recovery code, passkey assertion, challenge, or step-up evidence.

Operational events use validated dotted names and bounded identifiers. High-volume decisions log allow/deny outcome,
permission code, scope type, and safe correlation only; they exclude contact data and authentication secrets. These
logs and notification rows are operational evidence, not the future authoritative audit ledger.

## Verification and regression contract

The catalog must be installed and verified with:

```powershell
php bin/console db:migrate:plan
php bin/console db:migrate
php bin/console db:migrate:status
php bin/console db:seed
php bin/console db:seed:status
php bin/console security:authorization:verify
```

Completion requires unit, architecture, safe-403, notification, real MySQL constraint/decision, cross-workspace,
rollback/reapply, seed-idempotency, and independent-process concurrency tests. Repository quality, frontend regression,
scanners, SBOM/licence, release-artifact, and local CI gates remain mandatory. Missing catalog migrations or seed,
catalog drift, or protected-role invariant failure makes readiness return generic `not_ready` and makes the CLI return
non-zero.

## Future boundaries

P2-B07 owns HTTP Tenant Context resolution, workspace switching, tenant-aware repositories, cache keys, jobs, exports,
and existence-leakage controls. Later governed work owns custom roles and naming, role/permission retirement authority,
approval or dual-control workflows, temporary/support/break-glass grants, decision caching/analytics, retention,
privilege review, business-module permissions, and the authoritative audit ledger. None is implied by this foundation.

## P2-B07 Tenant Context authorization integration

Account Workspace Tenant Context supplies the exact server-resolved workspace and membership boundary for runtime
workspace operations. Selection grants no role or permission, raises no assurance, and provides no platform authority.
Every decision still requires active account, workspace, membership, role assignment, role, permission, and declared
assurance. A client workspace public ID is only a selection lookup and cannot override resolved context.

## P2-B08 exceptional authorization integration

P2-B08 adds a trusted internal decision source after normal B06 role evaluation: `TEMPORARY_PRIVILEGE`,
`SUPPORT_ACCESS`, or `BREAK_GLASS`. It is available only from an active, unexpired, same-account, same-session
privileged-access activation whose immutable permission snapshot exactly matches the requested scope. Normal roles
remain separately evaluated. The public deny response remains generic and never exposes this source.

All exceptional-access administration remains base-role-only through `BaseRoleAuthorizationGuard`. No temporary,
support, or break-glass policy permits authorization assignment, role mutation, privileged request/approval/review
administration, wildcard permission, or a policy-management action. The controlled seed has 27 permissions, 9 roles,
73 mappings, and 20 exact exceptional permission policies; `security:authorization:verify` and
`security:privileged-access:verify` fail closed on catalog or policy drift.

## P2-B09 security-audit integration

P2-B09 extends the governed catalog to 32 permissions, 9 roles, and 83 mappings. Platform and Workspace role
assignment and revocation append an authoritative, keyed audit event inside the same transaction as the role mutation,
step-up consumption, and notification intent. The event uses the Platform or exact Workspace stream respectively and
contains only the assignment public ID, role code, scope, reason, and opaque correlation ID. No exceptional access
source can authorize account suspension or reactivation; those operations use the base-role guard and phishing-resistant
action-bound step-up only.

## P2-B10 authorization assurance matrix

The B10 aggregate verifier composes the existing catalog verifier with Tenant Context, privileged-access, audit, and
route verifiers without issuing a grant or mutating data. The authorization matrix retains deny-by-default, exact
scope, exact active assignment, and assurance-as-necessary-not-sufficient behavior under normal and exceptional
authorization sources.

## P3-B05 identity-resolution authorization extension

The closed catalog adds five Platform permissions: `platform.people_profiles.view`,
`platform.people_profile_claims.authorize`, `platform.people_profile_verifications.manage`,
`platform.people_duplicates.view`, and `platform.people_duplicates.resolve`. It adds the system Platform role
`platform.people_profile_reviewer` and bounded mappings for Security Administrator, the reviewer, and Authorization
Auditor. Claim authorization, verification management, and duplicate resolution require the catalog's stated
multi-factor or phishing-resistant assurance. Privileged Access is not an identity-resolution administrator, and no
claim, verification assertion, duplicate case, canonical alias, Organization affiliation, or Person role creates a
permission or Workspace membership.
