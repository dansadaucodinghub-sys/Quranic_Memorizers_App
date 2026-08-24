# Privacy, Data Protection, and Child-Safety Requirements

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B03 |
| Document Title | Privacy, Data Protection, and Child-Safety Requirements |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Privacy and Child-Safety Governance |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed for governance approval; inherited locked decisions remain binding |
| Related Documents | [B03 index](../P0-B03-non-functional-requirements-index.md); [Traceability](../P0-B03-traceability-matrix.md); [Product constitution](../../project/product-constitution.md) |

## Purpose

Define privacy-by-design, personal-data, public-minimization, rights-workflow, guardianship, consent, and child-safety obligations without making legal conclusions.

## Scope

People, Guardians, Minors, profiles, competition records, media, moderation, privacy operations, public projections, processors, exports, and retained evidence.

## Interpretation and control posture

Requirements are implementation-neutral unless QMDB-BL-001 locks a technology. “Approved Baseline” identifies a direct locked-baseline obligation; “Proposed” requires governance approval; “Parameter Pending” is enforceable in structure and measurement but its target remains governed by the parameter register. Derived stores never replace authoritative MySQL records. Business policy and technical enforcement are both identified through traceability; neither creates legal or religious authority.

## Coverage profile

Minimization; purpose limitation; notice and consent versioning; processing and processor inventories; rights workflows; retention and holds; cross-border review; conservative public display; small-group suppression; Guardian authority; consent withdrawal; age transition; reporting, blocking, muting, interaction restrictions, and emergency containment.

## QMDB-NFR-PRI-001 — Privacy by design and processing accountability

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-PRI-001 |
| Title | Privacy by design and processing accountability |
| Quality Attribute | Privacy |
| Requirement Statement | The QMDB processing boundary shall minimize collection, bind data to recorded purposes, version notices and consent, use restricted defaults, maintain processor and data-flow inventories, log access, trigger privacy screening, and escalate suspected privacy incidents. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Privacy Critical |
| Applicable System Components | People, Privacy, all data modules, integrations |
| Applicable Actors | Data subject, Guardian, Privacy Officer, processor manager |
| Stimulus or Trigger | New or changed collection, purpose, processor, flow, public use, automation, or incident |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Require purpose and review metadata before processing, expose applicable notice, restrict unreviewed processing, and preserve accountable evidence without declaring lawful basis. |
| Response Measure | Every processing activity maps to purpose, subject, classes, processor/flow, review and controls; unreviewed high-risk processing remains blocked. |
| Measurement Source | Privacy inventory, PIA screening, access/audit logs |
| Failure Behavior | Do not start or expand processing; restrict exposure and escalate for qualified review. |
| Security or Privacy Impact | Reduces excessive, opaque, incompatible, or unlawful processing risk. |
| Dependencies | QMDB-BL-001; QMDB-FR-PRI-001; QMDB-FR-PRI-003 |
| Open Parameter References | None |
| Related Functional Requirements | QMDB-FR-PRI-001; QMDB-FR-PRI-003 |
| Related Use Cases | QMDB-UC-058 |
| Related Business Invariants | INV-026; INV-028 |
| Related Threats | QMDB-THR-022; QMDB-THR-027 |
| Related Controls | QMDB-CTL-015; QMDB-CTL-020 |
| Planned Implementation Phase | P2 — Identity, Security, and Tenant Isolation |
| Verification Method | Privacy design review; inventory and purpose-control tests |
| Required Evidence | Processing register, notice/consent versions, processor and flow inventory |
| Acceptance Criteria | A new processing purpose cannot silently reuse personal data or become public without recorded review and authority. |
| Status | Proposed |

## QMDB-NFR-PRI-002 — Classified personal-data handling

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-PRI-002 |
| Title | Classified personal-data handling |
| Quality Attribute | Privacy |
| Requirement Statement | The QMDB data-handling boundary shall classify and protect contact, birth/age, affiliation, geography, guardian, competition, score, identity, device, security, media, moderation, request, certificate and audit data according to purpose, sensitivity and approved access. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Privacy Critical |
| Applicable System Components | MySQL, object storage, logs, exports, backups, caches, support |
| Applicable Actors | Data subject, authorized worker, support, privacy and security roles |
| Stimulus or Trigger | Collect, store, transmit, log, export, back up, support-access, or dispose data |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Apply storage, transmission, encryption, logging, display, export, support and incident rules from QMDB-DCL-001 through QMDB-DCL-005; treat voice/facial media as biometric only when legally qualifying processing applies. |
| Response Measure | Data inventories have a classification and handling test; restricted and secret examples never enter public or ordinary logs. |
| Measurement Source | Data catalog, DLP/secret scans, access and export tests |
| Failure Behavior | Block or quarantine unclassified/restricted handling and raise review evidence. |
| Security or Privacy Impact | Reduces disclosure, misclassification and overbroad access. |
| Dependencies | QMDB-BL-001; QMDB-FR-PRI-003; QMDB-FR-RPT-002; QMDB-FR-OPS-001 |
| Open Parameter References | QMDB-PAR-021; QMDB-PAR-022; QMDB-PAR-023 |
| Related Functional Requirements | QMDB-FR-PRI-003; QMDB-FR-RPT-002; QMDB-FR-OPS-001 |
| Related Use Cases | QMDB-UC-057; QMDB-UC-059 |
| Related Business Invariants | INV-026; INV-028 |
| Related Threats | QMDB-THR-021; QMDB-THR-038 |
| Related Controls | QMDB-CTL-015; QMDB-CTL-022 |
| Planned Implementation Phase | P3 — Nigerian Geography, Organizations, and People |
| Verification Method | Data inventory, access, logging, export and backup review |
| Required Evidence | Completed handling matrix, restricted-data tests, secret scan |
| Acceptance Criteria | No restricted or secret-class record is exposed through a public view, ordinary support view, log, or uncontrolled export. |
| Status | Parameter Pending |

## QMDB-NFR-PRI-003 — Public disclosure minimization

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-PRI-003 |
| Title | Public disclosure minimization |
| Quality Attribute | Privacy |
| Requirement Statement | The QMDB public-delivery boundary shall exclude contact, identity, security, guardian, private-media and precise Minor-location data, minimize certificate and result fields by purpose, and suppress or aggregate small-group statistics when re-identification risk is material. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Privacy Critical |
| Applicable System Components | Public profiles, results, certificates, search, reports, CDN |
| Applicable Actors | Public visitor, External Verification Consumer, report viewer |
| Stimulus or Trigger | Public profile, search, result, certificate, media, report, or aggregate request |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Use field allowlists, consent/status checks, privacy-aware projections and small-group controls before release. |
| Response Measure | Public endpoint and projection tests find no prohibited field; approved output schemas and suppression decisions are evidenced. |
| Measurement Source | Contract tests, privacy scans, projection audits |
| Failure Behavior | Return reduced, suppressed, unavailable, or not-found-safe output without confirming private records. |
| Security or Privacy Impact | Prevents stalking, identity theft, child exposure, inference and oversharing. |
| Dependencies | QMDB-BL-001; QMDB-FR-PRI-004; QMDB-FR-SRH-001; QMDB-FR-RPT-001; QMDB-FR-CER-003 |
| Open Parameter References | QMDB-PAR-026 |
| Related Functional Requirements | QMDB-FR-PRI-004; QMDB-FR-SRH-001; QMDB-FR-RPT-001; QMDB-FR-CER-003 |
| Related Use Cases | QMDB-UC-045; QMDB-UC-055 |
| Related Business Invariants | INV-024; INV-026 |
| Related Threats | QMDB-THR-021; QMDB-THR-022 |
| Related Controls | QMDB-CTL-016; QMDB-CTL-015 |
| Planned Implementation Phase | P11 — Search, Analytics, and National Reporting |
| Verification Method | Public-schema, field-leakage, small-group, cache and search tests |
| Required Evidence | Approved allowlists, negative field tests, suppression review |
| Acceptance Criteria | Public outputs reveal only purpose-approved fields and do not expose precise Minor location or private access URLs. |
| Status | Proposed |

## QMDB-NFR-PRI-004 — Controlled privacy-rights and retention workflow

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-PRI-004 |
| Title | Controlled privacy-rights and retention workflow |
| Quality Attribute | Privacy Operations |
| Requirement Statement | The QMDB privacy-operations boundary shall acknowledge, verify, authorize, track, restrict, decide, evidence and securely deliver privacy requests while propagating corrections and visibility changes without silently rewriting official records or defeating holds. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Privacy Critical |
| Applicable System Components | Privacy, People, Records, search/cache projections, exports, Audit |
| Applicable Actors | Data subject, authorized Guardian, Privacy Officer, Records Governance |
| Stimulus or Trigger | Access, correction, export, deletion/anonymization, objection, closure, hold, or disputed identity request |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Create a controlled case, apply qualified deadline and legal-review dependencies, assess official-record/hold conflicts, record reasoned outcome, refresh projections, and deliver securely. |
| Response Measure | Each request has identity/authority, assignment, decision, completion, delivery and audit evidence; deadlines resolve through approved policy rather than invented values. |
| Measurement Source | Privacy case ledger, export delivery evidence, projection reconciliation |
| Failure Behavior | Hold or reject with reasons and appeal/escalation path; never disclose to an unverified requester or hard-delete protected official history. |
| Security or Privacy Impact | Protects rights while preserving integrity, confidentiality and accountable holds. |
| Dependencies | QMDB-BL-001; QMDB-FR-PRI-002; QMDB-FR-PRI-003 |
| Open Parameter References | QMDB-PAR-021; QMDB-PAR-022 |
| Related Functional Requirements | QMDB-FR-PRI-002; QMDB-FR-PRI-003 |
| Related Use Cases | QMDB-UC-058 |
| Related Business Invariants | INV-020; INV-026; INV-030 |
| Related Threats | QMDB-THR-027; QMDB-THR-038 |
| Related Controls | QMDB-CTL-015; QMDB-CTL-019 |
| Planned Implementation Phase | P12 — Quality, Accessibility, and Production Hardening |
| Verification Method | End-to-end privacy, identity, Guardian, export, hold and projection tests; qualified review |
| Required Evidence | Case record, delivery receipt, decision rationale, correction/rebuild evidence |
| Acceptance Criteria | An unverified or unauthorized request reveals nothing, while approved changes propagate without destroying governed official evidence. |
| Status | Parameter Pending |

## QMDB-NFR-CHD-001 — Protective Minor status, Guardian authority, and consent

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-CHD-001 |
| Title | Protective Minor status, Guardian authority, and consent |
| Quality Attribute | Child Safety |
| Requirement Statement | The QMDB child-safety boundary shall apply protective Minor defaults when status is uncertain and require a current scoped Guardian relationship and action-specific consent for competition, recording, profile, media and Recitation Clip actions. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Safety Critical |
| Applicable System Components | People, Guardianship, Competitions, Media, Community |
| Applicable Actors | Minor, Guardian, organizer, publisher |
| Stimulus or Trigger | Minor determination, Guardian link/dispute, consent grant/withdrawal, application, recording, or publication |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Evaluate status, relationship scope, consent purpose/version/state and additional approval; block on uncertainty or dispute and preserve history. |
| Response Measure | Boundary-age, uncertain-status, Guardian scope, dispute, expiry and withdrawal tests cover every consent-gated action. |
| Measurement Source | Guardian/consent ledger, authorization and workflow tests |
| Failure Behavior | Keep the action private/pending or withdraw visibility; do not erase official evidence solely because publication ends. |
| Security or Privacy Impact | Prevents unauthorized participation, recording, publication and Guardian misuse. |
| Dependencies | QMDB-BL-001; QMDB-FR-GUA-001; QMDB-FR-GUA-003; QMDB-FR-GUA-004; QMDB-FR-SOC-002 |
| Open Parameter References | None |
| Related Functional Requirements | QMDB-FR-GUA-001; QMDB-FR-GUA-003; QMDB-FR-GUA-004; QMDB-FR-SOC-002 |
| Related Use Cases | QMDB-UC-014; QMDB-UC-015; QMDB-UC-051 |
| Related Business Invariants | INV-022; INV-024 |
| Related Threats | QMDB-THR-023 |
| Related Controls | QMDB-CTL-016; QMDB-CTL-004 |
| Planned Implementation Phase | P3 — Nigerian Geography, Organizations, and People |
| Verification Method | Child-safety, age-boundary, Guardian, consent and withdrawal tests; qualified policy review |
| Required Evidence | Decision table, relationship and consent history, negative tests |
| Acceptance Criteria | Uncertain Minor or Guardian status and withdrawn/disputed consent block sensitive action and public visibility. |
| Status | Proposed |

## QMDB-NFR-CHD-002 — Safe child publication and interaction

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-CHD-002 |
| Title | Safe child publication and interaction |
| Quality Attribute | Child Safety |
| Requirement Statement | The QMDB community boundary shall prohibit unrestricted direct messaging, unmoderated user livestreaming, public contact and precise location, require reporting on every public post, enforce blocking/muting and comment restrictions, and support immediate scoped safety containment. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Safety Critical |
| Applicable System Components | Recitation Clips, comments, profiles, moderation, notifications |
| Applicable Actors | Minor, Guardian, community member, Moderator |
| Stimulus or Trigger | Publication, comment, contact attempt, report, block/mute, or credible safety event |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Apply age/consent/visibility gates, interaction policies and rate controls; make reporting accessible; restrict content immediately when credible risk warrants; limit moderator access. |
| Response Measure | Minor-publication, interaction, report, block/mute, flooding, containment and moderator-scope tests pass. |
| Measurement Source | Moderation cases, safety audit, public projection tests |
| Failure Behavior | Deny interaction or remove visibility, preserve evidence securely, notify scoped roles, and escalate without exposing the child. |
| Security or Privacy Impact | Reduces grooming, harassment, location/contact exposure, retaliation and unsafe persistence. |
| Dependencies | QMDB-BL-001; QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-FR-MOD-001; QMDB-FR-MOD-002 |
| Open Parameter References | QMDB-PAR-026; QMDB-PAR-027 |
| Related Functional Requirements | QMDB-FR-SOC-002; QMDB-FR-SOC-003; QMDB-FR-MOD-001; QMDB-FR-MOD-002 |
| Related Use Cases | QMDB-UC-052; QMDB-UC-053 |
| Related Business Invariants | INV-024; INV-025 |
| Related Threats | QMDB-THR-024; QMDB-THR-025; QMDB-THR-026 |
| Related Controls | QMDB-CTL-016; QMDB-CTL-017 |
| Planned Implementation Phase | P10 — Recitation Clips and Community Safety |
| Verification Method | Child-safety abuse, authorization, moderation, report-flood and public-disclosure tests |
| Required Evidence | Policy matrix, moderation evidence, public and interaction test report |
| Acceptance Criteria | Restricted Minor content and interactions fail private, are reportable, and can be contained without loss of accountable evidence. |
| Status | Parameter Pending |

## QMDB-NFR-CHD-003 — Age transition and consent withdrawal continuity

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-CHD-003 |
| Title | Age transition and consent withdrawal continuity |
| Quality Attribute | Child Safety |
| Requirement Statement | The QMDB lifecycle boundary shall govern age transition, disputed Guardian relationships, consent withdrawal, publication history, public-cache removal, and retained official evidence as auditable state changes rather than silent deletion or authority inheritance. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | High |
| Criticality | Safety Critical |
| Applicable System Components | People, Guardianship, Privacy, Media, Records, projections |
| Applicable Actors | Data subject, Guardian, Child-Safety and Privacy roles |
| Stimulus or Trigger | Age-band change, majority threshold, relationship dispute/resolution, consent withdrawal, or evidence hold |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Re-evaluate authority and purposes, restrict sensitive actions during dispute, remove affected public visibility and derivatives, purge projections/caches, and preserve lawful official/audit evidence under approved retention decisions. |
| Response Measure | Transition, race, cache purge, history and evidence-preservation tests prove no stale authority or visibility remains. |
| Measurement Source | Lifecycle events, consent history, cache/CDN verification, retention decision |
| Failure Behavior | Apply the more protective state and escalate unresolved authority; never transfer broad Guardian access automatically. |
| Security or Privacy Impact | Prevents stale consent, hidden publication persistence, identity conflict and evidence loss. |
| Dependencies | QMDB-BL-001; QMDB-FR-GUA-003; QMDB-FR-GUA-004; QMDB-FR-MED-004; QMDB-FR-PRI-003 |
| Open Parameter References | QMDB-PAR-023 |
| Related Functional Requirements | QMDB-FR-GUA-003; QMDB-FR-GUA-004; QMDB-FR-MED-004; QMDB-FR-PRI-003 |
| Related Use Cases | QMDB-UC-016 |
| Related Business Invariants | INV-020; INV-022; INV-024 |
| Related Threats | QMDB-THR-023; QMDB-THR-022 |
| Related Controls | QMDB-CTL-016; QMDB-CTL-019 |
| Planned Implementation Phase | P10 — Recitation Clips and Community Safety |
| Verification Method | Lifecycle, concurrency, consent-withdrawal, cache purge and retention tests |
| Required Evidence | State-transition audit, projection purge evidence, retained-record rationale |
| Acceptance Criteria | Withdrawal or dispute removes public/sensitive authority promptly while preserving only governed official evidence. |
| Status | Parameter Pending |

## Change control

Changes require impact review across the [parameter register](../../operations/quality-attribute-parameter-register.md), [threat model](../../security/threat-model.md), [acceptance scenarios](../P0-B03-non-functional-acceptance-scenarios.md), and [traceability matrix](../P0-B03-traceability-matrix.md). Locked ADR-001 through ADR-020 cannot be weakened here.
