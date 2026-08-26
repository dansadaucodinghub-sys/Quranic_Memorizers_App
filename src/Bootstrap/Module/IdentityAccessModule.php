<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Nyholm\Psr7\Factory\Psr17Factory;
use Qmdb\Modules\Identity\Domain\Repository\AccountRepository;
use Qmdb\Modules\Identity\Infrastructure\Security\ContactCipher;
use Qmdb\Modules\Identity\Infrastructure\Security\SodiumContactCipher;
use Qmdb\Modules\IdentityAccess\Application\Authentication\PasswordAuthenticationRepository;
use Qmdb\Modules\IdentityAccess\Application\Authentication\PasswordAuthenticationService;
use Qmdb\Modules\IdentityAccess\Application\Mail\EmailVerificationMessageFactory;
use Qmdb\Modules\IdentityAccess\Application\Readiness\IdentityAccessReadinessCheck;
use Qmdb\Modules\IdentityAccess\Application\Mail\EmailVerificationNotifier;
use Qmdb\Modules\IdentityAccess\Application\Registration\AccountRegistrationService;
use Qmdb\Modules\IdentityAccess\Application\Verification\EmailVerificationResendService;
use Qmdb\Modules\IdentityAccess\Application\Verification\EmailVerificationService;
use Qmdb\Modules\IdentityAccess\Configuration\IdentityAccessConfiguration;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationTokenGenerator;
use Qmdb\Modules\IdentityAccess\Domain\Repository\IdentityAccessRepository;
use Qmdb\Modules\IdentityAccess\Infrastructure\Mail\SymfonyMailerEmailVerificationNotifier;
use Qmdb\Modules\IdentityAccess\Infrastructure\Persistence\MySqlIdentityAccessRepository;
use Qmdb\Modules\IdentityAccess\Infrastructure\Persistence\MySqlIdentityRateLimiter;
use Qmdb\Modules\IdentityAccess\Infrastructure\Persistence\MySqlPasswordAuthenticationRepository;
use Qmdb\Modules\IdentityAccess\Infrastructure\Security\SecureEmailVerificationTokenGenerator;
use Qmdb\Modules\IdentityAccess\Interface\Http\AccountRegistrationAcceptedController;
use Qmdb\Modules\IdentityAccess\Interface\Http\AccountRegistrationFormController;
use Qmdb\Modules\IdentityAccess\Interface\Http\AccountRegistrationSubmitController;
use Qmdb\Modules\IdentityAccess\Interface\Http\ApplicationReadinessController;
use Qmdb\Modules\IdentityAccess\Interface\Http\EmailVerificationCompletedController;
use Qmdb\Modules\IdentityAccess\Interface\Http\EmailVerificationFormController;
use Qmdb\Modules\IdentityAccess\Interface\Http\EmailVerificationResendFormController;
use Qmdb\Modules\IdentityAccess\Interface\Http\EmailVerificationResendSubmitController;
use Qmdb\Modules\IdentityAccess\Interface\Http\EmailVerificationSubmitController;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityFormInputMapper;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityRequestContext;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\EmailLookupHashGenerator;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\HmacIdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\Password\Argon2IdPasswordHasher;
use Qmdb\Modules\IdentityAccess\Security\Password\Argon2IdPasswordVerifier;
use Qmdb\Modules\IdentityAccess\Security\Password\DummyPasswordHashProvider;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordHasher;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordHashingPolicy;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordPolicy;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordVerifier;
use Qmdb\Modules\IdentityAccess\Security\Peer\DirectPeerAddressResolver;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfCookieFactory;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfTokenManager;
use Qmdb\Modules\SecurityWeb\Csrf\SameOriginMutationValidator;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Health\DatabaseHealthCheck;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Localization\TranslationCatalog;
use Qmdb\Shared\Http\Message\JsonResponseFactory;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;
use Qmdb\Shared\Presentation\Response\PageOrFragmentResponseFactory;
use Qmdb\Shared\Presentation\View\PageRenderer;
use Qmdb\Shared\Presentation\View\PhpViewRenderer;
use Qmdb\Shared\Presentation\View\PresentationRequestContext;
use Qmdb\Shared\Security\Secrets\SecretName;
use Qmdb\Shared\Security\Secrets\SecretsProvider;
use Qmdb\Shared\Schema\Health\SchemaHealthCheck;
use Qmdb\Shared\Time\Clock;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;

final readonly class IdentityAccessModule implements Module
{
    private const ID = 'identity.access';

    public function __construct(private IdentityAccessConfiguration $configuration)
    {
    }

    public function id(): ModuleId
    {
        return new ModuleId(self::ID);
    }

    public function dependencies(): array
    {
        return [
            new ModuleId('foundation.core'),
            new ModuleId('foundation.application'),
            new ModuleId('foundation.observability'),
            new ModuleId('foundation.database'),
            new ModuleId('foundation.schema'),
            new ModuleId('foundation.http'),
            new ModuleId('foundation.presentation'),
            new ModuleId('security.web'),
            new ModuleId('identity.accounts'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(
            IdentityAccessConfiguration::class,
            self::ID,
            $this->configuration,
        ));
        $this->registerSecurity($context);
        $this->registerPersistence($context);
        $this->registerMail($context);
        $this->registerApplications($context);
        $context->service(ServiceDefinition::factory(
            IdentityAccessReadinessCheck::class,
            self::ID,
            [PasswordHashingPolicy::class, PasswordPolicy::class, CsrfTokenManager::class,
                IdentityFingerprintGenerator::class, SecretsProvider::class, SchemaHealthCheck::class],
            new ClosureServiceFactory(fn (DependencyResolver $resolver): IdentityAccessReadinessCheck =>
                new IdentityAccessReadinessCheck(
                    ServiceReference::get($resolver, PasswordHashingPolicy::class),
                    ServiceReference::get($resolver, PasswordPolicy::class),
                    ServiceReference::get($resolver, CsrfTokenManager::class),
                    ServiceReference::get($resolver, IdentityFingerprintGenerator::class),
                    ServiceReference::get($resolver, SecretsProvider::class),
                    $this->configuration,
                    ServiceReference::get($resolver, SchemaHealthCheck::class),
                )),
        ));
        $this->registerHttp($context);
    }

    private function registerSecurity(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            PasswordPolicy::class,
            self::ID,
            [],
            new ClosureServiceFactory(fn (DependencyResolver $resolver): PasswordPolicy => new PasswordPolicy(
                $this->configuration->passwordMinimumLength,
                $this->configuration->passwordMaximumBytes,
            )),
        ));
        $context->service(ServiceDefinition::instance(
            PasswordHashingPolicy::class,
            self::ID,
            new PasswordHashingPolicy(),
        ));
        $context->service(ServiceDefinition::factory(
            Argon2IdPasswordHasher::class,
            self::ID,
            [PasswordPolicy::class, PasswordHashingPolicy::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): Argon2IdPasswordHasher =>
                new Argon2IdPasswordHasher(
                    ServiceReference::get($resolver, PasswordPolicy::class),
                    ServiceReference::get($resolver, PasswordHashingPolicy::class),
                )),
        ));
        $context->alias(PasswordHasher::class, Argon2IdPasswordHasher::class);
        $context->service(ServiceDefinition::factory(
            Argon2IdPasswordVerifier::class,
            self::ID,
            [PasswordHashingPolicy::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): Argon2IdPasswordVerifier =>
                new Argon2IdPasswordVerifier(ServiceReference::get($resolver, PasswordHashingPolicy::class))),
        ));
        $context->alias(PasswordVerifier::class, Argon2IdPasswordVerifier::class);
        $context->service(ServiceDefinition::factory(
            DummyPasswordHashProvider::class,
            self::ID,
            [PasswordHashingPolicy::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): DummyPasswordHashProvider =>
                new DummyPasswordHashProvider(ServiceReference::get($resolver, PasswordHashingPolicy::class))),
        ));
        $context->service(ServiceDefinition::factory(
            HmacIdentityFingerprintGenerator::class,
            self::ID,
            [SecretsProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): HmacIdentityFingerprintGenerator =>
                new HmacIdentityFingerprintGenerator(self::secret($resolver, 'AUTH_IDENTITY_HMAC_KEY'))),
        ));
        $context->alias(IdentityFingerprintGenerator::class, HmacIdentityFingerprintGenerator::class);
        $context->service(ServiceDefinition::factory(
            EmailLookupHashGenerator::class,
            self::ID,
            [SecretsProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): EmailLookupHashGenerator =>
                new EmailLookupHashGenerator(self::secret($resolver, 'AUTH_IDENTITY_HMAC_KEY'))),
        ));
        $context->service(ServiceDefinition::factory(
            SodiumContactCipher::class,
            self::ID,
            [SecretsProvider::class],
            new ClosureServiceFactory(function (DependencyResolver $resolver): SodiumContactCipher {
                $key = base64_decode(self::secret($resolver, 'AUTH_CONTACT_ENCRYPTION_KEY'), true);
                if (!is_string($key)) {
                    throw new \RuntimeException('Contact encryption key encoding is invalid.');
                }

                return new SodiumContactCipher($key, $this->configuration->contactEncryptionKeyId);
            }),
        ));
        $context->alias(ContactCipher::class, SodiumContactCipher::class);
        $context->service(ServiceDefinition::instance(
            SecureEmailVerificationTokenGenerator::class,
            self::ID,
            new SecureEmailVerificationTokenGenerator(),
        ));
        $context->alias(EmailVerificationTokenGenerator::class, SecureEmailVerificationTokenGenerator::class);
        $context->service(ServiceDefinition::instance(
            DirectPeerAddressResolver::class,
            self::ID,
            new DirectPeerAddressResolver(),
        ));
    }

    private function registerPersistence(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            MySqlIdentityAccessRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlIdentityAccessRepository =>
                new MySqlIdentityAccessRepository(ServiceReference::get($resolver, DatabaseConnectionProvider::class))),
        ));
        $context->alias(IdentityAccessRepository::class, MySqlIdentityAccessRepository::class);
        $context->service(ServiceDefinition::factory(
            MySqlIdentityRateLimiter::class,
            self::ID,
            [DatabaseConnectionProvider::class, TransactionManager::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlIdentityRateLimiter =>
                new MySqlIdentityRateLimiter(
                    ServiceReference::get($resolver, DatabaseConnectionProvider::class),
                    ServiceReference::get($resolver, TransactionManager::class),
                )),
        ));
        $context->alias(IdentityRateLimiter::class, MySqlIdentityRateLimiter::class);
        $context->service(ServiceDefinition::factory(
            MySqlPasswordAuthenticationRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlPasswordAuthenticationRepository =>
                new MySqlPasswordAuthenticationRepository(
                    ServiceReference::get($resolver, DatabaseConnectionProvider::class),
                )),
        ));
        $context->alias(PasswordAuthenticationRepository::class, MySqlPasswordAuthenticationRepository::class);
    }

    private function registerMail(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            Mailer::class,
            self::ID,
            [SecretsProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): Mailer => new Mailer(
                Transport::fromDsn(self::secret($resolver, 'MAILER_DSN')),
            )),
        ));
        $context->alias(MailerInterface::class, Mailer::class);
        $context->service(ServiceDefinition::factory(
            SymfonyMailerEmailVerificationNotifier::class,
            self::ID,
            [MailerInterface::class],
            new ClosureServiceFactory(fn (DependencyResolver $resolver): SymfonyMailerEmailVerificationNotifier =>
                new SymfonyMailerEmailVerificationNotifier(
                    ServiceReference::get($resolver, MailerInterface::class),
                    $this->configuration->mailFromAddress,
                    $this->configuration->mailFromName,
                )),
        ));
        $context->alias(EmailVerificationNotifier::class, SymfonyMailerEmailVerificationNotifier::class);
        $context->service(ServiceDefinition::factory(
            EmailVerificationMessageFactory::class,
            self::ID,
            [TranslationCatalog::class, PhpViewRenderer::class],
            new ClosureServiceFactory(fn (DependencyResolver $resolver): EmailVerificationMessageFactory =>
                new EmailVerificationMessageFactory(
                    $this->configuration->publicBaseUrl,
                    ServiceReference::get($resolver, TranslationCatalog::class),
                    ServiceReference::get($resolver, PhpViewRenderer::class),
                )),
        ));
    }

    private function registerApplications(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            AccountRegistrationService::class,
            self::ID,
            [AccountRepository::class, IdentityAccessRepository::class, TransactionManager::class,
                PasswordHasher::class, EmailVerificationTokenGenerator::class, IdentityRateLimiter::class,
                IdentityFingerprintGenerator::class, EmailLookupHashGenerator::class, ContactCipher::class,
                EmailVerificationMessageFactory::class, EmailVerificationNotifier::class, EventLogger::class,
                Clock::class],
            new ClosureServiceFactory(fn (DependencyResolver $resolver): AccountRegistrationService =>
                new AccountRegistrationService(
                    ServiceReference::get($resolver, AccountRepository::class),
                    ServiceReference::get($resolver, IdentityAccessRepository::class),
                    ServiceReference::get($resolver, TransactionManager::class),
                    ServiceReference::get($resolver, PasswordHasher::class),
                    ServiceReference::get($resolver, EmailVerificationTokenGenerator::class),
                    ServiceReference::get($resolver, IdentityRateLimiter::class),
                    ServiceReference::get($resolver, IdentityFingerprintGenerator::class),
                    ServiceReference::get($resolver, EmailLookupHashGenerator::class),
                    ServiceReference::get($resolver, ContactCipher::class),
                    ServiceReference::get($resolver, EmailVerificationMessageFactory::class),
                    ServiceReference::get($resolver, EmailVerificationNotifier::class),
                    ServiceReference::get($resolver, EventLogger::class),
                    $this->configuration,
                    ServiceReference::get($resolver, Clock::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            EmailVerificationService::class,
            self::ID,
            [IdentityAccessRepository::class, TransactionManager::class, IdentityRateLimiter::class,
                IdentityFingerprintGenerator::class, Clock::class],
            new ClosureServiceFactory(fn (DependencyResolver $resolver): EmailVerificationService =>
                new EmailVerificationService(
                    ServiceReference::get($resolver, IdentityAccessRepository::class),
                    ServiceReference::get($resolver, TransactionManager::class),
                    ServiceReference::get($resolver, IdentityRateLimiter::class),
                    ServiceReference::get($resolver, IdentityFingerprintGenerator::class),
                    $this->configuration,
                    ServiceReference::get($resolver, Clock::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            EmailVerificationResendService::class,
            self::ID,
            [IdentityAccessRepository::class, TransactionManager::class, EmailVerificationTokenGenerator::class,
                IdentityRateLimiter::class, IdentityFingerprintGenerator::class, EmailLookupHashGenerator::class,
                EmailVerificationMessageFactory::class, EmailVerificationNotifier::class, EventLogger::class,
                Clock::class],
            new ClosureServiceFactory(fn (DependencyResolver $resolver): EmailVerificationResendService =>
                new EmailVerificationResendService(
                    ServiceReference::get($resolver, IdentityAccessRepository::class),
                    ServiceReference::get($resolver, TransactionManager::class),
                    ServiceReference::get($resolver, EmailVerificationTokenGenerator::class),
                    ServiceReference::get($resolver, IdentityRateLimiter::class),
                    ServiceReference::get($resolver, IdentityFingerprintGenerator::class),
                    ServiceReference::get($resolver, EmailLookupHashGenerator::class),
                    ServiceReference::get($resolver, EmailVerificationMessageFactory::class),
                    ServiceReference::get($resolver, EmailVerificationNotifier::class),
                    ServiceReference::get($resolver, EventLogger::class),
                    $this->configuration,
                    ServiceReference::get($resolver, Clock::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            PasswordAuthenticationService::class,
            self::ID,
            [PasswordAuthenticationRepository::class, PasswordVerifier::class, DummyPasswordHashProvider::class,
                IdentityRateLimiter::class, IdentityFingerprintGenerator::class, EmailLookupHashGenerator::class,
                Clock::class],
            new ClosureServiceFactory(fn (DependencyResolver $resolver): PasswordAuthenticationService =>
                new PasswordAuthenticationService(
                    ServiceReference::get($resolver, PasswordAuthenticationRepository::class),
                    ServiceReference::get($resolver, PasswordVerifier::class),
                    ServiceReference::get($resolver, DummyPasswordHashProvider::class),
                    ServiceReference::get($resolver, IdentityRateLimiter::class),
                    ServiceReference::get($resolver, IdentityFingerprintGenerator::class),
                    ServiceReference::get($resolver, EmailLookupHashGenerator::class),
                    $this->configuration,
                    ServiceReference::get($resolver, Clock::class),
                )),
        ));
    }

    private function registerHttp(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            IdentityFormInputMapper::class,
            self::ID,
            [PasswordPolicy::class],
            new ClosureServiceFactory(fn (DependencyResolver $resolver): IdentityFormInputMapper =>
                new IdentityFormInputMapper(
                    $this->configuration->formMaxBytes,
                    ServiceReference::get($resolver, PasswordPolicy::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            IdentityCsrf::class,
            self::ID,
            [CsrfCookieFactory::class, CsrfTokenManager::class, SameOriginMutationValidator::class, Clock::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): IdentityCsrf => new IdentityCsrf(
                ServiceReference::get($resolver, CsrfCookieFactory::class),
                ServiceReference::get($resolver, CsrfTokenManager::class),
                ServiceReference::get($resolver, SameOriginMutationValidator::class),
                ServiceReference::get($resolver, Clock::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            IdentityAccessView::class,
            self::ID,
            [PresentationRequestContext::class, PhpViewRenderer::class, PageRenderer::class,
                PageOrFragmentResponseFactory::class, Psr17Factory::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): IdentityAccessView =>
                new IdentityAccessView(
                    ServiceReference::get($resolver, PresentationRequestContext::class),
                    ServiceReference::get($resolver, PhpViewRenderer::class),
                    ServiceReference::get($resolver, PageRenderer::class),
                    ServiceReference::get($resolver, PageOrFragmentResponseFactory::class),
                    ServiceReference::get($resolver, Psr17Factory::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            IdentityRequestContext::class,
            self::ID,
            [DirectPeerAddressResolver::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): IdentityRequestContext =>
                new IdentityRequestContext(ServiceReference::get($resolver, DirectPeerAddressResolver::class))),
        ));
        $this->registerControllers($context);
    }

    private function registerControllers(ModuleRegistrationContext $context): void
    {
        $this->controller($context, AccountRegistrationFormController::class, [IdentityCsrf::class,
            IdentityAccessView::class], static fn (DependencyResolver $r): AccountRegistrationFormController =>
                new AccountRegistrationFormController(
                    ServiceReference::get($r, IdentityCsrf::class),
                    ServiceReference::get($r, IdentityAccessView::class),
                ));
        $this->controller(
            $context,
            AccountRegistrationAcceptedController::class,
            [IdentityAccessView::class],
            static fn (DependencyResolver $r): AccountRegistrationAcceptedController =>
            new AccountRegistrationAcceptedController(ServiceReference::get($r, IdentityAccessView::class))
        );
        $this->controller(
            $context,
            AccountRegistrationSubmitController::class,
            [IdentityFormInputMapper::class,
            IdentityCsrf::class, IdentityAccessView::class, IdentityRequestContext::class,
            AccountRegistrationService::class, FragmentRequestDetector::class],
            static fn (DependencyResolver $r): AccountRegistrationSubmitController =>
                new AccountRegistrationSubmitController(
                    ServiceReference::get($r, IdentityFormInputMapper::class),
                    ServiceReference::get($r, IdentityCsrf::class),
                    ServiceReference::get($r, IdentityAccessView::class),
                    ServiceReference::get($r, IdentityRequestContext::class),
                    ServiceReference::get($r, AccountRegistrationService::class),
                    ServiceReference::get($r, FragmentRequestDetector::class),
                )
        );
        $this->controller($context, EmailVerificationResendFormController::class, [IdentityCsrf::class,
            IdentityAccessView::class], static fn (DependencyResolver $r): EmailVerificationResendFormController =>
                new EmailVerificationResendFormController(
                    ServiceReference::get($r, IdentityCsrf::class),
                    ServiceReference::get($r, IdentityAccessView::class),
                ));
        $this->controller(
            $context,
            EmailVerificationResendSubmitController::class,
            [IdentityFormInputMapper::class, IdentityCsrf::class, IdentityAccessView::class,
                IdentityRequestContext::class, EmailVerificationResendService::class, FragmentRequestDetector::class],
            static fn (DependencyResolver $r): EmailVerificationResendSubmitController =>
                new EmailVerificationResendSubmitController(
                    ServiceReference::get($r, IdentityFormInputMapper::class),
                    ServiceReference::get($r, IdentityCsrf::class),
                    ServiceReference::get($r, IdentityAccessView::class),
                    ServiceReference::get($r, IdentityRequestContext::class),
                    ServiceReference::get($r, EmailVerificationResendService::class),
                    ServiceReference::get($r, FragmentRequestDetector::class),
                )
        );
        $this->controller($context, EmailVerificationFormController::class, [IdentityCsrf::class,
            IdentityAccessView::class], static fn (DependencyResolver $r): EmailVerificationFormController =>
                new EmailVerificationFormController(
                    ServiceReference::get($r, IdentityCsrf::class),
                    ServiceReference::get($r, IdentityAccessView::class),
                ));
        $this->controller(
            $context,
            EmailVerificationSubmitController::class,
            [IdentityFormInputMapper::class, IdentityCsrf::class, IdentityAccessView::class,
                IdentityRequestContext::class, EmailVerificationService::class, FragmentRequestDetector::class],
            static fn (DependencyResolver $r): EmailVerificationSubmitController =>
                new EmailVerificationSubmitController(
                    ServiceReference::get($r, IdentityFormInputMapper::class),
                    ServiceReference::get($r, IdentityCsrf::class),
                    ServiceReference::get($r, IdentityAccessView::class),
                    ServiceReference::get($r, IdentityRequestContext::class),
                    ServiceReference::get($r, EmailVerificationService::class),
                    ServiceReference::get($r, FragmentRequestDetector::class),
                )
        );
        $this->controller(
            $context,
            EmailVerificationCompletedController::class,
            [IdentityAccessView::class],
            static fn (DependencyResolver $r): EmailVerificationCompletedController =>
            new EmailVerificationCompletedController(ServiceReference::get($r, IdentityAccessView::class))
        );
        $this->controller(
            $context,
            ApplicationReadinessController::class,
            [JsonResponseFactory::class, DatabaseHealthCheck::class, SchemaHealthCheck::class,
                IdentityAccessReadinessCheck::class],
            static fn (DependencyResolver $r): ApplicationReadinessController =>
                new ApplicationReadinessController(
                    ServiceReference::get($r, JsonResponseFactory::class),
                    ServiceReference::get($r, DatabaseHealthCheck::class),
                    ServiceReference::get($r, SchemaHealthCheck::class),
                    ServiceReference::get($r, IdentityAccessReadinessCheck::class),
                )
        );
    }

    /**
     * @param class-string $class
     * @param list<class-string> $dependencies
     */
    private function controller(
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
