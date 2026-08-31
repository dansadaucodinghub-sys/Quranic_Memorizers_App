# QMDB-P3-B01 Implementation Report

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Source baseline | QMDB-BL-001 |
| Product freeze | QMDB-P0-FRZ-001 |
| Engineering freeze before this batch | QMDB-P1-FRZ-001 |
| Identity and tenant security freeze | QMDB-P2-FRZ-001 |
| Authorization | QMDB-P3-OPEN-B01 |
| Batch | QMDB-P3-B01 — Nigerian Administrative Geography and Jurisdiction Registry |
| Date | 2026-08-31 |
| Scope status | COMPLETE — Nigerian geography registry only |

## Delivered result

`reference.geography` now supplies a versioned, globally readable Nigeria administrative-reference registry. It is
intentionally independent of identity, authentication, tenant, workspace, organization, person, guardian,
competition, membership, permission and geography-write concerns.

The governed version-one release contains one Nigeria country record, 36 States, one Federal Capital Territory, 768
State LGAs and six FCT Area Councils: 811 administrative areas and 812 reference records including the country.
Level-one State/FCT records retain ISO `NG-XX` codes; level-two identifiers use immutable project codes of the form
`QMDB-NG-L2-{source-key}`. Every externally exposed record uses an opaque public ID, never a database primary key.

## Delivered implementation

- Added typed geography domain values, global repositories, dataset loader/validator, read-query handlers and a
  MySQL readiness probe.
- Added two ordered MySQL migrations and the `20260831020100_seed_nigeria_administrative_geography` seed. The seed
  validates dataset structure, counts, identifiers, hierarchy and the canonical checksum before opening its
  transaction.
- Added `geography_countries`, `geography_dataset_versions`, and `geography_administrative_areas`, with InnoDB,
  foreign keys, public-ID uniqueness, canonical-code uniqueness, parent-scoped slugs, active-dataset enforcement,
  checks, and supporting read indexes.
- Added `reference:geography:verify`, which validates both the canonical file and the active MySQL release.
- Added public read-only routes: `/locations/nigeria`, `/locations/nigeria/{levelOneSlug}`, and
  `/lookups/geography/children?parent={public-id}`. They are deliberately public, tenant-free, and mutation-free.
- Added English and Arabic UI text, RTL rendering, semantic labelled controls, native progressive forms, accessible
  live-region status, and a same-origin Fetch enhancement with fragment negotiation, request cancellation and
  stale-response protection.
- Added the P3/P2 extension ledger and explicit verifier policy so P2's original manifest remains historical and
  immutable while only named P3 composition bridges are recognized as controlled extensions.

## Data provenance and boundaries

The canonical file is [nigeria-administrative-areas-v1.json](../../../database/reference/nigeria-administrative-areas-v1.json)
and has checksum `b524b9b9d6a521f12f324ff3c0d66a6402e90519ac2014bf6fd4e8eeced0438a` over its `{country, areas}`
payload. Its structured source is the Open Admin Data Nigeria compiled extract; NBS, INEC and FCTA material is
recorded only as institutional corroboration. The detailed source/reconciliation decision and limitations are in
[Nigeria dataset provenance](../../data/nigeria-administrative-geography-provenance.md).

This release is not a legal boundary file, Arabic official-name registry, historical-alias catalogue, or a claim that
the compiled public source is a government publication. Corrected names, jurisdictional changes, boundaries and
aliases require a new reviewed dataset version; no HTTP or repository write surface was added in B01.

## Executable evidence

| Check | Result |
| --- | --- |
| Reset, schema install, migration and governed seed | PASS — 30 migrations and four seeds applied |
| `reference:geography:verify` | PASS — checksum and 811-area hierarchy verified |
| Migration/seed ledger | PASS — no pending geography seed |
| Route-security verifier | PASS — 91 classified routes; 44 mutation routes, all 44 CSRF-protected |
| Focused PHP dataset, architecture and MySQL integration run | PASS — 7 tests, 293 assertions |
| Maximum-level PHPStan | PASS — no errors |
| Complete frontend quality | PASS — JavaScript syntax for 32 files; 53 tests; zero high-severity npm audit findings |
| Complete isolated MySQL matrix | PASS — 86 tests, 1,674 assertions in 12:01.964; reset and canonical schema restoration completed |
| Local public HTTP exercise | PASS — directory and Arabic area page `200`; child fragment `200`; conditional directory GET `304` |
| Public HTTP isolation | PASS — cache/ETag and negotiation headers present; no `Set-Cookie`; no PII marker in rendered directory |
| Query-plan integration assertion | PASS — `ix_geography_administrative_areas_parent_name` selected for FCT child lookup |
| Bounded local read probe | 100 prepared `abuja` searches completed in 148.023 ms on the isolated local test MySQL instance; diagnostic only, not a production SLO |

The HTTP fragment returned the six FCT Area Councils plus its empty-select option. Arabic area rendering declared
`dir="rtl"`. The browserless HTTP check is supplemented by executable frontend unit coverage; no manual visual-browser
sign-off is claimed.

## Freeze and phase control

P0 is preserved and verified by `tools/ci/verify-frozen-baseline.php`. P2 remains a historical frozen baseline under
`QMDB-P2-FRZ-001`; the original P2 manifest is not regenerated or changed. The controlled P3 bridge is limited to
the named composition, readiness and route-policy files in
[p3-p2-freeze-extension-ledger.yaml](../../project/p3-p2-freeze-extension-ledger.yaml).

P3 remains in progress, but B01 is complete. `QMDB-P3-B02 — Person, Memorizer, Reciter, Competitor, and Guardian
Profiles` has not been started. Organizations, people, guardianship, consent, competitions and all tenant-owned
domain data remain outside this delivery.
