# QMDB-P3-B03 — Organizations Registry

## Status

Complete on 2026-09-01. This batch delivers the private, Workspace-owned `organizations.registry` foundation only.

## Implemented controls

- Fixed global descriptive classifications; they do not confer a role or permission.
- Tenant-scoped Organizations, historical names, classifications, self-declared jurisdictions, Units, Unit names and optional Unit locations.
- One active primary Organization name, one active primary classification, one active primary root Unit, bounded depth (8) and capacity (5,000).
- Opaque `QMO-` and `QMU-` registry codes, UUID public identifiers, optimistic versions, idempotency, account/session rate limits, CSRF and step-up retirement.
- All private reads and mutations require a trusted session-bound Workspace context and exact server-side authorization. Cross-Workspace public IDs resolve as unavailable.
- Retirement is append-only: an Organization retirement atomically retires active Units; a non-root Unit cannot retire with active children.
- Workspace audit events record safe technical metadata only; names, addresses and other sensitive values are excluded.

## Deliberate boundaries

This batch does not create Organization membership, staff/leadership, Person affiliation, Workspace provisioning, legal/accreditation claims, contact/address records, public directory, competition or media features. A jurisdiction is self-declared descriptive data, not legal or government proof.

## Recovery note

The initial B03 migration failed before its idempotency extension because it targeted an obsolete table name. The first classification step was retained, the corrected remaining steps resumed under the schema lock, and the schema record checksum was reconciled only after every retained step checksum matched the final migration source. The ledger retains the original failure/drift events; `db:schema:verify` is clean.

## Validation

- `organizations:registry:verify` passed with zero invalid rows.
- `security:routes:verify` classified all 126 routes and all 59 mutations have CSRF actions.
- `security:authorization:verify` passed with 36 permissions, nine roles and 97 mappings.
- Focused Organization unit/architecture tests passed: four tests and 27 assertions.
