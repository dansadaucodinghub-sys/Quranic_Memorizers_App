# Seed manifest

Production seeds are registered explicitly in `database/seeds.php`; discovery by filesystem scanning is prohibited.
IDs use `YYYYMMDDHHMMSS_description`, dependencies and DML step order are explicit, and applied SHA-256 checksums are
immutable. Seeds execute once inside a database transaction and may contain only controlled `INSERT`, `UPDATE`, or
`DELETE` statements. Corrections require a new seed or an owning-module migration.

The P1-B06 production seed registry is intentionally empty. Business roles, geography, Qur'an reference data, and
taxonomies belong to their owning phases.
