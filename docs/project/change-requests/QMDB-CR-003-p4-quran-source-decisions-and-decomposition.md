# QMDB-CR-003 — P4 Qur’an Source Decisions and Three-Batch Decomposition Approval

## Purpose

Resolve P4 scope, source, collation, source-seed, and search-policy decisions under `QMDB-P4-AUTH-DECOMPOSE-B01-EXEC`.

| Control | Decision |
| --- | --- |
| P4 phase identity | UNCHANGED — Qur’an Reference and Governance |
| P5 Competition assignment | UNCHANGED — Competition Configuration and Registration |
| Product intent | UNCHANGED |
| New authority | Explicit P4 batch decomposition and source-governance decisions |
| Current implementation authorization | P4-B01 only |
| P4-B02 | DEFINED / NOT AUTHORIZED |
| P4-B03 | DEFINED / NOT AUTHORIZED |
| P5 | NOT AUTHORIZED |

The owner instruction is the approval for this record. The approved canonical source is Tanzil Uthmani Qur’an Text v1.1; it is verbatim-only, attributed, release-governed, and never downloaded at runtime. Canonical Arabic storage uses column-level `utf8mb4_0900_bin` with no post-acquisition normalization. Search is separately governed for B03 only.

This change creates no canonical Qur’an text, Surah, Ayah, public reader, search, or Competition functionality. Rollback is a forward corrective commit only.
