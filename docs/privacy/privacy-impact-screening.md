# Privacy-Impact Screening

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B03 |
| Document Title | Privacy-Impact Screening |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Privacy and Child-Safety Governance |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed for governance approval; no open value is approved by this document |
| Related Documents | [Data classification](data-classification-and-handling.md); [Privacy NFRs](../requirements/non-functional/02-privacy-data-protection-and-child-safety.md) |

## Purpose

Screen planned processing for privacy and child-safety risk before implementation or material change.

## Scope and limitations

This is not a lawful-basis decision or legal opinion. It identifies data subjects/categories, public/private use, harm, minimization, access, retention, transfer and review dependencies. Voice/facial recordings are not automatically labelled biometric identifiers; qualifying purpose and processing require legal/privacy assessment.

## Screening register

| Activity ID | Purpose | Data Subjects | Data Categories | Public or Private | Primary System Module | Potential Harm | Minimization | Access Controls | Consent or Lawful-Basis Review Dependency | Retention Decision Dependency | Cross-Border Review | Child-Safety Impact | Required Privacy Review | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| PIA-001 | Create and secure an individual identity | Applicants, users, Minors | Identity, contact, age, device, security | Private | Identity and Access | Account takeover, overcollection, child exposure | Collect minimum; avoid identity evidence until approved | Subject and scoped operations | Required | Required | Possible | High for Minors | Required before P2 | Open |
| PIA-002 | Establish scoped authority and consent | Minors, Guardians | Relationship, evidence, consent history | Private | Guardianship | False Guardian, coercion, stale consent | Pending status and purpose-specific consent | Restricted roles and subject views | Required | Required | Possible | Critical | Required before P3 | Open |
| PIA-003 | Register, judge and retain official competition records | Competitors, Judges, Guardians | Profile, affiliation, scores, results, appeals | Mixed | Competitions and Scoring | Unfairness, exposure, immutable-history conflict | Participant Snapshot and field minimization | Assignment/role/workspace scope | Required | Required | Possible | High | Required before P5 | Open |
| PIA-004 | Issue proof and allow minimal verification | Recipients, public verifiers | Identity snapshot, result, signature/status | Mixed | Certificates | Forgery, over-disclosure, permanent linkage | Minimal verification allowlist | Certificate roles and public read-only endpoint | Required | Required | Possible | Medium | Required before P8 | Open |
| PIA-005 | Capture evidence and publish approved derivatives | Competitors, reciters, Minors, Guardians | Audio, video, facial/voice recording, metadata | Mixed | Media | Surveillance, unwanted publication, Minor harm | Quarantine, strip metadata, separate evidence/derivative | Purpose, assignment, consent and status | Required | Required | Likely provider review | Critical | Required before P9 | Open |
| PIA-006 | Enable governed Clips/interactions and safety response | Reciters, commenters, reporters, Minors | Content, interaction, reports, moderation evidence | Mixed | Community and Moderation | Harassment, profiling, retaliation, child targeting | No DMs/livestream; limit fields and engagement data | Visibility, block, moderation and case scopes | Required | Required | Provider review | Critical | Required before P10 | Open |
| PIA-007 | Provide discovery and aggregate oversight | Competitors, staff, organizations | Public fields, operational aggregates, search terms | Mixed | Search and Reporting | Re-identification, scope leakage, small groups | Allowlist, aggregation, suppression, no opaque engagement ranking | Public/scope-safe queries | Required | Required | Possible | High | Required before P11 | Open |
| PIA-008 | Resolve subject requests and constrained support | Data subjects, Guardians, users | Identity verification, requested data, case evidence | Private | Privacy and Support | Wrong-subject disclosure, insider browsing | Case-specific collection and secure delivery | Restricted assignment/JIT support | Required | Required | Possible | Critical | Required before P12 | Open |
| PIA-009 | Protect and reconstruct platform evidence | All users/actors | Security events, audit, backups, network/device context | Private | Audit and Operations | Broad surveillance, backup persistence, insider access | Redact, restrict, separate custody, purpose-bound telemetry | Security/audit/backup roles | Required | Required | DR/provider review | High | Required before P12 | Open |
| PIA-010 | Potential future assistance or analysis | Reciters, viewers, Minors | Behavior, content, voice/audio features, derived scores | Undecided | Deferred capability | Profiling, bias, biometric/automated-decision implications | No autonomous official judging; do not implement without new PIA | Not authorized | Required | Required | Required | Critical | Before any design or procurement | Deferred |

## Cross-cutting findings

Children, public publication, audio/video, historical official records, certificates, moderation, external processors, cross-border flows, subject rights, consent, security telemetry, small-group statistics and future automated analysis require continuing review. Residual risks include re-identification, stale public caches, false Guardian authority, insider access, immutable-record conflicts and provider jurisdiction.

## Trigger rule

A new purpose, data class, processor/region, public field, Minor workflow, automated recommendation/analysis, retention rule, breach pattern or material architecture change reopens screening and may require a full DPIA/qualified Nigerian review.

