# P3 Geography Integrity Adversarial Matrix

| Adversarial condition | Control | Evidence |
| --- | --- | --- |
| Invalid JSON, UTF-8 or checksum | governed local dataset validation | `reference:geography:verify` |
| Wrong State/FCT/LGA count or parent | dataset validator and schema projection readiness | Geography integration tests |
| Orphan/cycle/duplicate code | hierarchy validation and relational constraints | Geography integration tests |
| Injection-shaped search or wildcard abuse | bounded public query handling and escaping | Geography HTTP/integration tests |
| Public cache leaks private state | public geography is isolated from Account/Workspace payloads | route and controller architecture tests |

The production implementation performs no runtime external Geography fetch and exposes no HTTP mutation operation.
