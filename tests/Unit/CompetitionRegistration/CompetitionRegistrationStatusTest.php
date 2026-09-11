<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\CompetitionRegistration;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\CompetitionRegistration\Domain\CompetitionRegistrationStatus;

final class CompetitionRegistrationStatusTest extends TestCase
{
    public function testItProtectsTerminalRegistrationStates(): void
    {
        self::assertTrue(CompetitionRegistrationStatus::SUBMITTED->canTransitionTo(CompetitionRegistrationStatus::UNDER_REVIEW));
        self::assertTrue(CompetitionRegistrationStatus::APPROVED->canTransitionTo(CompetitionRegistrationStatus::ROSTERED));
        self::assertFalse(CompetitionRegistrationStatus::ROSTERED->canTransitionTo(CompetitionRegistrationStatus::WITHDRAWN));
        self::assertTrue(CompetitionRegistrationStatus::ROSTERED->isTerminal());
    }
}
