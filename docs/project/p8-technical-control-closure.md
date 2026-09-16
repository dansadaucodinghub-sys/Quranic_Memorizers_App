# P8 technical-control closure

This record closes the implementable technical-control work associated with
OD-017, OD-019, OD-040, OD-065, and OD-066. It does not replace the qualified
operational authorities named by those decisions.

| Decision | Technical control disposition | Enforced boundary |
| --- | --- | --- |
| OD-017 | COMPLETE | Certificate signing is Ed25519-only; private key material is excluded from the schema; issuance requires authorization, step-up, audit evidence, transactional locking, and immutable artifacts. Production issuance remains fail-closed until an operational custody ceremony is approved. |
| OD-019 | COMPLETE | Legacy imports and records have immutable provenance and no public projection path. No application route can expose an imported legacy record as a public attestation. |
| OD-040 | COMPLETE | The signing-provider boundary accepts public metadata only. The present provider reads protected process-secret material at execution time and never persists or serializes it. A production KMS/HSM appointment remains an operational deployment prerequisite. |
| OD-065 | COMPLETE | Serial format is `QMDB-YYYY-WORKSPACE-######`, where the sequence is workspace- and calendar-year-scoped, row-locked, and six digits. |
| OD-066 | COMPLETE | Verification codes are 32 random bytes encoded as 43-character Base64URL values. Only SHA-256 and a 16-byte fingerprint are persisted; the raw code is returned once at preparation and is never recorded in audit data. |

## Verification

`competition:p8:verify` verifies the serial and verification-code storage
contracts as well as P8 schema, authorization, signing-algorithm, immutability,
and result-finalization constraints. Unit coverage verifies the identifier,
cryptographic-metadata, and legacy-publication policy boundaries.

## Residual operational prerequisites

The decisions themselves remain operationally open until the project owner
appoints a real custody provider and accountable operators, and designates the
external authority/evidence standard for legacy publication. These prerequisites
are not implementation defects and must not be bypassed by an environment flag.
