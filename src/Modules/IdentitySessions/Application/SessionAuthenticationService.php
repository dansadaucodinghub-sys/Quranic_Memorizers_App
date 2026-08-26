<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Application;

use DateTimeImmutable;
use Qmdb\Modules\Identity\Domain\AccountStatus;
use Qmdb\Modules\IdentitySessions\Configuration\IdentitySessionConfiguration;
use Qmdb\Modules\IdentitySessions\Domain\AuthenticationOutcome;
use Qmdb\Modules\IdentitySessions\Domain\DeviceStatus;
use Qmdb\Modules\IdentitySessions\Domain\Repository\SessionAuthenticationRecord;
use Qmdb\Modules\IdentitySessions\Domain\Repository\UserSessionRepository;
use Qmdb\Modules\IdentitySessions\Domain\SessionCookieParser;
use Qmdb\Modules\IdentitySessions\Domain\SessionCookieValue;
use Qmdb\Modules\IdentitySessions\Domain\SessionStatus;
use Qmdb\Modules\IdentitySessions\Domain\SessionTokenSecret;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;

final readonly class SessionAuthenticationService
{
    public function __construct(
        private UserSessionRepository $sessions,
        private TransactionManager $transactions,
        private SessionCookieParser $parser,
        private SessionCookieFactory $cookies,
        private DummySessionTokenHashProvider $dummyHash,
        private IdentitySessionConfiguration $configuration,
        private Clock $clock,
    ) {
    }

    public function authenticate(?string $rawCookie): SessionAuthenticationResult
    {
        if ($rawCookie === null || $rawCookie === '') {
            return SessionAuthenticationResult::anonymous();
        }
        try {
            $cookie = $this->parser->parse($rawCookie);
        } catch (\InvalidArgumentException) {
            return $this->invalid(AuthenticationOutcome::INVALID_COOKIE);
        }
        $record = $this->sessions->findAuthenticationRecord($cookie->sessionId);
        if ($record === null) {
            $dummy = $this->dummyHash->hash();
            $dummy->matches($cookie->secretForVerification());
            $dummy->matches($cookie->secretForVerification());

            return $this->invalid(AuthenticationOutcome::INVALID_COOKIE);
        }

        return $this->evaluate($record, $cookie->secretForVerification());
    }

    private function evaluate(
        SessionAuthenticationRecord $record,
        SessionTokenSecret $secret,
    ): SessionAuthenticationResult {
        $now = $this->clock->now();
        $currentMatches = $record->currentTokenHash->matches($secret);
        $previousHash = $record->previousTokenHash ?? $this->dummyHash->hash();
        $previousMatches = $previousHash->matches($secret);
        $previousValid = $record->previousTokenHash !== null
            && $record->previousTokenExpiresAt !== null
            && $now < $record->previousTokenExpiresAt
            && $previousMatches;
        if (!$currentMatches && !$previousValid) {
            return $this->invalid(AuthenticationOutcome::INVALID_COOKIE);
        }
        if ($record->status !== SessionStatus::ACTIVE) {
            return $this->invalid(AuthenticationOutcome::REVOKED);
        }
        if ($record->accountStatus !== AccountStatus::ACTIVE) {
            return $this->invalid(AuthenticationOutcome::ACCOUNT_NOT_ACTIVE);
        }
        if ($record->deviceStatus !== DeviceStatus::ACTIVE) {
            return $this->invalid(AuthenticationOutcome::DEVICE_NOT_ACTIVE);
        }
        if ($now >= $record->idleExpiresAt || $now >= $record->absoluteExpiresAt) {
            $this->sessions->markExpired($record->internalId, $record->version, $now);

            return $this->invalid(AuthenticationOutcome::EXPIRED);
        }

        if ($currentMatches && $this->rotationDue($record, $now)) {
            return $this->rotate($record, $secret, $now);
        }
        if ($currentMatches) {
            $this->touchWhenDue($record, $now);
        }

        return SessionAuthenticationResult::authenticated($this->context($record));
    }

    private function rotate(
        SessionAuthenticationRecord $record,
        SessionTokenSecret $supplied,
        DateTimeImmutable $now,
    ): SessionAuthenticationResult {
        $newSecret = SessionTokenSecret::generate();
        $graceEnds = $now->modify('+' . $this->configuration->previousTokenGraceSeconds . ' seconds');
        $rotated = $this->transactions->transactional(fn (): bool => $this->sessions->rotate(
            $record->internalId,
            $record->version,
            $supplied->hash(),
            $newSecret->hash(),
            $graceEnds,
            $now,
        ));
        if ($rotated) {
            $context = new AuthenticatedAccountContext(
                $record->accountInternalId,
                $record->accountId,
                $record->internalId,
                $record->publicId,
                $record->deviceInternalId,
                $record->deviceId,
                $record->authenticatedAt,
                $record->version + 1,
            );

            return SessionAuthenticationResult::authenticated(
                $context,
                $this->cookies->issue(new SessionCookieValue($record->publicId, $newSecret)),
            );
        }
        $reloaded = $this->sessions->findAuthenticationRecord($record->publicId);
        if (
            $reloaded !== null
            && $reloaded->status === SessionStatus::ACTIVE
            && $reloaded->previousTokenHash?->matches($supplied) === true
            && $reloaded->previousTokenExpiresAt !== null
            && $now < $reloaded->previousTokenExpiresAt
            && $reloaded->accountStatus === AccountStatus::ACTIVE
            && $reloaded->deviceStatus === DeviceStatus::ACTIVE
        ) {
            return SessionAuthenticationResult::authenticated($this->context($reloaded));
        }

        return $this->invalid(AuthenticationOutcome::INVALID_COOKIE);
    }

    private function touchWhenDue(SessionAuthenticationRecord $record, DateTimeImmutable $now): void
    {
        $due = $record->lastSeenAt->modify('+' . $this->configuration->touchIntervalSeconds . ' seconds');
        if ($now < $due) {
            return;
        }
        $idle = $now->modify('+' . $this->configuration->idleTtlSeconds . ' seconds');
        if ($idle > $record->absoluteExpiresAt) {
            $idle = $record->absoluteExpiresAt;
        }
        $this->sessions->touchSession($record->internalId, $record->version, $now, $idle);
    }

    private function rotationDue(SessionAuthenticationRecord $record, DateTimeImmutable $now): bool
    {
        return $now >= $record->rotatedAt->modify('+' . $this->configuration->rotationIntervalSeconds . ' seconds');
    }

    private function context(SessionAuthenticationRecord $record): AuthenticatedAccountContext
    {
        return new AuthenticatedAccountContext(
            $record->accountInternalId,
            $record->accountId,
            $record->internalId,
            $record->publicId,
            $record->deviceInternalId,
            $record->deviceId,
            $record->authenticatedAt,
            $record->version,
        );
    }

    private function invalid(AuthenticationOutcome $outcome): SessionAuthenticationResult
    {
        return SessionAuthenticationResult::invalid($outcome, $this->cookies->clear());
    }
}
