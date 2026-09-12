<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\CompetitionResults;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\CompetitionResults\Domain\AppealLifecycle;
use Qmdb\Modules\CompetitionResults\Domain\AppealStatus;
use Qmdb\Modules\CompetitionResults\Domain\ResultRunLifecycle;
use Qmdb\Modules\CompetitionResults\Domain\ResultRunStatus;

final class ResultAndAppealLifecycleTest extends TestCase
{
    public function testPublishedResultsCanOnlyBeSuperseded(): void
    {
        $lifecycle = new ResultRunLifecycle();

        self::assertTrue($lifecycle->allows(ResultRunStatus::VERIFIED, ResultRunStatus::PUBLISHED));
        self::assertTrue($lifecycle->allows(ResultRunStatus::PUBLISHED, ResultRunStatus::SUPERSEDED));
        self::assertFalse($lifecycle->allows(ResultRunStatus::PUBLISHED, ResultRunStatus::VOIDED));
        self::assertTrue($lifecycle->allows(ResultRunStatus::CALCULATED, ResultRunStatus::VOIDED));
    }

    public function testAppealsBecomeTerminalAfterDecisionOrWithdrawal(): void
    {
        $lifecycle = new AppealLifecycle();

        self::assertTrue($lifecycle->allows(AppealStatus::SUBMITTED, AppealStatus::UNDER_REVIEW));
        self::assertTrue($lifecycle->allows(AppealStatus::UNDER_REVIEW, AppealStatus::UPHELD));
        self::assertFalse($lifecycle->allows(AppealStatus::UPHELD, AppealStatus::UNDER_REVIEW));
        self::assertFalse($lifecycle->allows(AppealStatus::WITHDRAWN, AppealStatus::UNDER_REVIEW));
    }
}
