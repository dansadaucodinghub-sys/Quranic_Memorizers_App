<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Configuration;

use LogicException;

/**
 * Executable requirement-to-capability mapping for the fixed P4 boundary.
 *
 */
final class QuranP4RequirementRegistry
{
    /** @return array<string, array<string, string>> */
    public function entries(): array
    {
        return [
            'QMDB-P4-REQ-001' => $this->entry('QMDB-P4-B01', 'quran:sources:verify', 'source_registry', 'quran-source-registry', 'quran-source-governance'),
            'QMDB-P4-REQ-002' => $this->entry('QMDB-P4-B01', 'quran:governance:verify', 'artifact_registration', 'quran-artifact-registration', 'quran-source-governance'),
            'QMDB-P4-REQ-003' => $this->entry('QMDB-P4-B01', 'quran:governance:verify', 'release_governance', 'quran-release-governance', 'quran-release-governance'),
            'QMDB-P4-REQ-004' => $this->entry('QMDB-P4-B01', 'quran:governance:verify', 'release_manifest', 'quran-release-manifest', 'quran-release-governance'),
            'QMDB-P4-REQ-005' => $this->entry('QMDB-P4-B01', 'quran:governance:verify', 'release_lifecycle', 'quran-release-lifecycle', 'quran-release-governance'),
            'QMDB-P4-REQ-006' => $this->entry('QMDB-P4-B01', 'quran:governance:verify', 'governance_security', 'quran-governance-security', 'quran-governance-security'),
            'QMDB-P4-REQ-007' => $this->entry('QMDB-P4-B01', 'quran:governance:verify', 'governance_interface', 'quran-governance-http', 'quran-governance-presentation'),
            'QMDB-P4-REQ-008' => $this->entry('QMDB-P4-B01', 'quran:governance:verify', 'governance_readiness', 'quran-governance-readiness', 'quran-governance-runtime'),
            'QMDB-P4-REQ-009' => $this->entry('QMDB-P4-B02', 'quran:artifacts:verify', 'canonical_artifacts', 'quran-artifacts', 'quran-source-artifacts'),
            'QMDB-P4-REQ-010' => $this->entry('QMDB-P4-B02', 'quran:content:verify', 'canonical_parser', 'quran-uthmani-parser', 'quran-canonical-import'),
            'QMDB-P4-REQ-011' => $this->entry('QMDB-P4-B02', 'quran:content:verify', 'metadata_parser', 'quran-metadata-parser', 'quran-canonical-import'),
            'QMDB-P4-REQ-012' => $this->entry('QMDB-P4-B02', 'quran:content:verify', 'canonical_import', 'quran-canonical-import', 'quran-canonical-import'),
            'QMDB-P4-REQ-013' => $this->entry('QMDB-P4-B02', 'quran:content:verify', 'canonical_integrity', 'quran-canonical-integrity', 'quran-canonical-runtime'),
            'QMDB-P4-REQ-014' => $this->entry('QMDB-P4-B02', 'quran:content:verify', 'structural_integrity', 'quran-structural-integrity', 'quran-canonical-runtime'),
            'QMDB-P4-REQ-015' => $this->entry('QMDB-P4-B02', 'quran:content:verify', 'canonical_activation', 'quran-release-activation', 'quran-canonical-runtime'),
            'QMDB-P4-REQ-016' => $this->entry('QMDB-P4-B02', 'quran:content:verify', 'content_readiness', 'quran-content-readiness', 'quran-canonical-runtime'),
            'QMDB-P4-REQ-017' => $this->entry('QMDB-P4-B03', 'quran:search-artifact:verify', 'search_artifact', 'quran-search-artifact', 'quran-search-artifact'),
            'QMDB-P4-REQ-018' => $this->entry('QMDB-P4-B03', 'quran:search-corpus:verify', 'search_corpus', 'quran-search-corpus', 'quran-search-runtime'),
            'QMDB-P4-REQ-019' => $this->entry('QMDB-P4-B03', 'quran:search-corpus:verify', 'search_alignment', 'quran-search-alignment', 'quran-search-runtime'),
            'QMDB-P4-REQ-020' => $this->entry('QMDB-P4-B03', 'quran:public-reference:verify', 'public_reference', 'quran-public-reference', 'quran-public-presentation'),
            'QMDB-P4-REQ-021' => $this->entry('QMDB-P4-B03', 'quran:public-reference:verify', 'public_navigation', 'quran-public-navigation', 'quran-public-presentation'),
            'QMDB-P4-REQ-022' => $this->entry('QMDB-P4-B03', 'quran:public-reference:verify', 'public_search', 'quran-public-search', 'quran-public-presentation'),
            'QMDB-P4-REQ-023' => $this->entry('QMDB-P4-B03', 'quran:public-reference:verify', 'public_accessibility', 'quran-public-accessibility', 'quran-public-presentation'),
            'QMDB-P4-REQ-024' => $this->entry('QMDB-P4-B03', 'quran:public-reference:verify', 'public_security', 'quran-public-security', 'quran-public-runtime'),
        ];
    }

    public function assertValid(): void
    {
        $entries = $this->entries();
        if (count($entries) !== 24) {
            throw new LogicException('P4 requirement registry must contain exactly 24 entries.');
        }

        foreach (range(1, 24) as $number) {
            $id = sprintf('QMDB-P4-REQ-%03d', $number);
            $entry = $entries[$id] ?? null;
            if (!is_array($entry) || count($entry) !== 5 || !in_array($entry['batch'], ['QMDB-P4-B01', 'QMDB-P4-B02', 'QMDB-P4-B03'], true)) {
                throw new LogicException('P4 requirement registry contains an invalid requirement entry.');
            }
            foreach ($entry as $value) {
                if ($value === '') {
                    throw new LogicException('P4 requirement registry contains incomplete evidence.');
                }
            }
        }
    }

    /** @return array<string, string> */
    private function entry(string $batch, string $verifier, string $capability, string $testGroup, string $releaseInclusion): array
    {
        return [
            'batch' => $batch,
            'verifier' => $verifier,
            'capability' => $capability,
            'test_group' => $testGroup,
            'release_inclusion' => $releaseInclusion,
        ];
    }
}
