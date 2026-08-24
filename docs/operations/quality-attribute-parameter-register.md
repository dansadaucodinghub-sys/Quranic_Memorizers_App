# Quality-Attribute Parameter Register

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B03 |
| Document Title | Quality-Attribute Parameter Register |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Product, Security, Privacy, Capacity and Business Continuity Governance |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed for governance approval; no open value is approved by this document |
| Related Documents | [B03 NFR index](../requirements/P0-B03-non-functional-requirements-index.md); [Open decisions](../project/open-decisions.md) |

## Purpose

Control measurable values that require organizational approval without turning omissions into invented production promises.

## Scope and policy

Every record is open. The stated conservative behavior is mandatory while the value is unresolved. Approval requires a named role, evidence and register/decision reference; this file does not approve legal, retention, security, capacity, availability or recovery policy.

## QMDB-PAR-001 — Authentication response time

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-001 |
| Name | Authentication response time |
| Description | Service response distribution for interactive authentication. |
| Affected Requirements | QMDB-NFR-PER-002 |
| Measurement Unit | milliseconds and percentile |
| Current Status | Open |
| Current Conservative Behavior | Instrument and fail with an accessible retry; do not weaken authentication. |
| Decision Owner Role | Identity and Security Governance |
| Evidence Required | Representative peak-load benchmark and user-risk review |
| Security Impact | Authentication abuse and timeout behavior |
| Privacy Impact | Authentication telemetry may contain personal/security metadata |
| Operational Impact | Capacity and user experience |
| Required Resolution Phase | P2 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Each material release |

## QMDB-PAR-002 — Score-draft save response time

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-002 |
| Name | Score-draft save response time |
| Description | Acknowledgement latency for save of a recoverable draft. |
| Affected Requirements | QMDB-NFR-PER-002 |
| Measurement Unit | milliseconds and percentile |
| Current Status | Open |
| Current Conservative Behavior | Preserve local/server draft distinction and show pending state. |
| Decision Owner Role | Scoring Governance |
| Evidence Required | Competition-like latency/load and accessibility evidence |
| Security Impact | Retries must remain idempotent |
| Privacy Impact | Draft may contain restricted competition data |
| Operational Impact | Judge continuity |
| Required Resolution Phase | P6 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Each scoring release |

## QMDB-PAR-003 — Score-submission response time

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-003 |
| Name | Score-submission response time |
| Description | End-to-end accepted/rejected receipt latency. |
| Affected Requirements | QMDB-NFR-PER-002; QMDB-NFR-UXR-001 |
| Measurement Unit | milliseconds and percentile |
| Current Status | Open |
| Current Conservative Behavior | Keep draft, prevent duplicate submission, and display authoritative receipt state. |
| Decision Owner Role | Scoring and Competition Operations |
| Evidence Required | Peak concurrency, exact scoring, idempotency and network-loss tests |
| Security Impact | Unsafe timeout cannot imply acceptance |
| Privacy Impact | Score and judge identifiers are restricted |
| Operational Impact | Competition-critical capacity |
| Required Resolution Phase | P6 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Each scoring release |

## QMDB-PAR-004 — Judge queue load time

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-004 |
| Name | Judge queue load time |
| Description | Response distribution for assigned work queue. |
| Affected Requirements | QMDB-NFR-PER-002 |
| Measurement Unit | milliseconds and percentile |
| Current Status | Open |
| Current Conservative Behavior | Load minimal paginated assignment data and never broaden scope. |
| Decision Owner Role | Competition Operations |
| Evidence Required | Peak panels, pagination and authorization tests |
| Security Impact | Queue cannot leak another panel |
| Privacy Impact | Assignments are confidential operational data |
| Operational Impact | Judge productivity |
| Required Resolution Phase | P6 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Each competition release |

## QMDB-PAR-005 — Check-in response time

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-005 |
| Name | Check-in response time |
| Description | Response distribution for competitor check-in. |
| Affected Requirements | QMDB-NFR-PER-002 |
| Measurement Unit | milliseconds and percentile |
| Current Status | Open |
| Current Conservative Behavior | Show pending/accepted state and prevent duplicate check-in. |
| Decision Owner Role | Competition Operations |
| Evidence Required | Venue load, retry, mobile and accessibility tests |
| Security Impact | No bypass of eligibility or scope |
| Privacy Impact | Attendance is personal competition data |
| Operational Impact | Venue throughput |
| Required Resolution Phase | P5 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Each competition release |

## QMDB-PAR-006 — Certificate verification response time

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-006 |
| Name | Certificate verification response time |
| Description | Public verification response distribution. |
| Affected Requirements | QMDB-NFR-PER-002 |
| Measurement Unit | milliseconds and percentile |
| Current Status | Open |
| Current Conservative Behavior | Return minimal verified status or controlled unavailable state; never bypass signature validation. |
| Decision Owner Role | Certificate Governance |
| Evidence Required | Peak public load and cryptographic tests |
| Security Impact | Do not trust stale or invalid signing state |
| Privacy Impact | Public output remains minimized |
| Operational Impact | Public trust and continuity |
| Required Resolution Phase | P8 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Each certificate release |

## QMDB-PAR-007 — Public live-update delay

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-007 |
| Name | Public live-update delay |
| Description | Authoritative-event-to-client display delay and reconnect window. |
| Affected Requirements | QMDB-NFR-ACC-003; QMDB-NFR-UXR-001; QMDB-NFR-RES-001 |
| Measurement Unit | milliseconds/seconds and percentile |
| Current Status | Open |
| Current Conservative Behavior | Label delayed/stale state and reconnect by sequence; never fabricate current data. |
| Decision Owner Role | Competition Operations and SRE |
| Evidence Required | Peak SSE, reconnect, screen-reader and degraded-mode tests |
| Security Impact | Sequence/replay integrity |
| Privacy Impact | Public projection only |
| Operational Impact | Live user experience |
| Required Resolution Phase | P7 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Each live release |

## QMDB-PAR-008 — Search response time

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-008 |
| Name | Search response time |
| Description | Response distribution for public and authorized search. |
| Affected Requirements | QMDB-NFR-PER-002 |
| Measurement Unit | milliseconds and percentile |
| Current Status | Open |
| Current Conservative Behavior | Use bounded pagination and label stale/unavailable projection. |
| Decision Owner Role | Search and Data Governance |
| Evidence Required | Mixed-language, tenant, privacy and peak-load tests |
| Security Impact | No scope bypass under timeout |
| Privacy Impact | Search output may expose personal data |
| Operational Impact | Search capacity |
| Required Resolution Phase | P11 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Each search release |

## QMDB-PAR-009 — Dashboard response time

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-009 |
| Name | Dashboard response time |
| Description | Response distribution for scoped dashboards. |
| Affected Requirements | QMDB-NFR-PER-002 |
| Measurement Unit | milliseconds and percentile |
| Current Status | Open |
| Current Conservative Behavior | Use asynchronous/stale-labeled views without shifting load to primary critical paths. |
| Decision Owner Role | Reporting and Platform Operations |
| Evidence Required | Peak reporting, scope and projection-lag tests |
| Security Impact | No unbounded primary query |
| Privacy Impact | Aggregation privacy |
| Operational Impact | Reporting capacity |
| Required Resolution Phase | P11 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Each dashboard release |

## QMDB-PAR-010 — Report generation threshold

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-010 |
| Name | Report generation threshold |
| Description | Boundary between synchronous and controlled asynchronous reports. |
| Affected Requirements | QMDB-NFR-PER-002 |
| Measurement Unit | estimated work units/seconds/rows |
| Current Status | Open |
| Current Conservative Behavior | Queue oversized work with expiry, scope recheck and receipt. |
| Decision Owner Role | Reporting Governance |
| Evidence Required | Representative data-volume and export security tests |
| Security Impact | No unbounded synchronous work |
| Privacy Impact | Exports may contain confidential data |
| Operational Impact | Worker/database isolation |
| Required Resolution Phase | P11 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Each reporting release |

## QMDB-PAR-011 — Media-upload authorization response time

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-011 |
| Name | Media-upload authorization response time |
| Description | Authorization-token issuance response distribution. |
| Affected Requirements | QMDB-NFR-PER-002; QMDB-NFR-MED-001 |
| Measurement Unit | milliseconds and percentile |
| Current Status | Open |
| Current Conservative Behavior | Issue no permission when policy/status is uncertain; token remains short-lived by approved policy. |
| Decision Owner Role | Media and Security Governance |
| Evidence Required | Peak uploads, consent, quota and authorization tests |
| Security Impact | No broad/long-lived upload authority |
| Privacy Impact | Media and Minor data exposure |
| Operational Impact | Media admission capacity |
| Required Resolution Phase | P9 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Each media release |

## QMDB-PAR-012 — Score-save and submission throughput

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-012 |
| Name | Score-save and submission throughput |
| Description | Sustained and burst rate for scoring commands. |
| Affected Requirements | QMDB-NFR-PER-001; QMDB-NFR-SCL-002 |
| Measurement Unit | commands per minute |
| Current Status | Open |
| Current Conservative Behavior | Prioritize scoring, shed lower tiers and preserve idempotency. |
| Decision Owner Role | Capacity Engineering and Scoring Governance |
| Evidence Required | National competition workload model and soak tests |
| Security Impact | No integrity shortcut under load |
| Privacy Impact | Minimal operational identifiers in metrics |
| Operational Impact | Web/MySQL/queue sizing |
| Required Resolution Phase | P6 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Before national pilot and each capacity change |

## QMDB-PAR-013 — Concurrent authenticated users

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-013 |
| Name | Concurrent authenticated users |
| Description | Assumed and tested active authenticated concurrency. |
| Affected Requirements | QMDB-NFR-PER-001; QMDB-NFR-SCL-002 |
| Measurement Unit | concurrent users |
| Current Status | Open |
| Current Conservative Behavior | Use conservative staged rollout and lower-tier throttling until approved. |
| Decision Owner Role | Product Operations and Capacity Engineering |
| Evidence Required | Forecast, pilot telemetry and load test |
| Security Impact | Overload must not fail open |
| Privacy Impact | Aggregate capacity metrics only |
| Operational Impact | Infrastructure sizing |
| Required Resolution Phase | P13 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Before national pilot |

## QMDB-PAR-014 — Concurrent judges and active sessions

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-014 |
| Name | Concurrent judges and active sessions |
| Description | Judge, panel and active competition concurrency model. |
| Affected Requirements | QMDB-NFR-PER-001; QMDB-NFR-SCL-002 |
| Measurement Unit | concurrent judges/sessions |
| Current Status | Open |
| Current Conservative Behavior | Cap pilot scope and reserve Tier 1 capacity. |
| Decision Owner Role | Competition Operations and Capacity Engineering |
| Evidence Required | Competition schedule model, panels, venue and load tests |
| Security Impact | No assignment/scope weakening |
| Privacy Impact | Judge activity is restricted |
| Operational Impact | Critical capacity |
| Required Resolution Phase | P13 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Before national pilot |

## QMDB-PAR-015 — Concurrent public live viewers

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-015 |
| Name | Concurrent public live viewers |
| Description | Public live audience and events-per-second model. |
| Affected Requirements | QMDB-NFR-PER-001; QMDB-NFR-SCL-002 |
| Measurement Unit | viewers and events per second |
| Current Status | Open |
| Current Conservative Behavior | Use CDN/public projections and degrade update frequency before scoring. |
| Decision Owner Role | Product Operations and Capacity Engineering |
| Evidence Required | Audience forecast, pilot telemetry and spike tests |
| Security Impact | Origin protection and sequence integrity |
| Privacy Impact | Public-only projection |
| Operational Impact | CDN/SSE capacity |
| Required Resolution Phase | P13 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Before public national event |

## QMDB-PAR-016 — Availability objectives by service tier

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-016 |
| Name | Availability objectives by service tier |
| Description | Approved availability objective for each criticality tier. |
| Affected Requirements | QMDB-NFR-PER-001; QMDB-NFR-AVL-001 |
| Measurement Unit | percentage over approved window |
| Current Status | Open |
| Current Conservative Behavior | Use service-criticality policy and do not claim an objective. |
| Decision Owner Role | Business Continuity Governance |
| Evidence Required | Business impact analysis, architecture and cost evidence |
| Security Impact | Availability cannot weaken security |
| Privacy Impact | Downtime communication may expose incident data |
| Operational Impact | Redundancy/staffing |
| Required Resolution Phase | P12 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Before production operational acceptance |

## QMDB-PAR-017 — Recovery Time Objective

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-017 |
| Name | Recovery Time Objective |
| Description | Maximum approved restoration time by service/data class. |
| Affected Requirements | QMDB-NFR-DAT-001; QMDB-NFR-DRC-001; QMDB-NFR-DRC-002 |
| Measurement Unit | time duration |
| Current Status | Open |
| Current Conservative Behavior | Restore in criticality order; make no numerical promise. |
| Decision Owner Role | Business Continuity Governance |
| Evidence Required | Business impact analysis and recovery exercises |
| Security Impact | Unsafe rush can corrupt data |
| Privacy Impact | Incident communications |
| Operational Impact | DR architecture and staffing |
| Required Resolution Phase | P12 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Before production resilience sign-off |

## QMDB-PAR-018 — Recovery Point Objective

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-018 |
| Name | Recovery Point Objective |
| Description | Maximum approved data-loss interval by record class. |
| Affected Requirements | QMDB-NFR-DAT-001; QMDB-NFR-DRC-001; QMDB-NFR-DRC-002 |
| Measurement Unit | time duration or zero-loss class |
| Current Status | Open |
| Current Conservative Behavior | Preserve transactional/PITR capability and do not accept unverified recovered writes. |
| Decision Owner Role | Records and Business Continuity Governance |
| Evidence Required | Business impact analysis, backup design and restore tests |
| Security Impact | Official record loss/tampering risk |
| Privacy Impact | Personal data recovery scope |
| Operational Impact | Backup/binlog design |
| Required Resolution Phase | P12 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Before production resilience sign-off |

## QMDB-PAR-019 — Backup frequency

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-019 |
| Name | Backup frequency |
| Description | Backup and log-capture cadence by dependency. |
| Affected Requirements | QMDB-NFR-DRC-001 |
| Measurement Unit | time interval |
| Current Status | Open |
| Current Conservative Behavior | Maintain recoverability design; block production sign-off without approved cadence. |
| Decision Owner Role | Database and Backup Operations |
| Evidence Required | RPO mapping, change rate, cost and restore evidence |
| Security Impact | Gaps increase ransomware/data-loss impact |
| Privacy Impact | Backups duplicate personal data |
| Operational Impact | Backup capacity |
| Required Resolution Phase | P12 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Before production data operations |

## QMDB-PAR-020 — Backup retention

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-020 |
| Name | Backup retention |
| Description | Retention and immutable-copy schedule by backup class. |
| Affected Requirements | QMDB-NFR-DRC-001 |
| Measurement Unit | time duration and copy count |
| Current Status | Open |
| Current Conservative Behavior | Use no silent deletion or indefinite policy claim; restrict and separately control copies. |
| Decision Owner Role | Records, Privacy and Backup Governance |
| Evidence Required | RPO/DR, legal/privacy and ransomware evidence |
| Security Impact | Too little/too much retention creates risk |
| Privacy Impact | Rights and retention conflict |
| Operational Impact | Storage and restore coverage |
| Required Resolution Phase | P12 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Before production backup automation |

## QMDB-PAR-021 — Audit-event retention

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-021 |
| Name | Audit-event retention |
| Description | Retention and checkpoint duration by audit category. |
| Affected Requirements | QMDB-NFR-PRI-002; QMDB-NFR-PRI-004; QMDB-NFR-AUD-001 |
| Measurement Unit | time duration |
| Current Status | Open |
| Current Conservative Behavior | Preserve significant official evidence under restricted access without asserting duration. |
| Decision Owner Role | Audit, Records and Privacy Governance |
| Evidence Required | Accountability, record, privacy and investigation review |
| Security Impact | Premature deletion defeats detection |
| Privacy Impact | Audit contains personal context |
| Operational Impact | Storage/search cost |
| Required Resolution Phase | P12 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Before production retention automation |

## QMDB-PAR-022 — Security and operational log retention

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-022 |
| Name | Security and operational log retention |
| Description | Retention by telemetry category. |
| Affected Requirements | QMDB-NFR-PRI-002; QMDB-NFR-PRI-004; QMDB-NFR-OBS-001 |
| Measurement Unit | time duration |
| Current Status | Open |
| Current Conservative Behavior | Minimize fields, restrict access, and retain only while purpose is documented. |
| Decision Owner Role | Security Operations and Privacy Governance |
| Evidence Required | Incident detection, privacy and storage evidence |
| Security Impact | Too short impairs detection; too long increases exposure |
| Privacy Impact | Logs include identifiers/network data |
| Operational Impact | Logging cost |
| Required Resolution Phase | P12 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Before production logging acceptance |

## QMDB-PAR-023 — Media and quarantine retention

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-023 |
| Name | Media and quarantine retention |
| Description | Retention by evidence, original, derivative, quarantine and removed-content class. |
| Affected Requirements | QMDB-NFR-MED-001; QMDB-NFR-PRI-002; QMDB-NFR-CHD-003 |
| Measurement Unit | time duration |
| Current Status | Open |
| Current Conservative Behavior | Keep restricted, hold when necessary, and neither publish nor auto-delete without policy. |
| Decision Owner Role | Media, Records, Privacy and Child-Safety Governance |
| Evidence Required | Purpose, appeal, safety, privacy, cost and legal review |
| Security Impact | Malware/evidence handling |
| Privacy Impact | Minor and recording exposure |
| Operational Impact | Object storage/lifecycle |
| Required Resolution Phase | P9 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Before production media lifecycle |

## QMDB-PAR-024 — Session idle duration

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-024 |
| Name | Session idle duration |
| Description | Maximum inactivity before reauthentication by risk class. |
| Affected Requirements | QMDB-NFR-IAM-001; QMDB-NFR-IAM-002 |
| Measurement Unit | time duration |
| Current Status | Open |
| Current Conservative Behavior | Use conservative short privileged sessions in design; approve through usability/security testing. |
| Decision Owner Role | Identity and Security Governance |
| Evidence Required | Threat model, role risk, accessibility and user research |
| Security Impact | Long sessions aid theft; short sessions can harm accessibility |
| Privacy Impact | Device/session telemetry |
| Operational Impact | Session-store capacity |
| Required Resolution Phase | P2 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Before authentication production acceptance |

## QMDB-PAR-025 — Session absolute duration

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-025 |
| Name | Session absolute duration |
| Description | Maximum Session lifetime regardless of activity. |
| Affected Requirements | QMDB-NFR-IAM-002 |
| Measurement Unit | time duration |
| Current Status | Open |
| Current Conservative Behavior | Require fresh authentication for privileged continuation; no final duration claim. |
| Decision Owner Role | Identity and Security Governance |
| Evidence Required | Threat, role, device and accessibility evidence |
| Security Impact | Persistence after compromise |
| Privacy Impact | Session inventory data |
| Operational Impact | Authentication load |
| Required Resolution Phase | P2 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Before authentication production acceptance |

## QMDB-PAR-026 — Rate and size limits

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-026 |
| Name | Rate and size limits |
| Description | Limits by authentication, API, upload, report, comment and report-abuse class. |
| Affected Requirements | QMDB-NFR-IAM-001; QMDB-NFR-SEC-002; QMDB-NFR-API-001; QMDB-NFR-PRI-003; QMDB-NFR-CHD-002; QMDB-NFR-RES-002 |
| Measurement Unit | requests/bytes per approved interval |
| Current Status | Open |
| Current Conservative Behavior | Apply conservative bounded requests and progressive friction without revealing account status. |
| Decision Owner Role | Security, Product and Operations Governance |
| Evidence Required | Abuse/load/accessibility tests and legitimate peak analysis |
| Security Impact | Too high enables abuse; too low creates denial |
| Privacy Impact | Identifiers used for limiting need minimization |
| Operational Impact | Edge/application capacity |
| Required Resolution Phase | P2 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Each major traffic change |

## QMDB-PAR-027 — Alert and release severity thresholds

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-027 |
| Name | Alert and release severity thresholds |
| Description | Thresholds for anomaly, finding, alert, incident and release block. |
| Affected Requirements | QMDB-NFR-IAM-001; QMDB-NFR-SEC-004; QMDB-NFR-OBS-003; QMDB-NFR-INC-001; QMDB-NFR-INF-001; QMDB-NFR-SUP-001; QMDB-NFR-SUP-002 |
| Measurement Unit | severity/rate/count over window |
| Current Status | Open |
| Current Conservative Behavior | Escalate credible high-impact signals conservatively; do not claim numeric threshold. |
| Decision Owner Role | Security and Release Governance |
| Evidence Required | Baseline telemetry, exercises, threat impact and false-positive review |
| Security Impact | Missed or storming alerts |
| Privacy Impact | Security telemetry contains personal context |
| Operational Impact | On-call/release load |
| Required Resolution Phase | P12 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Each incident or quarterly |

## QMDB-PAR-028 — Maximum acceptable replica lag

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-028 |
| Name | Maximum acceptable replica lag |
| Description | Lag boundary for non-authoritative reads and reporting. |
| Affected Requirements | QMDB-NFR-DAT-001; QMDB-NFR-PER-003; QMDB-NFR-OBS-003 |
| Measurement Unit | time or transaction lag |
| Current Status | Open |
| Current Conservative Behavior | Never use replica for authoritative finalization; label/pause stale projections. |
| Decision Owner Role | Database Architecture and Operations |
| Evidence Required | Consistency analysis, report tolerance and failover tests |
| Security Impact | Stale authorization/result risk |
| Privacy Impact | Stale privacy updates |
| Operational Impact | Read scaling |
| Required Resolution Phase | P11 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Each topology change |

## QMDB-PAR-029 — Maximum queue age and retry policy

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-029 |
| Name | Maximum queue age and retry policy |
| Description | Age/depth/retry limits by queue and workload. |
| Affected Requirements | QMDB-NFR-SEC-003; QMDB-NFR-API-001; QMDB-NFR-MED-001; QMDB-NFR-UXR-001; QMDB-NFR-SCL-001; QMDB-NFR-RES-001; QMDB-NFR-RES-002; QMDB-NFR-OBS-003 |
| Measurement Unit | time/count/depth |
| Current Status | Open |
| Current Conservative Behavior | Prioritize live official events, bound retry, dead-letter and shed lower tiers. |
| Decision Owner Role | SRE and Workload Owners |
| Evidence Required | Load/failure/recovery tests and business impact |
| Security Impact | Retry storm/duplicate effects |
| Privacy Impact | Delayed privacy/safety action |
| Operational Impact | Worker capacity |
| Required Resolution Phase | P7 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Each workload release |

## QMDB-PAR-030 — Maximum offline synchronization window

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-030 |
| Name | Maximum offline synchronization window |
| Description | Package/event age, clock tolerance and local-data expiry. |
| Affected Requirements | QMDB-NFR-UXR-001; QMDB-NFR-RES-003 |
| Measurement Unit | time duration |
| Current Status | Open |
| Current Conservative Behavior | Packages expire by governed policy; central acceptance holds uncertain or stale data. |
| Decision Owner Role | Competition Operations and Security Governance |
| Evidence Required | Venue risk, connectivity, device and reconciliation tests |
| Security Impact | Replay and stolen-device window |
| Privacy Impact | Local participant/score exposure |
| Operational Impact | Venue continuity |
| Required Resolution Phase | P13 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Before offline pilot |

## QMDB-PAR-031 — Maximum certificate-verification/signing outage

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-031 |
| Name | Maximum certificate-verification/signing outage |
| Description | Continuity boundary for issuance and public verification. |
| Affected Requirements | QMDB-NFR-CRY-001; QMDB-NFR-RES-001 |
| Measurement Unit | time duration |
| Current Status | Open |
| Current Conservative Behavior | Stop issuance without valid key; use only verified continuity mode for public status. |
| Decision Owner Role | Certificate and Business Continuity Governance |
| Evidence Required | Public trust, key custody, cache and recovery exercises |
| Security Impact | Forgery/invalid issuance risk |
| Privacy Impact | Minimal public data only |
| Operational Impact | Signing/verification continuity |
| Required Resolution Phase | P8 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Before certificate production |

## QMDB-PAR-032 — Trace sampling and retention

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-032 |
| Name | Trace sampling and retention |
| Description | Sampling and retention by critical flow and incident state. |
| Affected Requirements | QMDB-NFR-OBS-002 |
| Measurement Unit | percentage and time duration |
| Current Status | Open |
| Current Conservative Behavior | Always preserve required error/security correlations while minimizing payload and personal dimensions. |
| Decision Owner Role | SRE, Security and Privacy Governance |
| Evidence Required | Diagnostic value, cost, privacy and incident exercises |
| Security Impact | Missing evidence or sensitive leakage |
| Privacy Impact | Trace identifiers/payload risk |
| Operational Impact | Telemetry cost |
| Required Resolution Phase | P12 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Each telemetry architecture change |

## QMDB-PAR-033 — Disaster-recovery exercise frequency

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-033 |
| Name | Disaster-recovery exercise frequency |
| Description | Cadence and scope of recovery/continuity exercises. |
| Affected Requirements | QMDB-NFR-DRC-002 |
| Measurement Unit | time interval and scenario coverage |
| Current Status | Open |
| Current Conservative Behavior | Require exercise before national rollout and after material recovery change without claiming interval. |
| Decision Owner Role | Business Continuity Governance |
| Evidence Required | Risk, architecture change and exercise effectiveness |
| Security Impact | Untested recovery failure |
| Privacy Impact | Exercise data handling |
| Operational Impact | Staffing and environment cost |
| Required Resolution Phase | P13 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | Before national rollout |

## QMDB-PAR-034 — Optional numeric test-coverage target

| Field | Value |
| --- | --- |
| Parameter ID | QMDB-PAR-034 |
| Name | Optional numeric test-coverage target |
| Description | Governance-selected coverage metric if later required; never a substitute for risk-based tests. |
| Affected Requirements | QMDB-NFR-TST-001; QMDB-NFR-REL-001 |
| Measurement Unit | percentage by defined metric |
| Current Status | Open |
| Current Conservative Behavior | Require traced critical tests regardless of percentage; no numeric target is assumed. |
| Decision Owner Role | Engineering Quality Governance |
| Evidence Required | Defect history, test quality and maintainability review |
| Security Impact | Metric gaming can hide risk |
| Privacy Impact | Test data privacy |
| Operational Impact | CI duration |
| Required Resolution Phase | P12 |
| Approved Value | Not yet approved |
| Approval Reference | No approval recorded |
| Review Frequency | If governance proposes a numeric target |

## Change control

An approved value requires the accountable owner’s evidence, an approved decision reference, affected-NFR and test updates, and project-state recording. Superseded values retain history.

