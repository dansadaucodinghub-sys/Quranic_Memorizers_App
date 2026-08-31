# P2 Security Performance Baseline

| Document control | Value |
| --- | --- |
| Batch | QMDB-P2-B10 |
| Purpose | Deterministic structural and bounded-work evidence, not a production capacity claim |
| Runtime | Pinned PHP 8.5 and isolated MySQL 8.4 test environment |

## Enforced bounds

| Operation | Enforced bound | Evidence |
| --- | --- | --- |
| Audit readiness | Key and schema-control checks only; no audit-history scan | `SecurityAuditReadinessCheckTest` and source architecture test |
| Audit listing | Maximum 100 rows | `P2SecurityAuditIntegrationTest` |
| Audit filters | Six required `security_audit_events` indexes | audit-control verifier and MySQL integration |
| Workspace inventory | Bounded cursor page | session tenant-context repository and tenant tests |
| Workspace role lists | Maximum 100 rows | workspace role repository and authorization tests |
| Request target | Single bounded validation pass | `RequestTargetValidatorTest` |
| WebAuthn input | Configured maximum payload before costly parsing | MFA configuration and WebAuthn tests |
| Rate limits | Database buckets with bounded retry/window values | identity MySQL suites |
| Scheduler | Fixed claims, leases and batch sizes | scheduler integration tests |

## Executable local measurements

| Measurement | Executable evidence | Local acceptance bound | Interpretation |
| --- | --- | --- | --- |
| 1,000 audit event-hash operations | `P2SecurityPerformanceBaselineTest` | Less than 5,000 ms | Detects accidental algorithmic regression only. |
| 1,000 tenant cache-key constructions | `P2SecurityPerformanceBaselineTest` | Less than 5,000 ms | Verifies namespace/hash construction remains bounded. |
| 1,000 authorization decisions | `P2SecurityPerformanceBaselineTest` | Less than 5,000 ms | Includes normal decision logging in an in-memory test logger. |
| 100 bounded MySQL audit list queries | `P2SecurityAuditIntegrationTest` | Less than 5,000 ms | Uses the capped 25-row platform-list path in the isolated MySQL test schema. |

Measured values are recorded in the B10 implementation report from the final local validation host. The enforced
per-loop bounds and the isolated full MySQL duration are local regression observations, not database or production
service targets.

## Representative query shapes

| Query family | Required scope/index evidence | Validation |
| --- | --- | --- |
| Active session resolution | Selector lookup and account-bound session checks | session MySQL integration |
| Normalized email / credential lookup | Canonical HMAC fingerprint lookup | identity access/session MySQL integration |
| Verification and recovery challenge lookup | Challenge public ID plus hash/status/expiry checks | identity access/recovery MySQL integration |
| Workspace membership/context resolution | Account, workspace, membership composite relation | `tenancy:context:verify` |
| Workspace role assignment | Exact workspace predicate and bounded lists | `security:tenant-repositories:verify` |
| Privileged activation | Account/session/status/expiry policy lookup | `security:privileged-access:verify` |
| Audit events | Stream and event filter indexes; page maximum 100 | `security:audit:verify` and audit MySQL test |
| Account-state history | Account public ID and event pagination | account-state MySQL suite |

## Interpretation

The B10 completion gate is query shape, index presence, page/payload ceilings, correctness under concurrency, and absence of unbounded request-path loops. Wall-clock measurements are recorded only as indicative local observations in the B10 implementation report because they vary with host, MySQL buffer state and antivirus activity. This file does not claim throughput, latency SLOs, or production capacity. `OD-036` remains the production retention and capacity decision.
