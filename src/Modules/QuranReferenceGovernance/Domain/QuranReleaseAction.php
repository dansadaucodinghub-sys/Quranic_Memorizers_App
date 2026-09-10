<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Domain;

use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;

enum QuranReleaseAction: string
{
    case STAGE = 'STAGE';
    case VALIDATE = 'VALIDATE';
    case APPROVE = 'APPROVE';
    case ACTIVATE = 'ACTIVATE';
    case REJECT = 'REJECT';

    public function targetStatus(): string
    {
        return match ($this) {
            self::STAGE => 'STAGED', self::VALIDATE => 'VALIDATED', self::APPROVE => 'APPROVED',
            self::ACTIVATE => 'ACTIVE', self::REJECT => 'REJECTED',
        };
    }

    public function permission(): string
    {
        return match ($this) {
            self::APPROVE => 'platform.quran_releases.approve', self::ACTIVATE => 'platform.quran_releases.activate',
            default => 'platform.quran_releases.manage',
        };
    }

    public function stepUpAction(): ?StepUpAction
    {
        return match ($this) {
            self::APPROVE => StepUpAction::QURAN_RELEASE_APPROVE,
            self::ACTIVATE => StepUpAction::QURAN_RELEASE_ACTIVATE,
            self::REJECT => StepUpAction::QURAN_RELEASE_REJECT,
            default => null,
        };
    }

    public function auditCode(): SecurityEventCode
    {
        return match ($this) {
            self::STAGE => SecurityEventCode::QURAN_RELEASE_STAGED,
            self::VALIDATE => SecurityEventCode::QURAN_RELEASE_VALIDATED,
            self::APPROVE => SecurityEventCode::QURAN_RELEASE_APPROVED,
            self::ACTIVATE => SecurityEventCode::QURAN_RELEASE_ACTIVATED,
            self::REJECT => SecurityEventCode::QURAN_RELEASE_REJECTED,
        };
    }
}
