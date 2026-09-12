<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\CompetitionJudging;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\CompetitionJudging\Domain\RoundLifecycle;
use Qmdb\Modules\CompetitionJudging\Domain\RoundStatus;

final class RoundLifecycleTest extends TestCase
{
    public function testItAllowsTheNormalRoundLifecycle(): void
    {
        $lifecycle = new RoundLifecycle();

        self::assertTrue($lifecycle->allows(RoundStatus::DRAFT, RoundStatus::READY));
        self::assertTrue($lifecycle->allows(RoundStatus::READY, RoundStatus::SCORING_OPEN));
        self::assertTrue($lifecycle->allows(RoundStatus::SCORING_OPEN, RoundStatus::SCORING_CLOSED));
        self::assertTrue($lifecycle->allows(RoundStatus::SCORING_CLOSED, RoundStatus::RESULTS_CALCULATED));
        self::assertTrue($lifecycle->allows(RoundStatus::RESULTS_CALCULATED, RoundStatus::RESULTS_VERIFIED));
        self::assertTrue($lifecycle->allows(RoundStatus::RESULTS_VERIFIED, RoundStatus::RESULTS_PUBLISHED));
    }

    public function testItPermitsOnlyPrePublicationInvalidationAndRejectsPublishedReopening(): void
    {
        $lifecycle = new RoundLifecycle();

        self::assertTrue($lifecycle->allows(RoundStatus::RESULTS_CALCULATED, RoundStatus::SCORING_CLOSED));
        self::assertTrue($lifecycle->allows(RoundStatus::RESULTS_VERIFIED, RoundStatus::SCORING_CLOSED));
        self::assertFalse($lifecycle->allows(RoundStatus::RESULTS_PUBLISHED, RoundStatus::SCORING_CLOSED));
        self::assertFalse($lifecycle->allows(RoundStatus::CANCELLED, RoundStatus::READY));
    }
}
