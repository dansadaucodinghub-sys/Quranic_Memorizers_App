# P2 Accessibility and Progressive-Interaction Verification

## Automated evidence

- English and Arabic translation parity, `lang`/`dir`, and RTL foundations are exercised by presentation and frontend tests.
- Server-rendered pages remain the primary workflow; enhanced modal and fragment flows have full-page fallbacks.
- Native Fetch is used without jQuery, SPA routing or automatic mutation retry.
- Same-origin navigation, trusted-fragment allowlists, CSRF/idempotency/context-version headers, duplicate-submit guards and request-ID errors are tested.
- Focus is managed for opening, failure and successful modal completion. Failed mutations retain the dialog and fallback path.
- Browser storage is restricted to the allowlisted visual theme; no authentication, authorization, tenant context, role or permission authority is persisted.
- Reduced-motion, high-contrast/forced-colour foundations, semantic errors and keyboard-friendly controls are present and tested where deterministic automation is possible.

The frontend suite passed 30 syntax-checked JavaScript files and 51 tests. It covers registration, recovery, MFA, passkey, step-up, workspace switching, session/device revocation, safe fragments and progressive forms.

## Deferred manual evidence

No manual screen-reader, physical keyboard, 200%/400% zoom, forced-colour or supported-browser matrix result is claimed. These remain owned pre-production evidence items with automated safeguards and server-rendered fallback compensating controls, as recorded in the deferred-evidence register.
