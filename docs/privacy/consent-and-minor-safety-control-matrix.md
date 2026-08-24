# Consent and Minor-Safety Control Matrix

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B03 |
| Document Title | Consent and Minor-Safety Control Matrix |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Child-Safety and Privacy Governance |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed for governance approval; no open value is approved by this document |
| Related Documents | [Privacy NFRs](../requirements/non-functional/02-privacy-data-protection-and-child-safety.md); [Open decisions](../project/open-decisions.md) |

## Purpose

Define conservative technical behavior for Minor/Guardian/Consent actions while age, relationship-verification and legal interpretations remain open.

## Scope

Guardian authority is scoped, current and action-specific. It cannot override safety prohibitions, grant unrestricted access, rewrite official history, or create legal authority not approved by qualified governance.

| Action | Minor Default | Guardian Requirement | Additional Approval | Public Visibility | Withdrawal Behavior | Historical Evidence Behavior | Audit Requirement | Related Requirements |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Create Minor account | Private and restricted | Verified scoped Guardian or approved policy route | Age/status review | None | Account remains restricted pending lawful closure | Audit/account history preserved | Create, status and authority events | QMDB-NFR-CHD-001 |
| Link Guardian | Pending for sensitive authority | Approved verification method | Independent review for dispute/high risk | None | Relationship becomes suspended/ended; no inherited access | Former relationship and evidence retained under policy | Proposal, evidence, decision, change | QMDB-NFR-CHD-001; QMDB-NFR-CHD-003 |
| Apply to competition | Blocked without valid required consent | Current competition-scoped consent | Organizer eligibility review | No public effect | Pending/withdrawn unless official rules require controlled handling | Participant/registration evidence governed | Consent evaluation and application state | QMDB-NFR-CHD-001 |
| Record performance | No recording by default | Recording-purpose consent | Competition/media approval | Private evidence only | Stop future recording/publication; assess retained official evidence | Evidence may remain restricted under policy/hold | Consent version, recorder, purpose | QMDB-NFR-CHD-001; QMDB-NFR-MED-001 |
| Retain official evidence | Restricted | Guardian involvement does not alone authorize deletion | Records/privacy/qualified review | Never public by retention alone | Visibility removed; retention assessed | Governed evidence preserved when required | Hold, purpose, access and disposition | QMDB-NFR-PRI-004; QMDB-NFR-CHD-003 |
| Publish public profile | Hidden | Explicit current public-profile consent where required | Approved field policy | Minimal allowlist | Remove projection/cache | Official account history remains restricted | Consent and field/version audit | QMDB-NFR-PRI-003; QMDB-NFR-CHD-001 |
| Publish competition media | Private | Recording and publication consent | Media/safety approval | Approved derivative only | Unpublish/purge derivatives | Private evidence assessed separately | Approvals, access and purge | QMDB-NFR-MED-001; QMDB-NFR-CHD-002 |
| Publish Recitation Clip | Private/draft | Current Guardian approval for Minor | Moderation/safety checks | Approved Clip fields only | Unpublish and purge caches | Moderation/audit evidence retained per policy | Creation, consent, review, publication | QMDB-NFR-CHD-001; QMDB-NFR-CHD-002 |
| Enable comments | Disabled/restricted | Guardian consent cannot override safety policy | Age/community safety policy | Controlled interaction only | Disable future interaction; retain cases | Safety cases preserved | Policy decision and interactions | QMDB-NFR-CHD-002 |
| Display school | Hidden | Specific approved consent/policy | Privacy/child-safety field review | Only coarse approved representation | Remove public field | Participant Snapshot remains private official context | Field and consent audit | QMDB-NFR-PRI-003 |
| Display represented geography | Coarse/minimized | Approved competition/public-display policy | Small-group/privacy review | No precise location | Remove/suppress public field | Official snapshot retains governed representation | Display basis and suppression | QMDB-NFR-PRI-003 |
| Display precise location | Prohibited | Not overridden by ordinary Guardian consent | Exceptional qualified safety/privacy authority | None | Not applicable; remove immediately if exposed | Security incident evidence retained | Denial/exposure incident audit | QMDB-NFR-CHD-002 |
| Share certificate | Private delivery; subject-controlled share | Guardian involvement where policy requires | Certificate field/privacy review | Minimal verification only | Revoke link/visibility where supported; official status remains | Certificate lifecycle preserved | Share/verification and lifecycle events | QMDB-NFR-PRI-003 |
| Withdraw publication consent | Apply protective state | Authorized subject/Guardian within scope | Conflict/identity review if disputed | Remove affected visibility | Unpublish, purge CDN/search/cache | Official evidence remains restricted if required | Request, authority, propagation and receipt | QMDB-NFR-CHD-003 |
| Remove public media | Unpublish first | Authorized subject/Guardian or safety authority | Hold/evidence review | None after purge | Purge derivatives and access; assess master | Restricted official/moderation evidence governed | Unpublish, purge and disposition | QMDB-NFR-MED-001; QMDB-NFR-CHD-003 |
| Preserve official audit history | Restricted append-oriented | Guardian cannot rewrite history | Audit/privacy/records review | Never public by default | Correct through linked event, not deletion | Former event and correction preserved | Every correction and access | QMDB-NFR-AUD-001; QMDB-NFR-PRI-004 |

## Open-policy boundary

OD-011, OD-012, OD-013, OD-015, OD-016 and the B03 legal/child-safety decisions govern unresolved age, Guardian verification, evidence, retention, public field and escalation policy. Uncertainty applies the more protective state.
