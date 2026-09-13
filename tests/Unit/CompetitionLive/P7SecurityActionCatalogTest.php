<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\CompetitionLive;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalogRegistry;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;

final class P7SecurityActionCatalogTest extends TestCase
{
    public function testLiveOperationsUseNarrowRegisteredPermissionAndCsrfActions(): void
    {
        $catalog = AuthorizationCatalogRegistry::withPeopleIdentityResolution();
        $permission = $catalog->permission(new PermissionCode('workspace.competitions.operate_live'));

        self::assertNotNull($permission);
        self::assertSame(AuthenticationAssuranceLevel::MULTI_FACTOR, $permission->requiredAssurance);
        self::assertSame('competition.live_session.open', CsrfAction::COMPETITION_LIVE_SESSION_OPEN->value);
        self::assertSame('competition.live_session.complete_recovery', CsrfAction::COMPETITION_LIVE_SESSION_COMPLETE_RECOVERY->value);
        self::assertSame('competition.live_participant.check_in', CsrfAction::COMPETITION_LIVE_PARTICIPANT_CHECK_IN->value);
    }

    public function testTerminalLiveOperationsRequirePhishingResistantStepUpAndAuditableSubject(): void
    {
        self::assertSame(AuthenticationAssuranceLevel::PHISHING_RESISTANT, StepUpAction::COMPETITION_LIVE_SESSION_CLOSE->requirement());
        self::assertSame(AuthenticationAssuranceLevel::PHISHING_RESISTANT, StepUpAction::COMPETITION_LIVE_SESSION_CANCEL->requirement());
        self::assertSame(SecurityEventSubjectKind::COMPETITION_LIVE_SESSION, SecurityEventSubjectKind::from('COMPETITION_LIVE_SESSION'));
        self::assertSame('competition.live_session.recovered', SecurityEventCode::COMPETITION_LIVE_SESSION_RECOVERED->value);
        self::assertSame(SecurityEventSubjectKind::COMPETITION_LIVE_PARTICIPANT, SecurityEventSubjectKind::from('COMPETITION_LIVE_PARTICIPANT'));
    }
}
