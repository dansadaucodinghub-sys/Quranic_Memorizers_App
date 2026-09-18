<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaModeration\Domain;

use Qmdb\Shared\Identifier\UuidV7;

/** An independent review attestation, not inferred consent or a legal-validity guarantee. */
final readonly class MediaConsentEvidence
{
    public function __construct(
        public UuidV7 $evidenceReference,
        public string $evidenceSha256,
        public int $participants,
        public int $participantConsents,
        public int $minors,
        public int $guardianConsents,
        public bool $rightsVerified,
        public bool $organizationAuthorityVerified,
    ) {
        if (preg_match('/\A[a-f0-9]{64}\z/', $evidenceSha256) !== 1 || $participants < 1 || $participants > 1000 || $participantConsents !== $participants || $minors < 0 || $minors > $participants || $guardianConsents !== $minors || !$rightsVerified || !$organizationAuthorityVerified) {
            throw new \InvalidArgumentException('Complete rights, participant, guardian and representative evidence is required.');
        }
    }

    public function fingerprint(): string
    {
        return implode(':', [$this->evidenceReference->toString(), $this->evidenceSha256, $this->participants, $this->participantConsents, $this->minors, $this->guardianConsents]);
    }
}
