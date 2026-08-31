# P2 Authentication-Abuse Matrix

| Area | Abuse case | Control | Executable evidence | Status |
| --- | --- | --- | --- | --- |
| Registration | Existing/new email distinguishability | Generic accepted response and normalized HMAC scope | `P2IdentityAccessHttpIntegrationTest` | PASS |
| Registration | Duplicate/replayed submission | Idempotency fingerprint and unique account constraint | access MySQL suite | PASS |
| Registration | Same key with different data | Fingerprint conflict rejection | access MySQL suite | PASS |
| Registration | Same-email race | Unique normalized-email constraint and concurrent test | access MySQL suite | PASS |
| Registration | Rate-limit race | Peer/email buckets are transactionally enforced | access MySQL suite | PASS |
| Verification | GET mutation | GET is display-only | access HTTP suite | PASS |
| Verification | Token replay/expiry/exhaustion | Hashed, one-time, expiry and attempt bound | access MySQL suite | PASS |
| Recovery | Account enumeration | Generic request response | recovery HTTP suite | PASS |
| Recovery | Token persistence/replay | Hash-only, single-use, revocable challenge | recovery MySQL suite | PASS |
| Recovery | Reset race | One authoritative credential and session revocation | recovery MySQL suite | PASS |
| Password login | Unknown/invalid/suspended response | Generic failure and dummy hash | session HTTP suite | PASS |
| Password login | Rate-limit casing/normalization bypass | Canonical HMAC scopes | session MySQL suite | PASS |
| Password login | Rehash failure | No session created after failed rehash | session MySQL suite | PASS |
| Sessions | Fixation / selector-only login | Fresh secret, selector plus secret and dummy comparison | session MySQL suite | PASS |
| Sessions | Replay / rotation race | Current/previous token model, bounded grace and locking | session MySQL suite | PASS |
| Sessions | Idle/absolute expiry | Server-side expiry constraints | session MySQL suite | PASS |
| Sessions | Session-limit race | Database-bound concurrent session cap | session MySQL suite | PASS |
| Devices | Cookie/public-ID authority | Device is non-authenticating and secret/account bound | session MySQL suite | PASS |
| MFA | Password-only fallback | MFA policy and pre-auth transaction binding | MFA MySQL suite | PASS |
| MFA | TOTP replay/drift/key failure | Encrypted secret, counter consumption and bounded drift | MFA MySQL suite | PASS |
| Recovery codes | Reuse/brute force | HMAC storage, one-time atomic consumption and limit | MFA MySQL suite | PASS |
| WebAuthn | Replay/origin/RP-ID/user-verification | One-time ceremony, exact configuration and UV policy | WebAuthn MySQL suite | PASS |
| Step-up | Cross-session/action/reuse | Exact account/session/action/expiry one-use grant | MFA MySQL suite | PASS |
| Logging | Password/token/secret exposure | Redaction and safe errors | observability tests and Gitleaks | PASS |

Authentication test data is synthetic. Physical authenticator behavior, production origin approval, managed key custody, mail provider delivery and hosted CI remain operational evidence rather than local test claims.
