# P2 Implementation Decisions

This controlled extension records implementation decisions made after `QMDB-P0-FRZ-001`. It does not edit or
supersede the frozen product decision register. Any conflict is resolved in favor of the frozen baseline and approved
change control.

## P2-ADR-001 — Stateless public-form CSRF and canonical origin

- **Status:** Approved and implemented by QMDB-P2-B02.
- **Context:** Registration and email verification are public mutations that cannot rely on the session foundation owned
  by B03 and must not trust forwarded or Host headers.
- **Decision:** Bind purpose-specific HMAC CSRF tokens to a random host-only HttpOnly `SameSite=Strict` nonce cookie and
  issuance time; production uses `__Host-` and `Secure`. Validate `Origin` when supplied against the configured canonical
  application origin, which also owns verification-link generation.
- **Rationale:** Provides replay-bounded same-origin protection without creating authentication state or deriving trust
  from attacker-controlled request routing data.
- **Consequences:** CSRF state is not a session, tokens are action-specific, and any later session integration must retain
  equivalent-or-stronger origin/cookie controls.
- **Security impact:** Mitigates cross-site mutation, cookie injection and poisoned verification links while keeping token,
  cookie and origin evidence out of logs.
- **Future review conditions:** Change requires B02/B03 compatibility, browser security, deployment-origin and regression
  evidence.

## P2-ADR-002 — Enumeration-resistant password authentication before sessions

- **Status:** Approved and implemented by QMDB-P2-B02.
- **Context:** B02 must provide production password verification for B03 without exposing a temporary login endpoint or
  authentication token.
- **Decision:** Use Argon2id with versioned policy, a process-local Argon2id dummy hash for unknown/ineligible accounts,
  generic invalid results, database-backed HMAC-keyed email/peer throttles, and a verified principal returned only to
  trusted application consumers. B02 creates no session or login route.
- **Rationale:** Equalizes observable authentication work and establishes a safe service boundary without crossing the
  session batch boundary.
- **Consequences:** B03 consumes the verified principal and owns rotation, device and cookie behavior; it must not bypass
  B02 throttling or eligibility checks.
- **Security impact:** Reduces account enumeration, credential stuffing and premature bearer/session exposure.
- **Future review conditions:** Password-policy or algorithm changes require rehash migration, timing, capacity, abuse and
  compatibility evidence.

## P2-ADR-003 — Single-use email-verification challenge lifecycle

- **Status:** Approved and implemented by QMDB-P2-B02.
- **Context:** Email-link scanners must not activate accounts, raw tokens must not be stored, and concurrent POSTs must
  never duplicate activation.
- **Decision:** Store only SHA-256 hashes of 256-bit random tokens; GET renders confirmation only; POST locks and consumes
  the pending challenge in the same transaction that verifies email, activates the account and appends one status event.
  Challenges expire, revoke, enforce bounded attempts, and permit only one pending challenge per email.
- **Rationale:** Separates link possession from state mutation and makes replay/concurrency behavior explicit.
- **Consequences:** Resend revokes the prior pending challenge, email is delivered only after commit, and valid completed
  replay returns a safe completion without a second effect.
- **Security impact:** Mitigates token-database disclosure, scanner activation, brute force and double activation.
- **Future review conditions:** Token format, expiry, attempt limits or delivery-channel changes require threat, privacy,
  concurrency, migration and operational evidence.

## P2-ADR-004 — Opaque rotating server-side browser sessions

- **Status:** Approved and implemented by QMDB-P2-B03.
- **Context:** Password verification must become browser authentication without exposing bearer/JWT authority or allowing
  parallel Fetch requests to invalidate one another during rotation.
- **Decision:** Store account/device-owned server sessions with UUIDv7 selectors and SHA-256 hashes of 256-bit secrets;
  use idle and absolute TTLs, throttled touch, optimistic rotation, one bounded previous-token grace window, and an
  account-row lock for active-session-limit enforcement.
- **Rationale:** Server-side lifecycle control permits immediate account/device/session revocation and keeps raw secrets
  outside persistence while preserving concurrent browser request safety.
- **Consequences:** The current session uses logout; remote session/device revocation is account-scoped and versioned;
  B04 recovery mutations must revoke relevant sessions after successful password reset.
- **Security impact:** Mitigates fixation, database token disclosure, stale-session reuse, cross-account revocation, and
  parallel-token overwrite.
- **Future review conditions:** Production TTL, limit, device semantics, anomaly policy, or cookie changes require load,
  threat, privacy, browser, migration, and rollback evidence.

## P2-ADR-005 — Transactional account recovery and scheduled security notification

- **Status:** Approved and implemented by QMDB-P2-B04.
- **Context:** Recovery must resist account enumeration, database token disclosure, email-link scanning, replay,
  concurrency, stale sessions, mail outages, and ambiguous SMTP outcomes.
- **Decision:** Use generic recovery responses; server-generated idempotency; HMAC email/peer/request buckets; 256-bit
  tokens with SHA-256-only persistence; one pending challenge/account; non-consuming GET; authoritative locked POST;
  Argon2id hashing before the transaction; atomic credential replacement, challenge/event changes, all-session
  revocation, and deduplicated notification intent; preserve devices, clear session, rotate CSRF, and do not auto-login.
- **Delivery decision:** Deliver `PASSWORD_RESET_COMPLETED` intents through
  `identity.security_notifications.deliver` using bounded batches, execution-owned leases, optimistic versions, capped
  retry, and at-least-once semantics. Intent deduplication does not guarantee exactly-once SMTP.
- **Presentation decision:** Recovery and reset remain full-page progressive workflows with no modal dependency and a
  complete no-JavaScript fallback.
- **Boundary:** Recovery and notification histories are append-oriented operational evidence, not the future tamper-
  evident audit ledger.
- **Consequences:** Mail delivery never holds the reset transaction; a provider may deliver a duplicate after an
  ambiguous outcome; deployment must configure and monitor the CLI scheduler.
- **Future review conditions:** MFA/passkey recovery, provider, retention, CAPTCHA/trusted-proxy, password history,
  fraud review, and audit checkpoint decisions require their owning threat, privacy, operations, and test evidence.

## P2-ADR-006 — Conservative MFA, WebAuthn and action-scoped step-up profile

- **Status:** Accepted for QMDB-P2-B05 under `QMDB-RECOVERY-RUN-001`.
- **Context:** The frozen baseline requires MFA, passkeys, recovery codes and step-up but deliberately leaves provider,
  attestation, hardware, key-custody and organization-enforcement choices for controlled implementation.
- **Assurance decision:** Sessions use `PRIMARY`, `MULTI_FACTOR` and `PHISHING_RESISTANT`. Password is primary; TOTP and
  recovery code are secondary; a verified passkey assertion is phishing resistant. Server records, never client claims,
  determine the level.
- **Transaction decision:** Login MFA, passwordless passkey and step-up use opaque, expiring, attempt-bounded,
  purpose-bound transactions. The browser holds an HttpOnly verifier; MySQL holds only its keyed hash. Superseded
  pending WebAuthn ceremonies are revoked before replacement.
- **Step-up decision:** Grants are account-, session- and target-action-bound, short lived and single use. Strong factor
  administration consumes the grant in the protected mutation transaction. Password is not offered where an action
  requires multi-factor or phishing-resistant assurance.
- **TOTP decision:** Use the interoperable SHA-1/30-second/6-digit profile with one-step drift, atomic last-counter
  advancement and Sodium authenticated encryption under an externally supplied versioned key.
- **Recovery-code decision:** Generate ten independent codes from 16 random bytes, normalize with Crockford-style
  unambiguous symbols, display plaintext once and persist only keyed verifiers. Consumption is atomic.
- **WebAuthn decision:** Use exact configured RP ID and allowed-origin matching, required user verification, `none`
  attestation, discoverable credentials for passwordless use, stored public keys only, signature-counter risk handling,
  and credential suspension on detected counter regression.
- **MFA lifecycle decision:** Enabling requires an active strong authenticator and creates one recovery-code set;
  removing the final authenticator while MFA is enabled is denied. Policy and authenticator changes create durable
  security-notification intents and revoke affected sessions where required.
- **Presentation decision:** Server-rendered English/Arabic workflows are authoritative. JavaScript is bounded to
  WebAuthn encoding/transport and explicit clipboard copy. One-time secrets never enter browser storage.
- **Boundary:** B05 does not implement organization/workspace/role MFA enforcement, assisted recovery, attestation
  metadata services, hardware certification, conditional mediation, audit-ledger claims or B06 authorization policy.
- **Consequences:** Production must supply HTTPS RP/origin and managed encryption-key evidence; physical authenticators,
  target browsers and assistive technologies still require controlled manual verification before release.

## P2-ADR-007 — Explicit deny-by-default scoped authorization catalog

- **Status:** Approved and implemented by QMDB-P2-B06.
- **Context:** P2 requires executable least-privilege authorization without conflating authentication assurance,
  workspaces, geography, organizations or future business-resource authority.
- **Catalog decision:** Permission and system-role codes are explicit, immutable, seed-managed and checksum verified.
  Wildcards and role inheritance are prohibited. The production seed creates ten permissions, seven roles and 27
  mappings, but creates no role assignments.
- **Scope decision:** Platform and workspace scopes are distinct and cannot be coerced. Workspace decisions require a
  trusted `TenantContext`, an active workspace and active membership. Geography and business-resource scopes remain
  with their owning modules.
- **Decision decision:** Authorization denies by default. An allow requires a known active permission, active account,
  active role, active assignment, exact scope and sufficient server-derived authentication assurance. Assurance alone
  never grants a permission.
- **Administration decision:** Role mutations consume action-, account- and session-bound single-use step-up grants in
  the mutation transaction. Platform mutations require phishing-resistant assurance; workspace mutations require
  multi-factor assurance. An actor may delegate only a subset of the permissions they currently possess, with a
  locking in-transaction recheck to close revocation races.
- **Continuity decision:** Revocations preserve assignment history. The final usable platform security administrator and
  final workspace owner are protected under concurrency. Production bootstrap of the first platform administrator is
  deliberately not implemented and remains a deployment blocker.
- **Safety decision:** Authorization denials expose a generic HTTP 403 response. Assignment and revocation notifications
  reuse the existing durable scheduled-delivery system.
- **Boundary:** Custom roles, role-management UI, business-resource permissions and workspace switching are deferred;
  workspace switching belongs to QMDB-P2-B07.
- **Future review conditions:** Catalog expansion, explicit denies, inheritance, delegated administration, dual control,
  caching or emergency access require their owning approval, threat, privacy, migration and executable test evidence.

## P2-ADR-008 — Session-authoritative Account Workspace Tenant Context

- **Status:** Approved and implemented by QMDB-P2-B07.
- **Context:** Workspace operations require one explicit, trustworthy tenant boundary without allowing a public ID,
  browser state, device, or account-wide default to become authority.
- **Session decision:** Store zero or one selected workspace/membership pair on each active `user_sessions` row. New
  sessions start unselected. Selection and clearing use a dedicated positive optimistic context version and do not
  change authentication token/version/assurance state.
- **Integrity decision:** Enforce `(workspace_id, user_account_id, membership_id)` composition with a MySQL candidate
  key and composite foreign key plus all-or-none selection checks.
- **Resolution decision:** Resolve only ACTIVE account, session, workspace, and membership state after authentication.
  Clear invalid stored selection, increment its version, and never choose a replacement workspace automatically.
- **Client decision:** Workspace public ID is a bounded lookup selector only. No authoritative workspace cookie/header,
  local/session storage, or BroadcastChannel payload exists; cross-tab signaling carries version only.
- **Authorization decision:** Context selects the workspace scope but grants no role, permission, assurance, or platform
  authority. Tenant-owned repositories retain exact `workspace_id` SQL scope.
- **Deferred execution decision:** Future tenant jobs revalidate server-owned account/workspace/membership references.
  Cache keys are tenant namespaced; no durable queue, production tenant job, persistent cache, or export engine is added.
- **Boundary:** Workspace provisioning/defaults and P2-B08 temporary/support/break-glass controls are not implemented.

Implemented decision profile:

- Tenant Context is server-authoritative, bound to the authenticated session, and resolved after session
  authentication.
- New sessions begin without a selected workspace; selection is explicit, does not grant authorization, and never
  falls back to the first workspace.
- Device cookies do not restore context. Separate sessions retain separate selections, while tabs sharing a session
  share that session's context.
- Public workspace IDs are lookup selectors only. The stored workspace and membership use internal relational
  identities protected by the account-aware composite foreign key.
- Tenant Context versioning is independent from authentication and authorization and is used only to reject stale
  mutations.
- Invalid context is cleared, never replaced. Workspace switching is a primary POST-and-navigate transition and is
  not a modal workflow.
- Workspace authorization requires Account Workspace Tenant Context. Tenant-owned repositories require that context
  and bind exact workspace scope in SQL.
- Account workspace inventory is the explicit account-global exception needed to choose a workspace.
- Tenant-bound background execution revalidates account, workspace, and membership. Cache keys require a workspace
  namespace.
- Persistent tenant cache and workspace provisioning remain deferred.
