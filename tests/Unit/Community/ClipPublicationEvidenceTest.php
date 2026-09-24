<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Community;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\Community\Domain\ClipPublicationEvidence;

final class ClipPublicationEvidenceTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function rejectedEvidence(): iterable
    {
        foreach (
            [
                'creatorActive',
                'creatorSelfLinked',
                'publicProfileConsented',
                'mediaOwnedByCreator',
                'mediaApproved',
                'scanClean',
                'variantReady',
                'variantPublicSafe',
                'rightsGranted',
                'consentGranted',
                'independentConsentReview',
                'minorGuardianConsentComplete',
                'deliveryPublic',
                'quranReleaseActive',
                'quranRangeValid',
                'noEmergencySafetyCase',
            ] as $field
        ) {
            yield $field => [$field];
        }
        yield 'mediaHeld' => ['mediaHeld'];
    }

    #[DataProvider('rejectedEvidence')]
    public function testEverySafetyGateFailsClosed(string $field): void
    {
        $values = array_fill(0, 17, true);
        $values[12] = false;
        $order = [
            'creatorActive', 'creatorSelfLinked', 'publicProfileConsented', 'mediaOwnedByCreator', 'mediaApproved', 'scanClean',
            'variantReady', 'variantPublicSafe', 'rightsGranted', 'consentGranted',
            'independentConsentReview', 'minorGuardianConsentComplete', 'mediaHeld',
            'deliveryPublic', 'quranReleaseActive', 'quranRangeValid', 'noEmergencySafetyCase',
        ];
        $values[array_search($field, $order, true)] = $field === 'mediaHeld';
        $this->expectException(\DomainException::class);
        (new ClipPublicationEvidence(...$values))->assertPubliclyEligible();
    }

    public function testCompleteEvidencePermitsPublication(): void
    {
        $this->expectNotToPerformAssertions();
        $values = array_fill(0, 17, true);
        $values[12] = false;
        (new ClipPublicationEvidence(...$values))->assertPubliclyEligible();
    }
}
