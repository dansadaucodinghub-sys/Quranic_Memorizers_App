<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\CompetitionConfiguration;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\CompetitionConfiguration\Domain\CompetitionEditionStatus;

final class CompetitionEditionStatusTest extends TestCase
{
    public function testItAllowsOnlyTheP5EditionLifecycle(): void
    {
        self::assertTrue(CompetitionEditionStatus::DRAFT->canTransitionTo(CompetitionEditionStatus::PUBLISHED));
        self::assertTrue(CompetitionEditionStatus::REGISTRATION_CLOSED->canTransitionTo(CompetitionEditionStatus::ROSTER_FINALIZED));
        self::assertFalse(CompetitionEditionStatus::PUBLISHED->canTransitionTo(CompetitionEditionStatus::ROSTER_FINALIZED));
        self::assertFalse(CompetitionEditionStatus::ROSTER_FINALIZED->canTransitionTo(CompetitionEditionStatus::REGISTRATION_OPEN));
    }
}
