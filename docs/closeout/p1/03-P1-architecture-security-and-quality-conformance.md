# QMDB P1 Architecture, Security, and Quality Conformance

| Area | Status | Evidence |
| --- | --- | --- |
| Core PHP 8.5 modular monolith | CONFORMANT | runtime guard, PSR-4 and architecture tests |
| Explicit dependency composition | CONFORMANT | PSR-11 container and module-graph tests |
| HTTP boundary | CONFORMANT | PSR-7/15 kernel, route/middleware and socket tests |
| MySQL/InnoDB authority | CONFORMANT | 8.4.11 server verification and integration tests |
| Runtime/schema privilege separation | CONFORMANT | distinct non-root identities and scoped grants |
| Migration governance | CONFORMANT | immutable checksums, named locks, ledger and rollback rehearsal |
| Secret and error safety | CONFORMANT | redaction, generic public errors, Gitleaks and Trivy |
| Operational observability | CONFORMANT | bounded JSON logs and server-generated correlation IDs |
| Background lifecycle | CONFORMANT | bounded worker, fail-closed production policy and claim ownership |
| Presentation/localization | CONFORMANT | escaped templates, catalog parity, Arabic RTL and fallback routes |
| Accessibility foundation | CONFORMANT | semantic structure, focus, reduced motion, high contrast and tests |
| Progressive interaction | CONFORMANT | same-origin GET fragments, stale-response cancellation and safe modal |
| CI/supply chain | CONFORMANT | immutable actions, lockfiles, scanners, SBOM, licences and artifact checks |
| P0 scope/freeze | CONFORMANT | 82 hashes unchanged; no unauthorized domain implementation |

## Security boundary summary

The current public surface exposes foundation information and health only. There is no authentication or business
mutation endpoint in P1. Runtime secrets are lazy, redacted, excluded from safe projections, and not stored in committed
environment files. SQL remains confined to MySQL infrastructure/schema adapters, and production schema mutation cannot
be triggered through HTTP.

## Accessibility and localization boundary

Automated tests cover semantic layout, focus restoration, no-JavaScript fallbacks, Arabic direction, safe fragments,
theme allowlisting, reduced motion and failure announcements. Manual assistive-technology, zoom and forced-colour review
remains a production UI release gate because it requires an interactive target/browser matrix.

## Resilience boundary

Transactions retry only classified deadlock/serialization failures and never assume external side effects are safe to
repeat. Worker and scheduler execution are bounded, at-least-once semantics are explicit, stale ownership is rejected,
and continuous production workers fail closed without PCNTL when configured to require it.
