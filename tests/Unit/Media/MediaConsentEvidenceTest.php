<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Media;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\MediaModeration\Domain\MediaConsentEvidence;
use Qmdb\Shared\Identifier\UuidV7;

final class MediaConsentEvidenceTest extends TestCase
{
    public function testCompleteEvidenceIsBoundToItsChecksumAndReference(): void
    {
        $reference = UuidV7::generate();
        $evidence = new MediaConsentEvidence($reference, hash('sha256', 'synthetic evidence'), 2, 2, 1, 1, true, true);
        self::assertStringContainsString($reference->toString(), $evidence->fingerprint());
        self::assertStringContainsString(hash('sha256', 'synthetic evidence'), $evidence->fingerprint());
    }
    /** @return iterable<string,array{int,int,int,int,bool,bool}> */
    public static function incomplete(): iterable
    {
        yield 'no participants' => [0, 0, 0, 0, true, true];
        yield 'participant missing' => [2, 1, 0, 0, true, true];
        yield 'guardian missing' => [2, 2, 1, 0, true, true];
        yield 'rights missing' => [1, 1, 0, 0, false, true];
        yield 'representative authority missing' => [1, 1, 0, 0, true, false];
        yield 'impossible minors' => [1, 1, 2, 2, true, true];
    }
    #[DataProvider('incomplete')]
    public function testIncompleteEvidenceCannotGrantConsent(int $participants, int $consents, int $minors, int $guardians, bool $rights, bool $authority): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MediaConsentEvidence(UuidV7::generate(), hash('sha256', 'fixture'), $participants, $consents, $minors, $guardians, $rights, $authority);
    }
}
