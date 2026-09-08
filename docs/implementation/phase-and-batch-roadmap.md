# QMDB Phase and Batch Implementation Roadmap

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Source Baseline | QMDB-BL-001 |
| Freeze ID | QMDB-P0-FRZ-001 |
| Batch ID | QMDB-P0-CLOSE |
| Document Title | Phase and Batch Implementation Roadmap |
| Document Version | 1.0.0 |
| Document Status | APPROVED IMPLEMENTATION SEQUENCE |
| Document Owner Role | Product, Architecture, Engineering, Security, Privacy, Data, and Operations |
| Last Updated | 2026-08-24 |
| Approval Status | Approved for controlled implementation planning; only QMDB-P1-B01 is currently authorized |
| Related Documents | [Implementation entry point](README.md); [implementation map](requirements-to-implementation-map.md); [P1 backlog](P1-engineering-foundation-backlog.md); [freeze contract](../closeout/05-baseline-freeze-and-change-control.md) |

## Purpose

Define the authoritative P1–P13 implementation sequence, its phase gates, executable outcomes, assurance expectations, dependencies and explicit exclusions.

## Scope

This roadmap routes the 263 approved P0 requirements into implementation phases. It is authoritative implementation planning under the frozen baseline; detailed behavior remains authoritative in the requirement sources. Locked decisions remain binding, controlled open decisions must close at their assigned gates, and later-phase planning does not authorize early implementation.

## Roadmap rules

Each phase may start only after the preceding close gate and every decision that blocks its entry have passed. Each batch must meet the [Definition of Ready and Done](definition-of-ready-and-done.md), produce executable evidence, preserve migration order and update traceability.

> Beginning with QMDB-P1-B01, a batch cannot be marked complete through Markdown documentation alone unless the batch is explicitly classified as a documentation or review batch.

Every implementation batch must produce one or more in-scope executable PHP, test, MySQL migration, CLI, frontend, worker, engineering, deployment or operational configuration artifacts. Supporting documentation remains required but cannot replace implementation.

## Phase summary

| Phase | Authoritative name | Primary close gate |
| --- | --- | --- |
| P1 | Engineering and Repository Foundation | QMDB-P1-CLOSE |
| P2 | Identity, Security, and Tenant Isolation | QMDB-P2-CLOSE |
| P3 | Nigerian Geography, Organizations, People, and Guardianship | QMDB-P3-CLOSE |
| P4 | Qur’an Reference and Governance | QMDB-P4-CLOSE |
| P5 | Competition Configuration and Registration | QMDB-P5-CLOSE |
| P6 | Judging and Deterministic Scoring | QMDB-P6-CLOSE |
| P7 | Live Competition, Results, and Appeals | QMDB-P7-CLOSE |
| P8 | Certificates, Record Passport, and Trusted Archive | QMDB-P8-CLOSE |
| P9 | Audio and Video Evidence | QMDB-P9-CLOSE |
| P10 | Recitation Clips and Community Safety | QMDB-P10-CLOSE |
| P11 | Search, Analytics, and National Reporting | QMDB-P11-CLOSE |
| P12 | Quality, Accessibility, and Production Hardening | QMDB-P12-CLOSE |
| P13 | Pilot, Offline Venue Mode, and National Rollout | QMDB-P13-CLOSE |

## P1 — Engineering and Repository Foundation

| Required element | Phase contract |
| --- | --- |
| Phase objective | Create the secure, testable Core PHP 8.5 modular-monolith repository, runtime, data-access, migration, HTTP/CLI/worker, rendering, localization and CI foundations without domain implementation. |
| Entry criteria | P0 freeze passes; ADR-053–055 approved; no `BLOCKS_P1_B01` decision remains; required tools/repository access available. |
| Principal modules | Bootstrap; Shared Kernel; Configuration; HTTP/CLI; Database; Migration; Logging; Worker; Presentation Foundation; Engineering Quality. |
| Expected executable artifacts | Composer project, entry points, typed configuration, kernel, module registry, PDO transaction layer, migration/seed ledger, safe errors/logging, worker/scheduler, secure templates/localization/RTL and CI gates. |
| Main requirements | All 14 P1-mapped requirements and cross-cutting locked boundary/constitutional constraints. |
| Main security controls | QMDB-CTL-001 and QMDB-CTL-021; secure defaults, dependency assurance and no secret disclosure. |
| Main database migrations | Framework metadata only; no QMDB-MIG domain group and no business table. |
| Main tests | Unit, integration, architecture, configuration, HTTP/CLI, MySQL transaction, migration, worker, rendering, accessibility-foundation, analysis/style and dependency audit. |
| Exit criteria | B01–B10 meet Done; clean install and QMDB-P1-CLOSE pass; 14/14 mapped requirements have accepted evidence; no P2-entry blocker. |
| Dependencies | Frozen P0 baseline; supported PHP, Composer and disposable MySQL/CI environments as individual batches require. |
| Explicit exclusions | Business modules/tables, domain seeds, production hosting/provider commitment, business UI and later-phase workflows. |

## P2 — Identity, Security, and Tenant Isolation

| Required element | Phase contract |
| --- | --- |
| Phase objective | Implement secure identity, authentication, authorization, sessions and structural shared-schema Workspace isolation. |
| Entry criteria | P1-CLOSE; identity/MFA/session decisions for scope approved; tenancy and security owners accept test plan. |
| Principal modules | Identity and Access; Tenancy; Security Operations; Audit foundations. |
| Expected executable artifacts | Identity schema/services, credential and MFA flows, session controls, RBAC/capability policies, tenant context/repositories, security events and administration endpoints. |
| Main requirements | Approved P2 requirements in the implementation map. |
| Main security controls | QMDB-CTL-002, 003, 004, 005, 008 and 020. |
| Main database migrations | QMDB-MIG-001 and applicable QMDB-MIG-017–019 control/audit structures. |
| Main tests | Authentication, session/MFA, privilege, separation-of-duties, CSRF/rate-limit, cross-Workspace positive/negative, concurrency, audit and abuse tests. |
| Exit criteria | Identity and tenant boundaries operate without critical/high violations; P2-CLOSE passes. |
| Dependencies | P1 foundations and approved identity/security parameters. |
| Explicit exclusions | People/guardian domain, Qur’an content, competition and public experiences. |

## P3 — Nigerian Geography, Organizations, People, and Guardianship

| Required element | Phase contract |
| --- | --- |
| Phase objective | Implement governed Nigerian geography, organization hierarchy, person/profile and consent-aware guardianship records. |
| Entry criteria | P2-CLOSE; authoritative geography/organization sources and applicable minor/guardian policy gates approved. |
| Principal modules | Geography; Organizations; People and Profiles; Guardianship and Consent. |
| Expected executable artifacts | Controlled imports, organization membership, person/profile workflows, guardian relationships, consent/verification lifecycles and scoped administration. |
| Main requirements | Approved P3 requirements in the implementation map. |
| Main security controls | QMDB-CTL-005, 008, 010, 014, 015 and 020. |
| Main database migrations | QMDB-MIG-002–004. |
| Main tests | Provenance/import, hierarchy integrity, duplicate identity, authorization, guardian/consent/withdrawal, minor-safety, isolation, privacy and audit tests. |
| Exit criteria | Governed people and organization records support later participation safely; P3-CLOSE passes. |
| Dependencies | P2 identity/tenancy and approved legal/domain sources. |
| Explicit exclusions | Qur’an reference releases and competition execution. |

### P3-B02 completion record

`QMDB-P3-B02 — Person, Memorizer, Reciter, Competitor, and Guardian Profile Foundation` is complete as a bounded,
private global-Person foundation. It deliberately does not complete the Organizations, consent, public profile,
discovery, merge or tenant-owned-Person portions of P3. The next separately authorized batch is
`QMDB-P3-B03 — Organizations, Schools, Groups, Mosques, and Branches Registry`.

## P4 — Qur’an Reference and Governance

| Required element | Phase contract |
| --- | --- |
| Phase objective | Implement provenance-controlled, immutable/versioned canonical Qur’an references and governed supporting taxonomies. |
| Entry criteria | P3-CLOSE; canonical source, Arabic collation/normalization and religious-authority decisions required by scope approved. |
| Principal modules | Qur’an Reference and Content Governance; Controlled Taxonomies. |
| Expected executable artifacts | Import/release pipeline, provenance/checksums, versioned immutable content, validation, reference APIs and governance audit. |
| Main requirements | Approved P4 requirements in the implementation map. |
| Main security controls | QMDB-CTL-005, 015, 016 and 020. |
| Main database migrations | QMDB-MIG-005–007. |
| Main tests | Source provenance, Unicode/collation, checksum, reference integrity, immutability, supersession/rollback and authorization. |
| Exit criteria | An approved release can be imported, verified and referenced reproducibly; P4-CLOSE passes. |
| Dependencies | P3 governance actors and authoritative content decisions. |
| Explicit exclusions | Automated religious interpretation, competition and scoring. |

## P5 — Competition Configuration and Registration

| Required element | Phase contract |
| --- | --- |
| Phase objective | Implement versioned competition/rule configuration plus consent-aware registration, eligibility, review and participant snapshots. |
| Entry criteria | P4-CLOSE; competition-rule, eligibility, privacy and minor-registration policies required by scope approved. |
| Principal modules | Competition Configuration; Categories and Rules; Registration; Eligibility; Participant Records. |
| Expected executable artifacts | Competition hierarchy/rules, effective approvals, application/evidence flows, eligibility engine, review, consent checks and immutable approved snapshots. |
| Main requirements | Approved P5 requirements in the implementation map. |
| Main security controls | QMDB-CTL-005, 008, 010, 014 and 028. |
| Main database migrations | QMDB-MIG-008–010. |
| Main tests | Rule versioning, separation of duties, invalid states, age/guardian paths, eligibility boundaries, duplicate prevention, evidence authorization, snapshots and isolation. |
| Exit criteria | Approved competitions and eligible participants are reproducible and auditable; P5-CLOSE passes. |
| Dependencies | P2–P4 and approved rule/legal decisions. |
| Explicit exclusions | Scheduling, judging, score calculation and result publication. |

## P6 — Judging and Deterministic Scoring

| Required element | Phase contract |
| --- | --- |
| Phase objective | Implement judge assignment, server-authoritative score capture and deterministic exact calculation. |
| Entry criteria | P5-CLOSE; score formula, decimal precision, judging conflicts and correction policy approved. |
| Principal modules | Scheduling prerequisites; Judging; Scoring; Audit. |
| Expected executable artifacts | Judge panels/assignments, score sheets, validation/calculation services, immutable submissions, correction workflow and calculation evidence. |
| Main requirements | Approved P6 requirements in the implementation map. |
| Main security controls | QMDB-CTL-008, 009, 010, 014, 018 and 020. |
| Main database migrations | QMDB-MIG-011–013. |
| Main tests | Assignment conflicts, formula vectors, precision/rounding, authorization, locking/concurrency, replay/idempotency, correction history, isolation and load. |
| Exit criteria | Independent vectors prove deterministic, auditable scoring with no client authority; P6-CLOSE passes. |
| Dependencies | P4 reference and P5 competition/participants. |
| Explicit exclusions | Public live projections, final result publication, appeal and certificates. |

## P7 — Live Competition, Results, and Appeals

| Required element | Phase contract |
| --- | --- |
| Phase objective | Operate live events and controlled ranking, publication, correction and appeal adjudication. |
| Entry criteria | P6-CLOSE; tie-break, embargo/publication, live-transport and appeal rules approved. |
| Principal modules | Live Competition; Results; Appeals; Scheduling/Venues; Public-safe Projections. |
| Expected executable artifacts | Live operations, event/outbox projections, deterministic ranking, result snapshots, approval/publication states, appeal cases and supersession. |
| Main requirements | Approved P7 requirements in the implementation map. |
| Main security controls | QMDB-CTL-009, 010, 013, 014, 018 and 020. |
| Main database migrations | QMDB-MIG-014 plus applicable QMDB-MIG-017, 019 and 020. |
| Main tests | Live failure/recovery, ordering, duplicate delivery, ranking/ties, embargo, appeal effects, immutable history, privacy filtering and performance. |
| Exit criteria | Live operation and official result/appeal lifecycles are controlled and reproducible; P7-CLOSE passes. |
| Dependencies | P5–P6 and live/result governance decisions. |
| Explicit exclusions | Certificate/record-passport release and media evidence. |

## P8 — Certificates, Record Passport, and Trusted Archive

| Required element | Phase contract |
| --- | --- |
| Phase objective | Implement verifiable certificates, participant record passports and immutable/supersedable trusted archives. |
| Entry criteria | P7-CLOSE; signing, verification, disclosure, revocation and archive policies approved. |
| Principal modules | Certificates; Record Passport; Official Records; Trusted Archive. |
| Expected executable artifacts | Certificate issuance/signing abstraction, verification, revocation/supersession, passport projection, archive preservation and public-safe validation. |
| Main requirements | Approved P8 requirements in the implementation map. |
| Main security controls | QMDB-CTL-013, 014, 020 and 029. |
| Main database migrations | Certificate/official-record portions of QMDB-MIG-014 and QMDB-MIG-020. |
| Main tests | Signing boundary, forgery/tamper, verification, revocation, immutable history, disclosure allowlists, retention and accessibility. |
| Exit criteria | Official artifacts are independently verifiable and history-preserving; P8-CLOSE passes. |
| Dependencies | P7 approved results and signing/archive decisions. |
| Explicit exclusions | Media evidence and broad national analytics. |

## P9 — Audio and Video Evidence

| Required element | Phase contract |
| --- | --- |
| Phase objective | Implement private, consent-aware audio/video evidence ingestion, storage separation, processing and authorized review. |
| Entry criteria | P8-CLOSE; object storage, upload, malware scanning, media retention and minor-recording policy approved. |
| Principal modules | Media Evidence; Upload/Processing; Consent Enforcement; Secure Review. |
| Expected executable artifacts | Direct upload, quarantine/scan pipeline, metadata, private storage references, processing jobs, access grants and evidence lifecycle. |
| Main requirements | Approved P9 requirements in the implementation map. |
| Main security controls | QMDB-CTL-012, 015, 016 and 020. |
| Main database migrations | Evidence portions of QMDB-MIG-015. |
| Main tests | Type/size/signature validation, malware quarantine, broken upload, authorization, consent change, retention/deletion, provider failure and audit. |
| Exit criteria | Evidence is private by default, integrity-checked and accessible only through approved paths; P9-CLOSE passes. |
| Dependencies | P3 consent, P5 participation and approved media providers/policy. |
| Explicit exclusions | Public clips/community interaction and unrestricted distribution. |

## P10 — Recitation Clips and Community Safety

| Required element | Phase contract |
| --- | --- |
| Phase objective | Implement consent-safe clip derivation/publication, moderation, reporting and community-safety enforcement. |
| Entry criteria | P9-CLOSE; publication, moderation, takedown, appeal, interaction and child-safety policies approved. |
| Principal modules | Recitation Clips; Moderation; Abuse Reporting; Community Safety. |
| Expected executable artifacts | Clip derivation, consent/release checks, moderation queues, publication/takedown, reports, sanctions/appeals and safe public projections. |
| Main requirements | Approved P10 requirements in the implementation map. |
| Main security controls | QMDB-CTL-010, 012, 015, 016, 020 and 029. |
| Main database migrations | Community/clip portions of QMDB-MIG-015. |
| Main tests | Consent/withdrawal, moderator separation, abuse/rate-limit, takedown latency, appeal, minor privacy, cache invalidation, accessibility and audit. |
| Exit criteria | Only approved consent-valid clips publish and abuse paths are operational; P10-CLOSE passes. |
| Dependencies | P9 evidence and approved safety/moderation policy. |
| Explicit exclusions | Unmoderated social networking, private messaging and engagement-driven recommendation algorithms. |

## P11 — Search, Analytics, and National Reporting

| Required element | Phase contract |
| --- | --- |
| Phase objective | Implement permission-aware search, governed analytics, exports and national reporting from authoritative/rebuildable projections. |
| Entry criteria | P10-CLOSE; search provider, reporting definitions, data-disclosure, retention and export policies approved. |
| Principal modules | Search; Analytics; Reporting; Export; Notifications/Integrations where required. |
| Expected executable artifacts | Search indexes/APIs, reporting projections/jobs, dashboards, protected exports, delivery/outbox workers and data-quality indicators. |
| Main requirements | Approved P11 requirements in the implementation map. |
| Main security controls | QMDB-CTL-015, 016, 017, 018, 020 and 024. |
| Main database migrations | QMDB-MIG-016 and reporting/public projection portions of QMDB-MIG-020. |
| Main tests | Query authorization/leakage, stale/rebuild behavior, aggregation privacy, export injection/scale, delivery retry, accessibility, performance and audit. |
| Exit criteria | Search/analytics/reporting are governed, reproducible and privacy-safe; P11-CLOSE passes. |
| Dependencies | Prior domain phases and provider/reporting decisions. |
| Explicit exclusions | National rollout authorization and unapproved public datasets. |

## P12 — Quality, Accessibility, and Production Hardening

| Required element | Phase contract |
| --- | --- |
| Phase objective | Complete cross-cutting security, privacy, accessibility, resilience, observability and production assurance. |
| Entry criteria | Product capability complete; production legal/retention/incident/hosting decisions approved; operational environments available. |
| Principal modules | Security; Privacy; Accessibility; Audit; Observability; Incident Response; Backup/Recovery; Platform Operations. |
| Expected executable artifacts | Hardened policies/jobs, privacy lifecycle, accessible UI completion, SIEM/alerts, runbooks, backups/restores, DR, capacity tuning and release evidence. |
| Main requirements | Approved P12 requirements in the implementation map. |
| Main security controls | QMDB-CTL-019, 020, 021, 022, 023, 025, 026 and applicable complete control set. |
| Main database migrations | QMDB-MIG-017–019 and approved hardening changes. |
| Main tests | Full threat/abuse, penetration, privacy requests, retention/deletion, audit integrity, WCAG/manual RTL, load/capacity, chaos/failover, restore and incident exercises. |
| Exit criteria | Production assurance evidence approved with no production blocker; P12-CLOSE passes. |
| Dependencies | P2–P11 and production infrastructure/legal/operations decisions. |
| Explicit exclusions | Pilot or national go-live authorization. |

## P13 — Pilot, Offline Venue Mode, and National Rollout

| Required element | Phase contract |
| --- | --- |
| Phase objective | Prove controlled pilot operation, offline venue resilience, national-scale readiness, migration, support and rollout governance. |
| Entry criteria | P12-CLOSE; every production/national blocker closed; pilot scope and launch authority approved. |
| Principal modules | Offline Venue Operations; Synchronization; Release Governance; Migration; Capacity; Support; Training; National Operations. |
| Expected executable artifacts | Offline queue/sync/conflict handling, release candidate, evidence pack, migration/rollback, monitoring, training/support and staged rollout controls. |
| Main requirements | Approved P13 requirements in the implementation map. |
| Main security controls | QMDB-CTL-020, 023, 024, 026 and 027. |
| Main database migrations | QMDB-MIG-020 and final approved rollout/import steps. |
| Main tests | Offline duplicate/conflict/reconnect, pilot simulation, full regression, isolation, peak capacity, DR, migration rehearsal, accessibility, operations and rollback. |
| Exit criteria | Formal pilot then national go/no-go approvals with retained evidence and controlled deployment; P13-CLOSE passes. |
| Dependencies | All prior phases, venues/connectivity evidence and external national governance approval. |
| Explicit exclusions | No unapproved jurisdiction, organization, provider or dataset is implied by readiness. |

## Dependency flow

`P1 → P2 → P3 → P4 → P5 → P6 → P7 → P8 → P9 → P10 → P11 → P12 → P13`

Later phases may prepare research or designs, but implementation cannot bypass entry, decision, migration, control, acceptance or exit gates.

## Exact next action

QMDB-P3-B06 is complete: it adds a bounded P3 security verifier, explicit Person repository scope verification,
focused architecture evidence, threat/control reconciliation and deferred operational-evidence tracking. It does not
change the P2 frozen semantics or create P3 closeout artifacts. The next authorized action is **QMDB-P3-CLOSE** and
requires its own execution authorization.
