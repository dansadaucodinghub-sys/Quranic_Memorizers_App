# P3 Requirements-to-Batches Record

## QMDB-P3-B05

| Control | Delivered B05 boundary |
| --- | --- |
| Batch | QMDB-P3-B05 — Profile Claims, Verification, Consent, and Duplicate Resolution |
| Previous batch | QMDB-P3-B04 — Organization Memberships, Staff, Leadership, and Person Affiliations |
| Status | COMPLETE — private production capability, real-MySQL evidence, release verification, and frozen-baseline preservation recorded |
| Account/Person boundary | An Account requires a separate hash-only pairing, authorized claim, and claimant acceptance to obtain one SELF link; neither registry codes nor Organization affiliations are authority. |
| Verification boundary | Assertions describe QMDB record history only and grant no Permission, legal certification, biometric claim, or public badge. |
| Duplicate boundary | Cases are explicit, all active managers consent, conflicts block, and the source Person is retired with an immutable alias rather than deleted. |
| Integration boundary | Canonicalization participants run in one controlled transaction and cannot independently commit, create affiliations, roles, memberships, or Account transfers. |
| Explicit exclusions | Public Person discovery, automatic matching, legal/government identity verification, document/biometric handling, Account merge, self-link transfer, B06 implementation, competitions, media, and social features. |

## QMDB-P3-B06

| Control | Delivered B06 boundary |
| --- | --- |
| Batch | QMDB-P3-B06 — People, Geography, and Organization Security Hardening and Closeout Preparation |
| Status | COMPLETE — executable bounded security composition, repository scope verification and adversarial evidence reconciliation |
| Runtime verifier | `security:p3:verify` composes P2, Geography, People, Organizations, affiliations, identity resolution and Person repository scope checks without data mutation. |
| Scope preservation | No migration, seed, route, public discovery interface, feature workflow, P3 freeze or P3-CLOSE execution. |
| Deferred evidence | Hosted, manual accessibility, independent penetration, production operations and production-volume evidence remain explicitly non-blocking operational evidence. |

## Handoff

The next permitted batch is **QMDB-P3-CLOSE**. It requires separate authorization; B06 does not begin it.
