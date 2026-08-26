# QMDB-P2-B02 Implementation Report

## Batch result

`COMPLETE — all mandatory implementation, MySQL, concurrency, frontend, security, release, CI and freeze gates passed`

## Scope delivered

- Added explicit `security.web`, `identity.access`, and `application.http` modules without introducing a session or login
  route.
- Added sensitive plaintext-password policy, Argon2id hash/verify/rehash behavior, dummy-hash verification, generic
  password-authentication outcomes and database-backed email/peer throttling.
- Added enumeration-resistant registration with UUIDv7 idempotency, encrypted email persistence, atomic pending-account
  creation, active password credential and post-commit verification email delivery.
- Added hash-only 256-bit email-verification challenges, GET confirmation/POST consumption, bounded attempts,
  expiration/revocation, resend, atomic account activation and exactly one activation status event.
- Added action/time/cookie-bound stateless CSRF, production host-only Secure Strict cookie behavior, configured canonical
  origin validation and direct-peer-only address handling.
- Added English/Arabic server-rendered pages, localized HTML/plain-text email, normal POST fallback and narrowly governed
  same-origin progressive POST forms with accessible error/completion focus and no automatic retry.

## Dependencies

| Package | Resolved version | Purpose |
| --- | --- | --- |
| `symfony/mailer` | `7.4.17` | Provider-neutral SMTP/null transport and safe mail dispatch |
| `symfony/mime` and locked Symfony/PSR transitive packages | Composer lock | Standards-based multipart message construction and transport contracts |

No full-stack framework, ORM, queue, provider-specific SDK, OAuth package or frontend runtime dependency was added.

## Migrations and tables

| Migration | Tables |
| --- | --- |
| `20260826010500_create_identity_verification_foundation` | `identity_idempotency_records`, `account_email_verification_challenges` |
| `20260826010600_create_identity_rate_limit_foundation` | `identity_rate_limit_buckets` |

The tables use InnoDB, UTC-compatible `DATETIME(6)`, explicit checks and indexes, `RESTRICT` contact ownership, unique
public/token identifiers, and a generated nullable unique key allowing only one pending challenge per email. Stored
bucket/idempotency values are purpose-bound HMAC fingerprints rather than raw email or peer data.

## Defects corrected during validation

- Moved duplicate-contact classification into the MySQL persistence boundary so application services never depend on
  `PDOException`.
- Corrected repeated named PDO placeholders in the rate limiter and verification/resend state transitions; native MySQL
  prepares require a unique placeholder per occurrence.
- Updated B01 MySQL cleanup order so later B02 foreign keys coexist with foundation regression tests without disabling
  foreign-key enforcement.
- Split HTTP composition from foundation HTTP ownership to keep the module graph acyclic.
- Added identity readiness checks for schema, Argon2id policy, configured secrets, CSRF/fingerprint primitives and mailer
  transport shape.
- Recorded B02 decisions in the controlled P2 implementation-decision extension; the frozen P0 decision register remains
  byte-for-byte unchanged.

## Executable evidence added

- Security unit tests cover exact password handling, Argon2id, dummy hash, HMAC fingerprints, direct peer resolution,
  secure verification tokens, production URL policy, CSRF binding, host-only cookie attributes and cross-origin rejection.
- Password-authentication tests prove dummy verification for unknown accounts, generic failure, verified-principal/rehash
  behavior, throttle ordering and rate reset after success.
- Mail tests build English/Arabic one-link messages and capture multipart Symfony email without network access.
- HTTP/MySQL tests cover atomic generic registration, enumeration behavior, CSRF, route precedence, GET non-consumption,
  localization, accessible errors, unsafe content types, cross-origin rejection and no tenant/session creation.
- Parallel child-process tests prove one idempotent registration write, serialized threshold enforcement and single-use
  verification with exactly one activation event.
- Frontend tests cover safe mutation headers, same-origin policy, no retry, duplicate submission blocking, fragment
  replacement, password clearing, error focus and request-reference announcement.

## Security boundary

No plaintext password, password hash, verification token/hash, CSRF value, email address, raw peer address, rate-limit
fingerprint or mailer credential is written to public output or ordinary logs. GET never activates an account. SMTP runs
after commit. B02 creates no login/session/logout/device/recovery/MFA/role/permission surface.

## Final validation

| Gate | Result |
| --- | --- |
| PHP runtime and dependency policy | PASS — PHP 8.5.10; Composer validation/audit/strict PSR-4; Symfony Mailer 7.4.17 |
| PHP style and static analysis | PASS — PHPCS and maximum-level PHPStan |
| Complete PHPUnit suite | PASS — 753 tests after freeze reconciliation |
| Isolated MySQL suite | PASS — 23 tests, 242 assertions |
| B02 focused HTTP/MySQL/concurrency | PASS — 7 HTTP cases plus parallel registration, rate-limit and verification evidence |
| Frontend | PASS — 21 syntax-checked files; 34 tests; zero npm vulnerabilities |
| Schema lifecycle | PASS — ledger verify, plan, apply, no-op, both B02 rollbacks, reapply and status |
| Repository/P0/workflow/link/lock policy | PASS — P0 retains 177 exact checks and frozen decision-register hash |
| Gitleaks | PASS — history and working tree; narrow exact synthetic-test allowlist only |
| Trivy | PASS — HIGH/CRITICAL vulnerability, secret and misconfiguration scan |
| SBOM and licences | PASS — 148 SBOM checks; 26 runtime packages; zero unknown/review runtime licences |
| Release artifact | PASS — deterministic build, extracted PHP/CLI/HTTP/MySQL and external scanner verification |
| Local CI | PASS — all mandatory stages |
| P1 controlled engineering freeze | PASS — extension manifest regenerated from committed B02 source |

## Completion state

`QMDB-P2-B02` is complete. The next executable recovery batch is `QMDB-P2-B03 — Secure Sessions, Cookies, Devices,
Login, and Logout`. B04 and B05 remain blocked by their immediate sequential prerequisites, and B06 remains outside the
authorized recovery scope.
