# QMDB Presentation and Progressive Interaction Standard

## Authority and scope

This standard implements the approved QMDB-CR-001 direction for the QMDB-P1-B09 engineering foundation. It governs shared presentation mechanics only; business pages, authentication, mutations, CSRF, live transport, and user preference persistence remain outside this batch.

## Server-first rendering

- Every primary page is a meaningful server-rendered HTML document with ordinary links.
- JavaScript may enhance bounded reads but must not become the only path to content or navigation.
- Templates are selected only from the explicit immutable view registry. Request values never select a file.
- Templates receive one immutable `ViewData` object and approved rendering helpers; they do not receive the container, PDO, request globals, or extracted variables.
- Dynamic text and attributes are escaped for their HTML context. `SafeHtml` represents output created by trusted internal templates only.

## Localization and RTL

- Supported interface locales are explicitly `en` and `ar`; English is the safe default.
- Resolution order is a valid `lang` query value, valid same-origin `X-QMDB-Locale`, bounded `Accept-Language`, then English.
- Locale selection changes presentation only. It never changes authorization, ownership, workspace, or record state.
- Translation catalogs contain matching dot-separated keys and scalar plain text. They contain no HTML or request logic.
- Arabic pages use `lang="ar"`, `dir="rtl"`, meaningful translations, Arabic-capable system fonts, and CSS logical properties.

## Views, assets, and responses

- Full documents use `text/html; charset=utf-8`; fragments use `text/vnd.qmdb.fragment+html; charset=utf-8` and `X-QMDB-Fragment: 1`.
- HTML and fragments are `no-store`, `nosniff`, correlated with `X-Request-ID`, and receive `Content-Language`.
- Each fragment has exactly one `data-qmdb-fragment-root` and contains no document shell, script, style, external asset,
  inline event attribute, or nested modal. P2-B02 permits only its explicitly marked same-origin POST forms with CSRF and
  required idempotency controls; every other mutation form remains rejected.
- CSS and JavaScript are local static assets. There is no runtime frontend dependency, framework, bundler, CDN, or external font host.

## Design tokens and themes

- Foundation, semantic, and component tokens govern color, typography, spacing, borders, radii, elevation, motion, focus, widths, touch targets, and status semantics.
- Approved themes are system, light, dark, high-contrast, and emerald-and-gold.
- Only the allowlisted non-sensitive `qmdb.theme` preference may be stored in browser storage.
- Layout uses logical properties, reflows on narrow/high-zoom viewports, keeps visible focus, supports forced colors, and respects reduced motion and system color scheme.

## CSP and browser trust boundary

- One cryptographically secure nonce is generated per request and attached before response processing.
- HTML policy permits same-origin assets/connects and only the one nonce-bearing static theme bootstrap; it contains no `unsafe-inline`, `unsafe-eval`, wildcard origin, or external host.
- JSON and problem responses retain the non-document policy.
- The browser accepts fragments only from the current origin with the exact media type and marker, then applies the bounded fragment policy. This is defense in depth for trusted server output, not a general HTML sanitizer.

## Fetch and partial replacement

- Native Fetch sends same-origin credentials, fragment Accept, and current locale headers; redirects are errors.
- Requests are read-only GETs and are never automatically retried.
- One active request per interaction key is coordinated. A successor aborts its predecessor, and stale results never update the document.
- Partial refresh marks the target busy, replaces only the approved target, keeps existing content after failure, restores applicable focus, announces completion, and preserves fallback navigation.
- Public errors show generic localized text and a validated request reference only. Raw response HTML, exception messages, SQL, paths, cookies, and tokens are never displayed or logged.

## Accessible modal boundary

- One global labelled native dialog is used for concise read-only system information.
- Enhancement requires Fetch, AbortController, HTMLDialogElement, and `showModal`; otherwise the normal link navigates.
- Opening records the trigger, exposes loading state, fetches and validates one dialog fragment, updates the accessible title, and moves focus deliberately.
- Close button and native Escape behavior are supported. Closing aborts the request, clears transient content, resets state, and restores focus.
- Failure keeps the dialog open, displays a safe request reference when available, exposes the full-page fallback, focuses the error, and performs no blind retry.
- Nested modals and state-changing forms are prohibited.

## Accessibility verification

Automated checks cover landmarks, one page heading, language/direction, controls, live region, dialog labelling, non-color status, focus behavior, reduced motion, high contrast, and prohibited attributes. Manual verification is still required for screen readers, keyboard-only use, 200% and 400% zoom, RTL reading order, mobile viewports, forced/high contrast, reduced motion, and no-JavaScript operation. Automated evidence is not a claim of complete WCAG 2.2 AA conformance.

## P2-B02 registration and verification forms

P2-B02 implements the first approved state-changing progressive forms. Registration, verification confirmation, and
resend remain complete server-rendered pages and are never placed in a modal. The one authorized mutation client accepts
only same-origin POST, sends action-bound CSRF and required idempotency values, blocks duplicate submission, performs no
automatic retry, and consumes only validated form fragments or bounded problem details. Error focus, safe email
preservation, password clearing, request-reference presentation, English/Arabic parity and normal POST fallback are
executable requirements.

## Deferred boundaries

Authenticated/authorized tenant mutations beyond B02 still require their owning CSRF, idempotency, concurrency,
validation-fragment, audit and transaction decisions. SSE, bounded polling fallback, background-job progress,
browser-history policy, asset hashing/minification, user locale/theme persistence, and business modals remain deferred
to their owning phases.
## P2-B04 account-recovery presentation

Recovery request and reset are ordinary page workflows with progressive fragments, not modal workflows. English and
Arabic pages preserve semantic headings, associated labels and password guidance, focusable error summaries, live
status announcement, visible focus, RTL, reduced motion, forced colours, and keyboard operation. Invalid or expired
reset links render a safe request-another-link state without retaining the token.

## P2-B05 authenticator and step-up presentation

MFA challenge, authentication-security management, TOTP enrollment, passkey registration, factor revocation, recovery-
code status and action-scoped step-up are complete server-rendered workflows. JavaScript enhances only WebAuthn binary
exchange and the explicit recovery-code copy action; every non-WebAuthn action retains a normal form submission path.
The server decides which methods and protected controls are eligible. A hidden button or a forged client request never
substitutes for a valid, unconsumed step-up grant.

Pages preserve semantic headings, associated labels, field guidance, focusable error summaries, status announcements,
visible focus, keyboard operation, 400 percent reflow, reduced motion, forced colours and Arabic RTL. Recovery codes are
identified as one-time sensitive content and appear only once. Passkey cancellation, unavailable platform support and
failed ceremonies return bounded, localized guidance without exposing challenges or cryptographic details.

## P2-B07 workspace presentation extension

Authenticated page headers expose a text workspace status and a link to `/account/workspaces`. The selector has one
H1, named controls, explicit selected text, an accessible empty state, logical RTL ordering, visible focus inherited
from the design system, and no positive tabindex or switch modal. Full-page POST redirects and enhanced safe
navigation remain equivalent.

## P2-B08 privileged-access presentation extension

Privileged-access inventory, request, approval, activation, revocation, break-glass warning, and post-use review
workflows remain server-rendered pages with authenticated no-store responses. They use semantic headings, associated
labels, bounded guidance, error summaries, visible focus, keyboard operation, logical RTL, and localized English and
Arabic strings. The active-access panel has a direct end-access form and presents only safe status information.

State-changing actions use CSRF-protected ordinary forms and full-page confirmation fallback. Progressive enhancement
may update an approved fragment after an authoritative success, but it never retries a mutation, stores a permission
or privileged state in browser storage, or closes a confirmation modal until the server succeeds. Break-glass stays a
full-page warning flow because its safety-critical justification, incident reference, assurance, and confirmation must
remain available without JavaScript.
