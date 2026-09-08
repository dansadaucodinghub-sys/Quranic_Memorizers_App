# Person, Memorizer, Reciter, Competitor, and Guardian Profile Standard

| Control | Value |
| --- | --- |
| Batch | QMDB-P3-B02 |
| Authorization | QMDB-P3-B02-EXEC |
| Status | Implemented bounded foundation |
| Owning module | `people.profiles` |
| Scope | Private global Person profiles and self-declared profile-management guardianship |

## Core boundary

A **Person** is a global, private domain record; it is not a User Account, a Workspace-owned record, an
Authorization Role, or a public profile. An active Account can hold one active SELF link to one Person. A Person can
hold multiple active Person-role profiles (`MEMORIZER`, `RECITER`, `COMPETITOR`, and `GUARDIAN`), but those profiles
grant neither a Workspace permission nor a Competition registration. An opaque Person public ID is an external
reference only and never establishes access.

`COMPETITOR` means the Person is eligible for later competition-domain handling; it does not register the Person for
a competition. `GUARDIAN` identifies a Person role; authority comes only from an active, exact guardianship
relationship. B02 guardianship is a self-declared profile-management relationship and is not legal certification,
consent, document verification, or a publication decision.

## Data and lifecycle

- `people_persons` holds an opaque public ID, non-sequential registry code, immutable creation provenance, state and
  optimistic version. Records are not hard deleted.
- `people_person_names` retains active/historical official, preferred and Arabic Unicode-safe names. Names cannot
  contain markup, NUL or prohibited controls; names are not globally unique.
- `people_account_links` makes a SELF link explicit and enforces one active link per Account and per Person.
- `people_person_geographies` records optional nationality, origin and residence history. A level-two area must be a
  child of its selected level-one area; geography never creates jurisdictional authorization.
- `people_role_profiles` records bounded Person roles. `people_memorizer_progress` is only self- or
  guardian-declared Juz progress, not a verified result, certificate or religious assessment.
- `people_guardianships` records active/revoked self-declared profile-management relationships. A Guardian must be an
  adult with an active Guardian Person role; the dependent must be below the configurable technical threshold.

## Access and mutation rules

Self access requires the exact active SELF Account-to-Person link. Dependent access requires the exact active
guardianship for the authenticated Account's self-linked Guardian Person. Workspace roles, privileged support,
break-glass state and Person public IDs do not bypass this policy. Creation of a dependent and revocation of a
guardianship require P2 action-bound step-up; last-active-guardian protection prevents revoking the final active
guardian for a minor. The configurable age threshold is a product control, not legal advice, and adult dependent
management is deferred.

Mutations use CSRF actions, rate limits, privacy-safe idempotency fingerprints, optimistic versions and transactions.
Profile values, names and birth dates are excluded from audit metadata, security-notification content and browser
storage. Security events identify only bounded action categories and opaque references.

## Interface and accessibility

All pages are authenticated, server-rendered private routes with `Cache-Control: no-store` and `Referrer-Policy:
no-referrer`. Forms have explicit labels, validation summaries, ordinary POST behaviour and progressive same-origin
enhancement. Origin and residence start with the State/FCT list and fetch only the selected LGA/Area Council list.
English and Arabic templates preserve Unicode and RTL text without treating a script choice as an authorization or
identity-verification claim.

## Explicit exclusions

No public Person discovery, search, merge, deletion, profile claim, contacts, media, Organization affiliation,
dependent Account, consent, legal-status certification, relationship verification, competition entry, scoring,
certificate or tenant-owned Person record is implemented. P3-B03 is next: **Organizations, Schools, Groups, Mosques,
and Branches Registry**.

## P3-B05 profile-claim and canonicalization extension

An Account and a Person remain distinct aggregates. An opaque Person registry code is not authority and cannot create
a SELF link. P3-B05 introduces a separate Account-generated pairing, Guardian or Platform authorization, and claimant
acceptance sequence. The accepted claim creates an active SELF link only after multi-factor step-up; it creates no
Workspace membership or authorization role. Guardian confirmation is a QMDB workflow fact, not legal guardianship
certification, and adult acceptance revokes active Guardian profile-management authority under the controlled People
contract.

Duplicate handling is not a Person search or automatic matching feature. A duplicate case is explicitly reported,
requires every active manager's consent, and performs conflict preflight before any canonicalization. The source
Person is retired, never deleted; its immutable alias resolves to the retained canonical Person. Person names are not
automatically overwritten and Account links are neither merged nor transferred.
