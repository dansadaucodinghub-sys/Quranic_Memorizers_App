# P3 Operations, Release and Supply Chain

Final closeout runs the repository’s pinned PHP, Node and Oracle MySQL toolchain, Composer/npm audit, secret/filesystem scanners, SBOM and licence verification, clean-install and release build/verification. Generated values are recorded only after those gates terminate successfully from a clean committed revision.

## Trivy recovery completion

`QMDB-TRIVY-DB-RECOVERY-001` repaired the formerly blocked P3 artifact scan. The release verifier now receives a
schema-2, current Trivy DB from the governed cache and uses `--skip-db-update` only after validation. The clean
`06450fae88f92a4ea90c42e0321a40e1b7377939` release passed with archive SHA-256
`cc8d9a640664cf05bab0fb9df80c9f1b908129a35ec3b9c01ccf1c51a6e3fba5`, Gitleaks/Trivy `pass`, 3,120 PHP files linted
and readiness `200`. The cache is ignored and release-excluded; no database artifact is retained in source control.
