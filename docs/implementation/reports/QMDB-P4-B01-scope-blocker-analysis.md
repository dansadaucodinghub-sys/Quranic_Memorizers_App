# QMDB-P4-B01 Scope Blocker Analysis

| Field | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Operation | QMDB-P4-B01-SCOPE-RECOVERY-EXEC |
| Date | 2026-09-09 |
| Starting revision | `a46bc38f3a16a35decfc5a6beb4b7aeb39c879c5` |
| P4 identity | Qur’an Reference and Governance |
| Result | `P4_B01_SCOPE_UNRESOLVED` |

## Classification

- `B01_IDENTIFIER_MISSING`
- `B01_TITLE_MISSING`
- `P4_REQUIREMENTS_NOT_GROUPED_BY_BATCH`
- `P4_REQUIREMENTS_EXIST_ONLY_AT_PHASE_LEVEL`
- `DEPENDENCY_ORDER_UNDEFINED`
- `SOURCE_DATASET_UNAPPROVED`

## Authoritative evidence

The frozen roadmap assigns P4 to Qur’an Reference and Governance and P5 to Competition Configuration and Registration.
The requirements map assigns ten requirements to P4, but assigns each one to `QMDB-P4-BACKLOG` rather than an
executable batch. It defines no `QMDB-P4-B01`, `QMDB-P4-B02`, or `QMDB-P4-B03` identity, title, or allocation.

The following source-governance decisions remain open:

| Decision | Blocking effect |
| --- | --- |
| OD-054 — Canonical Arabic collation | Blocks canonical P4 text-column DDL pending qualified comparison evidence. |
| OD-055 — Search-normalized Arabic rules | Blocks the derivative search pipeline; it cannot be used to define or modify canonical text. |
| OD-063 — Qur’an data source and release process | Blocks activation and any production source import pending a qualified source, version, checksum, custody, and review authority. |
| OD-083 — Qur’an release seed source | Keeps the production release seed empty pending qualified source review. |

## Dependency finding

The candidate B01 root is the release-provenance and immutable-import boundary. `QMDB-FR-QRF-001` needs the
canonical source, collation decision, and attributable qualified governance. The remaining P4 requirements build on
that root: normalized search requires an imported release and OD-055; dual review requires a candidate and qualified
authority; activation requires the reviewed candidate; supersession requires an active predecessor; and public read
requires an active/historical release and safe projection.

No source-independent requirement forms a production-usable vertical slice. Creating tables, routes, or placeholder
release objects without approved source material would create a misleading, non-executable release-governance surface
and would violate the no-fabrication and no-placeholder rules.

## Minimum owner decisions required

1. Approve a Qur’an source authority, source package/version, usage authority, Reading or Riwāyah, numbering and
   orthographic tradition, custody/retrieval evidence, and qualified-review appointment process for OD-063/OD-083.
2. Approve canonical storage comparison semantics for OD-054 after the required qualified comparison evidence.
3. Authorize a P4 batch decomposition that assigns the resulting root requirement set to B01. OD-055 is additionally
   required before any B01 scope includes search normalization; it must not be inferred from canonical storage.

## Boundary preserved

No Qur’an production source, dataset, migration, seed, route, permission, or module was created by this analysis.
No Competition requirement was assigned to P4. P5 remains not started and not authorized.
