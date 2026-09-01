# QMDB-P3-B02 Implementation Report

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Source baseline | QMDB-BL-001 |
| Product freeze | QMDB-P0-FRZ-001 |
| P2 freeze | QMDB-P2-FRZ-001 |
| Authorization | QMDB-P3-B02-EXEC |
| Batch | Person, Memorizer, Reciter, Competitor, and Guardian Profile Foundation |
| Resolved P3 identity | P3 — Nigerian Geography, Organizations, People, and Guardianship |
| Required previous batch | QMDB-P3-B01 — Nigerian Administrative Geography and Jurisdiction Registry |
| Next batch | QMDB-P3-B03 — Organizations, Schools, Groups, Mosques, and Branches Registry |
| Execution date | 2026-09-01 |
| Starting revision | `455142462e966c7d87fcbe1e7a759ca3b13ca025` |
| Status | COMPLETE |

## Delivered scope

The `people.profiles` module creates global, non-public Person records with opaque public IDs and non-sequential
registry codes. An active Account may have exactly one active self Person link. Names are Unicode-safe and versioned;
primary name history, preferred and Arabic variants, nationality, origin and residence are private.

The delivered profile surface is limited to a signed-in Account and its authorized self-declared dependent
relationships. It supports bounded role profiles, Memorizer progress, private dependent creation/update, and guarded
relationship revocation. Sensitive profile update, dependent creation and guardianship revocation consume P2
step-up grants. All private responses carry no-store and no-referrer handling.

## Security and data controls

- MySQL constraints enforce active self-link uniqueness, one active name per type, active guardianship uniqueness,
  relationship distinctness, historical status transitions and foreign-key integrity.
- Role and guardian checks are enforced in the application transaction; minor/guardian limits, idempotency,
  optimistic versions and bounded rate limits are applied before state transition.
- Profile data is not placed in audit metadata or security notification content. The UI exposes opaque public IDs only,
  and profile templates do not use browser storage.
- Origin/residence selectors reuse the P3-B01 State/FCT directory and lazily request only the selected LGA/Area
  Council list. The initial form never loads all 774 level-two records.

## Deferred scope and policy

The technical age gate is configurable (`18` by default) and is not a legal determination. Self-declared
profile-management guardianship does not certify legal status and cannot authorize consent or public disclosure.
OD-011, OD-012, OD-016, OD-032 and field-protection review OD-058 remain controlling decisions. No merge, search,
public profile, contact, consent, organization affiliation, dependent Account or competition capability is present.

## Executable evidence

The test set covers domain validation, private-route architecture, catalog closure, schema constraints,
duplicate-link contention, account/profile isolation, geography parent checks and isolated MySQL concurrency.
The implementation source is committed in `193bce3d3b4bc71691fe6177f626daca6e252110`; the controlled P0
documentation-extension correction is committed in `6f8e242740f7127446907208e10cc672ef94a4a2`. Final engineering-freeze
and release identifiers are recorded by the closeout evidence commits.

## Module, schema and route composition

The `people.profiles` module is composed after geography and before the HTTP/console adapters. Four forward-only
migrations add the Person root/name/link foundation, geography associations, Person roles/Memorizer progress, and
guardianship/private operation results. The schema uses InnoDB foreign keys, active-state generated markers, unique
constraints, supporting indexes and optimistic version columns. No existing migration was edited after application.

The private route surface contains self-profile view/create/edit/update, role activation/deactivation, Memorizer
progress, dependent list/create/view/edit/update/progress, and guardianship-revocation routes. Every mutation is
classified by the production route-security policy and requires the relevant CSRF action. The server-rendered English
and Arabic views provide normal full-page forms; the only progressive update is a safe, whitelisted geography-child
lookup.

## Person and relationship model

- **Person and names:** global private Person roots use opaque public IDs and non-sequential registry codes. Primary,
  preferred and Arabic names preserve Unicode and history; no global uniqueness or public discovery is claimed.
- **Account links and access:** one active Account SELF link per Account and Person; profile access is exact self-link
  or exact active guardianship only. Workspace roles, P2 privileged access and public IDs do not bypass it.
- **Roles and progress:** `MEMORIZER`, `RECITER`, `COMPETITOR` and `GUARDIAN` are Person-role profiles, not
  authorization roles. Competitor is not competition registration. Memorizer progress is declared, not verified.
- **Guardianship:** a Guardian role plus active self-declared relationship permits bounded minor-profile management;
  it is not legal certification, consent or document verification. Step-up and last-active-guardian protection apply.

## Security, privacy and accessibility

Sensitive profile update, dependent creation and relationship revocation use action-bound P2 step-up. Mutations also
use transactions, optimistic versions, idempotency, rate limits and audit/notification integration. Names and birth
dates are absent from plaintext fingerprints, audit metadata and notifications. Private response handling uses
`no-store` and `no-referrer`; no profile browser storage, public cache or Person search route exists.

Labels, instructions, error feedback, ordinary POST behaviour and keyboard-operable controls are present on the
private profile forms. Arabic names and the Arabic locale preserve RTL rendering. The geography selector begins with
the 37 State/FCT rows and requests only the chosen child list, avoiding an initial 774-row payload.

## Validation scope

Focused unit, architecture, frontend, integration and MySQL tests cover input validation, uniqueness, status/lifecycle
rules, cross-account denial, revoked/last-guardian denial, relationship/role checks, idempotency, route policy and
concurrency. The verification CLI checks schema state without repair. The release pipeline also rechecks PHP syntax,
static analysis, style, full quality/MySQL/frontend suites, P0/P1/P2 governance and clean source state.

## Corrections and deferred evidence

The B02 closeout corrected deterministic foundation-test registration, batch metadata, P2 test-fixture teardown
ordering for the new Person foreign keys, P2 freeze controlled-extension accounting, and P0 documentation-freeze
accounting. The P0 correction preserves `QMDB-P0-FRZ-001` unchanged and accepts only the exact, hash-bound B02
documentation extension ledger; it does not rewrite the historical baseline. Production legal review of the age
threshold, adult dependent policy,
legal guardianship/document verification, consent, Person merge/claim/retirement, public profiles, organizations,
international geography, retention, physical assistive-technology and production-capacity evidence remain deferred.

## Pre-freeze validation evidence

- The serial Oracle MySQL suite passed: **89 tests, 1,733 assertions** in 12m54s. It reset, migrated, seeded,
  verified, ran all MySQL tests, then restored the canonical schema.
- Canonical schema evidence is clean: 34 forward-only migrations and four seeds are applied; schema verification,
  geography verification (811 areas: 36 States, FCT, 768 LGAs and six Area Councils), and People verification pass.
- Route-security verification reports 111 classified production routes, 53 mutation routes and 53 CSRF-protected
  mutations. P2 authorization, tenant-context, privileged-access, audit, tenant-repository and aggregate security
  verification pass.
- The frozen P0 verifier passes with 199 checks through
  `docs/project/p3-p0-freeze-extension-ledger.yaml`; the P2 freeze verifier passes with 7,062 checks.
- The PHP 8.5.10, Node 24.19.0 and MySQL 8.4.11 governed toolchain is in use. Composer validation, platform
  requirements, locked dependency audit and JavaScript syntax checks pass.

## Final local CI evidence

- `composer ci` passed all **34 recorded stages**. Its governed PHP quality suite passed **977 tests and 70,603
  assertions**; the frontend suite passed **53 tests**; and the isolated Oracle MySQL suite passed **89 tests and
  1,733 assertions** in 15m43s before restoring the canonical schema.
- Static analysis reported no errors; coding style checked 1,379 files; PHP syntax checked 1,474 files; Composer and
  npm dependency audits found no vulnerabilities. Gitleaks and Trivy repository scans passed with no leak or actionable
  repository finding.
- SBOM generation and validation passed (269 checks), and the runtime licence inventory reported 52 runtime packages,
  29 development packages, zero unknown runtime licences and zero items requiring review.
- The pre-closeout release archive built and verified from clean revision
  `8e4178cdc015060ed5da21cc5dadc7533710fc2f`: `qmdb-0.1.0-dev-8e4178cdc015.tar.gz`, SHA-256
  `e0b952655df1a786b3f53540edad18dab0659282319d57d6491d17875548b4e1`. Final engineering-freeze refresh and
  release verification follow this evidence update.
