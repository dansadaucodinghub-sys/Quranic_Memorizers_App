# Nigerian Administrative Geography and Jurisdiction Registry Standard

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Source baseline | QMDB-BL-001 |
| Product freeze | QMDB-P0-FRZ-001 |
| Engineering freeze | QMDB-P1-FRZ-001 |
| Security freeze | QMDB-P2-FRZ-001 |
| Authorization | QMDB-P3-OPEN-B01 |
| Batch | QMDB-P3-B01 — Nigerian Administrative Geography and Jurisdiction Registry |
| Status | IMPLEMENTED |
| Last updated | 2026-08-31 |

## Scope and boundary

`reference.geography` is a global reference module. It owns only the Nigerian country record, governed dataset
version, State and Federal Capital Territory (FCT) records, and Level-2 Local Government Area (LGA) and FCT Area
Council records. It has no identity, authentication, tenant, workspace, organization, person, guardian, competition,
membership, permission, or administrative-write dependency.

The module is deliberately read-only in this batch. There is no geography administration screen, import endpoint,
generic CRUD endpoint, mutation route, browser storage, or tenant context. A later governed data release must add a
new dataset version rather than directly changing the active source records.

## Data contract

The canonical dataset is [nigeria-administrative-areas-v1.json](../../database/reference/nigeria-administrative-areas-v1.json).
It has a stable Nigeria public identifier, a fixed dataset public identifier in the seeder, and deterministic opaque
public identifiers for every area. External consumers never receive database primary keys.

| Subject | Canonical identifier policy |
| --- | --- |
| Nigeria | `NG`, `NGA`, and `566`; canonical slug `nigeria` |
| State / FCT | ISO 3166-2 `NG-XX` canonical code and approved slug |
| Level-2 LGA / Area Council | `QMDB-NG-L2-{source-key}` project-owned canonical code; not an official government code |
| Display / lookup | Stored official name, normalized `search_name`, and parent-scoped canonical slug |

The initial dataset contains one country, 36 States, one FCT, 768 LGAs, six FCT Area Councils, 811 administrative
areas, and 812 reference records including the country.

## Persistence and integrity

The migration registry orders the following migrations after P2:

1. `20260831000100_create_geography_country_and_dataset_foundation`
2. `20260831000200_create_geography_administrative_area_hierarchy`

`geography_countries`, `geography_dataset_versions`, and `geography_administrative_areas` use InnoDB, opaque binary
public identifiers, country-safe foreign keys, a single-active-dataset generated marker, parent-scoped slug
uniqueness, canonical-code uniqueness, supported query indexes, status/version checks, and a parent/level/type
model. A dataset version holds source provenance and the SHA-256 checksum for the canonical `{country, areas}` JSON
payload. The active seeder validates all structural, identifier, hierarchy, FCT, count, and checksum rules before the
schema seed runner opens its transaction.

The initial data is referentially stable. There is no application write repository or HTTP mutation surface for these
tables. Future corrections require a reviewed release that supersedes the current dataset while retaining its
provenance and checksum.

## Public read interface

| Route | Classification | Result |
| --- | --- | --- |
| `GET /locations/nigeria` | public, read-only, no CSRF, no tenant context | HTML directory and bounded search |
| `GET /locations/nigeria/{levelOneSlug}` | public, read-only, no CSRF, no tenant context | State/FCT page and Level-2 listing |
| `GET /lookups/geography/children?parent={public-id}` | public, read-only, no CSRF, no tenant context | `text/vnd.qmdb.fragment+html` dependent-select fragment |

All public results set cache controls for one hour, vary by `Accept` and `Accept-Language`, use a dataset-checksum
ETag, and contain no personal, account, workspace, permission, or session data. The dependent selector uses native
Fetch with same-origin credentials, a fragment-only Accept header, AbortController cancellation, and stale-response
suppression. The server HTML remains usable when JavaScript is unavailable.

## Localization and accessibility

English and Arabic translations cover the public labels, error states, and live-region messages. Recorded Latin-script
official geographic names are preserved where an approved Arabic official name is not available. The pages use
semantic headings, labelled search and select controls, visible progressive fallbacks, escaped output, and a polite
live region for dependent-select status.

## Controlled future extension

P3-B02 may not be started by this batch. Later batches must retain public identifiers and historical dataset versions,
use repository-level public-id boundaries, add only governed source releases, and document any changed names,
boundaries, effective dates, or aliases as a versioned release. Organizations, people, guardianship, consent,
competitions, and tenant-scoped associations remain explicitly out of scope.
