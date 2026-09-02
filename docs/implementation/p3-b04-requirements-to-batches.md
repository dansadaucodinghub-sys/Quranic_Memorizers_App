# QMDB-P3-B04 Requirements-to-Batches Record

| Control | Value |
| --- | --- |
| Batch | QMDB-P3-B04 — Organization Memberships, Staff, Leadership, and Person Affiliations |
| Authorization | QMDB-P3-B04-EXEC, retained by QMDB-P3-B05-EXEC prerequisite authority |
| Status | COMPLETE — production implementation, real-MySQL verification, repository quality, and release-closeout evidence recorded |
| Scope boundary | Private consent-based affiliations only; no Workspace membership, authorization-role assignment, public directory, claim pairing, duplicate merge, competition, media, or B05 source |

## Delivered requirement slice

| Requirement area | Production evidence | Result |
| --- | --- | --- |
| Private Organization-to-Person affiliation | Tenant-scoped affiliation aggregate, opaque `QMA-` code, consent lifecycle, guarded account inventory and private routes | Delivered |
| Staff, volunteer, teaching, religious-service and leadership roles | Twelve fixed role definitions, assignment history, one-primary constraints, Person-role compatibility and leadership step-up | Delivered |
| Consent and Guardian authority | Self/Guardian-only response query, active guardianship validation, response authority/history and recipient notification targets | Delivered |
| Tenant and lifecycle integrity | Composite Workspace/Organization foreign keys, MySQL uniqueness marker, immutable status triggers, optimistic versions and retirement guards | Delivered |
| Security controls | Closed authorization catalog, CSRF, idempotency, rate limits, step-up, audit events, notifications and scheduler | Delivered |
| Accessibility/progressive enhancement | Server-rendered forms, controlled modal/fragment enhancement, keyboard/focus/live-region controls, no-JavaScript fallback and private roster filtering | Delivered |

## Explicitly not delivered

No B05 profile claim, secret pairing, verification assertion, duplicate case, Person consolidation, public Person search, Organization verification, role assignment, Workspace membership, competition participation, scoring, certificate, media or social capability is introduced by B04.

## Handoff

The next authorized batch is `QMDB-P3-B05 — Profile Claims, Verification, Consent, and Duplicate Resolution`. It may start only from the clean, committed B04 release baseline with the P2 frozen baseline remaining clean under its controlled extension policy.
