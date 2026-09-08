# People, Geography and Organization Security Hardening Standard

## B06 security boundary

P3 remains a composition of global governed Geography, global private Person authority, Workspace-owned Organizations, consent-bound affiliations and private identity resolution. B06 adds a bounded `security:p3:verify` runtime verifier; it creates no workflow, migration, seed, table, route or P3 closeout freeze.

The verifier is read-only. It invokes P2 structural security verification, Geography, People, Organization, affiliation and identity-resolution readiness checks, and a source-only Person repository scope check. It returns a non-zero result on invalid components and outputs bounded component statuses only.

## Required invariants

- Public IDs and registry codes never authorize a Person or Organization operation.
- SELF links and `PROFILE_MANAGEMENT` Guardianships are exact, active authority records.
- Organization and affiliation access always includes trusted Workspace scope.
- Pairing secrets remain hash-only and canonicalization retires a source Person instead of deleting or transferring Accounts.
- P2 CSRF, idempotency, rate limits, step-up, audit and tenant isolation remain unchanged.
