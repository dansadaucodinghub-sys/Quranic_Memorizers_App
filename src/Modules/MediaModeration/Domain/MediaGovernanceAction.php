<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaModeration\Domain;

use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;

enum MediaGovernanceAction: string
{
    case APPROVE = 'approve';
    case REJECT = 'reject';
    case WITHDRAW_CONSENT = 'withdraw-consent';
    case GRANT_CONSENT = 'consent-review';
    case HOLD = 'hold';
    case RELEASE_HOLD = 'release-hold';
    case REMOVE = 'remove';
    case ARCHIVE = 'archive';

    public function permission(): string
    {
        return match ($this) {
            self::APPROVE => 'workspace.media.approve',
            self::REJECT => 'workspace.media.reject',
            self::WITHDRAW_CONSENT => 'media.assets.manage_own',
            self::GRANT_CONSENT => 'workspace.media.review',
            self::HOLD, self::RELEASE_HOLD => 'workspace.media.hold',
            self::REMOVE, self::ARCHIVE => 'workspace.media.remove',
        };
    }

    public function csrf(): CsrfAction
    {
        return CsrfAction::from('media.' . str_replace('-', '_', $this->value));
    }

    public function stepUp(): ?StepUpAction
    {
        return match ($this) {
            self::WITHDRAW_CONSENT => null,
            self::GRANT_CONSENT => StepUpAction::MEDIA_CONSENT_GRANT,
            self::APPROVE => StepUpAction::MEDIA_APPROVE,
            self::REJECT => StepUpAction::MEDIA_REJECT,
            self::HOLD, self::RELEASE_HOLD => StepUpAction::MEDIA_HOLD,
            self::REMOVE, self::ARCHIVE => StepUpAction::MEDIA_REMOVE,
        };
    }
}
