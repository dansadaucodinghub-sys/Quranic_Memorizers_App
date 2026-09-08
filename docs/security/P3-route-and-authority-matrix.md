# P3 Route and Authority Matrix

All production routes are closed-policy classified by `security:routes:verify`. B06 additionally confirms `security:p3:verify` is registered and read-only.

| Route family | Authority | Cache and mutation control | Discovery boundary |
| --- | --- | --- | --- |
| Nigerian Geography | public read-only reference visitor | bounded query, representation-safe cache controls | no Account, Workspace or Person payload |
| Person self profile | authenticated Account with ACTIVE SELF link | `private, no-store`; CSRF/idempotency on mutation | no public Person lookup |
| Dependent profile | authenticated Account with active `PROFILE_MANAGEMENT` Guardianship | private; transaction revalidation | public ID is an identifier only |
| Workspace Organizations and units | active Workspace context and exact permission | private; CSRF/idempotency | all repository reads include Workspace scope |
| Organization affiliations | Organization-side authorized staff or exact Account/Guardian response authority | private; lifecycle/version checks | pending Person data is not public |
| Claims, verification and duplicates | exact Account, Guardian or Platform review role as applicable | private/no-store, CSRF, idempotency and step-up where configured | registry/public IDs do not grant access |

Unsafe GET mutations, unclassified routes and public Person/roster routes are prohibited and architecture-tested.
