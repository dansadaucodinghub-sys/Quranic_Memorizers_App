# P0-B03 Non-Functional Acceptance Scenarios

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B03 |
| Document Title | P0-B03 Non-Functional Acceptance Scenarios |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Engineering Quality, Security, Privacy, Accessibility and Reliability Governance |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed verification baseline |
| Related Documents | [B03 NFR index](P0-B03-non-functional-requirements-index.md); [Traceability](P0-B03-traceability-matrix.md); [Security verification](../security/security-verification-matrix.md) |

## Purpose

Define positive and negative evidence needed to accept B03 security, tenancy, privacy/safety, accessibility, resilience, recovery and release behavior.

## Execution rules

A scenario passes only in the stated representative environment with attributable evidence. Compilation, automated accessibility scanning alone, backup-job success without restore, or policy text without runtime proof is insufficient. Parameter-dependent assertions use approved register values when available and otherwise verify instrumentation plus conservative safe failure.

## QMDB-NFAS-001 — SQL injection cannot alter query structure

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-001 |
| Title | SQL injection cannot alter query structure |
| Related Requirements | QMDB-NFR-SEC-002; QMDB-NFR-DAT-001 |
| Related Threats or Risks | QMDB-THR-029; QMDB-RSK-001 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P2 |
| Preconditions | PDO native prepares and representative endpoints exist |
| Given | An attacker supplies SQL metacharacters in every supported value type |
| When | The request is validated and parameter-bound |
| Then | The query structure is unchanged, no extra row/action occurs, and a safe error/result is returned |
| Negative Assertions | No string-concatenated query, stack trace, or partial state change |
| Evidence Required | Static query review, DB/audit trace, negative test output |
| Verification Method | SAST plus integration/penetration test |
| Planned Phase | P2 |

## QMDB-NFAS-002 — Cross-site scripting is safely encoded

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-002 |
| Title | Cross-site scripting is safely encoded |
| Related Requirements | QMDB-NFR-SEC-002 |
| Related Threats or Risks | QMDB-THR-030; QMDB-RSK-002 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P2 |
| Preconditions | Rendered contexts and CSP policy exist |
| Given | Stored and reflected payloads enter text, attribute, URL and script-adjacent contexts |
| When | The content is rendered |
| Then | Payload remains inert and CSP records no executable bypass |
| Negative Assertions | No unsafe innerHTML/template bypass or Session exposure |
| Evidence Required | Browser DOM, CSP report and test fixtures |
| Verification Method | Browser security test |
| Planned Phase | P2 |

## QMDB-NFAS-003 — CSRF rejects invalid state change

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-003 |
| Title | CSRF rejects invalid state change |
| Related Requirements | QMDB-NFR-SEC-002 |
| Related Threats or Risks | QMDB-THR-031; QMDB-RSK-002 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P2 |
| Preconditions | Authenticated Session and protected state-changing route exist |
| Given | A cross-origin/invalid-token request is submitted |
| When | QMDB validates origin/token/method policy |
| Then | The request is rejected with no state change |
| Negative Assertions | No GET mutation or SameSite-only unsupported assumption |
| Evidence Required | Request/audit/DB before-after evidence |
| Verification Method | Integration and browser test |
| Planned Phase | P2 |

## QMDB-NFAS-004 — Public identifier does not authorize access

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-004 |
| Title | Public identifier does not authorize access |
| Related Requirements | QMDB-NFR-SEC-001; QMDB-NFR-DAT-002 |
| Related Threats or Risks | QMDB-THR-006; QMDB-RSK-001 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P2 |
| Preconditions | A valid public ID and unauthorized actor exist |
| Given | The actor requests protected details or mutation using the ID |
| When | Policy mediation executes |
| Then | Access is denied without protected fact disclosure |
| Negative Assertions | Identifier possession cannot grant read/write authority |
| Evidence Required | Authorization logs and DB state comparison |
| Verification Method | Authorization test |
| Planned Phase | P2 |

## QMDB-NFAS-005 — Workspace identifier cannot expose another Workspace

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-005 |
| Title | Workspace identifier cannot expose another Workspace |
| Related Requirements | QMDB-NFR-TEN-001 |
| Related Threats or Risks | QMDB-THR-005; QMDB-RSK-001 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P2 |
| Preconditions | Two Workspaces and scoped member exist |
| Given | Path/body/header/cache/job context is changed to the other Workspace |
| When | Repository and relationship scopes execute |
| Then | All reads/writes/exports/jobs are denied or empty scope-safe |
| Negative Assertions | No cache, projection or background cross-over |
| Evidence Required | Tenant test matrix, SQL scope, cache/job keys |
| Verification Method | Tenant-isolation integration test |
| Planned Phase | P2 |

## QMDB-NFAS-006 — Removed membership loses access

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-006 |
| Title | Removed membership loses access |
| Related Requirements | QMDB-NFR-SEC-001; QMDB-NFR-TEN-001 |
| Related Threats or Risks | QMDB-THR-007; QMDB-RSK-002 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P2 |
| Preconditions | User has active Session and membership |
| Given | Membership is removed while Session remains |
| When | Next protected operation re-evaluates authority |
| Then | Operation is denied and stale grants/caches are invalidated |
| Negative Assertions | No grace via Session, API key or job |
| Evidence Required | Membership/audit/cache invalidation evidence |
| Verification Method | Authorization lifecycle test |
| Planned Phase | P2 |

## QMDB-NFAS-007 — Privileged action requires stronger authentication

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-007 |
| Title | Privileged action requires stronger authentication |
| Related Requirements | QMDB-NFR-IAM-001; QMDB-NFR-SEC-001 |
| Related Threats or Risks | QMDB-THR-004; QMDB-RSK-002 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P2 |
| Preconditions | Authenticated user lacks current step-up assurance |
| Given | User initiates privileged action |
| When | Step-up policy evaluates |
| Then | Action pauses until approved stronger authentication succeeds |
| Negative Assertions | No role title or recovery path bypass |
| Evidence Required | Assurance/audit and browser flow evidence |
| Verification Method | Authentication/authorization E2E |
| Planned Phase | P2 |

## QMDB-NFAS-008 — Reset token is single-use

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-008 |
| Title | Reset token is single-use |
| Related Requirements | QMDB-NFR-IAM-001 |
| Related Threats or Risks | QMDB-THR-003; QMDB-RSK-002 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P2 |
| Preconditions | Valid reset token exists |
| Given | Token is successfully redeemed then replayed |
| When | Recovery verifier checks consumed state |
| Then | Replay fails non-enumerating and cannot alter credentials |
| Negative Assertions | No race permits two redemptions |
| Evidence Required | Concurrency, token and recovery audit |
| Verification Method | Security/concurrency test |
| Planned Phase | P2 |

## QMDB-NFAS-009 — Revoked Session is unusable

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-009 |
| Title | Revoked Session is unusable |
| Related Requirements | QMDB-NFR-IAM-002 |
| Related Threats or Risks | QMDB-THR-002; QMDB-RSK-002 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P2 |
| Preconditions | Active Session exists on two devices |
| Given | Subject/security operator revokes one Session |
| When | Revoked Session makes next request |
| Then | Request fails and cannot refresh/rotate into validity |
| Negative Assertions | No protected read/write after revocation |
| Evidence Required | Session store/audit/browser evidence |
| Verification Method | End-to-end revocation test |
| Planned Phase | P2 |

## QMDB-NFAS-010 — Integration cannot write official score

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-010 |
| Title | Integration cannot write official score |
| Related Requirements | QMDB-NFR-API-001; QMDB-NFR-DAT-002 |
| Related Threats or Risks | QMDB-THR-026; QMDB-RSK-003 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P12 |
| Preconditions | Scoped API client exists |
| Given | Client calls documented and alternate/bulk routes with score payload |
| When | API/policy/domain boundaries execute |
| Then | All direct official-score writes are absent or denied |
| Negative Assertions | No webhook, import or client scope bypass |
| Evidence Required | OpenAPI, route inventory, audit/DB evidence |
| Verification Method | Contract and security test |
| Planned Phase | P12 |

## QMDB-NFAS-011 — Normal administrator cannot alter canonical Qur’an text

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-011 |
| Title | Normal administrator cannot alter canonical Qur’an text |
| Related Requirements | QMDB-NFR-DAT-002; QMDB-NFR-L10-001 |
| Related Threats or Risks | QMDB-THR-017; QMDB-RSK-007 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P4 |
| Preconditions | Normal administrative account and active release exist |
| Given | Account attempts direct edit or unapproved replacement |
| When | Qur’an release governance executes |
| Then | Action is denied; active checksum/text unchanged |
| Negative Assertions | No database/admin UI alternate edit path |
| Evidence Required | Role tests, checksum/diff, audit |
| Verification Method | Authorization and domain-integrity test |
| Planned Phase | P4 |

## QMDB-NFAS-012 — Malicious upload remains quarantined

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-012 |
| Title | Malicious upload remains quarantined |
| Related Requirements | QMDB-NFR-MED-001 |
| Related Threats or Risks | QMDB-THR-018; QMDB-RSK-011 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P9 |
| Preconditions | Upload permission and malicious/spoof fixture exist |
| Given | Fixture is uploaded and processed |
| When | Type/malware/codec/resource controls execute |
| Then | Asset remains private quarantined and no derivative publishes |
| Negative Assertions | No worker DB secret, arbitrary egress or public URL |
| Evidence Required | Object policy, scan/worker/audit evidence |
| Verification Method | Media security integration test |
| Planned Phase | P9 |

## QMDB-NFAS-013 — Private original is not public

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-013 |
| Title | Private original is not public |
| Related Requirements | QMDB-NFR-MED-001 |
| Related Threats or Risks | QMDB-THR-020; QMDB-RSK-009 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P9 |
| Preconditions | Private original and public derivative exist |
| Given | Anonymous user guesses/copies original/object/CDN URL |
| When | Origin/access policy evaluates |
| Then | Original is denied non-enumerating; only approved derivative is available |
| Negative Assertions | No cache/referrer/log leak grants access |
| Evidence Required | Object/CDN access and cache evidence |
| Verification Method | Access-control/penetration test |
| Planned Phase | P9 |

## QMDB-NFAS-014 — Duplicate webhook is idempotent

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-014 |
| Title | Duplicate webhook is idempotent |
| Related Requirements | QMDB-NFR-API-001 |
| Related Threats or Risks | QMDB-THR-027; QMDB-RSK-022 |
| Priority | High |
| Environment | CI and isolated staging or exercise environment representative of P12 |
| Preconditions | Valid signed webhook and delivery record exist |
| Given | Same timestamped event is delivered repeatedly/out of order |
| When | Signature, timestamp and idempotency checks run |
| Then | At most one accepted business effect is recorded |
| Negative Assertions | No retry creates duplicate authoritative action |
| Evidence Required | Webhook ledger and DB/audit comparison |
| Verification Method | Integration/idempotency test |
| Planned Phase | P12 |

## QMDB-NFAS-015 — Duplicate outbox event has one effect

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-015 |
| Title | Duplicate outbox event has one effect |
| Related Requirements | QMDB-NFR-SCL-001; QMDB-NFR-RES-002 |
| Related Threats or Risks | QMDB-THR-040; QMDB-RSK-018 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P7 |
| Preconditions | Committed outbox event and idempotent consumer exist |
| Given | Event is delivered repeatedly including crash-after-commit |
| When | Consumer resumes |
| Then | One business effect exists and duplicates are auditable |
| Negative Assertions | No second notification/score/result action where uniqueness required |
| Evidence Required | Outbox/consumer/audit/reconciliation |
| Verification Method | Queue concurrency/recovery test |
| Planned Phase | P7 |

## QMDB-NFAS-016 — Secrets absent from production logs

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-016 |
| Title | Secrets absent from production logs |
| Related Requirements | QMDB-NFR-OBS-001; QMDB-NFR-INF-002 |
| Related Threats or Risks | QMDB-THR-035; QMDB-RSK-006 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P12 |
| Preconditions | Representative errors and secret canaries exist |
| Given | Authentication, recovery, API, media, DB and signing paths fail |
| When | Logging/tracing executes |
| Then | Canaries and prohibited fields are absent or irreversibly redacted |
| Negative Assertions | No password, Session, token, MFA/key/credential/full restricted body |
| Evidence Required | Central logs/traces and secret-scan report |
| Verification Method | Negative fixture and secret scan |
| Planned Phase | P12 |

## QMDB-NFAS-017 — Signing-key absence blocks issuance

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-017 |
| Title | Signing-key absence blocks issuance |
| Related Requirements | QMDB-NFR-CRY-001 |
| Related Threats or Risks | QMDB-THR-014; QMDB-RSK-005 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P8 |
| Preconditions | Eligible certificate request exists and key service is unavailable/revoked |
| Given | Issuance runs |
| When | Cryptographic availability/validity checks fail |
| Then | No certificate is issued; request remains recoverable and alert fires |
| Negative Assertions | No unsigned/fallback/private-key-in-DB issuance |
| Evidence Required | Certificate DB/artifact/audit/alert evidence |
| Verification Method | Cryptographic failure test |
| Planned Phase | P8 |

## QMDB-NFAS-018 — Audit-chain failure alerts

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-018 |
| Title | Audit-chain failure alerts |
| Related Requirements | QMDB-NFR-AUD-001; QMDB-NFR-OBS-003 |
| Related Threats or Risks | QMDB-THR-015; QMDB-RSK-015 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P12 |
| Preconditions | Hash-linked events and external checkpoint exist |
| Given | Event/hash/checkpoint is altered/deleted |
| When | Verifier runs |
| Then | Mismatch is detected, alert/incident starts and governed sensitive work is contained |
| Negative Assertions | No silent repair/pass or evidence destruction |
| Evidence Required | Verifier, alert, incident and checkpoint evidence |
| Verification Method | Audit tamper/incident test |
| Planned Phase | P12 |

## QMDB-NFAS-019 — Every tenant query is scoped

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-019 |
| Title | Every tenant query is scoped |
| Related Requirements | QMDB-NFR-TEN-001 |
| Related Threats or Risks | QMDB-THR-005; QMDB-RSK-001 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P2 |
| Preconditions | Tenant-owned repository inventory exists |
| Given | Automated architecture/repository tests inspect and execute queries |
| When | Scope policies and SQL run |
| Then | Each read/write includes trusted Workspace scope |
| Negative Assertions | No optional/default/unbounded tenant query |
| Evidence Required | Repository inventory, SQL traces, tests |
| Verification Method | Architecture and integration test |
| Planned Phase | P2 |

## QMDB-NFAS-020 — Cross-Workspace relationship is rejected

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-020 |
| Title | Cross-Workspace relationship is rejected |
| Related Requirements | QMDB-NFR-TEN-001; QMDB-NFR-DAT-001 |
| Related Threats or Risks | QMDB-THR-005; QMDB-RSK-001 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P3 |
| Preconditions | Records in different Workspaces exist |
| Given | Application and direct migration test attempt relationship |
| When | Composite integrity/policy executes |
| Then | Relationship fails with no partial write |
| Negative Assertions | No ordinary foreign-key disabling workaround |
| Evidence Required | Constraint metadata, DB error, transaction state |
| Verification Method | MySQL constraint/integration test |
| Planned Phase | P3 |

## QMDB-NFAS-021 — Cache keys do not mix Workspace data

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-021 |
| Title | Cache keys do not mix Workspace data |
| Related Requirements | QMDB-NFR-TEN-001; QMDB-NFR-SEC-003 |
| Related Threats or Risks | QMDB-THR-005; QMDB-RSK-017 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P7 |
| Preconditions | Identical local IDs exist in two Workspaces |
| Given | Cache fill/read/invalidate occurs concurrently |
| When | Tenant-aware key policy executes |
| Then | Each Workspace sees only its value and invalidation |
| Negative Assertions | No shared key, fallback or stale leak |
| Evidence Required | Key inspection and returned values |
| Verification Method | Cache isolation/concurrency test |
| Planned Phase | P7 |

## QMDB-NFAS-022 — Background job requires tenant context

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-022 |
| Title | Background job requires tenant context |
| Related Requirements | QMDB-NFR-TEN-001; QMDB-NFR-SCL-001 |
| Related Threats or Risks | QMDB-THR-005; QMDB-RSK-001 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P7 |
| Preconditions | Tenant-owned job exists |
| Given | Job lacks/contains invalid/stale Workspace context |
| When | Worker validates envelope/authority |
| Then | Job is rejected/quarantined with no tenant effect |
| Negative Assertions | No global/default Workspace execution |
| Evidence Required | Queue envelope, dead letter, DB/audit |
| Verification Method | Worker isolation test |
| Planned Phase | P7 |

## QMDB-NFAS-023 — Export cannot exceed actor scope

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-023 |
| Title | Export cannot exceed actor scope |
| Related Requirements | QMDB-NFR-TEN-001; QMDB-NFR-PRI-002 |
| Related Threats or Risks | QMDB-THR-008; QMDB-RSK-001 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P11 |
| Preconditions | Scoped exporter and mixed-scope data exist |
| Given | Exporter requests broad fields/rows then authority is changed |
| When | Approval/step-up/scope recheck executes |
| Then | Export contains only current approved scope or is cancelled |
| Negative Assertions | No stale approval, predictable URL or unexpired overbroad download |
| Evidence Required | Export manifest, approval, delivery/audit |
| Verification Method | Authorization/privacy race test |
| Planned Phase | P11 |

## QMDB-NFAS-024 — Minor profile hides restricted data

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-024 |
| Title | Minor profile hides restricted data |
| Related Requirements | QMDB-NFR-PRI-003; QMDB-NFR-CHD-001 |
| Related Threats or Risks | QMDB-THR-021; QMDB-RSK-008 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P3 |
| Preconditions | Minor/uncertain profile exists |
| Given | Public/search/result/profile endpoints are queried |
| When | Minor and field-allowlist policies run |
| Then | Only approved minimal fields display |
| Negative Assertions | No contact, Guardian, security, private link or unapproved school data |
| Evidence Required | Public schemas/responses/cache/search |
| Verification Method | Privacy/child-safety test |
| Planned Phase | P3 |

## QMDB-NFAS-025 — Precise Minor location is not public

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-025 |
| Title | Precise Minor location is not public |
| Related Requirements | QMDB-NFR-PRI-003; QMDB-NFR-CHD-002 |
| Related Threats or Risks | QMDB-THR-021; QMDB-RSK-008 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P10 |
| Preconditions | Minor has precise/coarse geography |
| Given | Public profile/result/media/report is requested |
| When | Public-minimization/suppression evaluates |
| Then | Precise location and identifying small-group inference are absent |
| Negative Assertions | No metadata, image EXIF, URL or aggregate leakage |
| Evidence Required | Endpoint/media metadata/report evidence |
| Verification Method | Privacy/media/inference test |
| Planned Phase | P10 |

## QMDB-NFAS-026 — Guardian consent checked before publication

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-026 |
| Title | Guardian consent checked before publication |
| Related Requirements | QMDB-NFR-CHD-001 |
| Related Threats or Risks | QMDB-THR-022; QMDB-RSK-010 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P10 |
| Preconditions | Minor asset and consent states exist |
| Given | Publication is attempted with absent/stale/wrong-purpose/disputed consent |
| When | Relationship, purpose/version/status checks run |
| Then | Publication remains private and gives safe action guidance |
| Negative Assertions | No alternate worker/API/cache publication |
| Evidence Required | Consent/publish/audit/object/projection evidence |
| Verification Method | Authorization/state/race test |
| Planned Phase | P10 |

## QMDB-NFAS-027 — Consent withdrawal removes public visibility

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-027 |
| Title | Consent withdrawal removes public visibility |
| Related Requirements | QMDB-NFR-CHD-003; QMDB-NFR-PRI-004 |
| Related Threats or Risks | QMDB-THR-022; QMDB-RSK-010 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P10 |
| Preconditions | Published Minor content and official evidence exist |
| Given | Authorized consent withdrawal completes |
| When | Publication/cache/search/CDN propagation runs |
| Then | Public visibility disappears and governed official evidence remains restricted |
| Negative Assertions | No hard deletion of required evidence or stale public copy |
| Evidence Required | Withdrawal receipt, purge probes, retention decision |
| Verification Method | End-to-end privacy/recovery test |
| Planned Phase | P10 |

## QMDB-NFAS-028 — Support sees minimum data

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-028 |
| Title | Support sees minimum data |
| Related Requirements | QMDB-NFR-PRI-002; QMDB-NFR-INF-002 |
| Related Threats or Risks | QMDB-THR-046; QMDB-RSK-014 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P2 |
| Preconditions | Ticket-bound support grant exists |
| Given | Operator opens subject/resource outside field/time/purpose scope |
| When | JIT field/resource policy runs |
| Then | Only approved fields display; outside scope denied and audited |
| Negative Assertions | No impersonation, password/MFA reset bypass or broad search |
| Evidence Required | Grant, UI/API response, expiry/audit |
| Verification Method | Support authorization/abuse test |
| Planned Phase | P2 |

## QMDB-NFAS-029 — Certificate verification is minimal

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-029 |
| Title | Certificate verification is minimal |
| Related Requirements | QMDB-NFR-PRI-003; QMDB-NFR-CRY-001 |
| Related Threats or Risks | QMDB-THR-013; QMDB-RSK-005 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P8 |
| Preconditions | Valid, revoked and unknown certificates exist |
| Given | Public verifier queries each |
| When | Signature/status and output allowlist run |
| Then | Only approved identity/result/status fields display accurately |
| Negative Assertions | No contact, precise location, private evidence or existence leak |
| Evidence Required | OpenAPI responses, crypto/status, privacy review |
| Verification Method | Contract/cryptographic/privacy test |
| Planned Phase | P8 |

## QMDB-NFAS-030 — Privacy export is securely delivered

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-030 |
| Title | Privacy export is securely delivered |
| Related Requirements | QMDB-NFR-PRI-004; QMDB-NFR-PRI-002 |
| Related Threats or Risks | QMDB-THR-020; QMDB-RSK-027 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P12 |
| Preconditions | Verified approved privacy case exists |
| Given | Export is generated, downloaded, expired and replayed |
| When | Scope, encryption, delivery token, expiry and audit run |
| Then | Only subject-approved data reaches authorized recipient once/within policy |
| Negative Assertions | No public URL, wrong subject, hidden source or indefinite token |
| Evidence Required | Manifest, encryption, delivery receipt, expiry/audit |
| Verification Method | Privacy/security E2E |
| Planned Phase | P12 |

## QMDB-NFAS-031 — Disputed Guardian relationship restricts actions

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-031 |
| Title | Disputed Guardian relationship restricts actions |
| Related Requirements | QMDB-NFR-CHD-001; QMDB-NFR-CHD-003 |
| Related Threats or Risks | QMDB-THR-022; QMDB-RSK-010 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P3 |
| Preconditions | Active relationship has a dispute event |
| Given | Guardian tries consent, export, publication or profile change |
| When | Protective transition and policies run |
| Then | Sensitive actions are blocked/reviewed; child visibility stays protective |
| Negative Assertions | No stale Session/cache authority or automatic transfer |
| Evidence Required | State/audit/cache and negative action evidence |
| Verification Method | Lifecycle/authorization test |
| Planned Phase | P3 |

## QMDB-NFAS-032 — Keyboard-only critical workflow

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-032 |
| Title | Keyboard-only critical workflow |
| Related Requirements | QMDB-NFR-ACC-001; QMDB-NFR-ACC-002 |
| Related Threats or Risks | QMDB-RSK-029 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P12 |
| Preconditions | Representative critical workflow exists |
| Given | User completes it using keyboard only |
| When | Focus, controls, review, confirmation and receipt operate |
| Then | Workflow completes without trap/pointer-only action |
| Negative Assertions | No hidden focus, drag-only, hover-only or timing barrier |
| Evidence Required | Manual steps/video/issue log |
| Verification Method | Manual keyboard E2E |
| Planned Phase | P12 |

## QMDB-NFAS-033 — Screen reader announces validation errors

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-033 |
| Title | Screen reader announces validation errors |
| Related Requirements | QMDB-NFR-ACC-001 |
| Related Threats or Risks | QMDB-RSK-029 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P12 |
| Preconditions | Form has multiple invalid fields |
| Given | Screen-reader user submits then corrects |
| When | Error summary, field links and status announcements run |
| Then | Errors/purpose/focus are announced and corrections preserve data |
| Negative Assertions | No color-only/unlabelled/generic error |
| Evidence Required | AT transcript, DOM/accessibility tree |
| Verification Method | Manual screen-reader test |
| Planned Phase | P12 |

## QMDB-NFAS-034 — Live scores announced without interruption

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-034 |
| Title | Live scores announced without interruption |
| Related Requirements | QMDB-NFR-ACC-003 |
| Related Threats or Risks | QMDB-RSK-029 |
| Priority | High |
| Environment | CI and isolated staging or exercise environment representative of P7 |
| Preconditions | Live scoreboard produces sequenced changes |
| Given | Screen-reader user views/pauses updates |
| When | Polite live-region and user controls operate |
| Then | Meaningful status is available without focus theft or excessive speech |
| Negative Assertions | No per-cell assertive flood or lost current-state access |
| Evidence Required | AT transcript, event logs, controls |
| Verification Method | Manual screen-reader/live test |
| Planned Phase | P7 |

## QMDB-NFAS-035 — Status understandable without color

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-035 |
| Title | Status understandable without color |
| Related Requirements | QMDB-NFR-ACC-001 |
| Related Threats or Risks | QMDB-RSK-029 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P12 |
| Preconditions | Provisional/final/invalid/error statuses exist |
| Given | Color/contrast is unavailable or altered |
| When | Text/icon/semantics render |
| Then | Every status remains distinguishable |
| Negative Assertions | No meaning conveyed by hue alone |
| Evidence Required | Screenshots, DOM, contrast/non-color review |
| Verification Method | Automated plus manual visual test |
| Planned Phase | P12 |

## QMDB-NFAS-036 — Judge workflow supports zoom and reflow

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-036 |
| Title | Judge workflow supports zoom and reflow |
| Related Requirements | QMDB-NFR-ACC-001; QMDB-NFR-ACC-002 |
| Related Threats or Risks | QMDB-RSK-029 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P6 |
| Preconditions | Judge scoring page and target viewport exist |
| Given | User zooms/reflows/text-spaces per WCAG test conditions |
| When | Layout and controls adapt |
| Then | All criteria, totals, errors, review and submit remain usable |
| Negative Assertions | No clipped/overlapping/two-axis critical content or hidden action |
| Evidence Required | Screenshots, DOM, manual record |
| Verification Method | Manual responsive accessibility test |
| Planned Phase | P6 |

## QMDB-NFAS-037 — RTL preserves reading and focus order

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-037 |
| Title | RTL preserves reading and focus order |
| Related Requirements | QMDB-NFR-L10-001 |
| Related Threats or Risks | QMDB-RSK-030 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P12 |
| Preconditions | Mixed Arabic/Latin workflow exists |
| Given | Arabic/RTL locale is activated and keyboard/AT navigation runs |
| When | Direction and bidi isolation apply |
| Then | Visual, DOM reading, focus and copied values remain logical |
| Negative Assertions | No canonical text/score reversal or directional-icon error |
| Evidence Required | RTL screenshots, DOM/focus/AT/copy test |
| Verification Method | Manual RTL/linguistic accessibility test |
| Planned Phase | P12 |

## QMDB-NFAS-038 — Reduced motion suppresses non-essential animation

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-038 |
| Title | Reduced motion suppresses non-essential animation |
| Related Requirements | QMDB-NFR-ACC-001 |
| Related Threats or Risks | QMDB-RSK-029 |
| Priority | High |
| Environment | CI and isolated staging or exercise environment representative of P12 |
| Preconditions | UI has optional transitions/live movement |
| Given | User enables reduced-motion preference |
| When | Presentation adapts |
| Then | Non-essential movement stops while state remains clear |
| Negative Assertions | No essential information lost or forced autoplay |
| Evidence Required | Preference/browser capture |
| Verification Method | Automated media query plus manual test |
| Planned Phase | P12 |

## QMDB-NFAS-039 — Session expiration warning is accessible

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-039 |
| Title | Session expiration warning is accessible |
| Related Requirements | QMDB-NFR-ACC-001; QMDB-NFR-IAM-002 |
| Related Threats or Risks | QMDB-RSK-029; QMDB-RSK-002 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P2 |
| Preconditions | Session approaches approved idle/absolute boundary |
| Given | Keyboard/screen-reader user is active |
| When | Accessible warning and permitted extension/reauth flow appears |
| Then | User understands consequence and can act without lost safe draft |
| Negative Assertions | No silent expiry, focus trap or security bypass |
| Evidence Required | AT/keyboard/session/draft evidence |
| Verification Method | Manual E2E plus Session test |
| Planned Phase | P2 |

## QMDB-NFAS-040 — Critical confirmation is recoverable

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-040 |
| Title | Critical confirmation is recoverable |
| Related Requirements | QMDB-NFR-ACC-002 |
| Related Threats or Risks | QMDB-RSK-029 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P12 |
| Preconditions | Sensitive action has entered data |
| Given | User reviews, triggers validation error, cancels/retries, then confirms |
| When | Review/error/receipt controls run |
| Then | No ambiguous or duplicate action; safe inputs persist |
| Negative Assertions | No pointer/color dependence or irreversible hidden default |
| Evidence Required | UI/audit/idempotency/receipt evidence |
| Verification Method | Accessibility and negative E2E |
| Planned Phase | P12 |

## QMDB-NFAS-041 — Social load does not block scoring

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-041 |
| Title | Social load does not block scoring |
| Related Requirements | QMDB-NFR-PER-001; QMDB-NFR-RES-001 |
| Related Threats or Risks | QMDB-THR-044; QMDB-RSK-024 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P10 |
| Preconditions | Tier isolation and approved parameters exist |
| Given | Social/feed/comment traffic saturates Tier 4 |
| When | Admission/backpressure/load shedding run |
| Then | Score save/submit remains within approved Tier 1 behavior |
| Negative Assertions | No shared queue/pool exhaustion or integrity shortcut |
| Evidence Required | Mixed-load SLIs, queue/DB metrics |
| Verification Method | Load/stress/degradation test |
| Planned Phase | P10 |

## QMDB-NFAS-042 — Media backlog does not block competition

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-042 |
| Title | Media backlog does not block competition |
| Related Requirements | QMDB-NFR-PER-001; QMDB-NFR-SCL-001 |
| Related Threats or Risks | QMDB-THR-044; QMDB-RSK-011 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P9 |
| Preconditions | Media and competition queues/pools exist |
| Given | Transcoding backlog exceeds approved age/depth |
| When | Media backpressure and workload isolation run |
| Then | Competition operations continue; media is delayed safely |
| Negative Assertions | No unscanned publish or worker borrowing that starves scoring |
| Evidence Required | Queue/resource/Tier SLI evidence |
| Verification Method | Mixed workload/soak test |
| Planned Phase | P9 |

## QMDB-NFAS-043 — Redis failure cannot change scores

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-043 |
| Title | Redis failure cannot change scores |
| Related Requirements | QMDB-NFR-SEC-003; QMDB-NFR-RES-001 |
| Related Threats or Risks | QMDB-THR-038; QMDB-RSK-017 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P7 |
| Preconditions | Authoritative score and Redis cache/stream exist |
| Given | Redis is stale, unavailable or manipulated |
| When | Scoring/read paths use authoritative rules |
| Then | Official score remains unchanged; derived state is stale-labelled/rebuilt |
| Negative Assertions | No cache-as-truth or fail-open mutation |
| Evidence Required | MySQL/Redis before-after and recovery evidence |
| Verification Method | Fault injection/security test |
| Planned Phase | P7 |

## QMDB-NFAS-044 — Duplicate queue processing is safe

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-044 |
| Title | Duplicate queue processing is safe |
| Related Requirements | QMDB-NFR-SCL-001; QMDB-NFR-RES-002 |
| Related Threats or Risks | QMDB-THR-040; QMDB-RSK-018 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P7 |
| Preconditions | Retryable sensitive job exists |
| Given | Job is concurrently delivered multiple times |
| When | Idempotency and transaction checks run |
| Then | One effect and one authoritative version result |
| Negative Assertions | No duplicate certificate, score, consent or notification where uniqueness applies |
| Evidence Required | Consumer/DB/audit/reconciliation |
| Verification Method | Concurrency/idempotency test |
| Planned Phase | P7 |

## QMDB-NFAS-045 — Failed worker resumes without duplicates

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-045 |
| Title | Failed worker resumes without duplicates |
| Related Requirements | QMDB-NFR-SCL-001; QMDB-NFR-RES-002 |
| Related Threats or Risks | QMDB-THR-039; QMDB-RSK-018 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P7 |
| Preconditions | Worker can crash at each commit/ack boundary |
| Given | Fault is injected then worker restarts |
| When | Retry/backoff/idempotency/dead-letter policy runs |
| Then | Work completes once or remains controlled failed |
| Negative Assertions | No infinite retry, poison starvation or duplicate official record |
| Evidence Required | Fault timeline, retry counters, DB/audit |
| Verification Method | Crash recovery test |
| Planned Phase | P7 |

## QMDB-NFAS-046 — Live clients reconnect by sequence

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-046 |
| Title | Live clients reconnect by sequence |
| Related Requirements | QMDB-NFR-UXR-001; QMDB-NFR-RES-001 |
| Related Threats or Risks | QMDB-RSK-017 |
| Priority | High |
| Environment | CI and isolated staging or exercise environment representative of P7 |
| Preconditions | Sequenced live events and disconnected client exist |
| Given | Client reconnects with last accepted sequence |
| When | SSE/replay window/snapshot policy runs |
| Then | Client catches up/detects gap and shows current/stale state |
| Negative Assertions | No duplicate display as new official event or invented gap fill |
| Evidence Required | Client/event sequence trace |
| Verification Method | Network-loss E2E |
| Planned Phase | P7 |

## QMDB-NFAS-047 — Stale replica not used for finalization

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-047 |
| Title | Stale replica not used for finalization |
| Related Requirements | QMDB-NFR-DAT-001; QMDB-NFR-PER-003 |
| Related Threats or Risks | QMDB-RSK-016 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P7 |
| Preconditions | Replica intentionally lags behind primary |
| Given | Finalization/authoritative transition is invoked |
| When | Data-source policy checks run |
| Then | Primary current data is used or operation stops |
| Negative Assertions | No replica/cache fallback for official decision |
| Evidence Required | DB connection/position/audit evidence |
| Verification Method | Replica-lag integration test |
| Planned Phase | P7 |

## QMDB-NFAS-048 — Oversized report becomes controlled job

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-048 |
| Title | Oversized report becomes controlled job |
| Related Requirements | QMDB-NFR-PER-002; QMDB-NFR-PER-003 |
| Related Threats or Risks | QMDB-RSK-024 |
| Priority | High |
| Environment | CI and isolated staging or exercise environment representative of P11 |
| Preconditions | Report exceeds approved synchronous work parameter |
| Given | Authorized user requests it |
| When | Work estimator and queue policy run |
| Then | Bounded asynchronous job/receipt is created or request safely rejected |
| Negative Assertions | No unbounded primary query or scope snapshot bypass |
| Evidence Required | Query plan, job, receipt, scope recheck |
| Verification Method | Performance/authorization test |
| Planned Phase | P11 |

## QMDB-NFAS-049 — Certificate verification degrades safely

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-049 |
| Title | Certificate verification degrades safely |
| Related Requirements | QMDB-NFR-RES-001; QMDB-NFR-CRY-001 |
| Related Threats or Risks | QMDB-RSK-005; QMDB-RSK-032 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P8 |
| Preconditions | Verifier dependency/CDN/signing status partially fails |
| Given | Public verification is requested |
| When | Continuity policy validates trust/freshness |
| Then | Approved verified snapshot or explicit unavailable/invalid state displays |
| Negative Assertions | No unsigned, stale-current or overbroad output |
| Evidence Required | Verifier/cache/status/crypto evidence |
| Verification Method | Fault/load/cryptographic test |
| Planned Phase | P8 |

## QMDB-NFAS-050 — MySQL recovery restores consistent records

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-050 |
| Title | MySQL recovery restores consistent records |
| Related Requirements | QMDB-NFR-DRC-001; QMDB-NFR-DAT-001 |
| Related Threats or Risks | QMDB-RSK-016; QMDB-RSK-020 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P12 |
| Preconditions | Known recovery point includes scores/results/audit/outbox |
| Given | Restore/PITR and reconciliation run |
| When | Constraints, hashes, versions and event positions validate |
| Then | Authoritative records are mutually consistent before service approval |
| Negative Assertions | No foreign-key disable shortcut, lost audit link or duplicate replay |
| Evidence Required | Restore manifest, SQL checks, hashes, reconciliation |
| Verification Method | Full recovery exercise |
| Planned Phase | P12 |

## QMDB-NFAS-051 — Backup restore reconstructs authoritative truth

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-051 |
| Title | Backup restore reconstructs authoritative truth |
| Related Requirements | QMDB-NFR-DRC-001 |
| Related Threats or Risks | QMDB-RSK-019; QMDB-RSK-020 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P12 |
| Preconditions | Selected encrypted backup and recovery environment exist |
| Given | Authorized operator restores without production shortcuts |
| When | Runbook and custody controls execute |
| Then | Authoritative MySQL/private objects/config/key metadata are verified |
| Negative Assertions | No declaration based solely on backup job success |
| Evidence Required | Backup/restore/custody/hash report |
| Verification Method | Restore test |
| Planned Phase | P12 |

## QMDB-NFAS-052 — Projections rebuild from authoritative data

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-052 |
| Title | Projections rebuild from authoritative data |
| Related Requirements | QMDB-NFR-DRC-001; QMDB-NFR-DAT-002 |
| Related Threats or Risks | QMDB-RSK-020 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P12 |
| Preconditions | Redis/search/read models are empty/corrupt |
| Given | Rebuild runs from MySQL/outbox |
| When | Idempotent projector and privacy/status rules execute |
| Then | Projection matches approved authoritative versions and omissions |
| Negative Assertions | No projection becomes source or restores removed visibility |
| Evidence Required | Counts/hashes/samples/privacy purge evidence |
| Verification Method | Rebuild/reconciliation test |
| Planned Phase | P12 |

## QMDB-NFAS-053 — Audit checkpoints validate after recovery

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-053 |
| Title | Audit checkpoints validate after recovery |
| Related Requirements | QMDB-NFR-DRC-001; QMDB-NFR-AUD-001 |
| Related Threats or Risks | QMDB-RSK-020; QMDB-RSK-028 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P12 |
| Preconditions | Audit events/backups/external checkpoints restored |
| Given | Chain verifier runs across recovery point |
| When | Hash/checkpoint relationships are recomputed |
| Then | All expected checkpoints match or incident blocks approval |
| Negative Assertions | No regenerated history/checkpoint substitution |
| Evidence Required | Verifier and external receipt evidence |
| Verification Method | Audit recovery test |
| Planned Phase | P12 |

## QMDB-NFAS-054 — Recovery reconciliation finds inconsistencies

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-054 |
| Title | Recovery reconciliation finds inconsistencies |
| Related Requirements | QMDB-NFR-DRC-001 |
| Related Threats or Risks | QMDB-RSK-020 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P12 |
| Preconditions | Known projection/object inconsistency is seeded |
| Given | Post-recovery reconciliation runs |
| When | Record/object/event/projection comparisons execute |
| Then | Mismatch is reported and affected service remains unapproved |
| Negative Assertions | No silent auto-fix that rewrites authoritative data |
| Evidence Required | Seed manifest, findings, correction/approval evidence |
| Verification Method | Recovery negative test |
| Planned Phase | P12 |

## QMDB-NFAS-055 — Offline submissions reconcile once

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-055 |
| Title | Offline submissions reconcile once |
| Related Requirements | QMDB-NFR-RES-003 |
| Related Threats or Risks | QMDB-THR-041; QMDB-RSK-026 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P13 |
| Preconditions | Signed package contains duplicate/out-of-order/conflicting events |
| Given | Connection returns and sync retries |
| When | Central device/package/version/sequence/idempotency checks run |
| Then | Each valid event is accepted once; invalid/conflict is quarantined |
| Negative Assertions | No central overwrite, replay or stale Ruleset acceptance |
| Evidence Required | Local/central logs and reconciliation report |
| Verification Method | Offline security/recovery test |
| Planned Phase | P13 |

## QMDB-NFAS-056 — DR exercise produces actions

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-056 |
| Title | DR exercise produces actions |
| Related Requirements | QMDB-NFR-DRC-002; QMDB-NFR-INC-001 |
| Related Threats or Risks | QMDB-RSK-020; QMDB-RSK-031 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P13 |
| Preconditions | Approved DR scenario/roles/environment exist |
| Given | Exercise declares, restores, communicates and closes |
| When | Incident/recovery lifecycle executes |
| Then | Evidence records parameter measures, failures, owners and due actions |
| Negative Assertions | No paper-only success or closure without verification |
| Evidence Required | Exercise timeline, RTO/RPO measures, action tracker |
| Verification Method | DR/tabletop/full exercise |
| Planned Phase | P13 |

## QMDB-NFAS-057 — Vulnerable dependency blocks release

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-057 |
| Title | Vulnerable dependency blocks release |
| Related Requirements | QMDB-NFR-SUP-001; QMDB-NFR-REL-001 |
| Related Threats or Risks | QMDB-THR-034; QMDB-RSK-022 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P1 |
| Preconditions | Dependency advisory exceeds approved severity policy |
| Given | CI evaluates lock/SBOM/reachability |
| When | Release gate runs |
| Then | Promotion blocks until remediation or authorized expiring risk decision |
| Negative Assertions | No audit suppression, ad hoc production install or missing SBOM update |
| Evidence Required | Audit/SBOM/gate/decision evidence |
| Verification Method | Pipeline/advisory simulation |
| Planned Phase | P1 |

## QMDB-NFAS-058 — Detected secret blocks release

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-058 |
| Title | Detected secret blocks release |
| Related Requirements | QMDB-NFR-INF-002; QMDB-NFR-REL-001 |
| Related Threats or Risks | QMDB-THR-035; QMDB-RSK-006 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P1 |
| Preconditions | Secret canary exists in source/build fixture |
| Given | CI scans commit/artifact |
| When | Secret gate runs |
| Then | Build/promotion fails and incident/rotation path is invoked |
| Negative Assertions | No masking-only success or unrotated exposed credential |
| Evidence Required | Scan/gate/revocation evidence |
| Verification Method | Pipeline secret test |
| Planned Phase | P1 |

## QMDB-NFAS-059 — Invalid migration blocks deployment

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-059 |
| Title | Invalid migration blocks deployment |
| Related Requirements | QMDB-NFR-MNT-002; QMDB-NFR-REL-001 |
| Related Threats or Risks | QMDB-RSK-016; QMDB-RSK-022 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P1 |
| Preconditions | Migration violates syntax/constraint/compatibility/rollback rule |
| Given | CI/staging validation executes |
| When | Migration gate runs |
| Then | Deployment stops before production change |
| Negative Assertions | No manual production edit or foreign-key disabling |
| Evidence Required | Dry run, schema diff, gate/audit |
| Verification Method | Migration pipeline test |
| Planned Phase | P1 |

## QMDB-NFAS-060 — Failed health check stops or rolls back

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-060 |
| Title | Failed health check stops or rolls back |
| Related Requirements | QMDB-NFR-SUP-002; QMDB-NFR-REL-001 |
| Related Threats or Risks | QMDB-RSK-016; QMDB-RSK-022 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P12 |
| Preconditions | Incremental deployment artifact fails readiness/critical synthetic check |
| Given | Post-deploy verification runs |
| When | Rollback trigger evaluates |
| Then | Promotion stops or prior artifact is restored |
| Negative Assertions | No continued rollout, direct patch or false healthy signal |
| Evidence Required | Deployment/health/rollback timeline |
| Verification Method | Deployment exercise |
| Planned Phase | P12 |

## QMDB-NFAS-061 — Production deployment is attributable

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-061 |
| Title | Production deployment is attributable |
| Related Requirements | QMDB-NFR-SUP-002; QMDB-NFR-REL-001 |
| Related Threats or Risks | QMDB-RSK-022 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P12 |
| Preconditions | Approved source, CI identity and artifact exist |
| Given | Artifact is promoted to production |
| When | Attestation/deployment audit validates |
| Then | Deployed checksum maps to reviewed source, lock/SBOM and actor |
| Negative Assertions | No rebuild/manual file change/shared deployment account |
| Evidence Required | Attestation, hashes, approvals, deployment audit |
| Verification Method | Artifact/deployment verification |
| Planned Phase | P12 |

## QMDB-NFAS-062 — Emergency change gets post-review

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-062 |
| Title | Emergency change gets post-review |
| Related Requirements | QMDB-NFR-REL-001; QMDB-NFR-INC-001 |
| Related Threats or Risks | QMDB-RSK-015; QMDB-RSK-031 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P12 |
| Preconditions | Declared emergency authorizes time-bound change |
| Given | Change is deployed/reverted and incident stabilizes |
| When | Emergency audit/review lifecycle runs |
| Then | Scope, evidence, outcome, risk and corrective actions are independently reviewed |
| Negative Assertions | No permanent bypass, missing rollback or silent config drift |
| Evidence Required | Incident/change/deployment/post-review record |
| Verification Method | Emergency change exercise |
| Planned Phase | P12 |

## QMDB-NFAS-063 — Result correction preserves prior official version

| Field | Value |
| --- | --- |
| Scenario ID | QMDB-NFAS-063 |
| Title | Result correction preserves prior official version |
| Related Requirements | QMDB-NFR-SEC-001; QMDB-NFR-DAT-002; QMDB-NFR-AUD-001 |
| Related Threats or Risks | QMDB-THR-012; QMDB-RSK-004 |
| Priority | Critical |
| Environment | CI and isolated staging or exercise environment representative of P7 |
| Preconditions | Final result and authorized independent correction case exist |
| Given | Correction is proposed, approved and applied |
| When | Versioned result/certificate/audit controls run |
| Then | A successor version is created and prior result remains immutable/discoverable by governed history |
| Negative Assertions | No in-place overwrite, self-approval or silent public/certificate inconsistency |
| Evidence Required | Before/after versions, approvals, audit, projection/certificate reconciliation |
| Verification Method | Authorization/concurrency/integrity E2E |
| Planned Phase | P7 |

## Coverage statement

QMDB-NFAS-001 through QMDB-NFAS-062 provide every mandated B03 scenario in order; QMDB-NFAS-063 closes the critical result-correction risk explicitly. All scenarios require safe negative assertions and evidence, not narrative confirmation.

