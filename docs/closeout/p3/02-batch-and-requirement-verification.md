# P3 Batch and Requirement Verification

| Batch | Delivered boundary | Current closeout evidence |
| --- | --- | --- |
| B01 | governed Nigerian Geography reference | dataset and projection verifier |
| B02 | private global Person, role and Guardianship profiles | account/Guardian authority tests |
| B03 | Workspace-owned Organization and Unit registry | tenant-scope and hierarchy tests |
| B04 | consent-bound affiliations and leadership lifecycle | cross-workspace and concurrency tests |
| B05 | claim pairing, verification and duplicate canonicalization | MySQL constraints and rollback evidence |
| B06 | P3 security hardening and readiness | `security:p3:verify`, adversarial matrices and defect register |

All six batches are verified. The completed final source gates are the serial MySQL suite (`102 tests`, `1,890 assertions`), full PHP quality (`1,080 tests`, `76,826 assertions`), and frontend quality (`58 tests`, zero npm audit findings). Release and P3-freeze evidence is governed separately in this closeout set.
