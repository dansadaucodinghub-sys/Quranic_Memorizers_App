# Data Classification and Handling

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B03 |
| Document Title | Data Classification and Handling |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Privacy, Security and Records Governance |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed for governance approval; no open value is approved by this document |
| Related Documents | [Privacy NFRs](../requirements/non-functional/02-privacy-data-protection-and-child-safety.md); [Retention decisions](retention-and-deletion-decision-register.md) |

## Purpose

Provide a small stable classification scheme and handling rules for QMDB data.

## Scope and authority

Classification governs storage, transmission, logs, public display, export, support, backup, incident and disposition. A lower-class derivative does not declassify its source; explicit approved derivation is required.

## QMDB-DCL-001 — Public Data

| Field | Rule |
| --- | --- |
| Classification ID | QMDB-DCL-001 |
| Permitted storage | Approved public endpoints/CDN derivatives |
| Permitted transmission | TLS |
| Encryption requirements | TLS |
| Logging rules | No secrets; safe operational logs only |
| Public-display rules | Permitted only by field allowlist and current status/consent |
| Export rules | Public export with provenance and rate control |
| Support-access rules | No privileged support need |
| Backup rules | Integrity-protected backup |
| Retention decision dependency | Policy dependent |
| Deletion or anonymization considerations | Remove/supersede stale projections |
| Incident severity | Low unless integrity or child inference impact |
| Example records | Approved public results, minimal certificate status, approved public profile/derivative |

## QMDB-DCL-002 — Internal Operational Data

| Field | Rule |
| --- | --- |
| Classification ID | QMDB-DCL-002 |
| Permitted storage | Controlled application/database/telemetry stores |
| Permitted transmission | TLS; at-rest per platform policy |
| Encryption requirements | TLS; at-rest per platform policy |
| Logging rules | Identifiers minimized and access-controlled |
| Public-display rules | Not public |
| Export rules | Scoped business export only |
| Support-access rules | Need-to-know scoped access |
| Backup rules | Encrypted restricted backup |
| Retention decision dependency | Policy dependent |
| Deletion or anonymization considerations | Delete/anonymize when purpose ends subject to records |
| Incident severity | Moderate |
| Example records | Schedules, queue metrics, configuration metadata, internal organization workflow |

## QMDB-DCL-003 — Confidential Personal Data

| Field | Rule |
| --- | --- |
| Classification ID | QMDB-DCL-003 |
| Permitted storage | Authoritative MySQL or approved private object storage |
| Permitted transmission | TLS and platform encryption |
| Encryption requirements | TLS and platform encryption |
| Logging rules | Minimized identifiers; no full bodies |
| Public-display rules | Not public except separately approved derived fields |
| Export rules | Authorized purpose-specific encrypted export |
| Support-access rules | Time-limited field-minimized support |
| Backup rules | Encrypted restricted backup |
| Retention decision dependency | Privacy/records decision |
| Deletion or anonymization considerations | Correction/deletion/anonymization assessment |
| Incident severity | High |
| Example records | Contact data, birth/age, affiliation, Guardian, competition history, device data |

## QMDB-DCL-004 — Restricted Sensitive Data

| Field | Rule |
| --- | --- |
| Classification ID | QMDB-DCL-004 |
| Permitted storage | Restricted MySQL fields/private quarantine/evidence stores |
| Permitted transmission | TLS, at-rest and field-level encryption where approved |
| Encryption requirements | TLS, at-rest and field-level encryption where approved |
| Logging rules | Never ordinary logs; references only |
| Public-display rules | Never public unless an independently approved derivative |
| Export rules | Dual-controlled encrypted export when required |
| Support-access rules | Exceptional purpose/field/period-scoped access |
| Backup rules | Separately controlled encrypted backup |
| Retention decision dependency | Privacy/security/records decision |
| Deletion or anonymization considerations | Deletion/hold assessment with evidence preservation |
| Incident severity | Critical |
| Example records | Identity evidence, MFA seed, recovery token, private/minor media, moderation evidence, privacy cases, security events |

## QMDB-DCL-005 — Security Secrets and Critical Cryptographic Material

| Field | Rule |
| --- | --- |
| Classification ID | QMDB-DCL-005 |
| Permitted storage | Dedicated secrets/KMS/HSM abstraction; never application DB/source/image |
| Permitted transmission | Mutually authenticated encrypted channels; encrypted custody |
| Encryption requirements | Mutually authenticated encrypted channels; encrypted custody |
| Logging rules | Never logged |
| Public-display rules | Never public |
| Export rules | Only controlled key/secret ceremony; no ordinary export |
| Support-access rules | Separate custodian/JIT access |
| Backup rules | Separately controlled recovery material where approved |
| Retention decision dependency | Key/secret policy |
| Deletion or anonymization considerations | Revoke/destroy by controlled ceremony; retain only public verification material as needed |
| Incident severity | Critical |
| Example records | Signing private keys, encryption keys, API/webhook secrets, object/database credentials |

## Data-handling matrix

| Record | Classification | Authoritative/permitted store | Special handling |
| --- | --- | --- | --- |
| Person profile | QMDB-DCL-003 | MySQL | Private; approved public projection only |
| Public profile | QMDB-DCL-001 | Public read model | Allowlisted, consent/status checked |
| Contact information | QMDB-DCL-003 | MySQL | Never public |
| Date of birth | QMDB-DCL-004 | Restricted MySQL field | Prefer derived age band for ordinary use |
| Age band | QMDB-DCL-003 | MySQL/read model as approved | Conservative Minor display |
| Minor status | QMDB-DCL-004 | Restricted MySQL | Child-safety policy |
| Guardian relationship | QMDB-DCL-004 | Restricted MySQL | Scoped authority, history retained |
| Consent record | QMDB-DCL-004 | Restricted MySQL/audit | Versioned purpose and withdrawal |
| Identity evidence | QMDB-DCL-004 | Restricted private store | Collect only after approval |
| Password hash | QMDB-DCL-004 | Identity store | Adaptive hash; never export |
| MFA secret | QMDB-DCL-005 | Secrets/KMS-backed protected store | Never ordinary DB/log/export |
| Session | QMDB-DCL-004 | Server-side Session store | No URL/log/local storage token |
| Recovery token | QMDB-DCL-005 | Hashed token record | Single use and expiring |
| Device information | QMDB-DCL-003 | Identity/security store | Minimized purpose-bound |
| Organization record | QMDB-DCL-002 | MySQL | Public derivative only if verified/approved |
| Organization-verification evidence | QMDB-DCL-004 | Restricted MySQL/object store | Reviewer-only |
| Qur’an reference release | QMDB-DCL-001 | Versioned authoritative MySQL/artifact | Published only after checksum/approval |
| Competition configuration | QMDB-DCL-002 | Authoritative MySQL | Approved public projection |
| Participant snapshot | QMDB-DCL-003 | Authoritative MySQL | Historical immutable context |
| Score sheet | QMDB-DCL-004 | Authoritative MySQL | Judge/panel scope |
| Provisional result | QMDB-DCL-002 | Authoritative MySQL plus public projection | Clearly provisional |
| Final result | QMDB-DCL-001 | Authoritative MySQL plus allowlisted projection | Former versions preserved |
| Appeal | QMDB-DCL-004 | Authoritative MySQL | Restricted case/evidence |
| Certificate | QMDB-DCL-003 | Authoritative MySQL/private artifact | Minimal public verification |
| Certificate-signing key | QMDB-DCL-005 | Dedicated KMS/HSM abstraction | Never application DB |
| Media evidence master | QMDB-DCL-004 | Private object storage | Signed scoped access |
| Public media derivative | QMDB-DCL-001 | Protected origin/CDN | Consent/status checked |
| Minor media | QMDB-DCL-004 | Private object storage | Guardian/safety restrictions |
| Moderation evidence | QMDB-DCL-004 | Restricted case/object store | Need-to-know |
| Privacy request | QMDB-DCL-004 | Restricted MySQL/private export | Requester/Privacy Officer only |
| Audit event | QMDB-DCL-004 | Append-oriented MySQL/checkpoint | Restricted export |
| Security event | QMDB-DCL-004 | Security event/log store | Restricted operations |
| Backup | QMDB-DCL-004 | Encrypted separate backup store | Classification inherits highest content |
| API credential | QMDB-DCL-005 | Secrets manager/hashed verifier | Never ordinary export |
| Webhook secret | QMDB-DCL-005 | Secrets manager | Rotatable, never payload/log |
| Analytics record | QMDB-DCL-002 | Approved aggregate store | Suppress small groups; no authoritative writes |

## Review rule

New data requires classification before persistence or logging. Classification change needs Privacy, Security and Records review, migration/derivative analysis, cache/search purge rules and audit evidence.

## P3-B02 Person-profile handling addendum

Person names, birth dates, nationality, origin/residence and guardianship relationships are `QMDB-DCL-003`
confidential personal data. They remain in authoritative private MySQL rows and are omitted from audit metadata,
security notifications, URL/referrer data, browser storage and public caches. Opaque public IDs are references, not
public-profile authorization. No export, public projection, contact record, consent record or retention automation is
introduced; their purpose, legal basis and retention remain open governance decisions.
