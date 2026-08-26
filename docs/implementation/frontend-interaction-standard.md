# QMDB Frontend Interaction Standard

## Interaction model

QMDB uses server-rendered initial pages with progressive enhancement. Native `fetch()` is preferred over adding jQuery solely for AJAX. Requests are same-origin by default, send explicit `Accept` headers, validate response content types, and consume either structured JSON or trusted server-rendered fragments.

## Fetch lifecycle

Every asynchronous region implements loading, success, empty, stale, and recoverable failure states. Superseded reads use `AbortController`; debounced searches ignore stale responses; only safe idempotent reads may retry under a bounded policy. Mutations are never automatically retried without an idempotency mechanism.

After authoritative success, only affected rows, counters, cards, or panels are replaced. Filters, pagination, scroll position, and unaffected state remain stable where appropriate. Significant changes are announced through accessible live regions, and focus is preserved.

## Accessible modal rules

A modal has one bounded purpose and never hosts a full dashboard, long workflow, complex scoring session, large report, or deep navigation. It provides correct dialog semantics, an accessible title, focus entry/trapping/restoration, keyboard-only operation, an explicit close control, clear primary and cancel actions, unsaved-change protection, screen-reader support, high-zoom reflow, and RTL behavior. Nested modals are prohibited. A failed submission keeps the modal open and exposes the authoritative server error.

## Mutation and persistence rules

One asynchronous mutation maps to one authenticated and authorized application command. The server validates workspace scope, input, current state, expected version, and resource identity; opens a short transaction; performs changes plus later-owned audit/outbox work; commits; and returns authoritative state. Duplicate protection uses idempotency keys, optimistic versions, unique constraints, state preconditions, or transaction locks. A stale update produces a controlled conflict and never silently overwrites newer data.

Transactions begin only after final submission. They never span form display, a confirmation prompt, user think time, an upload, an external request, a modal lifecycle, browser navigation, or an SSE stream. Retryable callbacks contain no irreversible external side effect.

## Live and resilient operation

Server-Sent Events are preferred for one-way competition status, projected scoreboards, judge progress, registration counters, result publication, and operational announcements. Controlled polling is a fallback and must not be aggressive. Critical official workflows retain practical non-JavaScript server-rendered behavior.

## Deferred background-work boundary

A future Fetch/AJAX mutation may hand deferred work to the background boundary only after authentication, authorization, tenant scope, authoritative-state validation, and the immediate database transaction have succeeded. Where deferral is genuine, the response may use `202 Accepted` and a safe public job reference. That reference is not a queue reservation token, does not grant access, contains no payload or tenant secret, and remains subject to normal server authorization.

The browser never selects a job class, handler service, queue, worker name, scheduler task ID, retry count, or reservation token. It also cannot start a worker, execute the scheduler, or trigger migration commands through HTTP. P1-B08 intentionally exposes no public job-status endpoint.

A modal may submit deferred work, then close or move to an accessible processing state after the server confirms acceptance. It never holds a database transaction open, waits for the worker to finish, blindly retries the mutation, or assumes completion. The UI retains the request ID and later reconciles authoritative state through controlled refresh, SSE, or bounded fallback polling; aggressive polling is prohibited.

SSE may later project accepted, processing, completed, or failed state and related counter/workflow updates. The SSE service does not execute jobs, and workers do not hold SSE connections. Worker-side changes pass through the owning application service and authoritative data boundary.

QMDB-P1-B09 is titled **View Rendering, Localization, RTL, Theme, and Progressive AJAX/Modal Foundation**. It owns the first presentation implementation of these rules; P1-B08 adds no JavaScript, modal, polling, partial-rendering, or SSE runtime.

## P2-B02 public mutation protocol

P2-B02 authorizes one narrowly owned mutation client for registration and email-verification forms. Only a form marked
`data-qmdb-progressive-form` inside an approved form region can be intercepted. The action must be same-origin POST;
file inputs, `formaction`, missing CSRF, and missing registration/resend idempotency values are rejected by the fragment
policy. The client sends URL-encoded content with same-origin credentials, `redirect: error`, `X-QMDB-CSRF`, and the
applicable `Idempotency-Key`.

One in-flight submission is allowed per form. There is no automatic retry. A successful authoritative response replaces
only the governed region and focuses completion content. Validation fragments focus the accessible error summary and
retain only safe email input. Unexpected failure preserves the form, clears every password control, restores controls,
and announces a validated request reference when supplied. Ordinary form POST remains the no-JavaScript path.

## Security authority

The browser is never authoritative for scores, deductions, results, certificates, registration eligibility, guardian authority, consent, organization verification, permissions, workspace scope, conflicts of interest, official corrections, canonical Qur’an text, or moderation decisions. Problem-details responses remain generic at public trust boundaries and detailed only in protected operational telemetry.

## P2-B03 authenticated mutation protocol

Login extends the approved same-origin progressive POST client with one validated `X-QMDB-Navigate` value. The client
accepts only an origin-relative path, rejects protocol-relative, control-character, and cross-origin values, and retains
ordinary POST/redirect behavior without JavaScript.

Session and device revocation use the single global dialog and ordinary confirmation-page links as fallback. A returned
revocation form must include action-bound CSRF and `expected_version`; the fragment policy rejects a revocation mutation
without either control. Successful enhanced revocation may replace only `#account-security-session-panel`, closes the
dialog only after that replacement succeeds, restores focus to the account-security heading, and announces completion.
Failure leaves the dialog open and exposes only a bounded request reference. Logout remains a POST form and may navigate
only after authoritative success. No authentication value is written to browser storage and no mutation is retried.

## Correlated failure handling

Every same-origin Fetch client reads `X-Request-ID` and, for `application/problem+json`, the `request_id` extension. When an unexpected operation fails, the client displays that value as a support reference and associates it with the accessible error summary. Header and body values are expected to match; a missing or malformed reference is handled as an unavailable support reference and is never synthesized from user data.

Clients never render exception messages, stack traces, SQL errors, filesystem paths, database connection details, secret names, or secret values. Field validation remains distinct from an unexpected-system failure. Error summaries use an accessible live region, receive focus when appropriate, and do not require a full-page reload solely to obtain a reference. Superseded asynchronous responses cannot replace newer content.

A failed modal mutation keeps the modal open, preserves only safe user-entered values, displays the generic failure and request reference, and restores focus to the modal summary or relevant field. The modal closes only after authoritative server success. State-changing requests are not automatically duplicated or retried without server-backed idempotency protection.

Operational telemetry never contains form bodies, full AJAX payloads, uploads, search text or query-string contents by default, authorization/cookie headers, CSRF tokens, passwords, guardian documents, score values, or identity evidence. Correlation identifiers—not copied payloads—bridge a user-visible failure to restricted logs.

## P1-B08 boundary

This standard records frontend implementation constraints. QMDB-P1-B08 adds server-side console/background execution contracts and the deferred-work boundary only; it adds no production JavaScript, AJAX endpoint, HTML-fragment renderer, modal component, SSE endpoint, public job-status route, business idempotency record, or optimistic-concurrency response format.

## P1-B09 implemented foundation

P1-B09 implements the first server-rendered presentation boundary. English and Arabic full pages are authoritative and remain usable without JavaScript. Same-origin read-only GET enhancement uses native Fetch with `text/vnd.qmdb.fragment+html`, `X-QMDB-Fragment: 1`, the active locale header, content-type validation, one approved fragment root, executable-markup rejection, AbortController cancellation, and stale-response protection.

The system-status card refreshes only its bounded target. System information may open in one global native dialog with an ordinary page link as fallback. Loading, failure, request reference, focus entry, close, Escape, restoration, and nested-modal prevention are controlled. Theme switching stores only an allowlisted `qmdb.theme` value; no identity, locale, workspace, cookie, or token is read or stored.

P1-B09 deliberately defers mutation forms, CSRF, idempotency transport, optimistic-concurrency responses, validation fragments, user preference persistence, SSE, polling fallback, background-job progress, and business-module modals. No state-changing request may be intercepted until its owning controls are approved and implemented.
