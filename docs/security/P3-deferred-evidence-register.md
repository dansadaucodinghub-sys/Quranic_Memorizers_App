# P3 Deferred Evidence Register

| Item | Existing automated evidence | Missing external evidence | Owner role | Gate | Risk | Compensating control | B06 blocker | P3-CLOSE blocker |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Hosted CI | local CI and deterministic tests | hosted execution record | DevSecOps | release | medium | committed scripts and clean-install gate | NO | NO |
| Independent penetration test | adversarial architecture/MySQL tests | independent assessment | security lead | production release | medium | least privilege and closed policy verifiers | NO | NO |
| Keyboard, screen reader, zoom and forced colours | static accessibility checks | manual browser evidence | accessibility lead | P3-CLOSE | medium | semantic templates and progressive interaction tests | NO | NO |
| Supported/mobile browser matrix | frontend automated suite | physical-device/browser runs | QA lead | P3-CLOSE | low | standards-based progressive enhancement | NO | NO |
| Production proxy, key custody, scheduler and notification operations | local configuration verification | deployment/provider evidence | operations lead | production release | high operational | no production claim; P2 controls remain active | NO | NO |
| Minor/Guardian terminology legal review | policy tests | legal review | product/legal owner | production release | medium | bounded non-legal product terminology | NO | NO |
| Independent Nigerian geography source review | checksum and hierarchy validation | external data review | data steward | P3-CLOSE | low | governed local dataset provenance | NO | NO |
| Large-volume load and DR rehearsal | bounded query/index and local concurrency tests | representative load/restore evidence | operations lead | production release | medium | page and batch limits | NO | NO |
