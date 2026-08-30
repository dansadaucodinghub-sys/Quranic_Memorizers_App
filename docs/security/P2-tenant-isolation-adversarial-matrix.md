# P2 Tenant-Isolation Adversarial Matrix

| Boundary | Attack | Source control | Executable evidence | Result |
| --- | --- | --- | --- | --- |
| New session | Inherit workspace automatically | Session context starts empty | `P2TenantContextIntegrationTest` | Denied / no context |
| Client request | Supply `X-Workspace-ID`, query or internal ID | Only server session selection is authoritative | tenant HTTP tests and `MySqlTenantContextSchemaVerifier` | Ignored or rejected |
| Context version | Replay stale/future selection | Optimistic version required on switch and clear | tenant HTTP/MySQL tests | Conflict / no mutation |
| Two sessions | Reuse another session context | Account and session resolved together | tenant integration suite | Context remains independent |
| Membership/workspace state | Use inactive relationship | Resolver invalidates and clears context | tenant integration suite | Context unavailable |
| Tenant role assignment | Read/update by public ID alone | Marker plus exact workspace predicate | repository verifier and authorization MySQL suite | Cross-workspace denied |
| Workspace membership | Unscoped list | Trusted `TenantContext` first parameter and bounded query | repository verifier | Scope required |
| Workspace inventory | Read another account memberships | Account predicate and bounded cursor | tenant verifier/B07 integration | Account-scoped only |
| Background work | Serialize internal context/session/roles | Public references re-resolved at execution | tenancy background tests | Invalid relation fails closed |
| Cache key | Cross-workspace collision or PII | Workspace namespace and hashed material | cache-key tests | Distinct bounded key |
| Privileged transition | Restore former normal context | Activation/end clear conflicting contexts | privileged-access MySQL suite | No union/restoration |

## Closed repository inventory

`security:tenant-repositories:verify` checks the only P2 repositories that own workspace-scoped records.

| Contract | Implementation | Trusted-context methods | SQL boundary |
| --- | --- | ---: | --- |
| `WorkspaceMembershipRepository` | `MySqlWorkspaceMembershipRepository` | 2 | Context-derived `workspace_id` predicate / insert binding |
| `WorkspaceRoleAssignmentRepository` | `MySqlWorkspaceRoleAssignmentRepository` | 7 | Context-derived `assignment.workspace_id` predicate for every lookup/list/update |

The verifier makes `WorkspaceRepository`, the session-context repository, and the tenant-bound background re-resolution repository explicit globals. A future tenant-owned repository is a verifier failure until it is added to this closed inventory with a context-bearing contract and exact scope evidence.
