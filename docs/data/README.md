# QMDB Data Architecture

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B04 |
| Document Title | Data Architecture Entry Point |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Enterprise Data Architecture |
| Last Updated | 2026-08-24 |
| Approval Status | B04 documentation complete; P0-CLOSE approval pending |
| Related Documents | [documentation index](../README.md); [project state](../project/project-state.md); [B04 requirements](../requirements/P0-B04-data-requirements.md) |

## Purpose

Provide the navigation, authority order, counts and interpretation rules for the QMDB-P0-B04 logical data architecture.

## Scope

These artifacts specify a future Core PHP/MySQL implementation. They contain no executable migration, production SQL, application code, seed data, provider selection, retention period, geography code, Quran text or scoring formula.

## Artifact map

1. [Data Architecture Overview](01-data-architecture-overview.md) — storage roles, source-of-truth, consistency, transactions, history, recovery and governance.
2. [Domain Aggregate and Ownership Model](02-domain-aggregate-and-ownership-model.md) — aggregate roots, entities, Value Objects, transaction/ownership boundaries and events.
3. [MySQL Schema Conventions](03-mysql-schema-conventions.md) — names, logical types, IDs, status/taxonomy, JSON, collation, uniqueness, locking and deletion.
4. [Entity-Relationship Model](04-entity-relationship-model.md) — one complete-domain map and ten detailed Mermaid ERDs.
5. [Table and Column Data Dictionary](05-table-and-column-data-dictionary.md) — complete logical table/column metadata.
6. [Relationship, Constraint, and Integrity Catalog](06-relationship-constraint-and-integrity-catalog.md) — foreign keys, delete/history behavior, constraints and invariant enforcement.
7. [Indexing and Query Access Patterns](07-indexing-and-query-access-patterns.md) — bounded reads, consistency, pagination, locking, indexes and write cost.
8. [Record Versioning, Lifecycle, and Deletion](08-record-versioning-lifecycle-and-deletion.md) — mutation, snapshots, supersession, revocation, withdrawal, archival and anonymization.
9. [Tenant-Isolation Data Model](09-tenant-isolation-data-model.md) — complete table classification, Workspace keys/relationships, exceptions, jobs, caches and tests.
10. [Data Classification, Ownership, and Lineage](10-data-classification-ownership-and-lineage.md) — per-table stewardship/handling, ten lineage paths and data-quality rules.
11. [Migration, Seed, and Bootstrap Plan](11-migration-seed-and-bootstrap-plan.md) — 20 ordered future migration groups and controlled bootstrap.
12. [Volume, Archival, and Partitioning Strategy](12-volume-archival-and-partitioning-strategy.md) — qualitative growth, archive/projection records and no-premature-partitioning rule.
13. [Schema Review and Implementation Readiness](13-schema-review-and-implementation-readiness.md) — blockers, dependencies, evidence and P0-CLOSE gate.
14. [MySQL Logical Schema Manifest](mysql-logical-schema.yaml) — machine-readable, non-executable implementation manifest.
15. [Controlled Taxonomy and Seed Catalog](controlled-taxonomy-and-seed-catalog.yaml) — machine-readable taxonomy authority/provenance plan without fabricated values.
16. [P0-B04 Data Requirements](../requirements/P0-B04-data-requirements.md) — single-obligation governed data requirements.
17. [P0-B04 Traceability Matrix](../requirements/P0-B04-traceability-matrix.md) — requirement-to-model/test linkage.

## Controlled counts

| Element | Count |
| --- | ---: |
| Domain Aggregates | 38 |
| Entities | 166 |
| Value Objects | 32 |
| Logical Tables | 250 |
| Logical Columns | 1909 |
| Relationships | 469 |
| Constraints | 785 |
| Indexes | 658 |
| Controlled Taxonomies | 22 |
| Seed Datasets | 16 |
| Data-Lineage Paths | 10 |
| Migration Groups | 20 |
| Data Requirements | 32 |
| Data-Quality Rules | 24 |
| ERD Diagrams | 11 |

## Authority and interpretation

1. Locked ADR-001–ADR-020 and B01 invariants govern.
2. B02 functional/state-machine authority and B03 security/privacy/operations constraints govern.
3. B04 Data Requirements govern this logical model.
4. The YAML manifest is the exact machine inventory; the Markdown dictionary is its human review surface.
5. Proposed ADR-030–ADR-052 and open decisions are not approved merely because the model references them.
6. P0-CLOSE decides whether to freeze the baseline. P1 later produces migrations and application code.

MySQL/InnoDB is authoritative for structured business records. Redis, search, analytics, CDNs and projection tables are not official score stores. Private media bytes and private cryptographic keys stay outside MySQL. Public identifiers never authorize. Finalized official history has no normal hard-delete path.

## Change control

A change to an aggregate, table, significant column, relationship, constraint, index, taxonomy, lineage, lifecycle, classification or migration group must update the YAML manifest, affected Markdown catalogs, B04 traceability, decision/risk dependencies, validation counts and project state in the same reviewed change.
