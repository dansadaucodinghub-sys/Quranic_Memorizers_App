# Table and Column Data Dictionary

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B04 |
| Document Title | Table and Column Data Dictionary |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Data Architecture and Database Governance |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed for governance approval |
| Related Documents | [schema manifest](mysql-logical-schema.yaml); [ERDs](04-entity-relationship-model.md); [integrity catalog](06-relationship-constraint-and-integrity-catalog.md) |

## Purpose

Provide the complete human-readable logical inventory synchronized with the machine-readable MySQL manifest.

## Scope and interpretation

The dictionary contains 250 tables and 1909 significant logical columns. MySQL is authoritative for structured records; Projection entries are rebuildable. Tenant classifications are security boundaries. Tenant-owned records carry non-null `workspace_id`. Public IDs never authorize. `DECIMAL(P_SCORE,S_SCORE)` is exact and blocks DDL until OD-049.

## Inventory by module

| Module | Table range/count | Authoritative | Projections | Implementation phase |
| --- | --- | ---: | ---: | --- |
| Workspaces and Authorization | QMDB-TBL-001–QMDB-TBL-015 (15) | 15 | 0 | P2 |
| Identity and Authentication | QMDB-TBL-016–QMDB-TBL-028 (13) | 13 | 0 | P2 |
| People and Guardianship | QMDB-TBL-029–QMDB-TBL-045 (17) | 16 | 1 | P3 |
| Geography | QMDB-TBL-046–QMDB-TBL-051 (6) | 6 | 0 | P3 |
| Organizations | QMDB-TBL-052–QMDB-TBL-060 (9) | 9 | 0 | P3 |
| Quran Reference Governance | QMDB-TBL-061–QMDB-TBL-075 (15) | 15 | 0 | P4 |
| Competition Configuration | QMDB-TBL-076–QMDB-TBL-095 (20) | 20 | 0 | P5 |
| Registration and Eligibility | QMDB-TBL-096–QMDB-TBL-108 (13) | 13 | 0 | P5 |
| Scheduling and Judging | QMDB-TBL-109–QMDB-TBL-119 (11) | 11 | 0 | P6 |
| Performance and Scoring | QMDB-TBL-120–QMDB-TBL-137 (18) | 18 | 0 | P6 |
| Results and Appeals | QMDB-TBL-138–QMDB-TBL-150 (13) | 13 | 0 | P7 |
| Certificates and Trusted Records | QMDB-TBL-151–QMDB-TBL-168 (18) | 18 | 0 | P8 |
| Media and Evidence | QMDB-TBL-169–QMDB-TBL-181 (13) | 13 | 0 | P9 |
| Recitation Clips and Moderation | QMDB-TBL-182–QMDB-TBL-202 (21) | 20 | 1 | P10 |
| Notifications | QMDB-TBL-203–QMDB-TBL-208 (6) | 6 | 0 | P7 |
| Search and Reporting | QMDB-TBL-209–QMDB-TBL-216 (8) | 4 | 4 | P11 |
| Privacy and Data Governance | QMDB-TBL-217–QMDB-TBL-225 (9) | 9 | 0 | P12 |
| Audit and Integrations | QMDB-TBL-226–QMDB-TBL-238 (13) | 13 | 0 | P12 |
| Platform and Offline Operations | QMDB-TBL-239–QMDB-TBL-250 (12) | 12 | 0 | P13 |

## Complete logical table definitions

### QMDB-TBL-001 — `workspaces`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-001 |
| Logical Table Name | workspaces |
| Owning Module | Workspaces and Authorization |
| Purpose | Stores workspaces as the Workspaces and Authorization module's governed relational record. |
| Aggregate | Workspace |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | None |
| Check Constraints | None |
| Indexes | QMDB-IDX-0001 (public_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Workspaces by relational/public identity |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-006; QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0001 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0002 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0003 | `workspace_code` | Human-readable code | `VARCHAR(64)` | No | None | No | No | No | Yes | Workspace Code for Workspaces. | Domain validation plus schema type/constraint. |
| QMDB-COL-0004 | `name` | Name | `VARCHAR(191)` | No | None | No | No | No | No | Name for Workspaces. | Domain validation plus schema type/constraint. |
| QMDB-COL-0005 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Workspaces. | Domain validation plus schema type/constraint. |
| QMDB-COL-0006 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Workspaces. | Domain validation plus schema type/constraint. |

### QMDB-TBL-002 — `workspace_settings`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-002 |
| Logical Table Name | workspace_settings |
| Owning Module | Workspaces and Authorization |
| Purpose | Stores workspace settings as the Workspaces and Authorization module's governed relational record. |
| Aggregate | Workspace |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0001: (workspace_id) -> workspaces(id); QMDB-REL-0002: (workspace_id) -> workspaces(id) |
| Check Constraints | QMDB-CST-0006: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0002 (public_id); QMDB-IDX-0003 (workspace_id, status_code, created_at, id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Workspace Settings by relational/public identity; List Workspace Settings within one Workspace using keyset pagination |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-006; QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0007 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0008 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0009 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0010 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Workspace Settings. | Domain validation plus schema type/constraint. |
| QMDB-COL-0011 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Workspace Settings. | Domain validation plus schema type/constraint. |
| QMDB-COL-0012 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Workspace Settings. | Domain validation plus schema type/constraint. |
| QMDB-COL-0013 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Workspace Settings. | Domain validation plus schema type/constraint. |

### QMDB-TBL-003 — `memberships`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-003 |
| Logical Table Name | memberships |
| Owning Module | Workspaces and Authorization |
| Purpose | Stores memberships as the Workspaces and Authorization module's governed relational record. |
| Aggregate | Workspace |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0003: (workspace_id) -> workspaces(id); QMDB-REL-0004: (workspace_id) -> workspaces(id); QMDB-REL-0005: (user_account_id) -> user_accounts(id) |
| Check Constraints | QMDB-CST-0010: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0004 (public_id); QMDB-IDX-0005 (workspace_id, status_code, created_at, id); QMDB-IDX-0006 (user_account_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Memberships by relational/public identity; List Memberships within one Workspace using keyset pagination |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-006; QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0014 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0015 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0016 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0017 | `user_account_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to user_accounts. | Domain validation plus schema type/constraint. |
| QMDB-COL-0018 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Memberships. | Domain validation plus schema type/constraint. |
| QMDB-COL-0019 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Memberships. | Domain validation plus schema type/constraint. |
| QMDB-COL-0020 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Memberships. | Domain validation plus schema type/constraint. |
| QMDB-COL-0021 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Memberships. | Domain validation plus schema type/constraint. |

### QMDB-TBL-004 — `roles`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-004 |
| Logical Table Name | roles |
| Owning Module | Workspaces and Authorization |
| Purpose | Stores roles as the Workspaces and Authorization module's governed relational record. |
| Aggregate | Workspace |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0006: (workspace_id) -> workspaces(id); QMDB-REL-0007: (workspace_id) -> workspaces(id) |
| Check Constraints | QMDB-CST-0014: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0007 (public_id); QMDB-IDX-0008 (workspace_id, status_code, created_at, id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Roles by relational/public identity; List Roles within one Workspace using keyset pagination |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-006; QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0022 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0023 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0024 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0025 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Roles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0026 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Roles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0027 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Roles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0028 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Roles. | Domain validation plus schema type/constraint. |

### QMDB-TBL-005 — `permissions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-005 |
| Logical Table Name | permissions |
| Owning Module | Workspaces and Authorization |
| Purpose | Stores permissions as the Workspaces and Authorization module's governed relational record. |
| Aggregate | Workspace |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | None |
| Check Constraints | QMDB-CST-0016: Version value shall be positive and monotonic within its lineage. |
| Indexes | None |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Permissions by relational/public identity |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-006; QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0029 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0030 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Permissions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0031 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Permissions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0032 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Permissions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0033 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Permissions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-006 — `role_permissions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-006 |
| Logical Table Name | role_permissions |
| Owning Module | Workspaces and Authorization |
| Purpose | Stores role permissions as the Workspaces and Authorization module's governed relational record. |
| Aggregate | Workspace |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0008: (workspace_id) -> workspaces(id); QMDB-REL-0009: (workspace_id, role_id) -> roles(workspace_id, id); QMDB-REL-0010: (permission_id) -> permissions(id) |
| Check Constraints | QMDB-CST-0019: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0009 (workspace_id, created_at, id); QMDB-IDX-0010 (workspace_id, role_id); QMDB-IDX-0011 (permission_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Role Permissions by relational/public identity; List Role Permissions within one Workspace using keyset pagination |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-006; QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0034 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0035 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0036 | `role_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to roles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0037 | `permission_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to permissions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0038 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | No | No | Yes | Sequence Number for Role Permissions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0039 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Role Permissions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-007 — `membership_roles`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-007 |
| Logical Table Name | membership_roles |
| Owning Module | Workspaces and Authorization |
| Purpose | Stores membership roles as the Workspaces and Authorization module's governed relational record. |
| Aggregate | Workspace |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0011: (workspace_id) -> workspaces(id); QMDB-REL-0012: (workspace_id, membership_id) -> memberships(workspace_id, id); QMDB-REL-0013: (workspace_id, role_id) -> roles(workspace_id, id) |
| Check Constraints | QMDB-CST-0022: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0012 (workspace_id, created_at, id); QMDB-IDX-0013 (workspace_id, membership_id); QMDB-IDX-0014 (workspace_id, role_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Membership Roles by relational/public identity; List Membership Roles within one Workspace using keyset pagination |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-006; QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0040 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0041 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0042 | `membership_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to memberships. | Domain validation plus schema type/constraint. |
| QMDB-COL-0043 | `role_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to roles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0044 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | No | No | Yes | Sequence Number for Membership Roles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0045 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Membership Roles. | Domain validation plus schema type/constraint. |

### QMDB-TBL-008 — `administrative_scopes`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-008 |
| Logical Table Name | administrative_scopes |
| Owning Module | Workspaces and Authorization |
| Purpose | Stores administrative scopes as the Workspaces and Authorization module's governed relational record. |
| Aggregate | Workspace |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0014: (workspace_id) -> workspaces(id); QMDB-REL-0015: (workspace_id) -> workspaces(id) |
| Check Constraints | QMDB-CST-0026: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0015 (public_id); QMDB-IDX-0016 (workspace_id, status_code, created_at, id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Administrative Scopes by relational/public identity; List Administrative Scopes within one Workspace using keyset pagination |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-006; QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0046 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0047 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0048 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0049 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Administrative Scopes. | Domain validation plus schema type/constraint. |
| QMDB-COL-0050 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Administrative Scopes. | Domain validation plus schema type/constraint. |
| QMDB-COL-0051 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Administrative Scopes. | Domain validation plus schema type/constraint. |
| QMDB-COL-0052 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Administrative Scopes. | Domain validation plus schema type/constraint. |

### QMDB-TBL-009 — `scope_grants`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-009 |
| Logical Table Name | scope_grants |
| Owning Module | Workspaces and Authorization |
| Purpose | Stores scope grants as the Workspaces and Authorization module's governed relational record. |
| Aggregate | Workspace |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Revocable Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0016: (workspace_id) -> workspaces(id); QMDB-REL-0017: (workspace_id, membership_id) -> memberships(workspace_id, id); QMDB-REL-0018: (workspace_id, administrative_scope_id) -> administrative_scopes(workspace_id, id) |
| Check Constraints | QMDB-CST-0029: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0017 (workspace_id, created_at, id); QMDB-IDX-0018 (workspace_id, membership_id); QMDB-IDX-0019 (workspace_id, administrative_scope_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Scope Grants by relational/public identity; List Scope Grants within one Workspace using keyset pagination |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-006; QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0053 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0054 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0055 | `membership_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to memberships. | Domain validation plus schema type/constraint. |
| QMDB-COL-0056 | `administrative_scope_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to administrative_scopes. | Domain validation plus schema type/constraint. |
| QMDB-COL-0057 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | No | No | Yes | Sequence Number for Scope Grants. | Domain validation plus schema type/constraint. |
| QMDB-COL-0058 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Scope Grants. | Domain validation plus schema type/constraint. |

### QMDB-TBL-010 — `competition_assignments`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-010 |
| Logical Table Name | competition_assignments |
| Owning Module | Workspaces and Authorization |
| Purpose | Stores competition assignments as the Workspaces and Authorization module's governed relational record. |
| Aggregate | Workspace |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0019: (workspace_id) -> workspaces(id); QMDB-REL-0020: (workspace_id, membership_id) -> memberships(workspace_id, id); QMDB-REL-0021: (workspace_id, competition_edition_id) -> competition_editions(workspace_id, id) |
| Check Constraints | QMDB-CST-0032: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0020 (workspace_id, created_at, id); QMDB-IDX-0021 (workspace_id, membership_id); QMDB-IDX-0022 (workspace_id, competition_edition_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Competition Assignments by relational/public identity; List Competition Assignments within one Workspace using keyset pagination |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-006; QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0059 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0060 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0061 | `membership_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to memberships. | Domain validation plus schema type/constraint. |
| QMDB-COL-0062 | `competition_edition_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to competition_editions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0063 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | No | No | Yes | Sequence Number for Competition Assignments. | Domain validation plus schema type/constraint. |
| QMDB-COL-0064 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Competition Assignments. | Domain validation plus schema type/constraint. |

### QMDB-TBL-011 — `approval_requests`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-011 |
| Logical Table Name | approval_requests |
| Owning Module | Workspaces and Authorization |
| Purpose | Stores approval requests as the Workspaces and Authorization module's governed relational record. |
| Aggregate | Workspace |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0022: (workspace_id) -> workspaces(id); QMDB-REL-0023: (workspace_id) -> workspaces(id) |
| Check Constraints | QMDB-CST-0036: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0023 (public_id); QMDB-IDX-0024 (workspace_id, status_code, created_at, id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Approval Requests by relational/public identity; List Approval Requests within one Workspace using keyset pagination |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-006; QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0065 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0066 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0067 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0068 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Approval Requests. | Domain validation plus schema type/constraint. |
| QMDB-COL-0069 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Approval Requests. | Domain validation plus schema type/constraint. |
| QMDB-COL-0070 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Approval Requests. | Domain validation plus schema type/constraint. |
| QMDB-COL-0071 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Approval Requests. | Domain validation plus schema type/constraint. |

### QMDB-TBL-012 — `approval_decisions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-012 |
| Logical Table Name | approval_decisions |
| Owning Module | Workspaces and Authorization |
| Purpose | Stores approval decisions as the Workspaces and Authorization module's governed relational record. |
| Aggregate | Workspace |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0024: (workspace_id) -> workspaces(id); QMDB-REL-0025: (workspace_id, approval_request_id) -> approval_requests(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0025 (public_id); QMDB-IDX-0026 (workspace_id, decision_code, created_at, id); QMDB-IDX-0027 (workspace_id, approval_request_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Approval Decisions by relational/public identity; List Approval Decisions within one Workspace using keyset pagination |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-006; QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0072 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0073 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0074 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0075 | `approval_request_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to approval_requests. | Domain validation plus schema type/constraint. |
| QMDB-COL-0076 | `decision_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Decision Code for Approval Decisions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0077 | `reason_code` | Human-readable code | `VARCHAR(64)` | No | None | No | No | No | No | Reason Code for Approval Decisions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0078 | `decided_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Decided At for Approval Decisions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0079 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Approval Decisions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-013 — `temporary_privilege_grants`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-013 |
| Logical Table Name | temporary_privilege_grants |
| Owning Module | Workspaces and Authorization |
| Purpose | Stores temporary privilege grants as the Workspaces and Authorization module's governed relational record. |
| Aggregate | Workspace |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Revocable Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0026: (workspace_id) -> workspaces(id); QMDB-REL-0027: (workspace_id, membership_id) -> memberships(workspace_id, id) |
| Check Constraints | QMDB-CST-0043: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0028 (public_id); QMDB-IDX-0029 (workspace_id, status_code, created_at, id); QMDB-IDX-0030 (workspace_id, membership_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Temporary Privilege Grants by relational/public identity; List Temporary Privilege Grants within one Workspace using keyset pagination |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-006; QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0080 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0081 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0082 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0083 | `membership_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to memberships. | Domain validation plus schema type/constraint. |
| QMDB-COL-0084 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Temporary Privilege Grants. | Domain validation plus schema type/constraint. |
| QMDB-COL-0085 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Temporary Privilege Grants. | Domain validation plus schema type/constraint. |
| QMDB-COL-0086 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Temporary Privilege Grants. | Domain validation plus schema type/constraint. |
| QMDB-COL-0087 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Temporary Privilege Grants. | Domain validation plus schema type/constraint. |

### QMDB-TBL-014 — `support_access_grants`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-014 |
| Logical Table Name | support_access_grants |
| Owning Module | Workspaces and Authorization |
| Purpose | Stores support access grants as the Workspaces and Authorization module's governed relational record. |
| Aggregate | Support Access Grant |
| Authoritative or Projection | Authoritative |
| Tenant Scope | CROSS_TENANT_OVERSIGHT |
| Expected Volume Class | LOW |
| Lifecycle Category | Revocable Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0028: (user_account_id) -> user_accounts(id) |
| Check Constraints | QMDB-CST-0046: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0031 (public_id); QMDB-IDX-0032 (user_account_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Support Access Grants by relational/public identity |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-006; QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0088 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0089 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0090 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | Yes | None | No | No | No | Yes | Nullable only for governed platform-global activity; cross-workspace use requires explicit policy and audit. | Domain validation plus schema type/constraint. |
| QMDB-COL-0091 | `user_account_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to user_accounts. | Domain validation plus schema type/constraint. |
| QMDB-COL-0092 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Support Access Grants. | Domain validation plus schema type/constraint. |
| QMDB-COL-0093 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Support Access Grants. | Domain validation plus schema type/constraint. |
| QMDB-COL-0094 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Support Access Grants. | Domain validation plus schema type/constraint. |
| QMDB-COL-0095 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Support Access Grants. | Domain validation plus schema type/constraint. |

### QMDB-TBL-015 — `break_glass_grants`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-015 |
| Logical Table Name | break_glass_grants |
| Owning Module | Workspaces and Authorization |
| Purpose | Stores break glass grants as the Workspaces and Authorization module's governed relational record. |
| Aggregate | Break-Glass Grant |
| Authoritative or Projection | Authoritative |
| Tenant Scope | CROSS_TENANT_OVERSIGHT |
| Expected Volume Class | LOW |
| Lifecycle Category | Revocable Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0029: (user_account_id) -> user_accounts(id) |
| Check Constraints | QMDB-CST-0049: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0033 (public_id); QMDB-IDX-0034 (user_account_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Break Glass Grants by relational/public identity |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-006; QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0096 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0097 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0098 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | Yes | None | No | Yes | No | Yes | Nullable only for governed platform-global activity; cross-workspace use requires explicit policy and audit. | Domain validation plus schema type/constraint. |
| QMDB-COL-0099 | `user_account_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to user_accounts. | Domain validation plus schema type/constraint. |
| QMDB-COL-0100 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Break Glass Grants. | Domain validation plus schema type/constraint. |
| QMDB-COL-0101 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Break Glass Grants. | Domain validation plus schema type/constraint. |
| QMDB-COL-0102 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Break Glass Grants. | Domain validation plus schema type/constraint. |
| QMDB-COL-0103 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Break Glass Grants. | Domain validation plus schema type/constraint. |

### QMDB-TBL-016 — `user_accounts`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-016 |
| Logical Table Name | user_accounts |
| Owning Module | Identity and Authentication |
| Purpose | Stores user accounts as the Identity and Authentication module's governed relational record. |
| Aggregate | Identity and Authentication |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0030: (person_id) -> persons(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0035 (public_id); QMDB-IDX-0036 (person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup User Accounts by relational/public identity |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-005; QMDB-FR-IAM-001; QMDB-FR-IAM-002; QMDB-FR-IAM-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0104 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0105 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0106 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-0107 | `account_status` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Account Status for User Accounts. | Domain validation plus schema type/constraint. |
| QMDB-COL-0108 | `preferred_locale` | Locale | `VARCHAR(16)` | No | None | No | No | No | No | Preferred Locale for User Accounts. | Domain validation plus schema type/constraint. |
| QMDB-COL-0109 | `preferred_time_zone` | Time zone | `VARCHAR(64)` | No | None | No | No | No | No | Preferred Time Zone for User Accounts. | Domain validation plus schema type/constraint. |
| QMDB-COL-0110 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for User Accounts. | Domain validation plus schema type/constraint. |

### QMDB-TBL-017 — `account_email_addresses`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-017 |
| Logical Table Name | account_email_addresses |
| Owning Module | Identity and Authentication |
| Purpose | Stores account email addresses as the Identity and Authentication module's governed relational record. |
| Aggregate | User Account |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0031: (user_account_id) -> user_accounts(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0037 (public_id); QMDB-IDX-0038 (user_account_id); QMDB-IDX-0039 (active_lookup_hash) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Account Email Addresses by relational/public identity |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-005; QMDB-FR-IAM-001; QMDB-FR-IAM-002; QMDB-FR-IAM-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | OD-051 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0111 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0112 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0113 | `user_account_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to user_accounts. | Domain validation plus schema type/constraint. |
| QMDB-COL-0114 | `email_ciphertext` | Encryption ciphertext | `VARBINARY(2048)` | No | None | No | No | Yes | No | Email Ciphertext for Account Email Addresses. | Domain validation plus schema type/constraint. |
| QMDB-COL-0115 | `lookup_hash` | Token hash | `BINARY(32)` | No | None | No | No | No | Yes | Lookup Hash for Account Email Addresses. | Domain validation plus schema type/constraint. |
| QMDB-COL-0116 | `active_lookup_hash` | Generated active key | `BINARY(32) GENERATED STORED` | Yes | None | No | No | No | Yes | Active Lookup Hash for Account Email Addresses. | Domain validation plus schema type/constraint. |
| QMDB-COL-0117 | `verified_at` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Verified At for Account Email Addresses. | Domain validation plus schema type/constraint. |
| QMDB-COL-0118 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Account Email Addresses. | Domain validation plus schema type/constraint. |
| QMDB-COL-1906 | `encryption_key_id` | Key identifier | `VARCHAR(255)` | Yes | None | No | No | No | No | Provider-neutral envelope key reference; no key material. | Domain validation plus schema constraint. |

### QMDB-TBL-018 — `account_phone_numbers`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-018 |
| Logical Table Name | account_phone_numbers |
| Owning Module | Identity and Authentication |
| Purpose | Stores account phone numbers as the Identity and Authentication module's governed relational record. |
| Aggregate | User Account |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0032: (user_account_id) -> user_accounts(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0040 (public_id); QMDB-IDX-0041 (user_account_id); QMDB-IDX-0042 (active_lookup_hash) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Account Phone Numbers by relational/public identity |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-005; QMDB-FR-IAM-001; QMDB-FR-IAM-002; QMDB-FR-IAM-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | OD-052 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0119 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0120 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0121 | `user_account_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to user_accounts. | Domain validation plus schema type/constraint. |
| QMDB-COL-0122 | `phone_ciphertext` | Encryption ciphertext | `VARBINARY(2048)` | No | None | No | No | Yes | No | Phone Ciphertext for Account Phone Numbers. | Domain validation plus schema type/constraint. |
| QMDB-COL-0123 | `lookup_hash` | Token hash | `BINARY(32)` | No | None | No | No | No | Yes | Lookup Hash for Account Phone Numbers. | Domain validation plus schema type/constraint. |
| QMDB-COL-0124 | `active_lookup_hash` | Generated active key | `BINARY(32) GENERATED STORED` | Yes | None | No | No | No | Yes | Active Lookup Hash for Account Phone Numbers. | Domain validation plus schema type/constraint. |
| QMDB-COL-0125 | `verified_at` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Verified At for Account Phone Numbers. | Domain validation plus schema type/constraint. |
| QMDB-COL-0126 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Account Phone Numbers. | Domain validation plus schema type/constraint. |
| QMDB-COL-1907 | `encryption_key_id` | Key identifier | `VARCHAR(255)` | Yes | None | No | No | No | No | Provider-neutral envelope key reference; no key material. | Domain validation plus schema constraint. |

### QMDB-TBL-019 — `account_credentials`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-019 |
| Logical Table Name | account_credentials |
| Owning Module | Identity and Authentication |
| Purpose | Stores account credentials as the Identity and Authentication module's governed relational record. |
| Aggregate | User Account |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Ephemeral Security Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0033: (user_account_id) -> user_accounts(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0043 (user_account_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Expire and delete under approved security retention policy. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Account Credentials by relational/public identity |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-005; QMDB-FR-IAM-001; QMDB-FR-IAM-002; QMDB-FR-IAM-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0127 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0128 | `user_account_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to user_accounts. | Domain validation plus schema type/constraint. |
| QMDB-COL-0129 | `credential_type` | Status | `VARCHAR(32)` | No | None | No | Yes | No | No | Credential Type for Account Credentials. | Domain validation plus schema type/constraint. |
| QMDB-COL-0130 | `password_hash` | Password hash | `VARCHAR(255)` | Yes | None | No | Yes | No | Yes | Password Hash for Account Credentials. | Domain validation plus schema type/constraint. |
| QMDB-COL-0131 | `secret_reference` | Key identifier | `VARCHAR(255)` | Yes | None | No | Yes | No | No | Secret Reference for Account Credentials. | Domain validation plus schema type/constraint. |
| QMDB-COL-0132 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Account Credentials. | Domain validation plus schema type/constraint. |

### QMDB-TBL-020 — `credential_history`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-020 |
| Logical Table Name | credential_history |
| Owning Module | Identity and Authentication |
| Purpose | Stores credential history as the Identity and Authentication module's governed relational record. |
| Aggregate | Identity and Authentication |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0034: (user_account_id) -> user_accounts(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0044 (user_account_id); QMDB-IDX-0045 (occurred_at, id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Credential History by relational/public identity; Read Credential History by bounded occurrence range |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-005; QMDB-FR-IAM-001; QMDB-FR-IAM-002; QMDB-FR-IAM-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0133 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0134 | `user_account_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to user_accounts. | Domain validation plus schema type/constraint. |
| QMDB-COL-0135 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | Yes | Yes | No | Yes | Event Type for Credential History. | Domain validation plus schema type/constraint. |
| QMDB-COL-0136 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Occurred At for Credential History. | Domain validation plus schema type/constraint. |
| QMDB-COL-0137 | `payload_json` | JSON document | `JSON` | Yes | None | Yes | Yes | No | No | Versioned bounded event metadata; core record fields remain relational. | Domain validation plus schema type/constraint. |
| QMDB-COL-0138 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | Yes | No | Yes | Content Hash for Credential History. | Domain validation plus schema type/constraint. |

### QMDB-TBL-021 — `auth_sessions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-021 |
| Logical Table Name | auth_sessions |
| Owning Module | Identity and Authentication |
| Purpose | Stores auth sessions as the Identity and Authentication module's governed relational record. |
| Aggregate | User Account |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0035: (user_account_id) -> user_accounts(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0046 (public_id); QMDB-IDX-0047 (user_account_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Auth Sessions by relational/public identity |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-005; QMDB-FR-IAM-001; QMDB-FR-IAM-002; QMDB-FR-IAM-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0139 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0140 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0141 | `user_account_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to user_accounts. | Domain validation plus schema type/constraint. |
| QMDB-COL-0142 | `session_token_hash` | Token hash | `BINARY(32)` | No | None | No | Yes | No | Yes | Session Token Hash for Auth Sessions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0143 | `expires_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Expires At for Auth Sessions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0144 | `revoked_at` | UTC timestamp | `DATETIME(6)` | Yes | None | No | Yes | No | No | Revoked At for Auth Sessions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0145 | `last_seen_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Last Seen At for Auth Sessions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0146 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Auth Sessions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-022 — `devices`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-022 |
| Logical Table Name | devices |
| Owning Module | Identity and Authentication |
| Purpose | Stores devices as the Identity and Authentication module's governed relational record. |
| Aggregate | User Account |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0036: (user_account_id) -> user_accounts(id) |
| Check Constraints | QMDB-CST-0064: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0048 (public_id); QMDB-IDX-0049 (user_account_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Devices by relational/public identity |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-005; QMDB-FR-IAM-001; QMDB-FR-IAM-002; QMDB-FR-IAM-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0147 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0148 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0149 | `user_account_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to user_accounts. | Domain validation plus schema type/constraint. |
| QMDB-COL-0150 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Devices. | Domain validation plus schema type/constraint. |
| QMDB-COL-0151 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Devices. | Domain validation plus schema type/constraint. |
| QMDB-COL-0152 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Devices. | Domain validation plus schema type/constraint. |
| QMDB-COL-0153 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Devices. | Domain validation plus schema type/constraint. |

### QMDB-TBL-023 — `mfa_methods`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-023 |
| Logical Table Name | mfa_methods |
| Owning Module | Identity and Authentication |
| Purpose | Stores mfa methods as the Identity and Authentication module's governed relational record. |
| Aggregate | User Account |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0037: (user_account_id) -> user_accounts(id) |
| Check Constraints | QMDB-CST-0067: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0050 (public_id); QMDB-IDX-0051 (user_account_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup MFA Methods by relational/public identity |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-005; QMDB-FR-IAM-001; QMDB-FR-IAM-002; QMDB-FR-IAM-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0154 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0155 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0156 | `user_account_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to user_accounts. | Domain validation plus schema type/constraint. |
| QMDB-COL-0157 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for MFA Methods. | Domain validation plus schema type/constraint. |
| QMDB-COL-0158 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for MFA Methods. | Domain validation plus schema type/constraint. |
| QMDB-COL-0159 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for MFA Methods. | Domain validation plus schema type/constraint. |
| QMDB-COL-0160 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for MFA Methods. | Domain validation plus schema type/constraint. |

### QMDB-TBL-024 — `passkeys`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-024 |
| Logical Table Name | passkeys |
| Owning Module | Identity and Authentication |
| Purpose | Stores passkeys as the Identity and Authentication module's governed relational record. |
| Aggregate | User Account |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0038: (user_account_id) -> user_accounts(id) |
| Check Constraints | QMDB-CST-0070: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0052 (public_id); QMDB-IDX-0053 (user_account_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Passkeys by relational/public identity |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-005; QMDB-FR-IAM-001; QMDB-FR-IAM-002; QMDB-FR-IAM-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0161 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0162 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0163 | `user_account_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to user_accounts. | Domain validation plus schema type/constraint. |
| QMDB-COL-0164 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Passkeys. | Domain validation plus schema type/constraint. |
| QMDB-COL-0165 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Passkeys. | Domain validation plus schema type/constraint. |
| QMDB-COL-0166 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Passkeys. | Domain validation plus schema type/constraint. |
| QMDB-COL-0167 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Passkeys. | Domain validation plus schema type/constraint. |

### QMDB-TBL-025 — `verification_challenges`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-025 |
| Logical Table Name | verification_challenges |
| Owning Module | Identity and Authentication |
| Purpose | Stores verification challenges as the Identity and Authentication module's governed relational record. |
| Aggregate | User Account |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Ephemeral Security Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0039: (user_account_id) -> user_accounts(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0054 (user_account_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Expire and delete under approved security retention policy. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Verification Challenges by relational/public identity |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-005; QMDB-FR-IAM-001; QMDB-FR-IAM-002; QMDB-FR-IAM-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0168 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0169 | `user_account_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to user_accounts. | Domain validation plus schema type/constraint. |
| QMDB-COL-0170 | `token_hash` | Token hash | `BINARY(32)` | No | None | Yes | No | No | Yes | One-way lookup verifier; plaintext secret is never stored. | Domain validation plus schema type/constraint. |
| QMDB-COL-0171 | `expires_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | Yes | Expires At for Verification Challenges. | Domain validation plus schema type/constraint. |
| QMDB-COL-0172 | `consumed_at` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Consumed At for Verification Challenges. | Domain validation plus schema type/constraint. |
| QMDB-COL-0173 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Verification Challenges. | Domain validation plus schema type/constraint. |

### QMDB-TBL-026 — `recovery_tokens`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-026 |
| Logical Table Name | recovery_tokens |
| Owning Module | Identity and Authentication |
| Purpose | Stores recovery tokens as the Identity and Authentication module's governed relational record. |
| Aggregate | User Account |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Ephemeral Security Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0040: (user_account_id) -> user_accounts(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0055 (user_account_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Expire and delete under approved security retention policy. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Recovery Tokens by relational/public identity |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-005; QMDB-FR-IAM-001; QMDB-FR-IAM-002; QMDB-FR-IAM-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0174 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0175 | `user_account_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to user_accounts. | Domain validation plus schema type/constraint. |
| QMDB-COL-0176 | `token_hash` | Token hash | `BINARY(32)` | No | None | Yes | Yes | No | Yes | One-way lookup verifier; plaintext secret is never stored. | Domain validation plus schema type/constraint. |
| QMDB-COL-0177 | `expires_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | Yes | Expires At for Recovery Tokens. | Domain validation plus schema type/constraint. |
| QMDB-COL-0178 | `consumed_at` | UTC timestamp | `DATETIME(6)` | Yes | None | No | Yes | No | No | Consumed At for Recovery Tokens. | Domain validation plus schema type/constraint. |
| QMDB-COL-0179 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Recovery Tokens. | Domain validation plus schema type/constraint. |

### QMDB-TBL-027 — `account_status_events`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-027 |
| Logical Table Name | account_status_events |
| Owning Module | Identity and Authentication |
| Purpose | Stores account status events as the Identity and Authentication module's governed relational record. |
| Aggregate | User Account |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | HIGH |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0041: (user_account_id) -> user_accounts(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0056 (user_account_id); QMDB-IDX-0057 (occurred_at, id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Account Status Events by relational/public identity; Read Account Status Events by bounded occurrence range |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-005; QMDB-FR-IAM-001; QMDB-FR-IAM-002; QMDB-FR-IAM-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0180 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0181 | `user_account_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to user_accounts. | Domain validation plus schema type/constraint. |
| QMDB-COL-0182 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | Yes | No | No | Yes | Event Type for Account Status Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-0183 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Occurred At for Account Status Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-0184 | `payload_json` | JSON document | `JSON` | Yes | None | Yes | No | No | No | Versioned bounded event metadata; core record fields remain relational. | Domain validation plus schema type/constraint. |
| QMDB-COL-0185 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | No | No | Yes | Content Hash for Account Status Events. | Domain validation plus schema type/constraint. |

### QMDB-TBL-028 — `authentication_security_events`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-028 |
| Logical Table Name | authentication_security_events |
| Owning Module | Identity and Authentication |
| Purpose | Stores authentication security events as the Identity and Authentication module's governed relational record. |
| Aggregate | Identity and Authentication |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | HIGH |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0042: (user_account_id) -> user_accounts(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0058 (user_account_id); QMDB-IDX-0059 (occurred_at, id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Authentication Security Events by relational/public identity; Read Authentication Security Events by bounded occurrence range |
| Implementation Phase | P2 |
| Related Requirements | QMDB-DR-005; QMDB-FR-IAM-001; QMDB-FR-IAM-002; QMDB-FR-IAM-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0186 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0187 | `user_account_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to user_accounts. | Domain validation plus schema type/constraint. |
| QMDB-COL-0188 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | Yes | Yes | No | Yes | Event Type for Authentication Security Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-0189 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Occurred At for Authentication Security Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-0190 | `payload_json` | JSON document | `JSON` | Yes | None | Yes | Yes | No | No | Versioned bounded event metadata; core record fields remain relational. | Domain validation plus schema type/constraint. |
| QMDB-COL-0191 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | Yes | No | Yes | Content Hash for Authentication Security Events. | Domain validation plus schema type/constraint. |

### QMDB-TBL-029 — `persons`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-029 |
| Logical Table Name | persons |
| Owning Module | People and Guardianship |
| Purpose | Stores persons as the People and Guardianship module's governed relational record. |
| Aggregate | Person |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | MODERATE |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | None |
| Check Constraints | None |
| Indexes | QMDB-IDX-0060 (public_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Persons by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-008; QMDB-FR-PPL-001; QMDB-FR-PPL-002; QMDB-FR-PPL-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0192 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0193 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0194 | `birth_date_ciphertext` | Encryption ciphertext | `VARBINARY(512)` | Yes | None | No | Yes | Yes | No | Birth Date Ciphertext for Persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-0195 | `minor_status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Minor Status Code for Persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-0196 | `identity_status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Identity Status Code for Persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-0197 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-1908 | `encryption_key_id` | Key identifier | `VARCHAR(255)` | Yes | None | No | Yes | No | No | Provider-neutral envelope key reference; no key material. | Domain validation plus schema constraint. |

### QMDB-TBL-030 — `person_names`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-030 |
| Logical Table Name | person_names |
| Owning Module | People and Guardianship |
| Purpose | Stores person names as the People and Guardianship module's governed relational record. |
| Aggregate | Person |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0043: (person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0079: Effective-until shall be null or later than effective-from. |
| Indexes | QMDB-IDX-0061 (public_id); QMDB-IDX-0062 (person_id); QMDB-IDX-0655 (display_name, id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Person Names by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-008; QMDB-FR-PPL-001; QMDB-FR-PPL-002; QMDB-FR-PPL-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0198 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0199 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0200 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-0201 | `name_type_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Name Type Code for Person Names. | Domain validation plus schema type/constraint. |
| QMDB-COL-0202 | `display_name` | Name | `VARCHAR(191)` | No | None | No | Yes | No | Yes | Display Name for Person Names. | Domain validation plus schema type/constraint. |
| QMDB-COL-0203 | `arabic_name` | Arabic text | `VARCHAR(191)` | Yes | None | No | Yes | No | No | Arabic Name for Person Names. | Domain validation plus schema type/constraint. |
| QMDB-COL-0204 | `effective_from` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Effective From for Person Names. | Domain validation plus schema type/constraint. |
| QMDB-COL-0205 | `effective_until` | UTC timestamp | `DATETIME(6)` | Yes | None | No | Yes | No | No | Effective Until for Person Names. | Domain validation plus schema type/constraint. |
| QMDB-COL-0206 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Person Names. | Domain validation plus schema type/constraint. |

### QMDB-TBL-031 — `person_profiles`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-031 |
| Logical Table Name | person_profiles |
| Owning Module | People and Guardianship |
| Purpose | Stores person profiles as the People and Guardianship module's governed relational record. |
| Aggregate | Person |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0044: (person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0082: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0063 (public_id); QMDB-IDX-0064 (person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Person Profiles by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-008; QMDB-FR-PPL-001; QMDB-FR-PPL-002; QMDB-FR-PPL-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0207 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0208 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0209 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-0210 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Person Profiles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0211 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Person Profiles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0212 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Person Profiles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0213 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Person Profiles. | Domain validation plus schema type/constraint. |

### QMDB-TBL-032 — `public_profiles`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-032 |
| Logical Table Name | public_profiles |
| Owning Module | People and Guardianship |
| Purpose | Stores public profiles as a rebuildable read model. |
| Aggregate | Person |
| Authoritative or Projection | Projection |
| Tenant Scope | PUBLIC_PROJECTION |
| Expected Volume Class | PROJECTION |
| Lifecycle Category | Rebuildable Projection |
| Data Classification | QMDB-DCL-001 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0045: (person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0084: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0065 (person_id); QMDB-IDX-0643 (active_handle_key) |
| Versioning Strategy | Track authoritative source version and rebuild deterministically. |
| Deletion or Archival Strategy | May be purged and rebuilt from authoritative source. |
| Audit Strategy | Record rebuild source/version and suppress restricted fields. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Public Profiles by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-008; QMDB-FR-PPL-001; QMDB-FR-PPL-002; QMDB-FR-PPL-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | OD-053; OD-070 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0214 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0215 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-0216 | `source_version` | Version number | `BIGINT UNSIGNED` | No | None | No | No | No | Yes | Source Version for Public Profiles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0217 | `projection_payload_json` | JSON document | `JSON` | No | None | No | No | No | No | Bounded rebuildable read-model payload with explicit schema version. | Domain validation plus schema type/constraint. |
| QMDB-COL-0218 | `projected_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | Yes | Projected At for Public Profiles. | Domain validation plus schema type/constraint. |
| QMDB-COL-1900 | `public_handle` | Public handle | `VARCHAR(64)` | Yes | None | No | No | No | Yes | Optional normalized public handle; policy and activation remain OD-053. | Domain validation plus schema constraint. |
| QMDB-COL-1901 | `active_handle_key` | Generated active key | `VARCHAR(64) GENERATED STORED` | Yes | None | No | No | No | Yes | Nullable status-aware handle key for MySQL active uniqueness. | Domain validation plus schema constraint. |

### QMDB-TBL-033 — `memorizer_profiles`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-033 |
| Logical Table Name | memorizer_profiles |
| Owning Module | People and Guardianship |
| Purpose | Stores memorizer profiles as the People and Guardianship module's governed relational record. |
| Aggregate | Person |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0046: (person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0087: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0066 (public_id); QMDB-IDX-0067 (person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Memorizer Profiles by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-008; QMDB-FR-PPL-001; QMDB-FR-PPL-002; QMDB-FR-PPL-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0219 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0220 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0221 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-0222 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Memorizer Profiles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0223 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Memorizer Profiles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0224 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Memorizer Profiles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0225 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Memorizer Profiles. | Domain validation plus schema type/constraint. |

### QMDB-TBL-034 — `competitor_profiles`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-034 |
| Logical Table Name | competitor_profiles |
| Owning Module | People and Guardianship |
| Purpose | Stores competitor profiles as the People and Guardianship module's governed relational record. |
| Aggregate | Person |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0047: (person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0090: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0068 (public_id); QMDB-IDX-0069 (person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Competitor Profiles by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-008; QMDB-FR-PPL-001; QMDB-FR-PPL-002; QMDB-FR-PPL-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0226 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0227 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0228 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-0229 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Competitor Profiles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0230 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Competitor Profiles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0231 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Competitor Profiles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0232 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Competitor Profiles. | Domain validation plus schema type/constraint. |

### QMDB-TBL-035 — `judge_profiles`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-035 |
| Logical Table Name | judge_profiles |
| Owning Module | People and Guardianship |
| Purpose | Stores judge profiles as the People and Guardianship module's governed relational record. |
| Aggregate | Person |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0048: (person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0093: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0070 (public_id); QMDB-IDX-0071 (person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Judge Profiles by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-008; QMDB-FR-PPL-001; QMDB-FR-PPL-002; QMDB-FR-PPL-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0233 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0234 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0235 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-0236 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Judge Profiles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0237 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Judge Profiles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0238 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Judge Profiles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0239 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Judge Profiles. | Domain validation plus schema type/constraint. |

### QMDB-TBL-036 — `coach_profiles`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-036 |
| Logical Table Name | coach_profiles |
| Owning Module | People and Guardianship |
| Purpose | Stores coach profiles as the People and Guardianship module's governed relational record. |
| Aggregate | Person |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0049: (person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0096: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0072 (public_id); QMDB-IDX-0073 (person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Coach Profiles by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-008; QMDB-FR-PPL-001; QMDB-FR-PPL-002; QMDB-FR-PPL-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0240 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0241 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0242 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-0243 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Coach Profiles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0244 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Coach Profiles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0245 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Coach Profiles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0246 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Coach Profiles. | Domain validation plus schema type/constraint. |

### QMDB-TBL-037 — `teacher_profiles`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-037 |
| Logical Table Name | teacher_profiles |
| Owning Module | People and Guardianship |
| Purpose | Stores teacher profiles as the People and Guardianship module's governed relational record. |
| Aggregate | Person |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0050: (person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0099: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0074 (public_id); QMDB-IDX-0075 (person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Teacher Profiles by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-008; QMDB-FR-PPL-001; QMDB-FR-PPL-002; QMDB-FR-PPL-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0247 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0248 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0249 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-0250 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Teacher Profiles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0251 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Teacher Profiles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0252 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Teacher Profiles. | Domain validation plus schema type/constraint. |
| QMDB-COL-0253 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Teacher Profiles. | Domain validation plus schema type/constraint. |

### QMDB-TBL-038 — `person_affiliations`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-038 |
| Logical Table Name | person_affiliations |
| Owning Module | People and Guardianship |
| Purpose | Stores person affiliations as the People and Guardianship module's governed relational record. |
| Aggregate | Person |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0051: (workspace_id) -> workspaces(id); QMDB-REL-0052: (person_id) -> persons(id); QMDB-REL-0053: (organization_id) -> organizations(id) |
| Check Constraints | QMDB-CST-0103: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0076 (public_id); QMDB-IDX-0077 (workspace_id, status_code, created_at, id); QMDB-IDX-0078 (person_id); QMDB-IDX-0079 (organization_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Person Affiliations by relational/public identity; List Person Affiliations within one Workspace using keyset pagination |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-008; QMDB-FR-PPL-001; QMDB-FR-PPL-002; QMDB-FR-PPL-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0254 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0255 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0256 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0257 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-0258 | `organization_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to organizations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0259 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Person Affiliations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0260 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Person Affiliations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0261 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Person Affiliations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0262 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Person Affiliations. | Domain validation plus schema type/constraint. |

### QMDB-TBL-039 — `person_geography_representations`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-039 |
| Logical Table Name | person_geography_representations |
| Owning Module | People and Guardianship |
| Purpose | Stores person geography representations as the People and Guardianship module's governed relational record. |
| Aggregate | Person |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0054: (workspace_id) -> workspaces(id); QMDB-REL-0055: (person_id) -> persons(id); QMDB-REL-0056: (administrative_area_id) -> administrative_areas(id) |
| Check Constraints | QMDB-CST-0107: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0080 (public_id); QMDB-IDX-0081 (workspace_id, status_code, created_at, id); QMDB-IDX-0082 (person_id); QMDB-IDX-0083 (administrative_area_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Person Geography Representations by relational/public identity; List Person Geography Representations within one Workspace using keyset pagination |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-008; QMDB-FR-PPL-001; QMDB-FR-PPL-002; QMDB-FR-PPL-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0263 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0264 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0265 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0266 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-0267 | `administrative_area_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to administrative_areas. | Domain validation plus schema type/constraint. |
| QMDB-COL-0268 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Person Geography Representations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0269 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Person Geography Representations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0270 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Person Geography Representations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0271 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Person Geography Representations. | Domain validation plus schema type/constraint. |

### QMDB-TBL-040 — `guardian_relationships`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-040 |
| Logical Table Name | guardian_relationships |
| Owning Module | People and Guardianship |
| Purpose | Stores guardian relationships as the People and Guardianship module's governed relational record. |
| Aggregate | Guardian Relationship |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0463: (guardian_person_id) -> persons(id); QMDB-REL-0464: (subject_person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0110: Effective-until shall be null or later than effective-from. |
| Indexes | QMDB-IDX-0084 (public_id); QMDB-IDX-0639 (guardian_person_id); QMDB-IDX-0640 (subject_person_id); QMDB-IDX-0647 (subject_person_id, guardian_person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Guardian Relationships by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-008; QMDB-FR-PPL-001; QMDB-FR-PPL-002; QMDB-FR-PPL-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0272 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0273 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0275 | `guardian_person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | No | Yes | No | Yes | Guardian Person ID for Guardian Relationships. | Domain validation plus schema type/constraint. |
| QMDB-COL-0276 | `subject_person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | No | Yes | No | Yes | Subject Person ID for Guardian Relationships. | Domain validation plus schema type/constraint. |
| QMDB-COL-0277 | `authority_scope_code` | Status | `VARCHAR(64)` | No | None | No | Yes | No | Yes | Authority Scope Code for Guardian Relationships. | Domain validation plus schema type/constraint. |
| QMDB-COL-0278 | `effective_from` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Effective From for Guardian Relationships. | Domain validation plus schema type/constraint. |
| QMDB-COL-0279 | `effective_until` | UTC timestamp | `DATETIME(6)` | Yes | None | No | Yes | No | No | Effective Until for Guardian Relationships. | Domain validation plus schema type/constraint. |
| QMDB-COL-0280 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Guardian Relationships. | Domain validation plus schema type/constraint. |
| QMDB-COL-1905 | `active_relationship_key` | Generated active key | `BINARY(32) GENERATED STORED` | Yes | None | No | Yes | No | Yes | Status-aware hash of guardian, subject and authority scope; NULL when inactive. | Domain validation plus schema constraint. |

### QMDB-TBL-041 — `consent_records`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-041 |
| Logical Table Name | consent_records |
| Owning Module | People and Guardianship |
| Purpose | Stores consent records as the People and Guardianship module's governed relational record. |
| Aggregate | Guardian Relationship |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0058: (guardian_relationship_id) -> guardian_relationships(id); QMDB-REL-0059: (person_id) -> persons(id); QMDB-REL-0470: (notice_version_id) -> privacy_notice_versions(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0086 (public_id); QMDB-IDX-0087 (guardian_relationship_id); QMDB-IDX-0088 (person_id); QMDB-IDX-0648 (notice_version_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Consent Records by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-008; QMDB-FR-PPL-001; QMDB-FR-PPL-002; QMDB-FR-PPL-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0281 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0282 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0283 | `guardian_relationship_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to guardian_relationships. | Domain validation plus schema type/constraint. |
| QMDB-COL-0284 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-0285 | `purpose_code` | Human-readable code | `VARCHAR(64)` | No | None | No | Yes | No | Yes | Purpose Code for Consent Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-0286 | `notice_version_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | No | Yes | No | Yes | Notice Version ID for Consent Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-0287 | `granted_at` | UTC timestamp | `DATETIME(6)` | Yes | None | No | Yes | No | No | Granted At for Consent Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-0288 | `withdrawn_at` | UTC timestamp | `DATETIME(6)` | Yes | None | No | Yes | No | No | Withdrawn At for Consent Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-0289 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Consent Records. | Domain validation plus schema type/constraint. |

### QMDB-TBL-042 — `consent_events`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-042 |
| Logical Table Name | consent_events |
| Owning Module | People and Guardianship |
| Purpose | Stores consent events as the People and Guardianship module's governed relational record. |
| Aggregate | Guardian Relationship |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | HIGH |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0060: (consent_record_id) -> consent_records(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0089 (consent_record_id); QMDB-IDX-0090 (occurred_at, id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Consent Events by relational/public identity; Read Consent Events by bounded occurrence range |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-008; QMDB-FR-PPL-001; QMDB-FR-PPL-002; QMDB-FR-PPL-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0290 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0291 | `consent_record_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to consent_records. | Domain validation plus schema type/constraint. |
| QMDB-COL-0292 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | Yes | Yes | No | Yes | Event Type for Consent Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-0293 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Occurred At for Consent Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-0294 | `payload_json` | JSON document | `JSON` | Yes | None | Yes | Yes | No | No | Versioned bounded event metadata; core record fields remain relational. | Domain validation plus schema type/constraint. |
| QMDB-COL-0295 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | Yes | No | Yes | Content Hash for Consent Events. | Domain validation plus schema type/constraint. |

### QMDB-TBL-043 — `identity_evidence`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-043 |
| Logical Table Name | identity_evidence |
| Owning Module | People and Guardianship |
| Purpose | Stores identity evidence as the People and Guardianship module's governed relational record. |
| Aggregate | Person |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | External Evidence Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0061: (person_id) -> persons(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0091 (public_id); QMDB-IDX-0092 (person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Identity Evidence by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-008; QMDB-FR-PPL-001; QMDB-FR-PPL-002; QMDB-FR-PPL-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-PRI-002 |
| Open Decisions | OD-058 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0296 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0297 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0298 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-0299 | `evidence_type` | Human-readable code | `VARCHAR(64)` | No | None | Yes | Yes | No | Yes | Evidence Type for Identity Evidence. | Domain validation plus schema type/constraint. |
| QMDB-COL-0300 | `object_key` | Object-storage key | `VARCHAR(1024)` | Yes | None | No | Yes | Yes | No | Private object reference; binary content is never stored in MySQL. | Domain validation plus schema type/constraint. |
| QMDB-COL-0301 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | Yes | No | Yes | Content Hash for Identity Evidence. | Domain validation plus schema type/constraint. |
| QMDB-COL-0302 | `collected_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | No | Collected At for Identity Evidence. | Domain validation plus schema type/constraint. |
| QMDB-COL-0303 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Identity Evidence. | Domain validation plus schema type/constraint. |

### QMDB-TBL-044 — `person_merge_cases`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-044 |
| Logical Table Name | person_merge_cases |
| Owning Module | People and Guardianship |
| Purpose | Stores person merge cases as the People and Guardianship module's governed relational record. |
| Aggregate | Person |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0062: (person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0118: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0093 (public_id); QMDB-IDX-0094 (person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Person Merge Cases by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-008; QMDB-FR-PPL-001; QMDB-FR-PPL-002; QMDB-FR-PPL-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0304 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0305 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0306 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-0307 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Person Merge Cases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0308 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Person Merge Cases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0309 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Person Merge Cases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0310 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Person Merge Cases. | Domain validation plus schema type/constraint. |

### QMDB-TBL-045 — `person_merge_events`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-045 |
| Logical Table Name | person_merge_events |
| Owning Module | People and Guardianship |
| Purpose | Stores person merge events as the People and Guardianship module's governed relational record. |
| Aggregate | Person |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | HIGH |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0063: (person_merge_case_id) -> person_merge_cases(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0095 (person_merge_case_id); QMDB-IDX-0096 (occurred_at, id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Person Merge Events by relational/public identity; Read Person Merge Events by bounded occurrence range |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-008; QMDB-FR-PPL-001; QMDB-FR-PPL-002; QMDB-FR-PPL-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0311 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0312 | `person_merge_case_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to person_merge_cases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0313 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | Yes | Yes | No | Yes | Event Type for Person Merge Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-0314 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Occurred At for Person Merge Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-0315 | `payload_json` | JSON document | `JSON` | Yes | None | Yes | Yes | No | No | Versioned bounded event metadata; core record fields remain relational. | Domain validation plus schema type/constraint. |
| QMDB-COL-0316 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | Yes | No | Yes | Content Hash for Person Merge Events. | Domain validation plus schema type/constraint. |

### QMDB-TBL-046 — `administrative_area_types`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-046 |
| Logical Table Name | administrative_area_types |
| Owning Module | Geography |
| Purpose | Stores administrative area types as the Geography module's governed relational record. |
| Aggregate | Administrative Area |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Versioned Configuration |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | None |
| Check Constraints | QMDB-CST-0121: Effective-until shall be null or later than effective-from. |
| Indexes | QMDB-IDX-0097 (code) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Administrative Area Types by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-009; QMDB-FR-GEO-001; QMDB-FR-GEO-002; QMDB-FR-GEO-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0317 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0318 | `code` | Human-readable code | `VARCHAR(64)` | No | None | Yes | No | No | Yes | Code for Administrative Area Types. | Domain validation plus schema type/constraint. |
| QMDB-COL-0319 | `name` | Name | `VARCHAR(191)` | No | None | No | No | No | Yes | Name for Administrative Area Types. | Domain validation plus schema type/constraint. |
| QMDB-COL-0320 | `effective_from` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Effective From for Administrative Area Types. | Domain validation plus schema type/constraint. |
| QMDB-COL-0321 | `effective_until` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Effective Until for Administrative Area Types. | Domain validation plus schema type/constraint. |
| QMDB-COL-0322 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Administrative Area Types. | Domain validation plus schema type/constraint. |

### QMDB-TBL-047 — `administrative_areas`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-047 |
| Logical Table Name | administrative_areas |
| Owning Module | Geography |
| Purpose | Stores administrative areas as the Geography module's governed relational record. |
| Aggregate | Administrative Area |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0064: (administrative_area_type_id) -> administrative_area_types(id); QMDB-REL-0461: (parent_area_id) -> administrative_areas(id); QMDB-REL-0462: (replacement_area_id) -> administrative_areas(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0098 (administrative_area_type_id); QMDB-IDX-0637 (parent_area_id); QMDB-IDX-0638 (replacement_area_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Administrative Areas by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-009; QMDB-FR-GEO-001; QMDB-FR-GEO-002; QMDB-FR-GEO-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0323 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0324 | `administrative_area_type_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to administrative_area_types. | Domain validation plus schema type/constraint. |
| QMDB-COL-0325 | `parent_area_id` | Internal identifier | `BIGINT UNSIGNED` | Yes | None | No | No | No | Yes | Parent Area ID for Administrative Areas. | Domain validation plus schema type/constraint. |
| QMDB-COL-0326 | `official_code` | Human-readable code | `VARCHAR(64)` | Yes | None | No | No | No | Yes | Official Code for Administrative Areas. | Domain validation plus schema type/constraint. |
| QMDB-COL-0327 | `official_name` | Name | `VARCHAR(191)` | No | None | No | No | No | No | Official Name for Administrative Areas. | Domain validation plus schema type/constraint. |
| QMDB-COL-0328 | `valid_from` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Valid From for Administrative Areas. | Domain validation plus schema type/constraint. |
| QMDB-COL-0329 | `valid_until` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Valid Until for Administrative Areas. | Domain validation plus schema type/constraint. |
| QMDB-COL-0330 | `replacement_area_id` | Internal identifier | `BIGINT UNSIGNED` | Yes | None | No | No | No | Yes | Replacement Area ID for Administrative Areas. | Domain validation plus schema type/constraint. |
| QMDB-COL-0331 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Administrative Areas. | Domain validation plus schema type/constraint. |

### QMDB-TBL-048 — `administrative_area_aliases`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-048 |
| Logical Table Name | administrative_area_aliases |
| Owning Module | Geography |
| Purpose | Stores administrative area aliases as the Geography module's governed relational record. |
| Aggregate | Administrative Area |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0065: (administrative_area_id) -> administrative_areas(id) |
| Check Constraints | QMDB-CST-0124: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0099 (administrative_area_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Administrative Area Aliases by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-009; QMDB-FR-GEO-001; QMDB-FR-GEO-002; QMDB-FR-GEO-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0332 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0333 | `administrative_area_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to administrative_areas. | Domain validation plus schema type/constraint. |
| QMDB-COL-0334 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Administrative Area Aliases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0335 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Administrative Area Aliases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0336 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Administrative Area Aliases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0337 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Administrative Area Aliases. | Domain validation plus schema type/constraint. |

### QMDB-TBL-049 — `administrative_area_history`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-049 |
| Logical Table Name | administrative_area_history |
| Owning Module | Geography |
| Purpose | Stores administrative area history as the Geography module's governed relational record. |
| Aggregate | Administrative Area |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0066: (administrative_area_id) -> administrative_areas(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0100 (administrative_area_id); QMDB-IDX-0101 (occurred_at, id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Administrative Area History by relational/public identity; Read Administrative Area History by bounded occurrence range |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-009; QMDB-FR-GEO-001; QMDB-FR-GEO-002; QMDB-FR-GEO-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0338 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0339 | `administrative_area_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to administrative_areas. | Domain validation plus schema type/constraint. |
| QMDB-COL-0340 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | Yes | No | No | Yes | Event Type for Administrative Area History. | Domain validation plus schema type/constraint. |
| QMDB-COL-0341 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Occurred At for Administrative Area History. | Domain validation plus schema type/constraint. |
| QMDB-COL-0342 | `payload_json` | JSON document | `JSON` | Yes | None | Yes | No | No | No | Versioned bounded event metadata; core record fields remain relational. | Domain validation plus schema type/constraint. |
| QMDB-COL-0343 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | No | No | Yes | Content Hash for Administrative Area History. | Domain validation plus schema type/constraint. |

### QMDB-TBL-050 — `venues`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-050 |
| Logical Table Name | venues |
| Owning Module | Geography |
| Purpose | Stores venues as the Geography module's governed relational record. |
| Aggregate | Administrative Area |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0067: (administrative_area_id) -> administrative_areas(id) |
| Check Constraints | QMDB-CST-0128: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0102 (public_id); QMDB-IDX-0103 (administrative_area_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Venues by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-009; QMDB-FR-GEO-001; QMDB-FR-GEO-002; QMDB-FR-GEO-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0344 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0345 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0346 | `administrative_area_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to administrative_areas. | Domain validation plus schema type/constraint. |
| QMDB-COL-0347 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Venues. | Domain validation plus schema type/constraint. |
| QMDB-COL-0348 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Venues. | Domain validation plus schema type/constraint. |
| QMDB-COL-0349 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Venues. | Domain validation plus schema type/constraint. |
| QMDB-COL-0350 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Venues. | Domain validation plus schema type/constraint. |

### QMDB-TBL-051 — `venue_areas`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-051 |
| Logical Table Name | venue_areas |
| Owning Module | Geography |
| Purpose | Stores venue areas as the Geography module's governed relational record. |
| Aggregate | Administrative Area |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0068: (venue_id) -> venues(id); QMDB-REL-0069: (administrative_area_id) -> administrative_areas(id) |
| Check Constraints | QMDB-CST-0131: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0104 (public_id); QMDB-IDX-0105 (venue_id); QMDB-IDX-0106 (administrative_area_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Venue Areas by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-009; QMDB-FR-GEO-001; QMDB-FR-GEO-002; QMDB-FR-GEO-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0351 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0352 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0353 | `venue_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to venues. | Domain validation plus schema type/constraint. |
| QMDB-COL-0354 | `administrative_area_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to administrative_areas. | Domain validation plus schema type/constraint. |
| QMDB-COL-0355 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Venue Areas. | Domain validation plus schema type/constraint. |
| QMDB-COL-0356 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Venue Areas. | Domain validation plus schema type/constraint. |
| QMDB-COL-0357 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Venue Areas. | Domain validation plus schema type/constraint. |
| QMDB-COL-0358 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Venue Areas. | Domain validation plus schema type/constraint. |

### QMDB-TBL-052 — `organization_types`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-052 |
| Logical Table Name | organization_types |
| Owning Module | Organizations |
| Purpose | Stores organization types as the Organizations module's governed relational record. |
| Aggregate | Organization |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Versioned Configuration |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | None |
| Check Constraints | QMDB-CST-0133: Effective-until shall be null or later than effective-from. |
| Indexes | QMDB-IDX-0107 (code) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Organization Types by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-010; QMDB-FR-ORG-001; QMDB-FR-ORG-002; QMDB-FR-ORG-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0359 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0360 | `code` | Human-readable code | `VARCHAR(64)` | No | None | Yes | No | No | Yes | Code for Organization Types. | Domain validation plus schema type/constraint. |
| QMDB-COL-0361 | `name` | Name | `VARCHAR(191)` | No | None | No | No | No | Yes | Name for Organization Types. | Domain validation plus schema type/constraint. |
| QMDB-COL-0362 | `effective_from` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Effective From for Organization Types. | Domain validation plus schema type/constraint. |
| QMDB-COL-0363 | `effective_until` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Effective Until for Organization Types. | Domain validation plus schema type/constraint. |
| QMDB-COL-0364 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Organization Types. | Domain validation plus schema type/constraint. |

### QMDB-TBL-053 — `organizations`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-053 |
| Logical Table Name | organizations |
| Owning Module | Organizations |
| Purpose | Stores organizations as the Organizations module's governed relational record. |
| Aggregate | Organization |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0070: (organization_type_id) -> organization_types(id) |
| Check Constraints | QMDB-CST-0136: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0108 (public_id); QMDB-IDX-0109 (organization_type_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Organizations by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-010; QMDB-FR-ORG-001; QMDB-FR-ORG-002; QMDB-FR-ORG-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0365 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0366 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0367 | `organization_type_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to organization_types. | Domain validation plus schema type/constraint. |
| QMDB-COL-0368 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Organizations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0369 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Organizations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0370 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Organizations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0371 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Organizations. | Domain validation plus schema type/constraint. |

### QMDB-TBL-054 — `organization_units`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-054 |
| Logical Table Name | organization_units |
| Owning Module | Organizations |
| Purpose | Stores organization units as the Organizations module's governed relational record. |
| Aggregate | Organization |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0071: (organization_id) -> organizations(id) |
| Check Constraints | QMDB-CST-0139: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0110 (public_id); QMDB-IDX-0111 (organization_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Organization Units by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-010; QMDB-FR-ORG-001; QMDB-FR-ORG-002; QMDB-FR-ORG-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0372 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0373 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0374 | `organization_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to organizations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0375 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Organization Units. | Domain validation plus schema type/constraint. |
| QMDB-COL-0376 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Organization Units. | Domain validation plus schema type/constraint. |
| QMDB-COL-0377 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Organization Units. | Domain validation plus schema type/constraint. |
| QMDB-COL-0378 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Organization Units. | Domain validation plus schema type/constraint. |

### QMDB-TBL-055 — `organization_relationships`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-055 |
| Logical Table Name | organization_relationships |
| Owning Module | Organizations |
| Purpose | Stores organization relationships as the Organizations module's governed relational record. |
| Aggregate | Organization |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0072: (organization_id) -> organizations(id) |
| Check Constraints | QMDB-CST-0142: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0112 (public_id); QMDB-IDX-0113 (organization_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Organization Relationships by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-010; QMDB-FR-ORG-001; QMDB-FR-ORG-002; QMDB-FR-ORG-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0379 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0380 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0381 | `organization_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to organizations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0382 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Organization Relationships. | Domain validation plus schema type/constraint. |
| QMDB-COL-0383 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Organization Relationships. | Domain validation plus schema type/constraint. |
| QMDB-COL-0384 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Organization Relationships. | Domain validation plus schema type/constraint. |
| QMDB-COL-0385 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Organization Relationships. | Domain validation plus schema type/constraint. |

### QMDB-TBL-056 — `organization_coverage_areas`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-056 |
| Logical Table Name | organization_coverage_areas |
| Owning Module | Organizations |
| Purpose | Stores organization coverage areas as the Organizations module's governed relational record. |
| Aggregate | Organization |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0073: (organization_id) -> organizations(id); QMDB-REL-0074: (administrative_area_id) -> administrative_areas(id) |
| Check Constraints | QMDB-CST-0145: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0114 (public_id); QMDB-IDX-0115 (organization_id); QMDB-IDX-0116 (administrative_area_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Organization Coverage Areas by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-010; QMDB-FR-ORG-001; QMDB-FR-ORG-002; QMDB-FR-ORG-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0386 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0387 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0388 | `organization_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to organizations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0389 | `administrative_area_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to administrative_areas. | Domain validation plus schema type/constraint. |
| QMDB-COL-0390 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Organization Coverage Areas. | Domain validation plus schema type/constraint. |
| QMDB-COL-0391 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Organization Coverage Areas. | Domain validation plus schema type/constraint. |
| QMDB-COL-0392 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Organization Coverage Areas. | Domain validation plus schema type/constraint. |
| QMDB-COL-0393 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Organization Coverage Areas. | Domain validation plus schema type/constraint. |

### QMDB-TBL-057 — `organization_verification_cases`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-057 |
| Logical Table Name | organization_verification_cases |
| Owning Module | Organizations |
| Purpose | Stores organization verification cases as the Organizations module's governed relational record. |
| Aggregate | Organization Verification |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0075: (organization_id) -> organizations(id) |
| Check Constraints | QMDB-CST-0148: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0117 (public_id); QMDB-IDX-0118 (organization_id); QMDB-IDX-0651 (status_code, created_at, id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Organization Verification Cases by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-010; QMDB-FR-ORG-001; QMDB-FR-ORG-002; QMDB-FR-ORG-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0394 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0395 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0396 | `organization_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to organizations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0397 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Organization Verification Cases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0398 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Organization Verification Cases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0399 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Organization Verification Cases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0400 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Organization Verification Cases. | Domain validation plus schema type/constraint. |

### QMDB-TBL-058 — `organization_verification_evidence`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-058 |
| Logical Table Name | organization_verification_evidence |
| Owning Module | Organizations |
| Purpose | Stores organization verification evidence as the Organizations module's governed relational record. |
| Aggregate | Organization Verification |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | External Evidence Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0076: (organization_verification_case_id) -> organization_verification_cases(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0119 (public_id); QMDB-IDX-0120 (organization_verification_case_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Organization Verification Evidence by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-010; QMDB-FR-ORG-001; QMDB-FR-ORG-002; QMDB-FR-ORG-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0401 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0402 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0403 | `organization_verification_case_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to organization_verification_cases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0404 | `evidence_type` | Human-readable code | `VARCHAR(64)` | No | None | Yes | No | No | Yes | Evidence Type for Organization Verification Evidence. | Domain validation plus schema type/constraint. |
| QMDB-COL-0405 | `object_key` | Object-storage key | `VARCHAR(1024)` | Yes | None | No | No | Yes | No | Private object reference; binary content is never stored in MySQL. | Domain validation plus schema type/constraint. |
| QMDB-COL-0406 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | No | No | Yes | Content Hash for Organization Verification Evidence. | Domain validation plus schema type/constraint. |
| QMDB-COL-0407 | `collected_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | No | Collected At for Organization Verification Evidence. | Domain validation plus schema type/constraint. |
| QMDB-COL-0408 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Organization Verification Evidence. | Domain validation plus schema type/constraint. |

### QMDB-TBL-059 — `organization_verification_decisions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-059 |
| Logical Table Name | organization_verification_decisions |
| Owning Module | Organizations |
| Purpose | Stores organization verification decisions as the Organizations module's governed relational record. |
| Aggregate | Organization Verification |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0077: (organization_verification_case_id) -> organization_verification_cases(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0121 (public_id); QMDB-IDX-0122 (organization_verification_case_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Organization Verification Decisions by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-010; QMDB-FR-ORG-001; QMDB-FR-ORG-002; QMDB-FR-ORG-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0409 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0410 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0411 | `organization_verification_case_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to organization_verification_cases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0412 | `decision_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Decision Code for Organization Verification Decisions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0413 | `reason_code` | Human-readable code | `VARCHAR(64)` | No | None | No | No | No | No | Reason Code for Organization Verification Decisions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0414 | `decided_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Decided At for Organization Verification Decisions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0415 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Organization Verification Decisions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-060 — `organization_branding_configurations`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-060 |
| Logical Table Name | organization_branding_configurations |
| Owning Module | Organizations |
| Purpose | Stores organization branding configurations as the Organizations module's governed relational record. |
| Aggregate | Organization |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Versioned Configuration |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0078: (organization_id) -> organizations(id) |
| Check Constraints | QMDB-CST-0155: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0123 (public_id); QMDB-IDX-0124 (organization_id) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Organization Branding Configurations by relational/public identity |
| Implementation Phase | P3 |
| Related Requirements | QMDB-DR-010; QMDB-FR-ORG-001; QMDB-FR-ORG-002; QMDB-FR-ORG-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0416 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0417 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0418 | `organization_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to organizations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0419 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Organization Branding Configurations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0420 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Organization Branding Configurations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0421 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Organization Branding Configurations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0422 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Organization Branding Configurations. | Domain validation plus schema type/constraint. |

### QMDB-TBL-061 — `quran_text_releases`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-061 |
| Logical Table Name | quran_text_releases |
| Owning Module | Quran Reference Governance |
| Purpose | Stores quran text releases as the Quran Reference Governance module's governed relational record. |
| Aggregate | Quran Text Release |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Versioned Configuration |
| Data Classification | QMDB-DCL-001 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0079: (quran_release_source_id) -> quran_release_sources(id) |
| Check Constraints | QMDB-CST-0157: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0125 (quran_release_source_id); QMDB-IDX-0652 (release_identifier) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Quran Text Releases by relational/public identity |
| Implementation Phase | P4 |
| Related Requirements | QMDB-DR-011; QMDB-FR-QRF-001; QMDB-FR-QRF-002; QMDB-FR-QRF-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | OD-054; OD-055; OD-063 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0423 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0424 | `quran_release_source_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to quran_release_sources. | Domain validation plus schema type/constraint. |
| QMDB-COL-0425 | `release_identifier` | Human-readable code | `VARCHAR(96)` | No | None | No | No | No | Yes | Release Identifier for Quran Text Releases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0426 | `release_version` | Version number | `INT UNSIGNED` | No | None | No | No | No | No | Release Version for Quran Text Releases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0427 | `canonical_checksum` | Content hash | `BINARY(32)` | No | None | No | No | No | No | Canonical Checksum for Quran Text Releases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0428 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Quran Text Releases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0429 | `activated_at` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Activated At for Quran Text Releases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0430 | `superseded_at` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Superseded At for Quran Text Releases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0431 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Quran Text Releases. | Domain validation plus schema type/constraint. |

### QMDB-TBL-062 — `quran_release_sources`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-062 |
| Logical Table Name | quran_release_sources |
| Owning Module | Quran Reference Governance |
| Purpose | Stores quran release sources as the Quran Reference Governance module's governed relational record. |
| Aggregate | Quran Text Release |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Versioned Configuration |
| Data Classification | QMDB-DCL-001 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | None |
| Check Constraints | QMDB-CST-0159: Version value shall be positive and monotonic within its lineage. |
| Indexes | None |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Quran Release Sources by relational/public identity |
| Implementation Phase | P4 |
| Related Requirements | QMDB-DR-011; QMDB-FR-QRF-001; QMDB-FR-QRF-002; QMDB-FR-QRF-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | OD-054; OD-055; OD-063 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0432 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0433 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Quran Release Sources. | Domain validation plus schema type/constraint. |
| QMDB-COL-0434 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Quran Release Sources. | Domain validation plus schema type/constraint. |
| QMDB-COL-0435 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Quran Release Sources. | Domain validation plus schema type/constraint. |
| QMDB-COL-0436 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Quran Release Sources. | Domain validation plus schema type/constraint. |

### QMDB-TBL-063 — `quran_readings`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-063 |
| Logical Table Name | quran_readings |
| Owning Module | Quran Reference Governance |
| Purpose | Stores quran readings as the Quran Reference Governance module's governed relational record. |
| Aggregate | Quran Text Release |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-001 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0080: (quran_text_release_id) -> quran_text_releases(id) |
| Check Constraints | QMDB-CST-0161: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0126 (quran_text_release_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Quran Readings by relational/public identity |
| Implementation Phase | P4 |
| Related Requirements | QMDB-DR-011; QMDB-FR-QRF-001; QMDB-FR-QRF-002; QMDB-FR-QRF-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | OD-054; OD-055; OD-063 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0437 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0438 | `quran_text_release_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to quran_text_releases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0439 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Quran Readings. | Domain validation plus schema type/constraint. |
| QMDB-COL-0440 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Quran Readings. | Domain validation plus schema type/constraint. |
| QMDB-COL-0441 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Quran Readings. | Domain validation plus schema type/constraint. |
| QMDB-COL-0442 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Quran Readings. | Domain validation plus schema type/constraint. |

### QMDB-TBL-064 — `surahs`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-064 |
| Logical Table Name | surahs |
| Owning Module | Quran Reference Governance |
| Purpose | Stores surahs as the Quran Reference Governance module's governed relational record. |
| Aggregate | Quran Text Release |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0081: (quran_text_release_id) -> quran_text_releases(id) |
| Check Constraints | QMDB-CST-0163: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0127 (quran_text_release_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Surahs by relational/public identity |
| Implementation Phase | P4 |
| Related Requirements | QMDB-DR-011; QMDB-FR-QRF-001; QMDB-FR-QRF-002; QMDB-FR-QRF-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | OD-054; OD-055; OD-063 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0443 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0444 | `quran_text_release_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to quran_text_releases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0445 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Surahs. | Domain validation plus schema type/constraint. |
| QMDB-COL-0446 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Surahs. | Domain validation plus schema type/constraint. |
| QMDB-COL-0447 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Surahs. | Domain validation plus schema type/constraint. |
| QMDB-COL-0448 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Surahs. | Domain validation plus schema type/constraint. |

### QMDB-TBL-065 — `ayahs`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-065 |
| Logical Table Name | ayahs |
| Owning Module | Quran Reference Governance |
| Purpose | Stores ayahs as the Quran Reference Governance module's governed relational record. |
| Aggregate | Quran Text Release |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0082: (quran_text_release_id) -> quran_text_releases(id); QMDB-REL-0083: (quran_reading_id) -> quran_readings(id); QMDB-REL-0084: (surah_id) -> surahs(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0128 (quran_text_release_id); QMDB-IDX-0129 (quran_reading_id); QMDB-IDX-0130 (surah_id); QMDB-IDX-0653 (quran_text_release_id, quran_reading_id, surah_number, ayah_number) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Ayahs by relational/public identity |
| Implementation Phase | P4 |
| Related Requirements | QMDB-DR-011; QMDB-FR-QRF-001; QMDB-FR-QRF-002; QMDB-FR-QRF-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | OD-054; OD-055; OD-063 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0449 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0450 | `quran_text_release_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to quran_text_releases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0451 | `quran_reading_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to quran_readings. | Domain validation plus schema type/constraint. |
| QMDB-COL-0452 | `surah_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to surahs. | Domain validation plus schema type/constraint. |
| QMDB-COL-0453 | `surah_number` | Sequence number | `SMALLINT UNSIGNED` | No | None | No | No | No | Yes | Surah Number for Ayahs. | Domain validation plus schema type/constraint. |
| QMDB-COL-0454 | `ayah_number` | Sequence number | `SMALLINT UNSIGNED` | No | None | No | No | No | Yes | Ayah Number for Ayahs. | Domain validation plus schema type/constraint. |
| QMDB-COL-0455 | `canonical_text` | Canonical Quran text | `LONGTEXT COLLATE utf8mb4_0900_bin` | No | None | No | No | No | No | Canonical Text for Ayahs. | Domain validation plus schema type/constraint. |
| QMDB-COL-0456 | `search_normalized_text` | Search-normalized text | `LONGTEXT COLLATE utf8mb4_0900_ai_ci` | No | None | No | No | No | No | Search Normalized Text for Ayahs. | Domain validation plus schema type/constraint. |
| QMDB-COL-0457 | `text_checksum` | Content hash | `BINARY(32)` | No | None | No | No | No | No | Text Checksum for Ayahs. | Domain validation plus schema type/constraint. |
| QMDB-COL-0458 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Ayahs. | Domain validation plus schema type/constraint. |

### QMDB-TBL-066 — `juz_ranges`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-066 |
| Logical Table Name | juz_ranges |
| Owning Module | Quran Reference Governance |
| Purpose | Stores juz ranges as the Quran Reference Governance module's governed relational record. |
| Aggregate | Quran Text Release |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0085: (quran_text_release_id) -> quran_text_releases(id); QMDB-REL-0086: (quran_reading_id) -> quran_readings(id) |
| Check Constraints | QMDB-CST-0167: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0131 (quran_text_release_id); QMDB-IDX-0132 (quran_reading_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Juz Ranges by relational/public identity |
| Implementation Phase | P4 |
| Related Requirements | QMDB-DR-011; QMDB-FR-QRF-001; QMDB-FR-QRF-002; QMDB-FR-QRF-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0459 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0460 | `quran_text_release_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to quran_text_releases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0461 | `quran_reading_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to quran_readings. | Domain validation plus schema type/constraint. |
| QMDB-COL-0462 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Juz Ranges. | Domain validation plus schema type/constraint. |
| QMDB-COL-0463 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Juz Ranges. | Domain validation plus schema type/constraint. |
| QMDB-COL-0464 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Juz Ranges. | Domain validation plus schema type/constraint. |
| QMDB-COL-0465 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Juz Ranges. | Domain validation plus schema type/constraint. |

### QMDB-TBL-067 — `hizb_ranges`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-067 |
| Logical Table Name | hizb_ranges |
| Owning Module | Quran Reference Governance |
| Purpose | Stores hizb ranges as the Quran Reference Governance module's governed relational record. |
| Aggregate | Quran Text Release |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0087: (quran_text_release_id) -> quran_text_releases(id); QMDB-REL-0088: (quran_reading_id) -> quran_readings(id) |
| Check Constraints | QMDB-CST-0169: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0133 (quran_text_release_id); QMDB-IDX-0134 (quran_reading_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Hizb Ranges by relational/public identity |
| Implementation Phase | P4 |
| Related Requirements | QMDB-DR-011; QMDB-FR-QRF-001; QMDB-FR-QRF-002; QMDB-FR-QRF-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0466 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0467 | `quran_text_release_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to quran_text_releases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0468 | `quran_reading_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to quran_readings. | Domain validation plus schema type/constraint. |
| QMDB-COL-0469 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Hizb Ranges. | Domain validation plus schema type/constraint. |
| QMDB-COL-0470 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Hizb Ranges. | Domain validation plus schema type/constraint. |
| QMDB-COL-0471 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Hizb Ranges. | Domain validation plus schema type/constraint. |
| QMDB-COL-0472 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Hizb Ranges. | Domain validation plus schema type/constraint. |

### QMDB-TBL-068 — `rub_ranges`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-068 |
| Logical Table Name | rub_ranges |
| Owning Module | Quran Reference Governance |
| Purpose | Stores rub ranges as the Quran Reference Governance module's governed relational record. |
| Aggregate | Quran Text Release |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0089: (quran_text_release_id) -> quran_text_releases(id); QMDB-REL-0090: (quran_reading_id) -> quran_readings(id) |
| Check Constraints | QMDB-CST-0171: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0135 (quran_text_release_id); QMDB-IDX-0136 (quran_reading_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Rub Ranges by relational/public identity |
| Implementation Phase | P4 |
| Related Requirements | QMDB-DR-011; QMDB-FR-QRF-001; QMDB-FR-QRF-002; QMDB-FR-QRF-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0473 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0474 | `quran_text_release_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to quran_text_releases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0475 | `quran_reading_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to quran_readings. | Domain validation plus schema type/constraint. |
| QMDB-COL-0476 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Rub Ranges. | Domain validation plus schema type/constraint. |
| QMDB-COL-0477 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Rub Ranges. | Domain validation plus schema type/constraint. |
| QMDB-COL-0478 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Rub Ranges. | Domain validation plus schema type/constraint. |
| QMDB-COL-0479 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Rub Ranges. | Domain validation plus schema type/constraint. |

### QMDB-TBL-069 — `page_references`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-069 |
| Logical Table Name | page_references |
| Owning Module | Quran Reference Governance |
| Purpose | Stores page references as the Quran Reference Governance module's governed relational record. |
| Aggregate | Quran Text Release |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0091: (quran_text_release_id) -> quran_text_releases(id); QMDB-REL-0092: (quran_reading_id) -> quran_readings(id) |
| Check Constraints | QMDB-CST-0173: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0137 (quran_text_release_id); QMDB-IDX-0138 (quran_reading_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Page References by relational/public identity |
| Implementation Phase | P4 |
| Related Requirements | QMDB-DR-011; QMDB-FR-QRF-001; QMDB-FR-QRF-002; QMDB-FR-QRF-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0480 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0481 | `quran_text_release_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to quran_text_releases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0482 | `quran_reading_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to quran_readings. | Domain validation plus schema type/constraint. |
| QMDB-COL-0483 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Page References. | Domain validation plus schema type/constraint. |
| QMDB-COL-0484 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Page References. | Domain validation plus schema type/constraint. |
| QMDB-COL-0485 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Page References. | Domain validation plus schema type/constraint. |
| QMDB-COL-0486 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Page References. | Domain validation plus schema type/constraint. |

### QMDB-TBL-070 — `passage_ranges`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-070 |
| Logical Table Name | passage_ranges |
| Owning Module | Quran Reference Governance |
| Purpose | Stores passage ranges as the Quran Reference Governance module's governed relational record. |
| Aggregate | Quran Text Release |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0093: (quran_text_release_id) -> quran_text_releases(id); QMDB-REL-0094: (quran_reading_id) -> quran_readings(id); QMDB-REL-0465: (start_ayah_id) -> ayahs(id); QMDB-REL-0466: (end_ayah_id) -> ayahs(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0139 (quran_text_release_id); QMDB-IDX-0140 (quran_reading_id); QMDB-IDX-0641 (start_ayah_id); QMDB-IDX-0642 (end_ayah_id); QMDB-IDX-0654 (quran_text_release_id, quran_reading_id, id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Passage Ranges by relational/public identity |
| Implementation Phase | P4 |
| Related Requirements | QMDB-DR-011; QMDB-FR-QRF-001; QMDB-FR-QRF-002; QMDB-FR-QRF-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | OD-054; OD-055; OD-063 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0487 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0488 | `quran_text_release_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to quran_text_releases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0489 | `quran_reading_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to quran_readings. | Domain validation plus schema type/constraint. |
| QMDB-COL-0490 | `start_ayah_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | No | No | No | Yes | Start Ayah ID for Passage Ranges. | Domain validation plus schema type/constraint. |
| QMDB-COL-0491 | `end_ayah_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | No | No | No | Yes | End Ayah ID for Passage Ranges. | Domain validation plus schema type/constraint. |
| QMDB-COL-0492 | `range_checksum` | Content hash | `BINARY(32)` | No | None | No | No | No | No | Range Checksum for Passage Ranges. | Domain validation plus schema type/constraint. |
| QMDB-COL-0493 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Passage Ranges. | Domain validation plus schema type/constraint. |

### QMDB-TBL-071 — `tajwid_rule_taxonomy`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-071 |
| Logical Table Name | tajwid_rule_taxonomy |
| Owning Module | Quran Reference Governance |
| Purpose | Stores tajwid rule taxonomy as the Quran Reference Governance module's governed relational record. |
| Aggregate | Quran Text Release |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Versioned Configuration |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0095: (quran_text_release_id) -> quran_text_releases(id) |
| Check Constraints | QMDB-CST-0176: Effective-until shall be null or later than effective-from. |
| Indexes | QMDB-IDX-0141 (quran_text_release_id); QMDB-IDX-0142 (code) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Tajwid Rule Taxonomy by relational/public identity |
| Implementation Phase | P4 |
| Related Requirements | QMDB-DR-011; QMDB-FR-QRF-001; QMDB-FR-QRF-002; QMDB-FR-QRF-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | OD-054; OD-055; OD-063 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0494 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0495 | `quran_text_release_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to quran_text_releases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0496 | `code` | Human-readable code | `VARCHAR(64)` | No | None | Yes | No | No | Yes | Code for Tajwid Rule Taxonomy. | Domain validation plus schema type/constraint. |
| QMDB-COL-0497 | `name` | Name | `VARCHAR(191)` | No | None | No | No | No | Yes | Name for Tajwid Rule Taxonomy. | Domain validation plus schema type/constraint. |
| QMDB-COL-0498 | `effective_from` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Effective From for Tajwid Rule Taxonomy. | Domain validation plus schema type/constraint. |
| QMDB-COL-0499 | `effective_until` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Effective Until for Tajwid Rule Taxonomy. | Domain validation plus schema type/constraint. |
| QMDB-COL-0500 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Tajwid Rule Taxonomy. | Domain validation plus schema type/constraint. |

### QMDB-TBL-072 — `competition_mistake_taxonomy`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-072 |
| Logical Table Name | competition_mistake_taxonomy |
| Owning Module | Quran Reference Governance |
| Purpose | Stores competition mistake taxonomy as the Quran Reference Governance module's governed relational record. |
| Aggregate | Quran Text Release |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Versioned Configuration |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0096: (quran_text_release_id) -> quran_text_releases(id) |
| Check Constraints | QMDB-CST-0178: Effective-until shall be null or later than effective-from. |
| Indexes | QMDB-IDX-0143 (quran_text_release_id); QMDB-IDX-0144 (code) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Competition Mistake Taxonomy by relational/public identity |
| Implementation Phase | P4 |
| Related Requirements | QMDB-DR-011; QMDB-FR-QRF-001; QMDB-FR-QRF-002; QMDB-FR-QRF-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0501 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0502 | `quran_text_release_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to quran_text_releases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0503 | `code` | Human-readable code | `VARCHAR(64)` | No | None | Yes | No | No | Yes | Code for Competition Mistake Taxonomy. | Domain validation plus schema type/constraint. |
| QMDB-COL-0504 | `name` | Name | `VARCHAR(191)` | No | None | No | No | No | Yes | Name for Competition Mistake Taxonomy. | Domain validation plus schema type/constraint. |
| QMDB-COL-0505 | `effective_from` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Effective From for Competition Mistake Taxonomy. | Domain validation plus schema type/constraint. |
| QMDB-COL-0506 | `effective_until` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Effective Until for Competition Mistake Taxonomy. | Domain validation plus schema type/constraint. |
| QMDB-COL-0507 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Competition Mistake Taxonomy. | Domain validation plus schema type/constraint. |

### QMDB-TBL-073 — `quran_release_approvals`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-073 |
| Logical Table Name | quran_release_approvals |
| Owning Module | Quran Reference Governance |
| Purpose | Stores quran release approvals as the Quran Reference Governance module's governed relational record. |
| Aggregate | Quran Text Release |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Versioned Configuration |
| Data Classification | QMDB-DCL-001 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0097: (quran_text_release_id) -> quran_text_releases(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0145 (quran_text_release_id) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Quran Release Approvals by relational/public identity |
| Implementation Phase | P4 |
| Related Requirements | QMDB-DR-011; QMDB-FR-QRF-001; QMDB-FR-QRF-002; QMDB-FR-QRF-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | OD-054; OD-055; OD-063 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0508 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0509 | `quran_text_release_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to quran_text_releases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0510 | `decision_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Decision Code for Quran Release Approvals. | Domain validation plus schema type/constraint. |
| QMDB-COL-0511 | `reason_code` | Human-readable code | `VARCHAR(64)` | No | None | No | No | No | No | Reason Code for Quran Release Approvals. | Domain validation plus schema type/constraint. |
| QMDB-COL-0512 | `decided_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Decided At for Quran Release Approvals. | Domain validation plus schema type/constraint. |
| QMDB-COL-0513 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Quran Release Approvals. | Domain validation plus schema type/constraint. |

### QMDB-TBL-074 — `quran_release_checksums`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-074 |
| Logical Table Name | quran_release_checksums |
| Owning Module | Quran Reference Governance |
| Purpose | Stores quran release checksums as the Quran Reference Governance module's governed relational record. |
| Aggregate | Quran Text Release |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Versioned Configuration |
| Data Classification | QMDB-DCL-001 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0098: (quran_text_release_id) -> quran_text_releases(id) |
| Check Constraints | QMDB-CST-0181: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0146 (quran_text_release_id); QMDB-IDX-0659 (quran_text_release_id, content_hash) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Quran Release Checksums by relational/public identity |
| Implementation Phase | P4 |
| Related Requirements | QMDB-DR-011; QMDB-FR-QRF-001; QMDB-FR-QRF-002; QMDB-FR-QRF-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | OD-054; OD-055; OD-063 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0514 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0515 | `quran_text_release_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to quran_text_releases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0516 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Quran Release Checksums. | Domain validation plus schema type/constraint. |
| QMDB-COL-0517 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Quran Release Checksums. | Domain validation plus schema type/constraint. |
| QMDB-COL-0518 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Quran Release Checksums. | Domain validation plus schema type/constraint. |
| QMDB-COL-0519 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Quran Release Checksums. | Domain validation plus schema type/constraint. |
| QMDB-COL-1909 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | No | No | Yes | Content hash for release integrity. | Checksum algorithm, canonical byte serialization and qualified release verification. |
| QMDB-COL-1910 | `algorithm_code` | Human-readable code | `VARCHAR(32)` | No | None | Yes | No | No | No | Human-readable code for release integrity. | Checksum algorithm, canonical byte serialization and qualified release verification. |

### QMDB-TBL-075 — `quran_release_correction_history`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-075 |
| Logical Table Name | quran_release_correction_history |
| Owning Module | Quran Reference Governance |
| Purpose | Stores quran release correction history as the Quran Reference Governance module's governed relational record. |
| Aggregate | Quran Text Release |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-001 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0099: (quran_text_release_id) -> quran_text_releases(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0147 (quran_text_release_id); QMDB-IDX-0148 (occurred_at, id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Quran Release Correction History by relational/public identity; Read Quran Release Correction History by bounded occurrence range |
| Implementation Phase | P4 |
| Related Requirements | QMDB-DR-011; QMDB-FR-QRF-001; QMDB-FR-QRF-002; QMDB-FR-QRF-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | OD-054; OD-055; OD-063 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0520 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0521 | `quran_text_release_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to quran_text_releases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0522 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | Yes | No | No | Yes | Event Type for Quran Release Correction History. | Domain validation plus schema type/constraint. |
| QMDB-COL-0523 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Occurred At for Quran Release Correction History. | Domain validation plus schema type/constraint. |
| QMDB-COL-0524 | `payload_json` | JSON document | `JSON` | Yes | None | Yes | No | No | No | Versioned bounded event metadata; core record fields remain relational. | Domain validation plus schema type/constraint. |
| QMDB-COL-0525 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | No | No | Yes | Content Hash for Quran Release Correction History. | Domain validation plus schema type/constraint. |

### QMDB-TBL-076 — `competition_series`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-076 |
| Logical Table Name | competition_series |
| Owning Module | Competition Configuration |
| Purpose | Stores competition series as the Competition Configuration module's governed relational record. |
| Aggregate | Competition Series |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0100: (workspace_id) -> workspaces(id) |
| Check Constraints | QMDB-CST-0186: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0149 (public_id); QMDB-IDX-0150 (workspace_id, status_code, created_at, id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Competition Series by relational/public identity; List Competition Series within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-012; QMDB-FR-CMP-001; QMDB-FR-CMP-002; QMDB-FR-CMP-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0526 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0527 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0528 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0529 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Competition Series. | Domain validation plus schema type/constraint. |
| QMDB-COL-0530 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Competition Series. | Domain validation plus schema type/constraint. |
| QMDB-COL-0531 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Competition Series. | Domain validation plus schema type/constraint. |
| QMDB-COL-0532 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Competition Series. | Domain validation plus schema type/constraint. |

### QMDB-TBL-077 — `competition_editions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-077 |
| Logical Table Name | competition_editions |
| Owning Module | Competition Configuration |
| Purpose | Stores competition editions as the Competition Configuration module's governed relational record. |
| Aggregate | Competition Edition |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0101: (workspace_id) -> workspaces(id); QMDB-REL-0102: (workspace_id, competition_sery_id) -> competition_series(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0151 (public_id); QMDB-IDX-0152 (workspace_id, status_code, created_at, id); QMDB-IDX-0153 (workspace_id, competition_sery_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Competition Editions by relational/public identity; List Competition Editions within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-012; QMDB-FR-CMP-001; QMDB-FR-CMP-002; QMDB-FR-CMP-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0533 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0534 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0535 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0536 | `competition_sery_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to competition_series. | Domain validation plus schema type/constraint. |
| QMDB-COL-0537 | `edition_code` | Human-readable code | `VARCHAR(64)` | No | None | No | No | No | Yes | Edition Code for Competition Editions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0538 | `name` | Name | `VARCHAR(191)` | No | None | No | No | No | No | Name for Competition Editions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0539 | `competition_time_zone` | Time zone | `VARCHAR(64)` | No | None | No | No | No | No | Competition Time Zone for Competition Editions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0540 | `starts_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Starts At for Competition Editions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0541 | `ends_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Ends At for Competition Editions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0542 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Competition Editions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0543 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Competition Editions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-078 — `competition_organization_relationships`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-078 |
| Logical Table Name | competition_organization_relationships |
| Owning Module | Competition Configuration |
| Purpose | Stores competition organization relationships as the Competition Configuration module's governed relational record. |
| Aggregate | Competition Edition |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0103: (workspace_id) -> workspaces(id); QMDB-REL-0104: (workspace_id, competition_edition_id) -> competition_editions(workspace_id, id); QMDB-REL-0105: (organization_id) -> organizations(id) |
| Check Constraints | QMDB-CST-0192: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0154 (workspace_id, created_at, id); QMDB-IDX-0155 (workspace_id, competition_edition_id); QMDB-IDX-0156 (organization_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Competition Organization Relationships by relational/public identity; List Competition Organization Relationships within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-012; QMDB-FR-CMP-001; QMDB-FR-CMP-002; QMDB-FR-CMP-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0544 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0545 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0546 | `competition_edition_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to competition_editions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0547 | `organization_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to organizations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0548 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | No | No | Yes | Sequence Number for Competition Organization Relationships. | Domain validation plus schema type/constraint. |
| QMDB-COL-0549 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Competition Organization Relationships. | Domain validation plus schema type/constraint. |

### QMDB-TBL-079 — `competition_administrative_areas`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-079 |
| Logical Table Name | competition_administrative_areas |
| Owning Module | Competition Configuration |
| Purpose | Stores competition administrative areas as the Competition Configuration module's governed relational record. |
| Aggregate | Competition Edition |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0106: (workspace_id) -> workspaces(id); QMDB-REL-0107: (workspace_id, competition_edition_id) -> competition_editions(workspace_id, id); QMDB-REL-0108: (administrative_area_id) -> administrative_areas(id) |
| Check Constraints | QMDB-CST-0195: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0157 (workspace_id, created_at, id); QMDB-IDX-0158 (workspace_id, competition_edition_id); QMDB-IDX-0159 (administrative_area_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Competition Administrative Areas by relational/public identity; List Competition Administrative Areas within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-012; QMDB-FR-CMP-001; QMDB-FR-CMP-002; QMDB-FR-CMP-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0550 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0551 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0552 | `competition_edition_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to competition_editions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0553 | `administrative_area_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to administrative_areas. | Domain validation plus schema type/constraint. |
| QMDB-COL-0554 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | No | No | Yes | Sequence Number for Competition Administrative Areas. | Domain validation plus schema type/constraint. |
| QMDB-COL-0555 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Competition Administrative Areas. | Domain validation plus schema type/constraint. |

### QMDB-TBL-080 — `competition_venues`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-080 |
| Logical Table Name | competition_venues |
| Owning Module | Competition Configuration |
| Purpose | Stores competition venues as the Competition Configuration module's governed relational record. |
| Aggregate | Competition Edition |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0109: (workspace_id) -> workspaces(id); QMDB-REL-0110: (workspace_id, competition_edition_id) -> competition_editions(workspace_id, id); QMDB-REL-0111: (venue_id) -> venues(id) |
| Check Constraints | QMDB-CST-0198: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0160 (workspace_id, created_at, id); QMDB-IDX-0161 (workspace_id, competition_edition_id); QMDB-IDX-0162 (venue_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Competition Venues by relational/public identity; List Competition Venues within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-012; QMDB-FR-CMP-001; QMDB-FR-CMP-002; QMDB-FR-CMP-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0556 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0557 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0558 | `competition_edition_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to competition_editions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0559 | `venue_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to venues. | Domain validation plus schema type/constraint. |
| QMDB-COL-0560 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | No | No | Yes | Sequence Number for Competition Venues. | Domain validation plus schema type/constraint. |
| QMDB-COL-0561 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Competition Venues. | Domain validation plus schema type/constraint. |

### QMDB-TBL-081 — `categories`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-081 |
| Logical Table Name | categories |
| Owning Module | Competition Configuration |
| Purpose | Stores categories as the Competition Configuration module's governed relational record. |
| Aggregate | Competition Configuration |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0112: (workspace_id) -> workspaces(id); QMDB-REL-0113: (workspace_id, competition_edition_id) -> competition_editions(workspace_id, id) |
| Check Constraints | QMDB-CST-0202: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0163 (public_id); QMDB-IDX-0164 (workspace_id, status_code, created_at, id); QMDB-IDX-0165 (workspace_id, competition_edition_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Categories by relational/public identity; List Categories within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-012; QMDB-FR-CMP-001; QMDB-FR-CMP-002; QMDB-FR-CMP-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0562 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0563 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0564 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0565 | `competition_edition_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to competition_editions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0566 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Categories. | Domain validation plus schema type/constraint. |
| QMDB-COL-0567 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Categories. | Domain validation plus schema type/constraint. |
| QMDB-COL-0568 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Categories. | Domain validation plus schema type/constraint. |
| QMDB-COL-0569 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Categories. | Domain validation plus schema type/constraint. |

### QMDB-TBL-082 — `divisions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-082 |
| Logical Table Name | divisions |
| Owning Module | Competition Configuration |
| Purpose | Stores divisions as the Competition Configuration module's governed relational record. |
| Aggregate | Competition Edition |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0114: (workspace_id) -> workspaces(id); QMDB-REL-0115: (workspace_id, category_id) -> categories(workspace_id, id) |
| Check Constraints | QMDB-CST-0206: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0166 (public_id); QMDB-IDX-0167 (workspace_id, status_code, created_at, id); QMDB-IDX-0168 (workspace_id, category_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Divisions by relational/public identity; List Divisions within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-012; QMDB-FR-CMP-001; QMDB-FR-CMP-002; QMDB-FR-CMP-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0570 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0571 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0572 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0573 | `category_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to categories. | Domain validation plus schema type/constraint. |
| QMDB-COL-0574 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Divisions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0575 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Divisions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0576 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Divisions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0577 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Divisions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-083 — `age_bands`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-083 |
| Logical Table Name | age_bands |
| Owning Module | Competition Configuration |
| Purpose | Stores age bands as the Competition Configuration module's governed relational record. |
| Aggregate | Competition Edition |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0116: (workspace_id) -> workspaces(id); QMDB-REL-0117: (workspace_id, competition_edition_id) -> competition_editions(workspace_id, id) |
| Check Constraints | QMDB-CST-0210: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0169 (public_id); QMDB-IDX-0170 (workspace_id, status_code, created_at, id); QMDB-IDX-0171 (workspace_id, competition_edition_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Age Bands by relational/public identity; List Age Bands within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-012; QMDB-FR-CMP-001; QMDB-FR-CMP-002; QMDB-FR-CMP-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0578 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0579 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0580 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0581 | `competition_edition_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to competition_editions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0582 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Age Bands. | Domain validation plus schema type/constraint. |
| QMDB-COL-0583 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Age Bands. | Domain validation plus schema type/constraint. |
| QMDB-COL-0584 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Age Bands. | Domain validation plus schema type/constraint. |
| QMDB-COL-0585 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Age Bands. | Domain validation plus schema type/constraint. |

### QMDB-TBL-084 — `stages`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-084 |
| Logical Table Name | stages |
| Owning Module | Competition Configuration |
| Purpose | Stores stages as the Competition Configuration module's governed relational record. |
| Aggregate | Competition Edition |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0118: (workspace_id) -> workspaces(id); QMDB-REL-0119: (workspace_id, competition_edition_id) -> competition_editions(workspace_id, id) |
| Check Constraints | QMDB-CST-0214: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0172 (public_id); QMDB-IDX-0173 (workspace_id, status_code, created_at, id); QMDB-IDX-0174 (workspace_id, competition_edition_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Stages by relational/public identity; List Stages within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-012; QMDB-FR-CMP-001; QMDB-FR-CMP-002; QMDB-FR-CMP-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0586 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0587 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0588 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0589 | `competition_edition_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to competition_editions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0590 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Stages. | Domain validation plus schema type/constraint. |
| QMDB-COL-0591 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Stages. | Domain validation plus schema type/constraint. |
| QMDB-COL-0592 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Stages. | Domain validation plus schema type/constraint. |
| QMDB-COL-0593 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Stages. | Domain validation plus schema type/constraint. |

### QMDB-TBL-085 — `rounds`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-085 |
| Logical Table Name | rounds |
| Owning Module | Competition Configuration |
| Purpose | Stores rounds as the Competition Configuration module's governed relational record. |
| Aggregate | Competition Edition |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0120: (workspace_id) -> workspaces(id); QMDB-REL-0121: (workspace_id, stage_id) -> stages(workspace_id, id) |
| Check Constraints | QMDB-CST-0218: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0175 (public_id); QMDB-IDX-0176 (workspace_id, status_code, created_at, id); QMDB-IDX-0177 (workspace_id, stage_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Rounds by relational/public identity; List Rounds within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-012; QMDB-FR-CMP-001; QMDB-FR-CMP-002; QMDB-FR-CMP-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0594 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0595 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0596 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0597 | `stage_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to stages. | Domain validation plus schema type/constraint. |
| QMDB-COL-0598 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Rounds. | Domain validation plus schema type/constraint. |
| QMDB-COL-0599 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Rounds. | Domain validation plus schema type/constraint. |
| QMDB-COL-0600 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Rounds. | Domain validation plus schema type/constraint. |
| QMDB-COL-0601 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Rounds. | Domain validation plus schema type/constraint. |

### QMDB-TBL-086 — `competition_sessions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-086 |
| Logical Table Name | competition_sessions |
| Owning Module | Competition Configuration |
| Purpose | Stores competition sessions as the Competition Configuration module's governed relational record. |
| Aggregate | Competition Edition |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0122: (workspace_id) -> workspaces(id); QMDB-REL-0123: (workspace_id, round_id) -> rounds(workspace_id, id); QMDB-REL-0124: (workspace_id, competition_venue_id) -> competition_venues(workspace_id, id) |
| Check Constraints | QMDB-CST-0222: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0178 (public_id); QMDB-IDX-0179 (workspace_id, status_code, created_at, id); QMDB-IDX-0180 (workspace_id, round_id); QMDB-IDX-0181 (workspace_id, competition_venue_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Competition Sessions by relational/public identity; List Competition Sessions within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-012; QMDB-FR-CMP-001; QMDB-FR-CMP-002; QMDB-FR-CMP-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0602 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0603 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0604 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0605 | `round_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to rounds. | Domain validation plus schema type/constraint. |
| QMDB-COL-0606 | `competition_venue_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to competition_venues. | Domain validation plus schema type/constraint. |
| QMDB-COL-0607 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Competition Sessions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0608 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Competition Sessions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0609 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Competition Sessions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0610 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Competition Sessions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-087 — `rulesets`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-087 |
| Logical Table Name | rulesets |
| Owning Module | Competition Configuration |
| Purpose | Stores rulesets as the Competition Configuration module's governed relational record. |
| Aggregate | Ruleset Version |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Versioned Configuration |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0125: (workspace_id) -> workspaces(id); QMDB-REL-0126: (workspace_id, competition_sery_id) -> competition_series(workspace_id, id) |
| Check Constraints | QMDB-CST-0226: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0182 (public_id); QMDB-IDX-0183 (workspace_id, status_code, created_at, id); QMDB-IDX-0184 (workspace_id, competition_sery_id) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Rulesets by relational/public identity; List Rulesets within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-012; QMDB-FR-CMP-001; QMDB-FR-CMP-002; QMDB-FR-CMP-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0611 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0612 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0613 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0614 | `competition_sery_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to competition_series. | Domain validation plus schema type/constraint. |
| QMDB-COL-0615 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Rulesets. | Domain validation plus schema type/constraint. |
| QMDB-COL-0616 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Rulesets. | Domain validation plus schema type/constraint. |
| QMDB-COL-0617 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Rulesets. | Domain validation plus schema type/constraint. |
| QMDB-COL-0618 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Rulesets. | Domain validation plus schema type/constraint. |

### QMDB-TBL-088 — `ruleset_versions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-088 |
| Logical Table Name | ruleset_versions |
| Owning Module | Competition Configuration |
| Purpose | Stores ruleset versions as the Competition Configuration module's governed relational record. |
| Aggregate | Ruleset Version |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Immutable Official Version |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0127: (workspace_id) -> workspaces(id); QMDB-REL-0128: (workspace_id, ruleset_id) -> rulesets(workspace_id, id); QMDB-REL-0129: (quran_text_release_id) -> quran_text_releases(id) |
| Check Constraints | QMDB-CST-0229: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0185 (workspace_id, status_code, created_at, id); QMDB-IDX-0186 (workspace_id, ruleset_id); QMDB-IDX-0187 (quran_text_release_id) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Ruleset Versions by relational/public identity; List Ruleset Versions within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-012; QMDB-FR-CMP-001; QMDB-FR-CMP-002; QMDB-FR-CMP-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0619 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0620 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0621 | `ruleset_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to rulesets. | Domain validation plus schema type/constraint. |
| QMDB-COL-0622 | `quran_text_release_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to quran_text_releases. | Domain validation plus schema type/constraint. |
| QMDB-COL-0623 | `version_number` | Version number | `INT UNSIGNED` | No | None | No | No | No | Yes | Version Number for Ruleset Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0624 | `definition_json` | JSON document | `JSON` | No | None | No | No | No | No | Definition Json for Ruleset Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0625 | `schema_version` | Version number | `INT UNSIGNED` | No | None | No | No | No | No | Schema Version for Ruleset Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0626 | `definition_checksum` | Content hash | `BINARY(32)` | No | None | No | No | No | No | Definition Checksum for Ruleset Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0627 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Ruleset Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0628 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Ruleset Versions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-089 — `scoring_criteria`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-089 |
| Logical Table Name | scoring_criteria |
| Owning Module | Competition Configuration |
| Purpose | Stores scoring criteria as the Competition Configuration module's governed relational record. |
| Aggregate | Ruleset Version |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Versioned Configuration |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0130: (workspace_id) -> workspaces(id); QMDB-REL-0131: (workspace_id, ruleset_version_id) -> ruleset_versions(workspace_id, id) |
| Check Constraints | QMDB-CST-0232: Effective-until shall be null or later than effective-from. |
| Indexes | QMDB-IDX-0188 (workspace_id, created_at, id); QMDB-IDX-0189 (workspace_id, ruleset_version_id); QMDB-IDX-0190 (code) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Scoring Criteria by relational/public identity; List Scoring Criteria within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-012; QMDB-FR-CMP-001; QMDB-FR-CMP-002; QMDB-FR-CMP-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0629 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0630 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0631 | `ruleset_version_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to ruleset_versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0632 | `code` | Human-readable code | `VARCHAR(64)` | No | None | Yes | No | No | Yes | Code for Scoring Criteria. | Domain validation plus schema type/constraint. |
| QMDB-COL-0633 | `name` | Name | `VARCHAR(191)` | No | None | No | No | No | Yes | Name for Scoring Criteria. | Domain validation plus schema type/constraint. |
| QMDB-COL-0634 | `effective_from` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Effective From for Scoring Criteria. | Domain validation plus schema type/constraint. |
| QMDB-COL-0635 | `effective_until` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Effective Until for Scoring Criteria. | Domain validation plus schema type/constraint. |
| QMDB-COL-0636 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Scoring Criteria. | Domain validation plus schema type/constraint. |

### QMDB-TBL-090 — `deduction_rules`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-090 |
| Logical Table Name | deduction_rules |
| Owning Module | Competition Configuration |
| Purpose | Stores deduction rules as the Competition Configuration module's governed relational record. |
| Aggregate | Ruleset Version |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Versioned Configuration |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0132: (workspace_id) -> workspaces(id); QMDB-REL-0133: (workspace_id, ruleset_version_id) -> ruleset_versions(workspace_id, id); QMDB-REL-0134: (workspace_id, scoring_criteria_id) -> scoring_criteria(workspace_id, id) |
| Check Constraints | QMDB-CST-0235: Effective-until shall be null or later than effective-from. |
| Indexes | QMDB-IDX-0191 (workspace_id, created_at, id); QMDB-IDX-0192 (workspace_id, ruleset_version_id); QMDB-IDX-0193 (workspace_id, scoring_criteria_id); QMDB-IDX-0194 (code) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Deduction Rules by relational/public identity; List Deduction Rules within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-012; QMDB-FR-CMP-001; QMDB-FR-CMP-002; QMDB-FR-CMP-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0637 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0638 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0639 | `ruleset_version_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to ruleset_versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0640 | `scoring_criteria_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to scoring_criteria. | Domain validation plus schema type/constraint. |
| QMDB-COL-0641 | `code` | Human-readable code | `VARCHAR(64)` | No | None | Yes | No | No | Yes | Code for Deduction Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0642 | `name` | Name | `VARCHAR(191)` | No | None | No | No | No | Yes | Name for Deduction Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0643 | `effective_from` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Effective From for Deduction Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0644 | `effective_until` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Effective Until for Deduction Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0645 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Deduction Rules. | Domain validation plus schema type/constraint. |

### QMDB-TBL-091 — `aggregation_rules`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-091 |
| Logical Table Name | aggregation_rules |
| Owning Module | Competition Configuration |
| Purpose | Stores aggregation rules as the Competition Configuration module's governed relational record. |
| Aggregate | Ruleset Version |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Versioned Configuration |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0135: (workspace_id) -> workspaces(id); QMDB-REL-0136: (workspace_id, ruleset_version_id) -> ruleset_versions(workspace_id, id) |
| Check Constraints | QMDB-CST-0238: Effective-until shall be null or later than effective-from. |
| Indexes | QMDB-IDX-0195 (workspace_id, created_at, id); QMDB-IDX-0196 (workspace_id, ruleset_version_id); QMDB-IDX-0197 (code) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Aggregation Rules by relational/public identity; List Aggregation Rules within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-012; QMDB-FR-CMP-001; QMDB-FR-CMP-002; QMDB-FR-CMP-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0646 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0647 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0648 | `ruleset_version_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to ruleset_versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0649 | `code` | Human-readable code | `VARCHAR(64)` | No | None | Yes | No | No | Yes | Code for Aggregation Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0650 | `name` | Name | `VARCHAR(191)` | No | None | No | No | No | Yes | Name for Aggregation Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0651 | `effective_from` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Effective From for Aggregation Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0652 | `effective_until` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Effective Until for Aggregation Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0653 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Aggregation Rules. | Domain validation plus schema type/constraint. |

### QMDB-TBL-092 — `tie_break_rules`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-092 |
| Logical Table Name | tie_break_rules |
| Owning Module | Competition Configuration |
| Purpose | Stores tie break rules as the Competition Configuration module's governed relational record. |
| Aggregate | Ruleset Version |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Versioned Configuration |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0137: (workspace_id) -> workspaces(id); QMDB-REL-0138: (workspace_id, ruleset_version_id) -> ruleset_versions(workspace_id, id) |
| Check Constraints | QMDB-CST-0241: Effective-until shall be null or later than effective-from. |
| Indexes | QMDB-IDX-0198 (workspace_id, created_at, id); QMDB-IDX-0199 (workspace_id, ruleset_version_id); QMDB-IDX-0200 (code) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Tie Break Rules by relational/public identity; List Tie Break Rules within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-012; QMDB-FR-CMP-001; QMDB-FR-CMP-002; QMDB-FR-CMP-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0654 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0655 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0656 | `ruleset_version_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to ruleset_versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0657 | `code` | Human-readable code | `VARCHAR(64)` | No | None | Yes | No | No | Yes | Code for Tie Break Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0658 | `name` | Name | `VARCHAR(191)` | No | None | No | No | No | Yes | Name for Tie Break Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0659 | `effective_from` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Effective From for Tie Break Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0660 | `effective_until` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Effective Until for Tie Break Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0661 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Tie Break Rules. | Domain validation plus schema type/constraint. |

### QMDB-TBL-093 — `disqualification_rules`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-093 |
| Logical Table Name | disqualification_rules |
| Owning Module | Competition Configuration |
| Purpose | Stores disqualification rules as the Competition Configuration module's governed relational record. |
| Aggregate | Ruleset Version |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Versioned Configuration |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0139: (workspace_id) -> workspaces(id); QMDB-REL-0140: (workspace_id, ruleset_version_id) -> ruleset_versions(workspace_id, id) |
| Check Constraints | QMDB-CST-0244: Effective-until shall be null or later than effective-from. |
| Indexes | QMDB-IDX-0201 (workspace_id, created_at, id); QMDB-IDX-0202 (workspace_id, ruleset_version_id); QMDB-IDX-0203 (code) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Disqualification Rules by relational/public identity; List Disqualification Rules within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-012; QMDB-FR-CMP-001; QMDB-FR-CMP-002; QMDB-FR-CMP-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0662 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0663 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0664 | `ruleset_version_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to ruleset_versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0665 | `code` | Human-readable code | `VARCHAR(64)` | No | None | Yes | No | No | Yes | Code for Disqualification Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0666 | `name` | Name | `VARCHAR(191)` | No | None | No | No | No | Yes | Name for Disqualification Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0667 | `effective_from` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Effective From for Disqualification Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0668 | `effective_until` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Effective Until for Disqualification Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0669 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Disqualification Rules. | Domain validation plus schema type/constraint. |

### QMDB-TBL-094 — `publication_rules`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-094 |
| Logical Table Name | publication_rules |
| Owning Module | Competition Configuration |
| Purpose | Stores publication rules as the Competition Configuration module's governed relational record. |
| Aggregate | Ruleset Version |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Versioned Configuration |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0141: (workspace_id) -> workspaces(id); QMDB-REL-0142: (workspace_id, ruleset_version_id) -> ruleset_versions(workspace_id, id) |
| Check Constraints | QMDB-CST-0247: Effective-until shall be null or later than effective-from. |
| Indexes | QMDB-IDX-0204 (workspace_id, created_at, id); QMDB-IDX-0205 (workspace_id, ruleset_version_id); QMDB-IDX-0206 (code) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Publication Rules by relational/public identity; List Publication Rules within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-012; QMDB-FR-CMP-001; QMDB-FR-CMP-002; QMDB-FR-CMP-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0670 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0671 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0672 | `ruleset_version_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to ruleset_versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0673 | `code` | Human-readable code | `VARCHAR(64)` | No | None | Yes | No | No | Yes | Code for Publication Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0674 | `name` | Name | `VARCHAR(191)` | No | None | No | No | No | Yes | Name for Publication Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0675 | `effective_from` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Effective From for Publication Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0676 | `effective_until` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Effective Until for Publication Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0677 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Publication Rules. | Domain validation plus schema type/constraint. |

### QMDB-TBL-095 — `appeal_rules`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-095 |
| Logical Table Name | appeal_rules |
| Owning Module | Competition Configuration |
| Purpose | Stores appeal rules as the Competition Configuration module's governed relational record. |
| Aggregate | Ruleset Version |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Versioned Configuration |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0143: (workspace_id) -> workspaces(id); QMDB-REL-0144: (workspace_id, ruleset_version_id) -> ruleset_versions(workspace_id, id) |
| Check Constraints | QMDB-CST-0250: Effective-until shall be null or later than effective-from. |
| Indexes | QMDB-IDX-0207 (workspace_id, created_at, id); QMDB-IDX-0208 (workspace_id, ruleset_version_id); QMDB-IDX-0209 (code) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Appeal Rules by relational/public identity; List Appeal Rules within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-012; QMDB-FR-CMP-001; QMDB-FR-CMP-002; QMDB-FR-CMP-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0678 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0679 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0680 | `ruleset_version_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to ruleset_versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0681 | `code` | Human-readable code | `VARCHAR(64)` | No | None | Yes | Yes | No | Yes | Code for Appeal Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0682 | `name` | Name | `VARCHAR(191)` | No | None | No | Yes | No | Yes | Name for Appeal Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0683 | `effective_from` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Effective From for Appeal Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0684 | `effective_until` | UTC timestamp | `DATETIME(6)` | Yes | None | No | Yes | No | No | Effective Until for Appeal Rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0685 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Appeal Rules. | Domain validation plus schema type/constraint. |

### QMDB-TBL-096 — `registrations`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-096 |
| Logical Table Name | registrations |
| Owning Module | Registration and Eligibility |
| Purpose | Stores registrations as the Registration and Eligibility module's governed relational record. |
| Aggregate | Registration |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | MODERATE |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0145: (workspace_id) -> workspaces(id); QMDB-REL-0146: (workspace_id, competition_edition_id) -> competition_editions(workspace_id, id); QMDB-REL-0147: (person_id) -> persons(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0210 (public_id); QMDB-IDX-0211 (workspace_id, status_code, submitted_at, id); QMDB-IDX-0212 (workspace_id, competition_edition_id); QMDB-IDX-0213 (person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Registrations by relational/public identity; List Registrations within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-013; QMDB-FR-REG-001; QMDB-FR-REG-002; QMDB-FR-REG-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0686 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0687 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0688 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0689 | `competition_edition_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to competition_editions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0690 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-0691 | `registration_code` | Human-readable code | `VARCHAR(64)` | No | None | No | Yes | No | Yes | Registration Code for Registrations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0692 | `submission_idempotency_hash` | Token hash | `BINARY(32)` | No | None | No | Yes | No | Yes | Submission Idempotency Hash for Registrations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0693 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Registrations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0694 | `submitted_at` | UTC timestamp | `DATETIME(6)` | Yes | None | No | Yes | No | Yes | Submitted At for Registrations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0695 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Registrations. | Domain validation plus schema type/constraint. |

### QMDB-TBL-097 — `registration_categories`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-097 |
| Logical Table Name | registration_categories |
| Owning Module | Registration and Eligibility |
| Purpose | Stores registration categories as the Registration and Eligibility module's governed relational record. |
| Aggregate | Registration |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0148: (workspace_id) -> workspaces(id); QMDB-REL-0149: (workspace_id, registration_id) -> registrations(workspace_id, id); QMDB-REL-0150: (workspace_id, category_id) -> categories(workspace_id, id) |
| Check Constraints | QMDB-CST-0256: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0214 (workspace_id, created_at, id); QMDB-IDX-0215 (workspace_id, registration_id); QMDB-IDX-0216 (workspace_id, category_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Registration Categories by relational/public identity; List Registration Categories within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-013; QMDB-FR-REG-001; QMDB-FR-REG-002; QMDB-FR-REG-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0696 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0697 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0698 | `registration_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to registrations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0699 | `category_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to categories. | Domain validation plus schema type/constraint. |
| QMDB-COL-0700 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | Yes | No | Yes | Sequence Number for Registration Categories. | Domain validation plus schema type/constraint. |
| QMDB-COL-0701 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Registration Categories. | Domain validation plus schema type/constraint. |

### QMDB-TBL-098 — `nominations`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-098 |
| Logical Table Name | nominations |
| Owning Module | Registration and Eligibility |
| Purpose | Stores nominations as the Registration and Eligibility module's governed relational record. |
| Aggregate | Registration |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0151: (workspace_id) -> workspaces(id); QMDB-REL-0152: (workspace_id, registration_id) -> registrations(workspace_id, id) |
| Check Constraints | QMDB-CST-0260: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0217 (public_id); QMDB-IDX-0218 (workspace_id, status_code, created_at, id); QMDB-IDX-0219 (workspace_id, registration_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Nominations by relational/public identity; List Nominations within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-013; QMDB-FR-REG-001; QMDB-FR-REG-002; QMDB-FR-REG-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0702 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0703 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0704 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0705 | `registration_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to registrations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0706 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Nominations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0707 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Nominations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0708 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Nominations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0709 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Nominations. | Domain validation plus schema type/constraint. |

### QMDB-TBL-099 — `nomination_authorities`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-099 |
| Logical Table Name | nomination_authorities |
| Owning Module | Registration and Eligibility |
| Purpose | Stores nomination authorities as the Registration and Eligibility module's governed relational record. |
| Aggregate | Registration |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0153: (workspace_id) -> workspaces(id); QMDB-REL-0154: (workspace_id, nomination_id) -> nominations(workspace_id, id); QMDB-REL-0155: (organization_id) -> organizations(id) |
| Check Constraints | QMDB-CST-0263: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0220 (workspace_id, created_at, id); QMDB-IDX-0221 (workspace_id, nomination_id); QMDB-IDX-0222 (organization_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Nomination Authorities by relational/public identity; List Nomination Authorities within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-013; QMDB-FR-REG-001; QMDB-FR-REG-002; QMDB-FR-REG-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0710 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0711 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0712 | `nomination_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to nominations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0713 | `organization_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to organizations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0714 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | No | No | Yes | Sequence Number for Nomination Authorities. | Domain validation plus schema type/constraint. |
| QMDB-COL-0715 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Nomination Authorities. | Domain validation plus schema type/constraint. |

### QMDB-TBL-100 — `eligibility_checks`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-100 |
| Logical Table Name | eligibility_checks |
| Owning Module | Registration and Eligibility |
| Purpose | Stores eligibility checks as the Registration and Eligibility module's governed relational record. |
| Aggregate | Registration |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0156: (workspace_id) -> workspaces(id); QMDB-REL-0157: (workspace_id, registration_id) -> registrations(workspace_id, id) |
| Check Constraints | QMDB-CST-0267: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0223 (public_id); QMDB-IDX-0224 (workspace_id, status_code, created_at, id); QMDB-IDX-0225 (workspace_id, registration_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Eligibility Checks by relational/public identity; List Eligibility Checks within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-013; QMDB-FR-REG-001; QMDB-FR-REG-002; QMDB-FR-REG-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0716 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0717 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0718 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0719 | `registration_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to registrations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0720 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Eligibility Checks. | Domain validation plus schema type/constraint. |
| QMDB-COL-0721 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Eligibility Checks. | Domain validation plus schema type/constraint. |
| QMDB-COL-0722 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Eligibility Checks. | Domain validation plus schema type/constraint. |
| QMDB-COL-0723 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Eligibility Checks. | Domain validation plus schema type/constraint. |

### QMDB-TBL-101 — `eligibility_evidence`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-101 |
| Logical Table Name | eligibility_evidence |
| Owning Module | Registration and Eligibility |
| Purpose | Stores eligibility evidence as the Registration and Eligibility module's governed relational record. |
| Aggregate | Registration |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | External Evidence Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0158: (workspace_id) -> workspaces(id); QMDB-REL-0159: (workspace_id, eligibility_check_id) -> eligibility_checks(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0226 (public_id); QMDB-IDX-0227 (workspace_id, created_at, id); QMDB-IDX-0228 (workspace_id, eligibility_check_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Eligibility Evidence by relational/public identity; List Eligibility Evidence within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-013; QMDB-FR-REG-001; QMDB-FR-REG-002; QMDB-FR-REG-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0724 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0725 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0726 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0727 | `eligibility_check_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to eligibility_checks. | Domain validation plus schema type/constraint. |
| QMDB-COL-0728 | `evidence_type` | Human-readable code | `VARCHAR(64)` | No | None | Yes | No | No | Yes | Evidence Type for Eligibility Evidence. | Domain validation plus schema type/constraint. |
| QMDB-COL-0729 | `object_key` | Object-storage key | `VARCHAR(1024)` | Yes | None | No | No | Yes | No | Private object reference; binary content is never stored in MySQL. | Domain validation plus schema type/constraint. |
| QMDB-COL-0730 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | No | No | Yes | Content Hash for Eligibility Evidence. | Domain validation plus schema type/constraint. |
| QMDB-COL-0731 | `collected_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | No | Collected At for Eligibility Evidence. | Domain validation plus schema type/constraint. |
| QMDB-COL-0732 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Eligibility Evidence. | Domain validation plus schema type/constraint. |

### QMDB-TBL-102 — `registration_decisions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-102 |
| Logical Table Name | registration_decisions |
| Owning Module | Registration and Eligibility |
| Purpose | Stores registration decisions as the Registration and Eligibility module's governed relational record. |
| Aggregate | Registration |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0160: (workspace_id) -> workspaces(id); QMDB-REL-0161: (workspace_id, registration_id) -> registrations(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0229 (public_id); QMDB-IDX-0230 (workspace_id, decision_code, created_at, id); QMDB-IDX-0231 (workspace_id, registration_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Registration Decisions by relational/public identity; List Registration Decisions within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-013; QMDB-FR-REG-001; QMDB-FR-REG-002; QMDB-FR-REG-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0733 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0734 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0735 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0736 | `registration_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to registrations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0737 | `decision_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Decision Code for Registration Decisions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0738 | `reason_code` | Human-readable code | `VARCHAR(64)` | No | None | No | Yes | No | No | Reason Code for Registration Decisions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0739 | `decided_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Decided At for Registration Decisions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0740 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Registration Decisions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-103 — `participant_snapshots`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-103 |
| Logical Table Name | participant_snapshots |
| Owning Module | Registration and Eligibility |
| Purpose | Stores participant snapshots as the Registration and Eligibility module's governed relational record. |
| Aggregate | Participant Snapshot |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Immutable Official Version |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0162: (workspace_id) -> workspaces(id); QMDB-REL-0163: (workspace_id, registration_id) -> registrations(workspace_id, id); QMDB-REL-0164: (person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0276: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0232 (workspace_id, created_at, id); QMDB-IDX-0233 (workspace_id, registration_id); QMDB-IDX-0234 (person_id) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Participant Snapshots by relational/public identity; List Participant Snapshots within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-013; QMDB-FR-REG-001; QMDB-FR-REG-002; QMDB-FR-REG-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0741 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0742 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0743 | `registration_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to registrations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0744 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-0745 | `snapshot_version` | Version number | `INT UNSIGNED` | No | None | No | Yes | No | No | Snapshot Version for Participant Snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-0746 | `display_name` | Name | `VARCHAR(191)` | No | None | No | Yes | No | No | Display Name for Participant Snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-0747 | `minor_status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Minor Status Code for Participant Snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-0748 | `snapshot_checksum` | Content hash | `BINARY(32)` | No | None | No | Yes | No | No | Snapshot Checksum for Participant Snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-0749 | `captured_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Captured At for Participant Snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-0750 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Participant Snapshots. | Domain validation plus schema type/constraint. |

### QMDB-TBL-104 — `participant_snapshot_affiliations`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-104 |
| Logical Table Name | participant_snapshot_affiliations |
| Owning Module | Registration and Eligibility |
| Purpose | Stores participant snapshot affiliations as the Registration and Eligibility module's governed relational record. |
| Aggregate | Participant Snapshot |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0165: (workspace_id) -> workspaces(id); QMDB-REL-0166: (workspace_id, participant_snapshot_id) -> participant_snapshots(workspace_id, id); QMDB-REL-0167: (organization_id) -> organizations(id) |
| Check Constraints | QMDB-CST-0279: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0235 (workspace_id, created_at, id); QMDB-IDX-0236 (workspace_id, participant_snapshot_id); QMDB-IDX-0237 (organization_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Participant Snapshot Affiliations by relational/public identity; List Participant Snapshot Affiliations within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-013; QMDB-FR-REG-001; QMDB-FR-REG-002; QMDB-FR-REG-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0751 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0752 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0753 | `participant_snapshot_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to participant_snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-0754 | `organization_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to organizations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0755 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | Yes | No | Yes | Sequence Number for Participant Snapshot Affiliations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0756 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Participant Snapshot Affiliations. | Domain validation plus schema type/constraint. |

### QMDB-TBL-105 — `participant_snapshot_geography`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-105 |
| Logical Table Name | participant_snapshot_geography |
| Owning Module | Registration and Eligibility |
| Purpose | Stores participant snapshot geography as the Registration and Eligibility module's governed relational record. |
| Aggregate | Participant Snapshot |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0168: (workspace_id) -> workspaces(id); QMDB-REL-0169: (workspace_id, participant_snapshot_id) -> participant_snapshots(workspace_id, id); QMDB-REL-0170: (administrative_area_id) -> administrative_areas(id) |
| Check Constraints | QMDB-CST-0282: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0238 (workspace_id, created_at, id); QMDB-IDX-0239 (workspace_id, participant_snapshot_id); QMDB-IDX-0240 (administrative_area_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Participant Snapshot Geography by relational/public identity; List Participant Snapshot Geography within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-013; QMDB-FR-REG-001; QMDB-FR-REG-002; QMDB-FR-REG-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0757 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0758 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0759 | `participant_snapshot_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to participant_snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-0760 | `administrative_area_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to administrative_areas. | Domain validation plus schema type/constraint. |
| QMDB-COL-0761 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | Yes | No | Yes | Sequence Number for Participant Snapshot Geography. | Domain validation plus schema type/constraint. |
| QMDB-COL-0762 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Participant Snapshot Geography. | Domain validation plus schema type/constraint. |

### QMDB-TBL-106 — `check_ins`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-106 |
| Logical Table Name | check_ins |
| Owning Module | Registration and Eligibility |
| Purpose | Stores check ins as the Registration and Eligibility module's governed relational record. |
| Aggregate | Registration |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0171: (workspace_id) -> workspaces(id); QMDB-REL-0172: (workspace_id, participant_snapshot_id) -> participant_snapshots(workspace_id, id); QMDB-REL-0173: (workspace_id, competition_session_id) -> competition_sessions(workspace_id, id) |
| Check Constraints | QMDB-CST-0286: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0241 (public_id); QMDB-IDX-0242 (workspace_id, status_code, created_at, id); QMDB-IDX-0243 (workspace_id, participant_snapshot_id); QMDB-IDX-0244 (workspace_id, competition_session_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Check Ins by relational/public identity; List Check Ins within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-013; QMDB-FR-REG-001; QMDB-FR-REG-002; QMDB-FR-REG-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0763 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0764 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0765 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0766 | `participant_snapshot_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to participant_snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-0767 | `competition_session_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to competition_sessions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0768 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Check Ins. | Domain validation plus schema type/constraint. |
| QMDB-COL-0769 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Check Ins. | Domain validation plus schema type/constraint. |
| QMDB-COL-0770 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Check Ins. | Domain validation plus schema type/constraint. |
| QMDB-COL-0771 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Check Ins. | Domain validation plus schema type/constraint. |

### QMDB-TBL-107 — `waitlist_entries`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-107 |
| Logical Table Name | waitlist_entries |
| Owning Module | Registration and Eligibility |
| Purpose | Stores waitlist entries as the Registration and Eligibility module's governed relational record. |
| Aggregate | Registration |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0174: (workspace_id) -> workspaces(id); QMDB-REL-0175: (workspace_id, registration_id) -> registrations(workspace_id, id) |
| Check Constraints | QMDB-CST-0290: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0245 (public_id); QMDB-IDX-0246 (workspace_id, status_code, created_at, id); QMDB-IDX-0247 (workspace_id, registration_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Waitlist Entries by relational/public identity; List Waitlist Entries within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-013; QMDB-FR-REG-001; QMDB-FR-REG-002; QMDB-FR-REG-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0772 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0773 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0774 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0775 | `registration_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to registrations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0776 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Waitlist Entries. | Domain validation plus schema type/constraint. |
| QMDB-COL-0777 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Waitlist Entries. | Domain validation plus schema type/constraint. |
| QMDB-COL-0778 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Waitlist Entries. | Domain validation plus schema type/constraint. |
| QMDB-COL-0779 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Waitlist Entries. | Domain validation plus schema type/constraint. |

### QMDB-TBL-108 — `registration_exceptions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-108 |
| Logical Table Name | registration_exceptions |
| Owning Module | Registration and Eligibility |
| Purpose | Stores registration exceptions as the Registration and Eligibility module's governed relational record. |
| Aggregate | Registration |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0176: (workspace_id) -> workspaces(id); QMDB-REL-0177: (workspace_id, registration_id) -> registrations(workspace_id, id) |
| Check Constraints | QMDB-CST-0294: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0248 (public_id); QMDB-IDX-0249 (workspace_id, status_code, created_at, id); QMDB-IDX-0250 (workspace_id, registration_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Registration Exceptions by relational/public identity; List Registration Exceptions within one Workspace using keyset pagination |
| Implementation Phase | P5 |
| Related Requirements | QMDB-DR-013; QMDB-FR-REG-001; QMDB-FR-REG-002; QMDB-FR-REG-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0780 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0781 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0782 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0783 | `registration_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to registrations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0784 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Registration Exceptions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0785 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Registration Exceptions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0786 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Registration Exceptions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0787 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Registration Exceptions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-109 — `competition_schedules`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-109 |
| Logical Table Name | competition_schedules |
| Owning Module | Scheduling and Judging |
| Purpose | Stores competition schedules as the Scheduling and Judging module's governed relational record. |
| Aggregate | Competition Edition |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0178: (workspace_id) -> workspaces(id); QMDB-REL-0179: (workspace_id, competition_edition_id) -> competition_editions(workspace_id, id) |
| Check Constraints | QMDB-CST-0298: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0251 (public_id); QMDB-IDX-0252 (workspace_id, status_code, created_at, id); QMDB-IDX-0253 (workspace_id, competition_edition_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Competition Schedules by relational/public identity; List Competition Schedules within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-014; QMDB-FR-SCH-001; QMDB-FR-SCH-002; QMDB-FR-SCH-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0788 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0789 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0790 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0791 | `competition_edition_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to competition_editions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0792 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Competition Schedules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0793 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Competition Schedules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0794 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Competition Schedules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0795 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Competition Schedules. | Domain validation plus schema type/constraint. |

### QMDB-TBL-110 — `session_schedules`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-110 |
| Logical Table Name | session_schedules |
| Owning Module | Scheduling and Judging |
| Purpose | Stores session schedules as the Scheduling and Judging module's governed relational record. |
| Aggregate | Competition Edition |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0180: (workspace_id) -> workspaces(id); QMDB-REL-0181: (workspace_id, competition_schedule_id) -> competition_schedules(workspace_id, id); QMDB-REL-0182: (workspace_id, competition_session_id) -> competition_sessions(workspace_id, id) |
| Check Constraints | QMDB-CST-0302: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0254 (public_id); QMDB-IDX-0255 (workspace_id, status_code, created_at, id); QMDB-IDX-0256 (workspace_id, competition_schedule_id); QMDB-IDX-0257 (workspace_id, competition_session_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Session Schedules by relational/public identity; List Session Schedules within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-014; QMDB-FR-SCH-001; QMDB-FR-SCH-002; QMDB-FR-SCH-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0796 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0797 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0798 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0799 | `competition_schedule_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to competition_schedules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0800 | `competition_session_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to competition_sessions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0801 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Session Schedules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0802 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Session Schedules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0803 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Session Schedules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0804 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Session Schedules. | Domain validation plus schema type/constraint. |

### QMDB-TBL-111 — `draw_orders`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-111 |
| Logical Table Name | draw_orders |
| Owning Module | Scheduling and Judging |
| Purpose | Stores draw orders as the Scheduling and Judging module's governed relational record. |
| Aggregate | Competition Edition |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0183: (workspace_id) -> workspaces(id); QMDB-REL-0184: (workspace_id, competition_session_id) -> competition_sessions(workspace_id, id); QMDB-REL-0185: (workspace_id, participant_snapshot_id) -> participant_snapshots(workspace_id, id) |
| Check Constraints | QMDB-CST-0306: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0258 (public_id); QMDB-IDX-0259 (workspace_id, status_code, created_at, id); QMDB-IDX-0260 (workspace_id, competition_session_id); QMDB-IDX-0261 (workspace_id, participant_snapshot_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Draw Orders by relational/public identity; List Draw Orders within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-014; QMDB-FR-SCH-001; QMDB-FR-SCH-002; QMDB-FR-SCH-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0805 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0806 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0807 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0808 | `competition_session_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to competition_sessions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0809 | `participant_snapshot_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to participant_snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-0810 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Draw Orders. | Domain validation plus schema type/constraint. |
| QMDB-COL-0811 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Draw Orders. | Domain validation plus schema type/constraint. |
| QMDB-COL-0812 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Draw Orders. | Domain validation plus schema type/constraint. |
| QMDB-COL-0813 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Draw Orders. | Domain validation plus schema type/constraint. |

### QMDB-TBL-112 — `judge_panels`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-112 |
| Logical Table Name | judge_panels |
| Owning Module | Scheduling and Judging |
| Purpose | Stores judge panels as the Scheduling and Judging module's governed relational record. |
| Aggregate | Judge Panel |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0186: (workspace_id) -> workspaces(id); QMDB-REL-0187: (workspace_id, competition_edition_id) -> competition_editions(workspace_id, id) |
| Check Constraints | QMDB-CST-0310: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0262 (public_id); QMDB-IDX-0263 (workspace_id, status_code, created_at, id); QMDB-IDX-0264 (workspace_id, competition_edition_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Judge Panels by relational/public identity; List Judge Panels within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-014; QMDB-FR-SCH-001; QMDB-FR-SCH-002; QMDB-FR-SCH-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0814 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0815 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0816 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0817 | `competition_edition_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to competition_editions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0818 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Judge Panels. | Domain validation plus schema type/constraint. |
| QMDB-COL-0819 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Judge Panels. | Domain validation plus schema type/constraint. |
| QMDB-COL-0820 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Judge Panels. | Domain validation plus schema type/constraint. |
| QMDB-COL-0821 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Judge Panels. | Domain validation plus schema type/constraint. |

### QMDB-TBL-113 — `judge_panel_members`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-113 |
| Logical Table Name | judge_panel_members |
| Owning Module | Scheduling and Judging |
| Purpose | Stores judge panel members as the Scheduling and Judging module's governed relational record. |
| Aggregate | Judge Panel |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0188: (workspace_id) -> workspaces(id); QMDB-REL-0189: (workspace_id, judge_panel_id) -> judge_panels(workspace_id, id); QMDB-REL-0190: (person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0313: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0265 (workspace_id, created_at, id); QMDB-IDX-0266 (workspace_id, judge_panel_id); QMDB-IDX-0267 (person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Judge Panel Members by relational/public identity; List Judge Panel Members within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-014; QMDB-FR-SCH-001; QMDB-FR-SCH-002; QMDB-FR-SCH-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0822 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0823 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0824 | `judge_panel_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to judge_panels. | Domain validation plus schema type/constraint. |
| QMDB-COL-0825 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-0826 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | No | No | Yes | Sequence Number for Judge Panel Members. | Domain validation plus schema type/constraint. |
| QMDB-COL-0827 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Judge Panel Members. | Domain validation plus schema type/constraint. |

### QMDB-TBL-114 — `judge_assignments`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-114 |
| Logical Table Name | judge_assignments |
| Owning Module | Scheduling and Judging |
| Purpose | Stores judge assignments as the Scheduling and Judging module's governed relational record. |
| Aggregate | Judge Assignment |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0191: (workspace_id) -> workspaces(id); QMDB-REL-0192: (workspace_id, judge_panel_id) -> judge_panels(workspace_id, id); QMDB-REL-0193: (person_id) -> persons(id); QMDB-REL-0194: (workspace_id, competition_session_id) -> competition_sessions(workspace_id, id) |
| Check Constraints | QMDB-CST-0317: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0268 (public_id); QMDB-IDX-0269 (workspace_id, status_code, created_at, id); QMDB-IDX-0270 (workspace_id, judge_panel_id); QMDB-IDX-0271 (person_id); QMDB-IDX-0272 (workspace_id, competition_session_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Judge Assignments by relational/public identity; List Judge Assignments within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-014; QMDB-FR-SCH-001; QMDB-FR-SCH-002; QMDB-FR-SCH-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0828 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0829 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0830 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0831 | `judge_panel_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to judge_panels. | Domain validation plus schema type/constraint. |
| QMDB-COL-0832 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-0833 | `competition_session_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to competition_sessions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0834 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Judge Assignments. | Domain validation plus schema type/constraint. |
| QMDB-COL-0835 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Judge Assignments. | Domain validation plus schema type/constraint. |
| QMDB-COL-0836 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Judge Assignments. | Domain validation plus schema type/constraint. |
| QMDB-COL-0837 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Judge Assignments. | Domain validation plus schema type/constraint. |

### QMDB-TBL-115 — `judge_qualification_evidence`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-115 |
| Logical Table Name | judge_qualification_evidence |
| Owning Module | Scheduling and Judging |
| Purpose | Stores judge qualification evidence as the Scheduling and Judging module's governed relational record. |
| Aggregate | Judge Assignment |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | External Evidence Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0195: (workspace_id) -> workspaces(id); QMDB-REL-0196: (workspace_id, judge_assignment_id) -> judge_assignments(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0273 (public_id); QMDB-IDX-0274 (workspace_id, created_at, id); QMDB-IDX-0275 (workspace_id, judge_assignment_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Judge Qualification Evidence by relational/public identity; List Judge Qualification Evidence within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-014; QMDB-FR-SCH-001; QMDB-FR-SCH-002; QMDB-FR-SCH-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0838 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0839 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0840 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0841 | `judge_assignment_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to judge_assignments. | Domain validation plus schema type/constraint. |
| QMDB-COL-0842 | `evidence_type` | Human-readable code | `VARCHAR(64)` | No | None | Yes | No | No | Yes | Evidence Type for Judge Qualification Evidence. | Domain validation plus schema type/constraint. |
| QMDB-COL-0843 | `object_key` | Object-storage key | `VARCHAR(1024)` | Yes | None | No | No | Yes | No | Private object reference; binary content is never stored in MySQL. | Domain validation plus schema type/constraint. |
| QMDB-COL-0844 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | No | No | Yes | Content Hash for Judge Qualification Evidence. | Domain validation plus schema type/constraint. |
| QMDB-COL-0845 | `collected_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | No | Collected At for Judge Qualification Evidence. | Domain validation plus schema type/constraint. |
| QMDB-COL-0846 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Judge Qualification Evidence. | Domain validation plus schema type/constraint. |

### QMDB-TBL-116 — `conflict_declarations`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-116 |
| Logical Table Name | conflict_declarations |
| Owning Module | Scheduling and Judging |
| Purpose | Stores conflict declarations as the Scheduling and Judging module's governed relational record. |
| Aggregate | Judge Assignment |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0197: (workspace_id) -> workspaces(id); QMDB-REL-0198: (workspace_id, judge_assignment_id) -> judge_assignments(workspace_id, id) |
| Check Constraints | QMDB-CST-0324: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0276 (public_id); QMDB-IDX-0277 (workspace_id, status_code, created_at, id); QMDB-IDX-0278 (workspace_id, judge_assignment_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Conflict Declarations by relational/public identity; List Conflict Declarations within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-014; QMDB-FR-SCH-001; QMDB-FR-SCH-002; QMDB-FR-SCH-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0847 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0848 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0849 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0850 | `judge_assignment_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to judge_assignments. | Domain validation plus schema type/constraint. |
| QMDB-COL-0851 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Conflict Declarations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0852 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Conflict Declarations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0853 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Conflict Declarations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0854 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Conflict Declarations. | Domain validation plus schema type/constraint. |

### QMDB-TBL-117 — `conflict_reviews`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-117 |
| Logical Table Name | conflict_reviews |
| Owning Module | Scheduling and Judging |
| Purpose | Stores conflict reviews as the Scheduling and Judging module's governed relational record. |
| Aggregate | Judge Assignment |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0199: (workspace_id) -> workspaces(id); QMDB-REL-0200: (workspace_id, conflict_declaration_id) -> conflict_declarations(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0279 (public_id); QMDB-IDX-0280 (workspace_id, decision_code, created_at, id); QMDB-IDX-0281 (workspace_id, conflict_declaration_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Conflict Reviews by relational/public identity; List Conflict Reviews within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-014; QMDB-FR-SCH-001; QMDB-FR-SCH-002; QMDB-FR-SCH-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0855 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0856 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0857 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0858 | `conflict_declaration_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to conflict_declarations. | Domain validation plus schema type/constraint. |
| QMDB-COL-0859 | `decision_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Decision Code for Conflict Reviews. | Domain validation plus schema type/constraint. |
| QMDB-COL-0860 | `reason_code` | Human-readable code | `VARCHAR(64)` | No | None | No | Yes | No | No | Reason Code for Conflict Reviews. | Domain validation plus schema type/constraint. |
| QMDB-COL-0861 | `decided_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Decided At for Conflict Reviews. | Domain validation plus schema type/constraint. |
| QMDB-COL-0862 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Conflict Reviews. | Domain validation plus schema type/constraint. |

### QMDB-TBL-118 — `recusal_records`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-118 |
| Logical Table Name | recusal_records |
| Owning Module | Scheduling and Judging |
| Purpose | Stores recusal records as the Scheduling and Judging module's governed relational record. |
| Aggregate | Judge Assignment |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0201: (workspace_id) -> workspaces(id); QMDB-REL-0202: (workspace_id, judge_assignment_id) -> judge_assignments(workspace_id, id) |
| Check Constraints | QMDB-CST-0331: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0282 (public_id); QMDB-IDX-0283 (workspace_id, status_code, created_at, id); QMDB-IDX-0284 (workspace_id, judge_assignment_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Recusal Records by relational/public identity; List Recusal Records within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-014; QMDB-FR-SCH-001; QMDB-FR-SCH-002; QMDB-FR-SCH-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0863 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0864 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0865 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0866 | `judge_assignment_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to judge_assignments. | Domain validation plus schema type/constraint. |
| QMDB-COL-0867 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Recusal Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-0868 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Recusal Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-0869 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Recusal Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-0870 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Recusal Records. | Domain validation plus schema type/constraint. |

### QMDB-TBL-119 — `judge_replacements`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-119 |
| Logical Table Name | judge_replacements |
| Owning Module | Scheduling and Judging |
| Purpose | Stores judge replacements as the Scheduling and Judging module's governed relational record. |
| Aggregate | Judge Assignment |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0203: (workspace_id) -> workspaces(id); QMDB-REL-0204: (workspace_id, judge_assignment_id) -> judge_assignments(workspace_id, id) |
| Check Constraints | QMDB-CST-0335: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0285 (public_id); QMDB-IDX-0286 (workspace_id, status_code, created_at, id); QMDB-IDX-0287 (workspace_id, judge_assignment_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Judge Replacements by relational/public identity; List Judge Replacements within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-014; QMDB-FR-SCH-001; QMDB-FR-SCH-002; QMDB-FR-SCH-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0871 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0872 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0873 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0874 | `judge_assignment_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to judge_assignments. | Domain validation plus schema type/constraint. |
| QMDB-COL-0875 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Judge Replacements. | Domain validation plus schema type/constraint. |
| QMDB-COL-0876 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Judge Replacements. | Domain validation plus schema type/constraint. |
| QMDB-COL-0877 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Judge Replacements. | Domain validation plus schema type/constraint. |
| QMDB-COL-0878 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Judge Replacements. | Domain validation plus schema type/constraint. |

### QMDB-TBL-120 — `performances`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-120 |
| Logical Table Name | performances |
| Owning Module | Performance and Scoring |
| Purpose | Stores performances as the Performance and Scoring module's governed relational record. |
| Aggregate | Performance |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0205: (workspace_id) -> workspaces(id); QMDB-REL-0206: (workspace_id, participant_snapshot_id) -> participant_snapshots(workspace_id, id); QMDB-REL-0207: (workspace_id, competition_session_id) -> competition_sessions(workspace_id, id); QMDB-REL-0208: (workspace_id, judge_panel_id) -> judge_panels(workspace_id, id); QMDB-REL-0209: (workspace_id, ruleset_version_id) -> ruleset_versions(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0288 (public_id); QMDB-IDX-0289 (workspace_id, status_code, created_at, id); QMDB-IDX-0290 (workspace_id, participant_snapshot_id); QMDB-IDX-0291 (workspace_id, competition_session_id); QMDB-IDX-0292 (workspace_id, judge_panel_id); QMDB-IDX-0293 (workspace_id, ruleset_version_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Performances by relational/public identity; List Performances within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-015; QMDB-FR-SCR-001; QMDB-FR-SCR-002; QMDB-FR-SCR-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0879 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0880 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0881 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0882 | `participant_snapshot_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to participant_snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-0883 | `competition_session_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to competition_sessions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0884 | `judge_panel_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to judge_panels. | Domain validation plus schema type/constraint. |
| QMDB-COL-0885 | `ruleset_version_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to ruleset_versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0886 | `performance_code` | Human-readable code | `VARCHAR(64)` | No | None | No | No | No | Yes | Performance Code for Performances. | Domain validation plus schema type/constraint. |
| QMDB-COL-0887 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Performances. | Domain validation plus schema type/constraint. |
| QMDB-COL-0888 | `opened_at` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Opened At for Performances. | Domain validation plus schema type/constraint. |
| QMDB-COL-0889 | `closed_at` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Closed At for Performances. | Domain validation plus schema type/constraint. |
| QMDB-COL-0890 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Performances. | Domain validation plus schema type/constraint. |

### QMDB-TBL-121 — `performance_passages`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-121 |
| Logical Table Name | performance_passages |
| Owning Module | Performance and Scoring |
| Purpose | Stores performance passages as the Performance and Scoring module's governed relational record. |
| Aggregate | Performance |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0210: (workspace_id) -> workspaces(id); QMDB-REL-0211: (workspace_id, performance_id) -> performances(workspace_id, id); QMDB-REL-0212: (passage_range_id) -> passage_ranges(id) |
| Check Constraints | QMDB-CST-0342: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0294 (public_id); QMDB-IDX-0295 (workspace_id, status_code, created_at, id); QMDB-IDX-0296 (workspace_id, performance_id); QMDB-IDX-0297 (passage_range_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Performance Passages by relational/public identity; List Performance Passages within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-015; QMDB-FR-SCR-001; QMDB-FR-SCR-002; QMDB-FR-SCR-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | OD-054; OD-055; OD-063 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0891 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0892 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0893 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0894 | `performance_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to performances. | Domain validation plus schema type/constraint. |
| QMDB-COL-0895 | `passage_range_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to passage_ranges. | Domain validation plus schema type/constraint. |
| QMDB-COL-0896 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Performance Passages. | Domain validation plus schema type/constraint. |
| QMDB-COL-0897 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Performance Passages. | Domain validation plus schema type/constraint. |
| QMDB-COL-0898 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Performance Passages. | Domain validation plus schema type/constraint. |
| QMDB-COL-0899 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Performance Passages. | Domain validation plus schema type/constraint. |

### QMDB-TBL-122 — `performance_events`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-122 |
| Logical Table Name | performance_events |
| Owning Module | Performance and Scoring |
| Purpose | Stores performance events as the Performance and Scoring module's governed relational record. |
| Aggregate | Performance |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | HIGH |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0213: (workspace_id) -> workspaces(id); QMDB-REL-0214: (workspace_id, performance_id) -> performances(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0298 (workspace_id, occurred_at, id); QMDB-IDX-0299 (workspace_id, performance_id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Performance Events by relational/public identity; List Performance Events within one Workspace using keyset pagination; Read Performance Events by bounded occurrence range |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-015; QMDB-FR-SCR-001; QMDB-FR-SCR-002; QMDB-FR-SCR-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0900 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0901 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0902 | `performance_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to performances. | Domain validation plus schema type/constraint. |
| QMDB-COL-0903 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | Yes | No | No | Yes | Event Type for Performance Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-0904 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Occurred At for Performance Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-0905 | `payload_json` | JSON document | `JSON` | Yes | None | Yes | No | No | No | Versioned bounded event metadata; core record fields remain relational. | Domain validation plus schema type/constraint. |
| QMDB-COL-0906 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | No | No | Yes | Content Hash for Performance Events. | Domain validation plus schema type/constraint. |

### QMDB-TBL-123 — `performance_incidents`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-123 |
| Logical Table Name | performance_incidents |
| Owning Module | Performance and Scoring |
| Purpose | Stores performance incidents as the Performance and Scoring module's governed relational record. |
| Aggregate | Performance |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0215: (workspace_id) -> workspaces(id); QMDB-REL-0216: (workspace_id, performance_id) -> performances(workspace_id, id) |
| Check Constraints | QMDB-CST-0348: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0300 (public_id); QMDB-IDX-0301 (workspace_id, status_code, created_at, id); QMDB-IDX-0302 (workspace_id, performance_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Performance Incidents by relational/public identity; List Performance Incidents within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-015; QMDB-FR-SCR-001; QMDB-FR-SCR-002; QMDB-FR-SCR-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0907 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0908 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0909 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0910 | `performance_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to performances. | Domain validation plus schema type/constraint. |
| QMDB-COL-0911 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Performance Incidents. | Domain validation plus schema type/constraint. |
| QMDB-COL-0912 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Performance Incidents. | Domain validation plus schema type/constraint. |
| QMDB-COL-0913 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Performance Incidents. | Domain validation plus schema type/constraint. |
| QMDB-COL-0914 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Performance Incidents. | Domain validation plus schema type/constraint. |

### QMDB-TBL-124 — `score_sheets`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-124 |
| Logical Table Name | score_sheets |
| Owning Module | Performance and Scoring |
| Purpose | Stores score sheets as the Performance and Scoring module's governed relational record. |
| Aggregate | Score Sheet |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0217: (workspace_id) -> workspaces(id); QMDB-REL-0218: (workspace_id, performance_id) -> performances(workspace_id, id); QMDB-REL-0219: (workspace_id, judge_assignment_id) -> judge_assignments(workspace_id, id) |
| Check Constraints | QMDB-CST-0352: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0303 (public_id); QMDB-IDX-0304 (workspace_id, sheet_status, created_at, id); QMDB-IDX-0305 (workspace_id, performance_id); QMDB-IDX-0306 (workspace_id, judge_assignment_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Score Sheets by relational/public identity; List Score Sheets within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-015; QMDB-FR-SCR-001; QMDB-FR-SCR-002; QMDB-FR-SCR-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0915 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0916 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0917 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0918 | `performance_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to performances. | Domain validation plus schema type/constraint. |
| QMDB-COL-0919 | `judge_assignment_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to judge_assignments. | Domain validation plus schema type/constraint. |
| QMDB-COL-0920 | `sheet_status` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Sheet Status for Score Sheets. | Domain validation plus schema type/constraint. |
| QMDB-COL-0921 | `current_version_number` | Version number | `INT UNSIGNED` | No | None | No | Yes | No | Yes | Current Version Number for Score Sheets. | Domain validation plus schema type/constraint. |
| QMDB-COL-0922 | `lock_version` | Version number | `INT UNSIGNED` | No | None | No | Yes | No | No | Lock Version for Score Sheets. | Domain validation plus schema type/constraint. |
| QMDB-COL-0923 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Score Sheets. | Domain validation plus schema type/constraint. |

### QMDB-TBL-125 — `score_sheet_versions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-125 |
| Logical Table Name | score_sheet_versions |
| Owning Module | Performance and Scoring |
| Purpose | Stores score sheet versions as the Performance and Scoring module's governed relational record. |
| Aggregate | Score Sheet |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Immutable Official Version |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0220: (workspace_id) -> workspaces(id); QMDB-REL-0221: (workspace_id, score_sheet_id) -> score_sheets(workspace_id, id); QMDB-REL-0222: (workspace_id, ruleset_version_id) -> ruleset_versions(workspace_id, id) |
| Check Constraints | QMDB-CST-0356: Version value shall be positive and monotonic within its lineage.; QMDB-CST-0357: Official score component shall be exact and non-negative; configured upper bounds are validated against the locked Ruleset Version.; QMDB-CST-0358: Official score component shall be exact and non-negative; configured upper bounds are validated against the locked Ruleset Version.; QMDB-CST-0359: Official score component shall be exact and non-negative; configured upper bounds are validated against the locked Ruleset Version. |
| Indexes | QMDB-IDX-0307 (workspace_id, submitted_at, id); QMDB-IDX-0308 (workspace_id, score_sheet_id); QMDB-IDX-0309 (workspace_id, ruleset_version_id) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Score Sheet Versions by relational/public identity; List Score Sheet Versions within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-015; QMDB-FR-SCR-001; QMDB-FR-SCR-002; QMDB-FR-SCR-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | OD-049 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0924 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0925 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0926 | `score_sheet_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to score_sheets. | Domain validation plus schema type/constraint. |
| QMDB-COL-0927 | `ruleset_version_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to ruleset_versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0928 | `version_number` | Version number | `INT UNSIGNED` | No | None | No | Yes | No | Yes | Version Number for Score Sheet Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0929 | `raw_total` | Score decimal | `DECIMAL(P_SCORE,S_SCORE)` | No | None | No | Yes | No | No | Raw Total for Score Sheet Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0930 | `deduction_total` | Score decimal | `DECIMAL(P_SCORE,S_SCORE)` | No | None | No | Yes | No | No | Deduction Total for Score Sheet Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0931 | `server_total` | Score decimal | `DECIMAL(P_SCORE,S_SCORE)` | No | None | No | Yes | No | No | Server Total for Score Sheet Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0932 | `calculation_checksum` | Content hash | `BINARY(32)` | No | None | No | Yes | No | No | Calculation Checksum for Score Sheet Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0933 | `submitted_at` | UTC timestamp | `DATETIME(6)` | Yes | None | No | Yes | No | Yes | Submitted At for Score Sheet Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0934 | `locked_at` | UTC timestamp | `DATETIME(6)` | Yes | None | No | Yes | No | No | Locked At for Score Sheet Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0935 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Score Sheet Versions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-126 — `score_items`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-126 |
| Logical Table Name | score_items |
| Owning Module | Performance and Scoring |
| Purpose | Stores score items as the Performance and Scoring module's governed relational record. |
| Aggregate | Score Sheet |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | MODERATE |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0223: (workspace_id) -> workspaces(id); QMDB-REL-0224: (workspace_id, score_sheet_version_id) -> score_sheet_versions(workspace_id, id); QMDB-REL-0225: (workspace_id, scoring_criterion_id) -> scoring_criteria(workspace_id, id) |
| Check Constraints | QMDB-CST-0364: Official score component shall be exact and non-negative; configured upper bounds are validated against the locked Ruleset Version.; QMDB-CST-0365: Official score component shall be exact and non-negative; configured upper bounds are validated against the locked Ruleset Version.; QMDB-CST-0366: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0310 (public_id); QMDB-IDX-0311 (workspace_id, created_at, id); QMDB-IDX-0312 (workspace_id, score_sheet_version_id); QMDB-IDX-0313 (workspace_id, scoring_criterion_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Score Items by relational/public identity; List Score Items within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-015; QMDB-FR-SCR-001; QMDB-FR-SCR-002; QMDB-FR-SCR-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | OD-049 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0936 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0937 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0938 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0939 | `score_sheet_version_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to score_sheet_versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0940 | `scoring_criterion_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to scoring_criteria. | Domain validation plus schema type/constraint. |
| QMDB-COL-0941 | `criterion_sequence` | Sequence number | `SMALLINT UNSIGNED` | No | None | No | Yes | No | No | Criterion Sequence for Score Items. | Domain validation plus schema type/constraint. |
| QMDB-COL-0942 | `raw_score` | Score decimal | `DECIMAL(P_SCORE,S_SCORE)` | No | None | No | Yes | No | No | Raw Score for Score Items. | Domain validation plus schema type/constraint. |
| QMDB-COL-0943 | `adjusted_score` | Score decimal | `DECIMAL(P_SCORE,S_SCORE)` | No | None | No | Yes | No | No | Adjusted Score for Score Items. | Domain validation plus schema type/constraint. |
| QMDB-COL-0944 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Score Items. | Domain validation plus schema type/constraint. |

### QMDB-TBL-127 — `score_deductions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-127 |
| Logical Table Name | score_deductions |
| Owning Module | Performance and Scoring |
| Purpose | Stores score deductions as the Performance and Scoring module's governed relational record. |
| Aggregate | Score Sheet |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0226: (workspace_id) -> workspaces(id); QMDB-REL-0227: (workspace_id, score_sheet_version_id) -> score_sheet_versions(workspace_id, id); QMDB-REL-0228: (workspace_id, deduction_rule_id) -> deduction_rules(workspace_id, id) |
| Check Constraints | QMDB-CST-0370: Official score component shall be exact and non-negative; configured upper bounds are validated against the locked Ruleset Version.; QMDB-CST-0371: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0314 (public_id); QMDB-IDX-0315 (workspace_id, created_at, id); QMDB-IDX-0316 (workspace_id, score_sheet_version_id); QMDB-IDX-0317 (workspace_id, deduction_rule_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Score Deductions by relational/public identity; List Score Deductions within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-015; QMDB-FR-SCR-001; QMDB-FR-SCR-002; QMDB-FR-SCR-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | OD-049 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0945 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0946 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0947 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0948 | `score_sheet_version_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to score_sheet_versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0949 | `deduction_rule_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to deduction_rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-0950 | `deduction_sequence` | Sequence number | `SMALLINT UNSIGNED` | No | None | No | Yes | No | No | Deduction Sequence for Score Deductions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0951 | `deduction_amount` | Score decimal | `DECIMAL(P_SCORE,S_SCORE)` | No | None | No | Yes | No | No | Deduction Amount for Score Deductions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0952 | `reason_code` | Human-readable code | `VARCHAR(64)` | No | None | No | Yes | No | Yes | Reason Code for Score Deductions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0953 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Score Deductions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-128 — `score_mistakes`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-128 |
| Logical Table Name | score_mistakes |
| Owning Module | Performance and Scoring |
| Purpose | Stores score mistakes as the Performance and Scoring module's governed relational record. |
| Aggregate | Score Sheet |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0229: (workspace_id) -> workspaces(id); QMDB-REL-0230: (workspace_id, score_sheet_version_id) -> score_sheet_versions(workspace_id, id); QMDB-REL-0231: (competition_mistake_taxonomy_id) -> competition_mistake_taxonomy(id); QMDB-REL-0232: (ayah_id) -> ayahs(id) |
| Check Constraints | QMDB-CST-0375: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0318 (public_id); QMDB-IDX-0319 (workspace_id, status_code, created_at, id); QMDB-IDX-0320 (workspace_id, score_sheet_version_id); QMDB-IDX-0321 (competition_mistake_taxonomy_id); QMDB-IDX-0322 (ayah_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Score Mistakes by relational/public identity; List Score Mistakes within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-015; QMDB-FR-SCR-001; QMDB-FR-SCR-002; QMDB-FR-SCR-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0954 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0955 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0956 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0957 | `score_sheet_version_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to score_sheet_versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0958 | `competition_mistake_taxonomy_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to competition_mistake_taxonomy. | Domain validation plus schema type/constraint. |
| QMDB-COL-0959 | `ayah_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to ayahs. | Domain validation plus schema type/constraint. |
| QMDB-COL-0960 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Score Mistakes. | Domain validation plus schema type/constraint. |
| QMDB-COL-0961 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Score Mistakes. | Domain validation plus schema type/constraint. |
| QMDB-COL-0962 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Score Mistakes. | Domain validation plus schema type/constraint. |
| QMDB-COL-0963 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Score Mistakes. | Domain validation plus schema type/constraint. |

### QMDB-TBL-129 — `score_signatures`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-129 |
| Logical Table Name | score_signatures |
| Owning Module | Performance and Scoring |
| Purpose | Stores score signatures as the Performance and Scoring module's governed relational record. |
| Aggregate | Score Sheet |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0233: (workspace_id) -> workspaces(id); QMDB-REL-0234: (workspace_id, score_sheet_version_id) -> score_sheet_versions(workspace_id, id) |
| Check Constraints | QMDB-CST-0379: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0323 (public_id); QMDB-IDX-0324 (workspace_id, status_code, created_at, id); QMDB-IDX-0325 (workspace_id, score_sheet_version_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Score Signatures by relational/public identity; List Score Signatures within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-015; QMDB-FR-SCR-001; QMDB-FR-SCR-002; QMDB-FR-SCR-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0964 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0965 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0966 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0967 | `score_sheet_version_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to score_sheet_versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0968 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Score Signatures. | Domain validation plus schema type/constraint. |
| QMDB-COL-0969 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Score Signatures. | Domain validation plus schema type/constraint. |
| QMDB-COL-0970 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Score Signatures. | Domain validation plus schema type/constraint. |
| QMDB-COL-0971 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Score Signatures. | Domain validation plus schema type/constraint. |

### QMDB-TBL-130 — `score_submission_receipts`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-130 |
| Logical Table Name | score_submission_receipts |
| Owning Module | Performance and Scoring |
| Purpose | Stores score submission receipts as the Performance and Scoring module's governed relational record. |
| Aggregate | Score Sheet |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0235: (workspace_id) -> workspaces(id); QMDB-REL-0236: (workspace_id, score_sheet_version_id) -> score_sheet_versions(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0326 (workspace_id, occurred_at, id); QMDB-IDX-0327 (workspace_id, score_sheet_version_id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Score Submission Receipts by relational/public identity; List Score Submission Receipts within one Workspace using keyset pagination; Read Score Submission Receipts by bounded occurrence range |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-015; QMDB-FR-SCR-001; QMDB-FR-SCR-002; QMDB-FR-SCR-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0972 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0973 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0974 | `score_sheet_version_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to score_sheet_versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0975 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | Yes | Yes | No | Yes | Event Type for Score Submission Receipts. | Domain validation plus schema type/constraint. |
| QMDB-COL-0976 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Occurred At for Score Submission Receipts. | Domain validation plus schema type/constraint. |
| QMDB-COL-0977 | `payload_json` | JSON document | `JSON` | Yes | None | Yes | Yes | No | No | Versioned bounded event metadata; core record fields remain relational. | Domain validation plus schema type/constraint. |
| QMDB-COL-0978 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | Yes | No | Yes | Content Hash for Score Submission Receipts. | Domain validation plus schema type/constraint. |

### QMDB-TBL-131 — `score_reopening_requests`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-131 |
| Logical Table Name | score_reopening_requests |
| Owning Module | Performance and Scoring |
| Purpose | Stores score reopening requests as the Performance and Scoring module's governed relational record. |
| Aggregate | Score Sheet |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0237: (workspace_id) -> workspaces(id); QMDB-REL-0238: (workspace_id, score_sheet_id) -> score_sheets(workspace_id, id); QMDB-REL-0239: (workspace_id, score_sheet_version_id) -> score_sheet_versions(workspace_id, id) |
| Check Constraints | QMDB-CST-0385: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0328 (public_id); QMDB-IDX-0329 (workspace_id, status_code, created_at, id); QMDB-IDX-0330 (workspace_id, score_sheet_id); QMDB-IDX-0331 (workspace_id, score_sheet_version_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Score Reopening Requests by relational/public identity; List Score Reopening Requests within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-015; QMDB-FR-SCR-001; QMDB-FR-SCR-002; QMDB-FR-SCR-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0979 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0980 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0981 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0982 | `score_sheet_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to score_sheets. | Domain validation plus schema type/constraint. |
| QMDB-COL-0983 | `score_sheet_version_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to score_sheet_versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-0984 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Score Reopening Requests. | Domain validation plus schema type/constraint. |
| QMDB-COL-0985 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Score Reopening Requests. | Domain validation plus schema type/constraint. |
| QMDB-COL-0986 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Score Reopening Requests. | Domain validation plus schema type/constraint. |
| QMDB-COL-0987 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Score Reopening Requests. | Domain validation plus schema type/constraint. |

### QMDB-TBL-132 — `score_reopening_approvals`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-132 |
| Logical Table Name | score_reopening_approvals |
| Owning Module | Performance and Scoring |
| Purpose | Stores score reopening approvals as the Performance and Scoring module's governed relational record. |
| Aggregate | Score Sheet |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0240: (workspace_id) -> workspaces(id); QMDB-REL-0241: (workspace_id, score_reopening_request_id) -> score_reopening_requests(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0332 (public_id); QMDB-IDX-0333 (workspace_id, decision_code, created_at, id); QMDB-IDX-0334 (workspace_id, score_reopening_request_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Score Reopening Approvals by relational/public identity; List Score Reopening Approvals within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-015; QMDB-FR-SCR-001; QMDB-FR-SCR-002; QMDB-FR-SCR-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0988 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0989 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0990 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0991 | `score_reopening_request_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to score_reopening_requests. | Domain validation plus schema type/constraint. |
| QMDB-COL-0992 | `decision_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Decision Code for Score Reopening Approvals. | Domain validation plus schema type/constraint. |
| QMDB-COL-0993 | `reason_code` | Human-readable code | `VARCHAR(64)` | No | None | No | Yes | No | No | Reason Code for Score Reopening Approvals. | Domain validation plus schema type/constraint. |
| QMDB-COL-0994 | `decided_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Decided At for Score Reopening Approvals. | Domain validation plus schema type/constraint. |
| QMDB-COL-0995 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Score Reopening Approvals. | Domain validation plus schema type/constraint. |

### QMDB-TBL-133 — `panel_aggregations`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-133 |
| Logical Table Name | panel_aggregations |
| Owning Module | Performance and Scoring |
| Purpose | Stores panel aggregations as the Performance and Scoring module's governed relational record. |
| Aggregate | Panel Aggregation |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0242: (workspace_id) -> workspaces(id); QMDB-REL-0243: (workspace_id, performance_id) -> performances(workspace_id, id); QMDB-REL-0244: (workspace_id, judge_panel_id) -> judge_panels(workspace_id, id); QMDB-REL-0245: (workspace_id, ruleset_version_id) -> ruleset_versions(workspace_id, id) |
| Check Constraints | QMDB-CST-0392: Version value shall be positive and monotonic within its lineage.; QMDB-CST-0393: Official score component shall be exact and non-negative; configured upper bounds are validated against the locked Ruleset Version. |
| Indexes | QMDB-IDX-0335 (public_id); QMDB-IDX-0336 (workspace_id, created_at, id); QMDB-IDX-0337 (workspace_id, performance_id); QMDB-IDX-0338 (workspace_id, judge_panel_id); QMDB-IDX-0339 (workspace_id, ruleset_version_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Panel Aggregations by relational/public identity; List Panel Aggregations within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-015; QMDB-FR-SCR-001; QMDB-FR-SCR-002; QMDB-FR-SCR-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | OD-049 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-0996 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-0997 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-0998 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-0999 | `performance_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to performances. | Domain validation plus schema type/constraint. |
| QMDB-COL-1000 | `judge_panel_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to judge_panels. | Domain validation plus schema type/constraint. |
| QMDB-COL-1001 | `ruleset_version_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to ruleset_versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1002 | `aggregation_version` | Version number | `INT UNSIGNED` | No | None | No | No | No | No | Aggregation Version for Panel Aggregations. | Domain validation plus schema type/constraint. |
| QMDB-COL-1003 | `aggregate_score` | Score decimal | `DECIMAL(P_SCORE,S_SCORE)` | No | None | No | No | No | No | Aggregate Score for Panel Aggregations. | Domain validation plus schema type/constraint. |
| QMDB-COL-1004 | `input_set_checksum` | Content hash | `BINARY(32)` | No | None | No | No | No | No | Input Set Checksum for Panel Aggregations. | Domain validation plus schema type/constraint. |
| QMDB-COL-1005 | `completed_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Completed At for Panel Aggregations. | Domain validation plus schema type/constraint. |
| QMDB-COL-1006 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Panel Aggregations. | Domain validation plus schema type/constraint. |

### QMDB-TBL-134 — `aggregation_inputs`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-134 |
| Logical Table Name | aggregation_inputs |
| Owning Module | Performance and Scoring |
| Purpose | Stores aggregation inputs as the Performance and Scoring module's governed relational record. |
| Aggregate | Panel Aggregation |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0246: (workspace_id) -> workspaces(id); QMDB-REL-0247: (workspace_id, panel_aggregation_id) -> panel_aggregations(workspace_id, id); QMDB-REL-0248: (workspace_id, score_sheet_version_id) -> score_sheet_versions(workspace_id, id) |
| Check Constraints | QMDB-CST-0396: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0340 (workspace_id, created_at, id); QMDB-IDX-0341 (workspace_id, panel_aggregation_id); QMDB-IDX-0342 (workspace_id, score_sheet_version_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Aggregation Inputs by relational/public identity; List Aggregation Inputs within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-015; QMDB-FR-SCR-001; QMDB-FR-SCR-002; QMDB-FR-SCR-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1007 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1008 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1009 | `panel_aggregation_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to panel_aggregations. | Domain validation plus schema type/constraint. |
| QMDB-COL-1010 | `score_sheet_version_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to score_sheet_versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1011 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | No | No | Yes | Sequence Number for Aggregation Inputs. | Domain validation plus schema type/constraint. |
| QMDB-COL-1012 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Aggregation Inputs. | Domain validation plus schema type/constraint. |

### QMDB-TBL-135 — `tie_break_evaluations`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-135 |
| Logical Table Name | tie_break_evaluations |
| Owning Module | Performance and Scoring |
| Purpose | Stores tie break evaluations as the Performance and Scoring module's governed relational record. |
| Aggregate | Panel Aggregation |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0249: (workspace_id) -> workspaces(id); QMDB-REL-0250: (workspace_id, panel_aggregation_id) -> panel_aggregations(workspace_id, id); QMDB-REL-0251: (workspace_id, tie_break_rule_id) -> tie_break_rules(workspace_id, id) |
| Check Constraints | QMDB-CST-0400: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0343 (public_id); QMDB-IDX-0344 (workspace_id, status_code, created_at, id); QMDB-IDX-0345 (workspace_id, panel_aggregation_id); QMDB-IDX-0346 (workspace_id, tie_break_rule_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Tie Break Evaluations by relational/public identity; List Tie Break Evaluations within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-015; QMDB-FR-SCR-001; QMDB-FR-SCR-002; QMDB-FR-SCR-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1013 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1014 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1015 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1016 | `panel_aggregation_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to panel_aggregations. | Domain validation plus schema type/constraint. |
| QMDB-COL-1017 | `tie_break_rule_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to tie_break_rules. | Domain validation plus schema type/constraint. |
| QMDB-COL-1018 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Tie Break Evaluations. | Domain validation plus schema type/constraint. |
| QMDB-COL-1019 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Tie Break Evaluations. | Domain validation plus schema type/constraint. |
| QMDB-COL-1020 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Tie Break Evaluations. | Domain validation plus schema type/constraint. |
| QMDB-COL-1021 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Tie Break Evaluations. | Domain validation plus schema type/constraint. |

### QMDB-TBL-136 — `score_anomaly_flags`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-136 |
| Logical Table Name | score_anomaly_flags |
| Owning Module | Performance and Scoring |
| Purpose | Stores score anomaly flags as the Performance and Scoring module's governed relational record. |
| Aggregate | Score Sheet |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0252: (workspace_id) -> workspaces(id); QMDB-REL-0253: (workspace_id, score_sheet_version_id) -> score_sheet_versions(workspace_id, id) |
| Check Constraints | QMDB-CST-0404: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0347 (public_id); QMDB-IDX-0348 (workspace_id, status_code, created_at, id); QMDB-IDX-0349 (workspace_id, score_sheet_version_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Score Anomaly Flags by relational/public identity; List Score Anomaly Flags within one Workspace using keyset pagination |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-015; QMDB-FR-SCR-001; QMDB-FR-SCR-002; QMDB-FR-SCR-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1022 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1023 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1024 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1025 | `score_sheet_version_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to score_sheet_versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1026 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Score Anomaly Flags. | Domain validation plus schema type/constraint. |
| QMDB-COL-1027 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Score Anomaly Flags. | Domain validation plus schema type/constraint. |
| QMDB-COL-1028 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Score Anomaly Flags. | Domain validation plus schema type/constraint. |
| QMDB-COL-1029 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Score Anomaly Flags. | Domain validation plus schema type/constraint. |

### QMDB-TBL-137 — `calculation_traces`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-137 |
| Logical Table Name | calculation_traces |
| Owning Module | Performance and Scoring |
| Purpose | Stores calculation traces as the Performance and Scoring module's governed relational record. |
| Aggregate | Panel Aggregation |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0254: (workspace_id) -> workspaces(id); QMDB-REL-0255: (workspace_id, panel_aggregation_id) -> panel_aggregations(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0350 (workspace_id, occurred_at, id); QMDB-IDX-0351 (workspace_id, panel_aggregation_id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Calculation Traces by relational/public identity; List Calculation Traces within one Workspace using keyset pagination; Read Calculation Traces by bounded occurrence range |
| Implementation Phase | P6 |
| Related Requirements | QMDB-DR-015; QMDB-FR-SCR-001; QMDB-FR-SCR-002; QMDB-FR-SCR-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1030 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1031 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1032 | `panel_aggregation_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to panel_aggregations. | Domain validation plus schema type/constraint. |
| QMDB-COL-1033 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | Yes | No | No | Yes | Event Type for Calculation Traces. | Domain validation plus schema type/constraint. |
| QMDB-COL-1034 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Occurred At for Calculation Traces. | Domain validation plus schema type/constraint. |
| QMDB-COL-1035 | `payload_json` | JSON document | `JSON` | Yes | None | Yes | No | No | No | Versioned bounded event metadata; core record fields remain relational. | Domain validation plus schema type/constraint. |
| QMDB-COL-1036 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | No | No | Yes | Content Hash for Calculation Traces. | Domain validation plus schema type/constraint. |

### QMDB-TBL-138 — `result_snapshots`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-138 |
| Logical Table Name | result_snapshots |
| Owning Module | Results and Appeals |
| Purpose | Stores result snapshots as the Results and Appeals module's governed relational record. |
| Aggregate | Result |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Immutable Official Version |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0256: (workspace_id) -> workspaces(id); QMDB-REL-0257: (workspace_id, competition_edition_id) -> competition_editions(workspace_id, id); QMDB-REL-0258: (workspace_id, panel_aggregation_id) -> panel_aggregations(workspace_id, id) |
| Check Constraints | QMDB-CST-0409: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0352 (workspace_id, result_status, created_at, id); QMDB-IDX-0353 (workspace_id, competition_edition_id); QMDB-IDX-0354 (workspace_id, panel_aggregation_id) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Result Snapshots by relational/public identity; List Result Snapshots within one Workspace using keyset pagination |
| Implementation Phase | P7 |
| Related Requirements | QMDB-DR-016; QMDB-FR-LIV-001; QMDB-FR-LIV-002; QMDB-FR-RSL-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1037 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1038 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1039 | `competition_edition_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to competition_editions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1040 | `panel_aggregation_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to panel_aggregations. | Domain validation plus schema type/constraint. |
| QMDB-COL-1041 | `result_version` | Version number | `INT UNSIGNED` | No | None | No | No | No | No | Result Version for Result Snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-1042 | `result_status` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Result Status for Result Snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-1043 | `result_checksum` | Content hash | `BINARY(32)` | No | None | No | No | No | No | Result Checksum for Result Snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-1044 | `published_at` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Published At for Result Snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-1045 | `finalized_at` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Finalized At for Result Snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-1046 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Result Snapshots. | Domain validation plus schema type/constraint. |

### QMDB-TBL-139 — `rankings`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-139 |
| Logical Table Name | rankings |
| Owning Module | Results and Appeals |
| Purpose | Stores rankings as the Results and Appeals module's governed relational record. |
| Aggregate | Result |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0259: (workspace_id) -> workspaces(id); QMDB-REL-0260: (workspace_id, result_snapshot_id) -> result_snapshots(workspace_id, id) |
| Check Constraints | QMDB-CST-0413: Official score component shall be exact and non-negative; configured upper bounds are validated against the locked Ruleset Version. |
| Indexes | QMDB-IDX-0355 (public_id); QMDB-IDX-0356 (workspace_id, created_at, id); QMDB-IDX-0357 (workspace_id, result_snapshot_id); QMDB-IDX-0657 (workspace_id, result_snapshot_id, rank_number) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Rankings by relational/public identity; List Rankings within one Workspace using keyset pagination |
| Implementation Phase | P7 |
| Related Requirements | QMDB-DR-016; QMDB-FR-LIV-001; QMDB-FR-LIV-002; QMDB-FR-RSL-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | OD-049 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1047 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1048 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1049 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1050 | `result_snapshot_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to result_snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-1051 | `rank_number` | Sequence number | `INT UNSIGNED` | No | None | No | No | No | Yes | Rank Number for Rankings. | Domain validation plus schema type/constraint. |
| QMDB-COL-1052 | `ranking_score` | Score decimal | `DECIMAL(P_SCORE,S_SCORE)` | No | None | No | No | No | No | Ranking Score for Rankings. | Domain validation plus schema type/constraint. |
| QMDB-COL-1053 | `tie_group_code` | Human-readable code | `VARCHAR(64)` | Yes | None | No | No | No | Yes | Tie Group Code for Rankings. | Domain validation plus schema type/constraint. |
| QMDB-COL-1054 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Rankings. | Domain validation plus schema type/constraint. |

### QMDB-TBL-140 — `placements`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-140 |
| Logical Table Name | placements |
| Owning Module | Results and Appeals |
| Purpose | Stores placements as the Results and Appeals module's governed relational record. |
| Aggregate | Result |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0261: (workspace_id) -> workspaces(id); QMDB-REL-0262: (workspace_id, result_snapshot_id) -> result_snapshots(workspace_id, id); QMDB-REL-0263: (workspace_id, participant_snapshot_id) -> participant_snapshots(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0358 (public_id); QMDB-IDX-0359 (workspace_id, created_at, id); QMDB-IDX-0360 (workspace_id, result_snapshot_id); QMDB-IDX-0361 (workspace_id, participant_snapshot_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Placements by relational/public identity; List Placements within one Workspace using keyset pagination |
| Implementation Phase | P7 |
| Related Requirements | QMDB-DR-016; QMDB-FR-LIV-001; QMDB-FR-LIV-002; QMDB-FR-RSL-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1055 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1056 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1057 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1058 | `result_snapshot_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to result_snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-1059 | `participant_snapshot_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to participant_snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-1060 | `placement_number` | Sequence number | `INT UNSIGNED` | No | None | No | No | No | Yes | Placement Number for Placements. | Domain validation plus schema type/constraint. |
| QMDB-COL-1061 | `placement_status` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Placement Status for Placements. | Domain validation plus schema type/constraint. |
| QMDB-COL-1062 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Placements. | Domain validation plus schema type/constraint. |

### QMDB-TBL-141 — `result_approvals`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-141 |
| Logical Table Name | result_approvals |
| Owning Module | Results and Appeals |
| Purpose | Stores result approvals as the Results and Appeals module's governed relational record. |
| Aggregate | Result |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Supersedable Official Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0264: (workspace_id) -> workspaces(id); QMDB-REL-0265: (workspace_id, result_snapshot_id) -> result_snapshots(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0362 (public_id); QMDB-IDX-0363 (workspace_id, decision_code, created_at, id); QMDB-IDX-0364 (workspace_id, result_snapshot_id) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Result Approvals by relational/public identity; List Result Approvals within one Workspace using keyset pagination |
| Implementation Phase | P7 |
| Related Requirements | QMDB-DR-016; QMDB-FR-LIV-001; QMDB-FR-LIV-002; QMDB-FR-RSL-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1063 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1064 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1065 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1066 | `result_snapshot_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to result_snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-1067 | `decision_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Decision Code for Result Approvals. | Domain validation plus schema type/constraint. |
| QMDB-COL-1068 | `reason_code` | Human-readable code | `VARCHAR(64)` | No | None | No | No | No | No | Reason Code for Result Approvals. | Domain validation plus schema type/constraint. |
| QMDB-COL-1069 | `decided_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Decided At for Result Approvals. | Domain validation plus schema type/constraint. |
| QMDB-COL-1070 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Result Approvals. | Domain validation plus schema type/constraint. |

### QMDB-TBL-142 — `result_finalization_bundles`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-142 |
| Logical Table Name | result_finalization_bundles |
| Owning Module | Results and Appeals |
| Purpose | Stores result finalization bundles as the Results and Appeals module's governed relational record. |
| Aggregate | Result |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Supersedable Official Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0266: (workspace_id) -> workspaces(id); QMDB-REL-0267: (workspace_id, result_snapshot_id) -> result_snapshots(workspace_id, id) |
| Check Constraints | QMDB-CST-0423: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0365 (public_id); QMDB-IDX-0366 (workspace_id, status_code, created_at, id); QMDB-IDX-0367 (workspace_id, result_snapshot_id) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Result Finalization Bundles by relational/public identity; List Result Finalization Bundles within one Workspace using keyset pagination |
| Implementation Phase | P7 |
| Related Requirements | QMDB-DR-016; QMDB-FR-LIV-001; QMDB-FR-LIV-002; QMDB-FR-RSL-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1071 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1072 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1073 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1074 | `result_snapshot_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to result_snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-1075 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Result Finalization Bundles. | Domain validation plus schema type/constraint. |
| QMDB-COL-1076 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Result Finalization Bundles. | Domain validation plus schema type/constraint. |
| QMDB-COL-1077 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Result Finalization Bundles. | Domain validation plus schema type/constraint. |
| QMDB-COL-1078 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Result Finalization Bundles. | Domain validation plus schema type/constraint. |

### QMDB-TBL-143 — `result_corrections`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-143 |
| Logical Table Name | result_corrections |
| Owning Module | Results and Appeals |
| Purpose | Stores result corrections as the Results and Appeals module's governed relational record. |
| Aggregate | Result |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Supersedable Official Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0268: (workspace_id) -> workspaces(id); QMDB-REL-0269: (workspace_id, result_snapshot_id) -> result_snapshots(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0368 (public_id); QMDB-IDX-0369 (workspace_id, decision_code, created_at, id); QMDB-IDX-0370 (workspace_id, result_snapshot_id) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Result Corrections by relational/public identity; List Result Corrections within one Workspace using keyset pagination |
| Implementation Phase | P7 |
| Related Requirements | QMDB-DR-016; QMDB-FR-LIV-001; QMDB-FR-LIV-002; QMDB-FR-RSL-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1079 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1080 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1081 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1082 | `result_snapshot_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to result_snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-1083 | `decision_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Decision Code for Result Corrections. | Domain validation plus schema type/constraint. |
| QMDB-COL-1084 | `reason_code` | Human-readable code | `VARCHAR(64)` | No | None | No | No | No | No | Reason Code for Result Corrections. | Domain validation plus schema type/constraint. |
| QMDB-COL-1085 | `decided_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Decided At for Result Corrections. | Domain validation plus schema type/constraint. |
| QMDB-COL-1086 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Result Corrections. | Domain validation plus schema type/constraint. |

### QMDB-TBL-144 — `result_supersession_links`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-144 |
| Logical Table Name | result_supersession_links |
| Owning Module | Results and Appeals |
| Purpose | Stores result supersession links as the Results and Appeals module's governed relational record. |
| Aggregate | Result |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Supersedable Official Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0270: (workspace_id) -> workspaces(id); QMDB-REL-0271: (workspace_id, result_snapshot_id) -> result_snapshots(workspace_id, id) |
| Check Constraints | QMDB-CST-0429: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0371 (workspace_id, created_at, id); QMDB-IDX-0372 (workspace_id, result_snapshot_id) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Result Supersession Links by relational/public identity; List Result Supersession Links within one Workspace using keyset pagination |
| Implementation Phase | P7 |
| Related Requirements | QMDB-DR-016; QMDB-FR-LIV-001; QMDB-FR-LIV-002; QMDB-FR-RSL-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1087 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1088 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1089 | `result_snapshot_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to result_snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-1090 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | Yes | No | Yes | Sequence Number for Result Supersession Links. | Domain validation plus schema type/constraint. |
| QMDB-COL-1091 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Result Supersession Links. | Domain validation plus schema type/constraint. |

### QMDB-TBL-145 — `appeals`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-145 |
| Logical Table Name | appeals |
| Owning Module | Results and Appeals |
| Purpose | Stores appeals as the Results and Appeals module's governed relational record. |
| Aggregate | Appeal |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0272: (workspace_id) -> workspaces(id); QMDB-REL-0273: (workspace_id, result_snapshot_id) -> result_snapshots(workspace_id, id); QMDB-REL-0274: (workspace_id, registration_id) -> registrations(workspace_id, id) |
| Check Constraints | QMDB-CST-0433: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0373 (public_id); QMDB-IDX-0374 (workspace_id, status_code, created_at, id); QMDB-IDX-0375 (workspace_id, result_snapshot_id); QMDB-IDX-0376 (workspace_id, registration_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Appeals by relational/public identity; List Appeals within one Workspace using keyset pagination |
| Implementation Phase | P7 |
| Related Requirements | QMDB-DR-016; QMDB-FR-LIV-001; QMDB-FR-LIV-002; QMDB-FR-RSL-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1092 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1093 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1094 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1095 | `result_snapshot_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to result_snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-1096 | `registration_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to registrations. | Domain validation plus schema type/constraint. |
| QMDB-COL-1097 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Appeals. | Domain validation plus schema type/constraint. |
| QMDB-COL-1098 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Appeals. | Domain validation plus schema type/constraint. |
| QMDB-COL-1099 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Appeals. | Domain validation plus schema type/constraint. |
| QMDB-COL-1100 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Appeals. | Domain validation plus schema type/constraint. |

### QMDB-TBL-146 — `appeal_evidence`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-146 |
| Logical Table Name | appeal_evidence |
| Owning Module | Results and Appeals |
| Purpose | Stores appeal evidence as the Results and Appeals module's governed relational record. |
| Aggregate | Appeal |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | External Evidence Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0275: (workspace_id) -> workspaces(id); QMDB-REL-0276: (workspace_id, appeal_id) -> appeals(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0377 (public_id); QMDB-IDX-0378 (workspace_id, created_at, id); QMDB-IDX-0379 (workspace_id, appeal_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Appeal Evidence by relational/public identity; List Appeal Evidence within one Workspace using keyset pagination |
| Implementation Phase | P7 |
| Related Requirements | QMDB-DR-016; QMDB-FR-LIV-001; QMDB-FR-LIV-002; QMDB-FR-RSL-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | OD-058 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1101 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1102 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1103 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1104 | `appeal_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to appeals. | Domain validation plus schema type/constraint. |
| QMDB-COL-1105 | `evidence_type` | Human-readable code | `VARCHAR(64)` | No | None | Yes | Yes | No | Yes | Evidence Type for Appeal Evidence. | Domain validation plus schema type/constraint. |
| QMDB-COL-1106 | `object_key` | Object-storage key | `VARCHAR(1024)` | Yes | None | No | Yes | Yes | No | Private object reference; binary content is never stored in MySQL. | Domain validation plus schema type/constraint. |
| QMDB-COL-1107 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | Yes | No | Yes | Content Hash for Appeal Evidence. | Domain validation plus schema type/constraint. |
| QMDB-COL-1108 | `collected_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | No | Collected At for Appeal Evidence. | Domain validation plus schema type/constraint. |
| QMDB-COL-1109 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Appeal Evidence. | Domain validation plus schema type/constraint. |

### QMDB-TBL-147 — `appeal_assignments`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-147 |
| Logical Table Name | appeal_assignments |
| Owning Module | Results and Appeals |
| Purpose | Stores appeal assignments as the Results and Appeals module's governed relational record. |
| Aggregate | Appeal |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0277: (workspace_id) -> workspaces(id); QMDB-REL-0278: (workspace_id, appeal_id) -> appeals(workspace_id, id); QMDB-REL-0279: (person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0440: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0380 (public_id); QMDB-IDX-0381 (workspace_id, status_code, created_at, id); QMDB-IDX-0382 (workspace_id, appeal_id); QMDB-IDX-0383 (person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Appeal Assignments by relational/public identity; List Appeal Assignments within one Workspace using keyset pagination |
| Implementation Phase | P7 |
| Related Requirements | QMDB-DR-016; QMDB-FR-LIV-001; QMDB-FR-LIV-002; QMDB-FR-RSL-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1110 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1111 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1112 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1113 | `appeal_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to appeals. | Domain validation plus schema type/constraint. |
| QMDB-COL-1114 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-1115 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Appeal Assignments. | Domain validation plus schema type/constraint. |
| QMDB-COL-1116 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Appeal Assignments. | Domain validation plus schema type/constraint. |
| QMDB-COL-1117 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Appeal Assignments. | Domain validation plus schema type/constraint. |
| QMDB-COL-1118 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Appeal Assignments. | Domain validation plus schema type/constraint. |

### QMDB-TBL-148 — `appeal_reviews`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-148 |
| Logical Table Name | appeal_reviews |
| Owning Module | Results and Appeals |
| Purpose | Stores appeal reviews as the Results and Appeals module's governed relational record. |
| Aggregate | Appeal |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0280: (workspace_id) -> workspaces(id); QMDB-REL-0281: (workspace_id, appeal_id) -> appeals(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0384 (public_id); QMDB-IDX-0385 (workspace_id, decision_code, created_at, id); QMDB-IDX-0386 (workspace_id, appeal_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Appeal Reviews by relational/public identity; List Appeal Reviews within one Workspace using keyset pagination |
| Implementation Phase | P7 |
| Related Requirements | QMDB-DR-016; QMDB-FR-LIV-001; QMDB-FR-LIV-002; QMDB-FR-RSL-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1119 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1120 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1121 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1122 | `appeal_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to appeals. | Domain validation plus schema type/constraint. |
| QMDB-COL-1123 | `decision_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Decision Code for Appeal Reviews. | Domain validation plus schema type/constraint. |
| QMDB-COL-1124 | `reason_code` | Human-readable code | `VARCHAR(64)` | No | None | No | Yes | No | No | Reason Code for Appeal Reviews. | Domain validation plus schema type/constraint. |
| QMDB-COL-1125 | `decided_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Decided At for Appeal Reviews. | Domain validation plus schema type/constraint. |
| QMDB-COL-1126 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Appeal Reviews. | Domain validation plus schema type/constraint. |

### QMDB-TBL-149 — `appeal_decisions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-149 |
| Logical Table Name | appeal_decisions |
| Owning Module | Results and Appeals |
| Purpose | Stores appeal decisions as the Results and Appeals module's governed relational record. |
| Aggregate | Appeal |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0282: (workspace_id) -> workspaces(id); QMDB-REL-0283: (workspace_id, appeal_id) -> appeals(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0387 (public_id); QMDB-IDX-0388 (workspace_id, decision_code, created_at, id); QMDB-IDX-0389 (workspace_id, appeal_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Appeal Decisions by relational/public identity; List Appeal Decisions within one Workspace using keyset pagination |
| Implementation Phase | P7 |
| Related Requirements | QMDB-DR-016; QMDB-FR-LIV-001; QMDB-FR-LIV-002; QMDB-FR-RSL-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1127 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1128 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1129 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1130 | `appeal_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to appeals. | Domain validation plus schema type/constraint. |
| QMDB-COL-1131 | `decision_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Decision Code for Appeal Decisions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1132 | `reason_code` | Human-readable code | `VARCHAR(64)` | No | None | No | Yes | No | No | Reason Code for Appeal Decisions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1133 | `decided_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Decided At for Appeal Decisions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1134 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Appeal Decisions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-150 — `appeal_events`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-150 |
| Logical Table Name | appeal_events |
| Owning Module | Results and Appeals |
| Purpose | Stores appeal events as the Results and Appeals module's governed relational record. |
| Aggregate | Appeal |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | HIGH |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0284: (workspace_id) -> workspaces(id); QMDB-REL-0285: (workspace_id, appeal_id) -> appeals(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0390 (workspace_id, occurred_at, id); QMDB-IDX-0391 (workspace_id, appeal_id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Appeal Events by relational/public identity; List Appeal Events within one Workspace using keyset pagination; Read Appeal Events by bounded occurrence range |
| Implementation Phase | P7 |
| Related Requirements | QMDB-DR-016; QMDB-FR-LIV-001; QMDB-FR-LIV-002; QMDB-FR-RSL-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1135 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1136 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1137 | `appeal_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to appeals. | Domain validation plus schema type/constraint. |
| QMDB-COL-1138 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | Yes | Yes | No | Yes | Event Type for Appeal Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1139 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Occurred At for Appeal Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1140 | `payload_json` | JSON document | `JSON` | Yes | None | Yes | Yes | No | No | Versioned bounded event metadata; core record fields remain relational. | Domain validation plus schema type/constraint. |
| QMDB-COL-1141 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | Yes | No | Yes | Content Hash for Appeal Events. | Domain validation plus schema type/constraint. |

### QMDB-TBL-151 — `certificate_templates`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-151 |
| Logical Table Name | certificate_templates |
| Owning Module | Certificates and Trusted Records |
| Purpose | Stores certificate templates as the Certificates and Trusted Records module's governed relational record. |
| Aggregate | Certificate |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Versioned Configuration |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0286: (workspace_id) -> workspaces(id) |
| Check Constraints | QMDB-CST-0451: Effective-until shall be null or later than effective-from. |
| Indexes | QMDB-IDX-0392 (workspace_id, created_at, id); QMDB-IDX-0393 (code) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Certificate Templates by relational/public identity; List Certificate Templates within one Workspace using keyset pagination |
| Implementation Phase | P8 |
| Related Requirements | QMDB-DR-018; QMDB-FR-CER-001; QMDB-FR-CER-002; QMDB-FR-CER-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | OD-065; OD-066 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1142 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1143 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1144 | `code` | Human-readable code | `VARCHAR(64)` | No | None | Yes | Yes | No | Yes | Code for Certificate Templates. | Domain validation plus schema type/constraint. |
| QMDB-COL-1145 | `name` | Name | `VARCHAR(191)` | No | None | No | Yes | No | Yes | Name for Certificate Templates. | Domain validation plus schema type/constraint. |
| QMDB-COL-1146 | `effective_from` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Effective From for Certificate Templates. | Domain validation plus schema type/constraint. |
| QMDB-COL-1147 | `effective_until` | UTC timestamp | `DATETIME(6)` | Yes | None | No | Yes | No | No | Effective Until for Certificate Templates. | Domain validation plus schema type/constraint. |
| QMDB-COL-1148 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Certificate Templates. | Domain validation plus schema type/constraint. |

### QMDB-TBL-152 — `certificate_template_versions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-152 |
| Logical Table Name | certificate_template_versions |
| Owning Module | Certificates and Trusted Records |
| Purpose | Stores certificate template versions as the Certificates and Trusted Records module's governed relational record. |
| Aggregate | Certificate |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Immutable Official Version |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0287: (workspace_id) -> workspaces(id); QMDB-REL-0288: (workspace_id, certificate_template_id) -> certificate_templates(workspace_id, id) |
| Check Constraints | QMDB-CST-0454: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0394 (workspace_id, status_code, created_at, id); QMDB-IDX-0395 (workspace_id, certificate_template_id) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Certificate Template Versions by relational/public identity; List Certificate Template Versions within one Workspace using keyset pagination |
| Implementation Phase | P8 |
| Related Requirements | QMDB-DR-018; QMDB-FR-CER-001; QMDB-FR-CER-002; QMDB-FR-CER-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | OD-065; OD-066 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1149 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1150 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1151 | `certificate_template_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to certificate_templates. | Domain validation plus schema type/constraint. |
| QMDB-COL-1152 | `version_number` | Version number | `INT UNSIGNED` | No | None | Yes | Yes | No | Yes | Version Number for Certificate Template Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1153 | `content_checksum` | Content hash | `BINARY(32)` | No | None | Yes | Yes | No | Yes | Content Checksum for Certificate Template Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1154 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Certificate Template Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1155 | `effective_from` | UTC timestamp | `DATETIME(6)` | Yes | None | No | Yes | No | No | Effective From for Certificate Template Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1156 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Certificate Template Versions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-153 — `certificates`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-153 |
| Logical Table Name | certificates |
| Owning Module | Certificates and Trusted Records |
| Purpose | Stores certificates as the Certificates and Trusted Records module's governed relational record. |
| Aggregate | Certificate |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Supersedable Official Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0289: (workspace_id) -> workspaces(id); QMDB-REL-0290: (workspace_id, result_snapshot_id) -> result_snapshots(workspace_id, id); QMDB-REL-0291: (workspace_id, participant_snapshot_id) -> participant_snapshots(workspace_id, id); QMDB-REL-0292: (workspace_id, certificate_template_version_id) -> certificate_template_versions(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0396 (public_id); QMDB-IDX-0397 (workspace_id, certificate_status, created_at, id); QMDB-IDX-0398 (workspace_id, result_snapshot_id); QMDB-IDX-0399 (workspace_id, participant_snapshot_id); QMDB-IDX-0400 (workspace_id, certificate_template_version_id); QMDB-IDX-0656 (verification_code_hash) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Certificates by relational/public identity; List Certificates within one Workspace using keyset pagination |
| Implementation Phase | P8 |
| Related Requirements | QMDB-DR-018; QMDB-FR-CER-001; QMDB-FR-CER-002; QMDB-FR-CER-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | OD-065; OD-066 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1157 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1158 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1159 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1160 | `result_snapshot_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to result_snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-1161 | `participant_snapshot_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to participant_snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-1162 | `certificate_template_version_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to certificate_template_versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1163 | `serial_number` | Human-readable code | `VARCHAR(128)` | No | None | No | Yes | No | Yes | Serial Number for Certificates. | Domain validation plus schema type/constraint. |
| QMDB-COL-1164 | `verification_code_hash` | Token hash | `BINARY(32)` | No | None | No | Yes | No | Yes | Verification Code Hash for Certificates. | Domain validation plus schema type/constraint. |
| QMDB-COL-1165 | `certificate_status` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Certificate Status for Certificates. | Domain validation plus schema type/constraint. |
| QMDB-COL-1166 | `issued_at` | UTC timestamp | `DATETIME(6)` | Yes | None | No | Yes | No | No | Issued At for Certificates. | Domain validation plus schema type/constraint. |
| QMDB-COL-1167 | `superseded_at` | UTC timestamp | `DATETIME(6)` | Yes | None | No | Yes | No | No | Superseded At for Certificates. | Domain validation plus schema type/constraint. |
| QMDB-COL-1168 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Certificates. | Domain validation plus schema type/constraint. |

### QMDB-TBL-154 — `certificate_signatures`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-154 |
| Logical Table Name | certificate_signatures |
| Owning Module | Certificates and Trusted Records |
| Purpose | Stores certificate signatures as the Certificates and Trusted Records module's governed relational record. |
| Aggregate | Certificate |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Supersedable Official Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0293: (workspace_id) -> workspaces(id); QMDB-REL-0294: (workspace_id, certificate_id) -> certificates(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0401 (public_id); QMDB-IDX-0402 (workspace_id, created_at, id); QMDB-IDX-0403 (workspace_id, certificate_id) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Certificate Signatures by relational/public identity; List Certificate Signatures within one Workspace using keyset pagination |
| Implementation Phase | P8 |
| Related Requirements | QMDB-DR-018; QMDB-FR-CER-001; QMDB-FR-CER-002; QMDB-FR-CER-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | OD-065; OD-066 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1169 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1170 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1171 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1172 | `certificate_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to certificates. | Domain validation plus schema type/constraint. |
| QMDB-COL-1173 | `document_hash` | Content hash | `BINARY(32)` | No | None | No | Yes | No | Yes | Document Hash for Certificate Signatures. | Domain validation plus schema type/constraint. |
| QMDB-COL-1174 | `signature_algorithm` | Human-readable code | `VARCHAR(64)` | No | None | No | Yes | No | No | Signature Algorithm for Certificate Signatures. | Domain validation plus schema type/constraint. |
| QMDB-COL-1175 | `signing_key_id` | Key identifier | `VARCHAR(255)` | No | None | No | Yes | No | No | Signing Key ID for Certificate Signatures. | Domain validation plus schema type/constraint. |
| QMDB-COL-1176 | `signature_value` | Digital signature | `VARBINARY(2048)` | No | None | No | Yes | No | No | Signature Value for Certificate Signatures. | Domain validation plus schema type/constraint. |
| QMDB-COL-1177 | `signed_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Signed At for Certificate Signatures. | Domain validation plus schema type/constraint. |
| QMDB-COL-1178 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Certificate Signatures. | Domain validation plus schema type/constraint. |

### QMDB-TBL-155 — `certificate_revocations`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-155 |
| Logical Table Name | certificate_revocations |
| Owning Module | Certificates and Trusted Records |
| Purpose | Stores certificate revocations as the Certificates and Trusted Records module's governed relational record. |
| Aggregate | Certificate |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Supersedable Official Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0295: (workspace_id) -> workspaces(id); QMDB-REL-0296: (workspace_id, certificate_id) -> certificates(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0404 (public_id); QMDB-IDX-0405 (workspace_id, decision_code, created_at, id); QMDB-IDX-0406 (workspace_id, certificate_id) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Certificate Revocations by relational/public identity; List Certificate Revocations within one Workspace using keyset pagination |
| Implementation Phase | P8 |
| Related Requirements | QMDB-DR-018; QMDB-FR-CER-001; QMDB-FR-CER-002; QMDB-FR-CER-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | OD-065; OD-066 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1179 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1180 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1181 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1182 | `certificate_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to certificates. | Domain validation plus schema type/constraint. |
| QMDB-COL-1183 | `decision_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Decision Code for Certificate Revocations. | Domain validation plus schema type/constraint. |
| QMDB-COL-1184 | `reason_code` | Human-readable code | `VARCHAR(64)` | No | None | No | Yes | No | No | Reason Code for Certificate Revocations. | Domain validation plus schema type/constraint. |
| QMDB-COL-1185 | `decided_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Decided At for Certificate Revocations. | Domain validation plus schema type/constraint. |
| QMDB-COL-1186 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Certificate Revocations. | Domain validation plus schema type/constraint. |

### QMDB-TBL-156 — `certificate_supersession_links`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-156 |
| Logical Table Name | certificate_supersession_links |
| Owning Module | Certificates and Trusted Records |
| Purpose | Stores certificate supersession links as the Certificates and Trusted Records module's governed relational record. |
| Aggregate | Certificate |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Supersedable Official Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0297: (workspace_id) -> workspaces(id); QMDB-REL-0298: (workspace_id, certificate_id) -> certificates(workspace_id, id) |
| Check Constraints | QMDB-CST-0468: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0407 (workspace_id, created_at, id); QMDB-IDX-0408 (workspace_id, certificate_id) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Certificate Supersession Links by relational/public identity; List Certificate Supersession Links within one Workspace using keyset pagination |
| Implementation Phase | P8 |
| Related Requirements | QMDB-DR-018; QMDB-FR-CER-001; QMDB-FR-CER-002; QMDB-FR-CER-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | OD-065; OD-066 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1187 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1188 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1189 | `certificate_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to certificates. | Domain validation plus schema type/constraint. |
| QMDB-COL-1190 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | Yes | No | Yes | Sequence Number for Certificate Supersession Links. | Domain validation plus schema type/constraint. |
| QMDB-COL-1191 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Certificate Supersession Links. | Domain validation plus schema type/constraint. |

### QMDB-TBL-157 — `certificate_verification_events`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-157 |
| Logical Table Name | certificate_verification_events |
| Owning Module | Certificates and Trusted Records |
| Purpose | Stores certificate verification events as the Certificates and Trusted Records module's governed relational record. |
| Aggregate | Certificate |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | HIGH |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0299: (workspace_id) -> workspaces(id); QMDB-REL-0300: (workspace_id, certificate_id) -> certificates(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0409 (workspace_id, occurred_at, id); QMDB-IDX-0410 (workspace_id, certificate_id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Certificate Verification Events by relational/public identity; List Certificate Verification Events within one Workspace using keyset pagination; Read Certificate Verification Events by bounded occurrence range |
| Implementation Phase | P8 |
| Related Requirements | QMDB-DR-018; QMDB-FR-CER-001; QMDB-FR-CER-002; QMDB-FR-CER-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | OD-065; OD-066 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1192 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1193 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1194 | `certificate_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to certificates. | Domain validation plus schema type/constraint. |
| QMDB-COL-1195 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | Yes | Yes | No | Yes | Event Type for Certificate Verification Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1196 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Occurred At for Certificate Verification Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1197 | `payload_json` | JSON document | `JSON` | Yes | None | Yes | Yes | No | No | Versioned bounded event metadata; core record fields remain relational. | Domain validation plus schema type/constraint. |
| QMDB-COL-1198 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | Yes | No | Yes | Content Hash for Certificate Verification Events. | Domain validation plus schema type/constraint. |

### QMDB-TBL-158 — `competition_records`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-158 |
| Logical Table Name | competition_records |
| Owning Module | Certificates and Trusted Records |
| Purpose | Stores competition records as the Certificates and Trusted Records module's governed relational record. |
| Aggregate | Competition Edition |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0301: (workspace_id) -> workspaces(id); QMDB-REL-0302: (workspace_id, result_snapshot_id) -> result_snapshots(workspace_id, id); QMDB-REL-0303: (workspace_id, participant_snapshot_id) -> participant_snapshots(workspace_id, id) |
| Check Constraints | QMDB-CST-0474: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0411 (public_id); QMDB-IDX-0412 (workspace_id, status_code, created_at, id); QMDB-IDX-0413 (workspace_id, result_snapshot_id); QMDB-IDX-0414 (workspace_id, participant_snapshot_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Competition Records by relational/public identity; List Competition Records within one Workspace using keyset pagination |
| Implementation Phase | P8 |
| Related Requirements | QMDB-DR-018; QMDB-FR-CER-001; QMDB-FR-CER-002; QMDB-FR-CER-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1199 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1200 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1201 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1202 | `result_snapshot_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to result_snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-1203 | `participant_snapshot_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to participant_snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-1204 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Competition Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-1205 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Competition Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-1206 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Competition Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-1207 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Competition Records. | Domain validation plus schema type/constraint. |

### QMDB-TBL-159 — `record_provenance`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-159 |
| Logical Table Name | record_provenance |
| Owning Module | Certificates and Trusted Records |
| Purpose | Stores record provenance as the Certificates and Trusted Records module's governed relational record. |
| Aggregate | Record Provenance |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0304: (workspace_id) -> workspaces(id); QMDB-REL-0305: (workspace_id, competition_record_id) -> competition_records(workspace_id, id) |
| Check Constraints | QMDB-CST-0478: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0415 (public_id); QMDB-IDX-0416 (workspace_id, status_code, created_at, id); QMDB-IDX-0417 (workspace_id, competition_record_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Record Provenance by relational/public identity; List Record Provenance within one Workspace using keyset pagination |
| Implementation Phase | P8 |
| Related Requirements | QMDB-DR-018; QMDB-FR-CER-001; QMDB-FR-CER-002; QMDB-FR-CER-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1208 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1209 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1210 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1211 | `competition_record_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to competition_records. | Domain validation plus schema type/constraint. |
| QMDB-COL-1212 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Record Provenance. | Domain validation plus schema type/constraint. |
| QMDB-COL-1213 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Record Provenance. | Domain validation plus schema type/constraint. |
| QMDB-COL-1214 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Record Provenance. | Domain validation plus schema type/constraint. |
| QMDB-COL-1215 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Record Provenance. | Domain validation plus schema type/constraint. |

### QMDB-TBL-160 — `record_evidence`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-160 |
| Logical Table Name | record_evidence |
| Owning Module | Certificates and Trusted Records |
| Purpose | Stores record evidence as the Certificates and Trusted Records module's governed relational record. |
| Aggregate | Record Provenance |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | External Evidence Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0306: (workspace_id) -> workspaces(id); QMDB-REL-0307: (workspace_id, competition_record_id) -> competition_records(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0418 (public_id); QMDB-IDX-0419 (workspace_id, created_at, id); QMDB-IDX-0420 (workspace_id, competition_record_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Record Evidence by relational/public identity; List Record Evidence within one Workspace using keyset pagination |
| Implementation Phase | P8 |
| Related Requirements | QMDB-DR-018; QMDB-FR-CER-001; QMDB-FR-CER-002; QMDB-FR-CER-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1216 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1217 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1218 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1219 | `competition_record_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to competition_records. | Domain validation plus schema type/constraint. |
| QMDB-COL-1220 | `evidence_type` | Human-readable code | `VARCHAR(64)` | No | None | Yes | No | No | Yes | Evidence Type for Record Evidence. | Domain validation plus schema type/constraint. |
| QMDB-COL-1221 | `object_key` | Object-storage key | `VARCHAR(1024)` | Yes | None | No | No | Yes | No | Private object reference; binary content is never stored in MySQL. | Domain validation plus schema type/constraint. |
| QMDB-COL-1222 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | No | No | Yes | Content Hash for Record Evidence. | Domain validation plus schema type/constraint. |
| QMDB-COL-1223 | `collected_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | No | Collected At for Record Evidence. | Domain validation plus schema type/constraint. |
| QMDB-COL-1224 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Record Evidence. | Domain validation plus schema type/constraint. |

### QMDB-TBL-161 — `record_disputes`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-161 |
| Logical Table Name | record_disputes |
| Owning Module | Certificates and Trusted Records |
| Purpose | Stores record disputes as the Certificates and Trusted Records module's governed relational record. |
| Aggregate | Record Provenance |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0308: (workspace_id) -> workspaces(id); QMDB-REL-0309: (workspace_id, competition_record_id) -> competition_records(workspace_id, id) |
| Check Constraints | QMDB-CST-0485: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0421 (public_id); QMDB-IDX-0422 (workspace_id, status_code, created_at, id); QMDB-IDX-0423 (workspace_id, competition_record_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Record Disputes by relational/public identity; List Record Disputes within one Workspace using keyset pagination |
| Implementation Phase | P8 |
| Related Requirements | QMDB-DR-018; QMDB-FR-CER-001; QMDB-FR-CER-002; QMDB-FR-CER-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1225 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1226 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1227 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1228 | `competition_record_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to competition_records. | Domain validation plus schema type/constraint. |
| QMDB-COL-1229 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Record Disputes. | Domain validation plus schema type/constraint. |
| QMDB-COL-1230 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Record Disputes. | Domain validation plus schema type/constraint. |
| QMDB-COL-1231 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Record Disputes. | Domain validation plus schema type/constraint. |
| QMDB-COL-1232 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Record Disputes. | Domain validation plus schema type/constraint. |

### QMDB-TBL-162 — `record_verification_decisions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-162 |
| Logical Table Name | record_verification_decisions |
| Owning Module | Certificates and Trusted Records |
| Purpose | Stores record verification decisions as the Certificates and Trusted Records module's governed relational record. |
| Aggregate | Record Provenance |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0310: (workspace_id) -> workspaces(id); QMDB-REL-0311: (workspace_id, record_dispute_id) -> record_disputes(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0424 (public_id); QMDB-IDX-0425 (workspace_id, decision_code, created_at, id); QMDB-IDX-0426 (workspace_id, record_dispute_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Record Verification Decisions by relational/public identity; List Record Verification Decisions within one Workspace using keyset pagination |
| Implementation Phase | P8 |
| Related Requirements | QMDB-DR-018; QMDB-FR-CER-001; QMDB-FR-CER-002; QMDB-FR-CER-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1233 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1234 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1235 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1236 | `record_dispute_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to record_disputes. | Domain validation plus schema type/constraint. |
| QMDB-COL-1237 | `decision_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Decision Code for Record Verification Decisions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1238 | `reason_code` | Human-readable code | `VARCHAR(64)` | No | None | No | No | No | No | Reason Code for Record Verification Decisions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1239 | `decided_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Decided At for Record Verification Decisions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1240 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Record Verification Decisions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-163 — `legacy_import_batches`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-163 |
| Logical Table Name | legacy_import_batches |
| Owning Module | Certificates and Trusted Records |
| Purpose | Stores legacy import batches as the Certificates and Trusted Records module's governed relational record. |
| Aggregate | Legacy Import Batch |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0312: (workspace_id) -> workspaces(id); QMDB-REL-0313: (workspace_id) -> workspaces(id) |
| Check Constraints | QMDB-CST-0492: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0427 (public_id); QMDB-IDX-0428 (workspace_id, status_code, created_at, id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Legacy Import Batches by relational/public identity; List Legacy Import Batches within one Workspace using keyset pagination |
| Implementation Phase | P8 |
| Related Requirements | QMDB-DR-018; QMDB-FR-CER-001; QMDB-FR-CER-002; QMDB-FR-CER-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1241 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1242 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1243 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1244 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Legacy Import Batches. | Domain validation plus schema type/constraint. |
| QMDB-COL-1245 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Legacy Import Batches. | Domain validation plus schema type/constraint. |
| QMDB-COL-1246 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Legacy Import Batches. | Domain validation plus schema type/constraint. |
| QMDB-COL-1247 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Legacy Import Batches. | Domain validation plus schema type/constraint. |

### QMDB-TBL-164 — `legacy_source_files`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-164 |
| Logical Table Name | legacy_source_files |
| Owning Module | Certificates and Trusted Records |
| Purpose | Stores legacy source files as the Certificates and Trusted Records module's governed relational record. |
| Aggregate | Legacy Import Batch |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | External Evidence Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0314: (workspace_id) -> workspaces(id); QMDB-REL-0315: (workspace_id, legacy_import_batche_id) -> legacy_import_batches(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0429 (public_id); QMDB-IDX-0430 (workspace_id, created_at, id); QMDB-IDX-0431 (workspace_id, legacy_import_batche_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Legacy Source Files by relational/public identity; List Legacy Source Files within one Workspace using keyset pagination |
| Implementation Phase | P8 |
| Related Requirements | QMDB-DR-018; QMDB-FR-CER-001; QMDB-FR-CER-002; QMDB-FR-CER-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1248 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1249 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1250 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1251 | `legacy_import_batche_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to legacy_import_batches. | Domain validation plus schema type/constraint. |
| QMDB-COL-1252 | `evidence_type` | Human-readable code | `VARCHAR(64)` | No | None | Yes | No | No | Yes | Evidence Type for Legacy Source Files. | Domain validation plus schema type/constraint. |
| QMDB-COL-1253 | `object_key` | Object-storage key | `VARCHAR(1024)` | Yes | None | No | No | Yes | No | Private object reference; binary content is never stored in MySQL. | Domain validation plus schema type/constraint. |
| QMDB-COL-1254 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | No | No | Yes | Content Hash for Legacy Source Files. | Domain validation plus schema type/constraint. |
| QMDB-COL-1255 | `collected_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | No | Collected At for Legacy Source Files. | Domain validation plus schema type/constraint. |
| QMDB-COL-1256 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Legacy Source Files. | Domain validation plus schema type/constraint. |

### QMDB-TBL-165 — `legacy_import_rows`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-165 |
| Logical Table Name | legacy_import_rows |
| Owning Module | Certificates and Trusted Records |
| Purpose | Stores legacy import rows as the Certificates and Trusted Records module's governed relational record. |
| Aggregate | Legacy Import Batch |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0316: (workspace_id) -> workspaces(id); QMDB-REL-0317: (workspace_id, legacy_import_batche_id) -> legacy_import_batches(workspace_id, id); QMDB-REL-0318: (workspace_id, legacy_source_file_id) -> legacy_source_files(workspace_id, id) |
| Check Constraints | QMDB-CST-0499: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0432 (public_id); QMDB-IDX-0433 (workspace_id, status_code, created_at, id); QMDB-IDX-0434 (workspace_id, legacy_import_batche_id); QMDB-IDX-0435 (workspace_id, legacy_source_file_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Legacy Import Rows by relational/public identity; List Legacy Import Rows within one Workspace using keyset pagination |
| Implementation Phase | P8 |
| Related Requirements | QMDB-DR-018; QMDB-FR-CER-001; QMDB-FR-CER-002; QMDB-FR-CER-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1257 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1258 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1259 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1260 | `legacy_import_batche_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to legacy_import_batches. | Domain validation plus schema type/constraint. |
| QMDB-COL-1261 | `legacy_source_file_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to legacy_source_files. | Domain validation plus schema type/constraint. |
| QMDB-COL-1262 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Legacy Import Rows. | Domain validation plus schema type/constraint. |
| QMDB-COL-1263 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Legacy Import Rows. | Domain validation plus schema type/constraint. |
| QMDB-COL-1264 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Legacy Import Rows. | Domain validation plus schema type/constraint. |
| QMDB-COL-1265 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Legacy Import Rows. | Domain validation plus schema type/constraint. |

### QMDB-TBL-166 — `legacy_import_validation_issues`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-166 |
| Logical Table Name | legacy_import_validation_issues |
| Owning Module | Certificates and Trusted Records |
| Purpose | Stores legacy import validation issues as the Certificates and Trusted Records module's governed relational record. |
| Aggregate | Legacy Import Batch |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0319: (workspace_id) -> workspaces(id); QMDB-REL-0320: (workspace_id, legacy_import_row_id) -> legacy_import_rows(workspace_id, id) |
| Check Constraints | QMDB-CST-0503: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0436 (public_id); QMDB-IDX-0437 (workspace_id, status_code, created_at, id); QMDB-IDX-0438 (workspace_id, legacy_import_row_id); QMDB-IDX-0658 (workspace_id, legacy_import_row_id, id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Legacy Import Validation Issues by relational/public identity; List Legacy Import Validation Issues within one Workspace using keyset pagination |
| Implementation Phase | P8 |
| Related Requirements | QMDB-DR-018; QMDB-FR-CER-001; QMDB-FR-CER-002; QMDB-FR-CER-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1266 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1267 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1268 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1269 | `legacy_import_row_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to legacy_import_rows. | Domain validation plus schema type/constraint. |
| QMDB-COL-1270 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Legacy Import Validation Issues. | Domain validation plus schema type/constraint. |
| QMDB-COL-1271 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Legacy Import Validation Issues. | Domain validation plus schema type/constraint. |
| QMDB-COL-1272 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Legacy Import Validation Issues. | Domain validation plus schema type/constraint. |
| QMDB-COL-1273 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Legacy Import Validation Issues. | Domain validation plus schema type/constraint. |

### QMDB-TBL-167 — `legacy_import_approvals`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-167 |
| Logical Table Name | legacy_import_approvals |
| Owning Module | Certificates and Trusted Records |
| Purpose | Stores legacy import approvals as the Certificates and Trusted Records module's governed relational record. |
| Aggregate | Legacy Import Batch |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0321: (workspace_id) -> workspaces(id); QMDB-REL-0322: (workspace_id, legacy_import_batche_id) -> legacy_import_batches(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0439 (public_id); QMDB-IDX-0440 (workspace_id, decision_code, created_at, id); QMDB-IDX-0441 (workspace_id, legacy_import_batche_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Legacy Import Approvals by relational/public identity; List Legacy Import Approvals within one Workspace using keyset pagination |
| Implementation Phase | P8 |
| Related Requirements | QMDB-DR-018; QMDB-FR-CER-001; QMDB-FR-CER-002; QMDB-FR-CER-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1274 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1275 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1276 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1277 | `legacy_import_batche_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to legacy_import_batches. | Domain validation plus schema type/constraint. |
| QMDB-COL-1278 | `decision_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Decision Code for Legacy Import Approvals. | Domain validation plus schema type/constraint. |
| QMDB-COL-1279 | `reason_code` | Human-readable code | `VARCHAR(64)` | No | None | No | No | No | No | Reason Code for Legacy Import Approvals. | Domain validation plus schema type/constraint. |
| QMDB-COL-1280 | `decided_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Decided At for Legacy Import Approvals. | Domain validation plus schema type/constraint. |
| QMDB-COL-1281 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Legacy Import Approvals. | Domain validation plus schema type/constraint. |

### QMDB-TBL-168 — `legacy_import_reconciliation`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-168 |
| Logical Table Name | legacy_import_reconciliation |
| Owning Module | Certificates and Trusted Records |
| Purpose | Stores legacy import reconciliation as the Certificates and Trusted Records module's governed relational record. |
| Aggregate | Legacy Import Batch |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0323: (workspace_id) -> workspaces(id); QMDB-REL-0324: (workspace_id, legacy_import_batche_id) -> legacy_import_batches(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0442 (workspace_id, occurred_at, id); QMDB-IDX-0443 (workspace_id, legacy_import_batche_id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Legacy Import Reconciliation by relational/public identity; List Legacy Import Reconciliation within one Workspace using keyset pagination; Read Legacy Import Reconciliation by bounded occurrence range |
| Implementation Phase | P8 |
| Related Requirements | QMDB-DR-018; QMDB-FR-CER-001; QMDB-FR-CER-002; QMDB-FR-CER-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1282 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1283 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1284 | `legacy_import_batche_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to legacy_import_batches. | Domain validation plus schema type/constraint. |
| QMDB-COL-1285 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | Yes | No | No | Yes | Event Type for Legacy Import Reconciliation. | Domain validation plus schema type/constraint. |
| QMDB-COL-1286 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Occurred At for Legacy Import Reconciliation. | Domain validation plus schema type/constraint. |
| QMDB-COL-1287 | `payload_json` | JSON document | `JSON` | Yes | None | Yes | No | No | No | Versioned bounded event metadata; core record fields remain relational. | Domain validation plus schema type/constraint. |
| QMDB-COL-1288 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | No | No | Yes | Content Hash for Legacy Import Reconciliation. | Domain validation plus schema type/constraint. |

### QMDB-TBL-169 — `media_assets`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-169 |
| Logical Table Name | media_assets |
| Owning Module | Media and Evidence |
| Purpose | Stores media assets as the Media and Evidence module's governed relational record. |
| Aggregate | Media Asset |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | MEDIA_METADATA |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0325: (workspace_id) -> workspaces(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0444 (public_id); QMDB-IDX-0445 (workspace_id, media_status, created_at, id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Media Assets by relational/public identity; List Media Assets within one Workspace using keyset pagination |
| Implementation Phase | P9 |
| Related Requirements | QMDB-DR-019; QMDB-FR-MED-001; QMDB-FR-MED-002; QMDB-FR-MED-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1289 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1290 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1291 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1292 | `purpose_code` | Human-readable code | `VARCHAR(64)` | No | None | No | Yes | No | Yes | Purpose Code for Media Assets. | Domain validation plus schema type/constraint. |
| QMDB-COL-1293 | `media_status` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Media Status for Media Assets. | Domain validation plus schema type/constraint. |
| QMDB-COL-1294 | `classification_code` | Human-readable code | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Classification Code for Media Assets. | Domain validation plus schema type/constraint. |
| QMDB-COL-1295 | `evidence_master_hash` | Content hash | `BINARY(32)` | Yes | None | No | Yes | No | Yes | Evidence Master Hash for Media Assets. | Domain validation plus schema type/constraint. |
| QMDB-COL-1296 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Media Assets. | Domain validation plus schema type/constraint. |

### QMDB-TBL-170 — `media_upload_authorizations`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-170 |
| Logical Table Name | media_upload_authorizations |
| Owning Module | Media and Evidence |
| Purpose | Stores media upload authorizations as the Media and Evidence module's governed relational record. |
| Aggregate | Media Asset |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | MEDIA_METADATA |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0326: (workspace_id) -> workspaces(id); QMDB-REL-0327: (workspace_id, media_asset_id) -> media_assets(workspace_id, id) |
| Check Constraints | QMDB-CST-0515: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0446 (public_id); QMDB-IDX-0447 (workspace_id, status_code, created_at, id); QMDB-IDX-0448 (workspace_id, media_asset_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Media Upload Authorizations by relational/public identity; List Media Upload Authorizations within one Workspace using keyset pagination |
| Implementation Phase | P9 |
| Related Requirements | QMDB-DR-019; QMDB-FR-MED-001; QMDB-FR-MED-002; QMDB-FR-MED-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1297 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1298 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1299 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1300 | `media_asset_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to media_assets. | Domain validation plus schema type/constraint. |
| QMDB-COL-1301 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Media Upload Authorizations. | Domain validation plus schema type/constraint. |
| QMDB-COL-1302 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Media Upload Authorizations. | Domain validation plus schema type/constraint. |
| QMDB-COL-1303 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Media Upload Authorizations. | Domain validation plus schema type/constraint. |
| QMDB-COL-1304 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Media Upload Authorizations. | Domain validation plus schema type/constraint. |

### QMDB-TBL-171 — `media_storage_objects`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-171 |
| Logical Table Name | media_storage_objects |
| Owning Module | Media and Evidence |
| Purpose | Stores media storage objects as the Media and Evidence module's governed relational record. |
| Aggregate | Media Asset |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | MEDIA_METADATA |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0328: (workspace_id) -> workspaces(id); QMDB-REL-0329: (workspace_id, media_asset_id) -> media_assets(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0449 (public_id); QMDB-IDX-0450 (workspace_id, created_at, id); QMDB-IDX-0451 (workspace_id, media_asset_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Media Storage Objects by relational/public identity; List Media Storage Objects within one Workspace using keyset pagination |
| Implementation Phase | P9 |
| Related Requirements | QMDB-DR-019; QMDB-FR-MED-001; QMDB-FR-MED-002; QMDB-FR-MED-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1305 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1306 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1307 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1308 | `media_asset_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to media_assets. | Domain validation plus schema type/constraint. |
| QMDB-COL-1309 | `object_key` | Object-storage key | `VARCHAR(1024)` | No | None | No | Yes | No | No | Object Key for Media Storage Objects. | Domain validation plus schema type/constraint. |
| QMDB-COL-1310 | `mime_type` | MIME type | `VARCHAR(255)` | No | None | No | Yes | No | No | Mime Type for Media Storage Objects. | Domain validation plus schema type/constraint. |
| QMDB-COL-1311 | `byte_size` | Counter | `BIGINT UNSIGNED` | No | None | No | Yes | No | No | Byte Size for Media Storage Objects. | Domain validation plus schema type/constraint. |
| QMDB-COL-1312 | `content_hash` | Content hash | `BINARY(32)` | No | None | No | Yes | No | Yes | Content Hash for Media Storage Objects. | Domain validation plus schema type/constraint. |
| QMDB-COL-1313 | `storage_class_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Storage Class Code for Media Storage Objects. | Domain validation plus schema type/constraint. |
| QMDB-COL-1314 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Media Storage Objects. | Domain validation plus schema type/constraint. |

### QMDB-TBL-172 — `media_variants`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-172 |
| Logical Table Name | media_variants |
| Owning Module | Media and Evidence |
| Purpose | Stores media variants as the Media and Evidence module's governed relational record. |
| Aggregate | Media Asset |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | MEDIA_METADATA |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0330: (workspace_id) -> workspaces(id); QMDB-REL-0331: (workspace_id, media_asset_id) -> media_assets(workspace_id, id); QMDB-REL-0332: (workspace_id, media_storage_object_id) -> media_storage_objects(workspace_id, id) |
| Check Constraints | QMDB-CST-0522: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0452 (public_id); QMDB-IDX-0453 (workspace_id, status_code, created_at, id); QMDB-IDX-0454 (workspace_id, media_asset_id); QMDB-IDX-0455 (workspace_id, media_storage_object_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Media Variants by relational/public identity; List Media Variants within one Workspace using keyset pagination |
| Implementation Phase | P9 |
| Related Requirements | QMDB-DR-019; QMDB-FR-MED-001; QMDB-FR-MED-002; QMDB-FR-MED-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1315 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1316 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1317 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1318 | `media_asset_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to media_assets. | Domain validation plus schema type/constraint. |
| QMDB-COL-1319 | `media_storage_object_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to media_storage_objects. | Domain validation plus schema type/constraint. |
| QMDB-COL-1320 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Media Variants. | Domain validation plus schema type/constraint. |
| QMDB-COL-1321 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Media Variants. | Domain validation plus schema type/constraint. |
| QMDB-COL-1322 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Media Variants. | Domain validation plus schema type/constraint. |
| QMDB-COL-1323 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Media Variants. | Domain validation plus schema type/constraint. |

### QMDB-TBL-173 — `media_hashes`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-173 |
| Logical Table Name | media_hashes |
| Owning Module | Media and Evidence |
| Purpose | Stores media hashes as the Media and Evidence module's governed relational record. |
| Aggregate | Media Asset |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | MEDIA_METADATA |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0333: (workspace_id) -> workspaces(id); QMDB-REL-0334: (workspace_id, media_asset_id) -> media_assets(workspace_id, id) |
| Check Constraints | QMDB-CST-0526: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0456 (public_id); QMDB-IDX-0457 (workspace_id, status_code, created_at, id); QMDB-IDX-0458 (workspace_id, media_asset_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Media Hashes by relational/public identity; List Media Hashes within one Workspace using keyset pagination |
| Implementation Phase | P9 |
| Related Requirements | QMDB-DR-019; QMDB-FR-MED-001; QMDB-FR-MED-002; QMDB-FR-MED-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1324 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1325 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1326 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1327 | `media_asset_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to media_assets. | Domain validation plus schema type/constraint. |
| QMDB-COL-1328 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Media Hashes. | Domain validation plus schema type/constraint. |
| QMDB-COL-1329 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Media Hashes. | Domain validation plus schema type/constraint. |
| QMDB-COL-1330 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Media Hashes. | Domain validation plus schema type/constraint. |
| QMDB-COL-1331 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Media Hashes. | Domain validation plus schema type/constraint. |

### QMDB-TBL-174 — `media_consent_links`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-174 |
| Logical Table Name | media_consent_links |
| Owning Module | Media and Evidence |
| Purpose | Stores media consent links as the Media and Evidence module's governed relational record. |
| Aggregate | Media Asset |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | MEDIA_METADATA |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0335: (workspace_id) -> workspaces(id); QMDB-REL-0336: (workspace_id, media_asset_id) -> media_assets(workspace_id, id); QMDB-REL-0337: (consent_record_id) -> consent_records(id) |
| Check Constraints | QMDB-CST-0529: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0459 (workspace_id, created_at, id); QMDB-IDX-0460 (workspace_id, media_asset_id); QMDB-IDX-0461 (consent_record_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Media Consent Links by relational/public identity; List Media Consent Links within one Workspace using keyset pagination |
| Implementation Phase | P9 |
| Related Requirements | QMDB-DR-019; QMDB-FR-MED-001; QMDB-FR-MED-002; QMDB-FR-MED-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1332 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1333 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1334 | `media_asset_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to media_assets. | Domain validation plus schema type/constraint. |
| QMDB-COL-1335 | `consent_record_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to consent_records. | Domain validation plus schema type/constraint. |
| QMDB-COL-1336 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | Yes | No | Yes | Sequence Number for Media Consent Links. | Domain validation plus schema type/constraint. |
| QMDB-COL-1337 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Media Consent Links. | Domain validation plus schema type/constraint. |

### QMDB-TBL-175 — `media_processing_jobs`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-175 |
| Logical Table Name | media_processing_jobs |
| Owning Module | Media and Evidence |
| Purpose | Stores media processing jobs as the Media and Evidence module's governed relational record. |
| Aggregate | Media Asset |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | MEDIA_METADATA |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0338: (workspace_id) -> workspaces(id); QMDB-REL-0339: (workspace_id, media_asset_id) -> media_assets(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0462 (public_id); QMDB-IDX-0463 (workspace_id, status_code, available_at, id); QMDB-IDX-0464 (workspace_id, media_asset_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Media Processing Jobs by relational/public identity; List Media Processing Jobs within one Workspace using keyset pagination |
| Implementation Phase | P9 |
| Related Requirements | QMDB-DR-019; QMDB-FR-MED-001; QMDB-FR-MED-002; QMDB-FR-MED-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1338 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1339 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1340 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1341 | `media_asset_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to media_assets. | Domain validation plus schema type/constraint. |
| QMDB-COL-1342 | `job_type` | Human-readable code | `VARCHAR(64)` | No | None | No | Yes | No | Yes | Job Type for Media Processing Jobs. | Domain validation plus schema type/constraint. |
| QMDB-COL-1343 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Media Processing Jobs. | Domain validation plus schema type/constraint. |
| QMDB-COL-1344 | `available_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | Yes | Available At for Media Processing Jobs. | Domain validation plus schema type/constraint. |
| QMDB-COL-1345 | `attempt_count` | Counter | `INT UNSIGNED` | No | 0 | No | Yes | No | No | Attempt Count for Media Processing Jobs. | Domain validation plus schema type/constraint. |
| QMDB-COL-1346 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Media Processing Jobs. | Domain validation plus schema type/constraint. |

### QMDB-TBL-176 — `media_processing_attempts`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-176 |
| Logical Table Name | media_processing_attempts |
| Owning Module | Media and Evidence |
| Purpose | Stores media processing attempts as the Media and Evidence module's governed relational record. |
| Aggregate | Media Asset |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | APPEND_DOMINANT |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0340: (workspace_id) -> workspaces(id); QMDB-REL-0341: (workspace_id, media_processing_job_id) -> media_processing_jobs(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0465 (workspace_id, occurred_at, id); QMDB-IDX-0466 (workspace_id, media_processing_job_id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Media Processing Attempts by relational/public identity; List Media Processing Attempts within one Workspace using keyset pagination; Read Media Processing Attempts by bounded occurrence range |
| Implementation Phase | P9 |
| Related Requirements | QMDB-DR-019; QMDB-FR-MED-001; QMDB-FR-MED-002; QMDB-FR-MED-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1347 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1348 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1349 | `media_processing_job_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to media_processing_jobs. | Domain validation plus schema type/constraint. |
| QMDB-COL-1350 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | Yes | Yes | No | Yes | Event Type for Media Processing Attempts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1351 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Occurred At for Media Processing Attempts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1352 | `payload_json` | JSON document | `JSON` | Yes | None | Yes | Yes | No | No | Versioned bounded event metadata; core record fields remain relational. | Domain validation plus schema type/constraint. |
| QMDB-COL-1353 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | Yes | No | Yes | Content Hash for Media Processing Attempts. | Domain validation plus schema type/constraint. |

### QMDB-TBL-177 — `media_metadata`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-177 |
| Logical Table Name | media_metadata |
| Owning Module | Media and Evidence |
| Purpose | Stores media metadata as the Media and Evidence module's governed relational record. |
| Aggregate | Media Asset |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | MEDIA_METADATA |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0342: (workspace_id) -> workspaces(id); QMDB-REL-0343: (workspace_id, media_asset_id) -> media_assets(workspace_id, id) |
| Check Constraints | QMDB-CST-0538: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0467 (public_id); QMDB-IDX-0468 (workspace_id, status_code, created_at, id); QMDB-IDX-0469 (workspace_id, media_asset_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Media Metadata by relational/public identity; List Media Metadata within one Workspace using keyset pagination |
| Implementation Phase | P9 |
| Related Requirements | QMDB-DR-019; QMDB-FR-MED-001; QMDB-FR-MED-002; QMDB-FR-MED-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1354 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1355 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1356 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1357 | `media_asset_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to media_assets. | Domain validation plus schema type/constraint. |
| QMDB-COL-1358 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Media Metadata. | Domain validation plus schema type/constraint. |
| QMDB-COL-1359 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Media Metadata. | Domain validation plus schema type/constraint. |
| QMDB-COL-1360 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Media Metadata. | Domain validation plus schema type/constraint. |
| QMDB-COL-1361 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Media Metadata. | Domain validation plus schema type/constraint. |

### QMDB-TBL-178 — `media_publications`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-178 |
| Logical Table Name | media_publications |
| Owning Module | Media and Evidence |
| Purpose | Stores media publications as the Media and Evidence module's governed relational record. |
| Aggregate | Media Asset |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | MEDIA_METADATA |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0344: (workspace_id) -> workspaces(id); QMDB-REL-0345: (workspace_id, media_asset_id) -> media_assets(workspace_id, id) |
| Check Constraints | QMDB-CST-0542: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0470 (public_id); QMDB-IDX-0471 (workspace_id, status_code, created_at, id); QMDB-IDX-0472 (workspace_id, media_asset_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Media Publications by relational/public identity; List Media Publications within one Workspace using keyset pagination |
| Implementation Phase | P9 |
| Related Requirements | QMDB-DR-019; QMDB-FR-MED-001; QMDB-FR-MED-002; QMDB-FR-MED-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1362 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1363 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1364 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1365 | `media_asset_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to media_assets. | Domain validation plus schema type/constraint. |
| QMDB-COL-1366 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Media Publications. | Domain validation plus schema type/constraint. |
| QMDB-COL-1367 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Media Publications. | Domain validation plus schema type/constraint. |
| QMDB-COL-1368 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Media Publications. | Domain validation plus schema type/constraint. |
| QMDB-COL-1369 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Media Publications. | Domain validation plus schema type/constraint. |

### QMDB-TBL-179 — `media_moderation_states`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-179 |
| Logical Table Name | media_moderation_states |
| Owning Module | Media and Evidence |
| Purpose | Stores media moderation states as the Media and Evidence module's governed relational record. |
| Aggregate | Media Asset |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | MEDIA_METADATA |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0346: (workspace_id) -> workspaces(id); QMDB-REL-0347: (workspace_id, media_asset_id) -> media_assets(workspace_id, id) |
| Check Constraints | QMDB-CST-0546: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0473 (public_id); QMDB-IDX-0474 (workspace_id, status_code, created_at, id); QMDB-IDX-0475 (workspace_id, media_asset_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Media Moderation States by relational/public identity; List Media Moderation States within one Workspace using keyset pagination |
| Implementation Phase | P9 |
| Related Requirements | QMDB-DR-019; QMDB-FR-MED-001; QMDB-FR-MED-002; QMDB-FR-MED-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1370 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1371 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1372 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1373 | `media_asset_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to media_assets. | Domain validation plus schema type/constraint. |
| QMDB-COL-1374 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Media Moderation States. | Domain validation plus schema type/constraint. |
| QMDB-COL-1375 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Media Moderation States. | Domain validation plus schema type/constraint. |
| QMDB-COL-1376 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Media Moderation States. | Domain validation plus schema type/constraint. |
| QMDB-COL-1377 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Media Moderation States. | Domain validation plus schema type/constraint. |

### QMDB-TBL-180 — `media_retention_states`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-180 |
| Logical Table Name | media_retention_states |
| Owning Module | Media and Evidence |
| Purpose | Stores media retention states as the Media and Evidence module's governed relational record. |
| Aggregate | Media Asset |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | MEDIA_METADATA |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0348: (workspace_id) -> workspaces(id); QMDB-REL-0349: (workspace_id, media_asset_id) -> media_assets(workspace_id, id) |
| Check Constraints | QMDB-CST-0550: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0476 (public_id); QMDB-IDX-0477 (workspace_id, status_code, created_at, id); QMDB-IDX-0478 (workspace_id, media_asset_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Media Retention States by relational/public identity; List Media Retention States within one Workspace using keyset pagination |
| Implementation Phase | P9 |
| Related Requirements | QMDB-DR-019; QMDB-FR-MED-001; QMDB-FR-MED-002; QMDB-FR-MED-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1378 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1379 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1380 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1381 | `media_asset_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to media_assets. | Domain validation plus schema type/constraint. |
| QMDB-COL-1382 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Media Retention States. | Domain validation plus schema type/constraint. |
| QMDB-COL-1383 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Media Retention States. | Domain validation plus schema type/constraint. |
| QMDB-COL-1384 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Media Retention States. | Domain validation plus schema type/constraint. |
| QMDB-COL-1385 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Media Retention States. | Domain validation plus schema type/constraint. |

### QMDB-TBL-181 — `media_access_events`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-181 |
| Logical Table Name | media_access_events |
| Owning Module | Media and Evidence |
| Purpose | Stores media access events as the Media and Evidence module's governed relational record. |
| Aggregate | Media Asset |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | APPEND_DOMINANT |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0350: (workspace_id) -> workspaces(id); QMDB-REL-0351: (workspace_id, media_asset_id) -> media_assets(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0479 (workspace_id, occurred_at, id); QMDB-IDX-0480 (workspace_id, media_asset_id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Media Access Events by relational/public identity; List Media Access Events within one Workspace using keyset pagination; Read Media Access Events by bounded occurrence range |
| Implementation Phase | P9 |
| Related Requirements | QMDB-DR-019; QMDB-FR-MED-001; QMDB-FR-MED-002; QMDB-FR-MED-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1386 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1387 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1388 | `media_asset_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to media_assets. | Domain validation plus schema type/constraint. |
| QMDB-COL-1389 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | Yes | Yes | No | Yes | Event Type for Media Access Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1390 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Occurred At for Media Access Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1391 | `payload_json` | JSON document | `JSON` | Yes | None | Yes | Yes | No | No | Versioned bounded event metadata; core record fields remain relational. | Domain validation plus schema type/constraint. |
| QMDB-COL-1392 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | Yes | No | Yes | Content Hash for Media Access Events. | Domain validation plus schema type/constraint. |

### QMDB-TBL-182 — `recitation_clips`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-182 |
| Logical Table Name | recitation_clips |
| Owning Module | Recitation Clips and Moderation |
| Purpose | Stores recitation clips as the Recitation Clips and Moderation module's governed relational record. |
| Aggregate | Recitation Clip |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0352: (workspace_id) -> workspaces(id); QMDB-REL-0353: (workspace_id, media_asset_id) -> media_assets(workspace_id, id); QMDB-REL-0354: (person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0556: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0481 (public_id); QMDB-IDX-0482 (workspace_id, status_code, created_at, id); QMDB-IDX-0483 (workspace_id, media_asset_id); QMDB-IDX-0484 (person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Recitation Clips by relational/public identity; List Recitation Clips within one Workspace using keyset pagination |
| Implementation Phase | P10 |
| Related Requirements | QMDB-DR-020; QMDB-FR-SOC-001; QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | OD-053 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1393 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1394 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1395 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1396 | `media_asset_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to media_assets. | Domain validation plus schema type/constraint. |
| QMDB-COL-1397 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-1398 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Recitation Clips. | Domain validation plus schema type/constraint. |
| QMDB-COL-1399 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Recitation Clips. | Domain validation plus schema type/constraint. |
| QMDB-COL-1400 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Recitation Clips. | Domain validation plus schema type/constraint. |
| QMDB-COL-1401 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Recitation Clips. | Domain validation plus schema type/constraint. |

### QMDB-TBL-183 — `social_posts`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-183 |
| Logical Table Name | social_posts |
| Owning Module | Recitation Clips and Moderation |
| Purpose | Stores social posts as the Recitation Clips and Moderation module's governed relational record. |
| Aggregate | Recitation Clip |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0355: (workspace_id) -> workspaces(id); QMDB-REL-0356: (person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0560: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0485 (public_id); QMDB-IDX-0486 (workspace_id, status_code, created_at, id); QMDB-IDX-0487 (person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Social Posts by relational/public identity; List Social Posts within one Workspace using keyset pagination |
| Implementation Phase | P10 |
| Related Requirements | QMDB-DR-020; QMDB-FR-SOC-001; QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | OD-053 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1402 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1403 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1404 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1405 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-1406 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Social Posts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1407 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Social Posts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1408 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Social Posts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1409 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Social Posts. | Domain validation plus schema type/constraint. |

### QMDB-TBL-184 — `post_media`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-184 |
| Logical Table Name | post_media |
| Owning Module | Recitation Clips and Moderation |
| Purpose | Stores post media as the Recitation Clips and Moderation module's governed relational record. |
| Aggregate | Recitation Clip |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0357: (workspace_id) -> workspaces(id); QMDB-REL-0358: (workspace_id, social_post_id) -> social_posts(workspace_id, id); QMDB-REL-0359: (workspace_id, media_asset_id) -> media_assets(workspace_id, id) |
| Check Constraints | QMDB-CST-0563: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0488 (workspace_id, created_at, id); QMDB-IDX-0489 (workspace_id, social_post_id); QMDB-IDX-0490 (workspace_id, media_asset_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Post Media by relational/public identity; List Post Media within one Workspace using keyset pagination |
| Implementation Phase | P10 |
| Related Requirements | QMDB-DR-020; QMDB-FR-SOC-001; QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1410 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1411 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1412 | `social_post_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to social_posts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1413 | `media_asset_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to media_assets. | Domain validation plus schema type/constraint. |
| QMDB-COL-1414 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | Yes | No | Yes | Sequence Number for Post Media. | Domain validation plus schema type/constraint. |
| QMDB-COL-1415 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Post Media. | Domain validation plus schema type/constraint. |

### QMDB-TBL-185 — `post_quran_tags`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-185 |
| Logical Table Name | post_quran_tags |
| Owning Module | Recitation Clips and Moderation |
| Purpose | Stores post quran tags as the Recitation Clips and Moderation module's governed relational record. |
| Aggregate | Recitation Clip |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0360: (workspace_id) -> workspaces(id); QMDB-REL-0361: (workspace_id, social_post_id) -> social_posts(workspace_id, id); QMDB-REL-0362: (passage_range_id) -> passage_ranges(id) |
| Check Constraints | QMDB-CST-0566: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0491 (workspace_id, created_at, id); QMDB-IDX-0492 (workspace_id, social_post_id); QMDB-IDX-0493 (passage_range_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Post Quran Tags by relational/public identity; List Post Quran Tags within one Workspace using keyset pagination |
| Implementation Phase | P10 |
| Related Requirements | QMDB-DR-020; QMDB-FR-SOC-001; QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | OD-054; OD-055; OD-063 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1416 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1417 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1418 | `social_post_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to social_posts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1419 | `passage_range_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to passage_ranges. | Domain validation plus schema type/constraint. |
| QMDB-COL-1420 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | No | No | Yes | Sequence Number for Post Quran Tags. | Domain validation plus schema type/constraint. |
| QMDB-COL-1421 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Post Quran Tags. | Domain validation plus schema type/constraint. |

### QMDB-TBL-186 — `post_competition_links`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-186 |
| Logical Table Name | post_competition_links |
| Owning Module | Recitation Clips and Moderation |
| Purpose | Stores post competition links as the Recitation Clips and Moderation module's governed relational record. |
| Aggregate | Recitation Clip |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0363: (workspace_id) -> workspaces(id); QMDB-REL-0364: (workspace_id, social_post_id) -> social_posts(workspace_id, id); QMDB-REL-0365: (workspace_id, competition_edition_id) -> competition_editions(workspace_id, id) |
| Check Constraints | QMDB-CST-0569: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0494 (workspace_id, created_at, id); QMDB-IDX-0495 (workspace_id, social_post_id); QMDB-IDX-0496 (workspace_id, competition_edition_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Post Competition Links by relational/public identity; List Post Competition Links within one Workspace using keyset pagination |
| Implementation Phase | P10 |
| Related Requirements | QMDB-DR-020; QMDB-FR-SOC-001; QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1422 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1423 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1424 | `social_post_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to social_posts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1425 | `competition_edition_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to competition_editions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1426 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | No | No | Yes | Sequence Number for Post Competition Links. | Domain validation plus schema type/constraint. |
| QMDB-COL-1427 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Post Competition Links. | Domain validation plus schema type/constraint. |

### QMDB-TBL-187 — `follows`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-187 |
| Logical Table Name | follows |
| Owning Module | Recitation Clips and Moderation |
| Purpose | Stores follows as the Recitation Clips and Moderation module's governed relational record. |
| Aggregate | Recitation Clip |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0366: (workspace_id) -> workspaces(id); QMDB-REL-0367: (person_id) -> persons(id); QMDB-REL-0467: (target_person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0572: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0497 (workspace_id, created_at, id); QMDB-IDX-0498 (person_id); QMDB-IDX-0644 (target_person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Follows by relational/public identity; List Follows within one Workspace using keyset pagination |
| Implementation Phase | P10 |
| Related Requirements | QMDB-DR-020; QMDB-FR-SOC-001; QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1428 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1429 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1430 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-1431 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | Yes | No | Yes | Sequence Number for Follows. | Domain validation plus schema type/constraint. |
| QMDB-COL-1432 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Follows. | Domain validation plus schema type/constraint. |
| QMDB-COL-1902 | `target_person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | target_person_id for governed follows behavior. | Domain validation plus schema constraint. |

### QMDB-TBL-188 — `reactions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-188 |
| Logical Table Name | reactions |
| Owning Module | Recitation Clips and Moderation |
| Purpose | Stores reactions as the Recitation Clips and Moderation module's governed relational record. |
| Aggregate | Recitation Clip |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | HIGH |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0368: (workspace_id) -> workspaces(id); QMDB-REL-0369: (workspace_id, social_post_id) -> social_posts(workspace_id, id); QMDB-REL-0370: (person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0575: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0499 (workspace_id, created_at, id); QMDB-IDX-0500 (workspace_id, social_post_id); QMDB-IDX-0501 (person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Reactions by relational/public identity; List Reactions within one Workspace using keyset pagination |
| Implementation Phase | P10 |
| Related Requirements | QMDB-DR-020; QMDB-FR-SOC-001; QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1433 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1434 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1435 | `social_post_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to social_posts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1436 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-1437 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | Yes | No | Yes | Sequence Number for Reactions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1438 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Reactions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-189 — `bookmarks`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-189 |
| Logical Table Name | bookmarks |
| Owning Module | Recitation Clips and Moderation |
| Purpose | Stores bookmarks as the Recitation Clips and Moderation module's governed relational record. |
| Aggregate | Recitation Clip |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0371: (workspace_id) -> workspaces(id); QMDB-REL-0372: (workspace_id, social_post_id) -> social_posts(workspace_id, id); QMDB-REL-0373: (person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0578: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0502 (workspace_id, created_at, id); QMDB-IDX-0503 (workspace_id, social_post_id); QMDB-IDX-0504 (person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Bookmarks by relational/public identity; List Bookmarks within one Workspace using keyset pagination |
| Implementation Phase | P10 |
| Related Requirements | QMDB-DR-020; QMDB-FR-SOC-001; QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1439 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1440 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1441 | `social_post_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to social_posts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1442 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-1443 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | Yes | No | Yes | Sequence Number for Bookmarks. | Domain validation plus schema type/constraint. |
| QMDB-COL-1444 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Bookmarks. | Domain validation plus schema type/constraint. |

### QMDB-TBL-190 — `comments`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-190 |
| Logical Table Name | comments |
| Owning Module | Recitation Clips and Moderation |
| Purpose | Stores comments as the Recitation Clips and Moderation module's governed relational record. |
| Aggregate | Recitation Clip |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | HIGH |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0374: (workspace_id) -> workspaces(id); QMDB-REL-0375: (workspace_id, social_post_id) -> social_posts(workspace_id, id); QMDB-REL-0376: (person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0582: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0505 (public_id); QMDB-IDX-0506 (workspace_id, status_code, created_at, id); QMDB-IDX-0507 (workspace_id, social_post_id); QMDB-IDX-0508 (person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Comments by relational/public identity; List Comments within one Workspace using keyset pagination |
| Implementation Phase | P10 |
| Related Requirements | QMDB-DR-020; QMDB-FR-SOC-001; QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1445 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1446 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1447 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1448 | `social_post_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to social_posts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1449 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-1450 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Comments. | Domain validation plus schema type/constraint. |
| QMDB-COL-1451 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Comments. | Domain validation plus schema type/constraint. |
| QMDB-COL-1452 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Comments. | Domain validation plus schema type/constraint. |
| QMDB-COL-1453 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Comments. | Domain validation plus schema type/constraint. |

### QMDB-TBL-191 — `comment_versions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-191 |
| Logical Table Name | comment_versions |
| Owning Module | Recitation Clips and Moderation |
| Purpose | Stores comment versions as the Recitation Clips and Moderation module's governed relational record. |
| Aggregate | Recitation Clip |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Immutable Official Version |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0377: (workspace_id) -> workspaces(id); QMDB-REL-0378: (workspace_id, comment_id) -> comments(workspace_id, id) |
| Check Constraints | QMDB-CST-0585: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0509 (workspace_id, status_code, created_at, id); QMDB-IDX-0510 (workspace_id, comment_id) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Comment Versions by relational/public identity; List Comment Versions within one Workspace using keyset pagination |
| Implementation Phase | P10 |
| Related Requirements | QMDB-DR-020; QMDB-FR-SOC-001; QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1454 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1455 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1456 | `comment_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to comments. | Domain validation plus schema type/constraint. |
| QMDB-COL-1457 | `version_number` | Version number | `INT UNSIGNED` | No | None | Yes | Yes | No | Yes | Version Number for Comment Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1458 | `content_checksum` | Content hash | `BINARY(32)` | No | None | Yes | Yes | No | Yes | Content Checksum for Comment Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1459 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Comment Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1460 | `effective_from` | UTC timestamp | `DATETIME(6)` | Yes | None | No | Yes | No | No | Effective From for Comment Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1461 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Comment Versions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-192 — `blocks`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-192 |
| Logical Table Name | blocks |
| Owning Module | Recitation Clips and Moderation |
| Purpose | Stores blocks as the Recitation Clips and Moderation module's governed relational record. |
| Aggregate | Recitation Clip |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0379: (workspace_id) -> workspaces(id); QMDB-REL-0380: (person_id) -> persons(id); QMDB-REL-0468: (target_person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0588: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0511 (workspace_id, created_at, id); QMDB-IDX-0512 (person_id); QMDB-IDX-0645 (target_person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Blocks by relational/public identity; List Blocks within one Workspace using keyset pagination |
| Implementation Phase | P10 |
| Related Requirements | QMDB-DR-020; QMDB-FR-SOC-001; QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1462 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1463 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1464 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-1465 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | No | No | Yes | Sequence Number for Blocks. | Domain validation plus schema type/constraint. |
| QMDB-COL-1466 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Blocks. | Domain validation plus schema type/constraint. |
| QMDB-COL-1903 | `target_person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | target_person_id for governed blocks behavior. | Domain validation plus schema constraint. |

### QMDB-TBL-193 — `mutes`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-193 |
| Logical Table Name | mutes |
| Owning Module | Recitation Clips and Moderation |
| Purpose | Stores mutes as the Recitation Clips and Moderation module's governed relational record. |
| Aggregate | Recitation Clip |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0381: (workspace_id) -> workspaces(id); QMDB-REL-0382: (person_id) -> persons(id); QMDB-REL-0469: (target_person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0591: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0513 (workspace_id, created_at, id); QMDB-IDX-0514 (person_id); QMDB-IDX-0646 (target_person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Mutes by relational/public identity; List Mutes within one Workspace using keyset pagination |
| Implementation Phase | P10 |
| Related Requirements | QMDB-DR-020; QMDB-FR-SOC-001; QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1467 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1468 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1469 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-1470 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | No | No | Yes | Sequence Number for Mutes. | Domain validation plus schema type/constraint. |
| QMDB-COL-1471 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Mutes. | Domain validation plus schema type/constraint. |
| QMDB-COL-1904 | `target_person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | target_person_id for governed mutes behavior. | Domain validation plus schema constraint. |

### QMDB-TBL-194 — `content_reports`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-194 |
| Logical Table Name | content_reports |
| Owning Module | Recitation Clips and Moderation |
| Purpose | Stores content reports as the Recitation Clips and Moderation module's governed relational record. |
| Aggregate | Moderation Case |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0383: (workspace_id) -> workspaces(id); QMDB-REL-0384: (workspace_id, social_post_id) -> social_posts(workspace_id, id); QMDB-REL-0385: (person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0595: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0515 (public_id); QMDB-IDX-0516 (workspace_id, status_code, created_at, id); QMDB-IDX-0517 (workspace_id, social_post_id); QMDB-IDX-0518 (person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Content Reports by relational/public identity; List Content Reports within one Workspace using keyset pagination |
| Implementation Phase | P10 |
| Related Requirements | QMDB-DR-020; QMDB-FR-SOC-001; QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1472 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1473 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1474 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1475 | `social_post_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to social_posts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1476 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-1477 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Content Reports. | Domain validation plus schema type/constraint. |
| QMDB-COL-1478 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Content Reports. | Domain validation plus schema type/constraint. |
| QMDB-COL-1479 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Content Reports. | Domain validation plus schema type/constraint. |
| QMDB-COL-1480 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Content Reports. | Domain validation plus schema type/constraint. |

### QMDB-TBL-195 — `report_evidence`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-195 |
| Logical Table Name | report_evidence |
| Owning Module | Recitation Clips and Moderation |
| Purpose | Stores report evidence as the Recitation Clips and Moderation module's governed relational record. |
| Aggregate | Moderation Case |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | External Evidence Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0386: (workspace_id) -> workspaces(id); QMDB-REL-0387: (workspace_id, content_report_id) -> content_reports(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0519 (public_id); QMDB-IDX-0520 (workspace_id, created_at, id); QMDB-IDX-0521 (workspace_id, content_report_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Report Evidence by relational/public identity; List Report Evidence within one Workspace using keyset pagination |
| Implementation Phase | P10 |
| Related Requirements | QMDB-DR-020; QMDB-FR-SOC-001; QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1481 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1482 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1483 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1484 | `content_report_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to content_reports. | Domain validation plus schema type/constraint. |
| QMDB-COL-1485 | `evidence_type` | Human-readable code | `VARCHAR(64)` | No | None | Yes | No | No | Yes | Evidence Type for Report Evidence. | Domain validation plus schema type/constraint. |
| QMDB-COL-1486 | `object_key` | Object-storage key | `VARCHAR(1024)` | Yes | None | No | No | Yes | No | Private object reference; binary content is never stored in MySQL. | Domain validation plus schema type/constraint. |
| QMDB-COL-1487 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | No | No | Yes | Content Hash for Report Evidence. | Domain validation plus schema type/constraint. |
| QMDB-COL-1488 | `collected_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | No | Collected At for Report Evidence. | Domain validation plus schema type/constraint. |
| QMDB-COL-1489 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Report Evidence. | Domain validation plus schema type/constraint. |

### QMDB-TBL-196 — `moderation_cases`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-196 |
| Logical Table Name | moderation_cases |
| Owning Module | Recitation Clips and Moderation |
| Purpose | Stores moderation cases as the Recitation Clips and Moderation module's governed relational record. |
| Aggregate | Moderation Case |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0388: (workspace_id) -> workspaces(id); QMDB-REL-0389: (workspace_id, content_report_id) -> content_reports(workspace_id, id) |
| Check Constraints | QMDB-CST-0602: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0522 (public_id); QMDB-IDX-0523 (workspace_id, status_code, created_at, id); QMDB-IDX-0524 (workspace_id, content_report_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Moderation Cases by relational/public identity; List Moderation Cases within one Workspace using keyset pagination |
| Implementation Phase | P10 |
| Related Requirements | QMDB-DR-020; QMDB-FR-SOC-001; QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1490 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1491 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1492 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1493 | `content_report_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to content_reports. | Domain validation plus schema type/constraint. |
| QMDB-COL-1494 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Moderation Cases. | Domain validation plus schema type/constraint. |
| QMDB-COL-1495 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Moderation Cases. | Domain validation plus schema type/constraint. |
| QMDB-COL-1496 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Moderation Cases. | Domain validation plus schema type/constraint. |
| QMDB-COL-1497 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Moderation Cases. | Domain validation plus schema type/constraint. |

### QMDB-TBL-197 — `moderation_assignments`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-197 |
| Logical Table Name | moderation_assignments |
| Owning Module | Recitation Clips and Moderation |
| Purpose | Stores moderation assignments as the Recitation Clips and Moderation module's governed relational record. |
| Aggregate | Moderation Case |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0390: (workspace_id) -> workspaces(id); QMDB-REL-0391: (workspace_id, moderation_case_id) -> moderation_cases(workspace_id, id); QMDB-REL-0392: (person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0606: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0525 (public_id); QMDB-IDX-0526 (workspace_id, status_code, created_at, id); QMDB-IDX-0527 (workspace_id, moderation_case_id); QMDB-IDX-0528 (person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Moderation Assignments by relational/public identity; List Moderation Assignments within one Workspace using keyset pagination |
| Implementation Phase | P10 |
| Related Requirements | QMDB-DR-020; QMDB-FR-SOC-001; QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1498 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1499 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1500 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1501 | `moderation_case_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to moderation_cases. | Domain validation plus schema type/constraint. |
| QMDB-COL-1502 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-1503 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Moderation Assignments. | Domain validation plus schema type/constraint. |
| QMDB-COL-1504 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Moderation Assignments. | Domain validation plus schema type/constraint. |
| QMDB-COL-1505 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Moderation Assignments. | Domain validation plus schema type/constraint. |
| QMDB-COL-1506 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Moderation Assignments. | Domain validation plus schema type/constraint. |

### QMDB-TBL-198 — `moderation_actions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-198 |
| Logical Table Name | moderation_actions |
| Owning Module | Recitation Clips and Moderation |
| Purpose | Stores moderation actions as the Recitation Clips and Moderation module's governed relational record. |
| Aggregate | Moderation Case |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0393: (workspace_id) -> workspaces(id); QMDB-REL-0394: (workspace_id, moderation_case_id) -> moderation_cases(workspace_id, id) |
| Check Constraints | QMDB-CST-0610: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0529 (public_id); QMDB-IDX-0530 (workspace_id, status_code, created_at, id); QMDB-IDX-0531 (workspace_id, moderation_case_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Moderation Actions by relational/public identity; List Moderation Actions within one Workspace using keyset pagination |
| Implementation Phase | P10 |
| Related Requirements | QMDB-DR-020; QMDB-FR-SOC-001; QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1507 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1508 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1509 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1510 | `moderation_case_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to moderation_cases. | Domain validation plus schema type/constraint. |
| QMDB-COL-1511 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Moderation Actions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1512 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Moderation Actions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1513 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Moderation Actions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1514 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Moderation Actions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-199 — `content_appeals`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-199 |
| Logical Table Name | content_appeals |
| Owning Module | Recitation Clips and Moderation |
| Purpose | Stores content appeals as the Recitation Clips and Moderation module's governed relational record. |
| Aggregate | Moderation Case |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0395: (workspace_id) -> workspaces(id); QMDB-REL-0396: (workspace_id, moderation_action_id) -> moderation_actions(workspace_id, id); QMDB-REL-0397: (person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0614: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0532 (public_id); QMDB-IDX-0533 (workspace_id, status_code, created_at, id); QMDB-IDX-0534 (workspace_id, moderation_action_id); QMDB-IDX-0535 (person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Content Appeals by relational/public identity; List Content Appeals within one Workspace using keyset pagination |
| Implementation Phase | P10 |
| Related Requirements | QMDB-DR-020; QMDB-FR-SOC-001; QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1515 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1516 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1517 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1518 | `moderation_action_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to moderation_actions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1519 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-1520 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Content Appeals. | Domain validation plus schema type/constraint. |
| QMDB-COL-1521 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Content Appeals. | Domain validation plus schema type/constraint. |
| QMDB-COL-1522 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Content Appeals. | Domain validation plus schema type/constraint. |
| QMDB-COL-1523 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Content Appeals. | Domain validation plus schema type/constraint. |

### QMDB-TBL-200 — `content_appeal_decisions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-200 |
| Logical Table Name | content_appeal_decisions |
| Owning Module | Recitation Clips and Moderation |
| Purpose | Stores content appeal decisions as the Recitation Clips and Moderation module's governed relational record. |
| Aggregate | Moderation Case |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0398: (workspace_id) -> workspaces(id); QMDB-REL-0399: (workspace_id, content_appeal_id) -> content_appeals(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0536 (public_id); QMDB-IDX-0537 (workspace_id, decision_code, created_at, id); QMDB-IDX-0538 (workspace_id, content_appeal_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Content Appeal Decisions by relational/public identity; List Content Appeal Decisions within one Workspace using keyset pagination |
| Implementation Phase | P10 |
| Related Requirements | QMDB-DR-020; QMDB-FR-SOC-001; QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1524 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1525 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1526 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1527 | `content_appeal_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to content_appeals. | Domain validation plus schema type/constraint. |
| QMDB-COL-1528 | `decision_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Decision Code for Content Appeal Decisions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1529 | `reason_code` | Human-readable code | `VARCHAR(64)` | No | None | No | Yes | No | No | Reason Code for Content Appeal Decisions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1530 | `decided_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Decided At for Content Appeal Decisions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1531 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Content Appeal Decisions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-201 — `creator_analytics_projections`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-201 |
| Logical Table Name | creator_analytics_projections |
| Owning Module | Recitation Clips and Moderation |
| Purpose | Stores creator analytics projections as a rebuildable read model. |
| Aggregate | Recitation Clip |
| Authoritative or Projection | Projection |
| Tenant Scope | PUBLIC_PROJECTION |
| Expected Volume Class | PROJECTION |
| Lifecycle Category | Rebuildable Projection |
| Data Classification | QMDB-DCL-001 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0400: (person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0619: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0539 (person_id) |
| Versioning Strategy | Track authoritative source version and rebuild deterministically. |
| Deletion or Archival Strategy | May be purged and rebuilt from authoritative source. |
| Audit Strategy | Record rebuild source/version and suppress restricted fields. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Creator Analytics Projections by relational/public identity |
| Implementation Phase | P10 |
| Related Requirements | QMDB-DR-020; QMDB-FR-SOC-001; QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | OD-070 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1532 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1533 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-1534 | `source_version` | Version number | `BIGINT UNSIGNED` | No | None | No | No | No | Yes | Source Version for Creator Analytics Projections. | Domain validation plus schema type/constraint. |
| QMDB-COL-1535 | `projection_payload_json` | JSON document | `JSON` | No | None | No | No | No | No | Bounded rebuildable read-model payload with explicit schema version. | Domain validation plus schema type/constraint. |
| QMDB-COL-1536 | `projected_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | Yes | Projected At for Creator Analytics Projections. | Domain validation plus schema type/constraint. |

### QMDB-TBL-202 — `feed_events`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-202 |
| Logical Table Name | feed_events |
| Owning Module | Recitation Clips and Moderation |
| Purpose | Stores feed events as the Recitation Clips and Moderation module's governed relational record. |
| Aggregate | Recitation Clip |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | APPEND_DOMINANT |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0401: (workspace_id) -> workspaces(id); QMDB-REL-0402: (workspace_id, social_post_id) -> social_posts(workspace_id, id); QMDB-REL-0403: (person_id) -> persons(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0540 (workspace_id, occurred_at, id); QMDB-IDX-0541 (workspace_id, social_post_id); QMDB-IDX-0542 (person_id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Feed Events by relational/public identity; List Feed Events within one Workspace using keyset pagination; Read Feed Events by bounded occurrence range |
| Implementation Phase | P10 |
| Related Requirements | QMDB-DR-020; QMDB-FR-SOC-001; QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1537 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1538 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1539 | `social_post_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to social_posts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1540 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-1541 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | Yes | No | No | Yes | Event Type for Feed Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1542 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Occurred At for Feed Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1543 | `payload_json` | JSON document | `JSON` | Yes | None | Yes | No | No | No | Versioned bounded event metadata; core record fields remain relational. | Domain validation plus schema type/constraint. |
| QMDB-COL-1544 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | No | No | Yes | Content Hash for Feed Events. | Domain validation plus schema type/constraint. |

### QMDB-TBL-203 — `notifications`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-203 |
| Logical Table Name | notifications |
| Owning Module | Notifications |
| Purpose | Stores notifications as the Notifications module's governed relational record. |
| Aggregate | Notification |
| Authoritative or Projection | Authoritative |
| Tenant Scope | SYSTEM_OPERATIONAL |
| Expected Volume Class | MODERATE |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | None |
| Check Constraints | QMDB-CST-0624: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0543 (public_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Notifications by relational/public identity |
| Implementation Phase | P7 |
| Related Requirements | QMDB-DR-021; QMDB-FR-NTF-001; QMDB-FR-NTF-002; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1545 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1546 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1547 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | Yes | None | No | Yes | No | Yes | Nullable only for governed platform-global activity; cross-workspace use requires explicit policy and audit. | Domain validation plus schema type/constraint. |
| QMDB-COL-1548 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Notifications. | Domain validation plus schema type/constraint. |
| QMDB-COL-1549 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Notifications. | Domain validation plus schema type/constraint. |
| QMDB-COL-1550 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Notifications. | Domain validation plus schema type/constraint. |
| QMDB-COL-1551 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Notifications. | Domain validation plus schema type/constraint. |

### QMDB-TBL-204 — `notification_preferences`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-204 |
| Logical Table Name | notification_preferences |
| Owning Module | Notifications |
| Purpose | Stores notification preferences as the Notifications module's governed relational record. |
| Aggregate | Notification |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0404: (workspace_id) -> workspaces(id); QMDB-REL-0405: (user_account_id) -> user_accounts(id) |
| Check Constraints | QMDB-CST-0628: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0544 (public_id); QMDB-IDX-0545 (workspace_id, status_code, created_at, id); QMDB-IDX-0546 (user_account_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Notification Preferences by relational/public identity; List Notification Preferences within one Workspace using keyset pagination |
| Implementation Phase | P7 |
| Related Requirements | QMDB-DR-021; QMDB-FR-NTF-001; QMDB-FR-NTF-002; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1552 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1553 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1554 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1555 | `user_account_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to user_accounts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1556 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Notification Preferences. | Domain validation plus schema type/constraint. |
| QMDB-COL-1557 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Notification Preferences. | Domain validation plus schema type/constraint. |
| QMDB-COL-1558 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Notification Preferences. | Domain validation plus schema type/constraint. |
| QMDB-COL-1559 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Notification Preferences. | Domain validation plus schema type/constraint. |

### QMDB-TBL-205 — `notification_templates`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-205 |
| Logical Table Name | notification_templates |
| Owning Module | Notifications |
| Purpose | Stores notification templates as the Notifications module's governed relational record. |
| Aggregate | Notification |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Versioned Configuration |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | None |
| Check Constraints | QMDB-CST-0630: Effective-until shall be null or later than effective-from. |
| Indexes | QMDB-IDX-0547 (code) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Notification Templates by relational/public identity |
| Implementation Phase | P7 |
| Related Requirements | QMDB-DR-021; QMDB-FR-NTF-001; QMDB-FR-NTF-002; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1560 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1561 | `code` | Human-readable code | `VARCHAR(64)` | No | None | Yes | Yes | No | Yes | Code for Notification Templates. | Domain validation plus schema type/constraint. |
| QMDB-COL-1562 | `name` | Name | `VARCHAR(191)` | No | None | No | Yes | No | Yes | Name for Notification Templates. | Domain validation plus schema type/constraint. |
| QMDB-COL-1563 | `effective_from` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Effective From for Notification Templates. | Domain validation plus schema type/constraint. |
| QMDB-COL-1564 | `effective_until` | UTC timestamp | `DATETIME(6)` | Yes | None | No | Yes | No | No | Effective Until for Notification Templates. | Domain validation plus schema type/constraint. |
| QMDB-COL-1565 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Notification Templates. | Domain validation plus schema type/constraint. |

### QMDB-TBL-206 — `notification_deliveries`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-206 |
| Logical Table Name | notification_deliveries |
| Owning Module | Notifications |
| Purpose | Stores notification deliveries as the Notifications module's governed relational record. |
| Aggregate | Notification |
| Authoritative or Projection | Authoritative |
| Tenant Scope | SYSTEM_OPERATIONAL |
| Expected Volume Class | HIGH |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0406: (notification_id) -> notifications(id) |
| Check Constraints | QMDB-CST-0633: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0548 (public_id); QMDB-IDX-0549 (notification_id); QMDB-IDX-0649 (status_code, created_at, id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Notification Deliveries by relational/public identity |
| Implementation Phase | P7 |
| Related Requirements | QMDB-DR-021; QMDB-FR-NTF-001; QMDB-FR-NTF-002; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1566 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1567 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1568 | `notification_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to notifications. | Domain validation plus schema type/constraint. |
| QMDB-COL-1569 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Notification Deliveries. | Domain validation plus schema type/constraint. |
| QMDB-COL-1570 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Notification Deliveries. | Domain validation plus schema type/constraint. |
| QMDB-COL-1571 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Notification Deliveries. | Domain validation plus schema type/constraint. |
| QMDB-COL-1572 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Notification Deliveries. | Domain validation plus schema type/constraint. |

### QMDB-TBL-207 — `notification_delivery_attempts`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-207 |
| Logical Table Name | notification_delivery_attempts |
| Owning Module | Notifications |
| Purpose | Stores notification delivery attempts as the Notifications module's governed relational record. |
| Aggregate | Notification |
| Authoritative or Projection | Authoritative |
| Tenant Scope | SYSTEM_OPERATIONAL |
| Expected Volume Class | APPEND_DOMINANT |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0407: (notification_delivery_id) -> notification_deliveries(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0550 (notification_delivery_id); QMDB-IDX-0551 (occurred_at, id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Notification Delivery Attempts by relational/public identity; Read Notification Delivery Attempts by bounded occurrence range |
| Implementation Phase | P7 |
| Related Requirements | QMDB-DR-021; QMDB-FR-NTF-001; QMDB-FR-NTF-002; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1573 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1574 | `notification_delivery_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to notification_deliveries. | Domain validation plus schema type/constraint. |
| QMDB-COL-1575 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | Yes | Yes | No | Yes | Event Type for Notification Delivery Attempts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1576 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Occurred At for Notification Delivery Attempts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1577 | `payload_json` | JSON document | `JSON` | Yes | None | Yes | Yes | No | No | Versioned bounded event metadata; core record fields remain relational. | Domain validation plus schema type/constraint. |
| QMDB-COL-1578 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | Yes | No | Yes | Content Hash for Notification Delivery Attempts. | Domain validation plus schema type/constraint. |

### QMDB-TBL-208 — `notification_dead_letters`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-208 |
| Logical Table Name | notification_dead_letters |
| Owning Module | Notifications |
| Purpose | Stores notification dead letters as the Notifications module's governed relational record. |
| Aggregate | Notification |
| Authoritative or Projection | Authoritative |
| Tenant Scope | SYSTEM_OPERATIONAL |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0408: (notification_delivery_id) -> notification_deliveries(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0552 (public_id); QMDB-IDX-0553 (notification_delivery_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Notification Dead Letters by relational/public identity |
| Implementation Phase | P7 |
| Related Requirements | QMDB-DR-021; QMDB-FR-NTF-001; QMDB-FR-NTF-002; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1579 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1580 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1581 | `notification_delivery_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to notification_deliveries. | Domain validation plus schema type/constraint. |
| QMDB-COL-1582 | `job_type` | Human-readable code | `VARCHAR(64)` | No | None | No | Yes | No | Yes | Job Type for Notification Dead Letters. | Domain validation plus schema type/constraint. |
| QMDB-COL-1583 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Notification Dead Letters. | Domain validation plus schema type/constraint. |
| QMDB-COL-1584 | `available_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | Yes | Available At for Notification Dead Letters. | Domain validation plus schema type/constraint. |
| QMDB-COL-1585 | `attempt_count` | Counter | `INT UNSIGNED` | No | 0 | No | Yes | No | No | Attempt Count for Notification Dead Letters. | Domain validation plus schema type/constraint. |
| QMDB-COL-1586 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Notification Dead Letters. | Domain validation plus schema type/constraint. |

### QMDB-TBL-209 — `search_projection_status`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-209 |
| Logical Table Name | search_projection_status |
| Owning Module | Search and Reporting |
| Purpose | Stores search projection status as a rebuildable read model. |
| Aggregate | Projection |
| Authoritative or Projection | Projection |
| Tenant Scope | SYSTEM_OPERATIONAL |
| Expected Volume Class | PROJECTION |
| Lifecycle Category | Rebuildable Projection |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | None |
| Check Constraints | QMDB-CST-0638: Version value shall be positive and monotonic within its lineage. |
| Indexes | None |
| Versioning Strategy | Track authoritative source version and rebuild deterministically. |
| Deletion or Archival Strategy | May be purged and rebuilt from authoritative source. |
| Audit Strategy | Record rebuild source/version and suppress restricted fields. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Search Projection Status by relational/public identity |
| Implementation Phase | P11 |
| Related Requirements | QMDB-DR-022; QMDB-FR-SRH-001; QMDB-FR-SRH-002; QMDB-FR-SRH-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | OD-070 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1587 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1588 | `source_version` | Version number | `BIGINT UNSIGNED` | No | None | No | No | No | Yes | Source Version for Search Projection Status. | Domain validation plus schema type/constraint. |
| QMDB-COL-1589 | `projection_payload_json` | JSON document | `JSON` | No | None | No | No | No | No | Bounded rebuildable read-model payload with explicit schema version. | Domain validation plus schema type/constraint. |
| QMDB-COL-1590 | `projected_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | Yes | Projected At for Search Projection Status. | Domain validation plus schema type/constraint. |

### QMDB-TBL-210 — `public_record_projections`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-210 |
| Logical Table Name | public_record_projections |
| Owning Module | Search and Reporting |
| Purpose | Stores public record projections as a rebuildable read model. |
| Aggregate | Projection |
| Authoritative or Projection | Projection |
| Tenant Scope | PUBLIC_PROJECTION |
| Expected Volume Class | PROJECTION |
| Lifecycle Category | Rebuildable Projection |
| Data Classification | QMDB-DCL-001 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0409: (competition_record_id) -> competition_records(id) |
| Check Constraints | QMDB-CST-0640: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0554 (competition_record_id) |
| Versioning Strategy | Track authoritative source version and rebuild deterministically. |
| Deletion or Archival Strategy | May be purged and rebuilt from authoritative source. |
| Audit Strategy | Record rebuild source/version and suppress restricted fields. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Public Record Projections by relational/public identity |
| Implementation Phase | P11 |
| Related Requirements | QMDB-DR-022; QMDB-FR-SRH-001; QMDB-FR-SRH-002; QMDB-FR-SRH-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | OD-070 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1591 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1592 | `competition_record_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to competition_records. | Domain validation plus schema type/constraint. |
| QMDB-COL-1593 | `source_version` | Version number | `BIGINT UNSIGNED` | No | None | No | No | No | Yes | Source Version for Public Record Projections. | Domain validation plus schema type/constraint. |
| QMDB-COL-1594 | `projection_payload_json` | JSON document | `JSON` | No | None | No | No | No | No | Bounded rebuildable read-model payload with explicit schema version. | Domain validation plus schema type/constraint. |
| QMDB-COL-1595 | `projected_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | Yes | Projected At for Public Record Projections. | Domain validation plus schema type/constraint. |

### QMDB-TBL-211 — `live_scoreboard_projections`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-211 |
| Logical Table Name | live_scoreboard_projections |
| Owning Module | Search and Reporting |
| Purpose | Stores live scoreboard projections as a rebuildable read model. |
| Aggregate | Projection |
| Authoritative or Projection | Projection |
| Tenant Scope | PUBLIC_PROJECTION |
| Expected Volume Class | PROJECTION |
| Lifecycle Category | Rebuildable Projection |
| Data Classification | QMDB-DCL-001 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0410: (result_snapshot_id) -> result_snapshots(id) |
| Check Constraints | QMDB-CST-0642: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0555 (result_snapshot_id) |
| Versioning Strategy | Track authoritative source version and rebuild deterministically. |
| Deletion or Archival Strategy | May be purged and rebuilt from authoritative source. |
| Audit Strategy | Record rebuild source/version and suppress restricted fields. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Live Scoreboard Projections by relational/public identity |
| Implementation Phase | P11 |
| Related Requirements | QMDB-DR-022; QMDB-FR-SRH-001; QMDB-FR-SRH-002; QMDB-FR-SRH-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | OD-070 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1596 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1597 | `result_snapshot_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to result_snapshots. | Domain validation plus schema type/constraint. |
| QMDB-COL-1598 | `source_version` | Version number | `BIGINT UNSIGNED` | No | None | No | No | No | Yes | Source Version for Live Scoreboard Projections. | Domain validation plus schema type/constraint. |
| QMDB-COL-1599 | `projection_payload_json` | JSON document | `JSON` | No | None | No | No | No | No | Bounded rebuildable read-model payload with explicit schema version. | Domain validation plus schema type/constraint. |
| QMDB-COL-1600 | `projected_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | Yes | Projected At for Live Scoreboard Projections. | Domain validation plus schema type/constraint. |

### QMDB-TBL-212 — `reporting_projection_status`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-212 |
| Logical Table Name | reporting_projection_status |
| Owning Module | Search and Reporting |
| Purpose | Stores reporting projection status as a rebuildable read model. |
| Aggregate | Projection |
| Authoritative or Projection | Projection |
| Tenant Scope | SYSTEM_OPERATIONAL |
| Expected Volume Class | PROJECTION |
| Lifecycle Category | Rebuildable Projection |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | None |
| Check Constraints | QMDB-CST-0644: Version value shall be positive and monotonic within its lineage. |
| Indexes | None |
| Versioning Strategy | Track authoritative source version and rebuild deterministically. |
| Deletion or Archival Strategy | May be purged and rebuilt from authoritative source. |
| Audit Strategy | Record rebuild source/version and suppress restricted fields. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Reporting Projection Status by relational/public identity |
| Implementation Phase | P11 |
| Related Requirements | QMDB-DR-022; QMDB-FR-SRH-001; QMDB-FR-SRH-002; QMDB-FR-SRH-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | OD-070 |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1601 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1602 | `source_version` | Version number | `BIGINT UNSIGNED` | No | None | No | No | No | Yes | Source Version for Reporting Projection Status. | Domain validation plus schema type/constraint. |
| QMDB-COL-1603 | `projection_payload_json` | JSON document | `JSON` | No | None | No | No | No | No | Bounded rebuildable read-model payload with explicit schema version. | Domain validation plus schema type/constraint. |
| QMDB-COL-1604 | `projected_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | Yes | Projected At for Reporting Projection Status. | Domain validation plus schema type/constraint. |

### QMDB-TBL-213 — `export_jobs`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-213 |
| Logical Table Name | export_jobs |
| Owning Module | Search and Reporting |
| Purpose | Stores export jobs as the Search and Reporting module's governed relational record. |
| Aggregate | Export |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0411: (workspace_id) -> workspaces(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0556 (public_id); QMDB-IDX-0557 (workspace_id, status_code, available_at, id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Export Jobs by relational/public identity; List Export Jobs within one Workspace using keyset pagination |
| Implementation Phase | P11 |
| Related Requirements | QMDB-DR-022; QMDB-FR-SRH-001; QMDB-FR-SRH-002; QMDB-FR-SRH-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1605 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1606 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1607 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1608 | `job_type` | Human-readable code | `VARCHAR(64)` | No | None | No | No | No | Yes | Job Type for Export Jobs. | Domain validation plus schema type/constraint. |
| QMDB-COL-1609 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Export Jobs. | Domain validation plus schema type/constraint. |
| QMDB-COL-1610 | `available_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | Yes | Available At for Export Jobs. | Domain validation plus schema type/constraint. |
| QMDB-COL-1611 | `attempt_count` | Counter | `INT UNSIGNED` | No | 0 | No | No | No | No | Attempt Count for Export Jobs. | Domain validation plus schema type/constraint. |
| QMDB-COL-1612 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Export Jobs. | Domain validation plus schema type/constraint. |

### QMDB-TBL-214 — `export_artifacts`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-214 |
| Logical Table Name | export_artifacts |
| Owning Module | Search and Reporting |
| Purpose | Stores export artifacts as the Search and Reporting module's governed relational record. |
| Aggregate | Export |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | External Evidence Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0412: (workspace_id) -> workspaces(id); QMDB-REL-0413: (workspace_id, export_job_id) -> export_jobs(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0558 (public_id); QMDB-IDX-0559 (workspace_id, created_at, id); QMDB-IDX-0560 (workspace_id, export_job_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Export Artifacts by relational/public identity; List Export Artifacts within one Workspace using keyset pagination |
| Implementation Phase | P11 |
| Related Requirements | QMDB-DR-022; QMDB-FR-SRH-001; QMDB-FR-SRH-002; QMDB-FR-SRH-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1613 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1614 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1615 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1616 | `export_job_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to export_jobs. | Domain validation plus schema type/constraint. |
| QMDB-COL-1617 | `evidence_type` | Human-readable code | `VARCHAR(64)` | No | None | Yes | No | No | Yes | Evidence Type for Export Artifacts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1618 | `object_key` | Object-storage key | `VARCHAR(1024)` | Yes | None | No | No | Yes | No | Private object reference; binary content is never stored in MySQL. | Domain validation plus schema type/constraint. |
| QMDB-COL-1619 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | No | No | Yes | Content Hash for Export Artifacts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1620 | `collected_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | No | Collected At for Export Artifacts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1621 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Export Artifacts. | Domain validation plus schema type/constraint. |

### QMDB-TBL-215 — `export_access_records`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-215 |
| Logical Table Name | export_access_records |
| Owning Module | Search and Reporting |
| Purpose | Stores export access records as the Search and Reporting module's governed relational record. |
| Aggregate | Export |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0414: (workspace_id) -> workspaces(id); QMDB-REL-0415: (workspace_id, export_artifact_id) -> export_artifacts(workspace_id, id); QMDB-REL-0416: (user_account_id) -> user_accounts(id) |
| Check Constraints | QMDB-CST-0654: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0561 (public_id); QMDB-IDX-0562 (workspace_id, status_code, created_at, id); QMDB-IDX-0563 (workspace_id, export_artifact_id); QMDB-IDX-0564 (user_account_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Export Access Records by relational/public identity; List Export Access Records within one Workspace using keyset pagination |
| Implementation Phase | P11 |
| Related Requirements | QMDB-DR-022; QMDB-FR-SRH-001; QMDB-FR-SRH-002; QMDB-FR-SRH-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1622 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1623 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1624 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1625 | `export_artifact_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to export_artifacts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1626 | `user_account_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to user_accounts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1627 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Export Access Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-1628 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Export Access Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-1629 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Export Access Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-1630 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Export Access Records. | Domain validation plus schema type/constraint. |

### QMDB-TBL-216 — `scheduled_reports`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-216 |
| Logical Table Name | scheduled_reports |
| Owning Module | Search and Reporting |
| Purpose | Stores scheduled reports as the Search and Reporting module's governed relational record. |
| Aggregate | Export |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0417: (workspace_id) -> workspaces(id); QMDB-REL-0418: (workspace_id) -> workspaces(id) |
| Check Constraints | QMDB-CST-0658: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0565 (public_id); QMDB-IDX-0566 (workspace_id, status_code, created_at, id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Scheduled Reports by relational/public identity; List Scheduled Reports within one Workspace using keyset pagination |
| Implementation Phase | P11 |
| Related Requirements | QMDB-DR-022; QMDB-FR-SRH-001; QMDB-FR-SRH-002; QMDB-FR-SRH-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1631 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1632 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1633 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1634 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Scheduled Reports. | Domain validation plus schema type/constraint. |
| QMDB-COL-1635 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Scheduled Reports. | Domain validation plus schema type/constraint. |
| QMDB-COL-1636 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Scheduled Reports. | Domain validation plus schema type/constraint. |
| QMDB-COL-1637 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Scheduled Reports. | Domain validation plus schema type/constraint. |

### QMDB-TBL-217 — `processing_purposes`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-217 |
| Logical Table Name | processing_purposes |
| Owning Module | Privacy and Data Governance |
| Purpose | Stores processing purposes as the Privacy and Data Governance module's governed relational record. |
| Aggregate | Privacy Request |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Versioned Configuration |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | None |
| Check Constraints | QMDB-CST-0660: Effective-until shall be null or later than effective-from. |
| Indexes | QMDB-IDX-0567 (code) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Processing Purposes by relational/public identity |
| Implementation Phase | P12 |
| Related Requirements | QMDB-DR-023; QMDB-FR-PRI-001; QMDB-FR-PRI-002; QMDB-FR-PRI-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1638 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1639 | `code` | Human-readable code | `VARCHAR(64)` | No | None | Yes | No | No | Yes | Code for Processing Purposes. | Domain validation plus schema type/constraint. |
| QMDB-COL-1640 | `name` | Name | `VARCHAR(191)` | No | None | No | No | No | Yes | Name for Processing Purposes. | Domain validation plus schema type/constraint. |
| QMDB-COL-1641 | `effective_from` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Effective From for Processing Purposes. | Domain validation plus schema type/constraint. |
| QMDB-COL-1642 | `effective_until` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Effective Until for Processing Purposes. | Domain validation plus schema type/constraint. |
| QMDB-COL-1643 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Processing Purposes. | Domain validation plus schema type/constraint. |

### QMDB-TBL-218 — `privacy_notice_versions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-218 |
| Logical Table Name | privacy_notice_versions |
| Owning Module | Privacy and Data Governance |
| Purpose | Stores privacy notice versions as the Privacy and Data Governance module's governed relational record. |
| Aggregate | Privacy Request |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_REFERENCE |
| Expected Volume Class | LOW |
| Lifecycle Category | Immutable Official Version |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0419: (processing_purpose_id) -> processing_purposes(id) |
| Check Constraints | QMDB-CST-0662: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0568 (processing_purpose_id) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Privacy Notice Versions by relational/public identity |
| Implementation Phase | P12 |
| Related Requirements | QMDB-DR-023; QMDB-FR-PRI-001; QMDB-FR-PRI-002; QMDB-FR-PRI-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1644 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1645 | `processing_purpose_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to processing_purposes. | Domain validation plus schema type/constraint. |
| QMDB-COL-1646 | `version_number` | Version number | `INT UNSIGNED` | No | None | Yes | Yes | No | Yes | Version Number for Privacy Notice Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1647 | `content_checksum` | Content hash | `BINARY(32)` | No | None | Yes | Yes | No | Yes | Content Checksum for Privacy Notice Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1648 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Privacy Notice Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1649 | `effective_from` | UTC timestamp | `DATETIME(6)` | Yes | None | No | Yes | No | No | Effective From for Privacy Notice Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1650 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Privacy Notice Versions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-219 — `privacy_requests`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-219 |
| Logical Table Name | privacy_requests |
| Owning Module | Privacy and Data Governance |
| Purpose | Stores privacy requests as the Privacy and Data Governance module's governed relational record. |
| Aggregate | Privacy Request |
| Authoritative or Projection | Authoritative |
| Tenant Scope | CROSS_TENANT_OVERSIGHT |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0420: (person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0665: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0569 (public_id); QMDB-IDX-0570 (person_id); QMDB-IDX-0650 (workspace_id, status_code, created_at, id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Privacy Requests by relational/public identity |
| Implementation Phase | P12 |
| Related Requirements | QMDB-DR-023; QMDB-FR-PRI-001; QMDB-FR-PRI-002; QMDB-FR-PRI-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1651 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1652 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1653 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | Yes | None | No | Yes | No | Yes | Nullable only for governed platform-global activity; cross-workspace use requires explicit policy and audit. | Domain validation plus schema type/constraint. |
| QMDB-COL-1654 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-1655 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Privacy Requests. | Domain validation plus schema type/constraint. |
| QMDB-COL-1656 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Privacy Requests. | Domain validation plus schema type/constraint. |
| QMDB-COL-1657 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Privacy Requests. | Domain validation plus schema type/constraint. |
| QMDB-COL-1658 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Privacy Requests. | Domain validation plus schema type/constraint. |

### QMDB-TBL-220 — `privacy_request_events`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-220 |
| Logical Table Name | privacy_request_events |
| Owning Module | Privacy and Data Governance |
| Purpose | Stores privacy request events as the Privacy and Data Governance module's governed relational record. |
| Aggregate | Privacy Request |
| Authoritative or Projection | Authoritative |
| Tenant Scope | CROSS_TENANT_OVERSIGHT |
| Expected Volume Class | HIGH |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0421: (privacy_request_id) -> privacy_requests(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0571 (privacy_request_id); QMDB-IDX-0572 (workspace_id, occurred_at, id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Privacy Request Events by relational/public identity; Read Privacy Request Events by bounded occurrence range |
| Implementation Phase | P12 |
| Related Requirements | QMDB-DR-023; QMDB-FR-PRI-001; QMDB-FR-PRI-002; QMDB-FR-PRI-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1659 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1660 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | Yes | None | No | Yes | No | Yes | Nullable only for governed platform-global activity; cross-workspace use requires explicit policy and audit. | Domain validation plus schema type/constraint. |
| QMDB-COL-1661 | `privacy_request_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to privacy_requests. | Domain validation plus schema type/constraint. |
| QMDB-COL-1662 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | Yes | Yes | No | Yes | Event Type for Privacy Request Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1663 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Occurred At for Privacy Request Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1664 | `payload_json` | JSON document | `JSON` | Yes | None | Yes | Yes | No | No | Versioned bounded event metadata; core record fields remain relational. | Domain validation plus schema type/constraint. |
| QMDB-COL-1665 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | Yes | No | Yes | Content Hash for Privacy Request Events. | Domain validation plus schema type/constraint. |

### QMDB-TBL-221 — `privacy_request_assignments`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-221 |
| Logical Table Name | privacy_request_assignments |
| Owning Module | Privacy and Data Governance |
| Purpose | Stores privacy request assignments as the Privacy and Data Governance module's governed relational record. |
| Aggregate | Privacy Request |
| Authoritative or Projection | Authoritative |
| Tenant Scope | CROSS_TENANT_OVERSIGHT |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0422: (privacy_request_id) -> privacy_requests(id); QMDB-REL-0423: (person_id) -> persons(id) |
| Check Constraints | QMDB-CST-0669: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0573 (public_id); QMDB-IDX-0574 (privacy_request_id); QMDB-IDX-0575 (person_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Privacy Request Assignments by relational/public identity |
| Implementation Phase | P12 |
| Related Requirements | QMDB-DR-023; QMDB-FR-PRI-001; QMDB-FR-PRI-002; QMDB-FR-PRI-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1666 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1667 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1668 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | Yes | None | No | Yes | No | Yes | Nullable only for governed platform-global activity; cross-workspace use requires explicit policy and audit. | Domain validation plus schema type/constraint. |
| QMDB-COL-1669 | `privacy_request_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to privacy_requests. | Domain validation plus schema type/constraint. |
| QMDB-COL-1670 | `person_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to persons. | Domain validation plus schema type/constraint. |
| QMDB-COL-1671 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Privacy Request Assignments. | Domain validation plus schema type/constraint. |
| QMDB-COL-1672 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Privacy Request Assignments. | Domain validation plus schema type/constraint. |
| QMDB-COL-1673 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Privacy Request Assignments. | Domain validation plus schema type/constraint. |
| QMDB-COL-1674 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Privacy Request Assignments. | Domain validation plus schema type/constraint. |

### QMDB-TBL-222 — `retention_policy_records`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-222 |
| Logical Table Name | retention_policy_records |
| Owning Module | Privacy and Data Governance |
| Purpose | Stores retention policy records as the Privacy and Data Governance module's governed relational record. |
| Aggregate | Privacy Request |
| Authoritative or Projection | Authoritative |
| Tenant Scope | GLOBAL_GOVERNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | None |
| Check Constraints | QMDB-CST-0672: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0576 (public_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Retention Policy Records by relational/public identity |
| Implementation Phase | P12 |
| Related Requirements | QMDB-DR-023; QMDB-FR-PRI-001; QMDB-FR-PRI-002; QMDB-FR-PRI-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1675 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1676 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1677 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Retention Policy Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-1678 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Retention Policy Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-1679 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Retention Policy Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-1680 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Retention Policy Records. | Domain validation plus schema type/constraint. |

### QMDB-TBL-223 — `data_holds`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-223 |
| Logical Table Name | data_holds |
| Owning Module | Privacy and Data Governance |
| Purpose | Stores data holds as the Privacy and Data Governance module's governed relational record. |
| Aggregate | Privacy Request |
| Authoritative or Projection | Authoritative |
| Tenant Scope | CROSS_TENANT_OVERSIGHT |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0424: (privacy_request_id) -> privacy_requests(id) |
| Check Constraints | QMDB-CST-0675: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0577 (public_id); QMDB-IDX-0578 (privacy_request_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Data Holds by relational/public identity |
| Implementation Phase | P12 |
| Related Requirements | QMDB-DR-023; QMDB-FR-PRI-001; QMDB-FR-PRI-002; QMDB-FR-PRI-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1681 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1682 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1683 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | Yes | None | No | No | No | Yes | Nullable only for governed platform-global activity; cross-workspace use requires explicit policy and audit. | Domain validation plus schema type/constraint. |
| QMDB-COL-1684 | `privacy_request_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to privacy_requests. | Domain validation plus schema type/constraint. |
| QMDB-COL-1685 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Data Holds. | Domain validation plus schema type/constraint. |
| QMDB-COL-1686 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Data Holds. | Domain validation plus schema type/constraint. |
| QMDB-COL-1687 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Data Holds. | Domain validation plus schema type/constraint. |
| QMDB-COL-1688 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Data Holds. | Domain validation plus schema type/constraint. |

### QMDB-TBL-224 — `anonymization_events`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-224 |
| Logical Table Name | anonymization_events |
| Owning Module | Privacy and Data Governance |
| Purpose | Stores anonymization events as the Privacy and Data Governance module's governed relational record. |
| Aggregate | Privacy Request |
| Authoritative or Projection | Authoritative |
| Tenant Scope | CROSS_TENANT_OVERSIGHT |
| Expected Volume Class | HIGH |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0425: (privacy_request_id) -> privacy_requests(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0579 (privacy_request_id); QMDB-IDX-0580 (workspace_id, occurred_at, id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Anonymization Events by relational/public identity; Read Anonymization Events by bounded occurrence range |
| Implementation Phase | P12 |
| Related Requirements | QMDB-DR-023; QMDB-FR-PRI-001; QMDB-FR-PRI-002; QMDB-FR-PRI-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1689 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1690 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | Yes | None | No | No | No | Yes | Nullable only for governed platform-global activity; cross-workspace use requires explicit policy and audit. | Domain validation plus schema type/constraint. |
| QMDB-COL-1691 | `privacy_request_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to privacy_requests. | Domain validation plus schema type/constraint. |
| QMDB-COL-1692 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | Yes | No | No | Yes | Event Type for Anonymization Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1693 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Occurred At for Anonymization Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1694 | `payload_json` | JSON document | `JSON` | Yes | None | Yes | No | No | No | Versioned bounded event metadata; core record fields remain relational. | Domain validation plus schema type/constraint. |
| QMDB-COL-1695 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | No | No | Yes | Content Hash for Anonymization Events. | Domain validation plus schema type/constraint. |

### QMDB-TBL-225 — `data_export_deliveries`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-225 |
| Logical Table Name | data_export_deliveries |
| Owning Module | Privacy and Data Governance |
| Purpose | Stores data export deliveries as the Privacy and Data Governance module's governed relational record. |
| Aggregate | Privacy Request |
| Authoritative or Projection | Authoritative |
| Tenant Scope | CROSS_TENANT_OVERSIGHT |
| Expected Volume Class | HIGH |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | QMDB-REL-0426: (privacy_request_id) -> privacy_requests(id); QMDB-REL-0427: (export_artifact_id) -> export_artifacts(id) |
| Check Constraints | QMDB-CST-0679: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0581 (public_id); QMDB-IDX-0582 (privacy_request_id); QMDB-IDX-0583 (export_artifact_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Data Export Deliveries by relational/public identity |
| Implementation Phase | P12 |
| Related Requirements | QMDB-DR-023; QMDB-FR-PRI-001; QMDB-FR-PRI-002; QMDB-FR-PRI-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1696 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1697 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1698 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | Yes | None | No | No | No | Yes | Nullable only for governed platform-global activity; cross-workspace use requires explicit policy and audit. | Domain validation plus schema type/constraint. |
| QMDB-COL-1699 | `privacy_request_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to privacy_requests. | Domain validation plus schema type/constraint. |
| QMDB-COL-1700 | `export_artifact_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to export_artifacts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1701 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Data Export Deliveries. | Domain validation plus schema type/constraint. |
| QMDB-COL-1702 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Data Export Deliveries. | Domain validation plus schema type/constraint. |
| QMDB-COL-1703 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Data Export Deliveries. | Domain validation plus schema type/constraint. |
| QMDB-COL-1704 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Data Export Deliveries. | Domain validation plus schema type/constraint. |

### QMDB-TBL-226 — `audit_events`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-226 |
| Logical Table Name | audit_events |
| Owning Module | Audit and Integrations |
| Purpose | Stores audit events as the Audit and Integrations module's governed relational record. |
| Aggregate | Audit Chain |
| Authoritative or Projection | Authoritative |
| Tenant Scope | SYSTEM_OPERATIONAL |
| Expected Volume Class | APPEND_DOMINANT |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | event_public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | None |
| Check Constraints | None |
| Indexes | QMDB-IDX-0584 (workspace_id, occurred_at, id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Audit Events by relational/public identity; Read Audit Events by bounded occurrence range |
| Implementation Phase | P12 |
| Related Requirements | QMDB-DR-025; QMDB-FR-AUD-001; QMDB-FR-AUD-002; QMDB-FR-INT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1705 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1706 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | Yes | None | No | Yes | No | Yes | Nullable only for governed platform-global activity; cross-workspace use requires explicit policy and audit. | Domain validation plus schema type/constraint. |
| QMDB-COL-1707 | `event_public_id` | Public identifier | `BINARY(16)` | No | None | No | Yes | No | No | Event Public ID for Audit Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1708 | `actor_type` | Status | `VARCHAR(32)` | No | None | No | Yes | No | No | Actor Type for Audit Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1709 | `actor_public_id` | Public identifier | `BINARY(16)` | Yes | None | No | Yes | No | No | Actor Public ID for Audit Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1710 | `action_code` | Human-readable code | `VARCHAR(96)` | No | None | No | Yes | No | Yes | Action Code for Audit Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1711 | `resource_type` | Human-readable code | `VARCHAR(64)` | No | None | No | Yes | No | No | Resource Type for Audit Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1712 | `resource_public_id` | Public identifier | `BINARY(16)` | Yes | None | No | Yes | No | No | Resource Public ID for Audit Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1713 | `canonical_payload_hash` | Content hash | `BINARY(32)` | No | None | No | Yes | No | Yes | Canonical Payload Hash for Audit Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1714 | `previous_event_hash` | Content hash | `BINARY(32)` | Yes | None | No | Yes | No | Yes | Previous Event Hash for Audit Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1715 | `event_hash` | Content hash | `BINARY(32)` | No | None | No | Yes | No | Yes | Event Hash for Audit Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1716 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | Yes | Occurred At for Audit Events. | Domain validation plus schema type/constraint. |

### QMDB-TBL-227 — `audit_checkpoints`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-227 |
| Logical Table Name | audit_checkpoints |
| Owning Module | Audit and Integrations |
| Purpose | Stores audit checkpoints as the Audit and Integrations module's governed relational record. |
| Aggregate | Audit Chain |
| Authoritative or Projection | Authoritative |
| Tenant Scope | SYSTEM_OPERATIONAL |
| Expected Volume Class | LOW |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0428: (audit_event_id) -> audit_events(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0585 (audit_event_id); QMDB-IDX-0586 (occurred_at, id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Audit Checkpoints by relational/public identity; Read Audit Checkpoints by bounded occurrence range |
| Implementation Phase | P12 |
| Related Requirements | QMDB-DR-025; QMDB-FR-AUD-001; QMDB-FR-AUD-002; QMDB-FR-INT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1717 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1718 | `audit_event_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to audit_events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1719 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | Yes | Yes | No | Yes | Event Type for Audit Checkpoints. | Domain validation plus schema type/constraint. |
| QMDB-COL-1720 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Occurred At for Audit Checkpoints. | Domain validation plus schema type/constraint. |
| QMDB-COL-1721 | `payload_json` | JSON document | `JSON` | Yes | None | Yes | Yes | No | No | Versioned bounded event metadata; core record fields remain relational. | Domain validation plus schema type/constraint. |
| QMDB-COL-1722 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | Yes | No | Yes | Content Hash for Audit Checkpoints. | Domain validation plus schema type/constraint. |

### QMDB-TBL-228 — `audit_verification_runs`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-228 |
| Logical Table Name | audit_verification_runs |
| Owning Module | Audit and Integrations |
| Purpose | Stores audit verification runs as the Audit and Integrations module's governed relational record. |
| Aggregate | Audit Chain |
| Authoritative or Projection | Authoritative |
| Tenant Scope | SYSTEM_OPERATIONAL |
| Expected Volume Class | LOW |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0429: (audit_checkpoint_id) -> audit_checkpoints(id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0587 (audit_checkpoint_id); QMDB-IDX-0588 (occurred_at, id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Audit Verification Runs by relational/public identity; Read Audit Verification Runs by bounded occurrence range |
| Implementation Phase | P12 |
| Related Requirements | QMDB-DR-025; QMDB-FR-AUD-001; QMDB-FR-AUD-002; QMDB-FR-INT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1723 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1724 | `audit_checkpoint_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to audit_checkpoints. | Domain validation plus schema type/constraint. |
| QMDB-COL-1725 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | Yes | Yes | No | Yes | Event Type for Audit Verification Runs. | Domain validation plus schema type/constraint. |
| QMDB-COL-1726 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Occurred At for Audit Verification Runs. | Domain validation plus schema type/constraint. |
| QMDB-COL-1727 | `payload_json` | JSON document | `JSON` | Yes | None | Yes | Yes | No | No | Versioned bounded event metadata; core record fields remain relational. | Domain validation plus schema type/constraint. |
| QMDB-COL-1728 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | Yes | No | Yes | Content Hash for Audit Verification Runs. | Domain validation plus schema type/constraint. |

### QMDB-TBL-229 — `outbox_events`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-229 |
| Logical Table Name | outbox_events |
| Owning Module | Audit and Integrations |
| Purpose | Stores outbox events as the Audit and Integrations module's governed relational record. |
| Aggregate | Outbox Event |
| Authoritative or Projection | Authoritative |
| Tenant Scope | SYSTEM_OPERATIONAL |
| Expected Volume Class | HIGH |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | event_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | None |
| Check Constraints | QMDB-CST-0684: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0589 (workspace_id, occurred_at, id); QMDB-IDX-0590 (available_at, claimed_at, id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Outbox Events by relational/public identity; Read Outbox Events by bounded occurrence range |
| Implementation Phase | P12 |
| Related Requirements | QMDB-DR-025; QMDB-FR-AUD-001; QMDB-FR-AUD-002; QMDB-FR-INT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1729 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1730 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | Yes | None | No | No | No | Yes | Nullable only for governed platform-global activity; cross-workspace use requires explicit policy and audit. | Domain validation plus schema type/constraint. |
| QMDB-COL-1731 | `event_id` | Public identifier | `BINARY(16)` | No | None | No | No | No | No | Event ID for Outbox Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1732 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | No | No | No | No | Event Type for Outbox Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1733 | `payload_schema_version` | Version number | `INT UNSIGNED` | No | None | No | No | No | No | Payload Schema Version for Outbox Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1734 | `payload_json` | JSON document | `JSON` | No | None | No | No | No | No | Payload Json for Outbox Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1735 | `aggregate_version` | Version number | `INT UNSIGNED` | No | None | No | No | No | No | Aggregate Version for Outbox Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1736 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | Yes | Occurred At for Outbox Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1737 | `available_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | Yes | Available At for Outbox Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1738 | `claimed_at` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | Yes | Claimed At for Outbox Events. | Domain validation plus schema type/constraint. |

### QMDB-TBL-230 — `idempotency_records`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-230 |
| Logical Table Name | idempotency_records |
| Owning Module | Audit and Integrations |
| Purpose | Stores idempotency records as the Audit and Integrations module's governed relational record. |
| Aggregate | Outbox Event |
| Authoritative or Projection | Authoritative |
| Tenant Scope | SYSTEM_OPERATIONAL |
| Expected Volume Class | HIGH |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | None |
| Check Constraints | None |
| Indexes | QMDB-IDX-0591 (public_id); QMDB-IDX-0592 (workspace_id, key_hash) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Idempotency Records by relational/public identity |
| Implementation Phase | P12 |
| Related Requirements | QMDB-DR-025; QMDB-FR-AUD-001; QMDB-FR-AUD-002; QMDB-FR-INT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1739 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1740 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1741 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | Yes | None | No | No | No | Yes | Nullable only for governed platform-global activity; cross-workspace use requires explicit policy and audit. | Domain validation plus schema type/constraint. |
| QMDB-COL-1742 | `key_hash` | Token hash | `BINARY(32)` | No | None | No | No | No | Yes | Key Hash for Idempotency Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-1743 | `request_hash` | Content hash | `BINARY(32)` | No | None | No | No | No | Yes | Request Hash for Idempotency Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-1744 | `resource_type` | Human-readable code | `VARCHAR(64)` | No | None | No | No | No | No | Resource Type for Idempotency Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-1745 | `resource_public_id` | Public identifier | `BINARY(16)` | Yes | None | No | No | No | No | Resource Public ID for Idempotency Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-1746 | `expires_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Expires At for Idempotency Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-1747 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Idempotency Records. | Domain validation plus schema type/constraint. |

### QMDB-TBL-231 — `api_clients`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-231 |
| Logical Table Name | api_clients |
| Owning Module | Audit and Integrations |
| Purpose | Stores api clients as the Audit and Integrations module's governed relational record. |
| Aggregate | API Client |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0430: (workspace_id) -> workspaces(id) |
| Check Constraints | QMDB-CST-0692: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0593 (public_id); QMDB-IDX-0594 (workspace_id, status_code, created_at, id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup API Clients by relational/public identity; List API Clients within one Workspace using keyset pagination |
| Implementation Phase | P12 |
| Related Requirements | QMDB-DR-025; QMDB-FR-AUD-001; QMDB-FR-AUD-002; QMDB-FR-INT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1748 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1749 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1750 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1751 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for API Clients. | Domain validation plus schema type/constraint. |
| QMDB-COL-1752 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for API Clients. | Domain validation plus schema type/constraint. |
| QMDB-COL-1753 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for API Clients. | Domain validation plus schema type/constraint. |
| QMDB-COL-1754 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for API Clients. | Domain validation plus schema type/constraint. |

### QMDB-TBL-232 — `api_client_scopes`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-232 |
| Logical Table Name | api_client_scopes |
| Owning Module | Audit and Integrations |
| Purpose | Stores api client scopes as the Audit and Integrations module's governed relational record. |
| Aggregate | API Client |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0431: (workspace_id) -> workspaces(id); QMDB-REL-0432: (workspace_id, api_client_id) -> api_clients(workspace_id, id) |
| Check Constraints | QMDB-CST-0695: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0595 (workspace_id, created_at, id); QMDB-IDX-0596 (workspace_id, api_client_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup API Client Scopes by relational/public identity; List API Client Scopes within one Workspace using keyset pagination |
| Implementation Phase | P12 |
| Related Requirements | QMDB-DR-025; QMDB-FR-AUD-001; QMDB-FR-AUD-002; QMDB-FR-INT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1755 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1756 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1757 | `api_client_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to api_clients. | Domain validation plus schema type/constraint. |
| QMDB-COL-1758 | `sequence_number` | Sequence number | `INT UNSIGNED` | Yes | None | No | No | No | Yes | Sequence Number for API Client Scopes. | Domain validation plus schema type/constraint. |
| QMDB-COL-1759 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for API Client Scopes. | Domain validation plus schema type/constraint. |

### QMDB-TBL-233 — `api_credentials`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-233 |
| Logical Table Name | api_credentials |
| Owning Module | Audit and Integrations |
| Purpose | Stores api credentials as the Audit and Integrations module's governed relational record. |
| Aggregate | API Client |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Ephemeral Security Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | credential_public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0433: (workspace_id) -> workspaces(id); QMDB-REL-0434: (workspace_id, api_client_id) -> api_clients(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0597 (workspace_id, created_at, id); QMDB-IDX-0598 (workspace_id, api_client_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Expire and delete under approved security retention policy. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup API Credentials by relational/public identity; List API Credentials within one Workspace using keyset pagination |
| Implementation Phase | P12 |
| Related Requirements | QMDB-DR-025; QMDB-FR-AUD-001; QMDB-FR-AUD-002; QMDB-FR-INT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1760 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1761 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1762 | `api_client_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to api_clients. | Domain validation plus schema type/constraint. |
| QMDB-COL-1763 | `credential_public_id` | Public identifier | `BINARY(16)` | No | None | No | Yes | No | No | Credential Public ID for API Credentials. | Domain validation plus schema type/constraint. |
| QMDB-COL-1764 | `secret_hash` | Token hash | `BINARY(32)` | No | None | No | Yes | No | Yes | Secret Hash for API Credentials. | Domain validation plus schema type/constraint. |
| QMDB-COL-1765 | `key_reference` | Key identifier | `VARCHAR(255)` | Yes | None | No | Yes | No | No | Key Reference for API Credentials. | Domain validation plus schema type/constraint. |
| QMDB-COL-1766 | `expires_at` | UTC timestamp | `DATETIME(6)` | Yes | None | No | Yes | No | No | Expires At for API Credentials. | Domain validation plus schema type/constraint. |
| QMDB-COL-1767 | `revoked_at` | UTC timestamp | `DATETIME(6)` | Yes | None | No | Yes | No | No | Revoked At for API Credentials. | Domain validation plus schema type/constraint. |
| QMDB-COL-1768 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for API Credentials. | Domain validation plus schema type/constraint. |

### QMDB-TBL-234 — `webhook_subscriptions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-234 |
| Logical Table Name | webhook_subscriptions |
| Owning Module | Audit and Integrations |
| Purpose | Stores webhook subscriptions as the Audit and Integrations module's governed relational record. |
| Aggregate | Webhook Subscription |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0435: (workspace_id) -> workspaces(id); QMDB-REL-0436: (workspace_id, api_client_id) -> api_clients(workspace_id, id) |
| Check Constraints | QMDB-CST-0701: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0599 (public_id); QMDB-IDX-0600 (workspace_id, status_code, created_at, id); QMDB-IDX-0601 (workspace_id, api_client_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Webhook Subscriptions by relational/public identity; List Webhook Subscriptions within one Workspace using keyset pagination |
| Implementation Phase | P12 |
| Related Requirements | QMDB-DR-025; QMDB-FR-AUD-001; QMDB-FR-AUD-002; QMDB-FR-INT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1769 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1770 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1771 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1772 | `api_client_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to api_clients. | Domain validation plus schema type/constraint. |
| QMDB-COL-1773 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Webhook Subscriptions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1774 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Webhook Subscriptions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1775 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Webhook Subscriptions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1776 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Webhook Subscriptions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-235 — `webhook_deliveries`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-235 |
| Logical Table Name | webhook_deliveries |
| Owning Module | Audit and Integrations |
| Purpose | Stores webhook deliveries as the Audit and Integrations module's governed relational record. |
| Aggregate | Webhook Subscription |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | HIGH |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0437: (workspace_id) -> workspaces(id); QMDB-REL-0438: (workspace_id, webhook_subscription_id) -> webhook_subscriptions(workspace_id, id); QMDB-REL-0439: (outbox_event_id) -> outbox_events(id) |
| Check Constraints | QMDB-CST-0705: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0602 (public_id); QMDB-IDX-0603 (workspace_id, status_code, created_at, id); QMDB-IDX-0604 (workspace_id, webhook_subscription_id); QMDB-IDX-0605 (outbox_event_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Webhook Deliveries by relational/public identity; List Webhook Deliveries within one Workspace using keyset pagination |
| Implementation Phase | P12 |
| Related Requirements | QMDB-DR-025; QMDB-FR-AUD-001; QMDB-FR-AUD-002; QMDB-FR-INT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1777 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1778 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1779 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1780 | `webhook_subscription_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to webhook_subscriptions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1781 | `outbox_event_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to outbox_events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1782 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Webhook Deliveries. | Domain validation plus schema type/constraint. |
| QMDB-COL-1783 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Webhook Deliveries. | Domain validation plus schema type/constraint. |
| QMDB-COL-1784 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Webhook Deliveries. | Domain validation plus schema type/constraint. |
| QMDB-COL-1785 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Webhook Deliveries. | Domain validation plus schema type/constraint. |

### QMDB-TBL-236 — `webhook_delivery_attempts`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-236 |
| Logical Table Name | webhook_delivery_attempts |
| Owning Module | Audit and Integrations |
| Purpose | Stores webhook delivery attempts as the Audit and Integrations module's governed relational record. |
| Aggregate | Webhook Subscription |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | APPEND_DOMINANT |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0440: (workspace_id) -> workspaces(id); QMDB-REL-0441: (workspace_id, webhook_delivery_id) -> webhook_deliveries(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0606 (workspace_id, occurred_at, id); QMDB-IDX-0607 (workspace_id, webhook_delivery_id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Webhook Delivery Attempts by relational/public identity; List Webhook Delivery Attempts within one Workspace using keyset pagination; Read Webhook Delivery Attempts by bounded occurrence range |
| Implementation Phase | P12 |
| Related Requirements | QMDB-DR-025; QMDB-FR-AUD-001; QMDB-FR-AUD-002; QMDB-FR-INT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1786 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1787 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1788 | `webhook_delivery_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to webhook_deliveries. | Domain validation plus schema type/constraint. |
| QMDB-COL-1789 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | Yes | No | No | Yes | Event Type for Webhook Delivery Attempts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1790 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Occurred At for Webhook Delivery Attempts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1791 | `payload_json` | JSON document | `JSON` | Yes | None | Yes | No | No | No | Versioned bounded event metadata; core record fields remain relational. | Domain validation plus schema type/constraint. |
| QMDB-COL-1792 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | No | No | Yes | Content Hash for Webhook Delivery Attempts. | Domain validation plus schema type/constraint. |

### QMDB-TBL-237 — `integration_events`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-237 |
| Logical Table Name | integration_events |
| Owning Module | Audit and Integrations |
| Purpose | Stores integration events as the Audit and Integrations module's governed relational record. |
| Aggregate | API Client |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | APPEND_DOMINANT |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0442: (workspace_id) -> workspaces(id); QMDB-REL-0443: (workspace_id, api_client_id) -> api_clients(workspace_id, id) |
| Check Constraints | None |
| Indexes | QMDB-IDX-0608 (workspace_id, occurred_at, id); QMDB-IDX-0609 (workspace_id, api_client_id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Integration Events by relational/public identity; List Integration Events within one Workspace using keyset pagination; Read Integration Events by bounded occurrence range |
| Implementation Phase | P12 |
| Related Requirements | QMDB-DR-025; QMDB-FR-AUD-001; QMDB-FR-AUD-002; QMDB-FR-INT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1793 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1794 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1795 | `api_client_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to api_clients. | Domain validation plus schema type/constraint. |
| QMDB-COL-1796 | `event_type` | Human-readable code | `VARCHAR(96)` | No | None | Yes | No | No | Yes | Event Type for Integration Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1797 | `occurred_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Occurred At for Integration Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1798 | `payload_json` | JSON document | `JSON` | Yes | None | Yes | No | No | No | Versioned bounded event metadata; core record fields remain relational. | Domain validation plus schema type/constraint. |
| QMDB-COL-1799 | `content_hash` | Content hash | `BINARY(32)` | No | None | Yes | No | No | Yes | Content Hash for Integration Events. | Domain validation plus schema type/constraint. |

### QMDB-TBL-238 — `external_provider_references`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-238 |
| Logical Table Name | external_provider_references |
| Owning Module | Audit and Integrations |
| Purpose | Stores external provider references as the Audit and Integrations module's governed relational record. |
| Aggregate | API Client |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0444: (workspace_id) -> workspaces(id); QMDB-REL-0445: (workspace_id, api_client_id) -> api_clients(workspace_id, id) |
| Check Constraints | QMDB-CST-0713: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0610 (public_id); QMDB-IDX-0611 (workspace_id, status_code, created_at, id); QMDB-IDX-0612 (workspace_id, api_client_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup External Provider References by relational/public identity; List External Provider References within one Workspace using keyset pagination |
| Implementation Phase | P12 |
| Related Requirements | QMDB-DR-025; QMDB-FR-AUD-001; QMDB-FR-AUD-002; QMDB-FR-INT-001; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1800 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1801 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1802 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1803 | `api_client_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to api_clients. | Domain validation plus schema type/constraint. |
| QMDB-COL-1804 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for External Provider References. | Domain validation plus schema type/constraint. |
| QMDB-COL-1805 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for External Provider References. | Domain validation plus schema type/constraint. |
| QMDB-COL-1806 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for External Provider References. | Domain validation plus schema type/constraint. |
| QMDB-COL-1807 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for External Provider References. | Domain validation plus schema type/constraint. |

### QMDB-TBL-239 — `feature_flags`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-239 |
| Logical Table Name | feature_flags |
| Owning Module | Platform and Offline Operations |
| Purpose | Stores feature flags as the Platform and Offline Operations module's governed relational record. |
| Aggregate | Platform Configuration |
| Authoritative or Projection | Authoritative |
| Tenant Scope | SYSTEM_OPERATIONAL |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | None |
| Check Constraints | QMDB-CST-0716: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0613 (public_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Feature Flags by relational/public identity |
| Implementation Phase | P13 |
| Related Requirements | QMDB-DR-027; QMDB-FR-OPS-001; QMDB-FR-OPS-002; QMDB-FR-OPS-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1808 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1809 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1810 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Feature Flags. | Domain validation plus schema type/constraint. |
| QMDB-COL-1811 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Feature Flags. | Domain validation plus schema type/constraint. |
| QMDB-COL-1812 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Feature Flags. | Domain validation plus schema type/constraint. |
| QMDB-COL-1813 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Feature Flags. | Domain validation plus schema type/constraint. |

### QMDB-TBL-240 — `feature_flag_versions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-240 |
| Logical Table Name | feature_flag_versions |
| Owning Module | Platform and Offline Operations |
| Purpose | Stores feature flag versions as the Platform and Offline Operations module's governed relational record. |
| Aggregate | Platform Configuration |
| Authoritative or Projection | Authoritative |
| Tenant Scope | SYSTEM_OPERATIONAL |
| Expected Volume Class | LOW |
| Lifecycle Category | Immutable Official Version |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | QMDB-REL-0446: (feature_flag_id) -> feature_flags(id) |
| Check Constraints | QMDB-CST-0718: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0614 (feature_flag_id) |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Feature Flag Versions by relational/public identity |
| Implementation Phase | P13 |
| Related Requirements | QMDB-DR-027; QMDB-FR-OPS-001; QMDB-FR-OPS-002; QMDB-FR-OPS-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1814 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1815 | `feature_flag_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to feature_flags. | Domain validation plus schema type/constraint. |
| QMDB-COL-1816 | `version_number` | Version number | `INT UNSIGNED` | No | None | Yes | No | No | Yes | Version Number for Feature Flag Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1817 | `content_checksum` | Content hash | `BINARY(32)` | No | None | Yes | No | No | Yes | Content Checksum for Feature Flag Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1818 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Feature Flag Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1819 | `effective_from` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Effective From for Feature Flag Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1820 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Feature Flag Versions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-241 — `configuration_versions`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-241 |
| Logical Table Name | configuration_versions |
| Owning Module | Platform and Offline Operations |
| Purpose | Stores configuration versions as the Platform and Offline Operations module's governed relational record. |
| Aggregate | Platform Configuration |
| Authoritative or Projection | Authoritative |
| Tenant Scope | SYSTEM_OPERATIONAL |
| Expected Volume Class | LOW |
| Lifecycle Category | Immutable Official Version |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | None required; referenced through owning aggregate or projection |
| Candidate Keys | (id) — Primary relational identity |
| Foreign Keys | None |
| Check Constraints | QMDB-CST-0720: Version value shall be positive and monotonic within its lineage. |
| Indexes | None |
| Versioning Strategy | Append a new immutable version; never overwrite historical facts. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Configuration Versions by relational/public identity |
| Implementation Phase | P13 |
| Related Requirements | QMDB-DR-027; QMDB-FR-OPS-001; QMDB-FR-OPS-002; QMDB-FR-OPS-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1821 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1822 | `version_number` | Version number | `INT UNSIGNED` | No | None | Yes | No | No | Yes | Version Number for Configuration Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1823 | `content_checksum` | Content hash | `BINARY(32)` | No | None | Yes | No | No | Yes | Content Checksum for Configuration Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1824 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Configuration Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1825 | `effective_from` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Effective From for Configuration Versions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1826 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Configuration Versions. | Domain validation plus schema type/constraint. |

### QMDB-TBL-242 — `operational_announcements`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-242 |
| Logical Table Name | operational_announcements |
| Owning Module | Platform and Offline Operations |
| Purpose | Stores operational announcements as the Platform and Offline Operations module's governed relational record. |
| Aggregate | Platform Configuration |
| Authoritative or Projection | Authoritative |
| Tenant Scope | SYSTEM_OPERATIONAL |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | None |
| Check Constraints | QMDB-CST-0723: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0615 (public_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Operational Announcements by relational/public identity |
| Implementation Phase | P13 |
| Related Requirements | QMDB-DR-027; QMDB-FR-OPS-001; QMDB-FR-OPS-002; QMDB-FR-OPS-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1827 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1828 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1829 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Operational Announcements. | Domain validation plus schema type/constraint. |
| QMDB-COL-1830 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Operational Announcements. | Domain validation plus schema type/constraint. |
| QMDB-COL-1831 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Operational Announcements. | Domain validation plus schema type/constraint. |
| QMDB-COL-1832 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Operational Announcements. | Domain validation plus schema type/constraint. |

### QMDB-TBL-243 — `background_job_ledger`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-243 |
| Logical Table Name | background_job_ledger |
| Owning Module | Platform and Offline Operations |
| Purpose | Stores background job ledger as the Platform and Offline Operations module's governed relational record. |
| Aggregate | Platform Configuration |
| Authoritative or Projection | Authoritative |
| Tenant Scope | SYSTEM_OPERATIONAL |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | None |
| Check Constraints | QMDB-CST-0726: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0616 (public_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Background Job Ledger by relational/public identity |
| Implementation Phase | P13 |
| Related Requirements | QMDB-DR-027; QMDB-FR-OPS-001; QMDB-FR-OPS-002; QMDB-FR-OPS-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1833 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1834 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1835 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Background Job Ledger. | Domain validation plus schema type/constraint. |
| QMDB-COL-1836 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Background Job Ledger. | Domain validation plus schema type/constraint. |
| QMDB-COL-1837 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Background Job Ledger. | Domain validation plus schema type/constraint. |
| QMDB-COL-1838 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Background Job Ledger. | Domain validation plus schema type/constraint. |

### QMDB-TBL-244 — `dead_letter_records`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-244 |
| Logical Table Name | dead_letter_records |
| Owning Module | Platform and Offline Operations |
| Purpose | Stores dead letter records as the Platform and Offline Operations module's governed relational record. |
| Aggregate | Platform Configuration |
| Authoritative or Projection | Authoritative |
| Tenant Scope | SYSTEM_OPERATIONAL |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity |
| Foreign Keys | None |
| Check Constraints | None |
| Indexes | QMDB-IDX-0617 (public_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Dead Letter Records by relational/public identity |
| Implementation Phase | P13 |
| Related Requirements | QMDB-DR-027; QMDB-FR-OPS-001; QMDB-FR-OPS-002; QMDB-FR-OPS-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1839 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1840 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1841 | `job_type` | Human-readable code | `VARCHAR(64)` | No | None | No | No | No | Yes | Job Type for Dead Letter Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-1842 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Dead Letter Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-1843 | `available_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | Yes | Available At for Dead Letter Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-1844 | `attempt_count` | Counter | `INT UNSIGNED` | No | 0 | No | No | No | No | Attempt Count for Dead Letter Records. | Domain validation plus schema type/constraint. |
| QMDB-COL-1845 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Dead Letter Records. | Domain validation plus schema type/constraint. |

### QMDB-TBL-245 — `offline_assignment_packages`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-245 |
| Logical Table Name | offline_assignment_packages |
| Owning Module | Platform and Offline Operations |
| Purpose | Stores offline assignment packages as the Platform and Offline Operations module's governed relational record. |
| Aggregate | Offline Synchronization |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0447: (workspace_id) -> workspaces(id); QMDB-REL-0448: (workspace_id, venue_edge_node_registration_id) -> venue_edge_node_registrations(workspace_id, id); QMDB-REL-0449: (workspace_id, competition_edition_id) -> competition_editions(workspace_id, id) |
| Check Constraints | QMDB-CST-0732: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0618 (public_id); QMDB-IDX-0619 (workspace_id, status_code, created_at, id); QMDB-IDX-0620 (workspace_id, venue_edge_node_registration_id); QMDB-IDX-0621 (workspace_id, competition_edition_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Offline Assignment Packages by relational/public identity; List Offline Assignment Packages within one Workspace using keyset pagination |
| Implementation Phase | P13 |
| Related Requirements | QMDB-DR-027; QMDB-FR-OPS-001; QMDB-FR-OPS-002; QMDB-FR-OPS-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1846 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1847 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1848 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1849 | `venue_edge_node_registration_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to venue_edge_node_registrations. | Domain validation plus schema type/constraint. |
| QMDB-COL-1850 | `competition_edition_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to competition_editions. | Domain validation plus schema type/constraint. |
| QMDB-COL-1851 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Offline Assignment Packages. | Domain validation plus schema type/constraint. |
| QMDB-COL-1852 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Offline Assignment Packages. | Domain validation plus schema type/constraint. |
| QMDB-COL-1853 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Offline Assignment Packages. | Domain validation plus schema type/constraint. |
| QMDB-COL-1854 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Offline Assignment Packages. | Domain validation plus schema type/constraint. |

### QMDB-TBL-246 — `offline_submission_events`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-246 |
| Logical Table Name | offline_submission_events |
| Owning Module | Platform and Offline Operations |
| Purpose | Stores offline submission events as the Platform and Offline Operations module's governed relational record. |
| Aggregate | Offline Synchronization |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | APPEND_DOMINANT |
| Lifecycle Category | Append-Only Event |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | event_public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0450: (workspace_id) -> workspaces(id); QMDB-REL-0451: (workspace_id, offline_assignment_package_id) -> offline_assignment_packages(workspace_id, id) |
| Check Constraints | QMDB-CST-0735: Version value shall be positive and monotonic within its lineage.; QMDB-CST-0736: Sequence shall be null only when unordered; otherwise positive. |
| Indexes | QMDB-IDX-0622 (workspace_id, created_at, id); QMDB-IDX-0623 (workspace_id, offline_assignment_package_id) |
| Versioning Strategy | Append only; corrections are linked events. |
| Deletion or Archival Strategy | No normal hard deletion; revoke, supersede, archive or governed anonymization only. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Offline Submission Events by relational/public identity; List Offline Submission Events within one Workspace using keyset pagination; Read Offline Submission Events by bounded occurrence range |
| Implementation Phase | P13 |
| Related Requirements | QMDB-DR-027; QMDB-FR-OPS-001; QMDB-FR-OPS-002; QMDB-FR-OPS-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1855 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1856 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1857 | `offline_assignment_package_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to offline_assignment_packages. | Domain validation plus schema type/constraint. |
| QMDB-COL-1858 | `event_public_id` | Public identifier | `BINARY(16)` | No | None | No | No | No | No | Event Public ID for Offline Submission Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1859 | `device_sequence` | Sequence number | `BIGINT UNSIGNED` | No | None | No | No | No | No | Device Sequence for Offline Submission Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1860 | `expected_record_version` | Version number | `INT UNSIGNED` | No | None | No | No | No | No | Expected Record Version for Offline Submission Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1861 | `idempotency_hash` | Token hash | `BINARY(32)` | No | None | No | No | No | Yes | Idempotency Hash for Offline Submission Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1862 | `event_payload_json` | JSON document | `JSON` | No | None | No | No | No | No | Event Payload Json for Offline Submission Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1863 | `captured_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Captured At for Offline Submission Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1864 | `received_at` | UTC timestamp | `DATETIME(6)` | Yes | None | No | No | No | No | Received At for Offline Submission Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1865 | `conflict_status` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Conflict Status for Offline Submission Events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1866 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Offline Submission Events. | Domain validation plus schema type/constraint. |

### QMDB-TBL-247 — `synchronization_batches`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-247 |
| Logical Table Name | synchronization_batches |
| Owning Module | Platform and Offline Operations |
| Purpose | Stores synchronization batches as the Platform and Offline Operations module's governed relational record. |
| Aggregate | Offline Synchronization |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0452: (workspace_id) -> workspaces(id); QMDB-REL-0453: (workspace_id, offline_assignment_package_id) -> offline_assignment_packages(workspace_id, id) |
| Check Constraints | QMDB-CST-0740: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0624 (public_id); QMDB-IDX-0625 (workspace_id, status_code, created_at, id); QMDB-IDX-0626 (workspace_id, offline_assignment_package_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Synchronization Batches by relational/public identity; List Synchronization Batches within one Workspace using keyset pagination |
| Implementation Phase | P13 |
| Related Requirements | QMDB-DR-027; QMDB-FR-OPS-001; QMDB-FR-OPS-002; QMDB-FR-OPS-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1867 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1868 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1869 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1870 | `offline_assignment_package_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to offline_assignment_packages. | Domain validation plus schema type/constraint. |
| QMDB-COL-1871 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Synchronization Batches. | Domain validation plus schema type/constraint. |
| QMDB-COL-1872 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Synchronization Batches. | Domain validation plus schema type/constraint. |
| QMDB-COL-1873 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Synchronization Batches. | Domain validation plus schema type/constraint. |
| QMDB-COL-1874 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Synchronization Batches. | Domain validation plus schema type/constraint. |

### QMDB-TBL-248 — `synchronization_conflicts`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-248 |
| Logical Table Name | synchronization_conflicts |
| Owning Module | Platform and Offline Operations |
| Purpose | Stores synchronization conflicts as the Platform and Offline Operations module's governed relational record. |
| Aggregate | Offline Synchronization |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-004 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0454: (workspace_id) -> workspaces(id); QMDB-REL-0455: (workspace_id, synchronization_batche_id) -> synchronization_batches(workspace_id, id); QMDB-REL-0456: (workspace_id, offline_submission_event_id) -> offline_submission_events(workspace_id, id) |
| Check Constraints | QMDB-CST-0744: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0627 (public_id); QMDB-IDX-0628 (workspace_id, status_code, created_at, id); QMDB-IDX-0629 (workspace_id, synchronization_batche_id); QMDB-IDX-0630 (workspace_id, offline_submission_event_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest plus selected field encryption under OD-058; keys outside MySQL. |
| Principal Query Patterns | Lookup Synchronization Conflicts by relational/public identity; List Synchronization Conflicts within one Workspace using keyset pagination |
| Implementation Phase | P13 |
| Related Requirements | QMDB-DR-027; QMDB-FR-OPS-001; QMDB-FR-OPS-002; QMDB-FR-OPS-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1875 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1876 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1877 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1878 | `synchronization_batche_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to synchronization_batches. | Domain validation plus schema type/constraint. |
| QMDB-COL-1879 | `offline_submission_event_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to offline_submission_events. | Domain validation plus schema type/constraint. |
| QMDB-COL-1880 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Synchronization Conflicts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1881 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Synchronization Conflicts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1882 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Synchronization Conflicts. | Domain validation plus schema type/constraint. |
| QMDB-COL-1883 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Synchronization Conflicts. | Domain validation plus schema type/constraint. |

### QMDB-TBL-249 — `venue_edge_node_registrations`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-249 |
| Logical Table Name | venue_edge_node_registrations |
| Owning Module | Platform and Offline Operations |
| Purpose | Stores venue edge node registrations as the Platform and Offline Operations module's governed relational record. |
| Aggregate | Administrative Area |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_OWNED |
| Expected Volume Class | MODERATE |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-003 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0457: (workspace_id) -> workspaces(id); QMDB-REL-0458: (venue_id) -> venues(id) |
| Check Constraints | QMDB-CST-0748: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0631 (public_id); QMDB-IDX-0632 (workspace_id, status_code, created_at, id); QMDB-IDX-0633 (venue_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | TLS/at-rest and encrypted exports/backups. |
| Principal Query Patterns | Lookup Venue Edge Node Registrations by relational/public identity; List Venue Edge Node Registrations within one Workspace using keyset pagination |
| Implementation Phase | P13 |
| Related Requirements | QMDB-DR-027; QMDB-FR-OPS-001; QMDB-FR-OPS-002; QMDB-FR-OPS-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1884 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1885 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | Yes | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1886 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1887 | `venue_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | Yes | No | Yes | Relational reference to venues. | Domain validation plus schema type/constraint. |
| QMDB-COL-1888 | `status_code` | Status | `VARCHAR(32)` | No | None | No | Yes | No | Yes | Status Code for Venue Edge Node Registrations. | Domain validation plus schema type/constraint. |
| QMDB-COL-1889 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | Yes | No | Yes | Version for Venue Edge Node Registrations. | Domain validation plus schema type/constraint. |
| QMDB-COL-1890 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | Yes | No | Yes | Created At for Venue Edge Node Registrations. | Domain validation plus schema type/constraint. |
| QMDB-COL-1891 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | Yes | No | No | Updated At for Venue Edge Node Registrations. | Domain validation plus schema type/constraint. |

### QMDB-TBL-250 — `venue_reconciliation_reports`

| Table property | Controlled value |
| --- | --- |
| Table ID | QMDB-TBL-250 |
| Logical Table Name | venue_reconciliation_reports |
| Owning Module | Platform and Offline Operations |
| Purpose | Stores venue reconciliation reports as the Platform and Offline Operations module's governed relational record. |
| Aggregate | Administrative Area |
| Authoritative or Projection | Authoritative |
| Tenant Scope | TENANT_CHILD |
| Expected Volume Class | LOW |
| Lifecycle Category | Mutable Current Record |
| Data Classification | QMDB-DCL-002 |
| Primary Key | id (BIGINT UNSIGNED, internal only) |
| Public Identifier | public_id; UUIDv7/BINARY(16) recommendation pending OD-047 |
| Candidate Keys | (id) — Primary relational identity; (public_id) — Opaque external identity; (workspace_id, id) — Tenant-safe parent candidate key |
| Foreign Keys | QMDB-REL-0459: (workspace_id) -> workspaces(id); QMDB-REL-0460: (workspace_id, synchronization_batche_id) -> synchronization_batches(workspace_id, id) |
| Check Constraints | QMDB-CST-0752: Version value shall be positive and monotonic within its lineage. |
| Indexes | QMDB-IDX-0634 (public_id); QMDB-IDX-0635 (workspace_id, status_code, created_at, id); QMDB-IDX-0636 (workspace_id, synchronization_batche_id) |
| Versioning Strategy | Use optimistic version where concurrent mutation is material. |
| Deletion or Archival Strategy | Governed archival/anonymization; generic soft deletion only where explicitly permitted. |
| Audit Strategy | Record significant state, authority and correction actions in Audit Events. |
| Encryption Requirements | Integrity/transport protection per class. |
| Principal Query Patterns | Lookup Venue Reconciliation Reports by relational/public identity; List Venue Reconciliation Reports within one Workspace using keyset pagination |
| Implementation Phase | P13 |
| Related Requirements | QMDB-DR-027; QMDB-FR-OPS-001; QMDB-FR-OPS-002; QMDB-FR-OPS-003; QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-TEN-001 |
| Open Decisions | None |

| Column ID | Column Name | Logical Type | Proposed MySQL Type | Nullable | Default | Immutable | Sensitive | Encrypted | Indexed | Description | Validation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-COL-1892 | `id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Internal relational primary key; never used as authorization. | Domain validation plus schema type/constraint. |
| QMDB-COL-1893 | `public_id` | Public identifier | `BINARY(16)` | No | None | Yes | No | No | Yes | Opaque sortable UUIDv7 candidate pending OD-047; authorization remains policy-based. | Domain validation plus schema type/constraint. |
| QMDB-COL-1894 | `workspace_id` | Workspace identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Non-null owning Workspace used in composite tenant integrity. | Domain validation plus schema type/constraint. |
| QMDB-COL-1895 | `synchronization_batche_id` | Internal identifier | `BIGINT UNSIGNED` | No | None | Yes | No | No | Yes | Relational reference to synchronization_batches. | Domain validation plus schema type/constraint. |
| QMDB-COL-1896 | `status_code` | Status | `VARCHAR(32)` | No | None | No | No | No | Yes | Status Code for Venue Reconciliation Reports. | Domain validation plus schema type/constraint. |
| QMDB-COL-1897 | `version` | Version number | `INT UNSIGNED` | No | 1 | No | No | No | Yes | Version for Venue Reconciliation Reports. | Domain validation plus schema type/constraint. |
| QMDB-COL-1898 | `created_at` | UTC timestamp | `DATETIME(6)` | No | None | Yes | No | No | Yes | Created At for Venue Reconciliation Reports. | Domain validation plus schema type/constraint. |
| QMDB-COL-1899 | `updated_at` | UTC timestamp | `DATETIME(6)` | No | None | No | No | No | No | Updated At for Venue Reconciliation Reports. | Domain validation plus schema type/constraint. |

## Required-concept mapping rule

Required B04 concepts use the canonical tables above. Person/User Account, current Profile/Participant Snapshot, Score Sheet/Version/Aggregation/Result, Media Asset/object bytes and authoritative/projection concepts remain distinct. No polymorphic FK or generic EAV table is used.

## Related documents

- [Data architecture index](README.md)
- [Logical schema YAML](mysql-logical-schema.yaml)
- [Relationship and integrity catalog](06-relationship-constraint-and-integrity-catalog.md)
- [Tenant-isolation model](09-tenant-isolation-data-model.md)

## P3-B02 implemented table addendum

The executable People foundation adds `people_persons`, `people_person_names`, `people_account_links`,
`people_person_geographies`, `people_role_profiles`, `people_memorizer_progress` and `people_guardianships`.
Person names and birth dates are confidential personal data; opaque public IDs and registry codes are not access
grants. Current/historical rows, status columns and optimistic versions preserve lifecycle evidence. The migrations,
schema verifier and People verification command are authoritative for executable column detail.

## P3-B05 identity-resolution table addendum

The executable extension adds eight private tables: `people_profile_claim_pairings`, `people_profile_claims`,
`people_profile_claim_events`, `people_profile_verification_assertions`, `people_duplicate_cases`,
`people_duplicate_consent_requirements`, `people_duplicate_case_events`, and `people_person_aliases`. Pairing rows
retain only selector, versioned verifier/HMAC metadata, lifecycle, attempt count, expiry and opaque references; they
never retain a plaintext pairing secret. Claim and duplicate events are historical rows. Verification assertions are
QMDB record history only. Alias rows preserve the retired source-to-canonical relationship and cannot be changed or
deleted through normal application lifecycle paths.
