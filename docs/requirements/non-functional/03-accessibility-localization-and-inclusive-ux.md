# Accessibility, Localization, and Inclusive UX Requirements

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B03 |
| Document Title | Accessibility, Localization, and Inclusive UX Requirements |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Accessibility and Localization Governance |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed for governance approval; inherited locked decisions remain binding |
| Related Documents | [B03 index](../P0-B03-non-functional-requirements-index.md); [Traceability](../P0-B03-traceability-matrix.md); [Product constitution](../../project/product-constitution.md) |

## Purpose

Define WCAG 2.2 Level AA, critical-transaction, Arabic/RTL, localization, mobile, low-bandwidth, and recoverable-interaction obligations.

## Scope

Every public, authenticated, judging, administrative, privacy, safety, support, verification, media, printable, and live interface.

## Interpretation and control posture

Requirements are implementation-neutral unless QMDB-BL-001 locks a technology. “Approved Baseline” identifies a direct locked-baseline obligation; “Proposed” requires governance approval; “Parameter Pending” is enforceable in structure and measurement but its target remains governed by the parameter register. Derived stores never replace authoritative MySQL records. Business policy and technical enforcement are both identified through traceability; neither creates legal or religious authority.

## Coverage profile

Keyboard and focus; semantics and errors; screen readers and live regions; contrast and non-color status; zoom, reflow, spacing, touch targets, reduced motion and time warnings; accessible authentication, media, tables, PDFs and live scores; critical-action review and receipts; Arabic/RTL and bidirectional isolation; localization; progressive enhancement and draft recovery.

## QMDB-NFR-ACC-001 — WCAG 2.2 Level AA interaction baseline

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-ACC-001 |
| Title | WCAG 2.2 Level AA interaction baseline |
| Quality Attribute | Accessibility |
| Requirement Statement | The QMDB presentation boundary shall target WCAG 2.2 Level AA with keyboard operation, visible unobscured focus, logical order/restoration, skip navigation, semantic structure, labels/instructions, error summaries, accessible dialogs/tables/live regions, contrast, non-color status, zoom, reflow, text spacing, usable targets, reduced motion and accessible time warnings. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Business Critical |
| Applicable System Components | All web presentation and generated user artifacts |
| Applicable Actors | Every user, including assistive-technology users |
| Stimulus or Trigger | Navigate, enter data, receive status/error, zoom/reflow, use reduced motion, or encounter time limit |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Expose equivalent semantic and keyboard behavior, announce meaningful changes without interruption, preserve focus, and warn/extend time where policy permits. |
| Response Measure | Automated and manual WCAG matrix checks pass for all critical workflows; automated scanning is never sole conformance evidence. |
| Measurement Source | Accessibility automation plus manual keyboard, screen-reader and visual review |
| Failure Behavior | Preserve data and provide a clear recoverable path; do not trap focus or require pointer/color/motion. |
| Security or Privacy Impact | Prevents exclusion and critical-action error. |
| Dependencies | QMDB-BL-001; QMDB-FR-UXA-001; QMDB-FR-UXA-002 |
| Open Parameter References | None |
| Related Functional Requirements | QMDB-FR-UXA-001; QMDB-FR-UXA-002 |
| Related Use Cases | QMDB-UC-024; QMDB-UC-035; QMDB-UC-058 |
| Related Business Invariants | INV-029 |
| Related Threats | QMDB-THR-046 |
| Related Controls | QMDB-CTL-025 |
| Planned Implementation Phase | P12 — Quality, Accessibility, and Production Hardening |
| Verification Method | Automated accessibility checks; manual keyboard, screen-reader, zoom, reflow and reduced-motion review |
| Required Evidence | Accessibility matrix, issue log, manual test recordings/notes |
| Acceptance Criteria | A keyboard and screen-reader user completes every critical path with understandable status, errors, focus and recovery. |
| Status | Approved Baseline |

## QMDB-NFR-ACC-002 — Accessible critical-action safeguards

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-ACC-002 |
| Title | Accessible critical-action safeguards |
| Quality Attribute | Accessibility |
| Requirement Statement | The QMDB critical-transaction boundary shall give score, finalization, certificate, consent, privacy, break-glass and organization-verification actions a clear name, consequence, review step, error prevention, confirmation, receipt and recoverable error path without color- or pointer-only dependence. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Competition Critical |
| Applicable System Components | Judging, Results, Certificates, Guardianship, Privacy, Operations, Organizations |
| Applicable Actors | Judge, approver, Guardian, Privacy Officer, emergency operator |
| Stimulus or Trigger | Initiate or confirm a sensitive or irreversible-seeming action |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Present authoritative values and scope for review, prevent/identify error, require explicit confirmation, preserve draft when safe, and provide accessible receipt. |
| Response Measure | Every listed action passes keyboard, screen-reader, mobile, error-injection and receipt evidence checks. |
| Measurement Source | End-to-end accessibility tests and transaction audit |
| Failure Behavior | Do not submit ambiguous data; preserve recoverable state and identify the exact correction path. |
| Security or Privacy Impact | Reduces inaccessible consent, score, authority and record errors. |
| Dependencies | QMDB-BL-001; QMDB-FR-SCR-003; QMDB-FR-RSL-003; QMDB-FR-CER-004; QMDB-FR-OPS-002 |
| Open Parameter References | None |
| Related Functional Requirements | QMDB-FR-SCR-003; QMDB-FR-RSL-003; QMDB-FR-CER-004; QMDB-FR-OPS-002 |
| Related Use Cases | QMDB-UC-036; QMDB-UC-042; QMDB-UC-046; QMDB-UC-060 |
| Related Business Invariants | INV-015; INV-029 |
| Related Threats | QMDB-THR-010; QMDB-THR-046 |
| Related Controls | QMDB-CTL-025; QMDB-CTL-004 |
| Planned Implementation Phase | P12 — Quality, Accessibility, and Production Hardening |
| Verification Method | Manual assistive-technology and end-to-end negative-error tests |
| Required Evidence | Review/confirmation/receipt captures and audit correlation |
| Acceptance Criteria | A critical action cannot depend on pointer or color and cannot complete without accessible review, confirmation and receipt. |
| Status | Proposed |

## QMDB-NFR-ACC-003 — Accessible authentication, media, documents, and live data

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-ACC-003 |
| Title | Accessible authentication, media, documents, and live data |
| Quality Attribute | Accessibility |
| Requirement Statement | The QMDB content-delivery boundary shall provide accessible authentication and challenge alternatives, captions or equivalents where applicable, operable audio/video controls, accessible PDF certificates and verification, structured scoreboards, and polite user-controlled live updates. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | High |
| Criticality | Business Critical |
| Applicable System Components | Identity UI, media player, certificates, live scoreboard, reports |
| Applicable Actors | Public visitor, account holder, competitor, Judge |
| Stimulus or Trigger | Authenticate, play media, read document/table, verify certificate, or receive live updates |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Avoid cognitive-test-only authentication, expose names/roles/states, permit playback control, tag generated documents, and announce live changes at user-manageable cadence. |
| Response Measure | Manual screen-reader, keyboard and document checks validate each content type and workflow. |
| Measurement Source | Assistive-technology testing, PDF inspection, live-region event logs |
| Failure Behavior | Offer equivalent accessible content or controlled fallback; never hide critical state in inaccessible media. |
| Security or Privacy Impact | Prevents content and transaction exclusion. |
| Dependencies | QMDB-BL-001; QMDB-FR-IAM-003; QMDB-FR-LIV-001; QMDB-FR-CER-003; QMDB-FR-MED-003 |
| Open Parameter References | QMDB-PAR-007 |
| Related Functional Requirements | QMDB-FR-IAM-003; QMDB-FR-LIV-001; QMDB-FR-CER-003; QMDB-FR-MED-003 |
| Related Use Cases | QMDB-UC-003; QMDB-UC-045; QMDB-UC-062 |
| Related Business Invariants | INV-029 |
| Related Threats | QMDB-THR-046 |
| Related Controls | QMDB-CTL-025 |
| Planned Implementation Phase | P12 — Quality, Accessibility, and Production Hardening |
| Verification Method | Screen-reader/keyboard media, PDF, authentication and live-region tests |
| Required Evidence | Tagged-PDF report, AT compatibility notes, event announcement evidence |
| Acceptance Criteria | Authentication, verification, media and live status remain usable without sight, sound, pointer precision or rapid announcements. |
| Status | Parameter Pending |

## QMDB-NFR-L10-001 — Arabic and RTL integrity

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-L10-001 |
| Title | Arabic and RTL integrity |
| Quality Attribute | Localization and RTL |
| Requirement Statement | The QMDB presentation boundary shall support full RTL layout, correct Arabic-script shaping, context-appropriate mirroring, bidirectional isolation for mixed text, logical reading/focus order, Arabic names and references, and non-corrupt copy, search and print behavior while preserving canonical Qur’an text. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Business Critical |
| Applicable System Components | All presentation, search, certificate and print surfaces |
| Applicable Actors | Arabic-language user, screen-reader user, data reviewer |
| Stimulus or Trigger | Select Arabic/RTL, display mixed content, navigate, copy, search, score, or print |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Apply semantic direction at container/field level, isolate embedded direction, review directional icons, retain canonical stored text, and format tables, dates, numerals and Qur’an/Riwāyah/Ayah references correctly. |
| Response Measure | Manual RTL and Arabic review passes for navigation, forms, breadcrumbs, score tables, live boards, search, copy/paste, screen readers and certificates. |
| Measurement Source | Visual regression, DOM/direction inspection, linguistic review |
| Failure Behavior | Fall back without changing canonical data; flag missing/unsafe translation or layout rather than misrepresenting content. |
| Security or Privacy Impact | Prevents misleading scores, names, references and inaccessible order. |
| Dependencies | QMDB-BL-001; QMDB-FR-UXA-001; QMDB-FR-QRF-001; QMDB-FR-SRH-001 |
| Open Parameter References | None |
| Related Functional Requirements | QMDB-FR-UXA-001; QMDB-FR-QRF-001; QMDB-FR-SRH-001 |
| Related Use Cases | QMDB-UC-017; QMDB-UC-055 |
| Related Business Invariants | INV-013; INV-029 |
| Related Threats | QMDB-THR-018; QMDB-THR-046 |
| Related Controls | QMDB-CTL-025; QMDB-CTL-014 |
| Planned Implementation Phase | P12 — Quality, Accessibility, and Production Hardening |
| Verification Method | Manual RTL, Arabic linguistic, screen-reader, copy/paste, search and print tests |
| Required Evidence | Reviewed screenshots, DOM order, Arabic terminology approval, canonical hash comparison |
| Acceptance Criteria | RTL changes visual arrangement without changing logical order, canonical Qur’an content, score meaning, or copied value. |
| Status | Approved Baseline |

## QMDB-NFR-L10-002 — Governed localization and time display

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-L10-002 |
| Title | Governed localization and time display |
| Quality Attribute | Localization |
| Requirement Statement | The QMDB localization boundary shall use translation keys, plural rules, locale-aware formatting, detectable fallbacks, UTC storage with user- and competition-local displays, mixed-language search, and qualified review of Arabic theological and Qur’anic terminology. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | High |
| Criticality | Supporting |
| Applicable System Components | Presentation, localization, search, time services, content administration |
| Applicable Actors | User, translator, domain reviewer, competition operator |
| Stimulus or Trigger | Render interface/content, display time, search mixed language, or encounter missing translation |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Resolve versioned translations and locale; display source/fallback clearly when needed; preserve UTC and show time-zone context at boundaries; route sensitive terminology for review. |
| Response Measure | Static checks find no hard-coded interface strings in implementation; locale/time/search tests and terminology approvals exist. |
| Measurement Source | Localization build checks, translation reports, time-zone tests |
| Failure Behavior | Use approved fallback and explicit time zone; never invent a translation or alter source Qur’an data. |
| Security or Privacy Impact | Prevents mistranslation, deadline ambiguity and content corruption. |
| Dependencies | QMDB-BL-001; QMDB-FR-UXA-001; QMDB-FR-UXA-002; QMDB-FR-SRH-001 |
| Open Parameter References | None |
| Related Functional Requirements | QMDB-FR-UXA-001; QMDB-FR-UXA-002; QMDB-FR-SRH-001 |
| Related Use Cases | QMDB-UC-024; QMDB-UC-055 |
| Related Business Invariants | INV-013; INV-029 |
| Related Threats | QMDB-THR-018 |
| Related Controls | QMDB-CTL-025 |
| Planned Implementation Phase | P12 — Quality, Accessibility, and Production Hardening |
| Verification Method | Localization key, fallback, plural, timezone, mixed-search and manual terminology review |
| Required Evidence | Translation coverage report, timezone boundary tests, reviewer record |
| Acceptance Criteria | Missing translation or time-zone context is visible and safe, and no hard-coded string blocks localization. |
| Status | Proposed |

## QMDB-NFR-UXR-001 — Low-bandwidth progressive and recoverable UX

| Field | Value |
| --- | --- |
| Requirement ID | QMDB-NFR-UXR-001 |
| Title | Low-bandwidth progressive and recoverable UX |
| Quality Attribute | Usability and Recoverable Interaction |
| Requirement Statement | The QMDB experience boundary shall keep critical workflows mobile-first and server-rendered, progressively enhance optional behavior, minimize and defer media, expose connection state, preserve drafts safely, paginate data, support low-data preference, and retry only through idempotent recoverable interaction. |
| Rationale | Protects the stated quality attribute across the affected boundary. |
| Priority | Critical |
| Criticality | Competition Critical |
| Applicable System Components | Presentation, judging, registration, live delivery, media, APIs |
| Applicable Actors | Mobile/low-bandwidth user, Judge, venue operator |
| Stimulus or Trigger | Slow, intermittent, low-data, script-limited, or reconnecting session |
| Operating Environment | Normal, peak, degraded, recovery, and hostile operating conditions |
| Required System Response | Deliver minimal critical assets, require explicit playback, preserve acknowledged/draft state, label pending versus committed work, reconnect from sequence, and reduce non-critical features first. |
| Response Measure | Mobile, throttled-network, script-disabled baseline, retry, reconnect and draft-recovery tests pass for critical workflows. |
| Measurement Source | Browser/network test traces, payload reports, draft/receipt audit |
| Failure Behavior | Retain safe local draft where approved, avoid duplicate action, and present a clear retry or alternate path. |
| Security or Privacy Impact | Prevents lost scores/applications, duplicate submission and exclusion on constrained devices. |
| Dependencies | QMDB-BL-001; QMDB-FR-UXA-002; QMDB-FR-SCR-002; QMDB-FR-LIV-002; QMDB-FR-OFF-001 |
| Open Parameter References | QMDB-PAR-003; QMDB-PAR-007; QMDB-PAR-030 |
| Related Functional Requirements | QMDB-FR-UXA-002; QMDB-FR-SCR-002; QMDB-FR-LIV-002; QMDB-FR-OFF-001 |
| Related Use Cases | QMDB-UC-024; QMDB-UC-035; QMDB-UC-061 |
| Related Business Invariants | INV-015; INV-021; INV-029 |
| Related Threats | QMDB-THR-042; QMDB-THR-045 |
| Related Controls | QMDB-CTL-018; QMDB-CTL-025 |
| Planned Implementation Phase | P12 — Quality, Accessibility, and Production Hardening |
| Verification Method | Mobile, throttled-network, progressive-enhancement, idempotency and accessibility tests |
| Required Evidence | Payload budget report, network traces, retry/draft/reconnect evidence |
| Acceptance Criteria | Loss or reconnection never silently discards an acknowledged critical action or duplicates an official record. |
| Status | Parameter Pending |

## Change control

Changes require impact review across the [parameter register](../../operations/quality-attribute-parameter-register.md), [threat model](../../security/threat-model.md), [acceptance scenarios](../P0-B03-non-functional-acceptance-scenarios.md), and [traceability matrix](../P0-B03-traceability-matrix.md). Locked ADR-001 through ADR-020 cannot be weakened here.

