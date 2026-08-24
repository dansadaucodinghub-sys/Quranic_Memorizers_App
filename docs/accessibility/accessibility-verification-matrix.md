# Accessibility Verification Matrix

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B03 |
| Document Title | Accessibility Verification Matrix |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Accessibility Governance and Engineering Quality |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed for governance approval; no open value is approved by this document |
| Related Documents | [Accessibility NFRs](../requirements/non-functional/03-accessibility-localization-and-inclusive-ux.md); [Acceptance scenarios](../requirements/P0-B03-non-functional-acceptance-scenarios.md) |

## Purpose

Define automated and human verification for critical interfaces. Automated scanning is necessary but never sufficient proof of WCAG 2.2 Level AA conformance.

## Scope and execution rule

Each workflow must be tested in representative LTR and RTL locales, keyboard-only, mobile/reflow, reduced motion, high contrast/non-color, throttled network and supported assistive-technology combinations appropriate to the interface. Results are release evidence, not a permanent conformance guarantee.

| Requirement ID | Interface or Workflow | Automated Check | Manual Check | Assistive Technology | RTL Check | Mobile Check | Low-Bandwidth Check | Planned Phase | Release Gate | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-NFR-ACC-001; QMDB-NFR-ACC-002; QMDB-NFR-L10-001; QMDB-NFR-UXR-001 | Registration | Automated semantics/forms | Keyboard, errors, zoom/reflow | Screen reader | Yes | Yes | Yes | P5 | Required for affected release | Planned |
| QMDB-NFR-ACC-001; QMDB-NFR-ACC-002; QMDB-NFR-L10-001; QMDB-NFR-UXR-001 | Login | Automated name/role/contrast | Keyboard, focus, accessible authentication | Screen reader/password manager | Yes | Yes | Yes | P2 | Required for affected release | Planned |
| QMDB-NFR-ACC-001; QMDB-NFR-ACC-002; QMDB-NFR-L10-001; QMDB-NFR-UXR-001 | MFA | Automated forms/time warnings | Keyboard, non-cognitive alternatives, recovery | Screen reader | Yes | Yes | Yes | P2 | Required for affected release | Planned |
| QMDB-NFR-ACC-001; QMDB-NFR-ACC-002; QMDB-NFR-L10-001; QMDB-NFR-UXR-001 | Password recovery | Automated forms/errors | Keyboard, non-enumerating usable recovery | Screen reader | Yes | Yes | Yes | P2 | Required for affected release | Planned |
| QMDB-NFR-ACC-001; QMDB-NFR-ACC-002; QMDB-NFR-L10-001; QMDB-NFR-UXR-001 | Profile | Automated headings/forms | Keyboard, errors, field privacy | Screen reader | Yes | Yes | Yes | P3 | Required for affected release | Planned |
| QMDB-NFR-ACC-001; QMDB-NFR-ACC-002; QMDB-NFR-L10-001; QMDB-NFR-UXR-001 | Guardian consent | Automated forms/dialogs | Consequence, review, confirmation, receipt | Screen reader | Yes | Yes | Yes | P3 | Required for affected release | Planned |
| QMDB-NFR-ACC-001; QMDB-NFR-ACC-002; QMDB-NFR-L10-001; QMDB-NFR-UXR-001 | Competition application | Automated forms/steps | Keyboard, draft recovery, error summary | Screen reader | Yes | Yes | Yes | P5 | Required for affected release | Planned |
| QMDB-NFR-ACC-001; QMDB-NFR-ACC-002; QMDB-NFR-L10-001; QMDB-NFR-UXR-001 | Check-in | Automated controls/status | Keyboard, touch, offline/error state | Screen reader | Yes | Yes | Yes | P5 | Required for affected release | Planned |
| QMDB-NFR-ACC-001; QMDB-NFR-ACC-002; QMDB-NFR-L10-001; QMDB-NFR-UXR-001 | Judge scoring | Automated table/form/live status | Keyboard-only, zoom/reflow, draft/receipt | Screen reader | Yes | Yes | Yes | P6 | Required for affected release | Planned |
| QMDB-NFR-ACC-001; QMDB-NFR-ACC-002; QMDB-NFR-L10-001; QMDB-NFR-UXR-001 | Score review | Automated table/dialog | Consequence, error prevention, focus | Screen reader | Yes | Yes | Yes | P6 | Required for affected release | Planned |
| QMDB-NFR-ACC-001; QMDB-NFR-ACC-002; QMDB-NFR-L10-001; QMDB-NFR-UXR-001 | Live scoreboard | Automated table/live region | Announcement cadence, pause, non-color | Screen reader | Yes | Yes | Yes | P7 | Required for affected release | Planned |
| QMDB-NFR-ACC-001; QMDB-NFR-ACC-002; QMDB-NFR-L10-001; QMDB-NFR-UXR-001 | Results | Automated table/status | Keyboard, non-color, provisional labels | Screen reader | Yes | Yes | Yes | P7 | Required for affected release | Planned |
| QMDB-NFR-ACC-001; QMDB-NFR-ACC-002; QMDB-NFR-L10-001; QMDB-NFR-UXR-001 | Appeals | Automated forms/steps | Keyboard, deadline/timezone, receipt | Screen reader | Yes | Yes | Yes | P7 | Required for affected release | Planned |
| QMDB-NFR-ACC-001; QMDB-NFR-ACC-002; QMDB-NFR-L10-001; QMDB-NFR-UXR-001 | Certificate verification | Automated headings/status | Keyboard, minimal output, print/PDF | Screen reader/PDF reader | Yes | Yes | Yes | P8 | Required for affected release | Planned |
| QMDB-NFR-ACC-001; QMDB-NFR-ACC-002; QMDB-NFR-L10-001; QMDB-NFR-UXR-001 | Media player | Automated accessible-name checks | Keyboard controls, captions/equivalent, no autoplay | Screen reader | Yes | Yes | Yes | P9 | Required for affected release | Planned |
| QMDB-NFR-ACC-001; QMDB-NFR-ACC-002; QMDB-NFR-L10-001; QMDB-NFR-UXR-001 | Recitation Clips | Automated controls/status | Keyboard, consent/visibility, report path | Screen reader | Yes | Yes | Yes | P10 | Required for affected release | Planned |
| QMDB-NFR-ACC-001; QMDB-NFR-ACC-002; QMDB-NFR-L10-001; QMDB-NFR-UXR-001 | Comment moderation | Automated tables/dialogs | Keyboard, safety actions, evidence review | Screen reader | Yes | Yes | Yes | P10 | Required for affected release | Planned |
| QMDB-NFR-ACC-001; QMDB-NFR-ACC-002; QMDB-NFR-L10-001; QMDB-NFR-UXR-001 | Reporting | Automated tables/charts | Keyboard, text alternatives, large report job | Screen reader | Yes | Yes | Yes | P11 | Required for affected release | Planned |
| QMDB-NFR-ACC-001; QMDB-NFR-ACC-002; QMDB-NFR-L10-001; QMDB-NFR-UXR-001 | Privacy request | Automated forms/errors | Keyboard, identity, secure delivery, receipt | Screen reader | Yes | Yes | Yes | P12 | Required for affected release | Planned |
| QMDB-NFR-ACC-001; QMDB-NFR-ACC-002; QMDB-NFR-L10-001; QMDB-NFR-UXR-001 | Support operations | Automated tables/dialogs | Keyboard, scope and expiry confirmation | Screen reader | Yes | Yes | Yes | P12 | Required for affected release | Planned |
| QMDB-NFR-ACC-001; QMDB-NFR-ACC-002; QMDB-NFR-L10-001; QMDB-NFR-UXR-001 | Break-glass confirmation | Automated dialog/status | Keyboard, consequence, review, receipt | Screen reader | Yes | Yes | Yes | P12 | Required for affected release | Planned |

## Evidence and authority

Record browser/AT versions, locale, viewport, network profile, tester role, steps, results, defects, remediation and retest. Accessibility conformance review authority remains an open governance decision.

