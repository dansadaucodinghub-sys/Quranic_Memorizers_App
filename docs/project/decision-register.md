# Architecture Decision Register

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Document version | 2.0.0 |
| Status | ADR-001 through ADR-020 locked; ADR-021 through ADR-041 and ADR-045 through ADR-055 approved for P1 implementation; ADR-042 through ADR-044 deferred/proposed |
| Current phase | P0 — Product Constitution and System Requirements |
| Last updated | 2026-08-24 |
| Document owner role | Architecture Governance |
| Approval status | Technical conventions approved under QMDB-P0-FRZ-001 by explicit project-owner instruction and successful P0 closeout validation; external-authority decisions remain open |

## Purpose

This register records locked architecture/product decisions, approved P1 technical conventions and clearly separated deferred proposals. Later work must not reverse or weaken a locked decision. A future review condition permits analysis; it does not unlock a decision. Replacement requires formal governance, a superseding ADR, impact analysis, migration and rollback plans, and updated requirements and traceability.

## Scope

The register covers 20 decisions locked by QMDB-P0-B01, 32 technical conventions approved for P1 implementation, and three deferred proposals requiring later domain or privacy validation. Unresolved choices are kept separately in the [Open-Decisions Register](open-decisions.md).

## Decisions

### ADR-001 — MySQL is the primary relational database

- **Status:** Locked.
- **Context:** QMDB requires transactional relational integrity, exact official records, mature operational tooling, and portable PHP support.
- **Decision:** MySQL LTS is the primary relational database.
- **Rationale:** A single governed relational source supports constraints, transactions, auditability, and predictable Core PHP integration.
- **Consequences:** Schemas, migrations, queries, backup, recovery, and observability must target the approved MySQL LTS line; topology remains open.
- **Security impact:** Database credentials, network paths, encryption, least privilege, auditing, and backup access require protected operational controls.
- **Future review conditions:** Review only if MySQL LTS cannot satisfy evidenced regulatory, integrity, resilience, or scale requirements; migration impact must be proven.

### ADR-002 — InnoDB is required for transactional business tables

- **Status:** Locked.
- **Context:** Competition, identity, consent, score, result, and audit workflows require atomic changes and referential integrity.
- **Decision:** All transactional business tables use InnoDB.
- **Rationale:** InnoDB provides transactions, row-level locking, crash recovery, and foreign-key enforcement needed by the domain.
- **Consequences:** Schema review must reject transactional business tables using other engines; transaction and concurrency behavior must be tested.
- **Security impact:** Reliable atomicity reduces partial authorization, score, consent, and audit states after failures.
- **Future review conditions:** Review only for a record class demonstrated to be non-transactional and outside authoritative business state, with architecture approval.

### ADR-003 — Core PHP 8.5 with strict typing

- **Status:** Locked.
- **Context:** The approved backend stack is Core PHP with explicit application architecture rather than a framework-defined domain.
- **Decision:** Backend code targets PHP 8.5 and begins PHP source files with `declare(strict_types=1)` where language rules permit.
- **Rationale:** Strict typing and a fixed runtime reduce coercion ambiguity and support maintainable contracts.
- **Consequences:** Composer constraints, coding standards, CI, production PHP-FPM, extensions, and developer environments must align with PHP 8.5.
- **Security impact:** Type discipline complements, but does not replace, runtime input validation and output encoding.
- **Future review conditions:** Review on PHP lifecycle/security-support changes through an explicit runtime-upgrade ADR.

### ADR-004 — Modular monolith before microservices

- **Status:** Locked.
- **Context:** QMDB has broad domains but needs consistent transactions, governance, and delivery before operational distribution complexity.
- **Decision:** Implement a secure modular monolith; do not prematurely split domain modules into independently deployed microservices.
- **Rationale:** Explicit module ownership preserves boundaries while retaining simple transactions, testing, and operations.
- **Consequences:** Modules use defined interfaces and events; extraction is not assumed and shared internals remain prohibited across boundaries.
- **Security impact:** A smaller deployment surface reduces service-to-service trust and secret sprawl, while module authorization remains mandatory.
- **Future review conditions:** Consider extraction only when measured scaling, isolation, ownership, or resilience evidence justifies its operational cost.

### ADR-005 — MVC presentation with domain and application separation

- **Status:** Locked.
- **Context:** Presentation changes must not own business rules or persistence behavior.
- **Decision:** Use MVC at the Presentation layer with distinct Domain, Application, Infrastructure, and Presentation layers.
- **Rationale:** Separation keeps invariants testable, interfaces replaceable, and domain behavior independent from HTTP and templates.
- **Consequences:** Controllers coordinate use cases; Domain/Application code owns business behavior; Infrastructure implements technical adapters.
- **Security impact:** Authorization and validation cannot rely solely on views, routes, or client controls.
- **Future review conditions:** UI/API delivery patterns may evolve without collapsing the four internal layers.

### ADR-006 — Shared-schema multi-tenancy

- **Status:** Locked.
- **Context:** Multiple organizations require isolation within one governed national platform.
- **Decision:** Use a shared-schema multi-tenant architecture.
- **Rationale:** The model supports consistent governance and operations while retaining an explicit Workspace boundary.
- **Consequences:** All queries, policies, indexes, uniqueness rules, jobs, cache keys, events, exports, and tests must account for tenant context.
- **Security impact:** Missing tenant predicates or context become critical authorization vulnerabilities requiring architecture and negative tests.
- **Future review conditions:** Review physical isolation options only for evidenced regulatory or scale needs without weakening logical isolation.

### ADR-007 — `workspace_id` on tenant-owned records

- **Status:** Locked.
- **Context:** Tenant ownership must be explicit and enforceable at the record boundary.
- **Decision:** Every tenant-owned record contains a non-null `workspace_id` identifying exactly one Workspace.
- **Rationale:** Explicit ownership supports scoped queries, constraints, audit, migrations, and incident analysis.
- **Consequences:** Ownership classification is required during data design; indirect inference alone is insufficient for tenant-owned records.
- **Security impact:** Authorization must compare authenticated tenant context to record ownership, not trust client input.
- **Future review conditions:** Only globally governed reference data may be exempt after explicit data-classification review.

### ADR-008 — Composite workspace-aware relational constraints

- **Status:** Locked.
- **Context:** Application filtering alone cannot prevent cross-workspace relational corruption.
- **Decision:** Use composite workspace-aware foreign keys and supporting unique indexes where appropriate for tenant-owned relationships.
- **Rationale:** Database-enforced consistency provides defense in depth against cross-tenant references.
- **Consequences:** Parent keys, child keys, index order, migrations, fixtures, and imports must preserve Workspace identity.
- **Security impact:** The database rejects cross-workspace relationships even if an application defect reaches persistence.
- **Future review conditions:** An exception requires documented impossibility or a global-reference classification and security approval.

### ADR-009 — RBAC plus ABAC authorization

- **Status:** Locked.
- **Context:** Role names alone cannot express workspace, geography, competition, assignment, resource state, risk, or time.
- **Decision:** Combine role-based access control, attribute-based access control, and explicit resource policies with deny-by-default behavior.
- **Rationale:** Contextual authority must be both understandable and sufficiently precise.
- **Consequences:** Every sensitive use case defines Role/Permission and relevant attributes; public IDs and UI visibility grant no authority.
- **Security impact:** Privilege escalation, confused-deputy behavior, stale assignment, and cross-scope access require negative tests and audit.
- **Future review conditions:** Authorization technology may change only if the combined semantics and policy evidence remain at least equivalent.

### ADR-010 — Server-authoritative exact-decimal scoring

- **Status:** Locked.
- **Context:** Official results cannot depend on browser state or binary floating-point behavior.
- **Decision:** The server calculates official scores using exact `DECIMAL` values; `FLOAT` and `DOUBLE` are prohibited for official scores.
- **Rationale:** Deterministic exact arithmetic supports reproducibility, review, and consistent ranking.
- **Consequences:** Client totals are previews; server validation, rounding policy, aggregation, and tie-break behavior use the identified Ruleset Version.
- **Security impact:** Server recalculation prevents tampered clients from establishing authoritative totals.
- **Future review conditions:** None for client authority; exact numeric implementation may evolve only with equivalence tests and rules governance.

### ADR-011 — Versioned official records

- **Status:** Locked.
- **Context:** Silent mutation destroys the evidence required to trust competition history.
- **Decision:** Submitted, finalized, locked, corrected, revoked, or superseded official records preserve identifiable versions and prior state.
- **Rationale:** Version history makes changes transparent, auditable, and recoverable.
- **Consequences:** Corrections create new versions with reason, actor, approvals, evidence, timestamps, and supersession relationships.
- **Security impact:** Tamper evidence and accountability improve; access to version history remains sensitivity-scoped.
- **Future review conditions:** Storage optimization may archive versions but cannot erase required history or provenance.

### ADR-012 — Controlled approval for sensitive corrections

- **Status:** Locked.
- **Context:** One actor must not silently rewrite high-impact official outcomes.
- **Decision:** Sensitive reopening, correction, revocation, and exceptional actions require policy-defined approval and separation of duties.
- **Rationale:** Independent review reduces error, fraud, coercion, and undisclosed conflicts.
- **Consequences:** Workflows must record requester, reviewer, rationale, evidence, decision, time, and resultant version.
- **Security impact:** Step-Up Authentication, scoped approval authority, conflict controls, and non-self-approval are required where policy specifies.
- **Future review conditions:** Exact approval matrices await domain governance, but control cannot be removed.

### ADR-013 — Transactional outbox plus Redis Streams

- **Status:** Locked.
- **Context:** Live updates and asynchronous work must not diverge from committed business state.
- **Decision:** Persist outbound business events transactionally with their state change and deliver them through Redis Streams.
- **Rationale:** The outbox closes the commit/publish gap; Redis Streams supports sequenced, recoverable consumers.
- **Consequences:** Consumers are idempotent, delivery is at least once, retries are bounded, and public SSE uses projections rather than authoritative storage.
- **Security impact:** Event payloads require minimization, tenant context, integrity controls, access restrictions, and replay-safe processing.
- **Future review conditions:** Transport may be reviewed for measured requirements only if transactional publication and idempotent delivery semantics remain.

### ADR-014 — Object storage for audio and video

- **Status:** Locked.
- **Context:** Large media bytes do not belong in relational transactional tables.
- **Decision:** Store audio and video in private S3-compatible object storage; MySQL stores metadata, ownership, Consent, hashes, lifecycle, and references.
- **Rationale:** Object storage supports scalable media lifecycle and controlled delivery while keeping relational workloads predictable.
- **Consequences:** Upload quarantine, hash validation, private access, derivative generation, retention, and deletion workflows cross explicit boundaries.
- **Security impact:** Evidence Masters remain private; scoped credentials, encryption, malware scanning, and publication policy are mandatory.
- **Future review conditions:** Provider selection and topology remain open; relational BLOB storage for these files remains prohibited.

### ADR-015 — Cryptographically verifiable results and certificates

- **Status:** Locked.
- **Context:** Public verification must detect altered representations without exposing sensitive source data.
- **Decision:** Identified Result and Certificate representations support cryptographic verification tied to versioned records and managed keys.
- **Rationale:** Signatures and hashes support authenticity and tamper evidence across online and exported representations.
- **Consequences:** Canonical serialization, key identifiers, rotation, revocation, verification status, and safe public payloads must be specified.
- **Security impact:** Key custody is high risk and requires separation of duties, protected storage, rotation, audit, and incident response.
- **Future review conditions:** Algorithms and key services may evolve through cryptographic agility; verifiability and history cannot be removed.

### ADR-016 — Guardian and child-safety foundation

- **Status:** Locked.
- **Context:** Minors may participate, appear in records, and have recordings or community content.
- **Decision:** Guardian, Consent, Minor privacy, reporting, moderation, and publication controls are foundational platform capabilities.
- **Rationale:** Safety cannot be retrofitted after public and social features expose data.
- **Consequences:** Conservative defaults apply when status or authority is uncertain; age and verification policy require qualified review.
- **Security impact:** No public Minor contact data, precise location, identity documents, or unauthorized media; access and changes are audited.
- **Future review conditions:** Policies may become more specific after legal and child-safety review but cannot weaken the foundation without formal approval.

### ADR-017 — Competition resources protected from social workloads

- **Status:** Locked.
- **Context:** Media, feeds, recommendations, and analytics can consume disproportionate compute and I/O during live events.
- **Decision:** Reserve and isolate capacity for authentication, judging, scoring, results, certificates, and competition operations.
- **Rationale:** Engagement workloads must not degrade official event integrity or availability.
- **Consequences:** Separate queues/pools, limits, priorities, back-pressure, circuit breakers, and load tests are required where applicable.
- **Security impact:** Resource exhaustion and denial-of-service risks are contained across workload classes.
- **Future review conditions:** Isolation mechanisms may evolve based on measurement; the priority boundary remains.

### ADR-018 — Declarative non-executable scoring rules

- **Status:** Locked.
- **Context:** Administratively supplied executable code would create remote execution, integrity, and governance risks.
- **Decision:** Rulesets use an approved, versioned declarative model and cannot contain arbitrary PHP, SQL, JavaScript, shell, or executable scripts.
- **Rationale:** Declarative rules can be validated, reviewed, reproduced, and safely evaluated.
- **Consequences:** Allowed operations, numeric precision, validation, version lock, and test vectors must be specified before rules implementation.
- **Security impact:** Eliminates administrator-supplied code execution and reduces injection risk.
- **Future review conditions:** The declarative vocabulary may expand through reviewed schema versions; arbitrary execution remains prohibited.

### ADR-019 — No SQL in controllers or templates

- **Status:** Locked.
- **Context:** Presentation-layer persistence logic bypasses module boundaries, policy, transactions, and testability.
- **Decision:** Controllers and templates contain no SQL; persistence is implemented behind Infrastructure interfaces used by Application/Domain workflows.
- **Rationale:** Clear ownership makes security, query performance, transactions, and migrations reviewable.
- **Consequences:** Architecture tests must detect forbidden dependencies and SQL usage in Presentation code.
- **Security impact:** Centralized persistence controls improve parameterization, tenant scoping, least privilege, and audit consistency.
- **Future review conditions:** None for raw SQL in Presentation; persistence technologies may change behind module interfaces.

### ADR-020 — No normal hard deletion of finalized official records

- **Status:** Locked.
- **Context:** Hard deletion of finalized records erases provenance and may invalidate results and certificates.
- **Decision:** Normal application workflows cannot hard-delete finalized official records; approved lifecycle states, revocation, supersession, archival, or legally governed anonymization are used.
- **Rationale:** History must remain interpretable and auditable while privacy/legal obligations are handled explicitly.
- **Consequences:** Destructive maintenance is exceptional, governed, evidenced, recoverable where possible, and reconciled with dependent records.
- **Security impact:** Reduces malicious or accidental erasure; access to archived or restricted history remains controlled.
- **Future review conditions:** Qualified legal/privacy obligations may require a governed disposition process, never an undocumented normal delete path.

## QMDB-P0-B03 technical conventions

The following decisions are approved for P1 implementation but are not locked architecture. They operationalize QMDB-P0-B03 without modifying ADR-001 through ADR-020. Future change requires controlled review by the identified governance roles.

### ADR-021 — Quality-attribute parameter register governs unapproved targets

- **Status:** Approved for P1 Implementation.
- **Context:** Latency, throughput, availability, recovery, retention, Session, rate and alert values require evidence and accountable approval.
- **Decision:** `docs/operations/quality-attribute-parameter-register.md` is the sole B03 source for unapproved/configurable quality targets; requirements reference parameter IDs and use conservative behavior until approval.
- **Rationale:** Parameter records keep specifications measurable without inventing production promises.
- **Consequences:** An approved value needs owner, evidence, decision reference, affected tests and retained history.
- **Security impact:** Prevents silent weakening or arbitrary tuning of safety thresholds.
- **Future review conditions:** Governance may approve individual values; the controlled register and history remain required.

### ADR-022 — Service criticality and workload tiering

- **Status:** Approved for P1 Implementation.
- **Context:** Competition-critical work must survive pressure from public, social, media and analytics demand.
- **Decision:** Capabilities use controlled criticality/recovery tiers and five workload classes; social and analytics degrade before authorization, scoring, finalization and verification.
- **Rationale:** Explicit tiering makes capacity, degraded mode and recovery order testable.
- **Consequences:** New capabilities require tier, dependencies, prohibited degradation, parameters and runbooks.
- **Security impact:** Availability pressure cannot authorize integrity or access-control bypass.
- **Future review conditions:** Tier placement may change through business-impact and load evidence; official integrity constraints remain fixed.

### ADR-023 — Five-level data-classification scheme

- **Status:** Approved for P1 Implementation.
- **Context:** Public, operational, personal, restricted and cryptographic data need stable handling rules.
- **Decision:** QMDB-DCL-001 through QMDB-DCL-005 govern storage, transmission, logging, display, export, support, backup, incident and disposition treatment.
- **Rationale:** Consistent classification reduces accidental disclosure and overbroad access.
- **Consequences:** Every new data element is classified before persistence/logging and derived public data needs an explicit allowlist.
- **Security impact:** Secrets and restricted data remain outside public, ordinary support and ordinary telemetry paths.
- **Future review conditions:** Classification changes require Privacy, Security and Records review plus migration/purge evidence.

### ADR-024 — STRIDE-based threat modelling is required

- **Status:** Approved for P1 Implementation.
- **Context:** QMDB spans browser, application, database, cache, media, integration, offline and operational trust boundaries.
- **Decision:** STRIDE is the structured completeness method for the controlled threat model; material boundary changes require updated threats, controls, risks and acceptance evidence.
- **Rationale:** A repeatable method reduces missed spoofing, tampering, repudiation, disclosure, denial and privilege threats.
- **Consequences:** Threat modelling becomes a design/release input rather than an optional narrative.
- **Security impact:** Critical threats require preventive plus detective or recovery control coverage.
- **Future review conditions:** Another recognized method may supplement STRIDE; traceable threat coverage remains required.

### ADR-025 — Independent penetration testing before national rollout

- **Status:** Approved for P1 Implementation.
- **Context:** Internal tests cannot alone establish sufficient assurance for national-scale privileged, scoring, tenant, media, certificate and offline boundaries.
- **Decision:** An independent, scoped penetration/security assessment is required before national production rollout and after qualifying high-impact change or incident.
- **Rationale:** Independent verification reduces systemic blind spots.
- **Consequences:** Provider, independence, scope, finding severity, retest and risk-acceptance authority remain open decisions.
- **Security impact:** National rollout is blocked when required independent evidence is absent.
- **Future review conditions:** Scope/frequency may be refined from risk and change evidence; independence cannot be silently waived.

### ADR-026 — Manual accessibility verification is a release requirement

- **Status:** Approved for P1 Implementation.
- **Context:** Automated scanning cannot prove keyboard, screen-reader, RTL, cognitive, live-region or transaction usability.
- **Decision:** WCAG 2.2 Level AA assurance combines automation with manual keyboard, assistive-technology, RTL, mobile, reflow, reduced-motion and low-bandwidth verification.
- **Rationale:** Human evaluation is necessary for critical workflow conformance.
- **Consequences:** Affected releases retain manual evidence, defects and retest; conformance authority remains open.
- **Security impact:** Accessible authentication and confirmation controls reduce exclusion and unsafe workaround pressure.
- **Future review conditions:** Supported technology combinations may evolve through user and platform evidence.

### ADR-027 — Immutable or separately controlled backup copies

- **Status:** Approved for P1 Implementation.
- **Context:** Online or same-control-plane backups may be destroyed by operator error, compromise or ransomware.
- **Decision:** Recovery design includes encrypted integrity-checked immutable or separately controlled copies, distinct credentials/custody, PITR and verified restoration.
- **Rationale:** Backup success without recoverable separation is insufficient continuity evidence.
- **Consequences:** Topology, cadence, retention, geography and RTO/RPO remain open; restore testing is mandatory.
- **Security impact:** Limits destructive compromise and bulk backup access.
- **Future review conditions:** Technology may vary, but independent failure-domain and restore evidence remain required.

### ADR-028 — External audit checkpoints

- **Status:** Approved for P1 Implementation.
- **Context:** A privileged actor controlling the primary audit store could rewrite events and local hashes.
- **Decision:** Audit chains create governed checkpoints in separately controlled storage so whole-chain rewriting becomes externally detectable.
- **Rationale:** Local hash linkage alone cannot prove history against full-store compromise.
- **Consequences:** Checkpoint service/custody, cadence and retention are open; verification failure starts controlled incident handling.
- **Security impact:** Improves tamper evidence for scores, results, certificates, consent and privileged access.
- **Future review conditions:** Checkpoint mechanism may change if equivalent independent evidence is demonstrated.

### ADR-029 — Separate workload classes and evidence-led extraction

- **Status:** Approved for P1 Implementation.
- **Context:** Scaling all work identically can starve competition operations or prematurely fragment the modular monolith.
- **Decision:** Web/worker/queue/database resources distinguish five workload classes; horizontal scaling and read models are preferred before service extraction, which requires measured operational evidence.
- **Rationale:** Isolation protects critical operations while preserving modular-monolith simplicity.
- **Consequences:** Capacity variables, SLIs, load tests and extraction criteria are required.
- **Security impact:** Social/media/report overload cannot justify integrity shortcuts or unreviewed microservices.
- **Future review conditions:** A bounded capability may be extracted only after approved evidence and architecture/security review.

## QMDB-P0-B04 technical conventions and deferred proposals

These schema conventions translate locked ADR-001 through ADR-020 into a deterministic logical model. ADR-030 through ADR-041 and ADR-045 through ADR-052 are approved for P1 implementation. ADR-042 through ADR-044 remain proposed pending later qualified validation. Linked decisions are resolved only where the closeout consistency section states so.

### ADR-030 — Internal primary-key convention

- **Status:** Approved for P1 Implementation.
- **Context:** QMDB-P0-B04 requires an implementation-ready MySQL convention without inventing unresolved policy or provider values.
- **Decision:** All relational tables use an internal `BIGINT UNSIGNED` primary key candidate; public identifiers remain separate.
- **Rationale:** Compact joins, predictable InnoDB clustering and simple composite tenant keys; OD-048 was resolved by approval of this convention.
- **Consequences:** P1 migration framework must not expose the key as authorization or sole public reference.
- **Security impact:** Preserve tenant isolation, least privilege, exact official records, private data, auditable change and no secret material in ordinary tables.
- **Future review conditions:** Approval, supersession or change requires affected manifest IDs, migrations, data/backfill, compatibility, rollback, security/privacy and test evidence.

### ADR-031 — Public identifier convention

- **Status:** Approved for P1 Implementation.
- **Context:** QMDB-P0-B04 requires an implementation-ready MySQL convention without inventing unresolved policy or provider values.
- **Decision:** Externally addressable aggregates use application-generated UUIDv7 encoded as `BINARY(16)`; other rows are addressed through their aggregate.
- **Rationale:** Sortable compact identifiers align with the UUID ecosystem; OD-047 was resolved by approval of this convention, while implementation must still verify the chosen library and serialization.
- **Consequences:** Public IDs are opaque but not secret and never grant access.
- **Security impact:** Preserve tenant isolation, least privilege, exact official records, private data, auditable change and no secret material in ordinary tables.
- **Future review conditions:** Approval, supersession or change requires affected manifest IDs, migrations, data/backfill, compatibility, rollback, security/privacy and test evidence.

### ADR-032 — Table naming convention

- **Status:** Approved for P1 Implementation.
- **Context:** QMDB-P0-B04 requires an implementation-ready MySQL convention without inventing unresolved policy or provider values.
- **Decision:** Logical tables use lowercase plural snake_case with canonical domain nouns and no meaningless prefix.
- **Rationale:** Deterministic names preserve domain language and migration readability.
- **Consequences:** Renames require compatibility/migration and manifest updates.
- **Security impact:** Preserve tenant isolation, least privilege, exact official records, private data, auditable change and no secret material in ordinary tables.
- **Future review conditions:** Approval, supersession or change requires affected manifest IDs, migrations, data/backfill, compatibility, rollback, security/privacy and test evidence.

### ADR-033 — Constraint naming convention

- **Status:** Approved for P1 Implementation.
- **Context:** QMDB-P0-B04 requires an implementation-ready MySQL convention without inventing unresolved policy or provider values.
- **Decision:** Future DDL uses deterministic `pk_`, `fk_`, `uq_`, and `ck_` names derived from table/purpose with collision-checked shortening.
- **Rationale:** Named constraints make deployment and incident evidence traceable within MySQL's 64-character limit.
- **Consequences:** CHECK names remain schema-unique.
- **Security impact:** Preserve tenant isolation, least privilege, exact official records, private data, auditable change and no secret material in ordinary tables.
- **Future review conditions:** Approval, supersession or change requires affected manifest IDs, migrations, data/backfill, compatibility, rollback, security/privacy and test evidence.

### ADR-034 — Index naming convention

- **Status:** Approved for P1 Implementation.
- **Context:** QMDB-P0-B04 requires an implementation-ready MySQL convention without inventing unresolved policy or provider values.
- **Decision:** Future DDL uses deterministic `ix_`/`ux_` names tied to catalogued access purpose and QMDB-IDX identity.
- **Rationale:** Purpose-led indexes reduce speculative duplication and ease query-plan review.
- **Consequences:** Migration generation may coalesce redundant candidates only with traceable evidence.
- **Security impact:** Preserve tenant isolation, least privilege, exact official records, private data, auditable change and no secret material in ordinary tables.
- **Future review conditions:** Approval, supersession or change requires affected manifest IDs, migrations, data/backfill, compatibility, rollback, security/privacy and test evidence.

### ADR-035 — Status modelling convention

- **Status:** Approved for P1 Implementation.
- **Context:** QMDB-P0-B04 requires an implementation-ready MySQL convention without inventing unresolved policy or provider values.
- **Decision:** Stable technical states use checked strings; governed/extensible classifications use relational taxonomies; historically meaningful taxonomies are versioned; MySQL ENUM is not the general baseline.
- **Rationale:** State and taxonomy evolution have different governance and migration costs.
- **Consequences:** State-machine and taxonomy references must remain synchronized.
- **Security impact:** Preserve tenant isolation, least privilege, exact official records, private data, auditable change and no secret material in ordinary tables.
- **Future review conditions:** Approval, supersession or change requires affected manifest IDs, migrations, data/backfill, compatibility, rollback, security/privacy and test evidence.

### ADR-036 — Controlled taxonomy convention

- **Status:** Approved for P1 Implementation.
- **Context:** QMDB-P0-B04 requires an implementation-ready MySQL convention without inventing unresolved policy or provider values.
- **Decision:** Every taxonomy has an owner, scope, extensibility class, source, version, checksum/review and historical preservation rule.
- **Rationale:** Prevents labels/codes from silently acquiring authority or changing historical meaning.
- **Consequences:** Authoritative geography, Quran and competition categories remain unpopulated until approved.
- **Security impact:** Preserve tenant isolation, least privilege, exact official records, private data, auditable change and no secret material in ordinary tables.
- **Future review conditions:** Approval, supersession or change requires affected manifest IDs, migrations, data/backfill, compatibility, rollback, security/privacy and test evidence.

### ADR-037 — Default MySQL transaction-isolation convention

- **Status:** Approved for P1 Implementation.
- **Context:** QMDB-P0-B04 requires an implementation-ready MySQL convention without inventing unresolved policy or provider values.
- **Decision:** Use `READ COMMITTED` as the ordinary application-session baseline while authoritative transitions use explicit primary locking/version/unique/idempotency controls.
- **Rationale:** Reduces long snapshot/gap-lock surprises without weakening explicit authoritative transactions; OD-056 was resolved by approval of this convention.
- **Consequences:** P1 connection setup must enforce and test the approved isolation plus explicit concurrency controls; production topology remains separately governed.
- **Security impact:** Preserve tenant isolation, least privilege, exact official records, private data, auditable change and no secret material in ordinary tables.
- **Future review conditions:** Approval, supersession or change requires affected manifest IDs, migrations, data/backfill, compatibility, rollback, security/privacy and test evidence.

### ADR-038 — Optimistic concurrency convention

- **Status:** Approved for P1 Implementation.
- **Context:** QMDB-P0-B04 requires an implementation-ready MySQL convention without inventing unresolved policy or provider values.
- **Decision:** Mutable aggregate roots expose a positive monotonic version checked in update predicates; immutable lineages use distinct version-number rows.
- **Rationale:** Detects stale commands without broad locks while preserving submitted history.
- **Consequences:** Conflict responses never silently retry a changed business decision.
- **Security impact:** Preserve tenant isolation, least privilege, exact official records, private data, auditable change and no secret material in ordinary tables.
- **Future review conditions:** Approval, supersession or change requires affected manifest IDs, migrations, data/backfill, compatibility, rollback, security/privacy and test evidence.

### ADR-039 — Record-versioning convention

- **Status:** Approved for P1 Implementation.
- **Context:** QMDB-P0-B04 requires an implementation-ready MySQL convention without inventing unresolved policy or provider values.
- **Decision:** Official/configuration lineages append immutable versions and use explicit effective, supersession, revocation or correction records.
- **Rationale:** Reproducibility requires identifying exact inputs and former states.
- **Consequences:** Generic update/delete repositories are prohibited for immutable lineages.
- **Security impact:** Preserve tenant isolation, least privilege, exact official records, private data, auditable change and no secret material in ordinary tables.
- **Future review conditions:** Approval, supersession or change requires affected manifest IDs, migrations, data/backfill, compatibility, rollback, security/privacy and test evidence.

### ADR-040 — Soft-deletion restrictions

- **Status:** Approved for P1 Implementation.
- **Context:** QMDB-P0-B04 requires an implementation-ready MySQL convention without inventing unresolved policy or provider values.
- **Decision:** Generic `deleted_at` is limited to approved ordinary removable records; official, audit, Consent and canonical Quran history use explicit lifecycle states.
- **Rationale:** Soft deletion alone cannot express revocation, withdrawal, supersession, anonymization or hold.
- **Consequences:** Active-key uniqueness and disposition policy must be designed per table.
- **Security impact:** Preserve tenant isolation, least privilege, exact official records, private data, auditable change and no secret material in ordinary tables.
- **Future review conditions:** Approval, supersession or change requires affected manifest IDs, migrations, data/backfill, compatibility, rollback, security/privacy and test evidence.

### ADR-041 — JSON document convention

- **Status:** Approved for P1 Implementation.
- **Context:** QMDB-P0-B04 requires an implementation-ready MySQL convention without inventing unresolved policy or provider values.
- **Decision:** JSON is limited to bounded schema-versioned definitions, snapshots, provider copies, events, media metadata and projections with owner/checksum/size/sensitivity rules.
- **Rationale:** Retains flexibility without replacing relational authority.
- **Consequences:** Frequently queried properties are normalized/generated; no core EAV design.
- **Security impact:** Preserve tenant isolation, least privilege, exact official records, private data, auditable change and no secret material in ordinary tables.
- **Future review conditions:** Approval, supersession or change requires affected manifest IDs, migrations, data/backfill, compatibility, rollback, security/privacy and test evidence.

### ADR-042 — Canonical Arabic collation proposal

- **Status:** Proposed.
- **Context:** QMDB-P0-B04 requires an implementation-ready MySQL convention without inventing unresolved policy or provider values.
- **Decision:** Store exact canonical Quran text separately using the `utf8mb4_0900_bin` candidate and checksum; OD-054/OD-063 require qualified source/collation validation.
- **Rationale:** Binary comparison avoids accent/case-insensitive equivalence changing canonical integrity.
- **Consequences:** Search normalization remains a separate derivative under OD-055.
- **Security impact:** Preserve tenant isolation, least privilege, exact official records, private data, auditable change and no secret material in ordinary tables.
- **Future review conditions:** Approval, supersession or change requires affected manifest IDs, migrations, data/backfill, compatibility, rollback, security/privacy and test evidence.

### ADR-043 — Normalized email strategy

- **Status:** Proposed.
- **Context:** QMDB-P0-B04 requires an implementation-ready MySQL convention without inventing unresolved policy or provider values.
- **Decision:** Store encrypted email plus approved normalized keyed lookup hash and nullable active-key uniqueness; normalization is unresolved under OD-051.
- **Rationale:** Supports private deterministic lookup and history without plaintext or partial indexes.
- **Consequences:** P2 requires test vectors, key design and race/backfill tests.
- **Security impact:** Preserve tenant isolation, least privilege, exact official records, private data, auditable change and no secret material in ordinary tables.
- **Future review conditions:** Approval, supersession or change requires affected manifest IDs, migrations, data/backfill, compatibility, rollback, security/privacy and test evidence.

### ADR-044 — Normalized phone strategy

- **Status:** Proposed.
- **Context:** QMDB-P0-B04 requires an implementation-ready MySQL convention without inventing unresolved policy or provider values.
- **Decision:** Store encrypted phone plus approved normalized keyed lookup hash and nullable active-key uniqueness; normalization is unresolved under OD-052.
- **Rationale:** Supports private deterministic lookup and history without plaintext or partial indexes.
- **Consequences:** P2 requires numbering-policy test vectors and key design.
- **Security impact:** Preserve tenant isolation, least privilege, exact official records, private data, auditable change and no secret material in ordinary tables.
- **Future review conditions:** Approval, supersession or change requires affected manifest IDs, migrations, data/backfill, compatibility, rollback, security/privacy and test evidence.

### ADR-045 — Encryption metadata convention

- **Status:** Approved for P1 Implementation.
- **Context:** QMDB-P0-B04 requires an implementation-ready MySQL convention without inventing unresolved policy or provider values.
- **Decision:** Field encryption envelopes record algorithm/version/key identifier/context while key material remains in KMS/HSM; OD-058 selects fields and OD-040 governs custody.
- **Rationale:** Enables rotation and cryptographic agility without secret storage in ordinary tables.
- **Consequences:** Indexes use separate approved lookup hashes, never ciphertext assumptions.
- **Security impact:** Preserve tenant isolation, least privilege, exact official records, private data, auditable change and no secret material in ordinary tables.
- **Future review conditions:** Approval, supersession or change requires affected manifest IDs, migrations, data/backfill, compatibility, rollback, security/privacy and test evidence.

### ADR-046 — Audit event canonicalization

- **Status:** Approved for P1 Implementation.
- **Context:** QMDB-P0-B04 requires an implementation-ready MySQL convention without inventing unresolved policy or provider values.
- **Decision:** Audit Events use a versioned deterministic canonical payload, actor/resource/correlation context, previous/event hashes and external checkpoints.
- **Rationale:** Hash linkage is only reliable when serialization is stable and separately anchored.
- **Consequences:** Corrections are linked events; operational logs do not replace the audit chain.
- **Security impact:** Preserve tenant isolation, least privilege, exact official records, private data, auditable change and no secret material in ordinary tables.
- **Future review conditions:** Approval, supersession or change requires affected manifest IDs, migrations, data/backfill, compatibility, rollback, security/privacy and test evidence.

### ADR-047 — Outbox payload versioning

- **Status:** Approved for P1 Implementation.
- **Context:** QMDB-P0-B04 requires an implementation-ready MySQL convention without inventing unresolved policy or provider values.
- **Decision:** Every Outbox Event names event type, schema version, aggregate identity/version, occurrence/availability time and bounded payload; consumers support declared compatibility.
- **Rationale:** Allows replay/evolution while keeping event and authoritative transaction atomic.
- **Consequences:** Payloads contain no secrets and only purpose-minimized sensitive data.
- **Security impact:** Preserve tenant isolation, least privilege, exact official records, private data, auditable change and no secret material in ordinary tables.
- **Future review conditions:** Approval, supersession or change requires affected manifest IDs, migrations, data/backfill, compatibility, rollback, security/privacy and test evidence.

### ADR-048 — Idempotency record design

- **Status:** Approved for P1 Implementation.
- **Context:** QMDB-P0-B04 requires an implementation-ready MySQL convention without inventing unresolved policy or provider values.
- **Decision:** Idempotency records scope a high-entropy key hash to Workspace/operation, bind request hash and persisted outcome reference, and expire under policy.
- **Rationale:** Retries, offline sync and at-least-once delivery must not duplicate business effects.
- **Consequences:** A reused key with a different request hash is rejected and audited.
- **Security impact:** Preserve tenant isolation, least privilege, exact official records, private data, auditable change and no secret material in ordinary tables.
- **Future review conditions:** Approval, supersession or change requires affected manifest IDs, migrations, data/backfill, compatibility, rollback, security/privacy and test evidence.

### ADR-049 — Projection naming convention

- **Status:** Approved for P1 Implementation.
- **Context:** QMDB-P0-B04 requires an implementation-ready MySQL convention without inventing unresolved policy or provider values.
- **Decision:** Rebuildable read models use purpose-specific `_projection`/`_projections` names and store source/schema version and projected time.
- **Rationale:** Makes non-authority and rebuild behavior visible in schema/repositories.
- **Consequences:** Projection freshness and rebuild objectives remain OD-070.
- **Security impact:** Preserve tenant isolation, least privilege, exact official records, private data, auditable change and no secret material in ordinary tables.
- **Future review conditions:** Approval, supersession or change requires affected manifest IDs, migrations, data/backfill, compatibility, rollback, security/privacy and test evidence.

### ADR-050 — Archive-table convention

- **Status:** Approved for P1 Implementation.
- **Context:** QMDB-P0-B04 requires an implementation-ready MySQL convention without inventing unresolved policy or provider values.
- **Decision:** No generic shadow/archive table is created initially; an approved archive preserves original identity/classification/checksum/hold and uses a purpose-specific schema/store.
- **Rationale:** Premature duplicate tables create drift and can evade authorization/retention.
- **Consequences:** Archive storage and thresholds remain OD-060/OD-069.
- **Security impact:** Preserve tenant isolation, least privilege, exact official records, private data, auditable change and no secret material in ordinary tables.
- **Future review conditions:** Approval, supersession or change requires affected manifest IDs, migrations, data/backfill, compatibility, rollback, security/privacy and test evidence.

### ADR-051 — Schema manifest as migration input

- **Status:** Approved for P1 Implementation.
- **Context:** QMDB-P0-B04 requires an implementation-ready MySQL convention without inventing unresolved policy or provider values.
- **Decision:** The approved YAML logical manifest is a reviewed input to future migration generation/verification, never executable by itself.
- **Rationale:** One machine inventory reduces Markdown/DDL drift and enables structural gates.
- **Consequences:** Generation tooling must pin schema/version/checksum and reject unresolved symbolic parameters.
- **Security impact:** Preserve tenant isolation, least privilege, exact official records, private data, auditable change and no secret material in ordinary tables.
- **Future review conditions:** Approval, supersession or change requires affected manifest IDs, migrations, data/backfill, compatibility, rollback, security/privacy and test evidence.

### ADR-052 — Migration-group ordering

- **Status:** Approved for P1 Implementation.
- **Context:** QMDB-P0-B04 requires an implementation-ready MySQL convention without inventing unresolved policy or provider values.
- **Decision:** Future migrations follow QMDB-MIG-001–020 dependency order with expand/migrate/contract, resumable backfill, later FK addition where necessary and forward compensation for official data.
- **Rationale:** MySQL immediate foreign keys and production DDL risk require explicit sequencing.
- **Consequences:** Every group produces metadata, constraint, tenant, rollback/restore and compatibility evidence.
- **Security impact:** Preserve tenant isolation, least privilege, exact official records, private data, auditable change and no secret material in ordinary tables.
- **Future review conditions:** Approval, supersession or change requires affected manifest IDs, migrations, data/backfill, compatibility, rollback, security/privacy and test evidence.

### ADR-053 — Bounded deadlock retry convention

- **Status:** Approved for P1 Implementation.
- **Context:** MySQL deadlocks and lock-wait conflicts are expected under concurrent transactional work, but unbounded or non-idempotent retry would duplicate effects or amplify load.
- **Decision:** Retry only classified transient database failures around an entire idempotent transaction; use a configurable finite attempt limit, jittered backoff, fresh transaction state, correlation telemetry, and fail closed when the bound is exhausted.
- **Rationale:** P1 requires a safe transaction abstraction before domain repositories exist, while numerical limits remain controlled parameters.
- **Consequences:** P1-B05 defines the contract and tests; each later command declares whether retry is safe. No controller, consumer, or repository performs ad hoc retry.
- **Security impact:** Prevents replay, denial-of-service amplification, stale authorization reuse, and duplicate official effects.
- **Future review conditions:** Change requires concurrency evidence, idempotency analysis, operational telemetry, compatibility and rollback review.

### ADR-054 — Repository, namespace, and module-directory convention

- **Status:** Approved for P1 Implementation.
- **Context:** QMDB-P1-B01 cannot create a coherent Core PHP repository without one PSR-4 namespace and directory boundary.
- **Decision:** Use the `Qmdb\` namespace mapped to `src/`, `Qmdb\Tests\` mapped to `tests/`, `public/index.php` as the HTTP front controller, `bin/console` as the CLI entry point, and future modules under `src/Modules/<Module>/{Domain,Application,Infrastructure,Presentation}`. Cross-module primitives live only in purpose-specific `src/Shared/` packages; bootstrap composition lives in `src/Bootstrap/`.
- **Rationale:** A deterministic layout enables autoloading, architecture tests and module ownership without introducing a framework.
- **Consequences:** P1-B01 creates only foundation directories and smoke paths; domain modules remain excluded. Namespace or layer changes require controlled architecture review.
- **Security impact:** Explicit boundaries reduce unauthorized dependency direction, presentation-owned business logic and accidental cross-module access.
- **Future review conditions:** Change requires architecture-test, autoload, compatibility, migration and repository-impact evidence.

### ADR-055 — P1 test, static-analysis, and coding-standard convention

- **Status:** Approved for P1 Implementation.
- **Context:** P1-B01 requires executable verification and cannot rely on documentation-only completion.
- **Decision:** Use PHPUnit for executable tests, PHPStan for static analysis, PSR-12-compatible automated style enforcement, Composer validation/audit scripts, and architecture tests that enforce strict typing and layer/module boundaries. Exact compatible dependency versions are locked by `composer.lock` during P1-B01 after environment verification.
- **Rationale:** These focused tools provide repeatable quality gates without adopting a full-stack PHP framework or fabricating versions before Composer resolves the PHP 8.5-compatible set.
- **Consequences:** Every dependency requires a documented purpose; P1-B10 promotes the same local commands into CI.
- **Security impact:** Repeatable analysis, dependency auditing and boundary tests reduce unsafe drift and supply-chain ambiguity.
- **Future review conditions:** Tool replacement requires equivalent-or-stronger evidence, migration cost, compatibility, security and rollback analysis.

### ADR-056 — Profile-claim pairing and non-destructive Person canonicalization

- **Status:** Approved for P3-B05 implementation.
- **Context:** A Person registry code is not proof of Account ownership, while duplicate resolution can corrupt
  sensitive relationships if it silently transfers links or deletes a Person.
- **Decision:** Profile claims require an expiring, one-time, attempt-bounded pairing secret stored only as a
  versioned HMAC plus Guardian or exact platform-record-review authorization and claimant acceptance. Duplicate
  resolution is a consent-gated, conflict-checked, reviewer-operated canonicalization that creates an immutable
  alias and retires the source Person; it never deletes a Person or performs automatic matching.
- **Rationale:** The split prevents a registry-code lookup, pairing secret, authorization record, or name similarity
  from independently granting identity control. Explicit consent, step-up and row-level constraints keep the
  resulting Account-to-Person link and downstream consolidation bounded and auditable.
- **Consequences:** Pairing material is never recoverable or emitted to Audit, notifications or browser storage.
  Assertions use only the term “QMDB profile record status” and grant no permission. Canonicalization must run in
  one caller-owned transaction through fixed participants, recheck current management authority, reject conflicts,
  preserve source history and keep Organization-affiliation reassignment tenant-safe.
- **Security impact:** The decision mitigates pairing replay, cross-Account claims, unilateral guardian/platform
  action, false legal-verification claims, consent bypass, alias chains/cycles, partial consolidation and Person
  deletion. Production key custody, retention, legal meaning and an independent second-review policy remain open.
- **Future review conditions:** Change requires privacy and records-governance approval, migration/rollback and
  concurrency evidence, updated threat/risk records, and no weakening of the pairing, consent or alias controls.

## QMDB-P0 closeout consistency review

QMDB-P0-CLOSE validated ADR-001 through ADR-020 without changing their status or meaning. ADR-021 through ADR-041 and ADR-045 through ADR-055 are approved technical conventions for P1 implementation under QMDB-P0-FRZ-001. ADR-042 through ADR-044 remain proposed because canonical Arabic collation and email/phone normalization require later qualified data, identity, security and privacy validation. OD-030, OD-047, OD-048, OD-056 and OD-057 are resolved by the approved roadmap and ADR-030, ADR-031, ADR-037 and ADR-053.

## QMDB-P0-B04 consistency review

QMDB-P0-B04 was checked against locked ADR-001 through ADR-020, all 30 invariants, B02 functional/state-machine authority and B03 security/privacy/operations controls. The model keeps MySQL/InnoDB, shared-schema Workspace integrity, exact server-authoritative scoring, versioned official records, private object storage, transactional outbox/idempotency, restricted Quran governance and no normal hard deletion. Closeout approved implementation-safe conventions while preserving unresolved precision, normalization, authority, source, provider, retention and production-policy decisions.

## QMDB-P0-B03 consistency review

QMDB-P0-B03 was checked against ADR-001 through ADR-020. The non-functional requirements, parameters, threat/control/risk records, privacy and accessibility matrices, continuity rules and acceptance scenarios preserve every locked decision. ADR-021 through ADR-029 are approved as technical assurance conventions without approving any open parameter, provider, authority or policy value.

## QMDB-P0-B02 consistency review

QMDB-P0-B02 was checked against ADR-001 through ADR-020. The functional requirements, use cases, workflows, capabilities, events, failure behavior and acceptance scenarios preserve all locked decisions. No new architectural decision was required: newly discovered authentication-policy, Person-merge, offline-authority, separation-of-duties and Appeal-fee questions are policy/governance matters recorded as OD-031 through OD-035 rather than falsely promoted to locked ADRs.

The detailed requirements therefore keep MySQL/InnoDB and the secure Core PHP modular-monolith boundary; Workspace-aware shared-schema integrity; RBAC plus contextual policy; exact server-authoritative scoring; versioned official records; transactional outbox/idempotent consumers; private object storage and isolated media; cryptographic verification; Guardian/child-safety foundations; protected competition capacity; declarative non-executable rules; layer-safe persistence; and no normal hard deletion.

## Related documents

- [Documentation index](../README.md)
- [Product constitution](product-constitution.md)
- [System boundaries](system-boundaries.md)
- [Core modules and business invariants](../domain/core-modules-and-business-invariants.md)
- [Open decisions](open-decisions.md)
- [P0-B01 requirements](../requirements/P0-B01-requirements.md)

## P3-B02 implemented decisions

- A Person is global and distinct from a User Account or Workspace-owned business record; one active SELF Account link
  is allowed per Account and per Person.
- Person roles are bounded profile categories, not Authorization Roles; Competitor is not competition registration and
  Guardian is not guardianship authority.
- Exact active guardianship grants only bounded dependent profile management. It is self-declared in B02 and is not
  legal certification, consent or verification.
- Names support Unicode/Arabic text and history; opaque display/public IDs do not authorize; Person records are not
  hard deleted.
- Memorizer progress is declared, not verified competition performance. Sensitive profile changes require step-up;
  private Person pages are no-store and profile values do not enter audit metadata.
