# QMDB P2 Identity, Security, and Tenant Isolation Roadmap

## Governance status

This roadmap is prepared by `QMDB-P1-CLOSE` but is not implementation authorization. P2 remains blocked while the
[P2 readiness assessment](../closeout/p1/05-P2-readiness-assessment.md) is `NOT_READY`.

Every P2 batch preserves Core PHP 8.5, MySQL/InnoDB, the modular monolith, server-derived tenant authority, explicit
transactions, migration checksums, structured logging, request IDs, server rendering and `QMDB-CR-001`. Each batch must
update traceability, risks, decisions and state only after its executable gates pass.

## Controlled sequence

| Batch | Objective and dependencies | Migrations and modules | HTTP, UI and AJAX | Security and audit | Mandatory tests and exit gate |
| --- | --- | --- | --- | --- | --- |
| QMDB-P2-B01 | Establish workspace/account/contact/credential schema and trusted tenant-context contracts. Depends on P1 closeout plus OD-051/052. | Identity and Tenancy modules; only `workspaces`, `user_accounts`, `account_email_addresses`, `account_phone_numbers`, `account_credentials`, `account_status_events`. | No public route, form, modal, login or mutation. | Non-root MySQL, exact constraints/indexes, opaque IDs, hash-only credentials, explicit tenant context. | Migration/rerun/drift/constraint/repository/cross-workspace/architecture/security tests on real MySQL. |
| QMDB-P2-B02 | Registration, email verification and password authentication. Depends on B01 and OD-031/051. | Verification/rate/authentication records owned by Identity. | Server forms plus approved CSRF/idempotent progressive mutations; no client authority. | Password hashing, enumeration resistance, throttling, purpose-bound tokens and security events. | Positive/negative authentication, CSRF, replay, rate, accessibility and RTL tests. |
| QMDB-P2-B03 | Secure sessions, cookies, devices and logout. Depends on B02. | Session/device records and lifecycle services. | Session inventory, logout and revocation pages with fallbacks. | HttpOnly/Secure/SameSite cookies, rotation, idle/absolute expiry, remote revocation. | Fixation, reuse, expiry, concurrency, revocation, cookie and cross-tenant tests. |
| QMDB-P2-B04 | Account recovery and security notifications. Depends on B03. | Recovery challenges/history and provider-neutral notification contracts. | Non-enumerating recovery flows with accessible fallbacks. | Single-use expiry, risk review, session revocation and mandatory notices. | Replay, expiry, race, enumeration, notification and recovery-abuse tests. |
| QMDB-P2-B05 | MFA, passkeys and step-up authentication. Depends on B03/B04 and OD-031. | Authenticator, passkey, recovery-code and assurance records. | Origin-bound ceremonies and accessible step-up flows. | No private passkey material; phishing/replay resistance; recovery governance. | Ceremony, origin/RP, counter/risk, factor recovery and privileged-action tests. |
| QMDB-P2-B06 | Roles, permissions and scoped contextual authorization. Depends on B03/B05 and OD-034. | Access Control module and versioned role/permission/scope/membership grants. | Deny-by-default policy enforcement; UI controls never authorize. | Separation of duties, step-up, reason/evidence and immediate revocation. | Authorization matrix, self-approval, stale grant, scope and cross-tenant denial tests. |
| QMDB-P2-B07 | Tenant context, workspace switching and tenant-aware data access. Depends on B06. | Immutable TenantContext and tenant-aware repository/job/cache/export boundaries. | Authorized switching only; active workspace is always explicit. | Client identifiers never establish authority; composite tenant relationships. | Repository mutation/read, cache, job, export and existence-leakage tests. |
| QMDB-P2-B08 | Temporary privilege, support access and break-glass controls. Depends on B05–B07. | Expiring grants, approval and review records. | Scoped support/emergency workflows with clear warnings. | Step-up, independent approval, notification, expiry and post-use review. | Expiry, self-approval, excessive scope, emergency abuse and review tests. |
| QMDB-P2-B09 | Security events, audit integrity and account-state operations. Depends on B03–B08. | Security Event and Audit modules; hash-linked records and checkpoints. | Suspension/reactivation and audit inspection surfaces. | Append-only evidence, before/after state, correlation and external checkpoint boundary. | Chain verification, tamper, suspension, reactivation, privilege-history and privacy tests. |
| QMDB-P2-B10 | Identity and tenant security hardening. Depends on B01–B09. | No new broad capability; bounded hardening migrations only if proven necessary. | Full interaction/security regression. | Threat/control reconciliation and abuse containment. | Authorization, cross-tenant, authentication, session, MFA, audit, performance and concurrency gates. |
| QMDB-P2-CLOSE | Verify identity lifecycle and authorize P3. Depends on B01–B10 complete. | Freeze verified P2 baseline. | End-to-end accessible/RTL/no-JS verification. | Independent security, privacy and tenant-isolation assessment. | All local/MySQL/CI/release/security/manual gates plus zero unresolved P3 blockers. |

## Batch documentation contract

Each batch report records objective, dependencies, mapped requirements, migrations, modules, routes, server pages, AJAX
interactions, authorization and audit controls, tests, explicit exclusions, actual command evidence and measurable exit
gate. A skipped mandatory MySQL or tenant-isolation test prevents completion.
