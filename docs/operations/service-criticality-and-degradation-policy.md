# Service Criticality and Degradation Policy

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B03 |
| Document Title | Service Criticality and Degradation Policy |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Business Continuity and Site Reliability Governance |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed for governance approval; no open value is approved by this document |
| Related Documents | [Availability NFRs](../requirements/non-functional/05-availability-resilience-and-disaster-recovery.md); [Parameter register](quality-attribute-parameter-register.md) |

## Purpose

Govern recovery priority and deterministic degraded behavior by capability.

## Tier policy

Tier 1 is competition/integrity critical; Tier 2 is controlled administration/safety; Tier 3 is public/read delivery; Tier 4 is media/social; Tier 5 is reporting/analytics. Social and analytics capabilities degrade before competition-critical operations. No tier permits authorization, tenant, consent, signature, audit or authoritative validation bypass.

| Capability | Criticality Tier | Authoritative Dependency | Optional Dependency | Permitted Degradation | Prohibited Degradation | Recovery Priority | User Communication | Related Parameters |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Login | Tier 1 | Identity store, Session store | Notification provider | Existing Sessions may continue by risk policy; new login unavailable | No authentication bypass | 1 | Accessible incident message | QMDB-PAR-001; QMDB-PAR-016 |
| Authorization | Tier 1 | Policy/context and MySQL | Cache | Use authoritative policy source | No fail-open | 1 | Controlled denial | QMDB-PAR-016 |
| Tenant resolution | Tier 1 | Membership/Workspace MySQL | Cache | Resolve authoritatively | No arbitrary/default Workspace | 1 | Controlled denial | QMDB-PAR-016 |
| Competition registration | Tier 2 | MySQL | Notifications | Queue or preserve draft | No eligibility/consent bypass | 4 | Delay and receipt | QMDB-PAR-016 |
| Check-in | Tier 1 | MySQL | Redis/live projection | Authoritative check-in with delayed projection | No duplicate or bypass | 2 | Pending/accepted state | QMDB-PAR-005; QMDB-PAR-016 |
| Judge scoring | Tier 1 | MySQL, authorization, ruleset | Redis/live/notifications | Draft preservation; pause submit if authority uncertain | No unverified mutation | 1 | Explicit pending/unavailable | QMDB-PAR-003; QMDB-PAR-012; QMDB-PAR-016 |
| Result aggregation | Tier 1 | MySQL, ruleset, scores | Redis/public projection | Pause or compute authoritatively | No stale replica/cache finalization | 1 | Controlled delay | QMDB-PAR-016 |
| Result finalization | Tier 1 | MySQL, approvals, audit/signing as required | Notifications | Pause safely | No quorum/approval/audit bypass | 1 | Controlled delay | QMDB-PAR-016 |
| Certificate verification | Tier 1 | Certificate status, public key/trust data | CDN | Verified cached snapshot if approved/current | No signature/status bypass | 2 | Verified or unavailable | QMDB-PAR-006; QMDB-PAR-031 |
| Live scoreboard | Tier 3 | Public projection | SSE, Redis, CDN | Static verified snapshot/stale label | No invented/current label on stale data | 5 | Stale/reconnect status | QMDB-PAR-007; QMDB-PAR-016 |
| Search | Tier 3 | Search projection | Cache | Reduced/temporarily unavailable | No primary unbounded fallback | 7 | Unavailable/stale | QMDB-PAR-008 |
| Notifications | Tier 3 | Outbox/delivery ledger | Email/SMS/push | Queue bounded delivery | No sensitive payload/fake success | 8 | In-app status | QMDB-PAR-029 |
| Media upload | Tier 4 | Object quarantine, authorization | CDN | Pause authorization/upload | No public/direct origin bypass | 9 | Retry later | QMDB-PAR-011 |
| Media processing | Tier 4 | Object storage, workers | Redis queue | Backlog and pause publication | No unscanned publication | 10 | Processing delayed | QMDB-PAR-023; QMDB-PAR-029 |
| Recitation Clips | Tier 4 | Approved media/community MySQL | Ranking/cache | Read-only or unavailable | No consent/safety bypass | 11 | Feature reduced | QMDB-PAR-016 |
| Comments | Tier 4 | Community MySQL/moderation | Notifications | Disable write/read as safety requires | No unmoderated fail-open | 11 | Feature reduced | QMDB-PAR-026 |
| Reporting | Tier 5 | Approved replica/read model | Worker queue | Queue or disable | No primary critical starvation/scope expansion | 12 | Job status | QMDB-PAR-010; QMDB-PAR-028 |
| Analytics | Tier 5 | Aggregate projection | Warehouse/worker | Disable | No effect on official records | 13 | Unavailable | QMDB-PAR-016 |
| Moderation | Tier 2 | MySQL/case evidence | Notifications/media preview | Preserve reports; emergency restrict visibility | No unsafe republish | 3 | Safety status | QMDB-PAR-027 |
| Privacy requests | Tier 2 | MySQL/case/export store | Notifications | Accept/queue securely | No deadline claim or wrong-subject disclosure | 6 | Receipt/status | QMDB-PAR-022 |
| Audit verification | Tier 1 | Audit events/checkpoints | Monitoring | Pause mandatory-audit high-risk changes | No silent acceptance of broken chain | 1 | Integrity incident state | QMDB-PAR-021; QMDB-PAR-027 |

## Change control

New capabilities require tier, dependencies, failure injection, user message, parameter and runbook mapping before production. Recovery order may change only through approved continuity review.
