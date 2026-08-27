# QMDB P2 Requirements-to-Batches Map

This map routes the frozen P0 requirements into executable P2 ownership. It does not change requirement meaning.
`QMDB-RECOVERY-RUN-001` resolved the historical entry block conservatively and authorizes only sequential B01–B05
execution; B01 through B04 are complete and B05 is next.

| Capability | Requirement IDs | Batch | Owning module | Migration group | Security controls | Acceptance scenarios/invariants | Test type |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Workspaces | QMDB-FR-TEN-001; QMDB-DR-001/002/004 | B01 | Tenancy | P2 identity-tenancy foundation | QMDB-CTL-005/008 | QMDB-AS-003/004; INV-001/002/026 | MySQL constraints, lifecycle contract |
| User accounts | QMDB-FR-IAM-001/006; QMDB-DR-001/004/005 | B01, B09 | Identity | Foundation; account-state operations | QMDB-CTL-002/008/015 | QMDB-AS-001/002; INV-003/026 | Entity, repository, state, MySQL |
| Account email addresses | QMDB-FR-IAM-001/002; QMDB-DR-005 | B01, B02 | Identity | Contact foundation; verification | QMDB-CTL-002/008/022 | QMDB-AS-001/002 | Normalization vectors, uniqueness, encryption boundary |
| Account phone numbers | QMDB-FR-IAM-001/005; QMDB-DR-005 | B01, B04 | Identity | Contact foundation; recovery | QMDB-CTL-002/008/022 | QMDB-AS-001/002 | Normalization vectors, uniqueness, privacy |
| Credentials/passwords | QMDB-FR-IAM-001/002/005 | B01, B02, B04 | Identity | Credential metadata; authentication/recovery | QMDB-CTL-002/022 | QMDB-AS-001/002; INV-025 | No plaintext, hash policy, replay and enumeration tests |
| Sessions | QMDB-FR-SES-001/003 | B03 | Identity | Session lifecycle | QMDB-CTL-003/005/007 | QMDB-AS-001/002; INV-004/025 | Fixation, rotation, expiry, revocation |
| Devices | QMDB-FR-SES-002 | B03 | Identity | Device/session inventory | QMDB-CTL-003/015 | QMDB-AS-002 | Label, privacy and remote termination |
| Account recovery | QMDB-FR-IAM-005 | B04 | Identity | Recovery challenges/history | QMDB-CTL-002/003/020 | QMDB-AS-002; INV-025 | Single-use, expiry, race and abuse tests |
| Passkeys | QMDB-FR-IAM-003 | B05 | Identity | Passkey credentials | QMDB-CTL-002/007/022 | QMDB-AS-001/002 | Origin, RP, challenge, replay and counter tests |
| MFA and step-up | QMDB-FR-IAM-004 | B05 | Identity | Authenticator/assurance | QMDB-CTL-002/004 | QMDB-AS-002/005; INV-024 | Factor, recovery and privileged-action tests |
| Memberships | QMDB-FR-TEN-001/002 | B06, B07 | Tenancy/Access Control | Membership and tenant-context grants | QMDB-CTL-004/005/008 | QMDB-AS-003/004; INV-001/002 | Lifecycle, scope and cross-workspace tests |
| Roles and permissions | QMDB-FR-AUT-001/002 | B06 | Access Control | Versioned role/permission grants | QMDB-CTL-004/005 | QMDB-AS-002/003/005; INV-004/013/024/025 | Matrix, deny-default, revocation |
| Administrative scopes | QMDB-FR-AUT-001/002 | B06 | Access Control | Scoped grants | QMDB-CTL-004/005 | QMDB-AS-003/005 | Scope intersection and self-approval tests |
| Tenant context/isolation | QMDB-FR-TEN-002; QMDB-DR-002/003 | B01, B07 | Tenancy | Context contract; tenant-aware repositories | QMDB-CTL-005/008 | QMDB-AS-003/004; INV-001/002 | Composite FK, trusted context, denial/leakage tests |
| Temporary privileges | QMDB-FR-AUT-002 | B08 | Access Control | Expiring exceptional grants | QMDB-CTL-004/005 | QMDB-AS-005; INV-004/013 | Expiry, approval and scope tests |
| Support access | QMDB-FR-AUT-002 | B08 | Access Control/Security Operations | Support case/grant evidence | QMDB-CTL-004/030 | QMDB-AS-005 | Field/time/case scope and notification tests |
| Break-glass access | QMDB-FR-AUT-002 | B08 | Security Operations | Emergency grant/review | QMDB-CTL-004/020/030 | QMDB-AS-005 | Step-up, expiry, immutable evidence and review |
| Security events | QMDB-FR-SEC-001/002 | B09 | Security Operations | Security event/incident records | QMDB-CTL-020/022 | INV-001/024/025 | Detection, containment, correlation and privacy |
| Audit history | QMDB-FR-AUD-001/002 | B09 | Audit | Hash-linked audit/checkpoints | QMDB-CTL-014/020 | INV-004/013/025 | Append-only, chain, tamper and export tests |
| Suspension/reactivation | QMDB-FR-IAM-006 | B09 | Identity | Account status events | QMDB-CTL-002/003/004 | QMDB-AS-001/002; INV-003/026 | Authorization, stale version, revocation and history |
| Security hardening | All preceding P2 requirements | B10 | Cross-module | Bounded remediation only | QMDB-CTL-001–008/014/020–022/030 | All IAM/tenant scenarios | Abuse, concurrency, performance and penetration regression |

## Historical preconditions and current disposition

OD-051 and OD-052 previously blocked B01. The recovery run authorized conservative ASCII-email and canonical E.164
contracts with executable vectors; those decisions are now implemented. Production providers, retention periods,
competition rules, and national rollout authority remain owned by their later governance gates.
