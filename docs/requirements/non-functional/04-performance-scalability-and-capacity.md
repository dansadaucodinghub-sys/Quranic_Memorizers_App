# Performance, Scalability, and Capacity Requirements

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B03 |
| Document Title | Performance, Scalability, and Capacity Requirements |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Performance and Capacity Engineering |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed for governance approval; inherited locked decisions remain binding |
| Related Documents | [B03 index](../P0-B03-non-functional-requirements-index.md); [Traceability](../P0-B03-traceability-matrix.md); [Product constitution](../../project/product-constitution.md) |

## Purpose

Define measurable workload, response, database, cache, queue, capacity-model, and evidence-led scalability obligations without inventing production targets.

## Scope

Competition-critical transactions, administration, public live delivery, media/social traffic, reporting/analytics, MySQL, Redis, workers, object storage, CDN, and read models.

## Interpretation and control posture

Requirements are implementation-neutral unless QMDB-BL-001 locks a technology. “Approved Baseline” identifies a direct locked-baseline obligation; “Proposed” requires governance approval; “Parameter Pending” is enforceable in structure and measurement but its target remains governed by the parameter register. Derived stores never replace authoritative MySQL records. Business policy and technical enforcement are both identified through traceability; neither creates legal or religious authority.

## Coverage profile

Five workload tiers; resource isolation; parameterized latency and throughput; indexed tenant queries; stable pagination; exact calculations; bounded transactions; cache safety; queue isolation, idempotency and backpressure; variable-based capacity model; stateless web and horizontal worker scaling; evidence-gated extraction.

## QMDB-NFR-PER-001 — Workload-tier isolation and priority

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-PER-001 |
| Title | Workload-tier isolation and priority |
| Quality Attribute | Performance |
| Requirement Statement | The QMDB runtime boundary shall isolate and prioritize Tier 1 competition-critical transactions over Tier 2 administration, Tier 3 public live, Tier 4 media/social, and Tier 5 reporting/analytics workloads. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Competition Critical |
| Applicable System Components | Web pools, MySQL, Redis, workers, queues, object storage, CDN |
| Applicable Actors | All workload producers and operators |
| Stimulus or Trigger | Peak competition demand, social spike, media backlog, report load, or resource pressure |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Reserve or prioritize critical capacity, apply bulkheads/backpressure, and shed/defer lower tiers before scoring, authorization, finalization or verification. |
| Response Measure | Load tests prove lower-tier saturation does not breach approved Tier 1 response/availability parameters or corrupt records. |
| Measurement Source | APM, queue/database metrics, workload load-test report |
| Failure Behavior | Throttle, queue, simplify or disable lower tiers first; never bypass integrity controls to increase throughput. |
| Security or Privacy Impact | Prevents social/media/report starvation of official operations. |
| Dependencies | QMDB-BL-001; QMDB-FR-RPT-003; QMDB-FR-MED-005; QMDB-FR-SCR-003 |
| Open Parameter References | QMDB-PAR-001 through QMDB-PAR-016 |
| Related Functional Requirements | QMDB-FR-RPT-003; QMDB-FR-MED-005; QMDB-FR-SCR-003 |
| Related Use Cases | QMDB-UC-036; QMDB-UC-062 |
| Related Business Invariants | INV-017; INV-021 |
| Related Threats | QMDB-THR-045 |
| Related Controls | QMDB-CTL-018; QMDB-CTL-024 |
| Planned Implementation Phase | P7 — Live Competition, Results, and Appeals |
| Verification Method | Mixed-workload load, stress and degradation tests |
| Required Evidence | Tier model, resource policy, SLI comparison and load evidence |
| Acceptance Criteria | Tier 4/5 saturation cannot block or alter Tier 1 operations within approved parameters. |
| Status | Parameter Pending |

## QMDB-NFR-PER-002 — Parameterized response performance

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-PER-002 |
| Title | Parameterized response performance |
| Quality Attribute | Performance |
| Requirement Statement | The QMDB service boundary shall measure authentication, score draft/save/submit, judge queue, check-in, certificate verification, live updates, search, dashboards, reports and upload authorization against approved QMDB quality parameters. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | High |
| Criticality | Operational |
| Applicable System Components | Web, APIs, MySQL, Redis, workers, live delivery |
| Applicable Actors | User, operator, performance engineer |
| Stimulus or Trigger | Representative request under normal, peak, degraded and recovery load |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Emit attributable latency/throughput/error measurements by workload and Workspace-safe dimensions, compare to approved values, and alert without high-cardinality personal leakage. |
| Response Measure | QMDB-PAR-001 through QMDB-PAR-011 each has a defined SLI, measurement source, load profile and evidence result. |
| Measurement Source | APM, synthetic tests, load tests, server timing |
| Failure Behavior | Fail or queue according to service tier; disclose controlled delay without accepting duplicate or unsafe shortcuts. |
| Security or Privacy Impact | Enables evidence-based performance and capacity decisions. |
| Dependencies | QMDB-BL-001; QMDB-FR-IAM-003; QMDB-FR-SCR-002; QMDB-FR-SCR-003; QMDB-FR-CER-003; QMDB-FR-SRH-001 |
| Open Parameter References | QMDB-PAR-001 through QMDB-PAR-011 |
| Related Functional Requirements | QMDB-FR-IAM-003; QMDB-FR-SCR-002; QMDB-FR-SCR-003; QMDB-FR-CER-003; QMDB-FR-SRH-001 |
| Related Use Cases | QMDB-UC-003; QMDB-UC-036; QMDB-UC-045 |
| Related Business Invariants | INV-017; INV-029 |
| Related Threats | QMDB-THR-045 |
| Related Controls | QMDB-CTL-024 |
| Planned Implementation Phase | P12 — Quality, Accessibility, and Production Hardening |
| Verification Method | Load, percentile-latency, throughput, error and regression tests |
| Required Evidence | Parameterized performance report and raw metric references |
| Acceptance Criteria | No unapproved target is claimed; every named workflow exposes a reproducible measure and safe threshold behavior. |
| Status | Parameter Pending |

## QMDB-NFR-PER-003 — Bounded database and cache performance

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-PER-003 |
| Title | Bounded database and cache performance |
| Quality Attribute | Performance |
| Requirement Statement | The QMDB persistence boundary shall index tenant filters and foreign keys, review query plans, prevent unbounded/N+1 access, use stable pagination and bounded batches/transactions, monitor slow queries, locks and deadlocks, and keep cache tenant-aware, bounded, expiring, invalidatable and non-authoritative. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Operational |
| Applicable System Components | MySQL, repositories, Redis cache, reporting |
| Applicable Actors | Application, worker, report generator, DBA |
| Stimulus or Trigger | Large list, batch, contested transaction, cache miss/stampede, replica lag, or query regression |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Use reviewed indexes and plans, cap work, retry deadlocks only idempotently, read primary for authoritative transitions, indicate stale projections, prevent stampedes, and fall back safely. |
| Response Measure | Repository/query plan checks, query-count budgets, batch bounds, lock metrics, cache size/tenant/expiry/invalidation and failure tests pass. |
| Measurement Source | MySQL performance schema/APM, Redis metrics, query regression suite |
| Failure Behavior | Reject/queue oversized work, bypass optional cache, and avoid stale replica for authoritative decisions. |
| Security or Privacy Impact | Prevents resource exhaustion, tenant leaks, stale finalization and contention. |
| Dependencies | QMDB-BL-001; QMDB-FR-SRH-001; QMDB-FR-RPT-001; QMDB-FR-SCR-004 |
| Open Parameter References | QMDB-PAR-028; QMDB-PAR-029 |
| Related Functional Requirements | QMDB-FR-SRH-001; QMDB-FR-RPT-001; QMDB-FR-SCR-004 |
| Related Use Cases | QMDB-UC-038; QMDB-UC-055; QMDB-UC-056 |
| Related Business Invariants | INV-005; INV-015; INV-017 |
| Related Threats | QMDB-THR-039; QMDB-THR-040 |
| Related Controls | QMDB-CTL-008; QMDB-CTL-009 |
| Planned Implementation Phase | P11 — Search, Analytics, and National Reporting |
| Verification Method | Query-plan, N+1, pagination, contention, deadlock, lag, stampede and cache-failure tests |
| Required Evidence | Plan baselines, query counts, lock/lag metrics, cache inspection |
| Acceptance Criteria | Oversized or stale reads cannot monopolize critical resources or authorize/finalize official state. |
| Status | Parameter Pending |

## QMDB-NFR-SCL-001 — Bounded idempotent queue capacity

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-SCL-001 |
| Title | Bounded idempotent queue capacity |
| Quality Attribute | Scalability and Capacity |
| Requirement Statement | The QMDB asynchronous-work boundary shall separate streams/queues by workload, cap concurrency and retries, use exponential backoff with jitter, isolate media and notifications, detect poison messages, support dead letters, expose depth/age, apply backpressure, and make every consumer idempotent. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Operational |
| Applicable System Components | Transactional outbox, Redis Streams, workers, media, notifications |
| Applicable Actors | Publisher, consumer, platform operator |
| Stimulus or Trigger | Message delivery, retry, crash after commit, poison input, backlog, or overload |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Prioritize live official events, deduplicate by stable identity, resume safely after commit, quarantine poison work, and shed lower-tier work when approved depth/age thresholds are exceeded. |
| Response Measure | Duplicate/crash/retry/backlog tests show one business effect; QMDB-PAR-029 governs maximum queue age. |
| Measurement Source | Queue metrics, outbox/consumer ledger, dead-letter evidence |
| Failure Behavior | Pause or quarantine the affected stream and preserve authoritative transaction; never retry without bound. |
| Security or Privacy Impact | Prevents duplicate official records, queue collapse and cross-tier starvation. |
| Dependencies | QMDB-BL-001; QMDB-FR-AUD-001; QMDB-FR-NTF-001; QMDB-FR-MED-005 |
| Open Parameter References | QMDB-PAR-029 |
| Related Functional Requirements | QMDB-FR-AUD-001; QMDB-FR-NTF-001; QMDB-FR-MED-005 |
| Related Use Cases | QMDB-UC-061; QMDB-UC-062 |
| Related Business Invariants | INV-007; INV-017 |
| Related Threats | QMDB-THR-041; QMDB-THR-042 |
| Related Controls | QMDB-CTL-018; QMDB-CTL-020 |
| Planned Implementation Phase | P7 — Live Competition, Results, and Appeals |
| Verification Method | Idempotency, crash-after-commit, poison, retry, backlog and load-shedding tests |
| Required Evidence | Consumer keys, retry policy, queue dashboard, dead-letter and reconciliation report |
| Acceptance Criteria | Duplicate or reordered delivery produces at most one authorized business effect and bounded observable failure. |
| Status | Parameter Pending |

## QMDB-NFR-SCL-002 — Variable-based evidence-led scalability

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-SCL-002 |
| Title | Variable-based evidence-led scalability |
| Quality Attribute | Scalability and Capacity |
| Requirement Statement | The QMDB architecture boundary shall maintain a measured capacity model for authenticated users, judges, sessions, competitors, panels, score rates, live viewers/events, media, social/search/report/notification load, database/audit growth and media storage before scaling or extracting services. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | High |
| Criticality | Business Critical |
| Applicable System Components | Architecture, web nodes, workers, MySQL, object storage, CDN, read models |
| Applicable Actors | Architecture Governance, Capacity Engineering, Platform Operations |
| Stimulus or Trigger | Forecast, load test, growth review, scaling event, or extraction proposal |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Use QMDB-PAR-012 through QMDB-PAR-015 and the capacity-variable catalog; scale stateless web/workers/media horizontally, use governed database/read/CDN strategies, and approve extraction only from measured need. |
| Response Measure | Capacity model has symbols, sources, dependencies, risks, approval phases and load-test mappings; extraction proposals contain evidence and preserve modular-monolith authority. |
| Measurement Source | Capacity dashboard, load tests, growth forecasts, ADR review |
| Failure Behavior | Add capacity or degrade lower tiers; do not introduce premature microservices or weaken transactions. |
| Security or Privacy Impact | Prevents under-capacity peaks and unjustified distributed complexity. |
| Dependencies | QMDB-BL-001; QMDB-FR-OPS-003; QMDB-FR-RPT-003; QMDB-FR-MED-005 |
| Open Parameter References | QMDB-PAR-012 through QMDB-PAR-015 |
| Related Functional Requirements | QMDB-FR-OPS-003; QMDB-FR-RPT-003; QMDB-FR-MED-005 |
| Related Use Cases | QMDB-UC-056; QMDB-UC-062 |
| Related Business Invariants | INV-017; INV-028 |
| Related Threats | QMDB-THR-045; QMDB-THR-037 |
| Related Controls | QMDB-CTL-024; QMDB-CTL-021 |
| Planned Implementation Phase | P13 — Pilot, Offline Venue Mode, and National Rollout |
| Verification Method | Capacity-model review; load, soak, growth and scaling exercise |
| Required Evidence | Approved assumptions, results, bottleneck analysis and extraction decision evidence |
| Acceptance Criteria | National rollout cannot rely on unapproved capacity assumptions or service extraction without measured justification. |
| Status | Parameter Pending |

## Workload-class model

| Class | Included workloads | Isolation and priority rule |
| --- | --- | --- |
| Tier 1 — Competition-Critical Transactions | Authentication for active competition roles; Participant check-in; Judge score drafting/submission/locking; Result aggregation/finalization; Certificate verification | Reserved/highest-priority capacity and dependencies; no lower tier may force integrity or authorization shortcuts. |
| Tier 2 — Competition Administration | Registration review; scheduling; Judge assignment; Appeal handling; Certificate issuance | Protected administrative capacity; may queue behind Tier 1 without losing acknowledged state. |
| Tier 3 — Public Live Experience | Live scoreboard; competition browsing; public Results | Scalable public read models and delivery; may become stale-labelled or temporarily unavailable before Tier 1 degrades. |
| Tier 4 — Media and Social | Uploads; transcoding; Recitation Clips; reactions; comments; feed ranking | Isolated queues/workers; backpressure, throttle, or disable before competition operations are affected. |
| Tier 5 — Reporting and Analytics | Dashboards; exports; aggregate reporting; historical analytics | Uses bounded jobs and approved replicas/read models; never creates unbounded primary-store load. |

## Capacity-variable catalog

No row contains an approved production value. Product and Capacity Governance approve values through QMDB-PAR-012 through QMDB-PAR-015 and linked service parameters.

| Symbol | Meaning | Measurement Source | Dependency | Risk | Required Approval Phase | Load-Test Use |
| --- | --- | --- | --- | --- | --- | --- |
| `U_auth` | Concurrent authenticated users | Session/active-request telemetry | Identity, web, Session store | Authentication and global resource saturation | P13 | Sets authenticated virtual-user concurrency. |
| `J_conc` | Concurrent Judges | Assignment and active-scoring telemetry | IAM, scoring, MySQL | Tier 1 scoring contention | P13 | Sets Judge concurrency and think-time model. |
| `S_active` | Active competition Sessions | Competition schedule/runtime telemetry | Competition, scoring, live delivery | Shared-resource burst concentration | P13 | Partitions simultaneous competition load. |
| `C_session` | Competitors per Session | Approved Session roster | Registration and scheduling | Queue/check-in/scoring volume | P5 | Sizes roster, check-in, draw and scoring data. |
| `J_panel` | Judge count per panel | Judge Panel configuration | Assignment and quorum rules | Quorum and Score Sheet multiplication | P6 | Multiplies sheets and aggregation commands. |
| `D_score` | Score draft saves per minute | Scoring command metrics | Web, MySQL, draft/version handling | Write and lock contention | P6 | Drives save-rate and recovery tests. |
| `F_score` | Score submissions per minute | Submitted Score Sheet metrics | Authorization, exact scoring, MySQL, outbox | Competition-critical write burst | P6 | Drives submit/concurrency/idempotency tests. |
| `V_live` | Concurrent public live viewers | Edge/SSE synthetic and aggregate telemetry | Read model, Redis, CDN/SSE | Public load reaches origin/Tier 1 | P13 | Sets public connection spike and reconnect load. |
| `E_live` | Live events per second | Outbox/projection/SSE metrics | MySQL outbox, Redis Streams, delivery | Backlog and stale public state | P7 | Drives event sequencing, fan-out, and lag tests. |
| `M_up` | Concurrent media uploads | Upload authorization/object telemetry | API, object quarantine | Bandwidth and storage admission pressure | P9 | Sets simultaneous upload/authorization load. |
| `M_min` | Media minutes uploaded per hour | Object/media metadata aggregates | Object storage and FFmpeg workers | Transcoding/storage backlog | P9 | Derives processing CPU and queue demand. |
| `R_social` | Social-feed requests per second | Community endpoint metrics | Cache/read model/community store | Social starvation of scoring | P10 | Saturates Tier 4 during mixed-tier tests. |
| `Q_search` | Search queries per second | Search API metrics | Search projection/cache | Projection and origin saturation | P11 | Drives public/authorized mixed-language search load. |
| `R_jobs` | Concurrent report-generation jobs | Reporting job ledger | Approved replica/read model/workers | Database/worker starvation and export exposure | P11 | Exercises asynchronous threshold and isolation. |
| `N_out` | Notification volume per interval | Notification delivery ledger | Outbox, providers, workers | Provider/queue backlog | P12 | Drives retry, provider outage, and dead-letter tests. |
| `G_db` | Authoritative database growth per competition | MySQL table/index growth telemetry | Competitions, scores, audit | Query/index/backup growth | P13 | Sizes long-duration and growth-regression datasets. |
| `G_audit` | Audit-event growth per interval | Audit table/checkpoint metrics | All significant actions | Storage, verification, export growth | P12 | Drives chain verification, retention, and restore tests. |
| `G_media` | Media-storage growth per interval | Object inventory/metadata | Media duration, derivatives, retention | Cost, lifecycle, restore scope | P9 | Sizes storage, manifest, recovery, and purge tests. |

## Change control

Changes require impact review across the [parameter register](../../operations/quality-attribute-parameter-register.md), [threat model](../../security/threat-model.md), [acceptance scenarios](../P0-B03-non-functional-acceptance-scenarios.md), and [traceability matrix](../P0-B03-traceability-matrix.md). Locked ADR-001 through ADR-020 cannot be weakened here.
