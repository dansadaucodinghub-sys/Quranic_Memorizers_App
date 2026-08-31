# P2 Privileged-Access Adversarial Matrix

| Scenario | Required control | Executable evidence | Status |
| --- | --- | --- | --- |
| Temporary privilege self-request | Authenticated subject and exact seeded snapshot | privileged-access MySQL suite | PASS |
| Self-approval | Requester and approver differ | privileged-access integration suite | PASS |
| Support approval | Distinct platform and workspace approvals | privileged-access integration suite | PASS |
| Support impersonation | No impersonation session, membership or role | privileged-access architecture/integration tests | PASS |
| Support write escalation | Exact read-only policy; write denied | privileged-access policy tests | PASS |
| Break-glass activation | Base security role, phishing-resistant step-up, incident reference | privileged-access integration suite | PASS |
| Prohibited access grant | Authorization/privileged-administration permissions rejected | seeded policy verifier | PASS |
| Expiry or scheduler delay | Synchronous expiry; task cannot extend | maintenance/MySQL tests | PASS |
| Context conflict | Normal and privileged contexts cannot combine or restore | tenancy/privileged integration tests | PASS |
| Review abuse | Independent review; overdue review blocks later activation | privileged-access integration suite | PASS |
| Audit failure | Authoritative privileged mutation rolls back | security-audit/privileged MySQL tests | PASS |

`security:privileged-access:verify` validates active schema, policy catalog, request, activation and review invariants without granting or extending access.
