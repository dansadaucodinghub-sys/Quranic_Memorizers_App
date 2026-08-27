# QMDB-P2-B04 Implementation Report

## Execution identity

| Field | Value |
| --- | --- |
| Project | Qur’an Memorizer DB (`QMDB`) |
| Product Baseline | `QMDB-BL-001` / `QMDB-P0-FRZ-001` |
| Engineering Baseline | `QMDB-P1-FRZ-001` |
| Approved Change | `QMDB-CR-001` — progressive interaction standard |
| Phase | P2 — Identity, Security, and Tenant Isolation |
| Batch | QMDB-P2-B04 — Account Recovery and Security Notifications |
| Status | COMPLETE |
| Execution Date | 2026-08-27 |

## Prerequisite verification and freeze impact

P2-B01, P2-B02, and P2-B03 were verified complete and frozen before B04 implementation began. P0 and P1 governed
decisions were not rewritten. B04 uses the frozen module, DI, MySQL migration, HTTP, presentation, mail, scheduler,
observability, CI, release, and controlled engineering-freeze extension points. The engineering freeze is regenerated
only after the committed B04 candidate passes its mandatory gates.

## Scope delivered

- Added explicit `identity.recovery` and `identity.security_notifications` modules with typed configuration,
  repositories, services, mail adapters, readiness checks, controllers, routes, views, fragments, translations, and
  scheduled delivery.
- Added three reversible migrations and four InnoDB tables: recovery challenges, append-only recovery events,
  notification intents, and append-only notification events. The constraint-extension migration adds recovery
  idempotency operations, rate scopes, notification type, and `PASSWORD_RESET` session revocation.
- Added 256-bit opaque recovery tokens with SHA-256-only persistence, single pending challenge per account, bounded
  attempt/expiry lifecycle, generic anti-enumeration response, HMAC email/peer limits, and after-commit recovery mail.
- Added the final reset transaction: precomputed Argon2id hash, locked revalidation, credential replacement with revoked
  history, other-challenge revocation, all-session revocation, device preservation, recovery events, and atomic
  password-reset-completed notification intent.
- Added leased, versioned, bounded-batch notification claiming, capped retries, terminal failure, stale-owner rejection,
  and at-least-once SMTP semantics outside transactions.

## Routes and presentation

Six routes implement recovery request, generic acceptance, reset form/submit, and completion. Four pages, four
fragments, and four HTML/text email templates provide English/Arabic, RTL, accessible, no-JavaScript-capable flows.
Recovery and reset use full pages and are never rendered as modals. The shared progressive-form policy now requires
recovery idempotency headers and retains no recovery secret in browser storage.

## Corrections made during validation

- Moved locked challenge validation ahead of reset-idempotency claiming so a losing concurrent reset cannot leave an
  incomplete idempotency claim.
- Made recovery delivery/configuration failures indistinguishable from other generic request outcomes while retaining
  bounded internal failure classification.
- Corrected typed persistence hydration and history mapping for maximum-level static analysis.
- Generalized readiness aggregation so recovery and notification checks contribute without session-module ownership.
- Added a bounded DI extension-dependency graph and deferred scheduled-handler adapter. Task listing no longer eagerly
  resolves mail, encryption, or database dependencies, while task execution remains constrained to declared services.
- Enabled Sodium in the recovered PHP 8.5 runtime because B04 scheduled-delivery composition exercised the existing
  encrypted-contact dependency in CLI integration tests.
- Bounded Trivy scan inputs by excluding generated PHPStan cache data from repository filesystem scanning.

## Verification evidence

Unit coverage verifies token entropy/redaction/hash matching, challenge state, deduplication, retry bounds, failure
classification, and notification invariants. MySQL/HTTP coverage verifies all four tables and constraints, hash-only
storage, one-pending enforcement, generic English/Arabic responses, progressive/fallback flows, the complete reset
transaction, credential history, session invalidation, device preservation, atomic intent creation, scheduled delivery,
claim/dedup/version/retry behavior, and concurrency-sensitive outcomes. Architecture tests enforce module direction,
token boundaries, thin controllers, POST authority, no session creation, no modal/browser storage, and scheduler-only
delivery. Frontend tests enforce recovery idempotency and invalid-token handling.

The final committed candidate is validated with strict Composer metadata/audit/autoload, PHPCS, maximum-level PHPStan,
the locked PHPUnit and isolated MySQL suites, Node 24 syntax/tests/audit, migration plan/status/rollback/reapply,
scheduler list/run, HTTP/readiness checks, repository/workflow/link/lock rules, Gitleaks, Trivy, SBOM/licence generation,
deterministic release verification, local CI, frozen P0 verification, and regenerated engineering-freeze verification.
Exact counts, revision, artifact hash, and generated-report evidence are retained by the closeout tools and project-state
ledger rather than exposing private runtime paths or sensitive material here.

## Security and known limitations

No password, password hash, recovery token/hash, session token, email address, recipient, mailer DSN, or cryptographic
key is included in this report. Scheduler delivery is at least once, not exactly once. An external production scheduler,
mail provider, trusted-proxy policy, retention policy, and manual browser/accessibility matrix remain deployment or later-
phase evidence; they do not mask an incomplete local source contract. Security-notification records are not represented
as tamper-evident audit evidence.

## Completion state

`QMDB-P2-B04` is complete. The next and only remaining batch authorized by `QMDB-RECOVERY-RUN-001` is
`QMDB-P2-B05 — MFA, Passkeys, Recovery Codes, and Step-Up Authentication`. P2-B06 remains blocked and outside scope.
