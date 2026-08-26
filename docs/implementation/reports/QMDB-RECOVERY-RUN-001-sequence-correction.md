# QMDB-RECOVERY-RUN-001 Sequence Correction

## Incorrect prior state

The repository contained P1 production foundations and an approved P1 freeze, but no P2 production module, migration,
route or test. Earlier conversation prompts/reports naming P2-B02 through B05 were not source evidence. The executable
sequence therefore stopped after P1-CLOSE even though later batch prompts had been supplied.

## Corrected sequence

`QMDB-RECOVERY-RUN-001` authorizes and requires this order:

```text
P1 executable revalidation
→ P1 freeze regeneration
→ P2-B01
→ P2-B02
→ P2-B03
→ P2-B04
→ P2-B05
```

No later prompt is treated as completion, no batch is skipped, and P2-B06 remains outside the recovery scope.

## Out-of-sequence code preserved

None. The initial audit found no P2 production implementation to preserve or reconcile. Frozen P0 requirements and P1
foundation behavior remain intact; P2 work is confined to controlled module, migration, route, presentation,
configuration and test extension points.

## Recovery-authorized blocker disposition

- `OD-051`: conservative ASCII email normalization, encryption and purpose-bound keyed lookup are recorded with test vectors.
- `OD-052`: strict already-canonical E.164 input, encryption and purpose-bound keyed lookup are recorded with test vectors.
- Hosted CI and manual production/release evidence remain deployment gates, not a reason to falsify local execution state.
