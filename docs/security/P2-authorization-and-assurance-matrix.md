# P2 Authorization and Assurance Matrix

The executable catalog is `AuthorizationCatalogRegistry::withAuditAccountState()`. The verifier checks its seeded database representation; the P2 aggregate command includes that result. Permission is never inferred from a public identifier, higher assurance, a privileged activation, or another workspace.

| Control | Owning module | Unit evidence | MySQL / integration evidence | Architecture / operational evidence | Status |
| --- | --- | --- | --- | --- | --- |
| Default deny and unknown Permission denial | security.authorization | `AuthorizationDecisionTest` | `P2SecurityAuthorizationIntegrationTest` | `security:authorization:verify` | PASS |
| Exact platform/workspace scope | security.authorization | `AuthorizationDecisionTest` | authorization integration suite | `P2RouteSecurityPolicyTest` | PASS |
| Assurance supplements, never replaces, Permission | security.authorization | decision/assurance tests | authorization integration suite | catalog verifier | PASS |
| No wildcard or implicit inheritance | security.authorization | catalog tests | catalog seed verification | architecture policy | PASS |
| Platform role cannot grant workspace Permission | security.authorization | decision tests | authorization integration suite | catalog verifier | PASS |
| Workspace role cannot grant platform Permission | security.authorization | decision tests | authorization integration suite | catalog verifier | PASS |
| Delegation subset and scope control | security.authorization | delegation validator tests | administration/concurrency suites | exact step-up enum | PASS |
| Last platform administrator protection | security.authorization | authorization unit tests | authorization concurrency suite | transaction/lock inspection | PASS |
| Last workspace owner protection | authorization, tenancy | authorization unit tests | authorization concurrency suite | tenant repository verifier | PASS |
| Privileged source cannot administer roles/access/accounts | privileged access | privileged policy tests | administration/account-state suites | catalog source policy | PASS |

## Route-held base-role requirements

| Route | Permission | Required assurance | Exact step-up action for mutation | Evidence |
| --- | --- | --- | --- | --- |
| `platform.security.accounts.detail` | `platform.accounts.view` | `MULTI_FACTOR` | Not applicable | route verifier and account-state integration |
| `platform.security.accounts.suspend` | `platform.accounts.suspend` | `PHISHING_RESISTANT` | `ACCOUNT_SUSPEND` | route verifier and account-state integration |
| `platform.security.accounts.reactivate` | `platform.accounts.reactivate` | `PHISHING_RESISTANT` | `ACCOUNT_REACTIVATE` | route verifier and account-state integration |
| `platform.security.events` | `platform.security_events.view` | `MULTI_FACTOR` | Not applicable | route verifier and audit viewer tests |
| `platform.security.events.detail` | `platform.security_events.view` | `MULTI_FACTOR` | Not applicable | route verifier and audit viewer tests |
| `platform.security.audit` | `platform.audit.verify` | `PHISHING_RESISTANT` | Not applicable | route verifier and audit verifier |

Other sensitive mutations are authentication ceremonies with service-enforced assurance, or are governed by the B08 privileged-access policy and action-bound Step-Up requirement. `security:p2:verify` remains read-only and combines catalog, tenant, privileged-access, audit-control and route-policy checks.
