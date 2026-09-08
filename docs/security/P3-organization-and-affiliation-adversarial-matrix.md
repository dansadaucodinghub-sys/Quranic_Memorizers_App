# P3 Organization and Affiliation Adversarial Matrix

| Abuse case | Control | Evidence |
| --- | --- | --- |
| Cross-Workspace read/update | Workspace predicate and composite relational constraints | Organization and affiliation MySQL integration tests |
| Cross-Organization Unit parent | immutable hierarchy and parent validation | Organization registry integration tests |
| Privileged access used as base role | explicit authorization requirement remains mandatory | P2/P3 security verifier composition |
| Affiliation lifecycle reactivation | terminal-status/version constraints and append-only history | affiliation integration tests |
| Concurrent affiliation mutation | optimistic lock one-winner worker test | `P3OrganizationAffiliationsIntegrationTest` |
| Public staff/roster discovery | no public route family is registered | P3 architecture tests |
