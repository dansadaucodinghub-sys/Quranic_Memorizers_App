# QMDB-P4 Requirement Inventory

| Field | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Operation | QMDB-P4-B01-SCOPE-RECOVERY-EXEC |
| P4 identity | Qur’an Reference and Governance |
| Requirement count | 10 |
| Existing batch assignment | `QMDB-P4-BACKLOG` for every item |
| Competition relation | P5-only; excluded from this inventory |

## Inventory

| Requirement ID | Exact requirement / title | Primary dependency | Source data | Capability boundary | Status |
| --- | --- | --- | --- | --- | --- |
| QMDB-DR-011 | Canonical Quran release integrity | QMDB-QRF-001 through QMDB-QRF-003 | Approved release source | Versioned, checksummed, governed release data | Blocked by OD-054/OD-063 |
| QMDB-QRF-001 | Every canonical Qur’an Text Release shall have an immutable release identifier, Reading context, source/provenance, checksum, qualified review evidence, activation state, and supersession relationship where applicable. | Qualified source, collation, review authority | Required | Release-integrity root | Blocked by OD-054/OD-063 |
| QMDB-QRF-002 | Reserve canonical Qur’an text release activation or supersession for separately authorized qualified review, denying normal administrators any edit capability. | QMDB-QRF-001 and qualified authority | Required | Restricted change governance | Blocked by OD-063 |
| QMDB-QRF-003 | Model Reading, Surah, Ayah, Juz, Hizb, Rubʿ, Page Reference, Passage Range, Tajwīd Rule Taxonomy, and Competition Mistake Taxonomy as distinct version-linked concepts. | Identified release/Reading | Required for structural content | Canonical-reference distinction | Blocked by OD-054/OD-063; competition taxonomy remains P6-dependent |
| QMDB-FR-QRF-001 | Import a structured Qur’an Text Release. | QMDB-QRF-001; OD-054; OD-063 | Required | Quarantined immutable candidate import | Blocked |
| QMDB-FR-QRF-002 | Separate canonical and search-normalized text. | QMDB-FR-QRF-001; OD-055 | Required | Rebuildable, non-canonical projection | Blocked |
| QMDB-FR-QRF-003 | Perform technical and dual qualified review. | Imported candidate and qualified review authority | Required | Separation-of-duties review | Blocked |
| QMDB-FR-QRF-004 | Activate and bind approved releases. | Technical validation and dual review | Required | Transactional activation | Blocked |
| QMDB-FR-QRF-005 | Correct, supersede, and preserve a release. | Active immutable predecessor | Required | Forward-only correction | Blocked |
| QMDB-FR-QRF-006 | Restrict modification and expose safe reference search. | Active/historical release and approved normalization | Required | Read-only projection and write denial | Blocked |

## Dependency graph summary

```text
approved source + OD-054 + qualified review authority
  -> QMDB-QRF-001 / QMDB-FR-QRF-001
  -> QMDB-FR-QRF-003
  -> QMDB-FR-QRF-004
  -> QMDB-FR-QRF-005

QMDB-FR-QRF-001 + OD-055
  -> QMDB-FR-QRF-002
  -> QMDB-FR-QRF-006
```

`QMDB-QRF-003` must remain version-linked to the identified release and does not independently authorize a generic
taxonomy or a Competition artifact. No P5 requirement appears in this inventory.

## Required future evidence

Before an executable B01 can be authorized, the selected requirements require approved provenance, source checksums,
deterministic release manifest validation, MySQL migration/constraint evidence, immutable lifecycle tests, qualified
review and authorization tests, and release verification. The scope recovery cannot substitute fixtures, remembered
counts, or a downloaded source for those decisions.
