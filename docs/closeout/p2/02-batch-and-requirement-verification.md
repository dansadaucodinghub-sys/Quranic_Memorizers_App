# P2 Batch and Requirement Verification

## Executable batch truth matrix

| Batch | Source and schema | Tests and security | Release and documentation | Status |
| --- | --- | --- | --- | --- |
| B01 | Accounts, contacts, credentials, workspaces, memberships; five foundation migrations | Unit, architecture, MySQL constraints and tenant negative tests | Standard/report and release inclusion | COMPLETE |
| B02 | Registration, verification, password authentication, rate limits | CSRF, anti-enumeration, replay, concurrency, HTTP, frontend and MySQL tests | Standard/report and release inclusion | COMPLETE |
| B03 | Server sessions, devices, cookie and rotation lifecycle | Fixation, expiry, revocation, concurrency, HTTP and frontend tests | Standard/report and release inclusion | COMPLETE |
| B04 | Recovery, reset and security-notification outbox | Genericity, replay, expiry, reset/revocation and scheduler tests | Standard/report and release inclusion | COMPLETE |
| B05 | MFA policy, TOTP, recovery codes, passkeys and step-up | Ceremony, assurance, replay, origin/RP, concurrency and abuse tests | Standard/report and release inclusion | COMPLETE |
| B06 | Permissions, roles and scoped assignments | Deny-by-default, delegation, assurance and survivor-protection tests | Standard/report and release inclusion | COMPLETE |
| B07 | Session-bound tenant context and tenant repositories | Context version, cross-session, cross-workspace, cache/job and MySQL tests | Standard/report and release inclusion | COMPLETE |
| B08 | Temporary, support and break-glass access | Approval, expiry, no-impersonation, review and abuse tests | Standard/report and release inclusion | COMPLETE |
| B09 | Audit ledger, checkpoints and account state | Tamper, append-only, lifecycle, suspension and revocation tests | Standard/report and release inclusion | COMPLETE |
| B10 | Route inventory, tenant repository policy and aggregate hardening | Route, abuse, fuzz, fault, concurrency, performance and verifier tests | Defect register, matrices, release and committed freeze evidence | COMPLETE |

Each row was revalidated in the clean closeout preflight by the full PHP, isolated MySQL, frontend, authorization, tenant, privileged-access, audit, route, tenant-repository and aggregate-P2 verifiers. No batch status is based only on its historical report.

## Requirement closure map

The authoritative source is `docs/implementation/requirements-to-implementation-map.md`. Its 52 P2 rows are accounted for below. All are `VERIFIED` for the P2 foundation, except operational evidence stated as `VERIFIED_WITH_DEFERRED_OPERATIONAL_EVIDENCE`; none is `BLOCKING` or silently `NOT_APPLICABLE`.

| Requirement IDs | Owning batches | Production and schema evidence | Executable evidence | Status |
| --- | --- | --- | --- | --- |
| QMDB-ACT-001–004; QMDB-FR-AUT-001–002; QMDB-IDN-003; QMDB-NFR-SEC-001; QMDB-SEC-001 | B06–B08 | Authorization catalog, assignments, tenant context, privileged access | Authorization verifier, architecture, HTTP, MySQL and adversarial tests | VERIFIED |
| QMDB-DR-001–006, QMDB-DR-028–032; QMDB-NFR-TEN-001 | B01, B06–B10 | Registry, migrations, composite tenant constraints and repositories | MySQL lifecycle, schema verifier, tenant verifier and concurrency tests | VERIFIED |
| QMDB-FR-IAM-001–006; QMDB-FR-SES-001–003; QMDB-IDN-001, QMDB-IDN-004–005; QMDB-NFR-IAM-001–002 | B01–B05, B09–B10 | Identity, sessions, recovery, MFA and account-state modules | Unit, HTTP, MySQL, frontend, abuse and route-security tests | VERIFIED |
| QMDB-FR-OPS-001–002; QMDB-SEC-002–004; QMDB-NFR-INF-002 | B08–B10 | Privileged request, approval, activation, review and policy records | Privileged verifier, MySQL, fault and adversarial tests | VERIFIED |
| QMDB-FR-AUD-001; QMDB-FR-SEC-001; QMDB-NFR-AUD-001; QMDB-NFR-OBS-001 | B04, B09–B10 | Notifications, audit streams/events/checkpoints and structured logging | Audit verifier, tamper tests, sensitive-log/error tests and scheduler tests | VERIFIED |
| QMDB-FR-PRI-001; QMDB-NFR-PRI-001 | B01–B05, B09–B10 | Encrypted contact/authenticator data, redaction and no-store responses | Privacy, scanner, error, logging and browser-storage tests | VERIFIED_WITH_DEFERRED_OPERATIONAL_EVIDENCE |
| QMDB-SEC-005 | B06, B10 | No P3 scoring/integration implementation exists; P2 denies unmodelled authority | Repository/route inventory, architecture and freeze no-P3 check | VERIFIED |

Deferred operational evidence has owners, release/phase gates and compensating controls in [operations and deferred evidence](06-operations-release-and-deferred-evidence.md).
