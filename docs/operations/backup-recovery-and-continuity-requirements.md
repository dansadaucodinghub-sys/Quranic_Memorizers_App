# Backup, Recovery, and Continuity Requirements

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B03 |
| Document Title | Backup, Recovery, and Continuity Requirements |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Business Continuity, Database and Platform Operations |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed for governance approval; no open value is approved by this document |
| Related Documents | [Availability NFRs](../requirements/non-functional/05-availability-resilience-and-disaster-recovery.md); [Service degradation](service-criticality-and-degradation-policy.md) |

## Purpose

Define recoverability evidence for authoritative data, objects, configuration, secrets, cryptographic trust, audit and rebuildable projections.

## Controlled requirements

- Backup scope includes MySQL full/incremental/PITR inputs, private object manifests/content as approved, configuration/artifact metadata, recovery secrets/keys under separate custody, and audit checkpoints.
- Backup owners and recovery approvers are separate where practicable; runtime credentials cannot administer backups.
- Backups are encrypted, access-restricted, integrity-checked, immutable or separately controlled, and copied across an independently controlled failure domain once approved.
- Restore order is identity/authorization/tenancy, authoritative MySQL, audit/checkpoints, signing/verification, scoring/results, then outbox/projections/cache/search/media and lower tiers.
- Signing-key recovery may re-establish trust through an approved ceremony; it must not fabricate or silently reuse compromised custody.
- Redis, search, dashboards and public projections are rebuildable and are never the sole source for official records.
- Recovery performs tenant, constraint, event, score/result, certificate, consent/privacy-removal, object-hash and audit-chain reconciliation before approval.
- Competition-day and Venue continuity uses approved signed packages/manual procedures without bypassing central acceptance.
- Exercises produce timings against open parameters, failures, corrective actions, communication evidence and approval.

## Dependency recovery matrix

| Dependency | Failure Impact | Local Fallback | Recovery Method | Data Reconciliation | Validation Evidence | Related Parameters |
| --- | --- | --- | --- | --- | --- | --- |
| MySQL primary | Authoritative reads/writes unavailable | No authoritative mutation; approved offline/manual continuity only | PITR/restore or approved failover | Outbox/audit/score/result comparison | Restore hashes, constraints, binlog point, reconciliation | QMDB-PAR-017; QMDB-PAR-018; QMDB-PAR-019 |
| MySQL replica | Reporting/search reads degraded | Primary only for bounded critical reads | Repair/reseed replica | Compare replication position and projections | Lag/consistency evidence | QMDB-PAR-028 |
| Redis/cache/Streams | Cache/live/jobs delayed | Bypass cache; preserve MySQL/outbox | Rebuild cache/consumer position | Deduplicate/replay outbox safely | Stream/consumer/reconciliation report | QMDB-PAR-029 |
| Private object storage | Media/evidence unavailable | Pause upload/publication; use no public original | Restore replicated/versioned objects | Verify content hashes and database references | Object manifest/hash evidence | QMDB-PAR-017; QMDB-PAR-018; QMDB-PAR-023 |
| Configuration/artifacts | Service cannot reproduce known state | Run last approved artifact/config | Restore versioned signed/attributable state | Compare checksums and environment values | Artifact/config attestation | QMDB-PAR-017 |
| Secrets/KMS | Connections/signing unavailable | Stop affected sensitive operation | Emergency recovery/re-establishment ceremony | Rotate/revoke and revalidate dependents | Custody, ceremony and validation evidence | QMDB-PAR-017; QMDB-PAR-031 |
| Audit checkpoints | Tamper verification uncertain | Pause mandatory-audit sensitive operations | Restore separately controlled checkpoints | Recompute chain and compare external receipts | Chain/checkpoint verification | QMDB-PAR-021 |
| Search/read models | Discovery/reporting stale | Authoritative bounded paths only where approved | Rebuild from authoritative events/data | Count/hash/version comparison | Rebuild and privacy purge evidence | QMDB-PAR-028 |
| Venue Edge data | Offline work pending/conflicted | Signed local drafts/manual fallback | Central synchronize under package policy | Replay/order/conflict report | Signed package/event/reconciliation | QMDB-PAR-030 |

## Required recovery evidence

Backup manifest; custody/access log; encryption/integrity proof; selected recovery point; environment and runbook versions; restore timeline; validation queries/hashes; audit/checkpoint result; object reconciliation; projection rebuild; exceptions; user communication; approver decision; and tracked post-recovery actions.

