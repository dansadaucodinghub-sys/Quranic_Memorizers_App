# QMDB-RECOVERY-RUN-001 Toolchain Report

## Result

`PASS — PORTABLE WINDOWS TOOLCHAIN ACTIVE`

The repository now owns a checksum-pinned, reproducible Windows bootstrap under `tools/windows/`. Generated archives,
binaries, database files, logs, caches and credentials remain beneath ignored `.runtime/` and are excluded from source,
release, syntax, link, freeze and sensitive-content inventories.

## Verified runtime

| Component | Resolved evidence |
| --- | --- |
| PHP | 8.5.10 NTS x64, official PHP Windows distribution |
| PHP extensions | curl, DOM, fileinfo, intl, JSON, Mbstring/polyfill, OpenSSL, PDO, PDO MySQL, PDO SQLite, SimpleXML, sodium, tokenizer, XMLWriter and ZIP available as required by runtime/tests |
| Composer | 2.8.8 official PHAR, executed by portable PHP 8.5.10; strict validation and platform checks pass |
| Node.js | v24.19.0 x64, official Node.js distribution |
| npm | 11.17.0 |
| MySQL | 8.4.11 MySQL Community Server - GPL, Oracle distribution, not MariaDB |
| MySQL endpoint | `127.0.0.1:3308`, isolated repository test data only |
| MySQL invariants | InnoDB; UTC session; `utf8mb4`; native prepared statements; strict SQL modes |
| Runtime account | Dedicated non-root DML/test-DDL identity scoped to the isolated `qmdb_test` schema |
| Schema account | Separate non-root DDL identity scoped to the isolated `qmdb_test` schema |
| Administrative account | Dedicated TCP test administrator used only for local instance lifecycle; never passed to application code |
| Windows security tools | Actionlint 1.7.12, Gitleaks 8.30.1 and Trivy 0.72.0 from checksum-verified official releases |
| Mode | Portable Windows; Docker was attempted first but the local Docker daemon could not start because the host WSL installation returned `Wsl/CallMsi/Install/E_INVALIDARG` |

## Locked archive checksums

| Archive | SHA-256 | Additional vendor evidence |
| --- | --- | --- |
| `php-8.5.10-nts-Win32-vs17-x64.zip` | `22ec430195984d233eb9e62c637a945bbcda06efca2f392d9d96d62c6acd34f8` | Official PHP Windows release metadata |
| `composer-2.8.8.phar` | `957263e284b9f7a13d7f475dc65f3614d151b0c4dcc7e8761f7e7f749447fb68` | Official Composer checksum |
| `node-v24.19.0-win-x64.zip` | `57f71ab3652e797d84acddc79c81cc9ff1c6ddb2a1974cdb83f00fee9bff4c73` | Official Node.js SHASUMS256 |
| `mysql-8.4.11-winx64.zip` | `a492371d687d2bab088b0062581144a0044b8964baefdf4faa579292b423d25c` | Oracle MD5 `2e833921898a9a030ea6bfe81bd811bc` also verified |
| `actionlint_1.7.12_windows_amd64.zip` | `6e7241b51e6817ea6a047693d8e6fed13b31819c9a0dd6c5a726e1592d22f6e9` | Official release checksum list |
| `gitleaks_8.30.1_windows_x64.zip` | `d29144deff3a68aa93ced33dddf84b7fdc26070add4aa0f4513094c8332afc4e` | Official release checksum list |
| `trivy_0.72.0_windows-64bit.zip` | `ed3cf122060f61818fe1f735fd97557954e16e10bc8b058af9852271cf2e91b3` | Official release checksum list |

The executable lock is `tools/runtime/toolchain.lock.json`. This report intentionally contains no password, token,
complete environment map or private-user attachment path.

## Lifecycle commands

```powershell
tools/windows/bootstrap-qmdb-toolchain.ps1
tools/windows/verify-qmdb-toolchain.ps1
tools/windows/start-qmdb-mysql-test.ps1
tools/windows/stop-qmdb-mysql-test.ps1
tools/windows/reset-qmdb-mysql-test.ps1
tools/windows/run-qmdb-command.ps1 composer quality
tools/windows/install-qmdb-security-tools.ps1
tools/windows/run-qmdb-secret-scan.ps1 repository
tools/windows/run-qmdb-filesystem-scan.ps1 repository
```

The reset command validates that its recursive target is inside the repository `.runtime` root. The initial isolated
test data was safely reset once to correct the TCP administrator host contract; no XAMPP or application data was used.
