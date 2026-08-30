<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Application;

use Qmdb\Modules\IdentitySessions\Domain\DeviceId;
use Qmdb\Modules\IdentitySessions\Domain\DeviceStatus;
use Qmdb\Modules\IdentitySessions\Domain\Repository\UserDeviceRepository;
use Qmdb\Modules\IdentitySessions\Domain\Repository\UserSessionRepository;
use Qmdb\Modules\IdentitySessions\Domain\SessionRevocationReason;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;

final readonly class RemoteDeviceRevocationService
{
    public function __construct(
        private UserDeviceRepository $devices,
        private UserSessionRepository $sessions,
        private TransactionManager $transactions,
        private SecurityAuditEventAppender $audit,
        private Clock $clock,
    ) {
    }

    public function revoke(
        AuthenticatedAccountContext $context,
        DeviceId $target,
        int $expectedVersion,
    ): RevocationOutcome {
        if ($target->toString() === $context->deviceId->toString()) {
            return RevocationOutcome::CURRENT_RESOURCE;
        }
        $device = $this->devices->findForAccount($context->accountInternalId, $target);
        if ($device === null) {
            return RevocationOutcome::NOT_FOUND;
        }
        if ($device->status !== DeviceStatus::ACTIVE) {
            return RevocationOutcome::ALREADY_INACTIVE;
        }
        if ($device->version !== $expectedVersion) {
            return RevocationOutcome::VERSION_CONFLICT;
        }
        $now = $this->clock->now();
        $changed = $this->transactions->transactional(function () use (
            $context,
            $target,
            $expectedVersion,
            $device,
            $now,
        ): bool {
            if (!$this->devices->revoke($context->accountInternalId, $target, $expectedVersion, $now)) {
                return false;
            }
            $this->sessions->revokeForDevice(
                $context->accountInternalId,
                $device->internalId,
                SessionRevocationReason::DEVICE_REVOCATION,
                $now,
            );
            $this->audit->account(
                SecurityEventCode::DEVICE_REVOKED,
                $context->accountId->toString(),
                $context->accountId->toString(),
                $context->sessionId->toString(),
                $now,
            );

            return true;
        });

        return $changed ? RevocationOutcome::REVOKED : RevocationOutcome::VERSION_CONFLICT;
    }
}
