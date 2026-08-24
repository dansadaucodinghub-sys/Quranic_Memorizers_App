# Platform Sides and Capabilities

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Document version | 1.0.0 |
| Status | Authoritative capability-boundary baseline |
| Current phase | P0 — Product Constitution and System Requirements |
| Last updated | 2026-08-24 |
| Document owner role | Product and Experience Architecture |
| Approval status | Baseline established; detailed workflow policy remains for later batches |

## Purpose

This document separates QMDB’s principal user experiences so capability, visibility, approval, audit, accessibility, and child-safety rules can be specified without creating a normal unrestricted “super administrator.” A “side” is an experience/policy boundary, not necessarily a separate deployed application.

## Scope

All capabilities remain subject to the [actor catalogue](stakeholders-and-actors.md), deny-by-default authorization, Workspace ownership, contextual scope, resource policy, record state, conflict status, and Step-Up Authentication where risk requires it.

## S01 — Public Side

| Concern | Boundary |
| --- | --- |
| Primary users | Public Visitors and External Verification Consumers. |
| Primary jobs | Discover approved competitions, records, profiles, clips, and verify certificates. |
| Main capabilities | Public search, approved Read Models, published results, public profiles, moderated media, safe certificate/record verification, content reporting. |
| Data visible | Only policy-approved projections with clear provenance and provisional/final/revoked/superseded labels. |
| Data hidden | Private media, evidence masters, exact Minor location/contact, identity documents, non-public scores, internal notes, Consent evidence. |
| Sensitive actions | Verification queries and Content Reports; both require anti-abuse controls. |
| Required approval controls | Publication/withdrawal follows owning-module, privacy, Consent, and moderation policy. |
| Required audit events | Publication state, verification abuse, reports, and privileged changes to public projections. |
| Mobile considerations | Mobile-first discovery, efficient assets, resilient pagination, low transfer sizes. |
| Accessibility considerations | Semantic navigation, keyboard access, WCAG 2.2 AA target, readable Arabic, RTL, non-color status. |
| Child-safety implications | Minor contact and precise location remain hidden; public media requires applicable Guardian/Consent checks. |
| Explicit capability exclusions | No authorization from QR/public IDs, private-data enumeration, unrestricted messaging, or unmoderated livestreaming. |

## S02 — Individual Account Side

| Concern | Boundary |
| --- | --- |
| Primary users | Registered Individuals. |
| Primary jobs | Secure account/profile management, preferences, own records, privacy and notification choices. |
| Main capabilities | Authentication, Session/Device review, Profile editing, public visibility request, data/Consent requests, bookmarks and permitted community actions. |
| Data visible | Own account/profile/security summary and explicitly authorized personal records. |
| Data hidden | Other people’s private records, server secrets, internal risk signals, unrelated organization/competition data. |
| Sensitive actions | Credential recovery, privacy export, account closure, contact/visibility/Consent changes. |
| Required approval controls | Step-up for high-risk changes; controlled review where identity or historical records are affected. |
| Required audit events | Login/recovery, Session/Device changes, sensitive profile/Consent/privacy actions. |
| Mobile considerations | Clear security states, safe interruption/retry, concise forms and upload progress. |
| Accessibility considerations | Accessible authentication, error recovery, labels, focus, time-limit handling. |
| Child-safety implications | Age/status-sensitive capabilities and Guardian policy; conservative visibility. |
| Explicit capability exclusions | Self-granting roles, editing historical snapshots, viewing raw risk/other users’ data. |

## S03 — Memorizer and Competitor Side

| Concern | Boundary |
| --- | --- |
| Primary users | Memorizers, Competitors, and Reciters. |
| Primary jobs | Register, submit evidence, participate, view schedules/results, maintain achievement history, Appeal. |
| Main capabilities | Registration, Nomination association, eligibility status, Check-In readiness, Passage/Draw views when released, own scores/results, Memorizer Passport, correction/Appeal requests. |
| Data visible | Own Participant Snapshot projection, assigned event data, own result/evidence status, approved public comparisons. |
| Data hidden | Other competitors’ private data, concealed Passage Assignments, judge private notes, moderation/security internals. |
| Sensitive actions | Eligibility evidence, representation claim, media/Consent, Appeal and historical-record challenge. |
| Required approval controls | Registrar/eligibility, Guardian/Consent, Appeal Reviewer, and record-governance approvals as applicable. |
| Required audit events | Submissions, status transitions, evidence access, Check-In, visibility, Appeal/correction request. |
| Mobile considerations | Event-day mobile workflows, offline-safe drafts only where later approved, explicit sync/current status. |
| Accessibility considerations | Plain-language statuses, Arabic/RTL, assistive upload and schedule workflows. |
| Child-safety implications | Guardian controls, hidden contact/location, age-appropriate communication and visibility. |
| Explicit capability exclusions | No score calculation authority, ranking changes, self-attestation, or access to peers’ restricted evidence. |

## S04 — Guardian Side

| Concern | Boundary |
| --- | --- |
| Primary users | Guardians with recorded scoped authority. |
| Primary jobs | Protect a Minor, manage purpose-specific Consent, review participation and safety settings. |
| Main capabilities | View named Minor’s authorized records, respond to Consent requests, manage visibility, report safety concern, request correction. |
| Data visible | Only the named Minor and purpose/scope allowed by the Guardian relationship and policy. |
| Data hidden | Other family members, unrelated competitions, judge notes, security internals. |
| Sensitive actions | Consent grant/withdrawal, media publication, profile visibility, identity/relationship evidence. |
| Required approval controls | Guardian authority method, Step-Up Authentication, dual/subject input where policy later requires. |
| Required audit events | Relationship evidence/status, all Consent and publication decisions, sensitive reads/exports. |
| Mobile considerations | Clear subject/purpose labels, safe document capture if approved, recoverable steps. |
| Accessibility considerations | Understandable policy versions, screen-reader friendly decisions, no coercive patterns. |
| Child-safety implications | This side is foundational; absence/ambiguity fails toward privacy and non-publication. |
| Explicit capability exclusions | No ownership of the Minor’s identity, score editing, blanket perpetual Consent, or authority outside scope. |

## S05 — Judge Side

| Concern | Boundary |
| --- | --- |
| Primary users | Judges. |
| Primary jobs | Concentrate on assigned Performance evidence and submit accurate Score Sheets. |
| Main capabilities | View assignment/rules/passages, declare conflict, enter Score Items, preview client total, submit, respond to controlled reopening. |
| Data visible | Minimum Participant Snapshot, assignment, Ruleset Version, relevant evidence, own Score Sheet/version status. |
| Data hidden | Unneeded personal/contact data, unauthorized peer sheets, rankings before policy release, unrelated assignments. |
| Sensitive actions | Conflict declaration, Score Sheet submission, new version after reopening. |
| Required approval controls | Valid assignment, panel/session state, MFA/step-up, reopening authority; server recalculation. |
| Required audit events | Assignment access, conflict, drafts as policy requires, submission, failed validation, reopening/version. |
| Mobile considerations | Large touch targets, autosaved non-authoritative drafts, network/status clarity, no accidental submit. |
| Accessibility considerations | Distraction-minimized, keyboard efficient, screen-reader semantics, non-color mistake/status cues. |
| Child-safety implications | Only minimum participant data; evidence use limited to judging purpose. |
| Explicit capability exclusions | No arbitrary rules, direct Result edits, peer overwrite, client-authoritative total, or out-of-scope evidence. |

## S06 — Chief Judge Side

| Concern | Boundary |
| --- | --- |
| Primary users | Chief Judges. |
| Primary jobs | Ensure panel completeness, conflict handling, and approved scoring review. |
| Main capabilities | Panel overview, assignment/recusal review, completeness checks, controlled reopening/exception request or approval per policy. |
| Data visible | Scoped panel submissions, evidence, validation states, conflicts, and result-calculation explanation. |
| Data hidden | Unrelated panels/Workspaces and private data unnecessary to oversight. |
| Sensitive actions | Judge replacement, reopening, exception, panel close recommendation. |
| Required approval controls | Separation of duties, no self-approval, conflict evaluation, Step-Up Authentication. |
| Required audit events | All panel access, conflict/replacement, reopening, exception, approval/rejection. |
| Mobile considerations | High-signal operational dashboard, explicit stale/offline states, reliable confirmation. |
| Accessibility considerations | Dense data with semantic headings/tables, focus management, text status and error recovery. |
| Child-safety implications | Participant detail minimized despite broader score access. |
| Explicit capability exclusions | No silent score mutation, global role management, or unilateral policy changes. |

## S07 — Organizer Side

| Concern | Boundary |
| --- | --- |
| Primary users | Competition Directors, Registrars, and explicitly assigned organizer personnel. |
| Primary jobs | Configure and operate an Edition under approved rules. |
| Main capabilities | Edition structure, Registration windows, schedules, venues, assignments, communications, operational status and reports. |
| Data visible | Assigned Edition operational data and minimized Participant information. |
| Data hidden | Other Workspaces, unnecessary identity documents, security secrets, unauthorized Judge content. |
| Sensitive actions | Open/close stages, assignment, eligibility exception, schedule/draw changes, publication requests. |
| Required approval controls | Rules/structure approval, separation for exceptions/corrections, Step-Up Authentication. |
| Required audit events | Configuration/version, assignments, state changes, data exports, exceptions, publication. |
| Mobile considerations | Event-day rosters, Check-In, resilient search, explicit sequence/current status. |
| Accessibility considerations | Keyboard/assistive workflows, large targets, clear errors and provisional/final labels. |
| Child-safety implications | Restricted rosters, no public precise locations/contact, Guardian workflow integration. |
| Explicit capability exclusions | No executable scoring, canonical Qur’an editing, unrestricted tenant access, or direct Final Result change. |

## S08 — School or Organization Administration Side

| Concern | Boundary |
| --- | --- |
| Primary users | Organization and School Administrators. |
| Primary jobs | Manage scoped organization identity, Units, Memberships, nominations, and reports. |
| Main capabilities | Organization profile, Membership/Role requests, roster, nomination, approved competitions, aggregate reports. |
| Data visible | Own Organization/Units and authorized members/participants. |
| Data hidden | Other organizations’ private data, cross-workspace records, scoring internals. |
| Sensitive actions | Member/Role changes, student access, attestation, nomination, export. |
| Required approval controls | Organization recognition, role grant policy, step-up, child-data purpose. |
| Required audit events | Membership/Role, roster, nomination/attestation, export and sensitive access. |
| Mobile considerations | Responsive roster and nomination workflows; constrained-network uploads. |
| Accessibility considerations | Accessible tables/forms, bulk action confirmation, branding constrained by AA requirements. |
| Child-safety implications | School/Minor data minimization and Guardian authority cannot be assumed. |
| Explicit capability exclusions | No inherited judging, Guardian, broader geography, or national authority. |

## S09 — LGA or Area Council Administration Side

| Concern | Boundary |
| --- | --- |
| Primary users | LGA or Area Council Coordinators. |
| Primary jobs | Coordinate approved local competitions and organizations. |
| Main capabilities | Scoped directories, competition coordination, nominations, aggregate reports, assignments where granted. |
| Data visible | Approved records within named area and authorized Workspaces. |
| Data hidden | State/national unrestricted detail, unrelated Workspaces, unnecessary personal data. |
| Sensitive actions | Cross-organization view/export, appointment, attestation. |
| Required approval controls | Explicit Administrative Scope and tenant/purpose policy; step-up. |
| Required audit events | Cross-organization access, exports, appointments, exceptions. |
| Mobile considerations | Field-ready, low-bandwidth lists and status synchronization. |
| Accessibility considerations | Plain administrative labels, RTL-ready forms, accessible maps only as enhancement. |
| Child-safety implications | Aggregation preferred; no precise Minor location/contact. |
| Explicit capability exclusions | Geographic label alone grants nothing; no State/FCT/national or score-edit authority. |

## S10 — State or FCT Administration Side

| Concern | Boundary |
| --- | --- |
| Primary users | State or FCT Coordinators. |
| Primary jobs | Coordinate approved State/FCT scope and lower-area reporting. |
| Main capabilities | Scoped program overview, organization coordination, approved assignments and aggregate reporting. |
| Data visible | Named State/FCT scope plus expressly authorized Workspaces and detail levels. |
| Data hidden | Other State/FCT scopes, private records without purpose, national unrestricted data. |
| Sensitive actions | Multi-organization report/export, coordinator appointment, exceptional access request. |
| Required approval controls | Explicit Administrative Scope, purpose, tenant policy, step-up/separation. |
| Required audit events | Privileged reads, appointments, cross-organization reports, exports, exceptions. |
| Mobile considerations | Progressive disclosure and efficient aggregate data. |
| Accessibility considerations | Semantic charts with tabular alternatives; no color-only comparisons. |
| Child-safety implications | Aggregate reporting and suppression of identifying low-volume detail where policy requires. |
| Explicit capability exclusions | No inferred national or cross-workspace authority and no official-score mutation. |

## S11 — National Administration Side

| Concern | Boundary |
| --- | --- |
| Primary users | National Coordinators and specifically appointed governance actors. |
| Primary jobs | Govern approved national programs and understand national outcomes. |
| Main capabilities | Approved aggregate reporting, rollout scope, policy publication, scoped appointments and oversight requests. |
| Data visible | Aggregates by default; record detail only with explicit purpose, policy, and minimum scope. |
| Data hidden | Secrets, indiscriminate personal/media detail, unrelated tenant internals. |
| Sensitive actions | Cross-workspace detail request/export, national appointment/policy action. |
| Required approval controls | Separate national authority, step-up, purpose justification, separation of duties. |
| Required audit events | Every cross-workspace access, export, appointment, policy/version, approval. |
| Mobile considerations | Summary-first views with secure drill-down and transfer controls. |
| Accessibility considerations | Accessible reporting alternatives, clear definitions and uncertainty/status labels. |
| Child-safety implications | De-identification/aggregation preferred and public Minor detail prohibited. |
| Explicit capability exclusions | No standing unrestricted “super administrator,” raw database access, or silent tenant entry. |

## S12 — Moderation and Community-Safety Side

| Concern | Boundary |
| --- | --- |
| Primary users | Media Moderators and Community-Safety Officers. |
| Primary jobs | Triage reports, protect people, apply content policy, manage content appeals. |
| Main capabilities | Queues, Moderation Cases, evidence, Actions, urgent containment, escalation, content-appeal handling by scope. |
| Data visible | Case-scoped content, reports, history, policy version, minimum identity/safety context. |
| Data hidden | Unrelated private activity, official score-edit controls, secrets. |
| Sensitive actions | Content restriction, account capability restriction, Minor safety escalation, evidence disclosure. |
| Required approval controls | High-impact actions and appeals use scoped review/separation; emergency action receives retrospective review. |
| Required audit events | Every case/evidence access, decision, policy basis, action, expiry/reversal, disclosure. |
| Mobile considerations | Safe evidence viewing, rapid containment, upload/data warnings. |
| Accessibility considerations | Trauma-aware clear UI, keyboard case handling, captions/transcripts where approved. |
| Child-safety implications | Highest-priority triage class; reporter and Minor identities protected. |
| Explicit capability exclusions | No official competition-record mutation or public exposure of report identities. |

## S13 — Appeals Side

| Concern | Boundary |
| --- | --- |
| Primary users | Appellants, Appeal Reviewers, and authorized follow-on actors. |
| Primary jobs | Submit, evaluate, decide, and implement governed remedies without rewriting history. |
| Main capabilities | Appeal intake, grounds/evidence, conflict declaration, review, decision, authorized reopening/correction trigger. |
| Data visible | Identified challenged versions and minimum related evidence. |
| Data hidden | Unrelated cases and unnecessary personal/security data. |
| Sensitive actions | Evidence access, Appeal Decision, remedy authorization. |
| Required approval controls | Reviewer authority/independence, conflicts, step-up, remedy separation. |
| Required audit events | Intake, evidence/version access, conflicts, communications, decision, remedy. |
| Mobile considerations | Draft preservation, upload progress, deadline/status clarity. |
| Accessibility considerations | Understandable grounds/status, structured evidence, explicit recovery errors. |
| Child-safety implications | Guardian/Minor participation and protected evidence handling per policy. |
| Explicit capability exclusions | No in-place source edits, deletion of original submissions, or self-review of conflicts. |

## S14 — Audit and Compliance Side

| Concern | Boundary |
| --- | --- |
| Primary users | Internal Auditors, qualified reviewers, and authorized oversight bodies. |
| Primary jobs | Assess controls, provenance, versions, access, decisions, and remediation. |
| Main capabilities | Purpose-scoped audit query, checkpoint verification, evidence package, findings and follow-up. |
| Data visible | Required metadata and approved evidence within engagement scope. |
| Data hidden | Secrets, unrelated content, excessive personal data, live credentials. |
| Sensitive actions | Broad query/export, evidence disclosure, finding closure. |
| Required approval controls | Engagement mandate, purpose, time limit, step-up, export review. |
| Required audit events | Every audit search/read/export, evidence package, finding/state change. |
| Mobile considerations | Read-only summary; sensitive bulk evidence restricted to suitable managed contexts. |
| Accessibility considerations | Accessible tables, downloadable accessible evidence, text alternatives for diagrams/charts. |
| Child-safety implications | Minimize and redact child data unless essential to the engagement. |
| Explicit capability exclusions | Audit Role grants no business mutation, unrestricted browsing, or secret access. |

## S15 — Privacy Administration Side

| Concern | Boundary |
| --- | --- |
| Primary users | Data-Protection or Privacy Officers and authorized case handlers. |
| Primary jobs | Govern purpose, minimization, Consent, retention, rights, residency, and privacy incidents. |
| Main capabilities | Data maps, policy versions, case workflows, scoped searches, retention holds/actions, impact assessments. |
| Data visible | Case/policy-scoped personal data and provenance. |
| Data hidden | Unrelated records, security secrets, data without approved purpose. |
| Sensitive actions | Disclosure/export, restriction/anonymization request, policy/retention approval. |
| Required approval controls | Qualified review, separation, legal-hold checks, step-up, owning-module workflow. |
| Required audit events | All personal-data search/access, decision, disclosure, retention and rights action. |
| Mobile considerations | No bulk sensitive export; safe case review and explicit redaction. |
| Accessibility considerations | Clear purpose/rights language, accessible Consent/policy comparison. |
| Child-safety implications | Enhanced minimization, Guardian/Minor policy, and qualified review. |
| Explicit capability exclusions | No final legal advice by the software, blanket access, or silent deletion of official history. |

## S16 — Security Operations Side

| Concern | Boundary |
| --- | --- |
| Primary users | Security Operators. |
| Primary jobs | Monitor, investigate, contain, and recover from threats. |
| Main capabilities | Security alerts, Session/credential containment, scoped incident evidence, key-event monitoring, risk controls. |
| Data visible | Security telemetry and minimum linked business context. |
| Data hidden | Plaintext secrets, unrelated content, business mutation controls. |
| Sensitive actions | Revoke access, contain Account/integration, privileged incident access, key operation. |
| Required approval controls | Just-in-time privilege, step-up, dual control for key/high-impact actions, incident linkage. |
| Required audit events | Every privileged read/action, alert disposition, containment, key/access event. |
| Mobile considerations | Alert acknowledgement only where secure; high-risk operations require suitable managed context. |
| Accessibility considerations | Non-color severity, keyboard workflows, readable event timelines and recovery guidance. |
| Child-safety implications | Security evidence involving Minors is especially restricted. |
| Explicit capability exclusions | No score/result editing, curiosity access, secrets in logs, or self-approved Break-Glass Access. |

## S17 — Platform Operations Side

| Concern | Boundary |
| --- | --- |
| Primary users | Platform Operators and controlled Background/Media worker operators. |
| Primary jobs | Deploy, monitor, scale, back up, restore, and maintain services. |
| Main capabilities | Change/release operations, health/capacity, queue controls, backup/restore tests, configuration and secret rotation. |
| Data visible | Infrastructure/telemetry by default; production content only under separately justified incident scope. |
| Data hidden | Business controls, plaintext secrets, unnecessary personal/media data. |
| Sensitive actions | Production deployment/access, restore, configuration/key changes, worker isolation override. |
| Required approval controls | Change control, environment separation, step-up, dual control/Break-Glass where required. |
| Required audit events | Every production action, deployment, rollback, restore, configuration/secret and privileged access event. |
| Mobile considerations | Monitoring/acknowledgement may be mobile; destructive/high-risk work uses controlled environments. |
| Accessibility considerations | Accessible operational dashboards and alert channels; status not color-only. |
| Child-safety implications | Operators do not browse child/media content absent case-specific authority. |
| Explicit capability exclusions | No manual silent database edits, normal hard deletion, shared accounts, or business-policy decisions. |

## S18 — Support Side

| Concern | Boundary |
| --- | --- |
| Primary users | Support Officers. |
| Primary jobs | Resolve a named user/organization issue safely and escalate specialized cases. |
| Main capabilities | Case intake, identity-safe troubleshooting, minimum diagnostic view, approved recovery assistance, escalation. |
| Data visible | Case-scoped fields explicitly necessary for resolution. |
| Data hidden | Full database/profile history, private media, scores/evidence, secrets, unrelated tenants. |
| Sensitive actions | Account recovery assistance, temporary diagnostic grant, protected escalation. |
| Required approval controls | Purpose, named case, time-bound field access, step-up and supervisor/security/privacy escalation by risk. |
| Required audit events | Every sensitive view/action, purpose, user communication, escalation and closure. |
| Mobile considerations | Secure case response; no bulk data or private media on unmanaged contexts. |
| Accessibility considerations | Accessible case UI and recovery instructions for users. |
| Child-safety implications | Minor cases route to trained safety/privacy roles; support does not expose contact/location. |
| Explicit capability exclusions | No impersonation, password/secret viewing, score correction, unrestricted tenant access, or Break-Glass by convenience. |

## Capability matrix

The matrix maps primary operating sides. “Governed” means the actor participates only through a formal mandate or review, not day-to-day access. Service actors operate behind the corresponding side and do not inherit human permissions.

| Actor ID / actor | Primary platform sides |
| --- | --- |
| ACTOR-001 Public Visitor | S01 |
| ACTOR-002 Registered Individual | S01, S02 |
| ACTOR-003 Minor | S02, S03 under protective policy |
| ACTOR-004 Memorizer | S02, S03 |
| ACTOR-005 Competitor | S02, S03, S13 as appellant |
| ACTOR-006 Reciter | S02, S03 |
| ACTOR-007 Guardian | S02, S04, S13 where authorized |
| ACTOR-008 Coach | S03, S07 where assigned |
| ACTOR-009 Teacher | S03, S08 where assigned |
| ACTOR-010 Judge | S05 |
| ACTOR-011 Chief Judge | S06 |
| ACTOR-012 Competition Registrar | S07 |
| ACTOR-013 Competition Director | S07 |
| ACTOR-014 Organization Administrator | S08 |
| ACTOR-015 School Administrator | S08 |
| ACTOR-016 LGA or Area Council Coordinator | S09 |
| ACTOR-017 State or FCT Coordinator | S10 |
| ACTOR-018 National Coordinator | S11 |
| ACTOR-019 Media Moderator | S12 |
| ACTOR-020 Community-Safety Officer | S12 |
| ACTOR-021 Appeal Reviewer | S13 |
| ACTOR-022 Certificate Officer | S07 plus certificate workspace; governed by S14/S16 controls |
| ACTOR-023 Internal Auditor | S14 |
| ACTOR-024 Data-Protection or Privacy Officer | S15 |
| ACTOR-025 Security Operator | S16 |
| ACTOR-026 Support Officer | S18 |
| ACTOR-027 Platform Operator | S17 |
| ACTOR-028 Break-Glass Administrator | S16/S17 only during declared incident |
| ACTOR-029 External Verification Consumer | S01 |
| ACTOR-030 Authorized Integration Client | API boundary corresponding to expressly granted owning side |
| ACTOR-031 Background Worker | S17 service plane; owning module use case only |
| ACTOR-032 Media Processing Worker | S17 isolated media plane |
| ACTOR-033 Qualified Qur’an Reviewer | Governed review across S07/S14; no normal operational side |
| ACTOR-034 Competition-Rules Governance Body | Governed review across S06/S07/S14 |
| ACTOR-035 Organization | S07/S08 through authorized principals and explicit relationship |
| ACTOR-036 Qualified Nigerian Privacy and Compliance Reviewer | Governed advice to S15; no standing production access |

## Cross-side controls

- Switching sides never enlarges authority; each request is independently authorized.
- Sensitive state-changing actions require server-side checks, current Record Version, explicit scope, and replay protection.
- Public and live views are rebuildable Read Models, not authoritative stores.
- Organization branding, language choice, or device size cannot remove accessibility, security, privacy, or status information.
- Social/media queues, worker pools, and data paths remain isolated from reserved competition capacity.

## Related documents

- [Documentation index](../README.md)
- [Stakeholders and actors](stakeholders-and-actors.md)
- [Domain glossary](domain-glossary.md)
- [System boundaries](../project/system-boundaries.md)
- [Core modules and business invariants](core-modules-and-business-invariants.md)
- [P0-B01 requirements](../requirements/P0-B01-requirements.md)

