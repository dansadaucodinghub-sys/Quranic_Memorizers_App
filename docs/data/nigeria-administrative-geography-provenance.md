# Nigeria Administrative Geography Dataset Provenance

| Field | Value |
| --- | --- |
| Dataset code | `QMDB-NG-ADMIN-2026-01` |
| Dataset version | `1` |
| Retrieval date | 2026-08-31 |
| Canonical payload checksum | `b524b9b9d6a521f12f324ff3c0d66a6402e90519ac2014bf6fd4e8eeced0438a` |
| Local canonical file | [nigeria-administrative-areas-v1.json](../../database/reference/nigeria-administrative-areas-v1.json) |
| Intended scope | Nigeria country, States, FCT, LGAs, and FCT Area Councils only |

## Source and reconciliation method

The initial structured extraction was taken from the public [Open Admin Data Nigeria administrative-divisions
dataset](https://github.com/open-admin-data/nigeria-administrative-divisions/tree/master/data), specifically its
`all-flat.json` listing of 37 Level-1 areas and 774 Level-2 units. It is a convenient public compiled extract, not a
government publication, and is not represented as an official boundary authority.

Institutional corroboration was recorded from the [National Bureau of Statistics LGA
catalogue](https://microdata.nigerianstat.gov.ng/index.php/catalog/163/variable/F2/V32?name=lga_name), [INEC State
offices](https://www.inecnigeria.org/inec-state-offices/), and the [FCTA FAQ](https://www.fcta.gov.ng/faq/). ISO
3166 country and subdivision codes are applied only where they are applicable to Nigeria’s country/State/FCT level;
the [ISO country-code guidance](https://www.iso.org/iso-3166-country-codes.html) is the code-system reference.

## Canonicalization rules

1. Preserve extracted official display names except expand FCT `Abuja Municipal` to `Abuja Municipal Area Council`
   in line with FCTA terminology.
2. Retain the recorded State/FCT ISO 3166-2 code as both `official_code` and canonical code.
3. Give every Level-2 record a project-owned immutable code in the form `QMDB-NG-L2-{source-key}`. These are
   application identifiers, not government codes.
4. Derive lower-case, hyphenated canonical slugs independently from display names and enforce uniqueness under the
   immediate parent.
5. Store a normalized search value separately from display text; never infer official names from it.
6. Compute SHA-256 over deterministic JSON encoding of exactly `country` and `areas`, excluding human-readable
   provenance metadata so the metadata can describe the checksum without self-reference.

## Verification and limitations

The loader validates UTF-8 text, public identifiers, ISO/project-code format, sibling slugs, parent existence, level
and type pairing, FCT-only Area Councils, State-only LGAs, all mandatory counts, and the canonical payload checksum
before seeding. Runtime verification also checks the active MySQL dataset checksum and counts.

This initial release does not claim legal boundary accuracy, a government-approved geospatial boundary file, historic
aliases, effective dates, or an Arabic official-name registry. A disputed spelling, renamed unit, boundary change, or
new official source requires a reviewed, checksum-governed successor dataset rather than editing this release.
