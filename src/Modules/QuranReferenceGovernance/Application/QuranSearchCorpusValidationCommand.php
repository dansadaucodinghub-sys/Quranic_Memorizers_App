<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Application;

use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class QuranSearchCorpusValidationCommand
{
    public function __construct(
        public AuthenticatedAccountContext $actor,
        public UuidV7 $releasePublicId,
        public int $expectedCorpusVersion,
        public UuidV7 $submissionId,
    ) {
    }
}
