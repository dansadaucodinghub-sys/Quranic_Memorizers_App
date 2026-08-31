# QMDB-P2-B10 Identity and Tenant Security Hardening Standard

## Status and boundary

This controlled extension records the final P2 hardening work explicitly authorized by the project owner on 2026-08-31.
It introduces no new identity capability, role, tenant type, external integration, automated enforcement policy, or
data-retention rule. QMDB-P2-B09 is complete with committed release and Engineering Freeze evidence; B10 must retain
that baseline and stop before the separately governed P2-CLOSE batch.

## Threat and control reconciliation

| Threat / control | Executable boundary | B10 assurance |
| --- | --- | --- |
| Account takeover; QMDB-CTL-002 | credential, recovery, MFA, passkey, step-up and rate-limit services | Existing abuse, replay and parallel MySQL tests remain mandatory. |
| Session theft/fixation; QMDB-CTL-003 | server-side sessions, secure cookies, rotation, revocation and device state | Existing rotation, expiry, ownership and concurrent-login tests remain mandatory. |
| Cross-workspace access; QMDB-CTL-005 | session-authoritative tenant context, composite membership constraints, tenant-scoped repositories/jobs | B10 reruns the full real-MySQL isolation and concurrency suite. |
| Privilege misuse; QMDB-CTL-004 | role/assurance guards, action-bound step-up and temporary-access state | Authorization, delegation, expiry, review and break-glass regression remains mandatory. |
| Audit tampering; QMDB-CTL-014 | keyed chains, immutable database controls, checkpoints and explicit verifier | Runtime readiness now validates the audit key and database controls; full history verification remains explicit and bounded. |
| Browser/request abuse; QMDB-CTL-007 | CSRF, origin validation, strict methods, no-store protected responses and session guard | The route-derived anonymous full-page/fragment matrix verifies each registered protected route. |

## Audit availability hardening

`SecurityAuditReadinessCheck` depends only on the `SecurityAuditControlVerifier` application port. Its MySQL
implementation validates the configured integrity-key version, required immutability triggers, and the indexes used by
the bounded audit views. Any exception or control error makes public readiness return the existing generic
`not_ready` response.

Readiness deliberately does not replay every historical event chain. That operation is proportional to retained audit
history and is therefore performed through `security:audit:verify` and the release/test evidence path. The design
prevents a health-probe amplification path while keeping full tamper detection explicit, auditable, and fail-closed for
release approval.

## Route and tenant assurance

The B10 HTTP matrix derives its route set from `routes/web.php`. A route is protected unless it is in the explicit,
small public identity/system allowlist. Every protected method is exercised without a session for both a normal request
and a QMDB fragment request. A request must be denied by either the authentication boundary or the earlier CSRF
boundary; neither mode may execute the protected operation or disclose session state.

Tenant assurance remains server-side: URL/query values never establish a workspace context, and MySQL repositories
continue to use authenticated account/session context, exact workspace predicates, composite foreign keys, optimistic
versions, and revalidation in background work. The MySQL suite exercises cross-account workspace selection, assignment,
background-job and revocation denial plus competing context and authorization mutations.

## Closed executable inventories

`ProductionRouteSecurityPolicyCatalog` is the single closed policy inventory for all registered production routes.
`security:routes:verify` compares it to the route collection and fails on an unclassified or retired policy entry,
missing CSRF metadata for a non-GET route, invalid assurance/step-up metadata, unsafe public administrative handler,
or token-bearing cache-policy omission. `RouteCollection` rejects duplicate names and ambiguous static/dynamic
precedence during route construction. The catalog is intentionally a verifier, not a parallel route-registration
mechanism.

`TenantRepositorySecurityVerifier` is the corresponding closed inventory for the P2 tenant-owned repository ports.
It checks that each repository receives the exact `TenantContext`, that its MySQL implementation has a workspace
predicate and trusted workspace resolver, and that it does not begin an unscoped public-ID lookup. Deliberately global
repositories are named explicitly. Any future tenant-owned repository must be added to this inventory as part of its
own implementation batch.

`security:p2:verify` composes the bounded authorization catalog, tenant-context schema, tenant-repository,
privileged-access schema, audit-control and route-security verifiers. It performs no mutation and no full historical
audit scan; the latter remains `security:audit:verify`.

## Bounded performance evidence

Audit result pages remain capped at 100 records. The verifier now requires the stream, code, actor, subject, workspace
and severity ordering indexes used by bounded audit inspection paths; index drift makes readiness fail. This is local
structural evidence against an accidental unbounded query regression, not a national-capacity claim. Numerical service
targets, workload tiers and capacity acceptance remain controlled by OD-036.

`P2SecurityPerformanceBaselineTest` performs 1,000 keyed audit-hash operations, 1,000 tenant cache-key
constructions, and 1,000 authorization decisions under a five-second local test bound for each loop.
`P2SecurityAuditIntegrationTest` performs 100 bounded MySQL audit-list queries under the same local test bound.
These checks are regression tripwires only; they do not state a production latency or capacity SLO.

## Traceability artifacts

- [Threat-model reconciliation](../security/P2-threat-model-reconciliation.md)
- [Authorization and assurance matrix](../security/P2-authorization-and-assurance-matrix.md)
- [Route-security matrix](../security/P2-route-security-matrix.md)
- [Tenant-isolation adversarial matrix](../security/P2-tenant-isolation-adversarial-matrix.md)
- [Privileged-access adversarial matrix](../security/P2-privileged-access-adversarial-matrix.md)
- [Audit-integrity adversarial matrix](../security/P2-audit-integrity-adversarial-matrix.md)
- [Authentication-abuse matrix](../security/P2-authentication-abuse-matrix.md)
- [Bounded performance baseline](../operations/P2-security-performance-baseline.md)
- [Security defect register](reports/QMDB-P2-B10-security-defect-register.md)

## Completion conditions

Complete B10 only after the focused route/architecture tests, full real-MySQL suite, static analysis, style, security,
clean-install, release, and Engineering Freeze gates pass from committed governed source. Do not authorize P2 closeout
while a mandatory B10 gate is unresolved.
