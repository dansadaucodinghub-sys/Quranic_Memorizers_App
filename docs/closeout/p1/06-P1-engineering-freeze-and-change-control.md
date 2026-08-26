# QMDB P1 Engineering Freeze and Change Control

## Freeze status

`QMDB-P1-FRZ-001 — APPROVED`

The machine-verifiable manifest freezes the P1 engineering foundation while preserving named controlled extension
points. It is subordinate to the P0 product freeze and does not authorize a product-semantic change.

## Revision semantics

The manifest records the Git revision containing the governed source snapshot. A later closeout-only commit may contain
the manifest and dynamic evidence, so the verifier accepts a descendant `HEAD` only when:

1. the recorded revision is an ancestor of `HEAD`;
2. no governed path changed between that revision and `HEAD`;
3. no governed path is modified or untracked in the working tree;
4. the current governed inventory and every SHA-256 value match the manifest.

This avoids a self-referential commit hash while retaining deterministic, fail-closed verification.

## Categories

- `FROZEN_ENGINEERING_FOUNDATION`: behavior and contracts that require formal engineering change control.
- `CONTROLLED_EXTENSION_POINT`: explicit composition/configuration points that may change only through an authorized
  batch with regression evidence.
- Dynamic state, reports, prompts and later-phase roadmaps remain outside the content freeze but are still governed
  records.
- Generated dependencies, caches, scan reports and release archives are excluded and must be recreated by locked tools.

## Post-freeze change record

Every change must identify its request/batch, affected freeze, reason, governed paths, security/data impact, migration
impact, tests, rollback path and approving owner. Regenerate and verify the manifest only after the replacement governed
snapshot is committed and all applicable quality gates pass.
