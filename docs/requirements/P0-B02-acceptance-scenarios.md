# P0-B02 Acceptance Scenarios

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Baseline | QMDB-BL-001 |
| Batch | QMDB-P0-B02 |
| Document version | 1.0.0 |
| Status | Complete Given–When–Then baseline |
| Last updated | 2026-08-24 |

## Execution rules

Acceptance uses real owning-module boundaries, server-authoritative time/calculation/authorization, isolated test Workspaces, deterministic fixtures, and durable record/audit/event assertions. A scenario passes only when its negative assertions also pass. Exact Ruleset, Qur’an, legal, authority and retention examples require qualified approved fixtures.

## QMDB-AS-001 — Registered individual verifies an account

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-001 |
| Title | Registered individual verifies an account |
| Related Requirements | QMDB-FR-IAM-001, QMDB-FR-IAM-002 |
| Related Use Case | QMDB-UC-001, QMDB-UC-002 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | an unverified Account and one current unexpired single-use verification challenge exist |
| When | the individual submits the matching challenge |
| Then | the Account becomes verified exactly once and an audit/outbox event is durable |
| And | a replay returns the prior safe outcome without creating another Account or verification event |
| Negative Assertions | no account existence, credential, token or other user data is disclosed. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P2. |

## QMDB-AS-002 — Privileged action requires stronger authentication

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-002 |
| Title | Privileged action requires stronger authentication |
| Related Requirements | QMDB-FR-IAM-003, QMDB-FR-IAM-004, QMDB-FR-AUT-001 |
| Related Use Case | QMDB-UC-003, QMDB-UC-009 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | an authenticated actor has a scoped privileged capability but no current step-up assertion |
| When | the actor attempts the sensitive action |
| Then | the server challenges for the policy-required stronger factor and does not commit the action |
| And | a valid current step-up permits only the originally authorized scope and duration |
| Negative Assertions | ordinary password/session possession alone cannot bypass the gate. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P2. |

## QMDB-AS-003 — Workspace identifier cannot expand access

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-003 |
| Title | Workspace identifier cannot expand access |
| Related Requirements | QMDB-FR-TEN-001, QMDB-FR-AUT-002 |
| Related Use Case | QMDB-UC-009 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | the actor is authorized only in Workspace A and a protected record exists in Workspace B |
| When | the actor changes a URL/body identifier from A to B |
| Then | the server denies non-enumeratingly and writes no business state |
| And | security/audit evidence records the mismatch without exposing Workspace B details |
| Negative Assertions | a public identifier, cached page or client-supplied workspace cannot become authorization. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P2. |

## QMDB-AS-004 — Composite relationship rejects cross-workspace reference

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-004 |
| Title | Composite relationship rejects cross-workspace reference |
| Related Requirements | QMDB-FR-TEN-002 |
| Related Use Case | QMDB-UC-007, QMDB-UC-008 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | a tenant-owned parent exists in Workspace A and child creation is attempted in Workspace B |
| When | the command references the parent identifier from A |
| Then | the transaction rejects the composite relationship and creates no partial child |
| And | the denial is safe and traceable |
| Negative Assertions | an application bug or privileged title cannot override workspace integrity. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P2. |

## QMDB-AS-005 — Suspended membership loses authority

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-005 |
| Title | Suspended membership loses authority |
| Related Requirements | QMDB-FR-TEN-002, QMDB-FR-AUT-002, QMDB-FR-SES-002 |
| Related Use Case | QMDB-UC-006, QMDB-UC-063 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | an actor has an active Session and cached UI from an active Membership |
| When | the Membership or owning Organization is suspended |
| Then | the next protected authorization denies and relevant sessions/caches are revoked or invalidated |
| And | former assignments and audit history remain |
| Negative Assertions | cached permissions and token lifetime cannot preserve revoked authority. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P2. |

## QMDB-AS-006 — Person supports multiple contextual roles

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-006 |
| Title | Person supports multiple contextual roles |
| Related Requirements | QMDB-FR-PPL-001, QMDB-FR-PPL-002 |
| Related Use Case | QMDB-UC-012 |
| Priority | High |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | one Person has valid assignments as Competitor and Judge in different nonconflicting contexts |
| When | the authorized actor views and updates one context |
| Then | the system preserves both contextual roles and evaluates each resource independently |
| And | a conflict policy still blocks incompatible same-context actions |
| Negative Assertions | creating one role does not overwrite the Person or another role. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P3. |

## QMDB-AS-007 — Normal administrator cannot edit canonical Qur’an text

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-007 |
| Title | Normal administrator cannot edit canonical Qur’an text |
| Related Requirements | QMDB-FR-QRF-001, QMDB-FR-QRF-004, QMDB-FR-AUT-002 |
| Related Use Case | QMDB-UC-017, QMDB-UC-018 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | a normal Organization Administrator is authenticated |
| When | the actor calls any canonical text mutation path |
| Then | the server denies, creates no release/content change, and records a security-sensitive audit event |
| And | only a qualified governed release workflow remains available |
| Negative Assertions | database/API aliases or platform title cannot grant canonical-text editing. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P4. |

## QMDB-AS-008 — Qur’an release requires approvals

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-008 |
| Title | Qur’an release requires approvals |
| Related Requirements | QMDB-FR-QRF-002, QMDB-FR-QRF-003, QMDB-FR-QRF-004, QMDB-FR-QRF-005 |
| Related Use Case | QMDB-UC-017, QMDB-UC-018 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | an imported release candidate has source and checksum but lacks required independent approvals |
| When | activation is requested |
| Then | the release remains nonactive and the missing governance evidence is identified |
| And | activation succeeds only after checksum comparison, conflict-cleared qualified approval and step-up |
| Negative Assertions | a single importer or normal administrator cannot self-activate. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P4. |

## QMDB-AS-009 — Competition dimensions remain separate

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-009 |
| Title | Competition dimensions remain separate |
| Related Requirements | QMDB-FR-CMP-001, QMDB-FR-CMP-002, QMDB-FR-CMP-003, QMDB-FR-GEO-001 |
| Related Use Case | QMDB-UC-019, QMDB-UC-020, QMDB-UC-021 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | a draft Edition is being configured |
| When | the director supplies organizer, host, competition scope, eligibility geography and represented geography |
| Then | the system stores/validates each dimension separately and publishes only after required review |
| And | historical/effective geography versions remain identifiable |
| Negative Assertions | one dimension cannot be inferred from or silently replace another. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P5. |

## QMDB-AS-010 — Minor consent-dependent action is blocked safely

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-010 |
| Title | Minor consent-dependent action is blocked safely |
| Related Requirements | QMDB-FR-GUA-001, QMDB-FR-GUA-002, QMDB-FR-GUA-003, QMDB-FR-GUA-004, QMDB-FR-PRI-001 |
| Related Use Case | QMDB-UC-014, QMDB-UC-015, QMDB-UC-024 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | a Minor attempts an action requiring purpose-specific Guardian authority and no current valid relationship/consent exists |
| When | submission or publication is requested |
| Then | the system keeps the action pending/private or rejects safely and exposes the required next step only to authorized parties |
| And | relationship disputes or withdrawal apply the more protective state |
| Negative Assertions | client visibility settings or asserted Guardian title cannot bypass the gate. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P5. |

## QMDB-AS-011 — Participant Snapshot remains stable

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-011 |
| Title | Participant Snapshot remains stable |
| Related Requirements | QMDB-FR-REG-004, QMDB-FR-REG-005, QMDB-FR-PPL-002 |
| Related Use Case | QMDB-UC-027 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | an eligible Registration produced a milestone Participant Snapshot |
| When | the Person later changes name or Organization |
| Then | the snapshot used by official competition records remains byte/version stable |
| And | a governed successor/correction may link to the former snapshot without overwriting it |
| Negative Assertions | profile updates cannot retroactively change published or official context. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P5. |

## QMDB-AS-012 — Judge cannot score outside assigned panel

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-012 |
| Title | Judge cannot score outside assigned panel |
| Related Requirements | QMDB-FR-JDG-001, QMDB-FR-JDG-003, QMDB-FR-SCR-001 |
| Related Use Case | QMDB-UC-031, QMDB-UC-035 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | a Judge is authenticated and assigned only to Panel A |
| When | the Judge submits a draft or official sheet for Panel B |
| Then | the server denies non-enumeratingly and creates no Score Sheet for Panel B |
| And | the attempt is audited with assignment/scope context |
| Negative Assertions | knowing a Performance identifier or holding another role cannot expand assignment. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P6. |

## QMDB-AS-013 — Unresolved judge conflict blocks official score

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-013 |
| Title | Unresolved judge conflict blocks official score |
| Related Requirements | QMDB-FR-JDG-002, QMDB-FR-SCR-001, QMDB-FR-SCR-004 |
| Related Use Case | QMDB-UC-032, QMDB-UC-036 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | a Judge assignment has an unresolved conflict declaration |
| When | the Judge submits an official Score Sheet |
| Then | the server rejects submission and keeps the draft/non-authoritative work separate |
| And | cleared or approved-exception status must be current at commit |
| Negative Assertions | client state, cached clearance or Chief Judge title cannot silently clear the conflict. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P6. |

## QMDB-AS-014 — Out-of-range criterion rejected

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-014 |
| Title | Out-of-range criterion rejected |
| Related Requirements | QMDB-FR-RUL-001, QMDB-FR-SCR-002 |
| Related Use Case | QMDB-UC-035, QMDB-UC-036 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | an active declarative Ruleset defines exact criterion bounds |
| When | a submission contains a value outside a bound |
| Then | the server rejects the complete official submission with field-level error and preserves safe draft input |
| And | no Score Sheet Version or aggregation event is created |
| Negative Assertions | client validation or rounded display cannot authorize the value. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P6. |

## QMDB-AS-015 — Manipulated client total ignored

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-015 |
| Title | Manipulated client total ignored |
| Related Requirements | QMDB-FR-SCR-002, QMDB-FR-SCR-003 |
| Related Use Case | QMDB-UC-036 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | valid criterion inputs imply an exact server total different from the client-supplied total |
| When | the Score Sheet is submitted |
| Then | the server calculates from server-owned rules/inputs and flags or rejects the mismatch by policy |
| And | any accepted record stores the server exact decimal result |
| Negative Assertions | the client total cannot become official or alter ranking. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P6. |

## QMDB-AS-016 — Duplicate score submission is idempotent

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-016 |
| Title | Duplicate score submission is idempotent |
| Related Requirements | QMDB-FR-SCR-004 |
| Related Use Case | QMDB-UC-036, QMDB-UC-061 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | one valid score submission committed but its acknowledgement was lost |
| When | the same idempotency key and payload are retried |
| Then | the original authoritative receipt/version is returned |
| And | only one official sheet version and one resulting business effect exist |
| Negative Assertions | a different payload with the same key is not accepted. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P6. |

## QMDB-AS-017 — Stale Score Sheet version rejected

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-017 |
| Title | Stale Score Sheet version rejected |
| Related Requirements | QMDB-FR-SCR-004, QMDB-FR-SCR-005 |
| Related Use Case | QMDB-UC-036, QMDB-UC-037 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | the client holds version N while version N+1/current state exists |
| When | the client submits against N |
| Then | the server rejects with a safe current-version conflict and does not merge or overwrite |
| And | the actor can reload or follow controlled reopening |
| Negative Assertions | last-write-wins cannot alter an official sheet. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P6. |

## QMDB-AS-018 — Submitted sheet cannot be silently overwritten

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-018 |
| Title | Submitted sheet cannot be silently overwritten |
| Related Requirements | QMDB-FR-SCR-004, QMDB-FR-AUD-001 |
| Related Use Case | QMDB-UC-036 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | a Score Sheet Version is submitted/locked |
| When | any normal update/delete command targets its criterion values |
| Then | the owning module rejects the mutation and records the attempt |
| And | change is possible only through approved reopening/new version |
| Negative Assertions | administrator, worker, integration or direct client route cannot rewrite it. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P6. |

## QMDB-AS-019 — Controlled reopening preserves former version

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-019 |
| Title | Controlled reopening preserves former version |
| Related Requirements | QMDB-FR-SCR-005 |
| Related Use Case | QMDB-UC-037 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | a submitted sheet has an eligible defect and independent approval prerequisites are satisfied |
| When | the Chief Judge completes step-up and the separate approver accepts the request |
| Then | a new reopened/version lineage is created and the former submitted version stays immutable |
| And | resubmission explicitly supersedes the former version and is audited |
| Negative Assertions | reopening cannot occur after an incompatible final state or via self-approval. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P6. |

## QMDB-AS-020 — Insufficient judge quorum blocks aggregation

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-020 |
| Title | Insufficient judge quorum blocks aggregation |
| Related Requirements | QMDB-FR-SCR-006, QMDB-FR-RSL-001 |
| Related Use Case | QMDB-UC-038 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | the active Ruleset requires more eligible locked sheets than are available |
| When | aggregation is requested |
| Then | no official panel aggregate/result is created and missing/ineligible inputs are identified to authorized staff |
| And | replacement/review follows the governed assignment/conflict workflow |
| Negative Assertions | the service cannot fill, average or waive quorum without approved declarative policy. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P6. |

## QMDB-AS-021 — Provisional result is visibly provisional

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-021 |
| Title | Provisional result is visibly provisional |
| Related Requirements | QMDB-FR-RSL-002, QMDB-FR-LIV-001, QMDB-FR-SRH-003 |
| Related Use Case | QMDB-UC-039 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | a provisional Result Version is approved for public projection |
| When | a visitor opens live/search/result views |
| Then | each view displays textual PROVISIONAL status, sequence/freshness and no final/certificate implication |
| And | stale or disconnected views are additionally labeled |
| Negative Assertions | color, cache age or position alone cannot communicate finality. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P7. |

## QMDB-AS-022 — Unresolved appeal blocks finalization

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-022 |
| Title | Unresolved appeal blocks finalization |
| Related Requirements | QMDB-FR-APL-001, QMDB-FR-APL-002, QMDB-FR-RSL-003 |
| Related Use Case | QMDB-UC-040, QMDB-UC-041, QMDB-UC-042 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | at least one eligible Appeal is SUBMITTED or UNDER_REVIEW |
| When | an authorized actor requests result finalization |
| Then | the transaction is rejected and no Final Result event/version is created |
| And | the blocking Appeal remains independently reviewable |
| Negative Assertions | privileged approval or notification failure cannot bypass the gate. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P7. |

## QMDB-AS-023 — Result correction preserves former result

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-023 |
| Title | Result correction preserves former result |
| Related Requirements | QMDB-FR-RSL-004, QMDB-FR-REC-001 |
| Related Use Case | QMDB-UC-043 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | a finalized Result requires an approved correction |
| When | the independent governed correction commits |
| Then | a successor Result Version links to the former version and public projections identify current/superseded state |
| And | audit records reason, evidence, approvers and impact |
| Negative Assertions | the former Result cannot be updated or hard-deleted. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P7. |

## QMDB-AS-024 — Corrected result triggers certificate review

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-024 |
| Title | Corrected result triggers certificate review |
| Related Requirements | QMDB-FR-RSL-004, QMDB-FR-CER-001, QMDB-FR-CER-004 |
| Related Use Case | QMDB-UC-043, QMDB-UC-046 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | an issued Certificate depends on a Result that is corrected |
| When | the successor Result changes a certificate-relevant fact |
| Then | the Certificate enters governed review and verification remains truthful about current/superseded/revoked state |
| And | affected recipients/officers receive safe required notice |
| Negative Assertions | the old Certificate cannot silently remain presented as current. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P7. |

## QMDB-AS-025 — Certificate hash mismatch fails verification

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-025 |
| Title | Certificate hash mismatch fails verification |
| Related Requirements | QMDB-FR-CER-002, QMDB-FR-CER-003 |
| Related Use Case | QMDB-UC-045 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | a known Certificate token is paired with altered document bytes |
| When | verification compares canonical hash, signature, key and lifecycle |
| Then | the outcome is INVALID/HASH_MISMATCH with no private source disclosure |
| And | the security correlation is recorded/rate-limited as policy requires |
| Negative Assertions | a valid QR/token alone cannot validate altered content. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P8. |

## QMDB-AS-026 — Revoked certificate remains discoverable as revoked

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-026 |
| Title | Revoked certificate remains discoverable as revoked |
| Related Requirements | QMDB-FR-CER-003, QMDB-FR-CER-004, QMDB-FR-OPS-005 |
| Related Use Case | QMDB-UC-045, QMDB-UC-046 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | an issued Certificate has a governed revocation record |
| When | a visitor verifies its token after download or key rotation |
| Then | the service returns REVOKED with minimal safe facts and historical/key status |
| And | the Certificate and revocation history remain linked |
| Negative Assertions | normal delete, cache or old PDF cannot restore VALID status. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P8. |

## QMDB-AS-027 — Legacy record displays provenance

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-027 |
| Title | Legacy record displays provenance |
| Related Requirements | QMDB-FR-IMP-001, QMDB-FR-REC-001, QMDB-FR-SRH-003 |
| Related Use Case | QMDB-UC-047 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | an imported historical item lacks an approved supporting authority |
| When | the item is viewed in an authorized record/search surface |
| Then | it is labeled Legacy Unverified with source/mapping provenance allowed for that viewer |
| And | later attestation creates a governed classification successor |
| Negative Assertions | import success cannot imply native official status or overwrite a native conflict. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P8. |

## QMDB-AS-028 — Private original cannot be fetched publicly

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-028 |
| Title | Private original cannot be fetched publicly |
| Related Requirements | QMDB-FR-MED-001, QMDB-FR-MED-003 |
| Related Use Case | QMDB-UC-048, QMDB-UC-049 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | a private Evidence Master exists and an approved public derivative may or may not exist |
| When | an unauthenticated request targets the original/object identifier |
| Then | the service denies non-enumeratingly and returns no storage URL/bytes |
| And | approved public access selects only the least-sensitive derivative |
| Negative Assertions | identifier knowledge, copied signed URL after expiry or public Clip cannot expose the original. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P9. |

## QMDB-AS-029 — Malicious upload remains quarantined

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-029 |
| Title | Malicious upload remains quarantined |
| Related Requirements | QMDB-FR-MED-001, QMDB-FR-MED-002 |
| Related Use Case | QMDB-UC-048 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | an authorized upload contains malware or spoofed/malformed bytes |
| When | processing detects the threat |
| Then | the asset stays private/quarantined or REJECTED, no derivative/publication occurs, and security evidence is created |
| And | the uploader receives a safe terminal response |
| Negative Assertions | filename extension, uploader role or manual retry cannot mark it safe. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P9. |

## QMDB-AS-030 — Minor Recitation Clip obeys guardian/visibility rules

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-030 |
| Title | Minor Recitation Clip obeys guardian/visibility rules |
| Related Requirements | QMDB-FR-SOC-001, QMDB-FR-SOC-002, QMDB-FR-PRI-004 |
| Related Use Case | QMDB-UC-050, QMDB-UC-051 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | a Minor creates a processed Clip without all current publication approvals |
| When | public publication or broad interaction is requested |
| Then | the Clip remains private/limited and contact/precise location/comments/messaging follow protective policy |
| And | Guardian history and emergency restriction remain available |
| Negative Assertions | client-selected PUBLIC or reaching adult-age boundary cannot auto-broaden visibility. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P10. |

## QMDB-AS-031 — Blocked user interaction denied

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-031 |
| Title | Blocked user interaction denied |
| Related Requirements | QMDB-FR-SOC-003 |
| Related Use Case | QMDB-UC-052 |
| Priority | High |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | an effective block exists between two accounts under a policy that prohibits interaction |
| When | the blocked actor attempts follow, reaction, comment or controlled share |
| Then | the server denies without disclosing unnecessary blocker details and creates no interaction/counter effect |
| And | reporting remains available through safe abuse controls |
| Negative Assertions | cached content or alternate client route cannot bypass the block. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P10. |

## QMDB-AS-032 — Moderation action creates appealable case

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-032 |
| Title | Moderation action creates appealable case |
| Related Requirements | QMDB-FR-MOD-001, QMDB-FR-MOD-002 |
| Related Use Case | QMDB-UC-053, QMDB-UC-054 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | a conflict-cleared moderator has reviewed preserved evidence |
| When | an eligible restrictive action is decided |
| Then | a scoped action, reason, policy version, notice and appeal window/case are recorded |
| And | an independent appeal may restore/modify without erasing former action |
| Negative Assertions | reporter identity, self-approval or silent permanent deletion cannot occur. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P10. |

## QMDB-AS-033 — Public search protects Minor data

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-033 |
| Title | Public search protects Minor data |
| Related Requirements | QMDB-FR-SRH-001, QMDB-FR-SRH-002, QMDB-FR-PRI-004 |
| Related Use Case | QMDB-UC-055 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | a Minor has restricted profile/record fields and a public projection may exist |
| When | a visitor searches by name/geography/competition filters |
| Then | only allow-listed minimized fields appear and restricted records are non-enumerating |
| And | visibility withdrawal/removal event retires the projection |
| Negative Assertions | contact, precise location, internal IDs or small-group inference cannot appear. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P11. |

## QMDB-AS-034 — Authorized export is audited

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-034 |
| Title | Authorized export is audited |
| Related Requirements | QMDB-FR-RPT-001, QMDB-FR-RPT-002, QMDB-FR-AUD-002 |
| Related Use Case | QMDB-UC-056, QMDB-UC-057 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | an actor has scoped report access but requests a sensitive/high-volume export |
| When | the actor supplies purpose, completes step-up and separate approval |
| Then | only minimized authorized fields/rows enter an expiring protected artifact and generation/download are audited |
| And | authorization is rechecked before generation and download |
| Negative Assertions | revoked scope, self-approval or ordinary report read cannot yield the export. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P11. |

## QMDB-AS-035 — Integration cannot write official score

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-035 |
| Title | Integration cannot write official score |
| Related Requirements | QMDB-FR-INT-001, QMDB-FR-INT-003 |
| Related Use Case | QMDB-UC-061 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | an authenticated integration client has valid non-score scopes |
| When | the client calls any direct/alias/bulk authoritative score mutation |
| Then | the request is forbidden, no source/outbox business event is created, and anomaly evidence is recorded |
| And | offline intake is accepted only through signed reconciliation and normal score APIs |
| Negative Assertions | API key possession, tenant ownership or service role cannot grant score authority. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P12. |

## QMDB-AS-036 — Duplicate outbox delivery is idempotent

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-036 |
| Title | Duplicate outbox delivery is idempotent |
| Related Requirements | QMDB-FR-INT-002, QMDB-FR-AUD-001 |
| Related Use Case | QMDB-UC-062 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | a consumer has committed the effect for an event ID |
| When | the outbox delivers the same event again |
| Then | the consumer returns/records the prior outcome and acknowledges without a second business effect |
| And | ordering/version rules still reject stale successors |
| Negative Assertions | at-least-once delivery cannot duplicate notice, score, certificate, projection transition or integration write. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P12. |

## QMDB-AS-037 — Live updates recover after reconnection

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-037 |
| Title | Live updates recover after reconnection |
| Related Requirements | QMDB-FR-LIV-001, QMDB-FR-LIV-002, QMDB-FR-OPS-003 |
| Related Use Case | QMDB-UC-062 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | a client has processed public sequence N and loses connection while later events commit |
| When | the client reconnects with N |
| Then | the service replays N+1 onward or instructs a verified snapshot rebuild, preserving status/freshness |
| And | gaps stop advancement and display stale state |
| Negative Assertions | reconnect cannot skip/duplicate sequence or treat projection as authoritative. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P7. |

## QMDB-AS-038 — Social degradation does not block scoring

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-038 |
| Title | Social degradation does not block scoring |
| Related Requirements | QMDB-FR-MED-005, QMDB-FR-RPT-003, QMDB-FR-OPS-003 |
| Related Use Case | QMDB-UC-036, QMDB-UC-053 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | social/media/analytics load exceeds its budget during live scoring |
| When | a valid assigned Judge submits an official sheet |
| Then | reserved scoring/auth capacity accepts or prioritizes the score while noncritical work is throttled/queued |
| And | operators see truthful service/queue state |
| Negative Assertions | feed availability, transcoding or reporting cannot be a scoring transaction dependency. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P12. |

## QMDB-AS-039 — Break-glass expires and is reviewed

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-039 |
| Title | Break-glass expires and is reviewed |
| Related Requirements | QMDB-FR-OPS-002, QMDB-FR-AUD-001, QMDB-FR-SEC-002 |
| Related Use Case | QMDB-UC-060 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | an emergency grant is active with exact scope and server expiry |
| When | expiry occurs and the operator attempts another action |
| Then | the command is denied, residual privilege/session is revoked, notifications occur and independent review task opens |
| And | all prior actions remain continuously auditable |
| Negative Assertions | the operator cannot extend/self-approve the grant or hide actions. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P2. |

## QMDB-AS-040 — Offline submissions reconcile once

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-040 |
| Title | Offline submissions reconcile once |
| Related Requirements | QMDB-FR-OFF-001, QMDB-FR-OFF-002 |
| Related Use Case | QMDB-UC-061 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | a registered device holds a signed package with ordered local submissions and connection returns |
| When | the operator synchronizes, including duplicates/out-of-order/stale/conflicting items |
| Then | each item is accepted once through normal score validation, held with reason or rejected, and a reconciliation report is produced |
| And | retries reuse package/item identifiers and preserve central versions |
| Negative Assertions | Edge Node time, direct database access or manual import cannot overwrite an official score. |
| Verification Level | Authorization, integration, security and end-to-end test as applicable. |
| Planned Implementation Phase | P13. |

## QMDB-AS-041 — Keyboard-only core workflow

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-041 |
| Title | Keyboard-only core workflow |
| Related Requirements | QMDB-FR-UXA-001 |
| Related Use Case | QMDB-UC-024, QMDB-UC-036, QMDB-UC-040 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | a user relies only on keyboard controls at supported zoom |
| When | the user completes registration, scoring or Appeal critical path |
| Then | all controls, dialogs, errors and confirmations are reachable in logical order with visible focus |
| And | focus returns/advances predictably after dynamic updates |
| Negative Assertions | pointer, drag, hover or inaccessible custom widget cannot be required. |
| Verification Level | Accessibility test; end-to-end test; manual assistive-technology review. |
| Planned Implementation Phase | P12. |

## QMDB-AS-042 — Screen-reader status announcements

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-042 |
| Title | Screen-reader status announcements |
| Related Requirements | QMDB-FR-UXA-001, QMDB-FR-NTF-002 |
| Related Use Case | QMDB-UC-036, QMDB-UC-062 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | a screen reader is active on an asynchronous workflow |
| When | validation, save, submit, reconnect or status transition occurs |
| Then | a concise programmatic announcement identifies outcome without moving focus or exposing hidden data |
| And | persistent status remains available for review |
| Negative Assertions | color, animation, toast timeout or visual position alone cannot communicate outcome. |
| Verification Level | Accessibility test; end-to-end test; manual assistive-technology review. |
| Planned Implementation Phase | P12. |

## QMDB-AS-043 — RTL interface direction

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-043 |
| Title | RTL interface direction |
| Related Requirements | QMDB-FR-UXA-001, QMDB-FR-QRF-006 |
| Related Use Case | QMDB-UC-018, QMDB-UC-034 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | Arabic locale/content and mixed-direction IDs/numbers are displayed |
| When | the user navigates/forms/scores/verifies records |
| Then | layout direction, reading order, punctuation and isolated identifiers are correct while canonical Qur’an text is unmodified |
| And | user-entered names and numbers retain semantic order |
| Negative Assertions | CSS mirroring cannot alter data, signal direction, numeric value or canonical text. |
| Verification Level | Accessibility test; end-to-end test; manual assistive-technology review. |
| Planned Implementation Phase | P12. |

## QMDB-AS-044 — High-contrast operation

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-044 |
| Title | High-contrast operation |
| Related Requirements | QMDB-FR-UXA-001 |
| Related Use Case | QMDB-UC-003, QMDB-UC-036, QMDB-UC-057 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | forced-colors/high-contrast mode is enabled |
| When | the user operates controls and reviews status/errors |
| Then | focus, boundaries, text, icons and state remain perceivable and actionable |
| And | native/system colors or tested tokens preserve meaning |
| Negative Assertions | background images, subtle shadows or transparent borders cannot be the sole affordance. |
| Verification Level | Accessibility test; end-to-end test; manual assistive-technology review. |
| Planned Implementation Phase | P12. |

## QMDB-AS-045 — Reduced-motion behavior

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-045 |
| Title | Reduced-motion behavior |
| Related Requirements | QMDB-FR-UXA-001 |
| Related Use Case | QMDB-UC-055, QMDB-UC-062 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | the user prefers reduced motion |
| When | live results, progress or navigation updates occur |
| Then | nonessential animation is removed/reduced and status changes remain understandable |
| And | manual pause/static alternative remains available |
| Negative Assertions | motion cannot be required to detect rank/status change or trigger vestibular discomfort. |
| Verification Level | Accessibility test; end-to-end test; manual assistive-technology review. |
| Planned Implementation Phase | P12. |

## QMDB-AS-046 — Non-color-only result status

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-046 |
| Title | Non-color-only result status |
| Related Requirements | QMDB-FR-UXA-001, QMDB-FR-RSL-002, QMDB-FR-SRH-003 |
| Related Use Case | QMDB-UC-039, QMDB-UC-055 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | provisional/final/superseded/revoked statuses appear in tables/cards/charts |
| When | a user reviews them without perceiving color |
| Then | text/icon-with-label and programmatic state identify each status consistently |
| And | legend and detail view explain current authority |
| Negative Assertions | red/green/gold color alone cannot indicate validity, rank change or error. |
| Verification Level | Accessibility test; end-to-end test; manual assistive-technology review. |
| Planned Implementation Phase | P12. |

## QMDB-AS-047 — Low-bandwidth behavior

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-047 |
| Title | Low-bandwidth behavior |
| Related Requirements | QMDB-FR-UXA-002, QMDB-FR-LIV-002, QMDB-FR-MED-005 |
| Related Use Case | QMDB-UC-036, QMDB-UC-055, QMDB-UC-062 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | network is slow/intermittent or media/social assets are unavailable |
| When | the user loads a core official workflow |
| Then | a lightweight/server-rendered or progressively enhanced path exposes authoritative status and supports safe retry/draft/receipt |
| And | media/live projection degrades before scoring/authentication |
| Negative Assertions | heavy feed, video, animation or WebSocket availability cannot be mandatory for official tasks. |
| Verification Level | Accessibility test; end-to-end test; manual assistive-technology review. |
| Planned Implementation Phase | P12. |

## QMDB-AS-048 — Recoverable validation errors

| Field | Specification |
| --- | --- |
| Scenario ID | QMDB-AS-048 |
| Title | Recoverable validation errors |
| Related Requirements | QMDB-FR-UXA-001, QMDB-FR-UXA-002 |
| Related Use Case | QMDB-UC-024, QMDB-UC-036, QMDB-UC-040 |
| Priority | Critical |
| Preconditions | Required fixtures are isolated, versioned and in the states stated below; actor/service identities and clocks are controlled. |
| Given | a multi-field form contains invalid or stale input |
| When | the server rejects the command |
| Then | an accessible error summary links to fields, valid entered values remain, sensitive values are safely handled, and retry guidance/idempotency state is clear |
| And | server time/version/conflict is explained without leaking protected data |
| Negative Assertions | validation cannot erase the whole form, trap focus, duplicate a command or expose another tenant. |
| Verification Level | Accessibility test; end-to-end test; manual assistive-technology review. |
| Planned Implementation Phase | P12. |

## Coverage statement

QMDB-AS-001 through QMDB-AS-040 cover the forty required positive/negative functional assertions. QMDB-AS-041 through QMDB-AS-048 are the eight required accessibility assertions. The traceability matrix may map additional requirements to the closest scenario and shall require requirement-specific evidence in addition to the shared scenario.

## Related documents

- [Functional requirements index](P0-B02-functional-requirements-index.md)
- [Use-case catalog](P0-B02-use-case-catalog.md)
- [Edge cases and failure behavior](P0-B02-edge-cases-and-failure-behavior.md)
- [Traceability matrix](P0-B02-traceability-matrix.md)
