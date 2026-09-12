<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\CompetitionJudging;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;

final class P6SecurityActionCatalogTest extends TestCase
{
    public function testP6MutationsUseActionSpecificCsrfTokens(): void
    {
        self::assertSame('competition.score.lock', CsrfAction::COMPETITION_SCORE_LOCK->value);
        self::assertSame('competition.result.publish', CsrfAction::COMPETITION_RESULT_PUBLISH->value);
        self::assertSame('competition.appeal.uphold', CsrfAction::COMPETITION_APPEAL_UPHOLD->value);
    }

    public function testHighRiskP6ActionsRequirePhishingResistantStepUp(): void
    {
        self::assertSame(AuthenticationAssuranceLevel::PHISHING_RESISTANT, StepUpAction::COMPETITION_RESULT_PUBLISH->requirement());
        self::assertSame(AuthenticationAssuranceLevel::PHISHING_RESISTANT, StepUpAction::COMPETITION_APPEAL_DISMISS->requirement());
        self::assertSame('/workspace/competitions', StepUpAction::COMPETITION_SCORE_CORRECT->continuation());
    }

    public function testP6RateLimitsAndAuditKindsAreClosedCatalogs(): void
    {
        self::assertSame('COMPETITION_JUDGE_SCORE_SUBMIT_PEER', IdentityRateLimitScope::COMPETITION_JUDGE_SCORE_SUBMIT_PEER->value);
        self::assertSame(SecurityEventSubjectKind::COMPETITION_RESULT_RUN, SecurityEventSubjectKind::from('COMPETITION_RESULT_RUN'));
        self::assertSame('competition.appeal.decided', SecurityEventCode::COMPETITION_APPEAL_DECIDED->value);
    }
}
