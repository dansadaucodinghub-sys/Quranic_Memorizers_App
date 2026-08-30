<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Application;

use Qmdb\Modules\IdentitySessions\Domain\Repository\UserSessionRepository;
use Qmdb\Modules\IdentitySessions\Domain\SessionId;
use Qmdb\Modules\IdentitySessions\Domain\SessionRevocationReason;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;

final readonly class RemoteSessionRevocationService
{
    public function __construct(
        private UserSessionRepository $sessions,
        private SecurityAuditEventAppender $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    public function revoke(
        AuthenticatedAccountContext $context,
        SessionId $target,
        int $expectedVersion,
    ): RevocationOutcome {
        if ($target->toString() === $context->sessionId->toString()) {
            return RevocationOutcome::CURRENT_RESOURCE;
        }
        $found = null;
        foreach ($this->sessions->listSessionsForAccount($context->accountInternalId) as $session) {
            if ($session->publicId === $target->toString()) {
                $found = $session;
                break;
            }
        }
        if ($found === null) {
            return RevocationOutcome::NOT_FOUND;
        }
        if ($found->status !== 'ACTIVE') {
            return RevocationOutcome::ALREADY_INACTIVE;
        }
        if ($found->version !== $expectedVersion) {
            return RevocationOutcome::VERSION_CONFLICT;
        }

        return $this->transactions->transactional(function () use ($context, $target, $expectedVersion): RevocationOutcome {
            $now = $this->clock->now();
            if (
                !$this->sessions->revokeOwned(
                    $context->accountInternalId,
                    $target,
                    $expectedVersion,
                    SessionRevocationReason::REMOTE_SESSION_REVOCATION,
                    $now,
                )
            ) {
                return RevocationOutcome::VERSION_CONFLICT;
            }
            $this->audit->account(
                SecurityEventCode::SESSION_REMOTE_REVOKED,
                $context->accountId->toString(),
                $context->accountId->toString(),
                $context->sessionId->toString(),
                $now,
            );

            return RevocationOutcome::REVOKED;
        });
    }
}
