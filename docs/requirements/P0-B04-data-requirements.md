# P0-B04 Data Requirements

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B04 |
| Document Title | P0-B04 Data Requirements |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Data Architecture and Database Governance |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed for governance approval; inherited approved baseline obligations remain binding |
| Related Documents | [Data architecture](../data/01-data-architecture-overview.md); [schema manifest](../data/mysql-logical-schema.yaml); [traceability](P0-B04-traceability-matrix.md) |

## Purpose

Define the single-obligation data requirements that translate QMDB-P0-B01 through P0-B03 into an implementation-ready MySQL logical model.

## Scope

These 32 records govern aggregates, tables, columns, relationships, lifecycle, classification, concurrency, projection, migration and recovery. They do not create executable DDL, approve open numeric values, retention periods, authorities, providers, Quran data, geography codes or competition rules.

## Requirement records

### QMDB-DR-001 — Authoritative relational source

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-001 |
| Title | Authoritative relational source |
| Requirement Statement | Structured authoritative QMDB records shall be persisted in MySQL/InnoDB and shall not be derived from Redis, clients, search indexes, CDNs, or analytics. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Cross-cutting |
| Owning Module | All modules |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | GLOBAL_GOVERNED |
| Affected Aggregates | Workspace; Support Access Grant; Break-Glass Grant; User Account; Person; Guardian Relationship; Administrative Area; Organization; Organization Verification; Quran Text Release; Competition Series; Competition Edition; Ruleset Version; Registration; Participant Snapshot; Judge Panel; Judge Assignment; Performance; Score Sheet; Panel Aggregation; Result; Appeal; Certificate; Record Provenance; Legacy Import Batch; Media Asset; Recitation Clip; Moderation Case; Notification; Export; Privacy Request; Audit Chain; Outbox Event; API Client; Webhook Subscription; Platform Configuration; Offline Synchronization |
| Affected Tables | QMDB-TBL-001; QMDB-TBL-002; QMDB-TBL-003; QMDB-TBL-004; QMDB-TBL-005; QMDB-TBL-006; QMDB-TBL-007; QMDB-TBL-008; QMDB-TBL-009; QMDB-TBL-010; QMDB-TBL-011; QMDB-TBL-012; QMDB-TBL-013; QMDB-TBL-014; QMDB-TBL-015; QMDB-TBL-016; QMDB-TBL-017; QMDB-TBL-018; QMDB-TBL-019; QMDB-TBL-020; QMDB-TBL-021; QMDB-TBL-022; QMDB-TBL-023; QMDB-TBL-024; QMDB-TBL-025; QMDB-TBL-026; QMDB-TBL-027; QMDB-TBL-028; QMDB-TBL-029; QMDB-TBL-030; QMDB-TBL-031; QMDB-TBL-033; QMDB-TBL-034; QMDB-TBL-035; QMDB-TBL-036; QMDB-TBL-037; QMDB-TBL-038; QMDB-TBL-039; QMDB-TBL-040; QMDB-TBL-041; QMDB-TBL-042; QMDB-TBL-043; QMDB-TBL-044; QMDB-TBL-045; QMDB-TBL-046; QMDB-TBL-047; QMDB-TBL-048; QMDB-TBL-049; QMDB-TBL-050; QMDB-TBL-051; QMDB-TBL-052; QMDB-TBL-053; QMDB-TBL-054; QMDB-TBL-055; QMDB-TBL-056; QMDB-TBL-057; QMDB-TBL-058; QMDB-TBL-059; QMDB-TBL-060; QMDB-TBL-061; QMDB-TBL-062; QMDB-TBL-063; QMDB-TBL-064; QMDB-TBL-065; QMDB-TBL-066; QMDB-TBL-067; QMDB-TBL-068; QMDB-TBL-069; QMDB-TBL-070; QMDB-TBL-071; QMDB-TBL-072; QMDB-TBL-073; QMDB-TBL-074; QMDB-TBL-075; QMDB-TBL-076; QMDB-TBL-077; QMDB-TBL-078; QMDB-TBL-079; QMDB-TBL-080; QMDB-TBL-081; QMDB-TBL-082; QMDB-TBL-083; QMDB-TBL-084; QMDB-TBL-085; QMDB-TBL-086; QMDB-TBL-087; QMDB-TBL-088; QMDB-TBL-089; QMDB-TBL-090; QMDB-TBL-091; QMDB-TBL-092; QMDB-TBL-093; QMDB-TBL-094; QMDB-TBL-095; QMDB-TBL-096; QMDB-TBL-097; QMDB-TBL-098; QMDB-TBL-099; QMDB-TBL-100; QMDB-TBL-101; QMDB-TBL-102; QMDB-TBL-103; QMDB-TBL-104; QMDB-TBL-105; QMDB-TBL-106; QMDB-TBL-107; QMDB-TBL-108; QMDB-TBL-109; QMDB-TBL-110; QMDB-TBL-111; QMDB-TBL-112; QMDB-TBL-113; QMDB-TBL-114; QMDB-TBL-115; QMDB-TBL-116; QMDB-TBL-117; QMDB-TBL-118; QMDB-TBL-119; QMDB-TBL-120; QMDB-TBL-121; QMDB-TBL-122; QMDB-TBL-123; QMDB-TBL-124; QMDB-TBL-125; QMDB-TBL-126; QMDB-TBL-127; QMDB-TBL-128; QMDB-TBL-129; QMDB-TBL-130; QMDB-TBL-131; QMDB-TBL-132; QMDB-TBL-133; QMDB-TBL-134; QMDB-TBL-135; QMDB-TBL-136; QMDB-TBL-137; QMDB-TBL-138; QMDB-TBL-139; QMDB-TBL-140; QMDB-TBL-141; QMDB-TBL-142; QMDB-TBL-143; QMDB-TBL-144; QMDB-TBL-145; QMDB-TBL-146; QMDB-TBL-147; QMDB-TBL-148; QMDB-TBL-149; QMDB-TBL-150; QMDB-TBL-151; QMDB-TBL-152; QMDB-TBL-153; QMDB-TBL-154; QMDB-TBL-155; QMDB-TBL-156; QMDB-TBL-157; QMDB-TBL-158; QMDB-TBL-159; QMDB-TBL-160; QMDB-TBL-161; QMDB-TBL-162; QMDB-TBL-163; QMDB-TBL-164; QMDB-TBL-165; QMDB-TBL-166; QMDB-TBL-167; QMDB-TBL-168; QMDB-TBL-169; QMDB-TBL-170; QMDB-TBL-171; QMDB-TBL-172; QMDB-TBL-173; QMDB-TBL-174; QMDB-TBL-175; QMDB-TBL-176; QMDB-TBL-177; QMDB-TBL-178; QMDB-TBL-179; QMDB-TBL-180; QMDB-TBL-181; QMDB-TBL-182; QMDB-TBL-183; QMDB-TBL-184; QMDB-TBL-185; QMDB-TBL-186; QMDB-TBL-187; QMDB-TBL-188; QMDB-TBL-189; QMDB-TBL-190; QMDB-TBL-191; QMDB-TBL-192; QMDB-TBL-193; QMDB-TBL-194; QMDB-TBL-195; QMDB-TBL-196; QMDB-TBL-197; QMDB-TBL-198; QMDB-TBL-199; QMDB-TBL-200; QMDB-TBL-202; QMDB-TBL-203; QMDB-TBL-204; QMDB-TBL-205; QMDB-TBL-206; QMDB-TBL-207; QMDB-TBL-208; QMDB-TBL-213; QMDB-TBL-214; QMDB-TBL-215; QMDB-TBL-216; QMDB-TBL-217; QMDB-TBL-218; QMDB-TBL-219; QMDB-TBL-220; QMDB-TBL-221; QMDB-TBL-222; QMDB-TBL-223; QMDB-TBL-224; QMDB-TBL-225; QMDB-TBL-226; QMDB-TBL-227; QMDB-TBL-228; QMDB-TBL-229; QMDB-TBL-230; QMDB-TBL-231; QMDB-TBL-232; QMDB-TBL-233; QMDB-TBL-234; QMDB-TBL-235; QMDB-TBL-236; QMDB-TBL-237; QMDB-TBL-238; QMDB-TBL-239; QMDB-TBL-240; QMDB-TBL-241; QMDB-TBL-242; QMDB-TBL-243; QMDB-TBL-244; QMDB-TBL-245; QMDB-TBL-246; QMDB-TBL-247; QMDB-TBL-248; QMDB-TBL-249; QMDB-TBL-250 |
| Data Classification | QMDB-DCL-002; QMDB-DCL-004; QMDB-DCL-003; QMDB-DCL-001 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-FR-AUT-002; QMDB-FR-IAM-001; QMDB-FR-IAM-002; QMDB-FR-IAM-003; QMDB-FR-IAM-004 |
| Related Non-Functional Requirements | QMDB-NFR-DAT-001; QMDB-NFR-DAT-002 |
| Related Business Invariants | INV-009; INV-010; INV-022 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P2 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-002 — Workspace ownership

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-002 |
| Title | Workspace ownership |
| Requirement Statement | Every tenant-owned logical record shall contain a non-null Workspace identifier established from trusted server context. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Tenancy |
| Owning Module | Workspaces and Authorization |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | TENANT_OWNED |
| Affected Aggregates | Workspace; Person; Competition Series; Competition Edition; Ruleset Version; Registration; Participant Snapshot; Judge Panel; Judge Assignment; Performance; Score Sheet; Panel Aggregation; Result; Appeal; Certificate; Record Provenance; Legacy Import Batch; Media Asset; Recitation Clip; Moderation Case; Notification; Export; API Client; Webhook Subscription; Offline Synchronization; Administrative Area |
| Affected Tables | QMDB-TBL-002; QMDB-TBL-003; QMDB-TBL-004; QMDB-TBL-006; QMDB-TBL-007; QMDB-TBL-008; QMDB-TBL-009; QMDB-TBL-010; QMDB-TBL-011; QMDB-TBL-012; QMDB-TBL-013; QMDB-TBL-038; QMDB-TBL-039; QMDB-TBL-076; QMDB-TBL-077; QMDB-TBL-078; QMDB-TBL-079; QMDB-TBL-080; QMDB-TBL-081; QMDB-TBL-082; QMDB-TBL-083; QMDB-TBL-084; QMDB-TBL-085; QMDB-TBL-086; QMDB-TBL-087; QMDB-TBL-088; QMDB-TBL-089; QMDB-TBL-090; QMDB-TBL-091; QMDB-TBL-092; QMDB-TBL-093; QMDB-TBL-094; QMDB-TBL-095; QMDB-TBL-096; QMDB-TBL-097; QMDB-TBL-098; QMDB-TBL-099; QMDB-TBL-100; QMDB-TBL-101; QMDB-TBL-102; QMDB-TBL-103; QMDB-TBL-104; QMDB-TBL-105; QMDB-TBL-106; QMDB-TBL-107; QMDB-TBL-108; QMDB-TBL-109; QMDB-TBL-110; QMDB-TBL-111; QMDB-TBL-112; QMDB-TBL-113; QMDB-TBL-114; QMDB-TBL-115; QMDB-TBL-116; QMDB-TBL-117; QMDB-TBL-118; QMDB-TBL-119; QMDB-TBL-120; QMDB-TBL-121; QMDB-TBL-122; QMDB-TBL-123; QMDB-TBL-124; QMDB-TBL-125; QMDB-TBL-126; QMDB-TBL-127; QMDB-TBL-128; QMDB-TBL-129; QMDB-TBL-130; QMDB-TBL-131; QMDB-TBL-132; QMDB-TBL-133; QMDB-TBL-134; QMDB-TBL-135; QMDB-TBL-136; QMDB-TBL-137; QMDB-TBL-138; QMDB-TBL-139; QMDB-TBL-140; QMDB-TBL-141; QMDB-TBL-142; QMDB-TBL-143; QMDB-TBL-144; QMDB-TBL-145; QMDB-TBL-146; QMDB-TBL-147; QMDB-TBL-148; QMDB-TBL-149; QMDB-TBL-150; QMDB-TBL-151; QMDB-TBL-152; QMDB-TBL-153; QMDB-TBL-154; QMDB-TBL-155; QMDB-TBL-156; QMDB-TBL-157; QMDB-TBL-158; QMDB-TBL-159; QMDB-TBL-160; QMDB-TBL-161; QMDB-TBL-162; QMDB-TBL-163; QMDB-TBL-164; QMDB-TBL-165; QMDB-TBL-166; QMDB-TBL-167; QMDB-TBL-168; QMDB-TBL-169; QMDB-TBL-170; QMDB-TBL-171; QMDB-TBL-172; QMDB-TBL-173; QMDB-TBL-174; QMDB-TBL-175; QMDB-TBL-176; QMDB-TBL-177; QMDB-TBL-178; QMDB-TBL-179; QMDB-TBL-180; QMDB-TBL-181; QMDB-TBL-182; QMDB-TBL-183; QMDB-TBL-184; QMDB-TBL-185; QMDB-TBL-186; QMDB-TBL-187; QMDB-TBL-188; QMDB-TBL-189; QMDB-TBL-190; QMDB-TBL-191; QMDB-TBL-192; QMDB-TBL-193; QMDB-TBL-194; QMDB-TBL-195; QMDB-TBL-196; QMDB-TBL-197; QMDB-TBL-198; QMDB-TBL-199; QMDB-TBL-200; QMDB-TBL-202; QMDB-TBL-204; QMDB-TBL-213; QMDB-TBL-214; QMDB-TBL-215; QMDB-TBL-216; QMDB-TBL-231; QMDB-TBL-232; QMDB-TBL-233; QMDB-TBL-234; QMDB-TBL-235; QMDB-TBL-236; QMDB-TBL-237; QMDB-TBL-238; QMDB-TBL-245; QMDB-TBL-246; QMDB-TBL-247; QMDB-TBL-248; QMDB-TBL-249; QMDB-TBL-250 |
| Data Classification | QMDB-DCL-002; QMDB-DCL-003; QMDB-DCL-004 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-FR-AUT-002; QMDB-FR-PPL-001; QMDB-FR-PPL-002; QMDB-FR-PPL-003; QMDB-FR-PPL-004 |
| Related Non-Functional Requirements | QMDB-NFR-TEN-001 |
| Related Business Invariants | INV-001 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P2 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-003 — Workspace-aware relationships

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-003 |
| Title | Workspace-aware relationships |
| Requirement Statement | Every tenant-owned child-to-parent relationship shall prevent cross-Workspace references through composite candidate keys and foreign keys where relationally applicable. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Tenancy |
| Owning Module | Workspaces and Authorization |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | TENANT_CHILD |
| Affected Aggregates | Workspace; Person; Competition Series; Competition Edition; Ruleset Version; Registration; Participant Snapshot; Judge Panel; Judge Assignment; Performance; Score Sheet; Panel Aggregation; Result; Appeal; Certificate; Record Provenance; Legacy Import Batch; Media Asset; Recitation Clip; Moderation Case; Notification; Export; API Client; Webhook Subscription; Offline Synchronization; Administrative Area |
| Affected Tables | QMDB-TBL-002; QMDB-TBL-003; QMDB-TBL-004; QMDB-TBL-006; QMDB-TBL-007; QMDB-TBL-008; QMDB-TBL-009; QMDB-TBL-010; QMDB-TBL-011; QMDB-TBL-012; QMDB-TBL-013; QMDB-TBL-038; QMDB-TBL-039; QMDB-TBL-076; QMDB-TBL-077; QMDB-TBL-078; QMDB-TBL-079; QMDB-TBL-080; QMDB-TBL-081; QMDB-TBL-082; QMDB-TBL-083; QMDB-TBL-084; QMDB-TBL-085; QMDB-TBL-086; QMDB-TBL-087; QMDB-TBL-088; QMDB-TBL-089; QMDB-TBL-090; QMDB-TBL-091; QMDB-TBL-092; QMDB-TBL-093; QMDB-TBL-094; QMDB-TBL-095; QMDB-TBL-096; QMDB-TBL-097; QMDB-TBL-098; QMDB-TBL-099; QMDB-TBL-100; QMDB-TBL-101; QMDB-TBL-102; QMDB-TBL-103; QMDB-TBL-104; QMDB-TBL-105; QMDB-TBL-106; QMDB-TBL-107; QMDB-TBL-108; QMDB-TBL-109; QMDB-TBL-110; QMDB-TBL-111; QMDB-TBL-112; QMDB-TBL-113; QMDB-TBL-114; QMDB-TBL-115; QMDB-TBL-116; QMDB-TBL-117; QMDB-TBL-118; QMDB-TBL-119; QMDB-TBL-120; QMDB-TBL-121; QMDB-TBL-122; QMDB-TBL-123; QMDB-TBL-124; QMDB-TBL-125; QMDB-TBL-126; QMDB-TBL-127; QMDB-TBL-128; QMDB-TBL-129; QMDB-TBL-130; QMDB-TBL-131; QMDB-TBL-132; QMDB-TBL-133; QMDB-TBL-134; QMDB-TBL-135; QMDB-TBL-136; QMDB-TBL-137; QMDB-TBL-138; QMDB-TBL-139; QMDB-TBL-140; QMDB-TBL-141; QMDB-TBL-142; QMDB-TBL-143; QMDB-TBL-144; QMDB-TBL-145; QMDB-TBL-146; QMDB-TBL-147; QMDB-TBL-148; QMDB-TBL-149; QMDB-TBL-150; QMDB-TBL-151; QMDB-TBL-152; QMDB-TBL-153; QMDB-TBL-154; QMDB-TBL-155; QMDB-TBL-156; QMDB-TBL-157; QMDB-TBL-158; QMDB-TBL-159; QMDB-TBL-160; QMDB-TBL-161; QMDB-TBL-162; QMDB-TBL-163; QMDB-TBL-164; QMDB-TBL-165; QMDB-TBL-166; QMDB-TBL-167; QMDB-TBL-168; QMDB-TBL-169; QMDB-TBL-170; QMDB-TBL-171; QMDB-TBL-172; QMDB-TBL-173; QMDB-TBL-174; QMDB-TBL-175; QMDB-TBL-176; QMDB-TBL-177; QMDB-TBL-178; QMDB-TBL-179; QMDB-TBL-180; QMDB-TBL-181; QMDB-TBL-182; QMDB-TBL-183; QMDB-TBL-184; QMDB-TBL-185; QMDB-TBL-186; QMDB-TBL-187; QMDB-TBL-188; QMDB-TBL-189; QMDB-TBL-190; QMDB-TBL-191; QMDB-TBL-192; QMDB-TBL-193; QMDB-TBL-194; QMDB-TBL-195; QMDB-TBL-196; QMDB-TBL-197; QMDB-TBL-198; QMDB-TBL-199; QMDB-TBL-200; QMDB-TBL-202; QMDB-TBL-204; QMDB-TBL-213; QMDB-TBL-214; QMDB-TBL-215; QMDB-TBL-216; QMDB-TBL-231; QMDB-TBL-232; QMDB-TBL-233; QMDB-TBL-234; QMDB-TBL-235; QMDB-TBL-236; QMDB-TBL-237; QMDB-TBL-238; QMDB-TBL-245; QMDB-TBL-246; QMDB-TBL-247; QMDB-TBL-248; QMDB-TBL-249; QMDB-TBL-250 |
| Data Classification | QMDB-DCL-002; QMDB-DCL-003; QMDB-DCL-004 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-FR-AUT-002; QMDB-FR-PPL-001; QMDB-FR-PPL-002; QMDB-FR-PPL-003; QMDB-FR-PPL-004 |
| Related Non-Functional Requirements | QMDB-NFR-TEN-001; QMDB-NFR-DAT-001 |
| Related Business Invariants | INV-002 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P2 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-004 — Identity separation

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-004 |
| Title | Identity separation |
| Requirement Statement | Person identity, User Account authentication, Membership, Role, Administrative Scope, and Competition Assignment shall remain independently governed relational concepts. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Identity |
| Owning Module | Identity and Authentication |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | GLOBAL_GOVERNED |
| Affected Aggregates | User Account |
| Affected Tables | QMDB-TBL-016; QMDB-TBL-017; QMDB-TBL-018; QMDB-TBL-019; QMDB-TBL-020; QMDB-TBL-021; QMDB-TBL-022; QMDB-TBL-023; QMDB-TBL-024; QMDB-TBL-025; QMDB-TBL-026; QMDB-TBL-027; QMDB-TBL-028 |
| Data Classification | QMDB-DCL-002; QMDB-DCL-004; QMDB-DCL-003 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-IAM-001; QMDB-FR-IAM-002; QMDB-FR-IAM-003; QMDB-FR-IAM-004; QMDB-FR-IAM-005; QMDB-FR-IAM-006; QMDB-FR-SES-001; QMDB-FR-SES-002 |
| Related Non-Functional Requirements | QMDB-NFR-SEC-001; QMDB-NFR-DAT-001 |
| Related Business Invariants | INV-003; INV-004 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P2 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-005 — Authentication record protection

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-005 |
| Title | Authentication record protection |
| Requirement Statement | Authentication credentials, Sessions, tokens, passkeys, MFA methods, and security events shall use purpose-specific protected records with hashed verifiers and revocable lifecycle state. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Identity |
| Owning Module | Identity and Authentication |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | GLOBAL_GOVERNED |
| Affected Aggregates | User Account |
| Affected Tables | QMDB-TBL-016; QMDB-TBL-017; QMDB-TBL-018; QMDB-TBL-019; QMDB-TBL-020; QMDB-TBL-021; QMDB-TBL-022; QMDB-TBL-023; QMDB-TBL-024; QMDB-TBL-025; QMDB-TBL-026; QMDB-TBL-027; QMDB-TBL-028 |
| Data Classification | QMDB-DCL-002; QMDB-DCL-004; QMDB-DCL-003 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-IAM-001; QMDB-FR-IAM-002; QMDB-FR-IAM-003; QMDB-FR-IAM-004; QMDB-FR-IAM-005; QMDB-FR-IAM-006; QMDB-FR-SES-001; QMDB-FR-SES-002 |
| Related Non-Functional Requirements | QMDB-NFR-IAM-001; QMDB-NFR-IAM-002 |
| Related Business Invariants | INV-029 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P2 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-006 — Scoped authorization records

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-006 |
| Title | Scoped authorization records |
| Requirement Statement | Workspace Membership, Role, Permission, Scope Grant, approval, support, and Break-Glass records shall preserve subject, resource, scope, time, approval, and revocation evidence. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Authorization |
| Owning Module | Workspaces and Authorization |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | HYBRID |
| Affected Aggregates | Workspace; Support Access Grant; Break-Glass Grant |
| Affected Tables | QMDB-TBL-001; QMDB-TBL-002; QMDB-TBL-003; QMDB-TBL-004; QMDB-TBL-005; QMDB-TBL-006; QMDB-TBL-007; QMDB-TBL-008; QMDB-TBL-009; QMDB-TBL-010; QMDB-TBL-011; QMDB-TBL-012; QMDB-TBL-013; QMDB-TBL-014; QMDB-TBL-015 |
| Data Classification | QMDB-DCL-002; QMDB-DCL-004 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-FR-AUT-002 |
| Related Non-Functional Requirements | QMDB-NFR-SEC-001; QMDB-NFR-TEN-001 |
| Related Business Invariants | INV-024; INV-025; INV-027 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P2 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-007 — Durable people and profiles

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-007 |
| Title | Durable people and profiles |
| Requirement Statement | Person records shall preserve current profile facts separately from contextual profiles and immutable historical Participant Snapshots. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | People |
| Owning Module | People and Guardianship |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | GLOBAL_GOVERNED |
| Affected Aggregates | Person; Guardian Relationship |
| Affected Tables | QMDB-TBL-029; QMDB-TBL-030; QMDB-TBL-031; QMDB-TBL-032; QMDB-TBL-033; QMDB-TBL-034; QMDB-TBL-035; QMDB-TBL-036; QMDB-TBL-037; QMDB-TBL-038; QMDB-TBL-039; QMDB-TBL-040; QMDB-TBL-041; QMDB-TBL-042; QMDB-TBL-043; QMDB-TBL-044; QMDB-TBL-045 |
| Data Classification | QMDB-DCL-003; QMDB-DCL-001; QMDB-DCL-004 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-PPL-001; QMDB-FR-PPL-002; QMDB-FR-PPL-003; QMDB-FR-PPL-004; QMDB-FR-GUA-001; QMDB-FR-GUA-002; QMDB-FR-GUA-003; QMDB-FR-GUA-004 |
| Related Non-Functional Requirements | QMDB-NFR-PRI-001; QMDB-NFR-PRI-002 |
| Related Business Invariants | INV-003; INV-007 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P3 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-008 — Guardianship and consent history

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-008 |
| Title | Guardianship and consent history |
| Requirement Statement | Guardian Relationship and purpose-bound Consent records shall preserve verification, scope, version, withdrawal, dispute, and historical evidence without erasing protected official history. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Guardianship |
| Owning Module | People and Guardianship |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | GLOBAL_GOVERNED |
| Affected Aggregates | Person; Guardian Relationship |
| Affected Tables | QMDB-TBL-029; QMDB-TBL-030; QMDB-TBL-031; QMDB-TBL-032; QMDB-TBL-033; QMDB-TBL-034; QMDB-TBL-035; QMDB-TBL-036; QMDB-TBL-037; QMDB-TBL-038; QMDB-TBL-039; QMDB-TBL-040; QMDB-TBL-041; QMDB-TBL-042; QMDB-TBL-043; QMDB-TBL-044; QMDB-TBL-045 |
| Data Classification | QMDB-DCL-003; QMDB-DCL-001; QMDB-DCL-004 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-PPL-001; QMDB-FR-PPL-002; QMDB-FR-PPL-003; QMDB-FR-PPL-004; QMDB-FR-GUA-001; QMDB-FR-GUA-002; QMDB-FR-GUA-003; QMDB-FR-GUA-004 |
| Related Non-Functional Requirements | QMDB-NFR-CHD-001; QMDB-NFR-CHD-003 |
| Related Business Invariants | INV-020 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P3 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-009 — Version-aware geography

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-009 |
| Title | Version-aware geography |
| Requirement Statement | Administrative Area records shall preserve authoritative source, type, code, name, validity, alias, parent, history, and replacement lineage. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | High |
| Data Domain | Geography |
| Owning Module | Geography |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | GLOBAL_REFERENCE |
| Affected Aggregates | Administrative Area |
| Affected Tables | QMDB-TBL-046; QMDB-TBL-047; QMDB-TBL-048; QMDB-TBL-049; QMDB-TBL-050; QMDB-TBL-051 |
| Data Classification | QMDB-DCL-002 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-GEO-001; QMDB-FR-GEO-002; QMDB-FR-GEO-003 |
| Related Non-Functional Requirements | QMDB-NFR-DAT-001; QMDB-NFR-L10-002 |
| Related Business Invariants | INV-006 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P3 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-010 — Organization authority and history

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-010 |
| Title | Organization authority and history |
| Requirement Statement | Organization records shall preserve type, hierarchy, coverage, relationship, verification evidence, decisions, branding, suspension, and historical identity. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | High |
| Data Domain | Organizations |
| Owning Module | Organizations |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | GLOBAL_GOVERNED |
| Affected Aggregates | Organization; Organization Verification |
| Affected Tables | QMDB-TBL-052; QMDB-TBL-053; QMDB-TBL-054; QMDB-TBL-055; QMDB-TBL-056; QMDB-TBL-057; QMDB-TBL-058; QMDB-TBL-059; QMDB-TBL-060 |
| Data Classification | QMDB-DCL-002 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-ORG-001; QMDB-FR-ORG-002; QMDB-FR-ORG-003; QMDB-FR-ORG-004 |
| Related Non-Functional Requirements | QMDB-NFR-PRI-002; QMDB-NFR-DAT-001 |
| Related Business Invariants | INV-006 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P3 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-011 — Canonical Quran release integrity

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-011 |
| Title | Canonical Quran release integrity |
| Requirement Statement | Canonical Quran data shall be imported as immutable versioned releases with source, Reading, structural references, exact text, checksums, approvals, and supersession history. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Quran Reference |
| Owning Module | Quran Reference Governance |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | GLOBAL_REFERENCE |
| Affected Aggregates | Quran Text Release |
| Affected Tables | QMDB-TBL-061; QMDB-TBL-062; QMDB-TBL-063; QMDB-TBL-064; QMDB-TBL-065; QMDB-TBL-066; QMDB-TBL-067; QMDB-TBL-068; QMDB-TBL-069; QMDB-TBL-070; QMDB-TBL-071; QMDB-TBL-072; QMDB-TBL-073; QMDB-TBL-074; QMDB-TBL-075 |
| Data Classification | QMDB-DCL-001; QMDB-DCL-002 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-QRF-001; QMDB-FR-QRF-002; QMDB-FR-QRF-003; QMDB-FR-QRF-004; QMDB-FR-QRF-005; QMDB-FR-QRF-006 |
| Related Non-Functional Requirements | QMDB-NFR-DAT-001; QMDB-NFR-AUD-001 |
| Related Business Invariants | INV-017; INV-018 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P4 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-012 — Versioned competition rules

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-012 |
| Title | Versioned competition rules |
| Requirement Statement | Competition Series, Edition, dimensions, schedules, and declarative Ruleset Versions shall preserve separated authority dimensions, immutable locked definitions, exact checksums, and effective versions. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Competition |
| Owning Module | Competition Configuration |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | TENANT_OWNED |
| Affected Aggregates | Competition Series; Competition Edition; Ruleset Version |
| Affected Tables | QMDB-TBL-076; QMDB-TBL-077; QMDB-TBL-078; QMDB-TBL-079; QMDB-TBL-080; QMDB-TBL-081; QMDB-TBL-082; QMDB-TBL-083; QMDB-TBL-084; QMDB-TBL-085; QMDB-TBL-086; QMDB-TBL-087; QMDB-TBL-088; QMDB-TBL-089; QMDB-TBL-090; QMDB-TBL-091; QMDB-TBL-092; QMDB-TBL-093; QMDB-TBL-094; QMDB-TBL-095 |
| Data Classification | QMDB-DCL-002; QMDB-DCL-004 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-CMP-001; QMDB-FR-CMP-002; QMDB-FR-CMP-003; QMDB-FR-CMP-004; QMDB-FR-RUL-001; QMDB-FR-RUL-002; QMDB-FR-RUL-003 |
| Related Non-Functional Requirements | QMDB-NFR-DAT-001; QMDB-NFR-MNT-002 |
| Related Business Invariants | INV-005; INV-006; INV-011; INV-012 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P5 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-013 — Registration and eligibility evidence

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-013 |
| Title | Registration and eligibility evidence |
| Requirement Statement | Registration, nomination, category, eligibility, exception, decision, Check-In, and Participant Snapshot records shall preserve idempotent intake, evidence, authority, outcome, and historical context. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Registration |
| Owning Module | Registration and Eligibility |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | TENANT_OWNED |
| Affected Aggregates | Registration; Participant Snapshot |
| Affected Tables | QMDB-TBL-096; QMDB-TBL-097; QMDB-TBL-098; QMDB-TBL-099; QMDB-TBL-100; QMDB-TBL-101; QMDB-TBL-102; QMDB-TBL-103; QMDB-TBL-104; QMDB-TBL-105; QMDB-TBL-106; QMDB-TBL-107; QMDB-TBL-108 |
| Data Classification | QMDB-DCL-003; QMDB-DCL-002 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-REG-001; QMDB-FR-REG-002; QMDB-FR-REG-003; QMDB-FR-REG-004; QMDB-FR-REG-005 |
| Related Non-Functional Requirements | QMDB-NFR-PRI-002; QMDB-NFR-DAT-001 |
| Related Business Invariants | INV-007 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P5 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-014 — Scheduling and judge authority

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-014 |
| Title | Scheduling and judge authority |
| Requirement Statement | Schedules, Draw Orders, Judge Panels, assignments, qualifications, conflicts, recusals, and replacements shall preserve current scope, time, decision, and historical state. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Judging |
| Owning Module | Scheduling and Judging |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | TENANT_OWNED |
| Affected Aggregates | Competition Edition; Judge Panel; Judge Assignment |
| Affected Tables | QMDB-TBL-109; QMDB-TBL-110; QMDB-TBL-111; QMDB-TBL-112; QMDB-TBL-113; QMDB-TBL-114; QMDB-TBL-115; QMDB-TBL-116; QMDB-TBL-117; QMDB-TBL-118; QMDB-TBL-119 |
| Data Classification | QMDB-DCL-002; QMDB-DCL-004 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-SCH-001; QMDB-FR-SCH-002; QMDB-FR-SCH-003; QMDB-FR-SCH-004; QMDB-FR-JDG-001; QMDB-FR-JDG-002; QMDB-FR-JDG-003; QMDB-FR-JDG-004 |
| Related Non-Functional Requirements | QMDB-NFR-SEC-001; QMDB-NFR-TEN-001 |
| Related Business Invariants | INV-004 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P6 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-015 — Exact versioned scoring

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-015 |
| Title | Exact versioned scoring |
| Requirement Statement | Performance, Score Sheet, Score Sheet Version, score item, deduction, mistake, signature, receipt, reopening, aggregation, and calculation records shall use exact decimal values and preserve every submitted version. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Scoring |
| Owning Module | Performance and Scoring |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | TENANT_OWNED |
| Affected Aggregates | Performance; Score Sheet; Panel Aggregation |
| Affected Tables | QMDB-TBL-120; QMDB-TBL-121; QMDB-TBL-122; QMDB-TBL-123; QMDB-TBL-124; QMDB-TBL-125; QMDB-TBL-126; QMDB-TBL-127; QMDB-TBL-128; QMDB-TBL-129; QMDB-TBL-130; QMDB-TBL-131; QMDB-TBL-132; QMDB-TBL-133; QMDB-TBL-134; QMDB-TBL-135; QMDB-TBL-136; QMDB-TBL-137 |
| Data Classification | QMDB-DCL-002; QMDB-DCL-004 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-SCR-001; QMDB-FR-SCR-002; QMDB-FR-SCR-003; QMDB-FR-SCR-004; QMDB-FR-SCR-005; QMDB-FR-SCR-006 |
| Related Non-Functional Requirements | QMDB-NFR-DAT-001; QMDB-NFR-DAT-002; QMDB-NFR-AUD-001 |
| Related Business Invariants | INV-008; INV-009; INV-010; INV-011; INV-013 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P6 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-016 — Result and Appeal preservation

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-016 |
| Title | Result and Appeal preservation |
| Requirement Statement | Provisional, final, corrected, superseded Result records and Appeal evidence and decisions shall preserve former versions and shall never rewrite source score submissions. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Results |
| Owning Module | Results and Appeals |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | TENANT_OWNED |
| Affected Aggregates | Result; Appeal |
| Affected Tables | QMDB-TBL-138; QMDB-TBL-139; QMDB-TBL-140; QMDB-TBL-141; QMDB-TBL-142; QMDB-TBL-143; QMDB-TBL-144; QMDB-TBL-145; QMDB-TBL-146; QMDB-TBL-147; QMDB-TBL-148; QMDB-TBL-149; QMDB-TBL-150 |
| Data Classification | QMDB-DCL-002; QMDB-DCL-004 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-LIV-001; QMDB-FR-LIV-002; QMDB-FR-RSL-001; QMDB-FR-RSL-002; QMDB-FR-RSL-003; QMDB-FR-RSL-004; QMDB-FR-APL-001; QMDB-FR-APL-002 |
| Related Non-Functional Requirements | QMDB-NFR-DAT-002; QMDB-NFR-AUD-001 |
| Related Business Invariants | INV-014; INV-016; INV-026 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P7 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-017 — Certificate integrity

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-017 |
| Title | Certificate integrity |
| Requirement Statement | Certificate records shall preserve recipient and Result version references, template version, serial, verification-code hash, document hash, signature metadata, issuance, revocation, verification, and supersession history. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Certificates |
| Owning Module | Certificates and Trusted Records |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | TENANT_OWNED |
| Affected Aggregates | Certificate; Competition Edition; Record Provenance; Legacy Import Batch |
| Affected Tables | QMDB-TBL-151; QMDB-TBL-152; QMDB-TBL-153; QMDB-TBL-154; QMDB-TBL-155; QMDB-TBL-156; QMDB-TBL-157; QMDB-TBL-158; QMDB-TBL-159; QMDB-TBL-160; QMDB-TBL-161; QMDB-TBL-162; QMDB-TBL-163; QMDB-TBL-164; QMDB-TBL-165; QMDB-TBL-166; QMDB-TBL-167; QMDB-TBL-168 |
| Data Classification | QMDB-DCL-003; QMDB-DCL-004; QMDB-DCL-002 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-CER-001; QMDB-FR-CER-002; QMDB-FR-CER-003; QMDB-FR-CER-004; QMDB-FR-REC-001; QMDB-FR-IMP-001 |
| Related Non-Functional Requirements | QMDB-NFR-CRY-001; QMDB-NFR-DAT-002 |
| Related Business Invariants | INV-015; INV-026 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P8 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-018 — Record provenance and legacy import

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-018 |
| Title | Record provenance and legacy import |
| Requirement Statement | Competition Record, provenance, evidence, dispute, verification, and Legacy Import records shall preserve source, checksum, validation, review, authority, reconciliation, and correction lineage. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Trusted Records |
| Owning Module | Certificates and Trusted Records |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | TENANT_OWNED |
| Affected Aggregates | Certificate; Competition Edition; Record Provenance; Legacy Import Batch |
| Affected Tables | QMDB-TBL-151; QMDB-TBL-152; QMDB-TBL-153; QMDB-TBL-154; QMDB-TBL-155; QMDB-TBL-156; QMDB-TBL-157; QMDB-TBL-158; QMDB-TBL-159; QMDB-TBL-160; QMDB-TBL-161; QMDB-TBL-162; QMDB-TBL-163; QMDB-TBL-164; QMDB-TBL-165; QMDB-TBL-166; QMDB-TBL-167; QMDB-TBL-168 |
| Data Classification | QMDB-DCL-003; QMDB-DCL-004; QMDB-DCL-002 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-CER-001; QMDB-FR-CER-002; QMDB-FR-CER-003; QMDB-FR-CER-004; QMDB-FR-REC-001; QMDB-FR-IMP-001 |
| Related Non-Functional Requirements | QMDB-NFR-DAT-001; QMDB-NFR-AUD-001 |
| Related Business Invariants | INV-026 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P8 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-019 — Media metadata separation

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-019 |
| Title | Media metadata separation |
| Requirement Statement | MySQL shall store governed Media Asset identity, ownership, consent, hashes, storage references, processing, moderation, publication, and retention state but shall not store audio or video binaries. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Media |
| Owning Module | Media and Evidence |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | TENANT_OWNED |
| Affected Aggregates | Media Asset |
| Affected Tables | QMDB-TBL-169; QMDB-TBL-170; QMDB-TBL-171; QMDB-TBL-172; QMDB-TBL-173; QMDB-TBL-174; QMDB-TBL-175; QMDB-TBL-176; QMDB-TBL-177; QMDB-TBL-178; QMDB-TBL-179; QMDB-TBL-180; QMDB-TBL-181 |
| Data Classification | QMDB-DCL-004 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-MED-001; QMDB-FR-MED-002; QMDB-FR-MED-003; QMDB-FR-MED-004; QMDB-FR-MED-005 |
| Related Non-Functional Requirements | QMDB-NFR-MED-001; QMDB-NFR-PRI-002 |
| Related Business Invariants | INV-019; INV-020 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P9 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-020 — Social and moderation isolation

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-020 |
| Title | Social and moderation isolation |
| Requirement Statement | Recitation Clip and social interaction records shall remain separate from official competition authority and shall preserve reports, evidence, cases, actions, appeals, safety state, and projection boundaries. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Community |
| Owning Module | Recitation Clips and Moderation |
| Authoritative Source | Authoritative MySQL source versions plus rebuild rules |
| Tenant Scope | TENANT_OWNED |
| Affected Aggregates | Recitation Clip; Moderation Case |
| Affected Tables | QMDB-TBL-182; QMDB-TBL-183; QMDB-TBL-184; QMDB-TBL-185; QMDB-TBL-186; QMDB-TBL-187; QMDB-TBL-188; QMDB-TBL-189; QMDB-TBL-190; QMDB-TBL-191; QMDB-TBL-192; QMDB-TBL-193; QMDB-TBL-194; QMDB-TBL-195; QMDB-TBL-196; QMDB-TBL-197; QMDB-TBL-198; QMDB-TBL-199; QMDB-TBL-200; QMDB-TBL-201; QMDB-TBL-202 |
| Data Classification | QMDB-DCL-002; QMDB-DCL-004; QMDB-DCL-003; QMDB-DCL-001 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-SOC-001; QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-FR-MOD-001; QMDB-FR-MOD-002 |
| Related Non-Functional Requirements | QMDB-NFR-CHD-002; QMDB-NFR-PER-001 |
| Related Business Invariants | INV-021; INV-030 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P10 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-021 — Reliable notifications

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-021 |
| Title | Reliable notifications |
| Requirement Statement | Notification intent, preference, template version, delivery, attempt, and dead-letter records shall be idempotent, privacy-minimized, non-authoritative, and recoverable. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | High |
| Data Domain | Notifications |
| Owning Module | Notifications |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | HYBRID |
| Affected Aggregates | Notification |
| Affected Tables | QMDB-TBL-203; QMDB-TBL-204; QMDB-TBL-205; QMDB-TBL-206; QMDB-TBL-207; QMDB-TBL-208 |
| Data Classification | QMDB-DCL-003 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-NTF-001; QMDB-FR-NTF-002 |
| Related Non-Functional Requirements | QMDB-NFR-SCL-001; QMDB-NFR-PRI-002 |
| Related Business Invariants | INV-023 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P7 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-022 — Rebuildable search and reporting

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-022 |
| Title | Rebuildable search and reporting |
| Requirement Statement | Search, public record, live scoreboard, reporting, export, and analytics records shall identify authoritative source versions and shall remain rebuildable non-authoritative projections. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Projection |
| Owning Module | Search and Reporting |
| Authoritative Source | Authoritative MySQL source versions plus rebuild rules |
| Tenant Scope | PUBLIC_PROJECTION |
| Affected Aggregates | Projection; Export |
| Affected Tables | QMDB-TBL-209; QMDB-TBL-210; QMDB-TBL-211; QMDB-TBL-212; QMDB-TBL-213; QMDB-TBL-214; QMDB-TBL-215; QMDB-TBL-216 |
| Data Classification | QMDB-DCL-002; QMDB-DCL-001; QMDB-DCL-003 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-SRH-001; QMDB-FR-SRH-002; QMDB-FR-SRH-003; QMDB-FR-RPT-001; QMDB-FR-RPT-002; QMDB-FR-RPT-003 |
| Related Non-Functional Requirements | QMDB-NFR-DAT-002; QMDB-NFR-PRI-003 |
| Related Business Invariants | INV-022 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P11 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-023 — Privacy lifecycle governance

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-023 |
| Title | Privacy lifecycle governance |
| Requirement Statement | Processing purpose, notice, Privacy Request, assignment, event, Retention Policy, Data Hold, anonymization, and export-delivery records shall preserve accountable rights and disposition decisions without inventing periods. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Privacy |
| Owning Module | Privacy and Data Governance |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | HYBRID |
| Affected Aggregates | Privacy Request |
| Affected Tables | QMDB-TBL-217; QMDB-TBL-218; QMDB-TBL-219; QMDB-TBL-220; QMDB-TBL-221; QMDB-TBL-222; QMDB-TBL-223; QMDB-TBL-224; QMDB-TBL-225 |
| Data Classification | QMDB-DCL-002; QMDB-DCL-004 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-PRI-001; QMDB-FR-PRI-002; QMDB-FR-PRI-003; QMDB-FR-PRI-004 |
| Related Non-Functional Requirements | QMDB-NFR-PRI-001; QMDB-NFR-PRI-004 |
| Related Business Invariants | INV-026 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P12 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-024 — Tamper-evident audit lineage

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-024 |
| Title | Tamper-evident audit lineage |
| Requirement Statement | Significant business and privileged actions shall create append-only canonical Audit Events linked by hashes and externally verifiable checkpoints. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Audit |
| Owning Module | Audit and Integrations |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | SYSTEM_OPERATIONAL |
| Affected Aggregates | Audit Chain; Outbox Event; API Client; Webhook Subscription |
| Affected Tables | QMDB-TBL-226; QMDB-TBL-227; QMDB-TBL-228; QMDB-TBL-229; QMDB-TBL-230; QMDB-TBL-231; QMDB-TBL-232; QMDB-TBL-233; QMDB-TBL-234; QMDB-TBL-235; QMDB-TBL-236; QMDB-TBL-237; QMDB-TBL-238 |
| Data Classification | QMDB-DCL-004; QMDB-DCL-002 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-AUD-001; QMDB-FR-AUD-002; QMDB-FR-INT-001; QMDB-FR-INT-002; QMDB-FR-INT-003 |
| Related Non-Functional Requirements | QMDB-NFR-AUD-001 |
| Related Business Invariants | INV-024; INV-025; INV-029 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P12 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-025 — Transactional events and idempotency

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-025 |
| Title | Transactional events and idempotency |
| Requirement Statement | Authoritative transactions shall atomically persist versioned Outbox Events and shall use scoped Idempotency Records so duplicate delivery cannot duplicate business effects. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Integration |
| Owning Module | Audit and Integrations |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | HYBRID |
| Affected Aggregates | Audit Chain; Outbox Event; API Client; Webhook Subscription |
| Affected Tables | QMDB-TBL-226; QMDB-TBL-227; QMDB-TBL-228; QMDB-TBL-229; QMDB-TBL-230; QMDB-TBL-231; QMDB-TBL-232; QMDB-TBL-233; QMDB-TBL-234; QMDB-TBL-235; QMDB-TBL-236; QMDB-TBL-237; QMDB-TBL-238 |
| Data Classification | QMDB-DCL-004; QMDB-DCL-002 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-AUD-001; QMDB-FR-AUD-002; QMDB-FR-INT-001; QMDB-FR-INT-002; QMDB-FR-INT-003 |
| Related Non-Functional Requirements | QMDB-NFR-SCL-001; QMDB-NFR-API-001 |
| Related Business Invariants | INV-023 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P12 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-026 — Scoped integration records

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-026 |
| Title | Scoped integration records |
| Requirement Statement | API Client, scope, credential verifier, Webhook Subscription, delivery, provider reference, and Integration Event records shall preserve least privilege, replay defense, rotation, and failure evidence. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Integration |
| Owning Module | Audit and Integrations |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | TENANT_OWNED |
| Affected Aggregates | Audit Chain; Outbox Event; API Client; Webhook Subscription |
| Affected Tables | QMDB-TBL-226; QMDB-TBL-227; QMDB-TBL-228; QMDB-TBL-229; QMDB-TBL-230; QMDB-TBL-231; QMDB-TBL-232; QMDB-TBL-233; QMDB-TBL-234; QMDB-TBL-235; QMDB-TBL-236; QMDB-TBL-237; QMDB-TBL-238 |
| Data Classification | QMDB-DCL-004; QMDB-DCL-002 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-AUD-001; QMDB-FR-AUD-002; QMDB-FR-INT-001; QMDB-FR-INT-002; QMDB-FR-INT-003 |
| Related Non-Functional Requirements | QMDB-NFR-API-001; QMDB-NFR-SEC-001 |
| Related Business Invariants | INV-028; INV-029 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P12 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-027 — Offline and platform operations

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-027 |
| Title | Offline and platform operations |
| Requirement Statement | Configuration, feature, job, dead-letter, offline package, event, synchronization, conflict, edge-node, and reconciliation records shall preserve Workspace context, expected version, sequence, idempotency, and audit provenance. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Operations |
| Owning Module | Platform and Offline Operations |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | HYBRID |
| Affected Aggregates | Platform Configuration; Offline Synchronization; Administrative Area |
| Affected Tables | QMDB-TBL-239; QMDB-TBL-240; QMDB-TBL-241; QMDB-TBL-242; QMDB-TBL-243; QMDB-TBL-244; QMDB-TBL-245; QMDB-TBL-246; QMDB-TBL-247; QMDB-TBL-248; QMDB-TBL-249; QMDB-TBL-250 |
| Data Classification | QMDB-DCL-002; QMDB-DCL-004; QMDB-DCL-003 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-OPS-001; QMDB-FR-OPS-002; QMDB-FR-OPS-003; QMDB-FR-OPS-004; QMDB-FR-OPS-005; QMDB-FR-OFF-001; QMDB-FR-OFF-002 |
| Related Non-Functional Requirements | QMDB-NFR-RES-003; QMDB-NFR-OBS-001 |
| Related Business Invariants | INV-023; INV-030 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P13 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-028 — Lifecycle-specific mutation

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-028 |
| Title | Lifecycle-specific mutation |
| Requirement Statement | Every logical table shall declare a lifecycle class whose mutation, versioning, deletion, restoration, cache, backup, and retention behavior preserves official history. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Lifecycle |
| Owning Module | All modules |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | HYBRID |
| Affected Aggregates | Workspace; Support Access Grant; Break-Glass Grant; User Account; Person; Guardian Relationship; Administrative Area; Organization; Organization Verification; Quran Text Release; Competition Series; Competition Edition; Ruleset Version; Registration; Participant Snapshot; Judge Panel; Judge Assignment; Performance; Score Sheet; Panel Aggregation; Result; Appeal; Certificate; Record Provenance; Legacy Import Batch; Media Asset; Recitation Clip; Moderation Case; Notification; Projection; Export; Privacy Request; Audit Chain; Outbox Event; API Client; Webhook Subscription; Platform Configuration; Offline Synchronization |
| Affected Tables | All 250 logical tables (QMDB-TBL-001–QMDB-TBL-250) |
| Data Classification | QMDB-DCL-002; QMDB-DCL-004; QMDB-DCL-003; QMDB-DCL-001 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-FR-AUT-002; QMDB-FR-IAM-001; QMDB-FR-IAM-002; QMDB-FR-IAM-003; QMDB-FR-IAM-004 |
| Related Non-Functional Requirements | QMDB-NFR-DAT-002; QMDB-NFR-PRI-004 |
| Related Business Invariants | INV-007; INV-008; INV-014; INV-015; INV-026 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P2 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-029 — Classification and encryption metadata

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-029 |
| Title | Classification and encryption metadata |
| Requirement Statement | Every logical table and sensitive column shall declare classification, visibility, export, support, audit, retention-decision, and encryption requirements while secrets remain outside ordinary tables. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Data Governance |
| Owning Module | All modules |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | HYBRID |
| Affected Aggregates | Workspace; Support Access Grant; Break-Glass Grant; User Account; Person; Guardian Relationship; Administrative Area; Organization; Organization Verification; Quran Text Release; Competition Series; Competition Edition; Ruleset Version; Registration; Participant Snapshot; Judge Panel; Judge Assignment; Performance; Score Sheet; Panel Aggregation; Result; Appeal; Certificate; Record Provenance; Legacy Import Batch; Media Asset; Recitation Clip; Moderation Case; Notification; Projection; Export; Privacy Request; Audit Chain; Outbox Event; API Client; Webhook Subscription; Platform Configuration; Offline Synchronization |
| Affected Tables | All 250 logical tables (QMDB-TBL-001–QMDB-TBL-250) |
| Data Classification | QMDB-DCL-002; QMDB-DCL-004; QMDB-DCL-003; QMDB-DCL-001 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-FR-AUT-002; QMDB-FR-IAM-001; QMDB-FR-IAM-002; QMDB-FR-IAM-003; QMDB-FR-IAM-004 |
| Related Non-Functional Requirements | QMDB-NFR-PRI-002; QMDB-NFR-CRY-001 |
| Related Business Invariants | INV-029 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P2 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-030 — Migration, seed, and bootstrap governance

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-030 |
| Title | Migration, seed, and bootstrap governance |
| Requirement Statement | Future schema migrations and seed imports shall follow dependency-ordered groups, provenance, checksums, review, rollback or compensation, and bootstrap controls without fabricated authoritative data or default shared credentials. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Migration |
| Owning Module | All modules |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | HYBRID |
| Affected Aggregates | Workspace; Support Access Grant; Break-Glass Grant; User Account; Person; Guardian Relationship; Administrative Area; Organization; Organization Verification; Quran Text Release; Competition Series; Competition Edition; Ruleset Version; Registration; Participant Snapshot; Judge Panel; Judge Assignment; Performance; Score Sheet; Panel Aggregation; Result; Appeal; Certificate; Record Provenance; Legacy Import Batch; Media Asset; Recitation Clip; Moderation Case; Notification; Projection; Export; Privacy Request; Audit Chain; Outbox Event; API Client; Webhook Subscription; Platform Configuration; Offline Synchronization |
| Affected Tables | All 250 logical tables (QMDB-TBL-001–QMDB-TBL-250) |
| Data Classification | QMDB-DCL-002; QMDB-DCL-004; QMDB-DCL-003; QMDB-DCL-001 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-FR-AUT-002; QMDB-FR-IAM-001; QMDB-FR-IAM-002; QMDB-FR-IAM-003; QMDB-FR-IAM-004 |
| Related Non-Functional Requirements | QMDB-NFR-MNT-002; QMDB-NFR-REL-001 |
| Related Business Invariants | INV-017; INV-029 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P2 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-031 — Query access and concurrency

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-031 |
| Title | Query access and concurrency |
| Requirement Statement | Every principal query and authoritative state transition shall have an identified bounded access path, tenant filter, consistency source, index rationale, and concurrency control. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Performance |
| Owning Module | All modules |
| Authoritative Source | Owning modular-monolith domain service and MySQL record |
| Tenant Scope | HYBRID |
| Affected Aggregates | Workspace; Support Access Grant; Break-Glass Grant; User Account; Person; Guardian Relationship; Administrative Area; Organization; Organization Verification; Quran Text Release; Competition Series; Competition Edition; Ruleset Version; Registration; Participant Snapshot; Judge Panel; Judge Assignment; Performance; Score Sheet; Panel Aggregation; Result; Appeal; Certificate; Record Provenance; Legacy Import Batch; Media Asset; Recitation Clip; Moderation Case; Notification; Projection; Export; Privacy Request; Audit Chain; Outbox Event; API Client; Webhook Subscription; Platform Configuration; Offline Synchronization |
| Affected Tables | All 250 logical tables (QMDB-TBL-001–QMDB-TBL-250) |
| Data Classification | QMDB-DCL-002; QMDB-DCL-004; QMDB-DCL-003; QMDB-DCL-001 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-FR-AUT-002; QMDB-FR-IAM-001; QMDB-FR-IAM-002; QMDB-FR-IAM-003; QMDB-FR-IAM-004 |
| Related Non-Functional Requirements | QMDB-NFR-PER-003; QMDB-NFR-SCL-001 |
| Related Business Invariants | INV-002; INV-008; INV-023 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P2 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

### QMDB-DR-032 — Archival and recovery

| Field | Controlled value |
| --- | --- |
| Requirement ID | QMDB-DR-032 |
| Title | Archival and recovery |
| Requirement Statement | Archival, backup, restore, legal hold, cold evidence, projection rebuild, and integrity verification shall preserve authoritative relationships and shall defer periods and partitioning until approved evidence exists. |
| Rationale | Converts approved product, integrity, privacy, security, and operational obligations into verifiable relational ownership and lifecycle rules. |
| Priority | Critical |
| Data Domain | Continuity |
| Owning Module | All modules |
| Authoritative Source | Authoritative MySQL source versions plus rebuild rules |
| Tenant Scope | HYBRID |
| Affected Aggregates | Workspace; Support Access Grant; Break-Glass Grant; User Account; Person; Guardian Relationship; Administrative Area; Organization; Organization Verification; Quran Text Release; Competition Series; Competition Edition; Ruleset Version; Registration; Participant Snapshot; Judge Panel; Judge Assignment; Performance; Score Sheet; Panel Aggregation; Result; Appeal; Certificate; Record Provenance; Legacy Import Batch; Media Asset; Recitation Clip; Moderation Case; Notification; Projection; Export; Privacy Request; Audit Chain; Outbox Event; API Client; Webhook Subscription; Platform Configuration; Offline Synchronization |
| Affected Tables | All 250 logical tables (QMDB-TBL-001–QMDB-TBL-250) |
| Data Classification | QMDB-DCL-002; QMDB-DCL-004; QMDB-DCL-003; QMDB-DCL-001 |
| Integrity Rules | Use declared primary/candidate keys, foreign keys, checks, transaction policy, authorization and tests. |
| Versioning Rules | Apply the table lifecycle policy; immutable and official records are appended or superseded, not overwritten. |
| Lifecycle Rules | Use explicit expiry, withdrawal, revocation, supersession, archival or anonymization; no ordinary hard deletion of finalized records. |
| Privacy Rules | Minimize, classify and purpose-bind personal data; apply Guardian and Consent controls where required. |
| Security Rules | Deny by default; derive Workspace context server-side; protect secrets and sensitive fields. |
| Audit Rules | Record material commands, transitions, approvals, exceptional access, corrections and dispositions. |
| Expected Query Patterns | Use catalogued bounded access patterns and purpose-driven indexes with primary reads for authoritative transitions. |
| Related Functional Requirements | QMDB-FR-TEN-001; QMDB-FR-TEN-002; QMDB-FR-AUT-001; QMDB-FR-AUT-002; QMDB-FR-IAM-001; QMDB-FR-IAM-002; QMDB-FR-IAM-003; QMDB-FR-IAM-004 |
| Related Non-Functional Requirements | QMDB-NFR-DRC-001; QMDB-NFR-DRC-002 |
| Related Business Invariants | INV-026 |
| Related Threats | QMDB-THR-001; QMDB-THR-006 |
| Planned Implementation Phase | P2 |
| Verification Method | Schema-manifest review; migration tests; constraint, authorization, concurrency, lifecycle and recovery tests. |
| Required Evidence | Parsed YAML manifest, implemented migration DDL, database metadata, automated tests and audit/recovery evidence. |
| Acceptance Criteria | All mapped tables implement this single shall obligation with no orphan reference, silent history loss, tenant bypass, floating score, or unexplained exception. |
| Status | Approved Baseline |

## Requirement quality statement

Every Requirement Statement above contains one primary **shall**, names its authority/tenant/lifecycle boundary through the controlled fields, and is objectively verifiable. Open values remain formal open decisions or parameters, never prose placeholders.

## Related documents

- [Documentation index](../README.md)
- [Table and column dictionary](../data/05-table-and-column-data-dictionary.md)
- [Relationship and integrity catalog](../data/06-relationship-constraint-and-integrity-catalog.md)
- [P0-B04 traceability matrix](P0-B04-traceability-matrix.md)
