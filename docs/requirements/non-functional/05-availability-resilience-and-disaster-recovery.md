# Availability, Resilience, and Disaster-Recovery Requirements

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B03 |
| Document Title | Availability, Resilience, and Disaster-Recovery Requirements |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Business Continuity and Reliability Engineering |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed for governance approval; inherited locked decisions remain binding |
| Related Documents | [B03 index](../P0-B03-non-functional-requirements-index.md); [Traceability](../P0-B03-traceability-matrix.md); [Product constitution](../../project/product-constitution.md) |

## Purpose

Define service criticality, permitted degradation, resilience, backup, recovery, disaster-recovery, and offline venue obligations.

## Scope

All authoritative and derived capabilities and their MySQL, Redis, object-storage, CDN, notification, search, signing, monitoring, and Venue Edge Node dependencies.

## Interpretation and control posture

Requirements are implementation-neutral unless QMDB-BL-001 locks a technology. “Approved Baseline” identifies a direct locked-baseline obligation; “Proposed” requires governance approval; “Parameter Pending” is enforceable in structure and measurement but its target remains governed by the parameter register. Derived stores never replace authoritative MySQL records. Business policy and technical enforcement are both identified through traceability; neither creates legal or religious authority.

## Coverage profile

Service tiers; safe degradation; timeouts, retries, circuit breakers, bulkheads and load shedding; transactional outbox and reconciliation; encrypted point-in-time recoverable backups; immutable copies; restore evidence; disaster declaration and service order; signed offline packages and central reconciliation.

## QMDB-NFR-AVL-001 — Service criticality and recovery ordering

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-AVL-001 |
| Title | Service criticality and recovery ordering |
| Quality Attribute | Availability |
| Requirement Statement | The QMDB operations boundary shall classify every capability by criticality, authoritative and optional dependencies, permitted/prohibited degradation, recovery priority, communication, data-loss tolerance and downtime parameters. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Business Critical |
| Applicable System Components | All services and dependencies |
| Applicable Actors | Platform operator, incident commander, business continuity owner |
| Stimulus or Trigger | Architecture/release review, outage, disaster, or service restoration |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Use the controlled service matrix to protect authentication, authorization, tenancy, scoring, finalization and verification before public/social/analytics restoration. |
| Response Measure | Every deployed capability maps to a tier and QMDB-PAR-016 through QMDB-PAR-018 as applicable; recovery exercises follow documented order. |
| Measurement Source | Service catalog, recovery exercise, availability metrics |
| Failure Behavior | If classification or authority is missing, treat authoritative mutation as unavailable and prioritize safe read/verification paths. |
| Security or Privacy Impact | Prevents unsafe degradation and arbitrary recovery order. |
| Dependencies | QMDB-BL-001; QMDB-FR-OPS-003; QMDB-FR-OPS-004 |
| Open Parameter References | QMDB-PAR-016; QMDB-PAR-017; QMDB-PAR-018 |
| Related Functional Requirements | QMDB-FR-OPS-003; QMDB-FR-OPS-004 |
| Related Use Cases | QMDB-UC-062 |
| Related Business Invariants | INV-017; INV-030 |
| Related Threats | QMDB-THR-039; QMDB-THR-045 |
| Related Controls | QMDB-CTL-023; QMDB-CTL-024 |
| Planned Implementation Phase | P12 — Quality, Accessibility, and Production Hardening |
| Verification Method | Service-catalog review; degradation and recovery-order exercises |
| Required Evidence | Criticality matrix, dependency map, exercise timeline |
| Acceptance Criteria | Every capability has an explicit safe degraded mode or explicit unavailable state before production approval. |
| Status | Parameter Pending |

## QMDB-NFR-RES-001 — Dependency-specific graceful degradation

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-RES-001 |
| Title | Dependency-specific graceful degradation |
| Quality Attribute | Resilience |
| Requirement Statement | The QMDB runtime boundary shall degrade Redis, Streams, replicas, primary MySQL, object storage, CDN, transcoding, notification providers, search, analytics, social feed, SSE, signing and monitoring according to explicit safe behavior that never permits unauthorized or unverifiable official mutation. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Competition Critical |
| Applicable System Components | All runtime dependencies and capabilities |
| Applicable Actors | User, operator, dependent service |
| Stimulus or Trigger | Dependency timeout, failure, lag, overload, or recovery |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Use authoritative source or verified snapshot where allowed, pause unsafe writes, queue bounded work, label stale/unavailable state, and reduce social/analytics/media before competition operations. |
| Response Measure | Fault-injection matrix passes for each dependency with no unauthorized mutation, duplicate effect, private-data leak, or false freshness. |
| Measurement Source | Chaos/fault tests, service metrics, audit/reconciliation |
| Failure Behavior | Stop affected official transition and preserve recoverable state; communicate status without exposing internals. |
| Security or Privacy Impact | Prevents cascading failure and integrity compromise. |
| Dependencies | QMDB-BL-001; QMDB-FR-OPS-003; QMDB-FR-MED-005; QMDB-FR-NTF-002 |
| Open Parameter References | QMDB-PAR-007; QMDB-PAR-029; QMDB-PAR-031 |
| Related Functional Requirements | QMDB-FR-OPS-003; QMDB-FR-MED-005; QMDB-FR-NTF-002 |
| Related Use Cases | QMDB-UC-062 |
| Related Business Invariants | INV-015; INV-017; INV-021 |
| Related Threats | QMDB-THR-039 through QMDB-THR-045 |
| Related Controls | QMDB-CTL-018; QMDB-CTL-023 |
| Planned Implementation Phase | P12 — Quality, Accessibility, and Production Hardening |
| Verification Method | Dependency fault injection, recovery, reconciliation and user-message tests |
| Required Evidence | Completed failure matrix, event timeline, state comparison and communications |
| Acceptance Criteria | Every named dependency failure has deterministic safe behavior and recovery evidence. |
| Status | Parameter Pending |

## QMDB-NFR-RES-002 — Bounded resilience patterns

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-RES-002 |
| Title | Bounded resilience patterns |
| Quality Attribute | Resilience |
| Requirement Statement | The QMDB integration and worker boundary shall apply explicit timeouts, bounded retries, exponential backoff, jitter, circuit breakers where justified, idempotency, duplicate detection, outbox, dead letters, reconciliation, health/readiness checks, bulkheads, backpressure, load shedding, maintenance mode, controlled feature flags and safe failure messages. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Operational |
| Applicable System Components | HTTP clients, APIs, workers, outbox, Redis, providers, deployment |
| Applicable Actors | Application, worker, operator |
| Stimulus or Trigger | Slow dependency, transient fault, duplicate, poison message, overload, maintenance, or feature change |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Contain the fault within its bulkhead, preserve transaction truth, stop retries at policy limit, expose health, reconcile effects, and restore through controlled flags or maintenance state. |
| Response Measure | Resilience pattern tests prove bounded calls and retries, correct readiness, idempotency and no unauthorized fail-open behavior. |
| Measurement Source | Traces, retry counters, circuit/health metrics, reconciliation ledger |
| Failure Behavior | Open/stop the circuit, queue or reject safely, and retain evidence for manual recovery. |
| Security or Privacy Impact | Prevents retry storms, cascading outages and duplicate state. |
| Dependencies | QMDB-BL-001; QMDB-FR-OPS-003; QMDB-FR-AUD-001; QMDB-FR-INT-002 |
| Open Parameter References | QMDB-PAR-026; QMDB-PAR-029 |
| Related Functional Requirements | QMDB-FR-OPS-003; QMDB-FR-AUD-001; QMDB-FR-INT-002 |
| Related Use Cases | QMDB-UC-061; QMDB-UC-062 |
| Related Business Invariants | INV-007; INV-028 |
| Related Threats | QMDB-THR-041; QMDB-THR-042; QMDB-THR-045 |
| Related Controls | QMDB-CTL-018; QMDB-CTL-020 |
| Planned Implementation Phase | P12 — Quality, Accessibility, and Production Hardening |
| Verification Method | Timeout, retry, circuit, readiness, idempotency, load-shed and reconciliation tests |
| Required Evidence | Configuration review, trace samples, fault-injection report |
| Acceptance Criteria | No retry path is unbounded and no degraded path bypasses authorization or authoritative validation. |
| Status | Parameter Pending |

## QMDB-NFR-DRC-001 — Encrypted restorable backups and PITR

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-DRC-001 |
| Title | Encrypted restorable backups and PITR |
| Quality Attribute | Backup and Recovery |
| Requirement Statement | The QMDB recovery boundary shall create encrypted, access-restricted, integrity-checked, separately controlled and immutable backup copies that support MySQL point-in-time recovery plus object, configuration, secret, key/checkpoint and audit recovery with tested runbooks. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Business Critical |
| Applicable System Components | MySQL, object storage, configuration, secrets, keys, audit checkpoints, backup systems |
| Applicable Actors | Backup operator, recovery operator, approver |
| Stimulus or Trigger | Scheduled backup, backup failure, restore test, corruption, loss, or ransomware |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Use separate credentials and custody, protect binary logs, verify completeness, restore in governed sequence, rebuild projections/Redis/search, reconcile records, and retain approval evidence. |
| Response Measure | Backup frequency/retention and RPO/RTO use QMDB-PAR-017 through QMDB-PAR-020; restore tests verify authoritative and cryptographic consistency. |
| Measurement Source | Backup logs, immutable-copy evidence, restore and reconciliation reports |
| Failure Behavior | Alert and contain failed backup; do not declare recoverability until successful verified restore evidence exists. |
| Security or Privacy Impact | Reduces irreversible loss, backup theft and false recovery confidence. |
| Dependencies | QMDB-BL-001; QMDB-FR-OPS-004; QMDB-FR-AUD-002; QMDB-FR-OPS-005 |
| Open Parameter References | QMDB-PAR-017; QMDB-PAR-018; QMDB-PAR-019; QMDB-PAR-020 |
| Related Functional Requirements | QMDB-FR-OPS-004; QMDB-FR-AUD-002; QMDB-FR-OPS-005 |
| Related Use Cases | QMDB-UC-045 |
| Related Business Invariants | INV-020; INV-030 |
| Related Threats | QMDB-THR-038; QMDB-THR-039 |
| Related Controls | QMDB-CTL-022; QMDB-CTL-023 |
| Planned Implementation Phase | P12 — Quality, Accessibility, and Production Hardening |
| Verification Method | Backup integrity, access, PITR, object/config/secret/key and full restore tests |
| Required Evidence | Backup manifests, immutable-copy proof, restore timeline, hashes and reconciliation approval |
| Acceptance Criteria | A successful backup claim requires a completed verified restore; lost projections are rebuildable from authoritative sources. |
| Status | Parameter Pending |

## QMDB-NFR-DRC-002 — Governed disaster recovery and continuity

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-DRC-002 |
| Title | Governed disaster recovery and continuity |
| Quality Attribute | Disaster Recovery |
| Requirement Statement | The QMDB continuity boundary shall define disaster declaration authority, recovery environment, failover, service restoration order, reconciliation, user communication, competition-day and certificate-verification continuity, venue fallback, exercises, review and closure approval. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Business Critical |
| Applicable System Components | Production and DR environments, all critical services |
| Applicable Actors | Incident commander, recovery authority, competition operations |
| Stimulus or Trigger | Declared disaster, site loss, prolonged primary outage, or exercise |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Activate approved environment and runbooks, restore critical services in order, verify data and signatures, reconcile, communicate, monitor, and record corrective actions. |
| Response Measure | RTO/RPO and exercise frequency use QMDB-PAR-017, QMDB-PAR-018 and QMDB-PAR-033; exercises produce signed evidence and tracked actions. |
| Measurement Source | DR exercise records, service SLIs, reconciliation and communications |
| Failure Behavior | Remain in controlled continuity/manual mode and prevent unverifiable official state until recovery approval. |
| Security or Privacy Impact | Reduces prolonged national outage and unsafe failover. |
| Dependencies | QMDB-BL-001; QMDB-FR-OPS-004; QMDB-FR-OFF-001 |
| Open Parameter References | QMDB-PAR-017; QMDB-PAR-018; QMDB-PAR-033 |
| Related Functional Requirements | QMDB-FR-OPS-004; QMDB-FR-OFF-001 |
| Related Use Cases | QMDB-UC-061; QMDB-UC-062 |
| Related Business Invariants | INV-017; INV-030 |
| Related Threats | QMDB-THR-039; QMDB-THR-045 |
| Related Controls | QMDB-CTL-023; QMDB-CTL-026 |
| Planned Implementation Phase | P13 — Pilot, Offline Venue Mode, and National Rollout |
| Verification Method | DR tabletop, failover, restore, continuity, communication and post-review exercise |
| Required Evidence | Declaration, timeline, RTO/RPO measures, reconciliation, approval and corrective actions |
| Acceptance Criteria | Disaster activation restores only verified services/data and cannot silently accept divergent official records. |
| Status | Parameter Pending |

## QMDB-NFR-RES-003 — Tamper-evident offline venue reconciliation

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-RES-003 |
| Title | Tamper-evident offline venue reconciliation |
| Quality Attribute | Offline Resilience |
| Requirement Statement | The QMDB Venue Edge boundary shall issue signed encrypted expiring assignment packages only to registered devices, keep local drafts non-authoritative with sequence and tamper evidence, and reconcile deferred events centrally with duplicate, replay, order, conflict and acceptance controls. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Competition Critical |
| Applicable System Components | Venue Edge Node, judge device, synchronization API, scoring domain |
| Applicable Actors | Venue operator, assigned Judge, synchronization operator |
| Stimulus or Trigger | Offline assignment, local draft/submit, clock variance, reconnect, replay, device compromise, or conflict |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Validate device/package/scope/version/rules/time, preserve local event order and hashes, accept each authorized event once, hold conflicts, expire local data, and produce reconciliation report. |
| Response Measure | Offline-window parameter QMDB-PAR-030 governs acceptance; tests cover expiry, replay, order, duplicates, tampering, conflict and manual fallback. |
| Measurement Source | Signed package/event ledger, central API audit, reconciliation report |
| Failure Behavior | Quarantine disputed or invalid events and never overwrite central official state automatically. |
| Security or Privacy Impact | Prevents replay, device compromise and divergent scoring. |
| Dependencies | QMDB-BL-001; QMDB-FR-OFF-001; QMDB-FR-OFF-002; QMDB-FR-SCR-004 |
| Open Parameter References | QMDB-PAR-030 |
| Related Functional Requirements | QMDB-FR-OFF-001; QMDB-FR-OFF-002; QMDB-FR-SCR-004 |
| Related Use Cases | QMDB-UC-061 |
| Related Business Invariants | INV-015; INV-030 |
| Related Threats | QMDB-THR-043; QMDB-THR-044 |
| Related Controls | QMDB-CTL-027; QMDB-CTL-020 |
| Planned Implementation Phase | P13 — Pilot, Offline Venue Mode, and National Rollout |
| Verification Method | Package signing, device, expiry, offline, replay, ordering, conflict and reconciliation tests |
| Required Evidence | Package manifest, event hashes, sync responses, reconciliation approval |
| Acceptance Criteria | Offline data is accepted at most once only after normal central authorization and scoring validation. |
| Status | Parameter Pending |

## Change control

Changes require impact review across the [parameter register](../../operations/quality-attribute-parameter-register.md), [threat model](../../security/threat-model.md), [acceptance scenarios](../P0-B03-non-functional-acceptance-scenarios.md), and [traceability matrix](../P0-B03-traceability-matrix.md). Locked ADR-001 through ADR-020 cannot be weakened here.

