# P2 Threat-Model Reconciliation

| Document control | Value |
| --- | --- |
| Batch | QMDB-P2-B10 — Identity and Tenant Security Hardening |
| Scope | P2 identity, authorization, tenant, privileged-access, account-state and audit boundaries |
| Reconciled against | Implemented source, routes, MySQL schema, executable tests and local verifiers |
| Status vocabulary | `MITIGATED`, `PARTIALLY_MITIGATED`, `DEFERRED_OPERATIONAL`, `BLOCKING` |

## Method

An implemented control must have executable evidence. Operational deployment evidence is kept distinct from local source-code assurance. No unresolved Critical or High source defect is recorded.

| ID | Threat | Affected modules | Preconditions | Implemented controls | Executable evidence | Residual risk / deferred evidence | Status |
| --- | --- | --- | --- | --- | --- | --- | --- |
| P2-THR-001 | Account enumeration | identity.access | Public registration/recovery | Generic response, HMAC fingerprint, rate limit | access/recovery MySQL HTTP suites | Production telemetry thresholds | MITIGATED |
| P2-THR-002 | Credential stuffing / brute force | identity.access, sessions | Repeated credential attempts | Peer/account buckets, dummy hash, bounded work | session MySQL HTTP suite | Edge/WAF capacity | PARTIALLY_MITIGATED |
| P2-THR-003 | Email-verification abuse | identity.access | Challenge identifier/resend | Hashed, expiring, bounded, one-time challenge | access MySQL HTTP suite | Mail-provider delivery | MITIGATED |
| P2-THR-004 | Password-reset abuse | identity.recovery | Recovery endpoint/challenge | Generic response, hashed single-use challenge, session revocation | recovery MySQL HTTP suite | Production mail-link delivery | MITIGATED |
| P2-THR-005 | CSRF | security.web, HTTP | Victim browser session | Action/cookie/time-bound CSRF and origin validation | P2 HTTP suites; `security:routes:verify` | Browser compatibility matrix | MITIGATED |
| P2-THR-006 | Session fixation | identity.sessions | Attacker sets pre-login cookie | Fresh session on login; old session revoked | session MySQL HTTP suite | Browser cookie implementation | MITIGATED |
| P2-THR-007 | Session replay / token theft | identity.sessions | Selector/secret replay | Secret-bound selector, dummy comparison, rotation, expiry | session MySQL HTTP suite | Endpoint compromise outside app boundary | MITIGATED |
| P2-THR-008 | Device-token abuse | identity.sessions | Device cookie/public ID obtained | Non-authenticating secret-bound device, revocation | session MySQL HTTP suite | Client device hygiene | MITIGATED |
| P2-THR-009 | MFA bypass | identity.multifactor | Password-only or transaction reuse | Purpose/account/session-bound pre-auth transaction | MFA MySQL suite | Physical authenticator/browser evidence | MITIGATED |
| P2-THR-010 | TOTP replay / drift | identity.multifactor | Captured code/clock input | Encrypted secret, bounded drift, consumed counter | MFA MySQL suite | Managed key custody | MITIGATED |
| P2-THR-011 | Recovery-code reuse | identity.multifactor | Captured/guessed code | HMAC storage, one-time consumption, rate limit | MFA MySQL suite | User handling of displayed-once codes | MITIGATED |
| P2-THR-012 | Passkey challenge replay | identity.multifactor | Ceremony payload replay | Hashed one-time ceremony and exact binding | WebAuthn MySQL suite | Physical authenticator matrix | MITIGATED |
| P2-THR-013 | Wrong WebAuthn origin / RP ID | identity.multifactor | Host/header manipulation | Configuration-owned RP ID, exact origin allowlist | MFA configuration and WebAuthn tests | Production origin approval | MITIGATED |
| P2-THR-014 | Missing user verification | identity.multifactor | Weak assertion | User-verification policy and ceremony validation | WebAuthn MySQL suite | Authenticator vendor behavior | MITIGATED |
| P2-THR-015 | Step-up confusion | identity.multifactor | Grant reuse | Exact action/account/session/expiry/one-use binding | MFA MySQL suite | Physical factor behavior | MITIGATED |
| P2-THR-016 | Authorization bypass/default allow | security.authorization | Crafted role/scope/public ID | Seeded catalog and exact scope/assurance checks | authorization unit/MySQL suites | Hosted CI confirmation | MITIGATED |
| P2-THR-017 | Permission/delegation escalation | security.authorization | Broader role assignment | Subset validation, step-up, last-admin invariant | administration/concurrency suites | Operational role review | MITIGATED |
| P2-THR-018 | Cross-workspace access | tenancy.context, authorization | Membership in another workspace | Session context, composite FK, exact SQL scope | tenant MySQL suite; repository verifier | Future P3 repositories must register | MITIGATED |
| P2-THR-019 | Context forgery/staleness | tenancy.context | Client selector or stale version | Session authority, optimistic version, invalidation | tenant HTTP/MySQL suites; `tenancy:context:verify` | Browser tab behavior | MITIGATED |
| P2-THR-020 | Tenant job/cache leakage | tenancy.context | Serialized job/cache collision | Public-reference re-resolution and namespaced hashed key | tenancy tests; repository verifier | No durable queue/cache in P2 | MITIGATED |
| P2-THR-021 | Privileged self-approval | privileged access | Requester attempts approval | Distinct approvers, base-role policy, activation bounds | privileged MySQL suite | Human review operations | MITIGATED |
| P2-THR-022 | Support-access abuse | privileged access | Operator requests/activates | Dual approval, read-only policy, expiry/review | privileged MySQL suite | Support procedures | MITIGATED |
| P2-THR-023 | Break-glass abuse | privileged access | Claimed incident | Exact policy, phishing-resistant step-up, bounded duration | privileged MySQL suite | Incident governance | MITIGATED |
| P2-THR-024 | Account-suspension abuse | account state | Privileged/self actor | Base role, exact step-up, last-admin lock, audit | account-state MySQL suite | Operator review | MITIGATED |
| P2-THR-025 | Audit-chain tampering | security.audit | Database writer alters event | Keyed chain, stream lock, append-only triggers | audit MySQL suite; `security:audit:verify` | DB-superuser/key compromise | PARTIALLY_MITIGATED |
| P2-THR-026 | Checkpoint tampering | security.audit | Checkpoint/head mutation | HMAC checkpoint chain, immutable triggers | audit MySQL suite; audit verifier | External publication deferred | PARTIALLY_MITIGATED |
| P2-THR-027 | Sensitive-log leakage | observability, identity | Diagnostic path | Redaction, safe exception boundary | observability tests; Gitleaks | Production log retention/access | MITIGATED |
| P2-THR-028 | Sensitive-error leakage | foundation.http | Malformed/internal failure | Problem boundary and correlation ID | HTTP observability suite | Reverse-proxy error pages | MITIGATED |
| P2-THR-029 | Host/forwarded-header trust | foundation.http, MFA | Header manipulation | Forwarded headers stripped; config owns URL/origin | native request factory/architecture tests | Trusted proxy policy | MITIGATED |
| P2-THR-030 | Unsafe request target/open redirect | foundation.http, identity | Malformed target/continuation | Strict target validation and relative continuation | target validator and identity HTTP tests | Infrastructure smuggling boundary | PARTIALLY_MITIGATED |
| P2-THR-031 | Fragment injection | presentation, security.web | Malicious fragment/target | Exact media type and allowlisted interaction contract | frontend/presentation tests | Manual browser/AT evidence | MITIGATED |
| P2-THR-032 | CSP/header regression | foundation.http | New response path | Kernel security-header middleware | HTTP/release verification | Browser CSP matrix | MITIGATED |
| P2-THR-033 | Supply-chain compromise | build/CI | Dependency/workflow compromise | Lockfiles, audit, SBOM, licences, scanners | Composer/npm audit, Gitleaks, Trivy, workflow policy | Registry/vendor compromise | PARTIALLY_MITIGATED |
| P2-THR-034 | Release contamination | build | Dirty/non-production content | Allowlist, manifest hash, extracted verification, dirty ineligibility | release build/verifier | Maintainer signing/release approval | MITIGATED |
| P2-THR-035 | Concurrency races | P2 mutation modules | Parallel DB connections | Constraints, short transactions, locks, versions | all P2 MySQL/concurrency suites | Production contention profile | MITIGATED |
| P2-THR-036 | Expensive-authentication DoS | access, MFA, audit | Repeated expensive public input | Rate limits, bounded parsing/payloads, bounded readiness | identity suites and audit-readiness test | Edge DDoS mitigation | PARTIALLY_MITIGATED |

## Deferred operational evidence

Hosted CI, production HTTPS origin/RP-ID approval, managed MFA and audit-key custody/rotation, notification-provider operations, physical authenticator/browser coverage, manual accessibility testing, production reverse-proxy behavior, independent penetration testing, and external audit checkpoint publication are not represented as executed.
