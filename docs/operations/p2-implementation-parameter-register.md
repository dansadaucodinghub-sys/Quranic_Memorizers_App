# P2 Implementation Parameter Register

## QMDB-P2-B04 recovery and notification parameters

| Parameter | Implemented default | Bound/meaning |
| --- | ---: | --- |
| Recovery challenge TTL | Typed environment default | Bounded challenge lifetime; startup rejects unsafe values |
| Recovery challenge attempts | Typed environment default | Positive maximum; exhaustion revokes the challenge |
| Recovery request email/peer windows | Typed environment defaults | HMAC-protected anonymous throttling |
| Recovery reset attempt window | Typed environment default | HMAC challenge and peer throttling |
| Notification batch size | 25 | Positive bounded work per scheduled invocation |
| Notification maximum attempts | 5 | Terminal failure after the configured bound |
| Notification lease | 120 seconds | Execution-owned claim lease |
| Notification retry base | 60 seconds | Exponential delay base |
| Notification retry maximum | 3,600 seconds | Retry-delay cap |
| Scheduled delivery cadence | 60 seconds | `identity.security_notifications.deliver` |

These are implementation parameters, not changes to the frozen P0 quality-attribute register. Final production values,
retention, provider, trusted-proxy, and scheduler deployment settings require environment-owner evidence.


This controlled extension records executable P2 defaults without modifying or claiming approval of the frozen P0
quality-attribute parameter register. Values remain implementation defaults until the recorded owners approve production
evidence and change control.

## QMDB-P2-B03 session parameters

| Parameter | Implemented default | Governance state | Required production evidence |
| --- | --- | --- | --- |
| Session idle TTL | 1,800 seconds | Conservative implementation default | Security/usability and concurrent-load review |
| Session absolute TTL | 43,200 seconds | Conservative implementation default | Assurance, user journey, and incident-response review |
| Rotation interval | 900 seconds | Conservative implementation default | Browser concurrency and database-capacity review |
| Previous-token grace | 30 seconds | Conservative implementation default; strictly shorter than rotation | Parallel-request latency distribution |
| Touch interval | 60 seconds | Conservative implementation default | Write-volume and expiry-accuracy load test |
| Maximum active sessions | 10 per account | Conservative implementation default | Security, support, and device-use analysis |
| Device-cookie lifetime | 31,536,000 seconds | Conservative implementation default | Privacy, device-loss, and user-expectation approval |

Changing a value requires accountable security/operations review, configuration regression tests, affected-NFR review,
and project-state evidence. Superseded values retain history.
