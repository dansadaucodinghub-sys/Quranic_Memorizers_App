# Product Constitution

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Document version | 1.0.0 |
| Status | Authoritative QMDB-P0-B01 baseline |
| Current phase | P0 — Product Constitution and System Requirements |
| Last updated | 2026-08-24 |
| Document owner role | Product and Domain Governance |
| Approval status | Formal organizational approval pending; no personal approver designated |

## Purpose

This constitution fixes the product intent and the principles that all later requirements, data models, policies, application modules, interfaces, tests, and operations must honor.

## Scope

This document governs product purpose, initial ecosystem scope, non-goals, success characteristics, and governance expectations. Technical trust boundaries are defined in [System Boundaries](system-boundaries.md); canonical definitions are in the [Domain Glossary](../domain/domain-glossary.md); unresolved matters are in [Open Decisions](open-decisions.md).

## Product vision

Qur’an Memorizer DB will be a nationally scalable, trusted, respectful, and accessible ecosystem through which Qur’an competition authorities, organizations, families, judges, memorizers, and the public can organize competitions, preserve evidence, verify achievements, and share moderated recitation content without compromising official-record integrity, tenant separation, privacy, or child safety.

The long-term product is a durable record of people’s contextual participation and achievement, not a collection of isolated competition databases. It supports Nigeria’s administrative hierarchy and diverse organizations while retaining a unified governance model, a consistent vocabulary, and auditable provenance.

## Mission

Qur’an Memorizer DB shall provide a governed source of truth for safe participation, human-authoritative judging, exact and auditable results, durable Qur’anic achievement records, verifiable certificates, and responsibly moderated recitation media.

## Problem statement

The platform addresses a connected set of operational and trust failures:

- Competition registrations and historical records are fragmented across organizations, files, and formats, making continuity and reconciliation difficult.
- Score organization is inconsistent, and the distinction between a judge submission, aggregate, provisional result, and final result is often unclear.
- Past results may be difficult to verify because authority, evidence, provenance, and correction history are not consistently preserved.
- Audio and video evidence may be lost, exposed inappropriately, or detached from the performance and consent context that gives it meaning.
- Certificates may be forged or impossible to verify without exposing sensitive holder information.
- National, State, Federal Capital Territory, Local Government Area, Area Council, school, mosque, and organization activity lacks a common model that preserves distinct ownership and authority.
- A Memorizer may lack a durable, evidence-based achievement history across multiple Competition Editions.
- Public audiences may receive late, ambiguous, or unauthoritative competition updates.
- Recitation videos may be published without structured consent, privacy classification, moderation, provenance, or minor-protection controls.
- Moderation, guardian controls, privacy governance, security evidence, and audit history are frequently fragmented rather than applied across the complete lifecycle.

## Strategic objectives

Success measures will be approved through governance rather than invented here. The platform nevertheless has objectively assessable objectives:

1. Maintain a durable Person identity that can participate across organizations and editions without unnecessary duplicate profiles.
2. Represent each Competition Edition, its rules, participants, judging, media evidence, results, appeals, corrections, and certificates as traceable records with explicit authority.
3. Preserve submitted and finalized official records through versioning, provenance, controlled corrections, and normal-workflow protection from hard deletion.
4. Make certificate and public-record verification possible without treating public identifiers as authorization or disclosing unnecessary personal data.
5. Apply tenant, geography, organization, competition, and time scopes consistently to every privileged capability.
6. Apply consent, guardian, privacy, and moderation controls before restricted or minor-related media becomes public.
7. Support critical competition workflows on mobile devices and constrained networks while meeting the WCAG 2.2 Level AA target and full LTR/RTL needs.
8. Isolate scoring and competition operations from social, recommendation, analytics, and media-processing workloads.
9. Produce auditable evidence that governance owners can use to assess integrity, security, privacy, operational recovery, and policy compliance.

## Product principles

| Principle | Constitutional meaning |
| --- | --- |
| Trust before engagement | Integrity, provenance, and safe defaults take precedence over growth metrics or social interaction. |
| Competition integrity before social growth | Community features cannot change, delay, or exhaust official competition operations. |
| Privacy by design | Data is minimized, purpose-bound, access-controlled, and retained according to approved policy from the start. |
| Child safety by design | Minor visibility, contact, precise location, recordings, and participation use conservative defaults and Guardian controls. |
| Accessibility by default | Critical and public workflows target WCAG 2.2 Level AA and remain usable with keyboard, assistive technology, high contrast, reduced motion, and adequate touch targets. |
| Arabic and RTL support | Arabic-script readability and full RTL layouts are product foundations, not after-market translations. |
| Historical accuracy | Current Profile changes cannot silently alter Participant Snapshots or official history. |
| Transparent corrections | Reopening, appeal, correction, revocation, and supersession retain prior versions, reason, actors, approvals, evidence, and time. |
| Least privilege | Authority is denied by default and limited by Role, Permission, workspace, organization, geography, competition, assignment, resource, risk, and time. |
| Tenant isolation | Cross-workspace access is denied unless a separately authorized, policy-controlled oversight capability applies. |
| Evidence-based records | Important claims identify provenance, verification scope, method, authority, date, status, and supporting evidence. |
| Human-authoritative judging | Qualified human judges remain authoritative; automated analysis is advisory unless later formally approved policy changes that boundary. |
| Low-bandwidth usability | Critical flows minimize transfers, tolerate intermittent connectivity safely, and make synchronization state visible where offline behavior is later approved. |
| National scalability | The architecture and domain model support multiple administrative and organizational levels without collapsing them into fixed State/LGA fields. |
| Respectful Qur’anic presentation | Text, audio, video, labels, moderation, and interaction patterns must reflect dignity, accuracy, and qualified Qur’an governance. |

## Product non-goals

The initial platform does not include:

- unrestricted direct messaging;
- unmoderated user livestreaming;
- automated systems that independently determine official scores or outcomes;
- arbitrary executable PHP, SQL, JavaScript, shell, or other scoring formulas;
- public exposure of a Minor’s precise location or contact information;
- public exposure of identity documents;
- a cryptocurrency or blockchain requirement;
- premature fragmentation into microservices;
- replacement of qualified human judges; or
- replacement of formal religious, legal, competition, or governmental authorities.

Payments, sponsorship capabilities, international expansion, and monetization are neither promised nor permanently excluded. They remain governed future policy decisions in the [Open-Decisions Register](open-decisions.md#commercial-and-expansion-decisions).

## Success characteristics

The organization will recognize a successful platform by observable evidence:

| Characteristic | Observable evidence |
| --- | --- |
| Trustworthy | Official records have explicit authority, provenance, version history, controlled correction paths, and independent verification outputs. |
| Correct | Server results reproduce from the identified Ruleset Version, exact Score Items, approved aggregation, and tie-break rules. |
| Safe | Cross-workspace access is denied by default; minor data and restricted Media Assets remain private absent valid purpose, consent, and authority. |
| Usable | Critical workflows work on mobile, support keyboard and assistive technology, distinguish provisional from final states, and explain error recovery. |
| Inclusive | English and Arabic foundations, full LTR/RTL behavior, readable Arabic script, low-bandwidth access, and non-color-only status cues are verified. |
| Durable | Participant Snapshots, Result versions, certificate history, evidence metadata, and Audit Events remain interpretable over time. |
| Scalable | Tenant, geography, and competition boundaries remain enforceable under growth; social and media work cannot consume reserved scoring capacity. |
| Operable | Monitored services, idempotent asynchronous work, tested backup/restore, incident evidence, and controlled Break-Glass Access support reliable recovery. |
| Governable | Named owner roles can review changes, conflicts, rules, Qur’an releases, retention, privacy, moderation, and security without one unrestricted operator. |

## Governance expectations

| Governance function | Required responsibility |
| --- | --- |
| Product and domain governance | Own product scope, canonical terminology, stakeholder outcomes, prioritization, and constitution changes. |
| Qualified Qur’an review | Approve canonical Qur’an Text Releases, readings, reference mappings, and religious terminology; normal administrators cannot edit canonical text. |
| Competition-rules governance | Approve declarative Ruleset Versions, scoring criteria, mistake taxonomies, aggregation, tie-break, and correction policies. |
| Security governance | Own risk acceptance, authentication standards, key control, privileged access, incident response, and security testing. |
| Privacy governance | Own purpose, lawful-policy review, minimization, retention, rights workflows, and qualified Nigerian privacy/compliance review. |
| Child-safety governance | Own Minor policy interpretation, Guardian verification, consent requirements, visibility defaults, reporting, and escalation. |
| Data stewardship | Own quality, deduplication, provenance classifications, historical validity, metadata, and authorized correction processes. |
| Moderation governance | Own community standards, case handling, appeals, enforcement consistency, and response objectives. |
| Technical change control | Own architecture compliance, ADR changes, release approval, migration safety, testing evidence, rollback, and operational readiness. |

Governance must enforce separation of duties. High-risk changes use Step-Up Authentication, explicit approvals, immutable Audit Events, and time-bounded authority. Break-Glass Access is exceptional, justified, temporary, monitored, and retrospectively reviewed.

## Constitutional constraints

- No public projection, certificate, client calculation, external provider, or integration is the authoritative official score store.
- No normal administrator may edit canonical Qur’an text, bypass tenant context, silently mutate finalized records, or grant themselves unrestricted authority.
- No legal, religious, retention, administrative-code, or organization-verification policy is inferred from this document; the relevant qualified owner must decide it.
- Amendments must identify affected requirements, invariants, ADRs, modules, privacy/security impacts, migration consequences, and approval evidence.

## Related documents

- [Documentation index](../README.md)
- [System boundaries](system-boundaries.md)
- [Domain glossary](../domain/domain-glossary.md)
- [Stakeholders and actors](../domain/stakeholders-and-actors.md)
- [Core modules and business invariants](../domain/core-modules-and-business-invariants.md)
- [P0-B01 requirements](../requirements/P0-B01-requirements.md)
- [Decision register](decision-register.md)
- [Open decisions](open-decisions.md)

