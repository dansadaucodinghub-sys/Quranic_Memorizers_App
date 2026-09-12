<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Domain;

/**
 * Canonical, immutable output of a result calculation.  Checksums are raw
 * SHA-256 bytes when persisted; keeping their hexadecimal representation at
 * the domain boundary makes accidental binary-to-text conversion explicit.
 *
 * @phpstan-type RankedRow array{participantPublicId:string,totalUnits:int,rank:int,publicLabel:string}
 */
final readonly class CalculatedCompetitionResult
{
    /** @param list<RankedRow> $rows */
    public function __construct(
        public array $rows,
        public string $inputChecksum,
        public string $resultChecksum,
    ) {
    }
}
