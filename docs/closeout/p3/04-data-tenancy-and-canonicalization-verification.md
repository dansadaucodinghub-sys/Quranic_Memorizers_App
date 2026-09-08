# P3 Data, Tenancy and Canonicalization Verification

Geography and People are global; Organizations and Organization-side affiliation records are Workspace-owned. The schema uses MySQL constraints, restrictive relationships, active/open markers, append-only lifecycle history and version checks. Canonicalization runs within one caller-owned transaction: it retires the source Person and writes an immutable alias; it never deletes a Person or transfers an Account.

Final serial MySQL execution must confirm the registered migration/seed ledger, integrity constraints, cross-workspace denial and canonical schema restore before the P3 freeze is generated.
