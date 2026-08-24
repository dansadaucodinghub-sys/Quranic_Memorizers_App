# Observability, Audit, and Incident-Response Requirements

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B03 |
| Document Title | Observability, Audit, and Incident-Response Requirements |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Security Operations and Site Reliability Engineering |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed for governance approval; inherited locked decisions remain binding |
| Related Documents | [B03 index](../P0-B03-non-functional-requirements-index.md); [Traceability](../P0-B03-traceability-matrix.md); [Product constitution](../../project/product-constitution.md) |

## Purpose

Define safe telemetry, tamper-evident audit, alerting, and controlled incident-response obligations.

## Scope

Requests, background work, authoritative transitions, integrations, media, authentication, privacy, safety, backups, recovery, infrastructure, and operational access.

## Interpretation and control posture

Requirements are implementation-neutral unless QMDB-BL-001 locks a technology. “Approved Baseline” identifies a direct locked-baseline obligation; “Proposed” requires governance approval; “Parameter Pending” is enforceable in structure and measurement but its target remains governed by the parameter register. Derived stores never replace authoritative MySQL records. Business policy and technical enforcement are both identified through traceability; neither creates legal or religious authority.

## Coverage profile

Structured redacted logs; metrics and traces; correlation; append-oriented hash-linked audit with external checkpoints; dashboards and alerts; incident lifecycle from DETECTED through POST_INCIDENT_REVIEW; evidence preservation, containment, recovery, communication, regulatory assessment, corrective action, and closure.

## QMDB-NFR-OBS-001 — Structured redacted operational logging

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-OBS-001 |
| Title | Structured redacted operational logging |
| Quality Attribute | Observability |
| Requirement Statement | The QMDB logging boundary shall emit centrally collected searchable structured UTC logs with request/correlation, component, severity, category, safe actor and Workspace context while excluding credentials, Sessions, recovery/MFA/key material, complete identity evidence, private-storage credentials and restricted bodies. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Security Critical |
| Applicable System Components | Web, application, workers, integrations, database operations |
| Applicable Actors | Service identities, operators, auditors |
| Stimulus or Trigger | Request, job, error, security event, access, deployment, or recovery action |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Record sufficient safe context for investigation, apply field allowlists/redaction and access controls, protect integrity, and retain only under approved parameter. |
| Response Measure | Log schema/redaction tests and secret scans pass; retention uses QMDB-PAR-022; critical traces correlate without restricted payload. |
| Measurement Source | Central log search, secret scan, schema validation |
| Failure Behavior | Drop/redact prohibited fields and alert on logging pipeline/control failure without exposing content. |
| Security or Privacy Impact | Supports detection while preventing telemetry leakage. |
| Dependencies | QMDB-BL-001; QMDB-FR-SEC-001; QMDB-FR-AUD-001; QMDB-FR-OPS-003 |
| Open Parameter References | QMDB-PAR-022 |
| Related Functional Requirements | QMDB-FR-SEC-001; QMDB-FR-AUD-001; QMDB-FR-OPS-003 |
| Related Use Cases | QMDB-UC-060 |
| Related Business Invariants | INV-027; INV-028 |
| Related Threats | QMDB-THR-037; QMDB-THR-038 |
| Related Controls | QMDB-CTL-020; QMDB-CTL-022 |
| Planned Implementation Phase | P2 — Identity, Security, and Tenant Isolation |
| Verification Method | Log-schema, redaction, access, integrity and secret-scanning tests |
| Required Evidence | Sample schemas, negative fixtures, access grants, retention mapping |
| Acceptance Criteria | No prohibited secret or complete restricted payload appears in production logs or traces. |
| Status | Parameter Pending |

## QMDB-NFR-OBS-002 — Critical metrics, tracing, and SLIs

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-OBS-002 |
| Title | Critical metrics, tracing, and SLIs |
| Quality Attribute | Observability |
| Requirement Statement | The QMDB telemetry boundary shall expose request, database, Redis, outbox, queue, live, scoring, result, certificate, media, authentication, privilege, tenancy, moderation, privacy, backup, restore and audit-integrity metrics plus correlated critical traces without restricted payload leakage. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Operational |
| Applicable System Components | All runtime and operational components |
| Applicable Actors | SRE, Security Operations, service owner |
| Stimulus or Trigger | Request/job execution, threshold breach, incident investigation, or capacity review |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Publish QMDB-SLI-001 through QMDB-SLI-020, propagate trace context across jobs/outbox/media/notifications, apply approved sampling/retention, and provide role-scoped dashboards. |
| Response Measure | Each required SLI has source, owner, dashboard, alert relation and test; trace sampling/retention use QMDB-PAR-032. |
| Measurement Source | Metrics/traces, dashboards, synthetic transactions |
| Failure Behavior | Mark telemetry degraded, use alternate health evidence, and avoid declaring service healthy without required signals. |
| Security or Privacy Impact | Reduces blind operation and shortens diagnosis. |
| Dependencies | QMDB-BL-001; QMDB-FR-OPS-003; QMDB-FR-SEC-001; QMDB-FR-AUD-002 |
| Open Parameter References | QMDB-PAR-032 |
| Related Functional Requirements | QMDB-FR-OPS-003; QMDB-FR-SEC-001; QMDB-FR-AUD-002 |
| Related Use Cases | QMDB-UC-062 |
| Related Business Invariants | INV-028; INV-030 |
| Related Threats | QMDB-THR-039; QMDB-THR-045 |
| Related Controls | QMDB-CTL-020; QMDB-CTL-024 |
| Planned Implementation Phase | P12 — Quality, Accessibility, and Production Hardening |
| Verification Method | Telemetry schema, correlation, dashboard, trace-redaction and synthetic monitoring tests |
| Required Evidence | SLI catalog, dashboard captures, trace exemplars, data-leak test |
| Acceptance Criteria | Every critical flow can be correlated end to end without logging protected payloads. |
| Status | Parameter Pending |

## QMDB-NFR-AUD-001 — Tamper-evident attributable audit

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-AUD-001 |
| Title | Tamper-evident attributable audit |
| Quality Attribute | Audit and Tamper Evidence |
| Requirement Statement | The QMDB audit boundary shall write append-oriented attributable events containing actor/effective role, Workspace/scope, resource, action/reason, before/after state references, request context, hashes, chain relationship and governed external checkpoints for significant allowed and denied actions. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Security Critical |
| Applicable System Components | Audit domain, MySQL, outbox, checkpoint storage, exports |
| Applicable Actors | System, actor, auditor, support and emergency operator |
| Stimulus or Trigger | Privilege, score, result, certificate, Guardian, consent, Qur’an release, support, break-glass, configuration, export, correction, or denial |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Commit audit with the authoritative transition where required, preserve correction history, verify chain/checkpoint, alert failures, restrict/export under approval and watermark/trace export. |
| Response Measure | All significant-action categories produce complete events; chain alteration/rewrite/deletion tests fail verification and alert; retention uses QMDB-PAR-021. |
| Measurement Source | Audit verification service, external checkpoint receipt, export ledger |
| Failure Behavior | Block high-risk transition when mandatory audit cannot be assured; otherwise contain, alert and reconcile under documented policy. |
| Security or Privacy Impact | Provides accountability and evidence against insider or record tampering. |
| Dependencies | QMDB-BL-001; QMDB-FR-AUD-001; QMDB-FR-AUD-002; QMDB-FR-OPS-001; QMDB-FR-OPS-002 |
| Open Parameter References | QMDB-PAR-021 |
| Related Functional Requirements | QMDB-FR-AUD-001; QMDB-FR-AUD-002; QMDB-FR-OPS-001; QMDB-FR-OPS-002 |
| Related Use Cases | QMDB-UC-037; QMDB-UC-043; QMDB-UC-060 |
| Related Business Invariants | INV-020; INV-030 |
| Related Threats | QMDB-THR-017; QMDB-THR-046 |
| Related Controls | QMDB-CTL-014; QMDB-CTL-020 |
| Planned Implementation Phase | P2 — Identity, Security, and Tenant Isolation |
| Verification Method | Transaction, completeness, chain tamper, external checkpoint, scope and export tests |
| Required Evidence | Event samples, chain/checkpoint verification, failure alert, export approval |
| Acceptance Criteria | Deletion or rewriting of audit history is detectable and sensitive operations cannot become unaudited. |
| Status | Parameter Pending |

## QMDB-NFR-OBS-003 — Owned alerting and runbook linkage

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-OBS-003 |
| Title | Owned alerting and runbook linkage |
| Quality Attribute | Alerting |
| Requirement Statement | The QMDB alerting boundary shall classify, route, deduplicate, acknowledge, escalate and close authentication, privilege, tenancy, score, audit, signing, backup, database, replication, queue, live, malware, moderation, child-safety, support, break-glass, secret and verification alerts through owned runbooks. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Operational |
| Applicable System Components | Monitoring, alert manager, dashboards, incident system |
| Applicable Actors | On-call operator, Security Operations, Privacy/Child-Safety roles |
| Stimulus or Trigger | QMDB-ALT-001 through QMDB-ALT-020 trigger condition |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Evaluate approved thresholds, attach safe diagnostic context and QMDB-RUN references, page the accountable role, preserve acknowledgement/escalation evidence, and prevent alert storms. |
| Response Measure | All alert categories have owner, parameter, runbook, synthetic trigger, routing and closure evidence; thresholds use QMDB-PAR-027. |
| Measurement Source | Alert manager, incident ledger, synthetic tests |
| Failure Behavior | Escalate telemetry/route failure through secondary path and display monitoring degradation. |
| Security or Privacy Impact | Prevents missed or unactionable critical conditions. |
| Dependencies | QMDB-BL-001; QMDB-FR-SEC-001; QMDB-FR-OPS-003 |
| Open Parameter References | QMDB-PAR-027; QMDB-PAR-028; QMDB-PAR-029 |
| Related Functional Requirements | QMDB-FR-SEC-001; QMDB-FR-OPS-003 |
| Related Use Cases | QMDB-UC-062 |
| Related Business Invariants | INV-028 |
| Related Threats | QMDB-THR-001; QMDB-THR-017; QMDB-THR-039 |
| Related Controls | QMDB-CTL-020; QMDB-CTL-024 |
| Planned Implementation Phase | P12 — Quality, Accessibility, and Production Hardening |
| Verification Method | Synthetic alert, routing, deduplication, escalation and runbook-link tests |
| Required Evidence | Alert catalog, route evidence, acknowledgement/closure records |
| Acceptance Criteria | Every critical alert reaches an accountable role with a tested runbook and evidence; unknown thresholds are not invented. |
| Status | Parameter Pending |

## QMDB-NFR-INC-001 — Controlled incident lifecycle

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-INC-001 |
| Title | Controlled incident lifecycle |
| Quality Attribute | Incident Response |
| Requirement Statement | The QMDB incident boundary shall govern security, privacy, child-safety and availability incidents through DETECTED, TRIAGED, DECLARED, CONTAINING, ERADICATING, RECOVERING, MONITORING, RESOLVED and POST_INCIDENT_REVIEW states with explicit authority and evidence. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Security Critical |
| Applicable System Components | Incident management, security/privacy/safety operations, recovery |
| Applicable Actors | Reporter, on-call responder, Incident Commander, approver |
| Stimulus or Trigger | Alert, report, suspected compromise, privacy/safety event, or major outage |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Assign severity and commander, preserve evidence, contain, maintain safe continuity, assess communication/regulatory/safety duties, recover and verify, identify root cause/actions, update runbooks, and obtain closure approval. |
| Response Measure | State transitions, role separation, evidence, recovery verification, communications decisions, corrective actions and closure are complete and auditable. |
| Measurement Source | Incident case, audit, recovery evidence |
| Failure Behavior | Keep incident open/restricted and affected capability contained when authority, evidence or verification is incomplete. |
| Security or Privacy Impact | Reduces unmanaged response, evidence loss and premature closure. |
| Dependencies | QMDB-BL-001; QMDB-FR-SEC-001; QMDB-FR-SEC-002 |
| Open Parameter References | QMDB-PAR-027 |
| Related Functional Requirements | QMDB-FR-SEC-001; QMDB-FR-SEC-002 |
| Related Use Cases | QMDB-UC-060 |
| Related Business Invariants | INV-028; INV-030 |
| Related Threats | QMDB-THR-046; QMDB-THR-037 |
| Related Controls | QMDB-CTL-020; QMDB-CTL-026 |
| Planned Implementation Phase | P12 — Quality, Accessibility, and Production Hardening |
| Verification Method | Tabletop, state, authority, evidence-preservation, containment, recovery and closure tests |
| Required Evidence | Incident timeline, evidence manifest, communications assessment, RCA and action tracker |
| Acceptance Criteria | No incident reaches RESOLVED without verified recovery, approvals and recorded corrective actions. |
| Status | Proposed |

## QMDB-NFR-INC-002 — Qualified notification and evidence decisions

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-INC-002 |
| Title | Qualified notification and evidence decisions |
| Quality Attribute | Incident Response |
| Requirement Statement | The QMDB incident-governance boundary shall route potential regulatory, privacy, child-safety, security and user notification decisions to qualified accountable roles without embedding unapproved statutory deadlines or legal conclusions. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | High |
| Criticality | Privacy Critical |
| Applicable System Components | Incident management, notifications, legal/compliance interface, privacy and safety operations |
| Applicable Actors | Incident Commander, Privacy Officer, Child-Safety authority, qualified reviewer |
| Stimulus or Trigger | Incident may require external notification, preservation, disclosure or authority contact |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Capture jurisdiction, subjects, data/classes, harm, timing, containment and evidence; restrict dissemination; obtain qualified decision and communication approval; retain decision history. |
| Response Measure | Every applicable incident includes notification assessment, accountable decision, approved content/audience/channel, timestamp and evidence; timing references approved policy. |
| Measurement Source | Incident ledger and communication records |
| Failure Behavior | Escalate conservatively, preserve evidence and avoid unauthorized disclosure or unsupported legal claim. |
| Security or Privacy Impact | Prevents missed duties, harmful over-disclosure and fabricated compliance. |
| Dependencies | QMDB-BL-001; QMDB-FR-SEC-002; QMDB-FR-NTF-001 |
| Open Parameter References | QMDB-PAR-027 |
| Related Functional Requirements | QMDB-FR-SEC-002; QMDB-FR-NTF-001 |
| Related Use Cases | QMDB-UC-053; QMDB-UC-060 |
| Related Business Invariants | INV-026; INV-028 |
| Related Threats | QMDB-THR-022; QMDB-THR-037 |
| Related Controls | QMDB-CTL-019; QMDB-CTL-020 |
| Planned Implementation Phase | P12 — Quality, Accessibility, and Production Hardening |
| Verification Method | Incident tabletop with privacy, child-safety and communication decision review |
| Required Evidence | Assessment form, qualified approval, message and delivery evidence |
| Acceptance Criteria | Potential notification is neither silently omitted nor sent without scoped qualified authority and minimization. |
| Status | Parameter Pending |

## Change control

Changes require impact review across the [parameter register](../../operations/quality-attribute-parameter-register.md), [threat model](../../security/threat-model.md), [acceptance scenarios](../P0-B03-non-functional-acceptance-scenarios.md), and [traceability matrix](../P0-B03-traceability-matrix.md). Locked ADR-001 through ADR-020 cannot be weakened here.

