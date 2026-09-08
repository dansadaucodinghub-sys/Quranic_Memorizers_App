# P3 People and Guardianship Adversarial Matrix

| Abuse case | Control | Evidence |
| --- | --- | --- |
| Public ID or registry code used as authority | exact SELF or active Guardianship lookup precedes profile access | `P3PersonRepositorySecurityVerifier` and P3 People MySQL tests |
| Cross-Account dependent read | `guardian_person_id` + ACTIVE status predicate | P3 Person integration tests |
| Revoked Guardian acts | active-status predicate and transaction locking | P3 Person integration tests |
| Last Guardian race | optimistic/version and active-Guardian integrity control | P3 Person integration tests |
| Client-supplied age | server UTC policy and birth-date validation | People profile policy tests |
| Unicode/Bidi name spoofing | normalized input validation and escaped presentation | People profile architecture and input tests |

No legal certification or relationship-verification claim is implemented.
