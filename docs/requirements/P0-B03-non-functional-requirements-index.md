# P0-B03 Non-Functional Requirements Index

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B03 |
| Document Title | P0-B03 Non-Functional Requirements Index |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Product, Architecture, Security, Privacy and Quality Governance |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed for governance approval; inherited locked decisions remain binding |
| Related Documents | [B01 requirements](P0-B01-requirements.md); [B02 index](P0-B02-functional-requirements-index.md); [B03 traceability](P0-B03-traceability-matrix.md) |

## Purpose

This is the authoritative discovery and one-row-per-requirement index for QMDB-P0-B03.

## Scope

It indexes non-functional obligations only. Functional behavior remains authoritative in QMDB-P0-B02; operational numeric targets remain open until approved in the parameter register.

## Requirement-writing standard

Every requirement contains all mandatory B03 fields, one primary `shall` obligation, affected boundary, measurable response, safe failure, traceability, verification and evidence. Unapproved production targets reference QMDB-PAR records.

## Identifier families

`QMDB-NFR-SEC`, `IAM`, `TEN`, `CRY`, `API`, `MED`, `PRI`, `CHD`, `ACC`, `L10`, `PER`, `SCL`, `AVL`, `RES`, `DRC`, `OBS`, `AUD`, `INC`, `MNT`, `TST`, `REL`, `INF`, `SUP`, `DAT`, and `UXR` are globally unique NFR families. Supporting records use QMDB-QAS, THR, ABU, CTL, RSK, DCL, PAR, NFAS, SLI, ALT and RUN.

## Priority definitions

| Priority | Meaning |
| --- | --- |
| Critical | Required to protect official truth, safety, privacy, security, tenant integrity, or competition continuity. |
| High | Required for production quality but may have a controlled staged implementation. |
| Medium | Useful governed improvement after higher priorities. |
| Deferred | Explicitly outside the current delivery horizon. |

## Criticality definitions

Competition Critical protects live official competition; Security Critical protects trust and authority; Privacy Critical protects personal data and rights; Safety Critical protects people, especially Minors; Business Critical protects core service value; Operational protects reliable operation; Supporting enables governed quality.

## Status definitions

Approved Baseline restates a locked obligation. Proposed requires governance approval. Parameter Pending requires approved parameter values before the relevant production gate. Deferred is intentionally postponed.

## Verification-method definitions

Architecture/configuration review examines design and controls; automated unit/integration/contract/security/performance/recovery tests create repeatable evidence; manual accessibility/privacy/child-safety/domain reviews cover judgments automation cannot prove; exercises validate operations and people.

## Quality-attribute model

Security, privacy/safety, accessibility/localization/usability, performance/scalability, availability/resilience/recovery, observability/audit/incident response, maintainability/testability/release, and infrastructure/supply-chain requirements are co-equal constraints. A performance or availability improvement cannot weaken authorization, exact scoring, privacy, child safety, auditability, or canonical Qur’an integrity.

## Requirement counts by domain

| Domain document | Count |
| --- | ---: |
| [Security and Zero-Trust Requirements](non-functional/01-security-and-zero-trust.md) | 12 |
| [Privacy, Data Protection, and Child-Safety Requirements](non-functional/02-privacy-data-protection-and-child-safety.md) | 7 |
| [Accessibility, Localization, and Inclusive UX Requirements](non-functional/03-accessibility-localization-and-inclusive-ux.md) | 6 |
| [Performance, Scalability, and Capacity Requirements](non-functional/04-performance-scalability-and-capacity.md) | 5 |
| [Availability, Resilience, and Disaster-Recovery Requirements](non-functional/05-availability-resilience-and-disaster-recovery.md) | 6 |
| [Observability, Audit, and Incident-Response Requirements](non-functional/06-observability-audit-and-incident-response.md) | 6 |
| [Maintainability, Testability, and Release-Quality Requirements](non-functional/07-maintainability-testability-and-release-quality.md) | 4 |
| [Infrastructure, Deployment, and Supply-Chain Requirements](non-functional/08-infrastructure-deployment-and-supply-chain.md) | 4 |
| **Total** | **50** |

## Requirement counts by priority

| Priority | Count |
| --- | ---: |
| Critical | 43 |
| High | 7 |
| Medium | 0 |
| Deferred | 0 |

## Requirement counts by implementation phase

| Planned phase | Count |
| --- | ---: |
| P1 — Engineering and Repository Foundation | 5 |
| P2 — Identity, Security, and Tenant Isolation | 9 |
| P3 — Nigerian Geography, Organizations, and People | 2 |
| P4 — Qur’an Reference Governance | 0 |
| P5 — Competition Configuration and Registration | 0 |
| P6 — Judging and Deterministic Scoring | 1 |
| P7 — Live Competition, Results, and Appeals | 3 |
| P8 — Certificates, Record Passport, and Trusted Archive | 1 |
| P9 — Audio and Video Evidence | 1 |
| P10 — Recitation Clips and Community Safety | 2 |
| P11 — Search, Analytics, and National Reporting | 2 |
| P12 — Quality, Accessibility, and Production Hardening | 21 |
| P13 — Pilot, Offline Venue Mode, and National Rollout | 3 |

## Parameter dependency

40 of 50 requirements reference one or more open quality parameters. A parameter dependency does not waive the behavioral, measurement, safe-failure, or evidence obligations.

## Master requirement table

| Requirement ID | Title | Quality Attribute | Priority | Criticality | Components | Planned Phase | Verification Method | Parameter Dependency | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| [QMDB-NFR-SEC-001](non-functional/01-security-and-zero-trust.md#qmdb-nfr-sec-001-denybydefault-complete-mediation) | Deny-by-default complete mediation | Security | Critical | Security Critical | Presentation, Application, Domain, authorization policies | P2 — Identity, Security, and Tenant Isolation | Architecture review; authorization and tenant-isolation tests | None | Approved Baseline |
| [QMDB-NFR-IAM-001](non-functional/01-security-and-zero-trust.md#qmdb-nfr-iam-001-riskaware-authentication-and-recovery) | Risk-aware authentication and recovery | Authentication Security | Critical | Security Critical | Identity and Access, Notifications, Security Operations | P2 — Identity, Security, and Tenant Isolation | Authentication security tests; recovery abuse exercise; configuration review | QMDB-PAR-024; QMDB-PAR-026; QMDB-PAR-027 | Parameter Pending |
| [QMDB-NFR-IAM-002](non-functional/01-security-and-zero-trust.md#qmdb-nfr-iam-002-serverside-revocable-sessions) | Server-side revocable sessions | Session Security | Critical | Security Critical | Identity and Access, browser presentation, session store | P2 — Identity, Security, and Tenant Isolation | Cookie inspection; fixation, rotation, expiry, and revocation tests | QMDB-PAR-024; QMDB-PAR-025 | Parameter Pending |
| [QMDB-NFR-TEN-001](non-functional/01-security-and-zero-trust.md#qmdb-nfr-ten-001-workspaceaware-authorization-integrity) | Workspace-aware authorization integrity | Tenant Isolation | Critical | Security Critical | All repositories, policies, caches, queues, exports, search projections | P2 — Identity, Security, and Tenant Isolation | Architecture, repository, authorization, cache-key, queue, and constraint tests | None | Approved Baseline |
| [QMDB-NFR-SEC-002](non-functional/01-security-and-zero-trust.md#qmdb-nfr-sec-002-input-output-and-browser-defense) | Input, output, and browser defense | Application Security | Critical | Security Critical | Nginx, Presentation, Application, PDO repositories, browser | P2 — Identity, Security, and Tenant Isolation | Static analysis; dynamic security; header/configuration and negative-input tests | QMDB-PAR-026 | Parameter Pending |
| [QMDB-NFR-DAT-001](non-functional/01-security-and-zero-trust.md#qmdb-nfr-dat-001-mysql-authoritativestore-security) | MySQL authoritative-store security | Data Integrity | Critical | Security Critical | MySQL, PDO repositories, migration and backup operations | P1 — Engineering and Repository Foundation | Configuration/grant/schema review; constraint, concurrency, replica-lag and restore tests | QMDB-PAR-028; QMDB-PAR-017; QMDB-PAR-018 | Approved Baseline |
| [QMDB-NFR-SEC-003](non-functional/01-security-and-zero-trust.md#qmdb-nfr-sec-003-redis-leastprivilege-and-safe-degradation) | Redis least-privilege and safe degradation | Redis Security | Critical | Operational | Redis cache, Redis Streams, outbox consumers, live projections | P7 — Live Competition, Results, and Appeals | Network/ACL/configuration review; duplicate, backlog, trim and outage tests | QMDB-PAR-029 | Parameter Pending |
| [QMDB-NFR-API-001](non-functional/01-security-and-zero-trust.md#qmdb-nfr-api-001-scoped-replaysafe-apis-and-webhooks) | Scoped replay-safe APIs and webhooks | API Security | Critical | Security Critical | API, integrations, webhook workers, audit | P12 — Quality, Accessibility, and Production Hardening | OpenAPI contract; API authorization, idempotency, replay and webhook security tests | QMDB-PAR-026; QMDB-PAR-029 | Parameter Pending |
| [QMDB-NFR-MED-001](non-functional/01-security-and-zero-trust.md#qmdb-nfr-med-001-quarantined-isolated-media-processing) | Quarantined isolated media processing | Media Security | Critical | Safety Critical | Media application, object storage, FFmpeg workers, CDN, moderation | P9 — Audio and Video Evidence | Media security integration, malware, access, sandbox and consent-race tests | QMDB-PAR-011; QMDB-PAR-023; QMDB-PAR-029 | Parameter Pending |
| [QMDB-NFR-CRY-001](non-functional/01-security-and-zero-trust.md#qmdb-nfr-cry-001-managed-cryptography-and-signing-custody) | Managed cryptography and signing custody | Cryptography | Critical | Security Critical | Key management abstraction, certificates, result bundles, audit checkpoints, TLS, restricted fields | P8 — Certificates, Record Passport, and Trusted Archive | Cryptographic design review; signature, rotation, revocation, entropy and secret-location tests | QMDB-PAR-031 | Parameter Pending |
| [QMDB-NFR-DAT-002](non-functional/01-security-and-zero-trust.md#qmdb-nfr-dat-002-authoritativeversusprojection-integrity) | Authoritative-versus-projection integrity | Data Integrity | Critical | Competition Critical | Domain services, MySQL, projections, integrations, clients | P6 — Judging and Deterministic Scoring | Architecture, alternate-path, client-tampering, projection-rebuild and reconciliation tests | None | Approved Baseline |
| [QMDB-NFR-SEC-004](non-functional/01-security-and-zero-trust.md#qmdb-nfr-sec-004-independent-security-verification) | Independent security verification | Security Assurance | High | Security Critical | Architecture, application, infrastructure, operations | P12 — Quality, Accessibility, and Production Hardening | Independent architecture review and penetration test | QMDB-PAR-027 | Proposed |
| [QMDB-NFR-PRI-001](non-functional/02-privacy-data-protection-and-child-safety.md#qmdb-nfr-pri-001-privacy-by-design-and-processing-accountability) | Privacy by design and processing accountability | Privacy | Critical | Privacy Critical | People, Privacy, all data modules, integrations | P2 — Identity, Security, and Tenant Isolation | Privacy design review; inventory and purpose-control tests | None | Proposed |
| [QMDB-NFR-PRI-002](non-functional/02-privacy-data-protection-and-child-safety.md#qmdb-nfr-pri-002-classified-personaldata-handling) | Classified personal-data handling | Privacy | Critical | Privacy Critical | MySQL, object storage, logs, exports, backups, caches, support | P3 — Nigerian Geography, Organizations, and People | Data inventory, access, logging, export and backup review | QMDB-PAR-021; QMDB-PAR-022; QMDB-PAR-023 | Parameter Pending |
| [QMDB-NFR-PRI-003](non-functional/02-privacy-data-protection-and-child-safety.md#qmdb-nfr-pri-003-public-disclosure-minimization) | Public disclosure minimization | Privacy | Critical | Privacy Critical | Public profiles, results, certificates, search, reports, CDN | P11 — Search, Analytics, and National Reporting | Public-schema, field-leakage, small-group, cache and search tests | QMDB-PAR-026 | Proposed |
| [QMDB-NFR-PRI-004](non-functional/02-privacy-data-protection-and-child-safety.md#qmdb-nfr-pri-004-controlled-privacyrights-and-retention-workflow) | Controlled privacy-rights and retention workflow | Privacy Operations | Critical | Privacy Critical | Privacy, People, Records, search/cache projections, exports, Audit | P12 — Quality, Accessibility, and Production Hardening | End-to-end privacy, identity, Guardian, export, hold and projection tests; qualified review | QMDB-PAR-021; QMDB-PAR-022 | Parameter Pending |
| [QMDB-NFR-CHD-001](non-functional/02-privacy-data-protection-and-child-safety.md#qmdb-nfr-chd-001-protective-minor-status-guardian-authority-and-consent) | Protective Minor status, Guardian authority, and consent | Child Safety | Critical | Safety Critical | People, Guardianship, Competitions, Media, Community | P3 — Nigerian Geography, Organizations, and People | Child-safety, age-boundary, Guardian, consent and withdrawal tests; qualified policy review | None | Proposed |
| [QMDB-NFR-CHD-002](non-functional/02-privacy-data-protection-and-child-safety.md#qmdb-nfr-chd-002-safe-child-publication-and-interaction) | Safe child publication and interaction | Child Safety | Critical | Safety Critical | Recitation Clips, comments, profiles, moderation, notifications | P10 — Recitation Clips and Community Safety | Child-safety abuse, authorization, moderation, report-flood and public-disclosure tests | QMDB-PAR-026; QMDB-PAR-027 | Parameter Pending |
| [QMDB-NFR-CHD-003](non-functional/02-privacy-data-protection-and-child-safety.md#qmdb-nfr-chd-003-age-transition-and-consent-withdrawal-continuity) | Age transition and consent withdrawal continuity | Child Safety | High | Safety Critical | People, Guardianship, Privacy, Media, Records, projections | P10 — Recitation Clips and Community Safety | Lifecycle, concurrency, consent-withdrawal, cache purge and retention tests | QMDB-PAR-023 | Parameter Pending |
| [QMDB-NFR-ACC-001](non-functional/03-accessibility-localization-and-inclusive-ux.md#qmdb-nfr-acc-001-wcag-22-level-aa-interaction-baseline) | WCAG 2.2 Level AA interaction baseline | Accessibility | Critical | Business Critical | All web presentation and generated user artifacts | P12 — Quality, Accessibility, and Production Hardening | Automated accessibility checks; manual keyboard, screen-reader, zoom, reflow and reduced-motion review | None | Approved Baseline |
| [QMDB-NFR-ACC-002](non-functional/03-accessibility-localization-and-inclusive-ux.md#qmdb-nfr-acc-002-accessible-criticalaction-safeguards) | Accessible critical-action safeguards | Accessibility | Critical | Competition Critical | Judging, Results, Certificates, Guardianship, Privacy, Operations, Organizations | P12 — Quality, Accessibility, and Production Hardening | Manual assistive-technology and end-to-end negative-error tests | None | Proposed |
| [QMDB-NFR-ACC-003](non-functional/03-accessibility-localization-and-inclusive-ux.md#qmdb-nfr-acc-003-accessible-authentication-media-documents-and-live-data) | Accessible authentication, media, documents, and live data | Accessibility | High | Business Critical | Identity UI, media player, certificates, live scoreboard, reports | P12 — Quality, Accessibility, and Production Hardening | Screen-reader/keyboard media, PDF, authentication and live-region tests | QMDB-PAR-007 | Parameter Pending |
| [QMDB-NFR-L10-001](non-functional/03-accessibility-localization-and-inclusive-ux.md#qmdb-nfr-l10-001-arabic-and-rtl-integrity) | Arabic and RTL integrity | Localization and RTL | Critical | Business Critical | All presentation, search, certificate and print surfaces | P12 — Quality, Accessibility, and Production Hardening | Manual RTL, Arabic linguistic, screen-reader, copy/paste, search and print tests | None | Approved Baseline |
| [QMDB-NFR-L10-002](non-functional/03-accessibility-localization-and-inclusive-ux.md#qmdb-nfr-l10-002-governed-localization-and-time-display) | Governed localization and time display | Localization | High | Supporting | Presentation, localization, search, time services, content administration | P12 — Quality, Accessibility, and Production Hardening | Localization key, fallback, plural, timezone, mixed-search and manual terminology review | None | Proposed |
| [QMDB-NFR-UXR-001](non-functional/03-accessibility-localization-and-inclusive-ux.md#qmdb-nfr-uxr-001-lowbandwidth-progressive-and-recoverable-ux) | Low-bandwidth progressive and recoverable UX | Usability and Recoverable Interaction | Critical | Competition Critical | Presentation, judging, registration, live delivery, media, APIs | P12 — Quality, Accessibility, and Production Hardening | Mobile, throttled-network, progressive-enhancement, idempotency and accessibility tests | QMDB-PAR-003; QMDB-PAR-007; QMDB-PAR-030 | Parameter Pending |
| [QMDB-NFR-PER-001](non-functional/04-performance-scalability-and-capacity.md#qmdb-nfr-per-001-workloadtier-isolation-and-priority) | Workload-tier isolation and priority | Performance | Critical | Competition Critical | Web pools, MySQL, Redis, workers, queues, object storage, CDN | P7 — Live Competition, Results, and Appeals | Mixed-workload load, stress and degradation tests | QMDB-PAR-001 through QMDB-PAR-016 | Parameter Pending |
| [QMDB-NFR-PER-002](non-functional/04-performance-scalability-and-capacity.md#qmdb-nfr-per-002-parameterized-response-performance) | Parameterized response performance | Performance | High | Operational | Web, APIs, MySQL, Redis, workers, live delivery | P12 — Quality, Accessibility, and Production Hardening | Load, percentile-latency, throughput, error and regression tests | QMDB-PAR-001 through QMDB-PAR-011 | Parameter Pending |
| [QMDB-NFR-PER-003](non-functional/04-performance-scalability-and-capacity.md#qmdb-nfr-per-003-bounded-database-and-cache-performance) | Bounded database and cache performance | Performance | Critical | Operational | MySQL, repositories, Redis cache, reporting | P11 — Search, Analytics, and National Reporting | Query-plan, N+1, pagination, contention, deadlock, lag, stampede and cache-failure tests | QMDB-PAR-028; QMDB-PAR-029 | Parameter Pending |
| [QMDB-NFR-SCL-001](non-functional/04-performance-scalability-and-capacity.md#qmdb-nfr-scl-001-bounded-idempotent-queue-capacity) | Bounded idempotent queue capacity | Scalability and Capacity | Critical | Operational | Transactional outbox, Redis Streams, workers, media, notifications | P7 — Live Competition, Results, and Appeals | Idempotency, crash-after-commit, poison, retry, backlog and load-shedding tests | QMDB-PAR-029 | Parameter Pending |
| [QMDB-NFR-SCL-002](non-functional/04-performance-scalability-and-capacity.md#qmdb-nfr-scl-002-variablebased-evidenceled-scalability) | Variable-based evidence-led scalability | Scalability and Capacity | High | Business Critical | Architecture, web nodes, workers, MySQL, object storage, CDN, read models | P13 — Pilot, Offline Venue Mode, and National Rollout | Capacity-model review; load, soak, growth and scaling exercise | QMDB-PAR-012 through QMDB-PAR-015 | Parameter Pending |
| [QMDB-NFR-AVL-001](non-functional/05-availability-resilience-and-disaster-recovery.md#qmdb-nfr-avl-001-service-criticality-and-recovery-ordering) | Service criticality and recovery ordering | Availability | Critical | Business Critical | All services and dependencies | P12 — Quality, Accessibility, and Production Hardening | Service-catalog review; degradation and recovery-order exercises | QMDB-PAR-016; QMDB-PAR-017; QMDB-PAR-018 | Parameter Pending |
| [QMDB-NFR-RES-001](non-functional/05-availability-resilience-and-disaster-recovery.md#qmdb-nfr-res-001-dependencyspecific-graceful-degradation) | Dependency-specific graceful degradation | Resilience | Critical | Competition Critical | All runtime dependencies and capabilities | P12 — Quality, Accessibility, and Production Hardening | Dependency fault injection, recovery, reconciliation and user-message tests | QMDB-PAR-007; QMDB-PAR-029; QMDB-PAR-031 | Parameter Pending |
| [QMDB-NFR-RES-002](non-functional/05-availability-resilience-and-disaster-recovery.md#qmdb-nfr-res-002-bounded-resilience-patterns) | Bounded resilience patterns | Resilience | Critical | Operational | HTTP clients, APIs, workers, outbox, Redis, providers, deployment | P12 — Quality, Accessibility, and Production Hardening | Timeout, retry, circuit, readiness, idempotency, load-shed and reconciliation tests | QMDB-PAR-026; QMDB-PAR-029 | Parameter Pending |
| [QMDB-NFR-DRC-001](non-functional/05-availability-resilience-and-disaster-recovery.md#qmdb-nfr-drc-001-encrypted-restorable-backups-and-pitr) | Encrypted restorable backups and PITR | Backup and Recovery | Critical | Business Critical | MySQL, object storage, configuration, secrets, keys, audit checkpoints, backup systems | P12 — Quality, Accessibility, and Production Hardening | Backup integrity, access, PITR, object/config/secret/key and full restore tests | QMDB-PAR-017; QMDB-PAR-018; QMDB-PAR-019; QMDB-PAR-020 | Parameter Pending |
| [QMDB-NFR-DRC-002](non-functional/05-availability-resilience-and-disaster-recovery.md#qmdb-nfr-drc-002-governed-disaster-recovery-and-continuity) | Governed disaster recovery and continuity | Disaster Recovery | Critical | Business Critical | Production and DR environments, all critical services | P13 — Pilot, Offline Venue Mode, and National Rollout | DR tabletop, failover, restore, continuity, communication and post-review exercise | QMDB-PAR-017; QMDB-PAR-018; QMDB-PAR-033 | Parameter Pending |
| [QMDB-NFR-RES-003](non-functional/05-availability-resilience-and-disaster-recovery.md#qmdb-nfr-res-003-tamperevident-offline-venue-reconciliation) | Tamper-evident offline venue reconciliation | Offline Resilience | Critical | Competition Critical | Venue Edge Node, judge device, synchronization API, scoring domain | P13 — Pilot, Offline Venue Mode, and National Rollout | Package signing, device, expiry, offline, replay, ordering, conflict and reconciliation tests | QMDB-PAR-030 | Parameter Pending |
| [QMDB-NFR-OBS-001](non-functional/06-observability-audit-and-incident-response.md#qmdb-nfr-obs-001-structured-redacted-operational-logging) | Structured redacted operational logging | Observability | Critical | Security Critical | Web, application, workers, integrations, database operations | P2 — Identity, Security, and Tenant Isolation | Log-schema, redaction, access, integrity and secret-scanning tests | QMDB-PAR-022 | Parameter Pending |
| [QMDB-NFR-OBS-002](non-functional/06-observability-audit-and-incident-response.md#qmdb-nfr-obs-002-critical-metrics-tracing-and-slis) | Critical metrics, tracing, and SLIs | Observability | Critical | Operational | All runtime and operational components | P12 — Quality, Accessibility, and Production Hardening | Telemetry schema, correlation, dashboard, trace-redaction and synthetic monitoring tests | QMDB-PAR-032 | Parameter Pending |
| [QMDB-NFR-AUD-001](non-functional/06-observability-audit-and-incident-response.md#qmdb-nfr-aud-001-tamperevident-attributable-audit) | Tamper-evident attributable audit | Audit and Tamper Evidence | Critical | Security Critical | Audit domain, MySQL, outbox, checkpoint storage, exports | P2 — Identity, Security, and Tenant Isolation | Transaction, completeness, chain tamper, external checkpoint, scope and export tests | QMDB-PAR-021 | Parameter Pending |
| [QMDB-NFR-OBS-003](non-functional/06-observability-audit-and-incident-response.md#qmdb-nfr-obs-003-owned-alerting-and-runbook-linkage) | Owned alerting and runbook linkage | Alerting | Critical | Operational | Monitoring, alert manager, dashboards, incident system | P12 — Quality, Accessibility, and Production Hardening | Synthetic alert, routing, deduplication, escalation and runbook-link tests | QMDB-PAR-027; QMDB-PAR-028; QMDB-PAR-029 | Parameter Pending |
| [QMDB-NFR-INC-001](non-functional/06-observability-audit-and-incident-response.md#qmdb-nfr-inc-001-controlled-incident-lifecycle) | Controlled incident lifecycle | Incident Response | Critical | Security Critical | Incident management, security/privacy/safety operations, recovery | P12 — Quality, Accessibility, and Production Hardening | Tabletop, state, authority, evidence-preservation, containment, recovery and closure tests | QMDB-PAR-027 | Proposed |
| [QMDB-NFR-INC-002](non-functional/06-observability-audit-and-incident-response.md#qmdb-nfr-inc-002-qualified-notification-and-evidence-decisions) | Qualified notification and evidence decisions | Incident Response | High | Privacy Critical | Incident management, notifications, legal/compliance interface, privacy and safety operations | P12 — Quality, Accessibility, and Production Hardening | Incident tabletop with privacy, child-safety and communication decision review | QMDB-PAR-027 | Parameter Pending |
| [QMDB-NFR-MNT-001](non-functional/07-maintainability-testability-and-release-quality.md#qmdb-nfr-mnt-001-maintainable-modular-core-php-design) | Maintainable modular Core PHP design | Maintainability | Critical | Supporting | Core PHP modular monolith and tests | P1 — Engineering and Repository Foundation | Static analysis; architecture, configuration and code review | None | Approved Baseline |
| [QMDB-NFR-MNT-002](non-functional/07-maintainability-testability-and-release-quality.md#qmdb-nfr-mnt-002-safe-versioned-mysql-change) | Safe versioned MySQL change | Maintainability | Critical | Business Critical | MySQL schema, migrations, deployment | P1 — Engineering and Repository Foundation | Migration static review, dry-run, compatibility, lock, rollback and recovery tests | QMDB-PAR-027 | Parameter Pending |
| [QMDB-NFR-TST-001](non-functional/07-maintainability-testability-and-release-quality.md#qmdb-nfr-tst-001-riskbased-test-evidence) | Risk-based test evidence | Testability and Quality Assurance | Critical | Business Critical | All code, data, interfaces, workers and operations | P12 — Quality, Accessibility, and Production Hardening | Traceability review and execution of required automated/manual suites | QMDB-PAR-034 | Parameter Pending |
| [QMDB-NFR-REL-001](non-functional/07-maintainability-testability-and-release-quality.md#qmdb-nfr-rel-001-attributable-gated-change-and-release) | Attributable gated change and release | Release and Change Management | Critical | Operational | Source control, CI/CD, artifacts, configuration, deployment | P12 — Quality, Accessibility, and Production Hardening | Release-pipeline, branch/approval, artifact, gate, rollback and emergency exercise | QMDB-PAR-027; QMDB-PAR-034 | Parameter Pending |
| [QMDB-NFR-INF-001](non-functional/08-infrastructure-deployment-and-supply-chain.md#qmdb-nfr-inf-001-separated-hardened-runtime-environments) | Separated hardened runtime environments | Infrastructure Security | Critical | Security Critical | Nginx, PHP-FPM, containers, networks, storage, environments | P1 — Engineering and Repository Foundation | Configuration, network, TLS/header, container, privilege, health and data-copy tests | QMDB-PAR-027 | Parameter Pending |
| [QMDB-NFR-INF-002](non-functional/08-infrastructure-deployment-and-supply-chain.md#qmdb-nfr-inf-002-managed-secrets-and-privileged-infrastructure-access) | Managed secrets and privileged infrastructure access | Infrastructure Security | Critical | Security Critical | Secrets manager, infrastructure, databases, backups, KMS, CI/CD | P2 — Identity, Security, and Tenant Isolation | Secret scan; IAM/grant, MFA/JIT, rotation, revocation, access-review and break-glass tests | QMDB-PAR-027 | Parameter Pending |
| [QMDB-NFR-SUP-001](non-functional/08-infrastructure-deployment-and-supply-chain.md#qmdb-nfr-sup-001-governed-dependency-and-sbom-security) | Governed dependency and SBOM security | Supply-Chain Security | Critical | Security Critical | Composer dependencies, CI/CD, build environment | P1 — Engineering and Repository Foundation | Lock consistency, provenance, audit, plugin, license, SBOM and emergency-update tests | QMDB-PAR-027 | Parameter Pending |
| [QMDB-NFR-SUP-002](non-functional/08-infrastructure-deployment-and-supply-chain.md#qmdb-nfr-sup-002-reproducible-promoted-artifact-and-secure-deployment) | Reproducible promoted artifact and secure deployment | Supply-Chain and Deployment Security | Critical | Operational | CI/CD, artifact registry, containers, deployment, MySQL migrations | P12 — Quality, Accessibility, and Production Hardening | Reproducibility, checksum/signature, traceability, scan, promotion, deployment and rollback tests | QMDB-PAR-027 | Parameter Pending |

## P0-B03 artifact map

- [Quality-attribute parameter register](../operations/quality-attribute-parameter-register.md)
- [Standards and regulatory evidence](../references/standards-and-regulatory-evidence.md)
- [Threat model](../security/threat-model.md)
- [Trust-boundary and data-flow analysis](../security/trust-boundary-and-data-flow-analysis.md)
- [Security control catalog](../security/security-control-catalog.md)
- [Security verification matrix](../security/security-verification-matrix.md)
- [Data classification and handling](../privacy/data-classification-and-handling.md)
- [Privacy-impact screening](../privacy/privacy-impact-screening.md)
- [Consent and Minor-safety matrix](../privacy/consent-and-minor-safety-control-matrix.md)
- [Retention and deletion decisions](../privacy/retention-and-deletion-decision-register.md)
- [Accessibility verification matrix](../accessibility/accessibility-verification-matrix.md)
- [Service criticality and degradation](../operations/service-criticality-and-degradation-policy.md)
- [Backup, recovery and continuity](../operations/backup-recovery-and-continuity-requirements.md)
- [Observability and alerting](../operations/observability-and-alerting-requirements.md)
- [Quality-attribute scenarios](P0-B03-quality-attribute-scenarios.md)
- [Security and abuse cases](P0-B03-security-and-abuse-cases.md)
- [Non-functional acceptance scenarios](P0-B03-non-functional-acceptance-scenarios.md)
- [Traceability matrix](P0-B03-traceability-matrix.md)
- [Risk register](../project/risk-register.md)

## Coverage summary

All mandatory B03 quality domains and locked technical boundaries have enforceable coverage. The 50 requirements explicitly preserve authoritative MySQL truth, tenant isolation, server-side exact scoring, private object media, human official judging, WCAG 2.2 AA, workload priority, recoverability and auditable change.

## Known unresolved parameters

Latency, throughput, capacity, availability, recovery, backup, retention, Session, rate, alert, lag, queue, offline-window, certificate outage, trace and optional coverage values remain in the [parameter register](../operations/quality-attribute-parameter-register.md). Conservative behavior is binding until qualified owners approve values.

## Change-control procedure

Any NFR change requires identifier preservation, impact analysis, owner review, parameter/control/threat/risk/acceptance updates, traceability validation, and project-state evidence. Requirements are never silently renumbered or weakened.

