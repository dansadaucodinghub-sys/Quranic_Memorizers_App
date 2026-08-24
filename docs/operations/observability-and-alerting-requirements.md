# Observability and Alerting Requirements

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B03 |
| Document Title | Observability and Alerting Requirements |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Site Reliability, Security and Operational Governance |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed for governance approval; no open value is approved by this document |
| Related Documents | [Observability NFRs](../requirements/non-functional/06-observability-audit-and-incident-response.md); [Parameter register](quality-attribute-parameter-register.md) |

## Purpose

Define required logs, metrics, traces, dashboards, alerts and owned runbook contracts.

## Telemetry policy

Logs are structured, UTC, correlated, redacted, access-controlled and integrity-protected. Metrics avoid uncontrolled personal/high-cardinality labels. Traces correlate critical requests, jobs, outbox consumers, media and notifications without restricted payloads. Retention/sampling and thresholds remain parameters.

## Service-level indicator catalog

| SLI ID | Indicator | Measurement Source | Owner Role | Coverage |
| --- | --- | --- | --- | --- |
| QMDB-SLI-001 | Request success/error rate | HTTP/app metrics | SRE | All runtime |
| QMDB-SLI-002 | Request latency distribution | APM/server timing | SRE | All interactive tiers |
| QMDB-SLI-003 | MySQL connection saturation | MySQL metrics | Database Operations | Authoritative store |
| QMDB-SLI-004 | Slow queries and plan regression | Performance schema/APM | Database Operations | Repositories/reporting |
| QMDB-SLI-005 | Deadlocks and lock waits | MySQL metrics | Database Operations | Scoring/results |
| QMDB-SLI-006 | Replication lag | MySQL replication metrics | Database Operations | Read projections/reporting |
| QMDB-SLI-007 | Queue depth and oldest age | Queue/Streams metrics | SRE | All asynchronous work |
| QMDB-SLI-008 | Failed/dead-letter jobs | Worker ledger | Service owner | Workers |
| QMDB-SLI-009 | Redis health and memory | Redis metrics | SRE | Cache/Streams |
| QMDB-SLI-010 | Transactional outbox backlog | MySQL/outbox metrics | SRE | Event delivery |
| QMDB-SLI-011 | Live-event end-to-end delay | Outbox/SSE/client synthetic | Competition Operations | Live scoreboard |
| QMDB-SLI-012 | Score-submit duration/failure | Application/domain metrics | Scoring owner | Judging |
| QMDB-SLI-013 | Missing Judge sheets/finalization failures | Domain reconciliation | Competition Operations | Results |
| QMDB-SLI-014 | Certificate issuance/verification failures | Certificate service metrics | Certificate Governance | Certificates |
| QMDB-SLI-015 | Upload failure/transcoding backlog | Media pipeline metrics | Media Operations | Media |
| QMDB-SLI-016 | Authentication failures/MFA resets | Identity security metrics | Security Operations | Identity |
| QMDB-SLI-017 | Cross-tenant denials/privilege/break-glass | Security/audit events | Security Operations | Authorization |
| QMDB-SLI-018 | Moderation/privacy request backlog | Case metrics | Safety/Privacy Operations | Moderation/privacy |
| QMDB-SLI-019 | Backup success and restore-test status | Backup/DR evidence | Business Continuity | Recovery |
| QMDB-SLI-020 | Audit-chain/checkpoint verification | Audit verifier | Audit Governance | Audit integrity |

## Required dashboards

Competition-day, security, privacy, moderation, media, database, queue, backup, certificate-verification and service-health dashboards must show relevant SLIs, current/approved thresholds, data freshness, degraded telemetry, ownership and linked runbooks. Dashboard access follows least privilege and Workspace/privacy minimization.

## Alert catalog

| Alert ID | Category | Threshold Parameter | Owner Role | Runbook |
| --- | --- | --- | --- | --- |
| QMDB-ALT-001 | Authentication attack/credential stuffing | QMDB-PAR-026; QMDB-PAR-027 | Security Operations | QMDB-RUN-001 |
| QMDB-ALT-002 | Privilege escalation or cross-Workspace attempt | QMDB-PAR-027 | Security Operations | QMDB-RUN-013 |
| QMDB-ALT-003 | Score tampering or submission failure | QMDB-PAR-027 | Competition Operations | QMDB-RUN-007 |
| QMDB-ALT-004 | Audit-chain/checkpoint failure | QMDB-PAR-027 | Audit and Security Operations | QMDB-RUN-015 |
| QMDB-ALT-005 | Signing or certificate verification failure | QMDB-PAR-027; QMDB-PAR-031 | Certificate Operations | QMDB-RUN-008; QMDB-RUN-009 |
| QMDB-ALT-006 | Backup failure | QMDB-PAR-027 | Backup Operations | QMDB-RUN-016 |
| QMDB-ALT-007 | Restore-test failure | QMDB-PAR-027 | Business Continuity | QMDB-RUN-017 |
| QMDB-ALT-008 | MySQL primary unavailable | QMDB-PAR-027 | Database Operations | QMDB-RUN-002 |
| QMDB-ALT-009 | Replica lag | QMDB-PAR-028 | Database Operations | QMDB-RUN-003 |
| QMDB-ALT-010 | Redis failure | QMDB-PAR-027 | SRE | QMDB-RUN-004 |
| QMDB-ALT-011 | Queue backlog/oldest age | QMDB-PAR-029 | SRE | QMDB-RUN-005 |
| QMDB-ALT-012 | Live-event delay | QMDB-PAR-007 | Competition Operations | QMDB-RUN-006 |
| QMDB-ALT-013 | Media malware detection | QMDB-PAR-027 | Media Security | QMDB-RUN-012 |
| QMDB-ALT-014 | Object storage or CDN outage | QMDB-PAR-027 | Media Operations | QMDB-RUN-010; QMDB-RUN-011 |
| QMDB-ALT-015 | Moderation/child-safety emergency | QMDB-PAR-027 | Child-Safety Operations | QMDB-RUN-019 |
| QMDB-ALT-016 | Suspicious support or break-glass use | QMDB-PAR-027 | Security Operations | QMDB-RUN-014 |
| QMDB-ALT-017 | Secret exposure | QMDB-PAR-027 | Security Operations | QMDB-RUN-014 |
| QMDB-ALT-018 | Social workload overload | QMDB-PAR-027 | SRE | QMDB-RUN-018 |
| QMDB-ALT-019 | Privacy incident | QMDB-PAR-027 | Privacy Operations | QMDB-RUN-020 |
| QMDB-ALT-020 | Offline synchronization conflict | QMDB-PAR-030 | Venue Operations | QMDB-RUN-021 |

## Required runbook contracts

| Runbook ID | Trigger/Scope | Owner Role | Minimum Required Content | Status |
| --- | --- | --- | --- | --- |
| QMDB-RUN-001 | Authentication outage or attack | Identity and Security Operations | Detection/confirmation; authority and severity; safe diagnosis; containment; continuity/degradation; evidence preservation; recovery/reconciliation; communication and escalation; verification; rollback; closure and corrective actions. | Required for implementation phase |
| QMDB-RUN-002 | MySQL primary failure | Database and Business Continuity Operations | Detection/confirmation; authority and severity; safe diagnosis; containment; continuity/degradation; evidence preservation; recovery/reconciliation; communication and escalation; verification; rollback; closure and corrective actions. | Required for implementation phase |
| QMDB-RUN-003 | Replica lag | Database Operations | Detection/confirmation; authority and severity; safe diagnosis; containment; continuity/degradation; evidence preservation; recovery/reconciliation; communication and escalation; verification; rollback; closure and corrective actions. | Required for implementation phase |
| QMDB-RUN-004 | Redis failure | SRE | Detection/confirmation; authority and severity; safe diagnosis; containment; continuity/degradation; evidence preservation; recovery/reconciliation; communication and escalation; verification; rollback; closure and corrective actions. | Required for implementation phase |
| QMDB-RUN-005 | Queue backlog | SRE and Workload Owner | Detection/confirmation; authority and severity; safe diagnosis; containment; continuity/degradation; evidence preservation; recovery/reconciliation; communication and escalation; verification; rollback; closure and corrective actions. | Required for implementation phase |
| QMDB-RUN-006 | Live-update outage | Competition Operations | Detection/confirmation; authority and severity; safe diagnosis; containment; continuity/degradation; evidence preservation; recovery/reconciliation; communication and escalation; verification; rollback; closure and corrective actions. | Required for implementation phase |
| QMDB-RUN-007 | Score-submission failure | Scoring and Competition Operations | Detection/confirmation; authority and severity; safe diagnosis; containment; continuity/degradation; evidence preservation; recovery/reconciliation; communication and escalation; verification; rollback; closure and corrective actions. | Required for implementation phase |
| QMDB-RUN-008 | Certificate-verification failure | Certificate Operations | Detection/confirmation; authority and severity; safe diagnosis; containment; continuity/degradation; evidence preservation; recovery/reconciliation; communication and escalation; verification; rollback; closure and corrective actions. | Required for implementation phase |
| QMDB-RUN-009 | Signing-service failure | Certificate and Security Key Custody | Detection/confirmation; authority and severity; safe diagnosis; containment; continuity/degradation; evidence preservation; recovery/reconciliation; communication and escalation; verification; rollback; closure and corrective actions. | Required for implementation phase |
| QMDB-RUN-010 | Object-storage failure | Media and Platform Operations | Detection/confirmation; authority and severity; safe diagnosis; containment; continuity/degradation; evidence preservation; recovery/reconciliation; communication and escalation; verification; rollback; closure and corrective actions. | Required for implementation phase |
| QMDB-RUN-011 | CDN failure | Platform Operations | Detection/confirmation; authority and severity; safe diagnosis; containment; continuity/degradation; evidence preservation; recovery/reconciliation; communication and escalation; verification; rollback; closure and corrective actions. | Required for implementation phase |
| QMDB-RUN-012 | Malware event | Media Security and Incident Response | Detection/confirmation; authority and severity; safe diagnosis; containment; continuity/degradation; evidence preservation; recovery/reconciliation; communication and escalation; verification; rollback; closure and corrective actions. | Required for implementation phase |
| QMDB-RUN-013 | Cross-tenant incident | Security and Privacy Operations | Detection/confirmation; authority and severity; safe diagnosis; containment; continuity/degradation; evidence preservation; recovery/reconciliation; communication and escalation; verification; rollback; closure and corrective actions. | Required for implementation phase |
| QMDB-RUN-014 | Privileged account compromise, secret exposure or break-glass misuse | Security Incident Commander | Detection/confirmation; authority and severity; safe diagnosis; containment; continuity/degradation; evidence preservation; recovery/reconciliation; communication and escalation; verification; rollback; closure and corrective actions. | Required for implementation phase |
| QMDB-RUN-015 | Audit verification failure | Audit and Security Operations | Detection/confirmation; authority and severity; safe diagnosis; containment; continuity/degradation; evidence preservation; recovery/reconciliation; communication and escalation; verification; rollback; closure and corrective actions. | Required for implementation phase |
| QMDB-RUN-016 | Backup failure | Backup Operations | Detection/confirmation; authority and severity; safe diagnosis; containment; continuity/degradation; evidence preservation; recovery/reconciliation; communication and escalation; verification; rollback; closure and corrective actions. | Required for implementation phase |
| QMDB-RUN-017 | Recovery operation | Business Continuity and Recovery Authority | Detection/confirmation; authority and severity; safe diagnosis; containment; continuity/degradation; evidence preservation; recovery/reconciliation; communication and escalation; verification; rollback; closure and corrective actions. | Required for implementation phase |
| QMDB-RUN-018 | Social overload | SRE and Community Operations | Detection/confirmation; authority and severity; safe diagnosis; containment; continuity/degradation; evidence preservation; recovery/reconciliation; communication and escalation; verification; rollback; closure and corrective actions. | Required for implementation phase |
| QMDB-RUN-019 | Child-safety emergency | Child-Safety Incident Authority | Detection/confirmation; authority and severity; safe diagnosis; containment; continuity/degradation; evidence preservation; recovery/reconciliation; communication and escalation; verification; rollback; closure and corrective actions. | Required for implementation phase |
| QMDB-RUN-020 | Privacy incident | Privacy Incident Authority | Detection/confirmation; authority and severity; safe diagnosis; containment; continuity/degradation; evidence preservation; recovery/reconciliation; communication and escalation; verification; rollback; closure and corrective actions. | Required for implementation phase |
| QMDB-RUN-021 | Venue offline synchronization conflict | Venue, Scoring and Security Operations | Detection/confirmation; authority and severity; safe diagnosis; containment; continuity/degradation; evidence preservation; recovery/reconciliation; communication and escalation; verification; rollback; closure and corrective actions. | Required for implementation phase |

## Escalation and evidence

Every alert records trigger data, threshold/version, deduplication key, owner, acknowledgement, escalation, incident link, action, resolution and closure. Alert-route or monitoring degradation uses an independent secondary notification path once selected. Full implementation runbooks are produced and exercised in their planned phases; P0 defines their required content and ownership only.

