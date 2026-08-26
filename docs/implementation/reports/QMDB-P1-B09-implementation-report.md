# QMDB-P1-B09 Implementation Report

## Project

Qur’an Memorizer DB (`QMDB`)

## Frozen Baseline and approved change

- Source baseline: `QMDB-BL-001`
- Frozen baseline: `QMDB-P0-FRZ-001`
- Approved post-freeze change: `QMDB-CR-001 — Asynchronous Progressive Interaction and Modal UX Standard`
- Frozen paths verified: 82 of 82 unchanged

## Phase, batch, status, and execution date

- Phase: P1 — Engineering and Repository Foundation
- Batch: QMDB-P1-B09 — View Rendering, Localization, RTL, Theme, and Progressive AJAX/Modal Foundation
- Execution date: 2026-08-25
- Status: `COMPLETE` — accepted by QMDB-P1-CLOSE on 2026-08-26

> **Final acceptance — 2026-08-26:** The blocked host-state narrative below is retained for traceability and is
> superseded. Server rendering, English/Arabic catalog parity, RTL, theme-persistence boundaries, reduced motion,
> accessible dialog behavior, safe fragments, and all 23 frontend tests passed under Node.js 24.19.0. Manual
> assistive-technology and forced-colour evidence remains a later UI/release gate. See
> [the P1 verification report](../../closeout/p1/02-P1-executable-verification-report.md).

Executable source, tests, and PHP 8.2 compatibility evidence are present. Completion is blocked because P1-B01 through P1-B08 remain formally incomplete, PHP 8.5/locked PHPUnit 13 acceptance is unavailable, approved MySQL LTS evidence is unavailable, and an interactive browser connection was unavailable. P1-B10 is not authorized.

## Prerequisite verification and corrections

- Preserved the existing uncommitted P1 working tree without reset, stash, revert, or commit.
- Initial `composer quality` failed at the normal platform check: PHP 8.2.12 does not satisfy PHP `^8.5`.
- Initial `composer test:mysql` failed because locked PHPUnit 13 requires PHP 8.4.1 or newer; the approved MySQL environment also remains absent.
- Node 25.2.1 and npm 11.6.2 are available. The first npm install attempt failed with `ECONNRESET`; the bounded retry succeeded and npm generated the lock file.
- Updated legacy B07/B08 architecture and route expectations for the approved presentation module and frontend surface.
- Made presentation catalog/view construction lazy for non-HTTP roots while retaining HTTP-bootstrap validation.
- Renamed the generic `ViewData::get()` accessor so it cannot be mistaken for a container/service locator.
- Preserved strict JSON/problem CSP while adding nonce-based HTML/fragment CSP.

## Implementation inventory

### Production source

- 33 production PHP files added for localization, presentation, request context, response negotiation, nonce handling, middleware, controllers, and `foundation.presentation`.
- 6 established production PHP/routing files materially updated for module composition, current-batch metadata, request attributes, CSP, HTTP services, and routes.
- 13 registered PHP views: one layout, three pages, two fragments, and seven components.
- Two explicit translation catalogs with 52 matching keys each.
- Six CSS files providing foundation/semantic/component tokens, responsive logical layout, themes, reduced motion, forced colors, and utilities.
- Nine native ES modules for bootstrap, Fetch, fragment policy, coordination, partial refresh, modal, focus, live-region, and theme behavior.

### Composer and npm changes

- Composer runtime dependencies: none added.
- Composer scripts added: `frontend:install`, `frontend:quality`; `quality` now includes frontend validation.
- npm runtime dependencies: none.
- Development dependency: `jsdom` 29.1.1, resolved by npm with 38 transitive development packages.
- Package mode: ES modules; no bundler or build pipeline.

## Presentation foundation

- Locales: `en`, `ar`; English fallback; Arabic RTL.
- Resolution: `lang`, same-origin `X-QMDB-Locale`, bounded `Accept-Language`, English.
- View registry: 13 explicit name-to-file registrations; no scanning or request-selected paths.
- Full pages: home, system about, system status.
- Fragments: system-about dialog and system-status card.
- HTML routes: 3; fragment-capable routes: 2.
- Themes: system, light, dark, high-contrast, emerald-and-gold.
- CSP: one secure nonce per request; nonce-bearing static theme bootstrap only; external production scripts are ES modules.

## Progressive interaction and modal capabilities

- Same-origin native Fetch requires `text/vnd.qmdb.fragment+html` and `X-QMDB-Fragment: 1`.
- Fragment policy rejects executable elements, event attributes, dangerous URLs, external/mutation forms, multiple roots, and the global dialog shell.
- One active request per interaction key; superseded requests abort and stale results cannot replace current content.
- Status refresh replaces only the status card and preserves existing content on failure.
- One read-only native dialog supports feature-detected enhancement, loading, safe content insertion, title update, explicit close, Escape, request abort, fallback link, safe request reference, failure focus, and focus restoration.
- No-JavaScript links expose the same about and status content as full pages.
- Mutation boundary remains GET-only; no mutation, CSRF, retry, polling, SSE, or background-job route was added.

## Security and accessibility controls

- Contextual UTF-8 text and attribute escaping; internal-only trusted rendered HTML.
- Safe relative application URLs and query construction.
- HTML-aware CSP contains no `unsafe-inline`, `unsafe-eval`, wildcard script source, CDN, or external font host.
- JSON/problem responses retain the strict non-document CSP.
- Global skip link, header/main/footer landmarks, labelled controls, one polite live region, non-color status text/symbols, one labelled dialog, visible focus, reduced-motion handling, high-contrast theme, responsive reflow, and RTL logical layout.
- Browser storage is limited to allowlisted `qmdb.theme`; frontend code reads no cookies or authentication tokens.
- Automated checks do not constitute a claim of complete WCAG 2.2 AA conformance.

## Tests and command results

- New B09 PHP test files: 4; new B09 PHP test cases: 20.
- Frontend test files: 6 plus one DOM support module; frontend test cases: 23.
- Repository PHP compatibility suite: 660 tests, 33,250 assertions, 13 expected MySQL skips on PHP 8.2.12 with PHPUnit 11.5.44.
- B09 focused PHP suite: 20 tests, 243 assertions.
- JavaScript syntax: PASS, 17 production/test/script files.
- Frontend tests: PASS, 23 of 23.
- npm audit: PASS, zero vulnerabilities.
- PSR-12 compatibility: PASS, 525 files.
- PHPStan maximum level compatibility: PASS, no errors.
- PHP syntax: PASS, 539 PHP files.
- Composer strict validation/audit/PSR-4: PASS / PASS with zero advisories / PASS.
- Normal Composer install/quality/locked tests: BLOCKED by PHP 8.2.12 versus PHP `^8.5` and locked PHPUnit 13.
- MySQL tests: 13 skipped in compatibility execution; approved MySQL LTS environment unavailable.
- Git diff check: PASS.
- Frozen baseline recheck: PASS, 82 of 82.

## Real HTTP verification

The diagnostic compatibility router exercised the actual PSR-7/PSR-15 runtime over a local socket while the production entrypoint kept its PHP 8.5 fail-closed guard.

- English home: 200 HTML, `lang=en`, `dir=ltr`, nonce CSP, request ID, theme/language controls, modal shell, status card, and no-JavaScript content.
- Arabic home: 200 HTML, `Content-Language: ar`, `lang=ar`, `dir=rtl`, meaningful Arabic.
- About/status pages: 200 complete HTML.
- About/status fragments: 200 QMDB fragment media type, marker header, one safe root, no document/script/style shell.
- API about/liveness: 200 JSON; readiness: expected 503 `not_ready` because approved MySQL is unavailable.
- HEAD: 200; OPTIONS: 204 with Allow; invalid encoded target: 400; missing route: 404; unsupported method: 405.
- Local JavaScript asset: 200 `application/javascript`.
- Interactive visual/browser inspection: NOT RUN; no browser connection was available to the in-app browser runtime.

## Architecture boundaries and known limitations

- Domain and application layers do not depend on presentation or templates.
- Controllers remain constructor-injected and contain no template paths, HTML bodies, CSS, JavaScript, PDO, or container access.
- The frozen decision register, roadmap, backlog, and quality-attribute register were not modified. B09 decisions are recorded here and in dynamic standards because the freeze has higher authority than the batch request to edit the locked decision register.
- PHP 8.2 compatibility evidence is diagnostic only. Required acceptance remains PHP 8.5 with the locked dependency set.
- Approved MySQL LTS, PCNTL/POSIX evidence inherited from prerequisites, and interactive browser/manual accessibility evidence remain unavailable.

## Open risks and next batch

Dynamic open-decision and risk registers record browser support/fallback, user preference persistence, mutation/CSRF/idempotency/concurrency, asset pipeline, CSP reporting, SSE/polling, and manual accessibility verification as unresolved.

- Next batch: QMDB-P1-B10 — NOT READY / NOT AUTHORIZED
- Remaining P1 batches: QMDB-P1-B10; QMDB-P1-CLOSE

## Final acceptance addendum — 2026-08-26

The historical next-batch statements above are superseded. Batch status is `COMPLETE`.
