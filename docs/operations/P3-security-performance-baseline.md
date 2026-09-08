# P3 Security and Performance Baseline

## Environment

Local automated evidence uses PHP 8.5.10 and MySQL 8.4.11 on Windows. Data is synthetic and small; timings are indicative only and are not production-capacity claims.

## Bounded evidence

| Operation/control | Bound | Evidence |
| --- | --- | --- |
| Geography validation | governed dataset and exact hierarchy counts | `reference:geography:verify` through `security:p3:verify` |
| Person self/dependent authority | exact Account or active Guardian predicate | P3 Person scope verifier and MySQL integration tests |
| Organization/affiliation inventory | Workspace predicate and page-bounded application paths | P3 Organization/Affiliation integration tests |
| Pairing lookup | selector unique index | `P3IdentityResolutionConstraintIntegrationTest` `EXPLAIN FORMAT=JSON` |
| Duplicate consent lookup | authority + decision index | identity-resolution constraint test `EXPLAIN FORMAT=JSON` |
| Mutation contention | one optimistic winner | P3 affiliation and identity-resolution worker tests |

The suite hard-fails on missing constraints or cross-scope persistence. It does not claim large-production-volume load evidence; that is deferred operational evidence.
