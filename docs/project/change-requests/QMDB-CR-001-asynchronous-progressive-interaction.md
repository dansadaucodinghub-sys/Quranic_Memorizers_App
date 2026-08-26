# QMDB-CR-001 — Asynchronous Progressive Interaction and Modal UX Standard

| Field | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Source baseline | QMDB-BL-001 |
| Frozen baseline | QMDB-P0-FRZ-001 |
| Change class | MINOR |
| Decision status | Approved by explicit project-owner instruction |
| Approval date | 2026-08-25 |
| Implementation status | Applies to future frontend and interaction batches |
| Database impact | No schema change in QMDB-P1-B05 |
| Security impact | State-changing asynchronous requests remain fully server-authorized |
| Accessibility impact | Controlled modal and partial-refresh accessibility requirements are mandatory |

## Approved direction

Initial pages remain server rendered and usable through progressive enhancement. Future bounded interactions use native Fetch by default, replace only affected regions, preserve authoritative server state, and provide accessible loading, focus, error, and completion feedback. One-way live competition projections use Server-Sent Events, with controlled polling only as a fallback.

Critical login, registration, guardian-consent, judge-score, result-finalization, appeal, and certificate-verification workflows retain a practical server-rendered fallback. JavaScript enhancement may not become the sole authority or recovery path for an official operation.

## Modal boundary

Modals are limited to concise, single-purpose tasks. They require dialog semantics, an accessible title, keyboard focus entry/trapping/restoration, Escape handling when safe, explicit close and cancel controls, unsaved-work protection, high-zoom and RTL usability, and visible server errors. Nested modals and long multi-step workflows in dialogs are prohibited.

## Server-authority boundary

Authentication, authorization, workspace isolation, validation, record-state and concurrency checks, official calculations, audit handling, and required idempotency remain server-side for every asynchronous mutation. Client-side disabled controls are usability aids, not duplicate-submission controls.

## Transaction boundary

Each mutation is one bounded server request with a short transaction. No transaction may remain open while a modal is displayed, user input is pending, an upload or third-party call runs, an SSE stream remains connected, or a browser refresh waits. Retried transaction callbacks must not perform irreversible external side effects.

## Change-control note

This post-freeze approval is recorded without changing the frozen P0 manifest or locked P0 documents. The executable frontend implementation, CSRF design, fragment convention, idempotency storage, optimistic-conflict response, browser-history behavior, modal component, browser matrix, and SSE reconnection policy remain assigned to their owning future phases.
