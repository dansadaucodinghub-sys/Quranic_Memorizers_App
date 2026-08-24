# Qur’an Memorizer DB Documentation

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project code | QMDB |
| Baseline | QMDB-BL-001 |
| Document version | 2.0.0 |
| Status | P0 COMPLETE; QMDB-P1-B01 READY |
| Current phase | P1 — Engineering and Repository Foundation (ready; implementation not started) |
| Last updated | 2026-08-24 |
| Owner role | Product and Architecture Governance |
| Approval status | QMDB-BL-001 frozen under QMDB-P0-FRZ-001; READY_WITH_DEFERRED_DECISIONS |

## Purpose

This directory is the authoritative documentation entry point for QMDB-BL-001. It defines product/domain authority, functional/non-functional/data requirements, use cases, lifecycles, threats, controls, privacy, child safety, accessibility, resilience, logical schema, data governance, operational evidence, acceptance, traceability, risks, parameters, and decision state that later design and implementation must preserve.

## Scope

The repository-visible baseline covers completed batches QMDB-P0-B01 through QMDB-P0-CLOSE and is frozen under QMDB-P0-FRZ-001. The repository contains 90 documentation artifacts and no production application code, executable migration/SQL, scoring implementation, vendor selection, final legal or religious policy, approved numerical production target, or claim that the application is implemented. QMDB-P1-B01 is the only authorized executable next batch.

## Documentation map

### Product and governance

- [Risk register](project/risk-register.md) — B03 risks, controls, owners, reassessment triggers, and verification mappings.

- [Product constitution](project/product-constitution.md) — vision, mission, principles, non-goals, success characteristics, and governance.
- [System boundaries](project/system-boundaries.md) — scope, authority, trust boundaries, external systems, tenancy, and system context.
- [Decision register](project/decision-register.md) — locked architecture and product decisions.
- [Open decisions](project/open-decisions.md) — unresolved policy, infrastructure, governance, and commercial questions.
- [Project state](project/project-state.md) — baseline status, completed work, risks, validation, and next batch.

### Domain model

- [Domain glossary](domain/domain-glossary.md) — canonical terms and terminology restrictions.
- [Stakeholders and actors](domain/stakeholders-and-actors.md) — human, organization, service, and oversight actors with scoped authority.
- [Platform sides and capabilities](domain/platform-sides-and-capabilities.md) — experience boundaries and actor-to-side mapping.
- [Core modules and business invariants](domain/core-modules-and-business-invariants.md) — modular-monolith ownership and non-negotiable rules.

### Requirements

- [P0-B01 baseline requirements](requirements/P0-B01-requirements.md) — uniquely identified, testable obligations.
- [P0-B01 traceability matrix](requirements/P0-B01-traceability-matrix.md) — requirement-to-source, concept, actor, module, invariant, phase, and verification mapping.
- [P0-B02 functional requirements index](requirements/P0-B02-functional-requirements-index.md) — master index and counts for 113 detailed requirements.
  - [Identity, access, and tenancy](requirements/functional/01-identity-access-and-tenancy.md)
  - [People, profiles, and guardianship](requirements/functional/02-people-profiles-and-guardianship.md)
  - [Geography and organizations](requirements/functional/03-geography-and-organizations.md)
  - [Qur’an reference governance](requirements/functional/04-quran-reference-governance.md)
  - [Competition configuration and registration](requirements/functional/05-competition-configuration-and-registration.md)
  - [Scheduling, judging, and scoring](requirements/functional/06-scheduling-judging-and-scoring.md)
  - [Results, appeals, certificates, and records](requirements/functional/07-results-appeals-certificates-and-records.md)
  - [Media, Recitation Clips, and moderation](requirements/functional/08-media-recitation-clips-and-moderation.md)
  - [Search, notifications, and reporting](requirements/functional/09-search-notifications-and-reporting.md)
  - [Privacy, audit, security, operations, and integrations](requirements/functional/10-privacy-audit-security-operations-and-integrations.md)
- [P0-B02 use-case catalog](requirements/P0-B02-use-case-catalog.md) — 65 actor-centered transactions.
- [P0-B02 user journeys](requirements/P0-B02-user-journeys.md) — 24 end-to-end journeys and cross-journey flows.
- [P0-B02 workflows and state machines](requirements/P0-B02-workflows-and-state-machines.md) — 29 Mermaid lifecycles and transition tables.
- [P0-B02 permission and capability matrix](requirements/P0-B02-permission-capability-matrix.md) — 32 stable capabilities, all 36 actors, and separation of duties.
- [P0-B02 event and notification catalog](requirements/P0-B02-event-and-notification-catalog.md) — domain/integration/live events, notifications, and audit categories.
- [P0-B02 edge cases and failure behavior](requirements/P0-B02-edge-cases-and-failure-behavior.md) — safe behavior for 80 specified failure cases.
- [P0-B02 acceptance scenarios](requirements/P0-B02-acceptance-scenarios.md) — 40 functional and eight accessibility scenarios.
- [P0-B02 traceability matrix](requirements/P0-B02-traceability-matrix.md) — exactly one trace row per detailed requirement.

### P0-B03 non-functional requirements

- [P0-B03 non-functional requirements index](requirements/P0-B03-non-functional-requirements-index.md) — master index and counts for 50 non-functional requirements.
  - [Security and zero trust](requirements/non-functional/01-security-and-zero-trust.md)
  - [Privacy, data protection, and child safety](requirements/non-functional/02-privacy-data-protection-and-child-safety.md)
  - [Accessibility, localization, and inclusive UX](requirements/non-functional/03-accessibility-localization-and-inclusive-ux.md)
  - [Performance, scalability, and capacity](requirements/non-functional/04-performance-scalability-and-capacity.md)
  - [Availability, resilience, and disaster recovery](requirements/non-functional/05-availability-resilience-and-disaster-recovery.md)
  - [Observability, audit, and incident response](requirements/non-functional/06-observability-audit-and-incident-response.md)
  - [Maintainability, testability, and release quality](requirements/non-functional/07-maintainability-testability-and-release-quality.md)
  - [Infrastructure, deployment, and supply chain](requirements/non-functional/08-infrastructure-deployment-and-supply-chain.md)
- [Quality-attribute scenarios](requirements/P0-B03-quality-attribute-scenarios.md) — 31 measurable stimulus-response cases.
- [Security and abuse cases](requirements/P0-B03-security-and-abuse-cases.md) — 34 attacker and insider misuse cases.
- [Non-functional acceptance scenarios](requirements/P0-B03-non-functional-acceptance-scenarios.md) — 63 positive and negative verification scenarios.
- [P0-B03 traceability matrix](requirements/P0-B03-traceability-matrix.md) — one row for every B03 NFR.

### Data architecture and governance

- [Data architecture index](data/README.md) — entry point and controlled counts.
- [Data architecture overview](data/01-data-architecture-overview.md) — stores, authority, consistency, transactions, history, recovery and governance.
- [Domain aggregate and ownership model](data/02-domain-aggregate-and-ownership-model.md) — aggregate roots, entities, Value Objects and transaction boundaries.
- [MySQL schema conventions](data/03-mysql-schema-conventions.md) — types, identifiers, naming, collation, JSON, uniqueness, concurrency and deletion.
- [Entity-relationship model](data/04-entity-relationship-model.md) — complete-domain map and ten detailed Mermaid ERDs.
- [Table and column data dictionary](data/05-table-and-column-data-dictionary.md) — complete 250-table/1,909-column logical inventory.
- [Relationship, constraint, and integrity catalog](data/06-relationship-constraint-and-integrity-catalog.md) — foreign keys, constraints and invariant enforcement.
- [Indexing and query access patterns](data/07-indexing-and-query-access-patterns.md) — bounded queries, consistency, pagination, locks and indexes.
- [Record versioning, lifecycle, and deletion](data/08-record-versioning-lifecycle-and-deletion.md) — official history and disposition semantics.
- [Tenant-isolation data model](data/09-tenant-isolation-data-model.md) — full table classification and Workspace structural enforcement.
- [Data classification, ownership, and lineage](data/10-data-classification-ownership-and-lineage.md) — stewardship, handling, lineage and quality rules.
- [Migration, seed, and bootstrap plan](data/11-migration-seed-and-bootstrap-plan.md) — ordered future migration groups and controlled imports.
- [Volume, archival, and partitioning strategy](data/12-volume-archival-and-partitioning-strategy.md) — growth, archives, projections and partition restrictions.
- [Schema review and implementation readiness](data/13-schema-review-and-implementation-readiness.md) — blockers, dependencies and P0-CLOSE evidence.
- [MySQL logical schema manifest](data/mysql-logical-schema.yaml) — machine-readable non-executable schema inventory.
- [Controlled taxonomy and seed catalog](data/controlled-taxonomy-and-seed-catalog.yaml) — source/provenance plan without fabricated authoritative values.
- [P0-B04 data requirements](requirements/P0-B04-data-requirements.md) — 32 single-obligation data requirements.
- [P0-B04 traceability matrix](requirements/P0-B04-traceability-matrix.md) — requirement-to-schema, lineage and verification mapping.

### Security assurance

- [Threat model](security/threat-model.md) — STRIDE analysis, 46 threats, and eight attack trees.
- [Trust boundaries and data flows](security/trust-boundary-and-data-flow-analysis.md) — 14 critical flows and authority boundaries.
- [Security control catalog](security/security-control-catalog.md) — 30 preventive, detective, corrective, and recovery controls.
- [Security verification matrix](security/security-verification-matrix.md) — control-to-test, evidence, owner, phase, and release-gate mapping.

### Privacy, accessibility, operations, and references

- [Standards and regulatory evidence](references/standards-and-regulatory-evidence.md) — controlled official-source register.
- [Data classification and handling](privacy/data-classification-and-handling.md) — five stable classes and record-handling matrix.
- [Privacy-impact screening](privacy/privacy-impact-screening.md) — implementation-neutral processing-risk review.
- [Consent and Minor-safety matrix](privacy/consent-and-minor-safety-control-matrix.md) — protective defaults, Guardian authority, and withdrawal behavior.
- [Retention and deletion decisions](privacy/retention-and-deletion-decision-register.md) — unresolved disposition rules without invented periods.
- [Accessibility verification matrix](accessibility/accessibility-verification-matrix.md) — automated and manual critical-workflow evidence.
- [Service criticality and degradation](operations/service-criticality-and-degradation-policy.md) — tier, dependency, and safe-failure policy.
- [Quality-attribute parameter register](operations/quality-attribute-parameter-register.md) — 34 unapproved target records and conservative behavior.
- [Backup, recovery, and continuity](operations/backup-recovery-and-continuity-requirements.md) — restore, rebuild, and reconciliation obligations.
- [Observability and alerting](operations/observability-and-alerting-requirements.md) — 20 SLIs, 20 alerts, and 21 runbook contracts.

### P0 closeout and baseline freeze

- [Closeout entry point](closeout/README.md)
- [P0 closeout report](closeout/01-P0-closeout-report.md)
- [Implementation readiness assessment](closeout/02-implementation-readiness-assessment.md)
- [Contradiction, gap, and resolution register](closeout/03-contradiction-gap-and-resolution-register.md)
- [Requirement coverage and traceability summary](closeout/04-requirement-coverage-and-traceability-summary.md)
- [Baseline freeze and change control](closeout/05-baseline-freeze-and-change-control.md)
- [Machine-readable P0 freeze manifest](closeout/qmdb-p0-baseline-freeze.yaml)

### Implementation governance

- [Implementation entry point](implementation/README.md)
- [Phase and batch roadmap](implementation/phase-and-batch-roadmap.md)
- [Requirements-to-implementation map](implementation/requirements-to-implementation-map.md)
- [Definition of Ready and Done](implementation/definition-of-ready-and-done.md)
- [P1 engineering foundation backlog](implementation/P1-engineering-foundation-backlog.md)
- [QMDB-P1-B01 executable implementation prompt](implementation/prompts/QMDB-P1-B01-core-php-repository-and-runtime-foundation.md)

## Authority and precedence

1. The [P0 freeze manifest and change-control contract](closeout/05-baseline-freeze-and-change-control.md) identify the frozen implementation baseline and permitted changes.
2. The locked decisions in the [decision register](project/decision-register.md) govern later technical work.
3. The [product constitution](project/product-constitution.md) governs product intent and principles.
4. The [domain glossary](domain/domain-glossary.md) governs canonical terminology.
5. The [business invariants](domain/core-modules-and-business-invariants.md#business-invariants) govern behavior across modules.
6. The [P0-B01 requirements](requirements/P0-B01-requirements.md) express baseline obligations.
7. The [P0-B02 detailed functional requirements](requirements/P0-B02-functional-requirements-index.md) refine those obligations without weakening them.
8. The [P0-B03 non-functional requirements](requirements/P0-B03-non-functional-requirements-index.md) constrain security, privacy, safety, accessibility, reliability, operations, and release quality without changing functional authority.
9. The [P0-B04 data requirements and logical schema](data/README.md) translate approved authority into implementation-ready relational design without resolving open values.
10. The [open-decisions register](project/open-decisions.md) identifies matters that must not be silently assumed.

If documents appear to conflict, work stops at design review until Product and Architecture Governance resolves the inconsistency without weakening a locked decision. Open decisions do not override conservative defaults.

## Baseline interpretation

- “Centralized” means a unified, governed platform and source of truth, not one server or one unrestricted administrator.
- A Person, User Account, Profile, contextual Role, Membership, Administrative Scope, and Competition Assignment are distinct.
- Competition Scope, Organizer Organization, Host Location, Eligibility Geography, and Represented Geography are distinct.
- Official records are server-authoritative, exact-decimal, versioned, auditable, and corrected without destroying history.
- Public pages, live scoreboards, certificates, and media derivatives are projections or representations, not authoritative score storage.
- Child safety, privacy, accessibility, tenant isolation, and operational isolation are foundational requirements.

## Change control

Documentation changes require a stable change identifier, review by the owning governance role, updated traceability when obligations change, and an entry in project state. Religious competition rules, Qur’an text releases, legal interpretations, retention periods, and authority appointments require their qualified decision owners; they must not be inferred from this baseline.

## Exact next action

Execute [QMDB-P1-B01 — Core PHP Repository and Runtime Foundation](implementation/prompts/QMDB-P1-B01-core-php-repository-and-runtime-foundation.md) only; produce executable PHP/tests and do not create domain migrations or later modules.
