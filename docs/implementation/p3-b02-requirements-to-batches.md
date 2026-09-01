# QMDB-P3-B02 Requirements-to-Batches Record

| Document control | Value |
| --- | --- |
| Batch | QMDB-P3-B02 — Person, Memorizer, Reciter, Competitor, and Guardian Profile Foundation |
| Authorization | QMDB-P3-B02-EXEC |
| Date | 2026-09-01 |
| Status | COMPLETE — bounded foundation only |

## Delivered requirements slice

| Frozen requirement | B02 evidence | Status |
| --- | --- | --- |
| QMDB-FR-PPL-001 | Global Person, opaque registry code, exactly-one active Account self-link, versioned names and private profile routes | FOUNDATION_DELIVERED |
| QMDB-FR-PPL-002 | Unicode-safe name history, optional preferred/Arabic name variants, no discovery or merge surface | FOUNDATION_DELIVERED; identity evidence and duplicate resolution deferred |
| QMDB-FR-PPL-003 | Bounded Memorizer, Reciter, Competitor and Guardian role profiles; Memorizer progress; origin/residence against B01 geography | FOUNDATION_DELIVERED; organization affiliations deferred |
| QMDB-FR-PRI-004 | Private-only routes, no-store/referrer controls, opaque external IDs, audit metadata minimization and no profile browser storage | FOUNDATION_DELIVERED |
| QMDB-NFR-CHD-001 | Configurable technical age gate, adult Guardian requirement, step-up creation/revocation and last-guardian guard | FOUNDATION_DELIVERED; legal status, consent and verification remain deferred |

## Explicitly not delivered

No public Person directory, search, merge, unlink, deletion, profile claiming, contacts, school/organization affiliations,
dependent Accounts, consent, relationship verification, legal guardianship certification, competition participation,
media, reporting or tenant-owned Person data is introduced. These require their own authorized P3 batches and the
still-open policy decisions listed in the project register.

## Next batch

`QMDB-P3-B03 — Organizations, Schools, Groups, Mosques, and Branches Registry` is the next batch and is not
implemented or authorized by this record.
