# P3 Data Scope and Repository Matrix

| Repository category | Scope proof | B06 verifier result |
| --- | --- | --- |
| Geography reference | global, local governed dataset and projection | `reference:geography:verify` PASS |
| Account SELF Person profile | active `people_account_links.account_id`, `SELF`, `ACTIVE` predicate | `person_repository_scopes` PASS |
| Guardian dependent Person profile | active `people_guardianships.guardian_person_id` predicate before public ID | `person_repository_scopes` PASS |
| Organization registry | `workspace_id=:workspace_id` | P3 readiness PASS; P3 Organization MySQL isolation evidence |
| Organization affiliation | `workspace_id=:workspace_id` and Organization join scope | P3 readiness PASS; P3 affiliation MySQL isolation evidence |
| Identity resolution | exact Account, Guardian or Platform application-service authority | readiness PASS; no discovery repository |

Public IDs and registry codes are opaque references only. They are never authorization grants. No unclassified P3 repository is accepted by B06.
