<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Application;

use Qmdb\Modules\IdentityAccess\Application\Authentication\PasswordAuthenticationOutcome;
use Qmdb\Modules\IdentityAccess\Application\Authentication\PasswordAuthenticationRepository;
use Qmdb\Modules\IdentityAccess\Application\Authentication\PasswordAuthenticationService;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordHasher;
use Qmdb\Modules\IdentitySessions\Configuration\IdentitySessionConfiguration;
use Qmdb\Modules\IdentitySessions\Domain\DeviceCookieParser;
use Qmdb\Modules\IdentitySessions\Domain\DeviceCookieValue;
use Qmdb\Modules\IdentitySessions\Domain\DeviceId;
use Qmdb\Modules\IdentitySessions\Domain\DeviceStatus;
use Qmdb\Modules\IdentitySessions\Domain\DeviceTokenSecret;
use Qmdb\Modules\IdentitySessions\Domain\DuplicateLoginSubmissionException;
use Qmdb\Modules\IdentitySessions\Domain\Repository\DeviceAuthenticationRecord;
use Qmdb\Modules\IdentitySessions\Domain\Repository\UserDeviceRepository;
use Qmdb\Modules\IdentitySessions\Domain\Repository\UserSessionRepository;
use Qmdb\Modules\IdentitySessions\Domain\SessionCookieValue;
use Qmdb\Modules\IdentitySessions\Domain\SessionId;
use Qmdb\Modules\IdentitySessions\Domain\SessionRevocationReason;
use Qmdb\Modules\IdentitySessions\Domain\SessionTokenSecret;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;

final readonly class AccountLoginService
{
    public function __construct(
        private PasswordAuthenticationService $passwords,
        private PasswordAuthenticationRepository $credentials,
        private PasswordHasher $hasher,
        private UserDeviceRepository $devices,
        private UserSessionRepository $sessions,
        private TransactionManager $transactions,
        private DeviceCookieParser $deviceParser,
        private SessionCookieFactory $sessionCookies,
        private DeviceCookieFactory $deviceCookies,
        private IdentitySessionConfiguration $configuration,
        private Clock $clock,
    ) {
    }

    public function login(AccountLoginCommand $command): AccountLoginResult
    {
        $authentication = $this->passwords->authenticate($command->email, $command->password, $command->peer);
        if ($authentication->outcome === PasswordAuthenticationOutcome::THROTTLED) {
            return AccountLoginResult::throttled($authentication->retryAfterSeconds);
        }
        if ($authentication->outcome !== PasswordAuthenticationOutcome::VERIFIED) {
            return AccountLoginResult::invalid();
        }
        $principal = $authentication->verifiedPrincipal();
        $rehash = $authentication->passwordRehashRequired
            ? $this->hasher->hash($command->password, $command->password->revealForHashing())
            : null;
        $sessionId = SessionId::generate();
        $sessionSecret = SessionTokenSecret::generate();
        [$device, $deviceValue] = $this->resolveDevice(
            $principal->accountInternalId,
            $command->deviceCookie,
        );
        $now = $this->clock->now();
        $idle = $now->modify('+' . $this->configuration->idleTtlSeconds . ' seconds');
        $absolute = $now->modify('+' . $this->configuration->absoluteTtlSeconds . ' seconds');
        if ($idle > $absolute) {
            $idle = $absolute;
        }
        try {
            $created = $this->transactions->transactional(function () use (
                $principal,
                $command,
                $rehash,
                &$device,
                $deviceValue,
                $now,
                $sessionId,
                $sessionSecret,
                $idle,
                $absolute,
            ): bool {
                $this->sessions->lockAccount($principal->accountInternalId);
                if ($this->sessions->loginSubmissionExists($command->submissionId)) {
                    return false;
                }
                if (
                    $command->currentContext !== null
                    && $command->currentContext->accountInternalId === $principal->accountInternalId
                ) {
                    $this->sessions->revokeCurrent(
                        $principal->accountInternalId,
                        $command->currentContext->sessionInternalId,
                        SessionRevocationReason::REAUTHENTICATION,
                        $now,
                    );
                }
                if ($rehash !== null) {
                    $this->credentials->replacePasswordHash($principal->accountInternalId, $rehash, $now);
                }
                if ($device === null && $deviceValue !== null) {
                    $device = $this->devices->createDevice(
                        $principal->accountInternalId,
                        $deviceValue->deviceId,
                        $deviceValue->secretForVerification()->hash(),
                        $now,
                    );
                }
                if (!$device instanceof DeviceAuthenticationRecord) {
                    throw new \LogicException('Authenticated device resolution failed.');
                }
                $this->sessions->expireEffectiveSessions($principal->accountInternalId, $now);
                $this->sessions->revokeOldestForLimit(
                    $principal->accountInternalId,
                    $this->configuration->maximumActiveSessions - 1,
                    $now,
                );
                $this->sessions->createSession(
                    $sessionId,
                    $principal->accountInternalId,
                    $device->internalId,
                    $command->submissionId,
                    $sessionSecret->hash(),
                    $now,
                    $idle,
                    $absolute,
                );

                return true;
            });
        } catch (DuplicateLoginSubmissionException) {
            return AccountLoginResult::replayed();
        }
        if (!$created) {
            return AccountLoginResult::replayed();
        }
        $instructions = [
            $this->sessionCookies->issue(new SessionCookieValue($sessionId, $sessionSecret)),
        ];
        if ($deviceValue !== null) {
            $instructions[] = $this->deviceCookies->issue($deviceValue);
        }

        return AccountLoginResult::authenticated($instructions);
    }

    /** @return array{?DeviceAuthenticationRecord, ?DeviceCookieValue} */
    private function resolveDevice(int $accountInternalId, ?string $rawCookie): array
    {
        if (is_string($rawCookie) && $rawCookie !== '') {
            try {
                $cookie = $this->deviceParser->parse($rawCookie);
                $record = $this->devices->findForAccount($accountInternalId, $cookie->deviceId);
                if (
                    $record !== null
                    && $record->status === DeviceStatus::ACTIVE
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
