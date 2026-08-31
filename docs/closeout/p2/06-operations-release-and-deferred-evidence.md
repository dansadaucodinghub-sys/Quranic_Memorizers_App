# P2 Operations, Release, and Deferred Evidence

## Verified local foundation

| Gate | Current executable evidence |
| --- | --- |
| Toolchain | PHP 8.5.10, Composer 2.8.8, Node 24.19.0, npm 11.17.0, Oracle MySQL Community Server 8.4.11, Git 2.49.0. |
| Quality | PHP quality: 939 tests / 66,596 assertions; MySQL: 83 / 1,659; frontend: 51 tests. |
| Security verifiers | Authorization, tenant context, privileged access, audit, route, tenant-repository and aggregate P2 verifiers pass. |
| Supply chain | Composer audit, npm audit, Gitleaks, Trivy, SBOM validation and runtime-licence validation pass. |
| Scheduler | Exact tasks are notification delivery, audit checkpoint and privileged-access maintenance; scheduler has no browser route and no migration capability. |
| Clean install | The B10 Git-clone clean-install executed Composer install, npm ci, PHP quality, MySQL and frontend quality without a local `.env`, vendor, node modules or cached release data. |

## Deferred-evidence register

| Item | Automated evidence | Missing external evidence | Owner | Required gate | Risk / compensating control | P2 blocker |
| --- | --- | --- | --- | --- | --- | --- |
| Hosted CI | Pinned workflow and local CI policy verified | Hosted run | DevSecOps | Before merge/publication | Local CI and static workflow checks | No |
| Physical passkey and roaming key | Deterministic WebAuthn tests | Real device/browser ceremony | Security Engineering | Before production passkey activation | Required UV/RP/origin policy and server-side ceremony controls | No |
| Browser, keyboard, AT, zoom and forced colour | Automated RTL/focus/fallback/reduced-motion tests | Manual matrix | Accessibility Governance | Before affected UI release | Server-rendered fallbacks and semantic controls | No |
| Production WebAuthn RP/origins | Exact configuration validation | Approved production values | Security/Platform | Before activation | Fail-closed configured allowlist | No |
| MFA/audit key custody and rotation | Encryption/HMAC versioning and redaction tests | Managed KMS custody/ceremony | Security Operations | Before production activation | No committed keys; external secret injection only | No |
| Notification provider and scheduler operations | Provider-neutral outbox and safe scheduler tests | Provider integration/operations | Platform Operations | Before delivery activation | Transactional intent and bounded retries | No |
| External audit checkpoint publisher | Interface and tamper-evident local ledger | Independent publication | Audit Governance | Before claim of external anchoring | No publication claim | No |
| Linux PCNTL and ShellCheck | Bounded worker and Windows/local tests | Linux host rehearsal | Platform Operations | Before continuous workers | Production fail-closed policy | No |
| Docker/WSL host repair | Not required by repository gates | Host repair | Developer Operations | Before using those paths | Pinned Windows toolchain works | No |
| Platform-admin bootstrap / workspace provisioning | Catalog and authorization tests | Operational bootstrap runbook | Security/Product Governance | Before live onboarding | No synthetic account/workspace seeding | No |
| Assisted lost-factor recovery | Recovery/MFA controls | Approved assisted-recovery policy | Security/Product Governance | Before enforced-MFA rollout | No unsafe automatic bypass | No |
| Independent penetration test | Adversarial automated matrix | Independent assessment | Security Governance | Before public production release | Continuous scanner/verifier gates | No |
| Legal/compliance review | Privacy-by-design controls | Qualified review | Legal/Privacy Governance | Before affected production processing | Conservative no-public/secret-safe defaults | No |

These are non-blocking for P2 source closeout only because executable controls pass, owners and release gates are explicit, and none is claimed as completed.
