# QMDB P1 Gap, Correction, and Deferred-Evidence Register

| Gap | Severity | Resolution | Status | P1 blocker | P2 blocker |
| --- | --- | --- | --- | --- | --- |
| QMDB-P1-GAP-001 MySQL evidence unavailable | High | Isolated checksum-verified MySQL 8.4.11; full DB/schema matrix passed | RESOLVED | No | No |
| QMDB-P1-GAP-002 external scanners unavailable | High | Official same-version Windows assets; actionlint, Gitleaks, ShellCheck and Trivy passed | RESOLVED | No | No |
| QMDB-P1-GAP-003 Node 24 unavailable | Medium | Bundled Node 24.19.0 with npm 11.6.2; frontend quality passed | RESOLVED | No | No |
| QMDB-P1-GAP-004 hosted CI unobserved | Medium | Workflow/static/local gates pass; execution deferred to pre-merge | DEFERRED_NON_BLOCKING | No | No |
| QMDB-P1-GAP-005 manual accessibility matrix | Medium | Automated foundation passes; manual release matrix retained | DEFERRED_NON_BLOCKING | No | No |
| QMDB-P1-GAP-006 real PCNTL signal delivery | Medium | portable lifecycle/fail-closed behavior passes; Linux deployment rehearsal retained | DEFERRED_NON_BLOCKING | No | No |
| QMDB-P1-GAP-007 dirty source | High | controlled local engineering revisions and clean artifact/freeze verification | RESOLVED | No | No |
| QMDB-P1-GAP-008 batch reports incomplete | High | B01–B10 reports reconciled to final acceptance evidence | RESOLVED | No | No |
| QMDB-P1-GAP-009 email normalization | High | Requires governed decision `OD-051` | OPEN_P2 | No | Yes |
| QMDB-P1-GAP-010 phone normalization | High | Requires governed decision `OD-052` | OPEN_P2 | No | Yes |
| QMDB-P1-GAP-011 frozen-document edit conflict | Low | P0 hashes preserved; dynamic evidence used | RESOLVED | No | No |
| QMDB-P1-GAP-012 freeze verifier missing | Low | deterministic generator/verifier and regression tests added | RESOLVED | No | No |
| QMDB-P1-GAP-013 repository policy closeout state | Low | policy now validates `P1_COMPLETE` | RESOLVED | No | No |
| QMDB-P1-GAP-014 deprecated PHP 8.5 PDO constants | Medium | migrated to `Pdo\Mysql` constants with regression coverage | RESOLVED | No | No |
| QMDB-P1-GAP-015 repeated native PDO placeholders | High | unique scheduler placeholders; MySQL claim suite passes | RESOLVED | No | No |
| QMDB-P1-GAP-016 driver-specific aggregate scalar type | Medium | integer value assertions across PDO drivers | RESOLVED | No | No |
| QMDB-P1-GAP-017 invalid actionlint flag | High | replaced with `-no-color`; actionlint and regression test pass | RESOLVED | No | No |
| QMDB-P1-GAP-018 secret scan included generated roots | High | bounded generated-root exclusion plus repository policy | RESOLVED | No | No |
| QMDB-P1-GAP-019 Composer PHAR dependency deprecations on PHP 8.5 | Low | commands pass; track Composer PHAR update without changing application runtime policy | DEFERRED_MAINTENANCE | No | No |
| QMDB-P1-GAP-020 Windows npm launcher resolution | High | resolve the real npm CLI across all PATH entries and execute it through Node without shell interpolation; regression test added | RESOLVED | No | No |

There are no open P1 blockers. Deferred operational evidence is explicit and cannot be promoted to a pass without its
own execution record.
