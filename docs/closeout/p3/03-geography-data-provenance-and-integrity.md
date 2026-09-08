# P3 Geography Data Provenance and Integrity

The local governed Nigeria dataset is validated by `reference:geography:verify`. It verifies checksum, UTF-8, exact country/state/FCT/LGA/Area Council counts and parent relationships before validating the database projection. The public interface is read-only, bounded and isolated from Person and Workspace data.

Provenance and source limitations are recorded in [Nigeria administrative geography provenance](../../data/nigeria-administrative-geography-provenance.md). No runtime external fetch or Geography HTTP mutation is implemented.
