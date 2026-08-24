# QMDB P1 Engineering Foundation Backlog

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Source Baseline | QMDB-BL-001 |
| Freeze ID | QMDB-P0-FRZ-001 |
| Batch ID | QMDB-P1 |
| Document Title | P1 Engineering Foundation Backlog |
| Document Version | 1.0.0 |
| Document Status | READY; only QMDB-P1-B01 currently authorized |
| Document Owner Role | Architecture and Engineering |
| Last Updated | 2026-08-24 |
| Approval Status | Approved backlog sequence under QMDB-P0-FRZ-001 |
| Related Documents | [Roadmap](phase-and-batch-roadmap.md); [Definition of Ready and Done](definition-of-ready-and-done.md); [implementation map](requirements-to-implementation-map.md); [B01 prompt](prompts/QMDB-P1-B01-core-php-repository-and-runtime-foundation.md) |

## Purpose

Define bounded, executable engineering batches for P1 and the evidence required to advance from a documentation-only P0 baseline to a production-oriented repository foundation.

## Scope

P1 establishes cross-cutting runtime and engineering infrastructure only. It does not implement QMDB business modules or domain tables. Requirements remain authoritative in the frozen P0 sources; this backlog supplies execution boundaries. Locked decisions remain binding, and deferred decisions do not block B01 unless reclassified through governance.

## Phase controls and constraints

- Apply ADR-001–020 and approved ADR-021–041 and ADR-045–055.
- Use Core PHP 8.5, Composer, MySQL/InnoDB, the modular-monolith direction and future module paths defined by ADR-054.
- Preserve shared-schema tenancy and server authority without implementing tenant domain tables in P1.
- Use PHPUnit, PHPStan, PSR-12-compatible style enforcement, Composer validation/audit and architecture tests.
- Use bounded jittered deadlock retry only for a classified transient failure around a whole idempotent transaction.
- Do not commit secrets or working credentials.
- Beginning with QMDB-P1-B01, documentation-only completion is prohibited unless a batch is explicitly documentation/review only.

## Backlog summary

| Batch | Outcome | Depends on | State |
| --- | --- | --- | --- |
| QMDB-P1-B01 | Core PHP Repository and Runtime Foundation | P0 freeze | READY / AUTHORIZED NEXT |
| QMDB-P1-B02 | Configuration, Environment, and Secrets Abstractions | B01 | PLANNED |
| QMDB-P1-B03 | HTTP Kernel, Routing, Request, Response, and Middleware | B02 | PLANNED |
| QMDB-P1-B04 | Dependency Injection, Module Registry, and Application Services | B03 | PLANNED |
| QMDB-P1-B05 | MySQL Connection and Transaction Foundation | B04 | PLANNED |
| QMDB-P1-B06 | Migration, Seeder, and Schema Ledger Foundation | B05 | PLANNED |
| QMDB-P1-B07 | Error Handling, Logging, Correlation, and Secure HTTP Foundation | B06 | PLANNED |
| QMDB-P1-B08 | CLI, Scheduler, Worker, and Background Execution Foundation | B07 | PLANNED |
| QMDB-P1-B09 | View Rendering, Localization, RTL, and Theme Foundation | B08 | PLANNED |
| QMDB-P1-B10 | CI Quality Gates and Engineering Verification | B09 | PLANNED |
| QMDB-P1-CLOSE | Engineering Foundation Verification | B01–B10 | PLANNED |

## QMDB-P1-B01 — Core PHP Repository and Runtime Foundation

- **Objective:** create the minimal executable Composer repository, Core PHP runtime and deterministic smoke paths.
- **Dependencies:** frozen P0 sources, PHP 8.5-compatible runtime, Composer and ADR-053–055.
- **Relevant requirements:** foundational applicability of QMDB-BND-001–004, QMDB-CON-001–004 and P1 NFRs; authoritative primary mapping remains in the implementation map.
- **Source files:** freeze manifest; this backlog; Definition of Ready/Done; MySQL schema conventions; project state; B01 prompt.
- **Executable deliverables:** `composer.json`/lock, PSR-4 source/tests, repository structure, `public/index.php`, `bin/console`, bootstrap/runtime identity, deterministic `app:about`, smoke tests, PHPUnit/PHPStan/style configuration, scripts and environment-neutral setup guidance.
- **Tests and commands:** PHP/Composer versions, strict Composer validation, install/audit/autoload, syntax, PHPUnit, PHPStan, style, CLI/HTTP smoke and architecture boundary.
- **Acceptance criteria:** clean install works; both entry points boot safely; smoke behavior is deterministic; namespaces autoload; errors expose no secrets; executable tests pass; no domain code/schema exists.
- **Explicit exclusions:** domain modules, MySQL domain tables/migrations, configuration/secrets implementation beyond a safe bootstrap minimum, business UI, workers, integrations and deployment.
- **State transition:** `READY → IN_PROGRESS → IN_REVIEW → VERIFIED → DONE`; B02 requires accepted B01 evidence.

## QMDB-P1-B02 — Configuration, Environment, and Secrets Abstractions

- **Objective:** implement immutable typed configuration, environment validation and replaceable time/identifier/secret boundaries.
- **Dependencies:** B01 complete; no vendor selection is required.
- **Relevant requirements:** QMDB-FR-OPS-003 and QMDB-NFR-INF-001.
- **Source files:** system boundaries; operational parameters; infrastructure NFR; ADR-054/055.
- **Executable deliverables:** environment loader, typed configuration objects/schema, required/unknown value validation, explicit environment separation, secrets-provider interface with safe local non-secret adapter, clock abstraction, identifier abstraction and configuration tests.
- **Tests and commands:** missing/invalid/unknown configuration; production debug prohibition; redaction; environment separation; deterministic clock/ID fakes; PHPUnit/PHPStan/style/Composer quality.
- **Acceptance criteria:** invalid production configuration fails closed; secret values never enter errors/logs; time and identifiers are injectable/testable; no product provider is selected.
- **Explicit exclusions:** real production credentials, cloud secret manager, database connection and domain IDs.
- **State transition:** B01 `DONE` plus authorization, then normal Ready/Done flow.

## QMDB-P1-B03 — HTTP Kernel, Routing, Request, Response, and Middleware

- **Objective:** implement a framework-independent HTTP kernel with bounded request/response, routing, middleware and dispatch behavior.
- **Dependencies:** B02.
- **Relevant requirements:** QMDB boundary, server-authority, validation and safe-failure obligations.
- **Source files:** system boundaries; threat model; edge/failure behavior; security requirements.
- **Executable deliverables:** request/response abstractions, router and route definitions, middleware pipeline, controller dispatcher, method/content/body restrictions, safe 404/405/error responses and tests.
- **Tests and commands:** route match/precedence/parameters; method restrictions; malformed/oversized request; middleware order; controller dispatch; safe error/content headers; full quality gate.
- **Acceptance criteria:** deterministic HTTP behavior with thin dispatch and no domain route; invalid input fails safely; no SQL/template concern enters controllers.
- **Explicit exclusions:** authentication, authorization, domain controllers and view rendering.
- **State transition:** B02 must be `DONE` before start.

## QMDB-P1-B04 — Dependency Injection, Module Registry, and Application Services

- **Objective:** establish controlled dependency construction, module registration and application interaction contracts.
- **Dependencies:** B03.
- **Relevant requirements:** QMDB-BND-001–004, QMDB-CON-001–004 and QMDB-NFR-MNT-001.
- **Source files:** system boundaries; core modules and invariants; ADR-001–020; ADR-054.
- **Executable deliverables:** minimal dependency-injection container, service definitions, module registry, dependency validation, application command/query contracts, domain-event contracts and architecture tests.
- **Tests and commands:** duplicate/missing/circular dependency; invalid module declaration; forbidden layer/module dependency; command/query/event contract; container boot; quality gate.
- **Acceptance criteria:** future modules fit `src/Modules/<Module>/{Domain,Application,Infrastructure,Presentation}`; prohibited dependencies fail; container and registry remain infrastructure rather than a service locator in domain code.
- **Explicit exclusions:** named business modules, domain event implementations and an external DI framework.
- **State transition:** B03 must be `DONE` before start.

## QMDB-P1-B05 — MySQL Connection and Transaction Foundation

- **Objective:** implement a secure PDO MySQL connection and explicit transaction boundary.
- **Dependencies:** B04 and a disposable MySQL integration environment.
- **Relevant requirements:** QMDB-NFR-DAT-001 and ADR-053.
- **Source files:** MySQL schema conventions; indexing/access patterns; tenant-isolation data model; data readiness assessment.
- **Executable deliverables:** PDO connection factory, native prepared statements, explicit connection settings, transaction manager, nested transaction policy, classified transient/deadlock failure, bounded jittered whole-idempotent-transaction retry, primary connection health and integration tests.
- **Tests and commands:** configuration/TLS policy; native prepare; commit/rollback; nested policy; deadlock/transient classification; retry exhaustion; non-idempotent rejection; database integration and quality gates.
- **Acceptance criteria:** credentials are never logged; unsafe PDO modes are rejected; failure rolls back; retry is finite and observable; no domain query/table exists.
- **Explicit exclusions:** tenant repositories, read-replica routing and domain schema.
- **State transition:** B04 must be `DONE` before start.

## QMDB-P1-B06 — Migration, Seeder, and Schema Ledger Foundation

- **Objective:** create controlled migration/seed execution and immutable schema/seed ledgers without business-domain tables.
- **Dependencies:** B05.
- **Relevant requirements:** QMDB-NFR-MNT-002.
- **Source files:** migration/seed/bootstrap plan; schema conventions; lifecycle/deletion; data readiness assessment.
- **Executable deliverables:** migration command/registry/ledger, ordered execution, locking/checksums, transaction policy, seed command/registry/ledger, schema status command and integration tests.
- **Tests and commands:** empty status/apply; deterministic order; duplicate/checksum drift; concurrent runner; transactional/nontransactional policy; failure recovery; seed replay/idempotency; forward/rollback cycle.
- **Acceptance criteria:** ledgers are safe and reproducible; schema status is truthful; no QMDB-MIG-001–020 business structure or authoritative seed values are implemented.
- **Explicit exclusions:** logical-schema translation, domain migrations and fabricated seed datasets.
- **State transition:** B05 must be `DONE` before start.

## QMDB-P1-B07 — Error Handling, Logging, Correlation, and Secure HTTP Foundation

- **Objective:** implement production-safe exception handling, structured telemetry context and baseline secure HTTP protections.
- **Dependencies:** B06.
- **Relevant requirements:** approved observability, secure-error, audit and privacy-logging foundation obligations.
- **Source files:** observability/incident NFRs; control catalog; threat model; operational parameters; privacy controls.
- **Executable deliverables:** exception hierarchy/mapper, production-safe handler, structured logger, correlation/request IDs, secret/PII redaction, secure header middleware and health/readiness behavior.
- **Tests and commands:** exception mapping; no stack/secret leakage; log schema/redaction; correlation propagation; header policy; truthful health states; PHPUnit/PHPStan/style/audit.
- **Acceptance criteria:** public failures are generic and correctly coded; operators receive safe correlation; logs diagnose without prohibited data; headers use secure defaults.
- **Explicit exclusions:** SIEM/monitoring vendor, business audit events and production alerting.
- **State transition:** B06 must be `DONE` before start.

## QMDB-P1-B08 — CLI, Scheduler, Worker, and Background Execution Foundation

- **Objective:** implement bounded console, scheduling and long-running worker lifecycle contracts.
- **Dependencies:** B07.
- **Relevant requirements:** operational, resilience, idempotency and workload-isolation foundation constraints.
- **Source files:** resilience NFRs; operational parameters; runbooks; event/outbox and failure catalogs.
- **Executable deliverables:** console command registry, scheduler foundation, worker loop/lifecycle, signal handling, graceful shutdown, job context/correlation, retry contracts and worker tests.
- **Tests and commands:** command registration/exit; schedule due/not-due; signal/shutdown; bounded work; retry/backoff classification; correlation; poison failure behavior; quality gate.
- **Acceptance criteria:** workers stop gracefully, do not retry indefinitely, expose truthful failure and do not contain domain jobs or depend on an unselected queue provider.
- **Explicit exclusions:** production scheduler, Redis Streams integration, domain outbox handlers and business jobs.
- **State transition:** B07 must be `DONE` before start.

## QMDB-P1-B09 — View Rendering, Localization, RTL, and Theme Foundation

- **Objective:** establish secure server-rendered presentation, translation, Arabic/RTL and accessible theme foundations.
- **Dependencies:** B08.
- **Relevant requirements:** approved accessibility/localization/secure-output foundation constraints.
- **Source files:** accessibility/localization NFR; accessibility acceptance scenarios; product identity/terminology; privacy/security output rules.
- **Executable deliverables:** secure PHP template renderer, contextual escaping, translation catalog/lookup, locale resolution, document direction/RTL, design/theme tokens, light/dark/high-contrast foundations, accessible base layout and rendering tests.
- **Tests and commands:** escaping/context misuse; missing translation/fallback; locale allowlist; English/Arabic direction; mixed text/numerals; contrast/theme tokens; semantic/keyboard baseline; quality gate.
- **Acceptance criteria:** untrusted output is escaped, Arabic/RTL works without layout-semantic inversion, preferences are accessible, and no business page is implemented.
- **Explicit exclusions:** product dashboard, domain forms, frontend framework and finalized visual brand assets.
- **State transition:** B08 must be `DONE` before start.

## QMDB-P1-B10 — CI Quality Gates and Engineering Verification

- **Objective:** automate reproducible engineering and supply-chain assurance.
- **Dependencies:** B09.
- **Relevant requirements:** QMDB-NFR-SUP-001 plus maintainability/testability gates.
- **Source files:** supply-chain/quality NFRs; control catalog; Definition of Ready/Done; ADR-055.
- **Executable deliverables:** CI workflow, automated tests, PHPStan/style, Composer validation/audit, secret-scanning integration where available, architecture tests, documentation-link check, build verification and release-artifact foundation.
- **Tests and commands:** clean CI run; lock validation; advisory behavior; workflow least privilege; cache/artifact inspection; deliberate failing-gate proof.
- **Acceptance criteria:** CI blocks failed required gates, dependencies are reproducible, reports are retained safely, and no deployment secret/provider is embedded.
- **Explicit exclusions:** production deployment and arbitrary external service commitment.
- **State transition:** B09 must be `DONE` before start.

## QMDB-P1-CLOSE — Engineering Foundation Verification

- **Objective:** prove P1 conformance and decide whether P2 may start.
- **Dependencies:** B01–B10 `DONE`.
- **Relevant requirements:** all 14 P1-mapped requirements and cross-cutting foundation constraints.
- **Source files:** all P1 artifacts/evidence; freeze manifest; implementation map; Definition of Ready/Done; P2 entry criteria.
- **Executable/review deliverables:** close report, requirement/control evidence matrix, actual command results, risk/decision updates and explicit P2 authorization or blocker statement.
- **Tests and commands:** clean installation; strict Composer gate; unit/integration/architecture; MySQL connection; migration/seed framework; HTTP/CLI smoke; logging/secure errors; worker lifecycle; localization/RTL/accessibility; CI quality gates.
- **Acceptance criteria:** 14/14 mapped requirements have accepted evidence; no critical/high foundation violation or P2 blocker; business domain table count remains zero; state is truthful.
- **Explicit exclusions:** P2 implementation.
- **State transition:** `IN_REVIEW → VERIFIED → DONE` followed only by explicit QMDB-P2 authorization.

## Exact next action

Execute only [QMDB-P1-B01](prompts/QMDB-P1-B01-core-php-repository-and-runtime-foundation.md). Do not execute a later P1 batch in the same change.

