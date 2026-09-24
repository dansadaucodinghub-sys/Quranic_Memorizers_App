<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Domain;

/** Only authoritative server-side evidence may construct this publication assessment. */
final readonly class ClipPublicationEvidence
{
    public function __construct(
        public bool $creatorActive,
        public bool $creatorSelfLinked,
        public bool $publicProfileConsented,
        public bool $mediaOwnedByCreator,
        public bool $mediaApproved,
        public bool $scanClean,
        public bool $variantReady,
        public bool $variantPublicSafe,
        public bool $rightsGranted,
        public bool $consentGranted,
        public bool $independentConsentReview,
        public bool $minorGuardianConsentComplete,
        public bool $mediaHeld,
        public bool $deliveryPublic,
        public bool $quranReleaseActive,
        public bool $quranRangeValid,
        public bool $noEmergencySafetyCase,
    ) {
    }

    public function assertPubliclyEligible(): void
    {
        if (
            !$this->creatorActive
            || !$this->creatorSelfLinked
            || !$this->publicProfileConsented
            || !$this->mediaOwnedByCreator
            || !$this->mediaApproved
            || !$this->scanClean
            || !$this->variantReady
            || !$this->variantPublicSafe
            || !$this->rightsGranted
            || !$this->consentGranted
            || !$this->independentConsentReview
            || !$this->minorGuardianConsentComplete
            || $this->mediaHeld
            || !$this->deliveryPublic
            || !$this->quranReleaseActive
            || !$this->quranRangeValid
            || !$this->noEmergencySafetyCase
        ) {
            throw new \DomainException('Clip publication requires current media, consent, creator, and Qur’an approval.');
        }
    }
}
