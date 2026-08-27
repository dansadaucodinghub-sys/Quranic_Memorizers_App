<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Application;

use Qmdb\Modules\IdentityMultiFactor\Domain\SessionAuthenticationAssurance;
use Qmdb\Modules\IdentitySessions\Application\AuthenticationCookieInstruction;
use Qmdb\Modules\IdentitySessions\Application\DeviceCookieFactory;
use Qmdb\Modules\IdentitySessions\Application\SessionCookieFactory;
use Qmdb\Modules\IdentitySessions\Configuration\IdentitySessionConfiguration;
use Qmdb\Modules\IdentitySessions\Domain\DeviceCookieParser;
use Qmdb\Modules\IdentitySessions\Domain\DeviceCookieValue;
use Qmdb\Modules\IdentitySessions\Domain\DeviceId;
use Qmdb\Modules\IdentitySessions\Domain\DeviceStatus;
use Qmdb\Modules\IdentitySessions\Domain\DeviceTokenSecret;
use Qmdb\Modules\IdentitySessions\Domain\LoginSubmissionId;
use Qmdb\Modules\IdentitySessions\Domain\Repository\DeviceAuthenticationRecord;
use Qmdb\Modules\IdentitySessions\Domain\Repository\UserDeviceRepository;
use Qmdb\Modules\IdentitySessions\Domain\Repository\UserSessionRepository;
use Qmdb\Modules\IdentitySessions\Domain\SessionCookieValue;
use Qmdb\Modules\IdentitySessions\Domain\SessionId;
use Qmdb\Modules\IdentitySessions\Domain\SessionTokenSecret;
use Qmdb\Shared\Time\Clock;

final readonly class StrongSessionIssuanceService
{
    public function __construct(
        private UserDeviceRepository $devices,
        private UserSessionRepository $sessions,
        private DeviceCookieParser $deviceParser,
        private SessionCookieFactory $sessionCookies,
        private DeviceCookieFactory $deviceCookies,
        private IdentitySessionConfiguration $configuration,
        private Clock $clock,
    ) {
    }

    /** @return list<AuthenticationCookieInstruction> */
    public function issueWithinTransaction(
        int $accountInternalId,
        ?string $rawDeviceCookie,
        SessionAuthenticationAssurance $assurance,
    ): array {
        $sessionId = SessionId::generate();
        $sessionSecret = SessionTokenSecret::generate();
        [$device, $deviceValue] = $this->resolveDevice($accountInternalId, $rawDeviceCookie);
        $now = $this->clock->now();
        $this->sessions->lockAccount($accountInternalId);
        if ($device === null && $deviceValue !== null) {
            $device = $this->devices->createDevice(
                $accountInternalId,
                $deviceValue->deviceId,
                $deviceValue->secretForVerification()->hash(),
                $now,
            );
        }
        if (!$device instanceof DeviceAuthenticationRecord) {
            throw new \UnexpectedValueException('Authenticated device resolution failed.');
        }
        $this->sessions->expireEffectiveSessions($accountInternalId, $now);
        $this->sessions->revokeOldestForLimit(
            $accountInternalId,
            $this->configuration->maximumActiveSessions - 1,
            $now,
        );
        $idle = $now->modify('+' . $this->configuration->idleTtlSeconds . ' seconds');
        $absolute = $now->modify('+' . $this->configuration->absoluteTtlSeconds . ' seconds');
        if ($idle > $absolute) {
            $idle = $absolute;
        }
        $this->sessions->createSession(
            $sessionId,
            $accountInternalId,
            $device->internalId,
            LoginSubmissionId::generate(),
            $sessionSecret->hash(),
            $now,
            $idle,
            $absolute,
            $assurance,
        );
        $instructions = [$this->sessionCookies->issue(new SessionCookieValue($sessionId, $sessionSecret))];
        if ($deviceValue !== null) {
            $instructions[] = $this->deviceCookies->issue($deviceValue);
        }

        return $instructions;
    }

    /** @return array{?DeviceAuthenticationRecord, ?DeviceCookieValue} */
    private function resolveDevice(int $accountInternalId, ?string $rawCookie): array
    {
        if (is_string($rawCookie) && $rawCookie !== '') {
            try {
                $cookie = $this->deviceParser->parse($rawCookie);
                $record = $this->devices->findForAccount($accountInternalId, $cookie->deviceId);
                if (
                    $record !== null && $record->status === DeviceStatus::ACTIVE
                    && $record->tokenHash->matches($cookie->secretForVerification())
                ) {
                    return [$record, null];
                }
            } catch (\InvalidArgumentException) {
            }
        }
        $secret = DeviceTokenSecret::generate();

        return [null, new DeviceCookieValue(DeviceId::generate(), $secret)];
    }
}
