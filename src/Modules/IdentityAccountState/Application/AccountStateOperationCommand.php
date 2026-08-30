<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccountState\Application;

use Qmdb\Modules\IdentityAccountState\Domain\AccountStateJustification;
use Qmdb\Modules\IdentityAccountState\Domain\AccountStateOperationType;
use Qmdb\Modules\IdentityAccountState\Domain\AccountStateReasonCode;
use Qmdb\Modules\IdentityAccountState\Domain\AccountStateReference;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class AccountStateOperationCommand
{
    public function __construct(
        public AuthenticatedAccountContext $actor,
        public AccountStateOperationType $operation,
        public UuidV7 $targetAccountId,
        public int $expectedAccountVersion,
        public UuidV7 $submissionId,
        public AccountStateReasonCode $reason,
        public AccountStateJustification $justification,
        public AccountStateReference $reference,
        public ?string $correlationId,
    ) {
        if (
            $expectedAccountVersion < 1
            || !$reason->permits($operation)
            || ($correlationId !== null && preg_match('/\A[a-f0-9]{32}\z/D', $correlationId) !== 1)
        ) {
            throw new \InvalidArgumentException('Account-state operation is invalid.');
        }
    }
}
