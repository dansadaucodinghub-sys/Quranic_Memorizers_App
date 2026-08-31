# QMDB P2 Identity, Security, and Tenant Isolation Roadmap

## Governance status

This roadmap was prepared by `QMDB-P1-CLOSE`. `QMDB-RECOVERY-RUN-001` subsequently authorized strict sequential local
execution through B05. The project owner separately authorized B06 through B09, and B09 is complete with committed
Engineering Freeze and release evidence. B10 is complete with committed local CI, clean-install, release, and freeze
evidence. P2-CLOSE has verified the complete P2 foundation and frozen `QMDB-P2-FRZ-001`; P3 remains separately
not started and not authorized.

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
| QMDB-P2-B10 | Identity and tenant security hardening. Depends on B01–B09. | COMPLETE — bounded audit-readiness and dependency hardening; no migration was required. | Full interaction/security regression passed. | Threat/control reconciliation, route-derived denial assurance and abuse containment verified. | Direct local CI, Composer CI, clean-install, release, and committed freeze passed. |
| QMDB-P2-CLOSE | Verify and freeze the identity lifecycle. Depends on B01–B10 complete. | COMPLETE — QMDB-P2-FRZ-001 generated and verified. | Automated accessible/RTL/progressive verification passed; manual evidence deferred explicitly. | Local security, privacy and tenant-isolation assessment passed; external assessment remains a release gate. | Full local/MySQL/CI/release/security gates pass; P3 remains not started/not authorized. |

## Batch documentation contract

Each batch report records objective, dependencies, mapped requirements, migrations, modules, routes, server pages, AJAX
interactions, authorization and audit controls, tests, explicit exclusions, actual command evidence and measurable exit
gate. A skipped mandatory MySQL or tenant-isolation test prevents completion.
