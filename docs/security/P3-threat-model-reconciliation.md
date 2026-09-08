# P3 Threat-Model Reconciliation

## Scope and outcome

`QMDB-P3-B06` reconciles the implemented P3 Geography, Person, Organization, affiliation and identity-resolution boundaries against the frozen P2 security baseline. The result is **no unresolved Critical or High code defect**. The B06 verifier is deliberately read-only and composes P2 authorization, route, tenant-repository and audit controls with the five P3 readiness checks and a Person repository scope check.

| Threat | Boundary and executable evidence | Result |
| --- | --- | --- |
| Geography tampering or hierarchy corruption | Dataset validator; `reference:geography:verify`; `GeographyReferenceIntegrationTest` | Mitigated |
| Public Person discovery or public-ID authority | Private routes; account/Guardian-scoped repository queries; `P3SecurityHardeningArchitectureTest` | Mitigated |
| Guardian overreach or revocation race | active Guardianship predicates, transaction locks and P3 People/MySQL tests | Mitigated |
| Cross-workspace Organization access | workspace predicate in registry and affiliation repositories; P3 Organization MySQL tests | Mitigated |
| Claim pairing disclosure or replay | HMAC-only secret persistence, one-time state, rate limits and B05 identity tests | Mitigated |
| Duplicate merge without consent or rollback | explicit consent preflight, one orchestrating transaction, immutable aliases and B05 constraints | Mitigated |
| Audit or browser storage disclosure | private/no-store controllers, redacted audit contracts and static browser-storage checks | Mitigated |
| Hosted infrastructure and independent testing | deferred operational evidence register | Deferred operational |

No mitigation changes P2 authentication, authorization, session, CSRF, rate-limit, idempotency, audit or tenant semantics.
