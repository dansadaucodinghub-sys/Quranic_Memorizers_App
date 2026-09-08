# P3 Security, Privacy and Authority Verification

The P3 verifier composes P2 security with Geography, People, Organization, affiliation, identity-resolution and Person repository scope controls. It is read-only and emits only bounded component statuses.

- Public IDs and registry codes are references, never authority.
- Person access requires an active SELF link or active exact Guardian profile-management relationship.
- Organization and affiliation repositories require trusted Workspace scope.
- Pairing secrets are HMAC-only; duplicate resolution is explicit, consent-gated and non-destructive.
- Private P3 interfaces use private/no-store boundaries; no public Person or roster discovery route exists.

Detailed controls and limitations remain governed by the B06 [threat reconciliation](../../security/P3-threat-model-reconciliation.md) and [deferred-evidence register](../../security/P3-deferred-evidence-register.md).
