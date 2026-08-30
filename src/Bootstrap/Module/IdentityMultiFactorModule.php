<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Nyholm\Psr7\Factory\Psr17Factory;
use Qmdb\Modules\IdentityAccess\Application\Authentication\PasswordAuthenticationRepository;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordVerifier;
use Qmdb\Modules\IdentityMultiFactor\Application\AccountMfaDisablementService;
use Qmdb\Modules\IdentityMultiFactor\Application\AccountMfaEnablementService;
use Qmdb\Modules\IdentityMultiFactor\Application\AuthenticationTransactionCookieFactory;
use Qmdb\Modules\IdentityMultiFactor\Application\MfaLoginService;
use Qmdb\Modules\IdentityMultiFactor\Application\MultiFactorNotificationService;
use Qmdb\Modules\IdentityMultiFactor\Application\PasskeyRevocationService;
use Qmdb\Modules\IdentityMultiFactor\Application\Readiness\IdentityMultiFactorReadinessCheck;
use Qmdb\Modules\IdentityMultiFactor\Application\RecoveryCodeRegenerationService;
use Qmdb\Modules\IdentityMultiFactor\Application\SecureRecoveryCodeGenerator;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpAuthenticationService;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Application\StrongSessionIssuanceService;
use Qmdb\Modules\IdentityMultiFactor\Application\TotpAuthenticatorRevocationService;
use Qmdb\Modules\IdentityMultiFactor\Application\TotpEnrollmentService;
use Qmdb\Modules\IdentityMultiFactor\Application\TotpVerifier;
use Qmdb\Modules\IdentityMultiFactor\Application\WebAuthnService;
use Qmdb\Modules\IdentityMultiFactor\Configuration\IdentityMultiFactorConfiguration;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\AccountMfaPolicyRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\AuthenticationTransactionRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\MultiFactorNotificationTargetRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\PasskeyCredentialRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\RecoveryCodeSetRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\StepUpGrantRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\TotpAuthenticatorRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\WebAuthnCeremonyRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\WebAuthnUserHandleRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\TotpSecretEncryptor;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransactionCookieParser;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Security\SodiumTotpSecretEncryptor;
use Qmdb\Modules\IdentityMultiFactor\Interface\Http\IdentityMultiFactorController;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\IdentityMultiFactor\Interface\Http\MultiFactorRequestInput;
use Qmdb\Modules\IdentitySecurityNotifications\Configuration\SecurityNotificationConfiguration;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\Repository\AccountSecurityNotificationRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\SecurityNotificationDeduplicationKeyFactory;
use Qmdb\Modules\IdentitySessions\Application\DeviceCookieFactory;
use Qmdb\Modules\IdentitySessions\Application\SessionCookieFactory;
use Qmdb\Modules\IdentitySessions\Application\AuthenticationCookieResponseDecorator;
use Qmdb\Modules\IdentitySessions\Configuration\IdentitySessionConfiguration;
use Qmdb\Modules\IdentitySessions\Domain\DeviceCookieParser;
use Qmdb\Modules\IdentitySessions\Domain\Repository\UserDeviceRepository;
use Qmdb\Modules\IdentitySessions\Domain\Repository\UserSessionRepository;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Qmdb\Shared\Http\Message\JsonResponseFactory;
use Qmdb\Shared\Schema\Health\SchemaHealthCheck;
use Qmdb\Shared\Security\Secrets\SecretName;
use Qmdb\Shared\Security\Secrets\SecretsProvider;
use Qmdb\Shared\Time\Clock;

final readonly class IdentityMultiFactorModule implements Module
{
    private const ID = 'identity.multifactor';

    public function id(): ModuleId
    {
        return new ModuleId(self::ID);
    }

    public function dependencies(): array
    {
        return [
            new ModuleId('foundation.core'),
            new ModuleId('foundation.application'),
            new ModuleId('foundation.database'),
            new ModuleId('foundation.schema'),
            new ModuleId('foundation.http'),
            new ModuleId('foundation.presentation'),
            new ModuleId('security.web'),
            new ModuleId('identity.access'),
            new ModuleId('identity.sessions'),
            new ModuleId('identity.security_notifications'),
            new ModuleId('security.audit'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            SodiumTotpSecretEncryptor::class,
            self::ID,
            [SecretsProvider::class, IdentityMultiFactorConfiguration::class],
            new ClosureServiceFactory(static function (DependencyResolver $resolver): SodiumTotpSecretEncryptor {
                $encoded = self::secret($resolver, 'AUTH_MFA_ENCRYPTION_KEY');
                $key = base64_decode($encoded, true);
                if (!is_string($key)) {
                    throw new \RuntimeException('MFA encryption key encoding is invalid.');
                }

                return new SodiumTotpSecretEncryptor(
                    $key,
                    ServiceReference::get($resolver, IdentityMultiFactorConfiguration::class)->encryptionKeyVersion,
                );
            }),
        ));
        $context->alias(TotpSecretEncryptor::class, SodiumTotpSecretEncryptor::class);
        $this->simple(
            $context,
            TotpVerifier::class,
            [IdentityMultiFactorConfiguration::class],
            static fn (DependencyResolver $r): TotpVerifier => new TotpVerifier(
                ServiceReference::get($r, IdentityMultiFactorConfiguration::class),
            )
        );
        $this->simple(
            $context,
            SecureRecoveryCodeGenerator::class,
            [IdentityMultiFactorConfiguration::class],
            static fn (DependencyResolver $r): SecureRecoveryCodeGenerator => new SecureRecoveryCodeGenerator(
                ServiceReference::get($r, IdentityMultiFactorConfiguration::class),
            )
        );
        $this->simple(
            $context,
            StepUpGuard::class,
            [StepUpGrantRepository::class, Clock::class],
            static fn (DependencyResolver $r): StepUpGuard => new StepUpGuard(
                ServiceReference::get($r, StepUpGrantRepository::class),
                ServiceReference::get($r, Clock::class),
            )
        );
        $this->registerNotifications($context);
        $this->registerAuthentication($context);
        $this->registerManagement($context);
        $this->registerReadiness($context);
        $this->registerHttp($context);
    }

    private function registerNotifications(ModuleRegistrationContext $context): void
    {
        $this->simple($context, MultiFactorNotificationService::class, [
            MultiFactorNotificationTargetRepository::class,
            AccountSecurityNotificationRepository::class,
            SecurityNotificationDeduplicationKeyFactory::class,
            SecurityNotificationConfiguration::class,
        ], static fn (DependencyResolver $r): MultiFactorNotificationService => new MultiFactorNotificationService(
            ServiceReference::get($r, MultiFactorNotificationTargetRepository::class),
            ServiceReference::get($r, AccountSecurityNotificationRepository::class),
            ServiceReference::get($r, SecurityNotificationDeduplicationKeyFactory::class),
            ServiceReference::get($r, SecurityNotificationConfiguration::class),
        ));
    }

    private function registerAuthentication(ModuleRegistrationContext $context): void
    {
        $this->simple($context, StrongSessionIssuanceService::class, [
            UserDeviceRepository::class, UserSessionRepository::class, DeviceCookieParser::class,
            SessionCookieFactory::class, DeviceCookieFactory::class, IdentitySessionConfiguration::class, Clock::class,
        ], static fn (DependencyResolver $r): StrongSessionIssuanceService => new StrongSessionIssuanceService(
            ServiceReference::get($r, UserDeviceRepository::class),
            ServiceReference::get($r, UserSessionRepository::class),
            ServiceReference::get($r, DeviceCookieParser::class),
            ServiceReference::get($r, SessionCookieFactory::class),
            ServiceReference::get($r, DeviceCookieFactory::class),
            ServiceReference::get($r, IdentitySessionConfiguration::class),
            ServiceReference::get($r, Clock::class),
        ));
        $this->simple($context, MfaLoginService::class, [
            AuthenticationTransactionRepository::class, TotpAuthenticatorRepository::class,
            RecoveryCodeSetRepository::class, MultiFactorNotificationTargetRepository::class,
            TotpSecretEncryptor::class, TotpVerifier::class, StrongSessionIssuanceService::class,
            MultiFactorNotificationService::class, TransactionManager::class,
            AuthenticationTransactionCookieFactory::class, SecretsProvider::class, SecurityAuditEventAppender::class, Clock::class,
        ], static fn (DependencyResolver $r): MfaLoginService => new MfaLoginService(
            ServiceReference::get($r, AuthenticationTransactionRepository::class),
            ServiceReference::get($r, TotpAuthenticatorRepository::class),
            ServiceReference::get($r, RecoveryCodeSetRepository::class),
            ServiceReference::get($r, MultiFactorNotificationTargetRepository::class),
            ServiceReference::get($r, TotpSecretEncryptor::class),
            ServiceReference::get($r, TotpVerifier::class),
            ServiceReference::get($r, StrongSessionIssuanceService::class),
            ServiceReference::get($r, MultiFactorNotificationService::class),
            ServiceReference::get($r, TransactionManager::class),
            ServiceReference::get($r, AuthenticationTransactionCookieFactory::class),
            self::secret($r, 'AUTH_IDENTITY_HMAC_KEY'),
            ServiceReference::get($r, SecurityAuditEventAppender::class),
            ServiceReference::get($r, Clock::class),
        ));
        $this->simple($context, StepUpAuthenticationService::class, [
            AuthenticationTransactionRepository::class, StepUpGrantRepository::class,
            AccountMfaPolicyRepository::class, TotpAuthenticatorRepository::class,
            RecoveryCodeSetRepository::class, PasswordAuthenticationRepository::class, PasswordVerifier::class,
            TotpSecretEncryptor::class, TotpVerifier::class, TransactionManager::class,
            AuthenticationTransactionCookieFactory::class, IdentityMultiFactorConfiguration::class,
            SecretsProvider::class, Clock::class,
        ], static fn (DependencyResolver $r): StepUpAuthenticationService => new StepUpAuthenticationService(
            ServiceReference::get($r, AuthenticationTransactionRepository::class),
            ServiceReference::get($r, StepUpGrantRepository::class),
            ServiceReference::get($r, AccountMfaPolicyRepository::class),
            ServiceReference::get($r, TotpAuthenticatorRepository::class),
            ServiceReference::get($r, RecoveryCodeSetRepository::class),
            ServiceReference::get($r, PasswordAuthenticationRepository::class),
            ServiceReference::get($r, PasswordVerifier::class),
            ServiceReference::get($r, TotpSecretEncryptor::class),
            ServiceReference::get($r, TotpVerifier::class),
            ServiceReference::get($r, TransactionManager::class),
            ServiceReference::get($r, AuthenticationTransactionCookieFactory::class),
            ServiceReference::get($r, IdentityMultiFactorConfiguration::class),
            self::secret($r, 'AUTH_IDENTITY_HMAC_KEY'),
            ServiceReference::get($r, Clock::class),
        ));
    }

    private function registerManagement(ModuleRegistrationContext $context): void
    {
        $this->simple($context, TotpEnrollmentService::class, [
            TotpAuthenticatorRepository::class, TotpSecretEncryptor::class, TotpVerifier::class,
            StepUpGuard::class, MultiFactorNotificationService::class, TransactionManager::class,
            IdentityMultiFactorConfiguration::class, SecurityAuditEventAppender::class, Clock::class,
        ], static fn (DependencyResolver $r): TotpEnrollmentService => new TotpEnrollmentService(
            ServiceReference::get($r, TotpAuthenticatorRepository::class),
            ServiceReference::get($r, TotpSecretEncryptor::class),
            ServiceReference::get($r, TotpVerifier::class),
            ServiceReference::get($r, StepUpGuard::class),
            ServiceReference::get($r, MultiFactorNotificationService::class),
            ServiceReference::get($r, TransactionManager::class),
            ServiceReference::get($r, IdentityMultiFactorConfiguration::class),
            ServiceReference::get($r, SecurityAuditEventAppender::class),
            ServiceReference::get($r, Clock::class),
        ));
        $this->simple($context, AccountMfaEnablementService::class, [
            AccountMfaPolicyRepository::class, RecoveryCodeSetRepository::class,
            SecureRecoveryCodeGenerator::class, StepUpGuard::class, UserSessionRepository::class,
            MultiFactorNotificationService::class, TransactionManager::class, SecretsProvider::class, SecurityAuditEventAppender::class, Clock::class,
        ], static fn (DependencyResolver $r): AccountMfaEnablementService => new AccountMfaEnablementService(
            ServiceReference::get($r, AccountMfaPolicyRepository::class),
            ServiceReference::get($r, RecoveryCodeSetRepository::class),
            ServiceReference::get($r, SecureRecoveryCodeGenerator::class),
            ServiceReference::get($r, StepUpGuard::class),
            ServiceReference::get($r, UserSessionRepository::class),
            ServiceReference::get($r, MultiFactorNotificationService::class),
            ServiceReference::get($r, TransactionManager::class),
            self::secret($r, 'AUTH_IDENTITY_HMAC_KEY'),
            ServiceReference::get($r, SecurityAuditEventAppender::class),
            ServiceReference::get($r, Clock::class),
        ));
        $this->simple($context, AccountMfaDisablementService::class, [
            AccountMfaPolicyRepository::class, RecoveryCodeSetRepository::class, StepUpGuard::class,
            UserSessionRepository::class, MultiFactorNotificationService::class, TransactionManager::class,
            SecurityAuditEventAppender::class, Clock::class,
        ], static fn (DependencyResolver $r): AccountMfaDisablementService => new AccountMfaDisablementService(
            ServiceReference::get($r, AccountMfaPolicyRepository::class),
            ServiceReference::get($r, RecoveryCodeSetRepository::class),
            ServiceReference::get($r, StepUpGuard::class),
            ServiceReference::get($r, UserSessionRepository::class),
            ServiceReference::get($r, MultiFactorNotificationService::class),
            ServiceReference::get($r, TransactionManager::class),
            ServiceReference::get($r, SecurityAuditEventAppender::class),
            ServiceReference::get($r, Clock::class),
        ));
        $this->simple($context, RecoveryCodeRegenerationService::class, [
            AccountMfaPolicyRepository::class, RecoveryCodeSetRepository::class,
            SecureRecoveryCodeGenerator::class, StepUpGuard::class, MultiFactorNotificationService::class,
            TransactionManager::class, SecretsProvider::class, SecurityAuditEventAppender::class, Clock::class,
        ], static fn (DependencyResolver $r): RecoveryCodeRegenerationService => new RecoveryCodeRegenerationService(
            ServiceReference::get($r, AccountMfaPolicyRepository::class),
            ServiceReference::get($r, RecoveryCodeSetRepository::class),
            ServiceReference::get($r, SecureRecoveryCodeGenerator::class),
            ServiceReference::get($r, StepUpGuard::class),
            ServiceReference::get($r, MultiFactorNotificationService::class),
            ServiceReference::get($r, TransactionManager::class),
            self::secret($r, 'AUTH_IDENTITY_HMAC_KEY'),
            ServiceReference::get($r, SecurityAuditEventAppender::class),
            ServiceReference::get($r, Clock::class),
        ));
        $this->simple($context, TotpAuthenticatorRevocationService::class, [
            TotpAuthenticatorRepository::class, AccountMfaPolicyRepository::class, StepUpGuard::class,
            MultiFactorNotificationService::class, TransactionManager::class, SecurityAuditEventAppender::class, Clock::class,
        ], static fn (DependencyResolver $r): TotpAuthenticatorRevocationService =>
            new TotpAuthenticatorRevocationService(
                ServiceReference::get($r, TotpAuthenticatorRepository::class),
                ServiceReference::get($r, AccountMfaPolicyRepository::class),
                ServiceReference::get($r, StepUpGuard::class),
                ServiceReference::get($r, MultiFactorNotificationService::class),
                ServiceReference::get($r, TransactionManager::class),
                ServiceReference::get($r, SecurityAuditEventAppender::class),
                ServiceReference::get($r, Clock::class),
            ));
        $this->simple($context, PasskeyRevocationService::class, [
            PasskeyCredentialRepository::class, AccountMfaPolicyRepository::class, StepUpGuard::class,
            MultiFactorNotificationService::class, TransactionManager::class, SecurityAuditEventAppender::class, Clock::class,
        ], static fn (DependencyResolver $r): PasskeyRevocationService => new PasskeyRevocationService(
            ServiceReference::get($r, PasskeyCredentialRepository::class),
            ServiceReference::get($r, AccountMfaPolicyRepository::class),
            ServiceReference::get($r, StepUpGuard::class),
            ServiceReference::get($r, MultiFactorNotificationService::class),
            ServiceReference::get($r, TransactionManager::class),
            ServiceReference::get($r, SecurityAuditEventAppender::class),
            ServiceReference::get($r, Clock::class),
        ));
        $this->simple($context, WebAuthnService::class, [
            WebAuthnCeremonyRepository::class, WebAuthnUserHandleRepository::class,
            PasskeyCredentialRepository::class, StepUpGuard::class, MultiFactorNotificationService::class,
            MultiFactorNotificationTargetRepository::class, TransactionManager::class, IdentityMultiFactorConfiguration::class,
            SecurityAuditEventAppender::class, Clock::class,
        ], static fn (DependencyResolver $r): WebAuthnService => new WebAuthnService(
            ServiceReference::get($r, WebAuthnCeremonyRepository::class),
            ServiceReference::get($r, WebAuthnUserHandleRepository::class),
            ServiceReference::get($r, PasskeyCredentialRepository::class),
            ServiceReference::get($r, StepUpGuard::class),
            ServiceReference::get($r, MultiFactorNotificationService::class),
            ServiceReference::get($r, MultiFactorNotificationTargetRepository::class),
            ServiceReference::get($r, TransactionManager::class),
            ServiceReference::get($r, IdentityMultiFactorConfiguration::class),
            ServiceReference::get($r, SecurityAuditEventAppender::class),
            ServiceReference::get($r, Clock::class),
        ));
    }

    private function registerReadiness(ModuleRegistrationContext $context): void
    {
        $this->simple($context, IdentityMultiFactorReadinessCheck::class, [
            IdentityMultiFactorConfiguration::class, TotpVerifier::class, SecureRecoveryCodeGenerator::class,
            SecretsProvider::class, SchemaHealthCheck::class,
        ], static fn (DependencyResolver $r): IdentityMultiFactorReadinessCheck =>
            new IdentityMultiFactorReadinessCheck(
                ServiceReference::get($r, IdentityMultiFactorConfiguration::class),
                ServiceReference::get($r, TotpVerifier::class),
                ServiceReference::get($r, SecureRecoveryCodeGenerator::class),
                ServiceReference::get($r, SecretsProvider::class),
                ServiceReference::get($r, SchemaHealthCheck::class),
            ));
    }

    private function registerHttp(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(
            AuthenticationTransactionCookieParser::class,
            self::ID,
            new AuthenticationTransactionCookieParser(),
        ));
        $context->service(ServiceDefinition::instance(
            MultiFactorRequestInput::class,
            self::ID,
            new MultiFactorRequestInput(),
        ));
        $this->simple($context, IdentityMultiFactorController::class, [
            MultiFactorRequestInput::class, IdentityCsrf::class, IdentityAccessView::class,
            AuthenticatedRequestGuard::class, MfaLoginService::class, StepUpAuthenticationService::class,
            StepUpGuard::class,
            TotpEnrollmentService::class, AccountMfaEnablementService::class,
            AccountMfaDisablementService::class, RecoveryCodeRegenerationService::class,
            TotpAuthenticatorRevocationService::class, PasskeyRevocationService::class, WebAuthnService::class,
            AuthenticationTransactionRepository::class, AccountMfaPolicyRepository::class,
            TotpAuthenticatorRepository::class, RecoveryCodeSetRepository::class, PasskeyCredentialRepository::class,
            AuthenticationTransactionCookieFactory::class, AuthenticationTransactionCookieParser::class,
            DeviceCookieFactory::class, AuthenticationCookieResponseDecorator::class,
            JsonResponseFactory::class, Psr17Factory::class, IdentityMultiFactorConfiguration::class,
        ], static fn (DependencyResolver $r): IdentityMultiFactorController => new IdentityMultiFactorController(
            ServiceReference::get($r, MultiFactorRequestInput::class),
            ServiceReference::get($r, IdentityCsrf::class),
            ServiceReference::get($r, IdentityAccessView::class),
            ServiceReference::get($r, AuthenticatedRequestGuard::class),
            ServiceReference::get($r, MfaLoginService::class),
            ServiceReference::get($r, StepUpAuthenticationService::class),
            ServiceReference::get($r, StepUpGuard::class),
            ServiceReference::get($r, TotpEnrollmentService::class),
            ServiceReference::get($r, AccountMfaEnablementService::class),
            ServiceReference::get($r, AccountMfaDisablementService::class),
            ServiceReference::get($r, RecoveryCodeRegenerationService::class),
            ServiceReference::get($r, TotpAuthenticatorRevocationService::class),
            ServiceReference::get($r, PasskeyRevocationService::class),
            ServiceReference::get($r, WebAuthnService::class),
            ServiceReference::get($r, AuthenticationTransactionRepository::class),
            ServiceReference::get($r, AccountMfaPolicyRepository::class),
            ServiceReference::get($r, TotpAuthenticatorRepository::class),
            ServiceReference::get($r, RecoveryCodeSetRepository::class),
            ServiceReference::get($r, PasskeyCredentialRepository::class),
            ServiceReference::get($r, AuthenticationTransactionCookieFactory::class),
            ServiceReference::get($r, AuthenticationTransactionCookieParser::class),
            ServiceReference::get($r, DeviceCookieFactory::class),
            ServiceReference::get($r, AuthenticationCookieResponseDecorator::class),
            ServiceReference::get($r, JsonResponseFactory::class),
            ServiceReference::get($r, Psr17Factory::class),
            ServiceReference::get($r, IdentityMultiFactorConfiguration::class),
        ));
    }

    /** @param list<class-string> $dependencies */
    private function simple(
        ModuleRegistrationContext $context,
        string $class,
        array $dependencies,
        \Closure $factory,
    ): void {
        $context->service(ServiceDefinition::factory(
            $class,
            self::ID,
            $dependencies,
            new ClosureServiceFactory($factory),
        ));
    }

    private static function secret(DependencyResolver $resolver, string $name): string
    {
        return ServiceReference::get($resolver, SecretsProvider::class)
            ->get(SecretName::fromString($name))
            ->reveal();
    }
}
