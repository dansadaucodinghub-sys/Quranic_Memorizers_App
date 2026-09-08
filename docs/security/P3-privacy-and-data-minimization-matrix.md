# P3 Privacy and Data-Minimization Matrix

| Data class | Collection/use boundary | Protection |
| --- | --- | --- |
| Person names, birth dates, sex and geography | private profile and authorized dependent workflows only | private/no-store responses; no public search; audit metadata excludes profile fields |
| Organization names and affiliations | private Workspace or authorized Account views | tenant-scoped queries; no public roster or staff directory |
| Pairing code material | display-once claimant pairing workflow | selector plus versioned HMAC only; no plaintext secret persistence |
| Review justifications and duplicate comparisons | bounded Platform review only | private controllers and generic external errors |
| Account/session/CSRF/MFA values | identity and security subsystems | not emitted by P3 verifier or evidence reports |

P3 verifiers emit bounded counts/statuses only and do not expose Person names, dates, pairing data or review justifications.
