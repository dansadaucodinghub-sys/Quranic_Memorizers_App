# P2 Audit-Integrity Adversarial Matrix

| Scenario | Control | Executable evidence | Status |
| --- | --- | --- | --- |
| Stream identity collision | Deterministic stream key and uniqueness | `P2SecurityAuditIntegrationTest` | PASS |
| Same-stream append race | Stream lock and sequence/hash chain | audit MySQL integration | PASS |
| Cross-stream append | Independent stream locks | audit MySQL integration | PASS |
| Mutation rollback | Audit append shares authoritative transaction | audit/account-state/privileged MySQL suites | PASS |
| Event update/delete | Database append-only triggers | audit MySQL test and control verifier | PASS |
| Checkpoint update/delete | Database append-only triggers | audit MySQL test and control verifier | PASS |
| Hash or metadata tamper | Canonical metadata, event and checkpoint verification | `security:audit:verify` | PASS |
| Unknown key version | Integrity-key failure is a verification failure | audit tests | PASS |
| Verifier repair attempt | Verifier is read-only | verifier source/integration tests | PASS |
| Readiness probe exhaustion | Bounded control check, no historical scan | `SecurityAuditReadinessCheckTest` | PASS |
| Unbounded listing | Maximum page 100 and six filter indexes | audit MySQL test/control verifier | PASS |
| External publication claim | No provider is claimed active | audit standard/status tests | DEFERRED_OPERATIONAL |

P2 makes the ledger tamper-evident, not tamper-proof against a party with simultaneous database and integrity-key control. Independent key custody and external checkpoint publication remain operational controls.
