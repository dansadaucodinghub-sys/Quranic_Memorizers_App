# QMDB-CR-002 — Post-P3 Additive Extension Governance

## Decision

This corrective change establishes a strict, additive-only post-P3 extension boundary. It exists because
`QMDB-P3-FRZ-001` correctly protects P3 content but has no governed path for an owner-authorized future phase.

## Scope and non-impact

- P3 product, domain, migration, seed, authentication, authorization, tenancy, Person, Organization, affiliation,
  claim, canonicalization, and audit semantics are unchanged.
- The frozen roadmap and requirements map are unchanged: P4 is Qur’an Reference and Governance; Competition
  Configuration remains P5.
- `QMDB-P3-FRZ-001` remains valid immutable historical evidence.
- This request authorizes only a future, exact `QMDB-P4-B01` entry after its frozen scope is uniquely resolved.

## Controls

The successor baseline rejects deletion or mutation of a frozen entry, unknown governed files, broad path grants,
and invalid ledger chains. A future entry must identify `QMDB-CR-002`, `QMDB-P3-FRZ-002`, phase P4, batch
`QMDB-P4-B01`, every new file checksum, and every append-only shared-registry change.

## Prohibitions

This request does not authorize P4-B02, P5, a wildcard source directory, Competition production code, altered
applied migrations or seeds, a bypass switch, or any rewrite of Git history.

## Verification and rollback

Policy, ledger, historical-baseline, successor-baseline, tamper, unknown-file, and clean-tree tests are required.
Any defect is corrected forward through a new commit; neither historical manifests nor history are rewritten.
