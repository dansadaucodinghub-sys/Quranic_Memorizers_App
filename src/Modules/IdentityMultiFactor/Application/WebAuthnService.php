<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Application;

use Qmdb\Modules\IdentityMultiFactor\Configuration\IdentityMultiFactorConfiguration;
use Qmdb\Modules\IdentityMultiFactor\Domain\PasskeyCredential;
use Qmdb\Modules\IdentityMultiFactor\Domain\PasskeyCredentialStatus;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\PasskeyCredentialRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\WebAuthnCeremonyRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\WebAuthnUserHandleRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\MultiFactorNotificationTargetRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentityMultiFactor\Domain\WebAuthnCeremonyPurpose;
use Qmdb\Modules\IdentityMultiFactor\Domain\WebAuthnChallenge;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Security\PasskeyCounterChecker;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\EmptyTrustPath;

final readonly class WebAuthnService
{
    private SerializerInterface&NormalizerInterface $serializer;

    public function __construct(
        private WebAuthnCeremonyRepository $ceremonies,
        private WebAuthnUserHandleRepository $userHandles,
        private PasskeyCredentialRepository $passkeys,
        private StepUpGuard $stepUp,
        private MultiFactorNotificationService $notifications,
        private MultiFactorNotificationTargetRepository $targets,
        private TransactionManager $transactions,
        private IdentityMultiFactorConfiguration $configuration,
        private SecurityAuditEventAppender $audit,
        private Clock $clock,
    ) {
        $serializer = (new WebauthnSerializerFactory(AttestationStatementSupportManager::create()))->create();
        if (!$serializer instanceof NormalizerInterface) {
            throw new \RuntimeException('WebAuthn serializer cannot normalize credential options.');
        }
        $this->serializer = $serializer;
    }

    /** @return array{ceremonyId: string, publicKey: array<string, mixed>} */
    public function registrationOptions(AuthenticatedAccountContext $context): array
    {
        $this->stepUp->require($context, StepUpAction::MFA_REGISTER_PASSKEY);
        $now = $this->clock->now();
        $handle = $this->userHandles->findOrCreateUserHandle($context->accountInternalId, $now);
        $challenge = WebAuthnChallenge::generate();
        $ceremony = $this->transactions->transactional(function () use ($context, $challenge, $now) {
            $this->ceremonies->revokePendingCeremonies(
                $context->sessionInternalId,
                null,
                WebAuthnCeremonyPurpose::PASSKEY_REGISTRATION,
                $now,
            );

            return $this->ceremonies->createCeremony(
                $context->accountInternalId,
                $context->sessionInternalId,
                null,
                WebAuthnCeremonyPurpose::PASSKEY_REGISTRATION,
                $challenge,
                $this->configuration->stepUpMaximumAttempts,
                $now,
                $now->modify('+' . $this->configuration->webauthnChallengeTtlSeconds . ' seconds'),
            );
        });
        $options = $this->creationOptions($context, $handle, $challenge);

        return ['ceremonyId' => $ceremony->publicId, 'publicKey' => $this->normalize($options)];
    }

    public function verifyRegistration(
        AuthenticatedAccountContext $context,
        string $ceremonyPublicId,
        string $displayName,
        string $credentialJson,
    ): PasskeyCredential {
        $displayName = trim($displayName);
        if (
            $displayName === '' || mb_strlen($displayName) > 120
            || strlen($credentialJson) > $this->configuration->webauthnMaximumResponseBytes
        ) {
            throw new \DomainException('Passkey registration response is invalid.');
        }
        $ceremony = $this->ceremonies->findCeremony($ceremonyPublicId);
        if (
            $ceremony === null || $ceremony->purpose !== WebAuthnCeremonyPurpose::PASSKEY_REGISTRATION
            || $ceremony->accountInternalId !== $context->accountInternalId
            || $ceremony->sessionInternalId !== $context->sessionInternalId
        ) {
            throw new \DomainException('Passkey registration ceremony is invalid.');
        }
        $credential = $this->deserializeCredential($credentialJson);
        if (!$credential->response instanceof AuthenticatorAttestationResponse) {
            throw new \DomainException('Passkey registration response is invalid.');
        }
        $challenge = new WebAuthnChallenge($credential->response->clientDataJSON->challenge);
        if (!$ceremony->accepts($challenge, $this->clock->now())) {
            $this->recordFailure($ceremonyPublicId);
            throw new \DomainException('Passkey registration ceremony is invalid.');
        }
        $handle = $this->userHandles->findOrCreateUserHandle($context->accountInternalId, $this->clock->now());
        $options = $this->creationOptions($context, $handle, $challenge);
        $record = AuthenticatorAttestationResponseValidator::create($this->manager()->creationCeremony())->check(
            $credential->response,
            $options,
            $this->configuration->relyingPartyId,
        );
        if (!hash_equals($handle, $record->userHandle)) {
            throw new \DomainException('Passkey registration user binding is invalid.');
        }
        $now = $this->clock->now();

        return $this->transactions->transactional(function () use (
            $context,
            $ceremonyPublicId,
            $displayName,
            $record,
            $now,
        ): PasskeyCredential {
            $locked = $this->ceremonies->findCeremony($ceremonyPublicId, true);
            if (
                $locked === null || $locked->purpose !== WebAuthnCeremonyPurpose::PASSKEY_REGISTRATION
                || $locked->accountInternalId !== $context->accountInternalId
                || $locked->sessionInternalId !== $context->sessionInternalId
            ) {
                throw new \DomainException('Passkey registration ceremony changed.');
            }
            $this->stepUp->consume($context, StepUpAction::MFA_REGISTER_PASSKEY);
            if ($this->passkeys->findPasskeyByCredentialId($record->publicKeyCredentialId, true) !== null) {
                throw new \DomainException('Passkey credential is already registered.');
            }
            $passkey = $this->passkeys->createPasskey(
                $context->accountInternalId,
                UuidV7::generate()->toString(),
                $record->publicKeyCredentialId,
                $record->credentialPublicKey,
                $record->counter,
                $record->aaguid->toBinary(),
                array_values($record->transports),
                $record->backupEligible ?? false,
                $record->backupStatus ?? false,
                $record->attestationType,
                $displayName,
                $now,
            );
            if (!$this->ceremonies->consumeCeremony($locked, $now)) {
                throw new \UnexpectedValueException('Passkey registration ceremony consumption failed.');
            }
            $this->notifications->create(
                $context->accountInternalId,
                AccountSecurityNotificationType::PASSKEY_ADDED,
                $passkey->publicId,
                $now,
            );
            $this->audit->account(
                SecurityEventCode::PASSKEY_ADDED,
                $context->accountId->toString(),
                $context->accountId->toString(),
                $context->sessionId->toString(),
                $now,
                ['authenticator_public_id' => $passkey->publicId],
            );

            return $passkey;
        });
    }

    /**
     * @return array{ceremonyId: string, publicKey: array<string, mixed>}
     */
    public function assertionOptions(
        WebAuthnCeremonyPurpose $purpose,
        ?int $accountInternalId,
        ?int $sessionInternalId,
        ?int $authenticationTransactionInternalId,
    ): array {
        $now = $this->clock->now();
        $challenge = WebAuthnChallenge::generate();
        $ceremony = $this->transactions->transactional(function () use (
            $accountInternalId,
            $sessionInternalId,
            $authenticationTransactionInternalId,
            $purpose,
            $challenge,
            $now,
        ) {
            $this->ceremonies->revokePendingCeremonies(
                $sessionInternalId,
                $authenticationTransactionInternalId,
                $purpose,
                $now,
            );

            return $this->ceremonies->createCeremony(
                $accountInternalId,
                $sessionInternalId,
                $authenticationTransactionInternalId,
                $purpose,
                $challenge,
                $this->configuration->passkeyMaximumAttempts,
                $now,
                $now->modify('+' . $this->configuration->webauthnChallengeTtlSeconds . ' seconds'),
            );
        });
        $options = $this->requestOptions($challenge, $accountInternalId);

        return ['ceremonyId' => $ceremony->publicId, 'publicKey' => $this->normalize($options)];
    }

    public function verifyAssertion(
        string $ceremonyPublicId,
        WebAuthnCeremonyPurpose $purpose,
        string $credentialJson,
        ?int $expectedAccountInternalId = null,
        ?int $expectedSessionInternalId = null,
        ?int $expectedAuthenticationTransactionInternalId = null,
    ): PasskeyCredential {
        if (strlen($credentialJson) > $this->configuration->webauthnMaximumResponseBytes) {
            throw new \DomainException('Passkey assertion response is invalid.');
        }
        $ceremony = $this->ceremonies->findCeremony($ceremonyPublicId);
        if (
            $ceremony === null || $ceremony->purpose !== $purpose
            || $ceremony->accountInternalId !== $expectedAccountInternalId
            || $ceremony->sessionInternalId !== $expectedSessionInternalId
            || $ceremony->authenticationTransactionInternalId !== $expectedAuthenticationTransactionInternalId
        ) {
            throw new \DomainException('Passkey assertion ceremony is invalid.');
        }
        $credential = $this->deserializeCredential($credentialJson);
        if (!$credential->response instanceof AuthenticatorAssertionResponse) {
            throw new \DomainException('Passkey assertion response is invalid.');
        }
        $challenge = new WebAuthnChallenge($credential->response->clientDataJSON->challenge);
        if (!$ceremony->accepts($challenge, $this->clock->now())) {
            $this->recordFailure($ceremonyPublicId);
            throw new \DomainException('Passkey assertion ceremony is invalid.');
        }
        $passkey = $this->passkeys->findPasskeyByCredentialId($credential->rawId);
        if (
            $passkey === null || $passkey->status !== PasskeyCredentialStatus::ACTIVE
            || $expectedAccountInternalId !== null && $passkey->accountInternalId !== $expectedAccountInternalId
        ) {
            throw new \DomainException('Passkey assertion is invalid.');
        }
        $userHandle = $this->userHandles->findOrCreateUserHandle(
            $passkey->accountInternalId,
            $this->clock->now(),
        );
        if (
            $credential->response->userHandle !== null
            && !hash_equals($userHandle, $credential->response->userHandle)
        ) {
            throw new \DomainException('Passkey user binding is invalid.');
        }
        $storedCounter = $passkey->signatureCounter;
        try {
            $validated = AuthenticatorAssertionResponseValidator::create($this->manager()->requestCeremony())->check(
                $this->credentialRecord($passkey, $userHandle),
                $credential->response,
                $this->requestOptions($challenge, $expectedAccountInternalId),
                $this->configuration->relyingPartyId,
                $userHandle,
            );
        } catch (\Webauthn\Exception\CounterException $exception) {
            $this->suspendForCounterSignal($passkey);
            throw new \DomainException('Passkey assertion is invalid.', 0, $exception);
        }
        $now = $this->clock->now();

        return $this->transactions->transactional(function () use (
            $ceremonyPublicId,
            $purpose,
            $passkey,
            $validated,
            $storedCounter,
            $now,
        ): PasskeyCredential {
            $lockedCeremony = $this->ceremonies->findCeremony($ceremonyPublicId, true);
            $lockedPasskey = $this->passkeys->findPasskeyByCredentialId(
                $passkey->credentialIdForVerification(),
                true,
            );
            if (
                $lockedCeremony === null || $lockedCeremony->purpose !== $purpose
                || $lockedPasskey === null || $lockedPasskey->status !== PasskeyCredentialStatus::ACTIVE
                || $lockedPasskey->signatureCounter !== $storedCounter
                || !$this->passkeys->recordPasskeyUse(
                    $lockedPasskey,
                    max($storedCounter, $validated->counter),
                    $validated->backupStatus ?? false,
                    $now,
                )
                || !$this->ceremonies->consumeCeremony($lockedCeremony, $now)
            ) {
                throw new \DomainException('Passkey assertion changed concurrently.');
            }

            return $this->passkeys->findPasskeyByCredentialId($passkey->credentialIdForVerification())
                ?? throw new \UnexpectedValueException('Validated passkey could not be reloaded.');
        });
    }

    private function creationOptions(
        AuthenticatedAccountContext $context,
        string $handle,
        WebAuthnChallenge $challenge,
    ): PublicKeyCredentialCreationOptions {
        $exclude = array_map(
            static fn (PasskeyCredential $credential): PublicKeyCredentialDescriptor =>
                PublicKeyCredentialDescriptor::create(
                    PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                    $credential->credentialIdForVerification(),
                    $credential->transports,
                ),
            $this->passkeys->listPasskeys($context->accountInternalId, true),
        );

        return PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create(
                $this->configuration->relyingPartyName,
                $this->configuration->relyingPartyId,
            ),
            PublicKeyCredentialUserEntity::create(
                $context->accountId->toString(),
                $handle,
                'QMDB account',
            ),
            $challenge->revealForVerification(),
            [PublicKeyCredentialParameters::createPk(-7), PublicKeyCredentialParameters::createPk(-257)],
            AuthenticatorSelectionCriteria::create(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
            ),
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            $exclude,
            max(1, $this->configuration->webauthnChallengeTtlSeconds * 1000),
        );
    }

    private function requestOptions(
        WebAuthnChallenge $challenge,
        ?int $accountInternalId,
    ): PublicKeyCredentialRequestOptions {
        $allow = $accountInternalId === null ? [] : array_map(
            static fn (PasskeyCredential $credential): PublicKeyCredentialDescriptor =>
                PublicKeyCredentialDescriptor::create(
                    PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                    $credential->credentialIdForVerification(),
                    $credential->transports,
                ),
            $this->passkeys->listPasskeys($accountInternalId),
        );

        return PublicKeyCredentialRequestOptions::create(
            $challenge->revealForVerification(),
            $this->configuration->relyingPartyId,
            $allow,
            PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            max(1, $this->configuration->webauthnChallengeTtlSeconds * 1000),
        );
    }

    private function credentialRecord(PasskeyCredential $passkey, string $userHandle): CredentialRecord
    {
        $aaguid = $passkey->aaguid === null
            ? Uuid::fromString('00000000-0000-0000-0000-000000000000')
            : Uuid::fromBinary($passkey->aaguid);

        return CredentialRecord::create(
            $passkey->credentialIdForVerification(),
            PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            $passkey->transports,
            $passkey->attestationFormat,
            EmptyTrustPath::create(),
            $aaguid,
            $passkey->publicKeyForVerification(),
            $userHandle,
            $passkey->signatureCounter,
            backupEligible: $passkey->backupEligible,
            backupStatus: $passkey->backupState,
            uvInitialized: true,
        );
    }

    private function manager(): CeremonyStepManagerFactory
    {
        $factory = new CeremonyStepManagerFactory();
        $factory->setAllowedOrigins($this->configuration->allowedOrigins, false);
        $factory->setCounterChecker(new PasskeyCounterChecker());

        return $factory;
    }

    private function deserializeCredential(string $json): PublicKeyCredential
    {
        return $this->serializer->deserialize($json, PublicKeyCredential::class, 'json');
    }

    /** @return array<string, mixed> */
    private function normalize(object $options): array
    {
        $normalized = $this->serializer->normalize($options, 'json');
        if (!is_array($normalized)) {
            throw new \UnexpectedValueException('WebAuthn options normalization failed.');
        }
        $result = [];
        foreach ($normalized as $key => $value) {
            if (!is_string($key)) {
                throw new \UnexpectedValueException('WebAuthn options normalization failed.');
            }
            $result[$key] = $value;
        }

        return $result;
    }

    private function recordFailure(string $ceremonyPublicId): void
    {
        $now = $this->clock->now();
        $this->transactions->transactional(function () use ($ceremonyPublicId, $now): void {
            $ceremony = $this->ceremonies->findCeremony($ceremonyPublicId, true);
            if ($ceremony !== null) {
                $this->ceremonies->recordCeremonyFailure($ceremony, $now);
            }
        });
    }

    private function suspendForCounterSignal(PasskeyCredential $passkey): void
    {
        $now = $this->clock->now();
        $this->transactions->transactional(function () use ($passkey, $now): void {
            $locked = $this->passkeys->findPasskeyByCredentialId($passkey->credentialIdForVerification(), true);
            if (
                $locked !== null && $locked->status === PasskeyCredentialStatus::ACTIVE
                && $this->passkeys->suspendPasskey($locked, $now)
            ) {
                $this->notifications->create(
                    $locked->accountInternalId,
                    AccountSecurityNotificationType::PASSKEY_SUSPENDED,
                    $locked->publicId,
                    $now,
                );
                $target = $this->targets->notificationTarget($locked->accountInternalId);
                if ($target === null) {
                    throw new \UnexpectedValueException('Passkey audit account identity is unavailable.');
                }
                $this->audit->account(
                    SecurityEventCode::PASSKEY_SUSPENDED,
                    $target['account_public_id'],
                    null,
                    null,
                    $now,
                    ['authenticator_public_id' => $locked->publicId],
                );
            }
        });
    }
}
