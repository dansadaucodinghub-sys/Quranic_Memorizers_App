# P0-B02 Permission and Capability Matrix

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Baseline | QMDB-BL-001 |
| Batch | QMDB-P0-B02 |
| Document version | 1.0.0 |
| Status | Complete capability baseline |
| Last updated | 2026-08-24 |

## Matrix legend

| Value | Meaning |
| --- | --- |
| A | Allowed within assigned scope |
| C | Conditionally allowed |
| R | Read-only within assigned scope |
| P | Requires additional approval |
| S | Requires step-up authentication |
| E | Emergency break-glass only |
| N | Denied |

Values combine when controls accumulate. No value is a grant by title: active named identity, Workspace/Membership, Role/Permission, administrative/resource/assignment scope, record state, time and risk remain mandatory.

## Actor groups

- **G1 — Public and verification:** ACTOR-001 Public Visitor; ACTOR-029 External Verification Consumer.
- **G2 — Individual and participant:** ACTOR-002 Registered Individual; ACTOR-003 Minor; ACTOR-004 Memorizer; ACTOR-005 Competitor; ACTOR-006 Reciter; ACTOR-007 Guardian; ACTOR-008 Coach; ACTOR-009 Teacher.
- **G3 — Competition officials:** ACTOR-010 Judge; ACTOR-011 Chief Judge; ACTOR-012 Competition Registrar; ACTOR-013 Competition Director.
- **G4 — Organization and coordinators:** ACTOR-014 Organization Administrator; ACTOR-015 School Administrator; ACTOR-016 LGA or Area Council Coordinator; ACTOR-017 State or FCT Coordinator; ACTOR-018 National Coordinator.
- **G5 — Safety, records and oversight:** ACTOR-019 Media Moderator; ACTOR-020 Community-Safety Officer; ACTOR-021 Appeal Reviewer; ACTOR-022 Certificate Officer; ACTOR-023 Internal Auditor; ACTOR-024 Data-Protection or Privacy Officer; ACTOR-033 Qualified Qur’an Reviewer; ACTOR-034 Competition-Rules Governance Body; ACTOR-036 Qualified Nigerian Privacy and Compliance Reviewer.
- **G6 — Security, support and operations:** ACTOR-025 Security Operator; ACTOR-026 Support Officer; ACTOR-027 Platform Operator; ACTOR-028 Break-Glass Administrator.
- **G7 — Organizations and service actors:** ACTOR-030 Authorized Integration Client; ACTOR-031 Background Worker; ACTOR-032 Media Processing Worker; ACTOR-035 Organization.

All 36 actors from the P0-B01 actor catalog are represented. Grouping makes the matrix readable; the explicit restriction in a capability definition takes precedence over a permissive group cell.

## Capability definitions

| Capability ID | Capability name | Owning module | Applicable scope | Sensitive data involved | Required authentication level | Approval requirement | Audit requirement | Explicitly denied actors | Related functional requirements |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-CAP-001 | Account management | Identity and Access | Own account; security incident scope | Credentials, contact, recovery and session data | Strong authentication; step-up for recovery/closure | Additional approval only for exceptional assisted recovery | All credential, recovery, state and session changes | Public visitors; organization titles outside an assigned support/security case | QMDB-FR-IAM-001, QMDB-FR-IAM-002, QMDB-FR-IAM-005, QMDB-FR-IAM-006, QMDB-FR-SES-002 |
| QMDB-CAP-002 | Profile management | People and Profiles | Own Person/Profile or named registrar case | Personal, identity and Minor data | Strong authentication; step-up for sensitive identity change | Approval for merge or protected correction | Sensitive reads, link, merge and visibility changes | Public visitors; unrelated administrators | QMDB-FR-PPL-001, QMDB-FR-PPL-002, QMDB-FR-PPL-003, QMDB-FR-PPL-004 |
| QMDB-CAP-003 | Guardian management | People and Guardianship | Named Person, purpose and time | Child, family and authority evidence | Strong authentication and step-up | Independent verification/override approval | Every relationship/evidence/override/consent action | Public visitors; coaches; teachers; unrelated administrators | QMDB-FR-GUA-001, QMDB-FR-GUA-002, QMDB-FR-GUA-004 |
| QMDB-CAP-004 | Workspace management | Workspaces and Tenancy | Assigned Workspace | Membership, organization and tenant configuration | MFA; step-up for suspension/closure | Approval for suspension/archive and ownership transfer | Every membership/role/scope/state change | Public/ordinary individuals; cross-workspace titles | QMDB-FR-TEN-001, QMDB-FR-TEN-002 |
| QMDB-CAP-005 | Organization verification | Organizations | Assigned Workspace and administrative geography | Organization evidence and authority | MFA plus step-up | Independent organization-governance approval | Submission, evidence access, decision, suspension | Public visitors; applicant self-approval; normal organization admin as approver | QMDB-FR-ORG-002, QMDB-FR-ORG-004 |
| QMDB-CAP-006 | Geography management | Geography | Governed national hierarchy or assigned scope | Boundary/source/version evidence | MFA plus step-up | Qualified governance review for structural change | Every source/version/status/boundary change | Public visitors; ordinary tenant administrators | QMDB-FR-GEO-001, QMDB-FR-GEO-002, QMDB-FR-GEO-003 |
| QMDB-CAP-007 | Qur’an reference release management | Qur’an Reference Governance | Named release mandate | Canonical text, mappings, checksums and review evidence | Phishing-resistant MFA/step-up | Independent qualified review and quorum policy | Import, comparison, approval, activation, supersession | Normal administrators; integrations; competition staff without reviewer mandate | QMDB-FR-QRF-001, QMDB-FR-QRF-002, QMDB-FR-QRF-003, QMDB-FR-QRF-004, QMDB-FR-QRF-005 |
| QMDB-CAP-008 | Competition creation | Competition Configuration | Assigned organizer Workspace and Organization | Competition contacts, dimensions and configuration | MFA; step-up for publication-impacting state | Review required before approval/publication | Create/change/state events and dimension changes | Public visitors; judges outside director assignment | QMDB-FR-CMP-001, QMDB-FR-CMP-003 |
| QMDB-CAP-009 | Competition approval | Competition Configuration | Named Edition and governance scope | Competition/rules/evidence | MFA plus step-up | Separate approving-role category; no self-approval | Review, decision, reason, evidence and state | Creator acting alone; integrations; public actors | QMDB-FR-CMP-002, QMDB-FR-CMP-004, QMDB-FR-RUL-003 |
| QMDB-CAP-010 | Registration review | Registration and Eligibility | Assigned Edition/category/Workspace | Identity, Minor, eligibility and evidence | MFA; step-up for exception | Approval for late/exception path | Every sensitive view, evidence request and decision | Public visitors; judges; unrelated organization staff | QMDB-FR-REG-001, QMDB-FR-REG-002, QMDB-FR-REG-003, QMDB-FR-REG-004, QMDB-FR-REG-005 |
| QMDB-CAP-011 | Participant check-in | Scheduling and Venue Operations | Assigned session/participant roster | Participant attendance and limited identity | MFA or registered venue session; step-up for exception | Approval for late-admission exception where policy requires | Check-In, late, absent, withdrawal and override | Public visitors; unassigned judges | QMDB-FR-SCH-003 |
| QMDB-CAP-012 | Judge assignment | Judging | Assigned Edition/panel/session | Judge identity, qualification, schedule and conflicts | MFA plus step-up | Chief Judge/director separation and conflict review | Invitation, acceptance, replacement, recusal, revocation | Public/competitors; integrations; conflicted assigner | QMDB-FR-JDG-001, QMDB-FR-JDG-003 |
| QMDB-CAP-013 | Conflict review | Judging and Governance | Named declaration and affected assignment | Relationships, evidence and decision | MFA plus step-up | Independent conflict reviewer; no self-decision | Declaration, evidence access, decision, exception | Subject deciding own conflict; public/integrations | QMDB-FR-JDG-002 |
| QMDB-CAP-014 | Score submission | Scoring | Assigned judge/performance/version/time | Participant evidence and official scoring data | MFA and step-up at official submission | No second approval for own eligible sheet; quorum later | Draft save as policy requires; every submission/lock/denial | All actors except assigned conflict-cleared Judge; integrations | QMDB-FR-SCR-001, QMDB-FR-SCR-002, QMDB-FR-SCR-003, QMDB-FR-SCR-004 |
| QMDB-CAP-015 | Score reopening | Scoring | Named submitted sheet and panel | Former/current scores, reason and evidence | MFA plus step-up | Separate approving category; no self-approval | Request, review, approval/denial, versions and resubmission | Public; integrations; original judge acting alone | QMDB-FR-SCR-005 |
| QMDB-CAP-016 | Result publication | Results and Live Delivery | Named Edition/category/round | Scores, rankings and public Participant projection | MFA plus step-up | Publication gate and panel completeness review | Projection version/status/sequence and decision | Public actor as publisher; integrations; social services | QMDB-FR-RSL-001, QMDB-FR-RSL-002, QMDB-FR-LIV-001 |
| QMDB-CAP-017 | Result finalization | Results | Named Edition/result version | Complete scoring, appeals and approval evidence | MFA plus step-up | Separate approving category; no unresolved appeal; no self-approval | All readiness checks, approval, signature/hash and state | Public, integrations, conflicted or source-editing actor alone | QMDB-FR-RSL-003 |
| QMDB-CAP-018 | Appeal review | Appeals | Named assigned Appeal | Scores, evidence, identities and reasons | MFA plus step-up | Independent conflict-cleared reviewer/panel | Access, conflicts, deliberation, decision and remedy | Original decision-maker alone; public; integrations | QMDB-FR-APL-001, QMDB-FR-APL-002, QMDB-FR-APL-003 |
| QMDB-CAP-019 | Certificate issuance | Certificates | Assigned current Final Result/recipient | Recipient snapshot, result and key metadata | MFA plus step-up | Issuance approval/key ceremony per OD-017/OD-034 | Eligibility, generation, hash/sign, issue and delivery | Public; integrations; result editor acting alone | QMDB-FR-CER-001, QMDB-FR-CER-002 |
| QMDB-CAP-020 | Certificate revocation | Certificates | Named issued Certificate | Certificate, reason, key and recipient status | MFA plus step-up | Independent approval; no self-approval | Reason, evidence, approval, revocation and notices | Public; integrations; ordinary organization admin | QMDB-FR-CER-004 |
| QMDB-CAP-021 | Legacy import | Trusted Records | Approved source and target Workspace | Historical personal/evidence/provenance data | MFA; step-up for publication | Independent attestation/publication approval | Batch/item source, mapping, decisions and reconciliation | Public; unapproved organizations; integrations without import contract | QMDB-FR-IMP-001, QMDB-FR-REC-001 |
| QMDB-CAP-022 | Media publication | Media and Evidence | Owned/assigned asset and approved purpose | Voice/video, Minor, consent, ownership and evidence | MFA; step-up for restricted Minor publication | Guardian/moderation/separate approval as applicable | Upload, processing, consent, approval, publication/unpublication | Public uploaders; worker as publisher; conflicted reviewer | QMDB-FR-MED-001, QMDB-FR-MED-002, QMDB-FR-MED-003, QMDB-FR-MED-004 |
| QMDB-CAP-023 | Moderation | Moderation and Safety | Assigned queue/case/policy | Reports, reporter identity, Minor and safety evidence | MFA; step-up for high-impact action | Independent approval/appeal for permanent/high-risk action | Every case access, decision, action, notice and appeal | Public actor as moderator; conflicted moderator; integrations | QMDB-FR-MOD-001, QMDB-FR-MOD-002 |
| QMDB-CAP-024 | Privacy requests | Privacy and Data Governance | Verified subject/case/policy | Highly Restricted identity and personal data | MFA plus step-up | Qualified/independent approval for destructive or retention override | Every identity check, search, redaction, decision and delivery | Public/unverified requester; unrelated admin/support | QMDB-FR-PRI-001, QMDB-FR-PRI-002, QMDB-FR-PRI-003, QMDB-FR-PRI-004 |
| QMDB-CAP-025 | Audit access | Audit and Integrity | Approved engagement/range/purpose | Broad Highly Restricted metadata and selected evidence | Phishing-resistant MFA plus step-up | Independent approval for raw access/export | Every query, result, export/download and finding | Normal administrators; audited subject without engagement; integrations | QMDB-FR-AUD-001, QMDB-FR-AUD-002 |
| QMDB-CAP-026 | Security operations | Security Operations | Assigned incident/environment/control | Security telemetry, evidence, sessions and keys | Phishing-resistant MFA, just-in-time step-up | Approval for high-impact containment/key action | Every investigation, containment, key/session action and review | Business administrators; public; integrations except own telemetry contract | QMDB-FR-SEC-001, QMDB-FR-SEC-002 |
| QMDB-CAP-027 | Support access | Platform Support | Named verified support case/fields/time | Minimum personal/account diagnostics | MFA plus step-up | Separate approval for temporary data access | Grant, each view/action, expiry and review | Unassigned support; impersonation; broad tenant browsing | QMDB-FR-OPS-001 |
| QMDB-CAP-028 | Break-glass access | Platform Operations | Declared incident, explicit resources and short duration | Potentially all classes within exact emergency scope | Strong MFA and per-action step-up where feasible | Approval where feasible and mandatory post-use independent review | Activation, every action, expiry/revocation and findings | Everyone without named emergency eligibility; standing use | QMDB-FR-OPS-002 |
| QMDB-CAP-029 | Reporting | Reporting and Analytics | Assigned Workspace/administrative geography and report | Aggregates plus justified detail | MFA; step-up for sensitive drill-down | Approval only for sensitive/high-risk report class | Report/filter/scope/source/freshness and anomalous access | Public except public statistics; cross-scope coordinators | QMDB-FR-RPT-001, QMDB-FR-RPT-003 |
| QMDB-CAP-030 | Data export | Reporting and Privacy | Named approved report/query and current scope | Bulk personal, official, audit or security data | MFA plus step-up | Separate approval for high-volume/sensitive/audit export | Request, fields, rows, purpose, approval, artifact hash/download/expiry | Public; ordinary users; revoked requester; unapproved support | QMDB-FR-RPT-002, QMDB-FR-AUD-002 |
| QMDB-CAP-031 | Integration management | Integrations | Owned client, tenant, API scope and endpoint | Credentials, webhooks and contract-specific data | MFA for admin; strong machine authentication for client | Approval for sensitive scopes | Registration, credentials, scope, delivery, replay, suspension | Public; client self-expansion; integrations for authoritative scores | QMDB-FR-INT-001, QMDB-FR-INT-002, QMDB-FR-INT-003 |
| QMDB-CAP-032 | Platform operations | Platform Operations | Assigned environment/service/change/restore | Configuration, infrastructure, backups, keys and operational metadata | Phishing-resistant MFA, just-in-time and step-up | Change/recovery/key approval based on impact | Every config/deploy/health/queue/restore/key action | Business admin; support; public; permanent unrestricted admin | QMDB-FR-OPS-003, QMDB-FR-OPS-004, QMDB-FR-OPS-005, QMDB-FR-OFF-001, QMDB-FR-OFF-002 |

## Actor-group capability matrix

| Capability | G1 Public | G2 Individual | G3 Competition | G4 Organization/coordinators | G5 Safety/oversight | G6 Security/support/operations | G7 Organization/services |
| --- | --- | --- | --- | --- | --- | --- | --- |
| QMDB-CAP-001 — Account management | R | C | N | N | N | N | N |
| QMDB-CAP-002 — Profile management | R | A | C | C | C+P | C | N |
| QMDB-CAP-003 — Guardian management | N | C+S | N | N | C+P+S | N | N |
| QMDB-CAP-004 — Workspace management | N | N | N | A+S | C+P+S | C+S | N |
| QMDB-CAP-005 — Organization verification | R | N | N | C | A+P+S | C+S | N |
| QMDB-CAP-006 — Geography management | R | R | N | R | A+P+S | C+S | R |
| QMDB-CAP-007 — Qur’an reference release management | R | R | R | R | A+P+S | C+S | N |
| QMDB-CAP-008 — Competition creation | R | N | C+S | A+S | R | N | N |
| QMDB-CAP-009 — Competition approval | R | N | C+S | C+P+S | A+P+S | N | N |
| QMDB-CAP-010 — Registration review | N | C | A+S | C | R | C | N |
| QMDB-CAP-011 — Participant check-in | N | R | A | C | N | N | N |
| QMDB-CAP-012 — Judge assignment | N | N | A+S | C+S | C+P+S | N | N |
| QMDB-CAP-013 — Conflict review | N | C | A+S | C | A+P+S | C | N |
| QMDB-CAP-014 — Score submission | N | C | A+S | N | N | N | N |
| QMDB-CAP-015 — Score reopening | N | N | C+P+S | N | A+P+S | N | N |
| QMDB-CAP-016 — Result publication | R | R | C+S | C+S | R | N | R |
| QMDB-CAP-017 — Result finalization | R | R | C+P+S | C+P+S | A+P+S | N | N |
| QMDB-CAP-018 — Appeal review | N | C | R | N | A+S | N | N |
| QMDB-CAP-019 — Certificate issuance | R | R | N | N | A+P+S | C+S | N |
| QMDB-CAP-020 — Certificate revocation | R | R | N | N | A+P+S | C+S | N |
| QMDB-CAP-021 — Legacy import | R | R | C | C+S | A+P+S | C | C |
| QMDB-CAP-022 — Media publication | R | C | C | C | A+P+S | C | N |
| QMDB-CAP-023 — Moderation | C | C | N | N | A+S | C | N |
| QMDB-CAP-024 — Privacy requests | N | A+S | N | N | A+P+S | C | N |
| QMDB-CAP-025 — Audit access | N | N | N | N | A+P+S | C+S | N |
| QMDB-CAP-026 — Security operations | N | N | N | N | C+P+S | A+S | N |
| QMDB-CAP-027 — Support access | N | N | N | N | R | A+P+S | N |
| QMDB-CAP-028 — Break-glass access | N | N | N | N | R | E+S | N |
| QMDB-CAP-029 — Reporting | R | R | R | A | A | R | R |
| QMDB-CAP-030 — Data export | N | C+S | C+S | C+P+S | A+P+S | C+S | C |
| QMDB-CAP-031 — Integration management | N | N | N | C+S | C+P+S | A+S | A |
| QMDB-CAP-032 — Platform operations | N | N | N | N | R | A+P+S | C |

## Approval and separation-of-duties controls

Exact persons, appointing bodies, and quorum remain open under OD-034. The conservative control below is binding until then: the initiator cannot approve their own high-risk action, all named actions require reason/evidence/audit, and access is no broader than the affected scope.

| Sensitive action | Initiating role | Approving role category | Prohibited self-approval | Step-up authentication | Reason requirement | Evidence requirement | Audit requirement | Notification requirement | Expiration or review requirement |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Organization verification | Organization Administrator | Independent organization-governance category | Prohibited | Required | Verification basis and scope | Organization evidence and reviewer conflict declaration | Request, evidence access, decision and status | Applicant and affected governance owner | Review on material evidence/status change |
| Qur’an Text Release activation | Qualified Qur’an Reviewer/importer | Independent qualified Qur’an-review category/quorum | Prohibited | Required | Source, checksum and release rationale | Source manifest, comparison and approval evidence | Every import/review/approval/activation | Qualified reviewers and operations | Review on successor, mismatch or incident |
| Ruleset activation | Rules author or governance member | Independent competition-rules category/quorum | Prohibited | Required | Scope and effective-date rationale | Declarative schema, test vectors and conflict declarations | Proposal, vote/approval, lock and activation | Affected competition officials | Review on successor or identified defect |
| Late-registration exception | Competition Registrar | Independent competition-governance category | Prohibited | Required | Why normal deadline cannot apply | Server deadline, application evidence and policy basis | Request, approval/denial and registration effect | Applicant and registrar | Expires after named registration decision |
| Judge conflict exception | Chief Judge or conflict reviewer | Independent conflict-governance category | Prohibited | Required | Why recusal/replacement is impracticable | Declaration, relationships, risk controls and limits | Every view, decision, assignment and expiration | Judge, panel leadership and oversight | Expires at named panel/performance; mandatory review |
| Score-sheet reopening | Chief Judge | Independent scoring-governance category | Prohibited | Required | Specific defect and requested scope | Submitted version, evidence and result state | Request, approval, reopened/resubmitted versions | Original judge, panel and oversight | Expires if unused; review before aggregation |
| Result finalization | Competition Director | Independent result-governance category | Prohibited | Required | Readiness and exception statement | Quorum, locked sheets, aggregation and appeal-clearance evidence | All checks, approval and final version | Competitors and authorized officials | One-time for named version; correction is separate |
| Final-result correction | Records custodian | Independent result/appeals governance category | Prohibited | Required | Error, impact and correction rationale | Former result, appeal/evidence and downstream impact | Proposal, approval, successor and projection changes | Affected competitors, certificate officer and oversight | Mandatory post-publication impact review |
| Certificate revocation | Certificate Officer | Independent certificate/security category | Prohibited | Required | Revocation basis and public-status wording | Certificate/result/key status and evidence | Request, approval, revocation and verification projection | Recipient, records/security owners | Review if basis/key incident changes |
| Person-profile merge | Privacy/records custodian | Independent identity/privacy category | Prohibited | Required | Duplicate basis and survivor rule | Identity evidence, conflicts and historical links | Proposal, each source, approval, aliases and reversal record | Affected subjects and custodians | Mandatory post-merge review; reversal path required |
| Guardian relationship override | Guardian verifier | Independent child-safety/privacy category | Prohibited | Required | Override purpose and protective basis | Relationship/identity evidence, dispute and subject impact | Request, evidence, approval, scope and expiry | Guardian/subject where safe; child-safety owner | Time/purpose bounded; review on dispute |
| Legacy record publication | Records importer/reviewer | Independent records/attesting-authority category | Prohibited | Required | Provenance classification and publication purpose | Source, mapping, attestation and reconciliation | Batch/item decision and projection | Affected organization/subject where appropriate | Review on dispute or new provenance |
| Restricted Minor media release | Guardian or media publisher | Independent child-safety/moderation category | Prohibited | Required | Specific publication purpose and audience | Current authority/consent, safe derivative, content review | Every approval, access-class change and withdrawal | Guardian/subject and safety team | Expires/rechecks on authority, consent or policy change |
| Break-glass access | Break-Glass Administrator | Security approving category where feasible | Prohibited | Required | Declared emergency and normal-path failure | Incident, exact scope, excluded actions and duration | Activation, every action, expiry/revocation and review | Security/oversight immediately | Automatic expiry and mandatory independent post-use review |
| High-volume data export | Authorized report user | Privacy/security approving category | Prohibited | Required | Purpose, fields, recipients and volume | Query/scope, minimization and handling plan | Request, approval, artifact hash/download/expiry | Approver/security and requester | Short artifact expiry; access rechecked at download |
| Audit-record export | Internal Auditor | Independent audit/security approving category | Prohibited | Required | Engagement purpose and range | Scope, redaction plan and chain verification | Query, approval, export hash/download/expiry | Audit/security oversight | Short expiry and engagement close review |
| Retention-policy override | Privacy or records custodian | Independent privacy/records/legal-review category | Prohibited | Required | Record-level exception and policy dependency | Retention class, holds, official/evidence impact and qualified review | Proposal, approvals, each disposition and later review | Affected custodians/subject where safe | Explicit expiration/review date; no permanent silent override |

## Authority boundary

Global or national oversight is an explicit reporting/review scope, not a bypass around tenant, record-owner, competition-assignment, privacy, Qur’an-review, scoring, or correction policy. Platform operators manage services, security operators manage incidents, and break-glass actors receive temporary emergency grants; none is a normal unrestricted super-administrator.

## Related documents

- [Stakeholders and actors](../domain/stakeholders-and-actors.md)
- [Use-case catalog](P0-B02-use-case-catalog.md)
- [Workflows and state machines](P0-B02-workflows-and-state-machines.md)
- [Open decisions](../project/open-decisions.md)

