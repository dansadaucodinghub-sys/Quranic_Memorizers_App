<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Application;

use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\QuranReferenceGovernance\Domain\QuranReleaseAction;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class QuranReleaseTransitionCommand
{
    public function __construct(
        public AuthenticatedAccountContext $actor,
        public UuidV7 $releasePublicId,
        public int $expectedVersion,
        public UuidV7 $submissionId,
        public QuranReleaseAction $action,
        public ?string $reasonCode = null,
        public ?string $correlationId = null,
    ) {
        if ($expectedVersion < 1 || ($reasonCode !== null && preg_match('/\A[A-Z][A-Z0-9_]{1,95}\z/', $reasonCode) !== 1) || ($correlationId !== null && preg_match('/\A[a-f0-9]{32}\z/D', $correlationId) !== 1)) {
            throw new \InvalidArgumentException('Qur’an release transition command is invalid.');
        }
    }
}
