<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Application;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Qmdb\Modules\IdentityMultiFactor\Configuration\IdentityMultiFactorConfiguration;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\TotpAuthenticatorRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentityMultiFactor\Domain\TotpAuthenticatorStatus;
use Qmdb\Modules\IdentityMultiFactor\Domain\TotpSecret;
use Qmdb\Modules\IdentityMultiFactor\Domain\TotpSecretEncryptor;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;
use SensitiveParameter;

final readonly class TotpEnrollmentService
{
    public function __construct(
        private TotpAuthenticatorRepository $authenticators,
        private TotpSecretEncryptor $encryptor,
        private TotpVerifier $verifier,
        private StepUpGuard $stepUp,
        private MultiFactorNotificationService $notifications,
        private TransactionManager $transactions,
        private IdentityMultiFactorConfiguration $configuration,
        private SecurityAuditEventAppender $audit,
        private Clock $clock,
    ) {
    }

    public function start(AuthenticatedAccountContext $context): TotpEnrollmentResult
    {
        $secret = TotpSecret::generate();
        $publicId = UuidV7::generate()->toString();
        $encrypted = $this->encryptor->encrypt($secret, $context->accountId->toString(), $publicId);
        $now = $this->clock->now();
        $expiresAt = $now->modify('+' . $this->configuration->totpEnrollmentTtlSeconds . ' seconds');
        $this->transactions->transactional(function () use (
            $context,
            $publicId,
            $encrypted,
            $expiresAt,
            $now,
        ): void {
            $this->stepUp->consume($context, StepUpAction::MFA_ENROLL_TOTP);
            $this->authenticators->createPendingTotp(
                $context->accountInternalId,
                $publicId,
                $encrypted,
                $expiresAt,
                $now,
            );
        });

        return new TotpEnrollmentResult(
            $publicId,
            $secret,
            $this->verifier->provisioningUri($secret, $context->accountId->toString()),
            $expiresAt,
        );
    }

    public function confirm(
        AuthenticatedAccountContext $context,
        string $authenticatorPublicId,
        #[SensitiveParameter] string $code,
    ): bool {
        $now = $this->clock->now();
        $candidate = $this->authenticators->findTotp($context->accountInternalId, $authenticatorPublicId);
        if (
            $candidate === null || $candidate->status !== TotpAuthenticatorStatus::PENDING
            || $candidate->enrollmentExpiresAt === null || $now >= $candidate->enrollmentExpiresAt
        ) {
            return false;
        }
        $secret = $this->encryptor->decrypt(
            $candidate->secret,
            $context->accountId->toString(),
            $candidate->publicId,
        );
        $counter = $this->verifier->acceptedCounter($secret, $code, $now, null);
        if ($counter === null) {
            return false;
        }

        return $this->transactions->transactional(function () use ($context, $candidate, $counter, $now): bool {
            $locked = $this->authenticators->findTotp(
                $context->accountInternalId,
                $candidate->publicId,
                true,
            );
            if (
                $locked === null || $locked->status !== TotpAuthenticatorStatus::PENDING
                || $locked->enrollmentExpiresAt === null || $now >= $locked->enrollmentExpiresAt
                || !$this->authenticators->confirmTotp($locked, $counter, $now)
            ) {
                return false;
            }
            $this->notifications->create(
                $context->accountInternalId,
                AccountSecurityNotificationType::TOTP_AUTHENTICATOR_ADDED,
                $locked->publicId,
                $now,
            );
            $this->audit->account(SecurityEventCode::TOTP_ADDED, $context->accountId->toString(), $context->accountId->toString(), $context->sessionId->toString(), $now, ['authenticator_public_id' => $locked->publicId]);

            return true;
        });
    }

    public function qrSvg(AuthenticatedAccountContext $context, string $authenticatorPublicId): string
    {
        $authenticator = $this->authenticators->findTotp($context->accountInternalId, $authenticatorPublicId);
        $now = $this->clock->now();
        if (
            $authenticator === null || $authenticator->status !== TotpAuthenticatorStatus::PENDING
            || $authenticator->enrollmentExpiresAt === null || $now >= $authenticator->enrollmentExpiresAt
        ) {
            throw new \DomainException('TOTP enrollment is unavailable.');
        }
        $secret = $this->encryptor->decrypt(
            $authenticator->secret,
            $context->accountId->toString(),
            $authenticator->publicId,
        );
        $result = (new SvgWriter())->write(new QrCode(
            $this->verifier->provisioningUri($secret, $context->accountId->toString()),
        ));

        return $result->getString();
    }
}
