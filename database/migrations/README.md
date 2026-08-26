# Migration manifest

Production migrations are registered explicitly in `database/migrations.php`; directory scanning and request-selected
migration classes are prohibited. IDs use `YYYYMMDDHHMMSS_description`, dependencies are explicit, and step order is
stable. Applied aggregate and per-step SHA-256 checksums are immutable. Never edit an applied migration; add a forward
or approved compensating migration instead.

The schema metadata installer is framework infrastructure and is not represented as a normal business migration.
Business migrations begin in their owning domain phases. The P1-B06 production registry is intentionally empty.
