# Record Versioning, Lifecycle, and Deletion

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B04 |
| Document Title | Record Versioning, Lifecycle, and Deletion |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Records, Privacy, and Data Architecture Governance |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed; retention periods and legal interpretations remain open |
| Related Documents | [dictionary](05-table-and-column-data-dictionary.md); [retention register](../privacy/retention-and-deletion-decision-register.md); [open decisions](../project/open-decisions.md) |

## Purpose

Separate mutation, versioning, withdrawal, revocation, supersession, removal, anonymization, archival, retention and restoration so official history and privacy decisions are not collapsed into generic soft deletion.

## Scope

Every logical table has one primary lifecycle category in the dictionary/YAML. Retention duration is not specified here; it remains an accountable policy dependency.

## Lifecycle categories

| Category | Mutation Policy | Versioning Policy | Deletion Policy | Audit Policy | Restoration Policy | Cache Behavior | Backup Behavior | Retention Dependency | Example Tables |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Ephemeral Security Record | Mutable only for consume/revoke/expiry metadata | No historical business version; security event records are separate | Expire/delete after approved security period and hold check | Security event for use/revocation/anomaly | Reissue, not restore plaintext secret | Never public cache | Encrypted backup only if policy requires | OD-037 | verification_challenges; recovery_tokens; auth_sessions |
| Mutable Current Record | Optimistic controlled update | Increment version on material change | Archive/anonymize only through approved lifecycle; generic soft delete only if explicit | Before/after metadata and actor for significant change | Restore latest valid version or governed correction | Invalidate by version | Standard encrypted backup | OD-014; OD-037 | user_accounts; person_profiles; organizations |
| Versioned Configuration | New version for meaning/effective change | Immutable version rows with effective range/checksum | Supersede/archive; never repurpose referenced code | Approval, activation and supersession | Reactivate only through approval | Cache by version | Retain referenced versions | OD-014 | ruleset_versions; configuration_versions; privacy_notice_versions |
| Historical Snapshot | Immutable after capture | Append annotated corrected snapshot only when policy allows | No normal hard delete; anonymization assessed against official purpose/hold | Capture/correction/access audit | Restore exact checksum/version | Cache derivative only | Preserve with official lineage | OD-014; OD-037 | participant_snapshots; result_snapshots |
| Append-Only Event | No update/delete in normal path | Sequence/hash/correlation identifies immutable event | Archive under policy; correction is a linked event | Event is audit evidence; chain verification as applicable | Replay/reconcile, never edit | No authoritative cache mutation | Append-oriented backup/archive | OD-037 | audit_events; consent_events; appeal_events; outbox_events |
| Immutable Official Version | No mutation after submission/lock | Monotonic lineage version and checksum | No normal hard delete | Submission/approval/signature/correction audit | Restore exact version; quarantine mismatch | Read cache by immutable identity | Preserve all referenced versions | OD-014 | score_sheet_versions; ruleset_versions |
| Supersedable Official Record | Only pre-final state changes; final correction appends successor | Former and successor linked, acyclic | Withdraw/revoke/supersede/archive; no erasure | Approval/evidence/public notice/audit | Restore lineage and verification status | Invalidate by current-lineage version | Preserve former/current records | OD-014 | result_snapshots; certificates |
| Revocable Record | State transition may revoke future authority | Former grant/issuance remains | Revoke/expire; no historical erasure | Grant/use/revoke/review audit | Regrant as new record | Fail closed on stale cache | Retain per security/records policy | OD-037 | support_access_grants; break_glass_grants; certificates |
| Rebuildable Projection | Replace/purge/rebuild from source version | Projection schema/source cursor only | May purge after authoritative source remains | Rebuild/freshness/publication evidence | Full rebuild and reconcile | It is the cache/read model | Recreated, not relied on for restore truth | OD-070 | public_profiles; live_scoreboard_projections; public_record_projections |
| External Evidence Record | Metadata immutable; review state separate | Hash/source/version preserve evidence | Archive/cold-store/hold; delete only by governed disposition | Access, validation, decision and disposition | Restore by hash and object inventory | Never unrestricted cache | Encrypted object/database backup as applicable | OD-013; OD-015; OD-037 | identity_evidence; record_evidence; media_storage_objects |
| Archived Record | Read-only except restore/disposition metadata | Original identity/version retained | Cold-store or archive schema only after approved design | Archive/restore/access verification | Restore through controlled rehearsal | Not hot cached | Integrity-checked archive | OD-060 | future approved archive representations |
| Anonymizable Personal Record | Only fields approved by privacy/records decision transform | Event links original subject under restricted evidence as approved | Anonymize after identity, hold and official-purpose assessment | Request, search scope, transformation, verification | Restore only when policy/key design explicitly permits | Purge restricted derivatives | Backup propagation follows approved policy | OD-041; OD-037 | person_profiles; public_profiles; notification history |

## Required record lifecycles

| Record | Table | Lifecycle | Mutation/version rule | Allowed disposition | Historical/audit rule |
| --- | --- | --- | --- | --- | --- |
| User Account | `user_accounts` (QMDB-TBL-016) | Mutable Current Record | Use optimistic version where concurrent mutation is material. | Governed archival/anonymization; generic soft deletion only where explicitly permitted. | Record significant state, authority and correction actions in Audit Events. |
| Session | `auth_sessions` (QMDB-TBL-021) | Mutable Current Record | Use optimistic version where concurrent mutation is material. | Governed archival/anonymization; generic soft deletion only where explicitly permitted. | Record significant state, authority and correction actions in Audit Events. |
| Device | `devices` (QMDB-TBL-022) | Mutable Current Record | Use optimistic version where concurrent mutation is material. | Governed archival/anonymization; generic soft deletion only where explicitly permitted. | Record significant state, authority and correction actions in Audit Events. |
| Person Profile | `person_profiles` (QMDB-TBL-031) | Mutable Current Record | Use optimistic version where concurrent mutation is material. | Governed archival/anonymization; generic soft deletion only where explicitly permitted. | Record significant state, authority and correction actions in Audit Events. |
| Public Profile | `public_profiles` (QMDB-TBL-032) | Rebuildable Projection | Track authoritative source version and rebuild deterministically. | May be purged and rebuilt from authoritative source. | Record rebuild source/version and suppress restricted fields. |
| Organization | `organizations` (QMDB-TBL-053) | Mutable Current Record | Use optimistic version where concurrent mutation is material. | Governed archival/anonymization; generic soft deletion only where explicitly permitted. | Record significant state, authority and correction actions in Audit Events. |
| Organization Verification | `organization_verification_cases` (QMDB-TBL-057) | Mutable Current Record | Use optimistic version where concurrent mutation is material. | Governed archival/anonymization; generic soft deletion only where explicitly permitted. | Record significant state, authority and correction actions in Audit Events. |
| Guardian Relationship | `guardian_relationships` (QMDB-TBL-040) | Mutable Current Record | Use optimistic version where concurrent mutation is material. | Governed archival/anonymization; generic soft deletion only where explicitly permitted. | Record significant state, authority and correction actions in Audit Events. |
| Consent | `consent_records` (QMDB-TBL-041) | Mutable Current Record | Use optimistic version where concurrent mutation is material. | Governed archival/anonymization; generic soft deletion only where explicitly permitted. | Record significant state, authority and correction actions in Audit Events. |
| Quran Text Release | `quran_text_releases` (QMDB-TBL-061) | Versioned Configuration | Append a new immutable version; never overwrite historical facts. | No normal hard deletion; revoke, supersede, archive or governed anonymization only. | Record significant state, authority and correction actions in Audit Events. |
| Competition Edition | `competition_editions` (QMDB-TBL-077) | Mutable Current Record | Use optimistic version where concurrent mutation is material. | Governed archival/anonymization; generic soft deletion only where explicitly permitted. | Record significant state, authority and correction actions in Audit Events. |
| Ruleset Version | `ruleset_versions` (QMDB-TBL-088) | Immutable Official Version | Append a new immutable version; never overwrite historical facts. | No normal hard deletion; revoke, supersede, archive or governed anonymization only. | Record significant state, authority and correction actions in Audit Events. |
| Registration | `registrations` (QMDB-TBL-096) | Mutable Current Record | Use optimistic version where concurrent mutation is material. | Governed archival/anonymization; generic soft deletion only where explicitly permitted. | Record significant state, authority and correction actions in Audit Events. |
| Participant Snapshot | `participant_snapshots` (QMDB-TBL-103) | Immutable Official Version | Append a new immutable version; never overwrite historical facts. | No normal hard deletion; revoke, supersede, archive or governed anonymization only. | Record significant state, authority and correction actions in Audit Events. |
| Judge Assignment | `judge_assignments` (QMDB-TBL-114) | Mutable Current Record | Use optimistic version where concurrent mutation is material. | Governed archival/anonymization; generic soft deletion only where explicitly permitted. | Record significant state, authority and correction actions in Audit Events. |
| Performance | `performances` (QMDB-TBL-120) | Mutable Current Record | Use optimistic version where concurrent mutation is material. | Governed archival/anonymization; generic soft deletion only where explicitly permitted. | Record significant state, authority and correction actions in Audit Events. |
| Score Sheet | `score_sheets` (QMDB-TBL-124) | Mutable Current Record | Use optimistic version where concurrent mutation is material. | Governed archival/anonymization; generic soft deletion only where explicitly permitted. | Record significant state, authority and correction actions in Audit Events. |
| Score Sheet Version | `score_sheet_versions` (QMDB-TBL-125) | Immutable Official Version | Append a new immutable version; never overwrite historical facts. | No normal hard deletion; revoke, supersede, archive or governed anonymization only. | Record significant state, authority and correction actions in Audit Events. |
| Panel Aggregation | `panel_aggregations` (QMDB-TBL-133) | Mutable Current Record | Use optimistic version where concurrent mutation is material. | Governed archival/anonymization; generic soft deletion only where explicitly permitted. | Record significant state, authority and correction actions in Audit Events. |
| Result | `result_snapshots` (QMDB-TBL-138) | Immutable Official Version | Append a new immutable version; never overwrite historical facts. | No normal hard deletion; revoke, supersede, archive or governed anonymization only. | Record significant state, authority and correction actions in Audit Events. |
| Appeal | `appeals` (QMDB-TBL-145) | Mutable Current Record | Use optimistic version where concurrent mutation is material. | Governed archival/anonymization; generic soft deletion only where explicitly permitted. | Record significant state, authority and correction actions in Audit Events. |
| Certificate | `certificates` (QMDB-TBL-153) | Supersedable Official Record | Append a new immutable version; never overwrite historical facts. | No normal hard deletion; revoke, supersede, archive or governed anonymization only. | Record significant state, authority and correction actions in Audit Events. |
| Legacy Record | `competition_records` (QMDB-TBL-158) | Mutable Current Record | Use optimistic version where concurrent mutation is material. | Governed archival/anonymization; generic soft deletion only where explicitly permitted. | Record significant state, authority and correction actions in Audit Events. |
| Media Asset | `media_assets` (QMDB-TBL-169) | Mutable Current Record | Use optimistic version where concurrent mutation is material. | Governed archival/anonymization; generic soft deletion only where explicitly permitted. | Record significant state, authority and correction actions in Audit Events. |
| Recitation Clip | `recitation_clips` (QMDB-TBL-182) | Mutable Current Record | Use optimistic version where concurrent mutation is material. | Governed archival/anonymization; generic soft deletion only where explicitly permitted. | Record significant state, authority and correction actions in Audit Events. |
| Comment | `comments` (QMDB-TBL-190) | Mutable Current Record | Use optimistic version where concurrent mutation is material. | Governed archival/anonymization; generic soft deletion only where explicitly permitted. | Record significant state, authority and correction actions in Audit Events. |
| Moderation Case | `moderation_cases` (QMDB-TBL-196) | Mutable Current Record | Use optimistic version where concurrent mutation is material. | Governed archival/anonymization; generic soft deletion only where explicitly permitted. | Record significant state, authority and correction actions in Audit Events. |
| Privacy Request | `privacy_requests` (QMDB-TBL-219) | Mutable Current Record | Use optimistic version where concurrent mutation is material. | Governed archival/anonymization; generic soft deletion only where explicitly permitted. | Record significant state, authority and correction actions in Audit Events. |
| Audit Event | `audit_events` (QMDB-TBL-226) | Append-Only Event | Append only; corrections are linked events. | No normal hard deletion; revoke, supersede, archive or governed anonymization only. | Record significant state, authority and correction actions in Audit Events. |
| Outbox Event | `outbox_events` (QMDB-TBL-229) | Append-Only Event | Append only; corrections are linked events. | No normal hard deletion; revoke, supersede, archive or governed anonymization only. | Record significant state, authority and correction actions in Audit Events. |
| Idempotency Record | `idempotency_records` (QMDB-TBL-230) | Mutable Current Record | Use optimistic version where concurrent mutation is material. | Governed archival/anonymization; generic soft deletion only where explicitly permitted. | Record significant state, authority and correction actions in Audit Events. |
| Projection | `public_record_projections` (QMDB-TBL-210) | Rebuildable Projection | Track authoritative source version and rebuild deterministically. | May be purged and rebuilt from authoritative source. | Record rebuild source/version and suppress restricted fields. |
| Offline Submission | `offline_submission_events` (QMDB-TBL-246) | Append-Only Event | Append only; corrections are linked events. | No normal hard deletion; revoke, supersede, archive or governed anonymization only. | Record significant state, authority and correction actions in Audit Events. |

## Historical preservation scenarios

### Profile change after competition

Person names, school, Organization, geography and public visibility may change in current records. The Participant Snapshot keeps captured display identity, affiliation/geography children, Minor state, version, checksum and capture time. Performance, Score Sheet, Result, Certificate and public record lineage continue to reference that snapshot; no cascade updates historical facts.

### Score reopening

A submitted `score_sheet_versions` row is immutable. `score_reopening_requests` identifies the original version, reason, requester and evidence; `score_reopening_approvals` preserves independent decisions. Approval advances the Score Sheet lineage to a new version number. Aggregation inputs identify exact former/replacement versions, calculation traces explain impact, and Audit/Outbox events accompany transitions.

### Result correction

A final `result_snapshots` row remains verifiable. `result_corrections`, approval/finalization evidence and `result_supersession_links` create an acyclic successor. Public projections publish correction status without deleting former truth. Certificate eligibility/impact is evaluated explicitly; no automatic overwrite occurs.

### Certificate correction

The original Certificate, document hash, signature metadata, issuance and verification events remain. A revocation or supersession record states reason/authority, and a distinct Certificate receives its own serial/code/hash/signature. Verification reports the queried certificate’s lifecycle and current successor reference without excess personal data.

### Consent withdrawal

Withdrawal appends a Consent Event and changes current authority. Publication gates re-evaluate; public profile/media/social projections unpublish and purge caches/CDN derivatives. Private competition evidence is separately assessed against purpose, policy and Data Hold. Guardian/Consent/Audit history is not destroyed.

### Organization suspension

Current Membership/delegation/public authority is restricted. Existing Participant Snapshots, Results, Certificates and Record Provenance retain the Organization identity captured at the event. Operational exceptions/decisions are explicit and audited.

### Geography change

Administrative Area name/code/history records retain validity intervals, aliases and replacement links. New workflows use current areas; historical Participant Snapshot geography keeps its captured representation. Replacement graphs are cycle-checked.

### Quran release correction

The original release/source/checksum/approvals and every Competition/Ruleset/Performance reference remain. A corrected release receives a new release/version/checksum and supersession history. Normal administrators cannot update canonical Ayah text in place.

### Offline synchronization

Assignment Package, Edge Node, device sequence, expected resource version, event ID/idempotency hash, receipt time, conflict and reconciliation outcome remain. Duplicate/out-of-order events return the prior outcome or enter a conflict hold; they never overwrite central official state.

## Disposition decision sequence

1. Authenticate and authorize the requester/decision owner.
2. Resolve subject, purpose, authoritative records, projections, backups, object evidence and external processors.
3. Check Guardian/Minor rules, open Appeal/dispute, Data Hold, security/records purpose and qualified policy.
4. Choose the accurate action: correct, withdraw, revoke, supersede, unpublish/remove, anonymize, archive or approved hard delete.
5. Execute authoritative state and Outbox/Audit evidence atomically where applicable.
6. Purge/rebuild projections, caches, CDN and authorized exports; verify provider outcomes.
7. Reconcile hashes/counts/visibility and issue a minimized receipt.
8. Propagate backup/archive consequences under approved policy rather than falsely claiming immediate physical erasure.

## Related documents

- [Data classification, ownership and lineage](10-data-classification-ownership-and-lineage.md)
- [Volume, archival and partitioning](12-volume-archival-and-partitioning-strategy.md)
- [Retention and deletion register](../privacy/retention-and-deletion-decision-register.md)

## P3-B02 lifecycle addendum

People records are not hard deleted. Name, geography, role, progress and guardianship transitions retain historical
rows or status evidence, while current mutations use optimistic versions. Retention, Person retirement, deceased
handling, legal correction, merge and deletion/anonymization policy remain open decisions; this foundation does not
invent them.

## P3-B05 claim, duplicate, and alias lifecycle addendum

Pairings transition from active to consumed, revoked, expired, or attempts-exhausted; claims transition through
pending acceptance, accepted, declined, revoked, or expired with immutable event history. Verification assertions are
active or revoked historical facts. Duplicate cases move only through explicit report, consent, review, dismissal,
block, or resolved states. Resolution does not hard-delete a Person: it retires the duplicate source, records an
immutable alias, and retains case history. Alias chains/cycles, Account merge, self-link transfer, automatic name
overwrite, and independently committed participant changes are prohibited.
