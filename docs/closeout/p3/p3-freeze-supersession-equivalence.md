# P3 Freeze Supersession Equivalence

`QMDB-P3-FRZ-002` supersedes `QMDB-P3-FRZ-001` only to govern exact additive post-P3 extensions.
`QMDB-P3-FRZ-001` remains the immutable historical P3 evidence.

| Changed classification | Paths / purpose | Product effect |
| --- | --- | --- |
| FREEZE_POLICY | P2 compatibility prefix and P3 successor policy | Governance only |
| FREEZE_VERIFIER | Historical and effective P3 verifiers | Governance only |
| EXTENSION_LEDGER | `post-freeze-extensions.yaml` | Empty governed genesis |
| CHANGE_REQUEST | `QMDB-CR-002` | Owner-authorized governance correction |
| GOVERNANCE_TEST | P3 successor freeze tests | Verification only |
| CLOSEOUT_EVIDENCE | This record and successor manifest | Evidence only |
| PROJECT_STATE | Current phase correction | Ledger truth only |

No P3 product behavior, domain model, authentication, authorization, tenant semantics, Person authority,
Organization semantics, affiliation consent, claim semantics, canonicalization semantics, applied migration, or
applied seed is changed. The historical verifier checks every original P3 product checksum and verifies the original
governance bytes from its recorded Git source revision. The effective verifier requires old entries to remain exact
unless a ledger entry proves an append-only authorized extension.
