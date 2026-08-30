<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\Identity\Infrastructure\Security\ContactCipher;
use Qmdb\Modules\IdentityAccess\Configuration\IdentityAccessConfiguration;
use Qmdb\Modules\IdentityAccess\Domain\Repository\IdentityAccessRepository;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\EmailLookupHashGenerator;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordHasher;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityRecovery\Application\Mail\PasswordRecoveryMessageFactory;
use Qmdb\Modules\IdentityRecovery\Application\Mail\PasswordRecoveryNotifier;
use Qmdb\Modules\IdentityRecovery\Application\PasswordRecoveryRequestService;
use Qmdb\Modules\IdentityRecovery\Application\PasswordResetService;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\IdentityRecovery\Application\Readiness\IdentityRecoveryReadinessCheck;
use Qmdb\Modules\IdentityRecovery\Configuration\IdentityRecoveryConfiguration;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryTokenGenerator;
use Qmdb\Modules\IdentityRecovery\Domain\Repository\PasswordRecoveryChallengeRepository;
use Qmdb\Modules\IdentityRecovery\Domain\Repository\PasswordRecoveryEventRepository;
use Qmdb\Modules\IdentityRecovery\Infrastructure\Mail\SymfonyMailerPasswordRecoveryNotifier;
use Qmdb\Modules\IdentityRecovery\Infrastructure\Persistence\MySqlPasswordRecoveryRepository;
use Qmdb\Modules\IdentityRecovery\Infrastructure\Security\SecurePasswordRecoveryTokenGenerator;
use Qmdb\Modules\IdentitySecurityNotifications\Configuration\SecurityNotificationConfiguration;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\Repository\AccountSecurityNotificationRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\SecurityNotificationDeduplicationKeyFactory;
use Qmdb\Modules\IdentitySessions\Domain\Repository\UserSessionRepository;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityRequestContext;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordPolicy;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordHashingPolicy;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordRecoveryRequestAcceptedController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordRecoveryRequestFormController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordRecoveryRequestSubmitController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordResetCompletedController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordResetFormController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordResetSubmitController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\RecoveryFormInputMapper;
use Qmdb\Modules\IdentitySessions\Application\AuthenticationCookieResponseDecorator;
use Qmdb\Modules\IdentitySessions\Application\SessionCookieFactory;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Localization\TranslationCatalog;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Qmdb\Shared\Presentation\View\PhpViewRenderer;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfTokenManager;
use Qmdb\Shared\Schema\Health\SchemaHealthCheck;
use Qmdb\Shared\Security\Secrets\SecretsProvider;
use Qmdb\Shared\Time\Clock;
use Symfony\Component\Mailer\MailerInterface;

final readonly class IdentityRecoveryModule implements Module
{
    private const ID = 'identity.recovery';

    public function __construct(
        private IdentityRecoveryConfiguration $configuration,
        private IdentityAccessConfiguration $identityAccess,
    ) {
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
            new ModuleId('identity.access'),
            new ModuleId('identity.sessions'),
            new ModuleId('identity.security_notifications'),
            new ModuleId('security.audit'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(
            IdentityRecoveryConfiguration::class,
            self::ID,
            $this->configuration,
        ));
        $context->service(ServiceDefinition::instance(
            SecurePasswordRecoveryTokenGenerator::class,
            self::ID,
            new SecurePasswordRecoveryTokenGenerator(),
        ));
        $context->alias(PasswordRecoveryTokenGenerator::class, SecurePasswordRecoveryTokenGenerator::class);
        $this->registerPersistence($context);
        $this->registerMail($context);
        $this->registerApplications($context);
        $this->registerReadiness($context);
        $this->registerHttp($context);
    }

    private function registerPersistence(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            MySqlPasswordRecoveryRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlPasswordRecoveryRepository =>
                new MySqlPasswordRecoveryRepository(
                    ServiceReference::get($resolver, DatabaseConnectionProvider::class),
                )),
        ));
        $context->alias(PasswordRecoveryChallengeRepository::class, MySqlPasswordRecoveryRepository::class);
        $context->alias(PasswordRecoveryEventRepository::class, MySqlPasswordRecoveryRepository::class);
    }

    private function registerMail(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            PasswordRecoveryMessageFactory::class,
            self::ID,
            [TranslationCatalog::class, PhpViewRenderer::class],
            new ClosureServiceFactory(fn (DependencyResolver $resolver): PasswordRecoveryMessageFactory =>
                new PasswordRecoveryMessageFactory(
                    $this->identityAccess->publicBaseUrl,
                    ServiceReference::get($resolver, TranslationCatalog::class),
                    ServiceReference::get($resolver, PhpViewRenderer::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            SymfonyMailerPasswordRecoveryNotifier::class,
            self::ID,
            [MailerInterface::class],
            new ClosureServiceFactory(fn (DependencyResolver $resolver): SymfonyMailerPasswordRecoveryNotifier =>
                new SymfonyMailerPasswordRecoveryNotifier(
                    ServiceReference::get($resolver, MailerInterface::class),
                    $this->identityAccess->mailFromAddress,
                    $this->identityAccess->mailFromName,
                )),
        ));
        $context->alias(PasswordRecoveryNotifier::class, SymfonyMailerPasswordRecoveryNotifier::class);
    }

    private function registerApplications(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            PasswordRecoveryRequestService::class,
            self::ID,
            [
                PasswordRecoveryChallengeRepository::class,
                PasswordRecoveryEventRepository::class,
                IdentityAccessRepository::class,
                TransactionManager::class,
                PasswordRecoveryTokenGenerator::class,
                IdentityRateLimiter::class,
                IdentityFingerprintGenerator::class,
                EmailLookupHashGenerator::class,
                ContactCipher::class,
                PasswordRecoveryMessageFactory::class,
                PasswordRecoveryNotifier::class,
                IdentityRecoveryConfiguration::class,
                Clock::class,
            ],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): PasswordRecoveryRequestService =>
                new PasswordRecoveryRequestService(
                    ServiceReference::get($resolver, PasswordRecoveryChallengeRepository::class),
                    ServiceReference::get($resolver, PasswordRecoveryEventRepository::class),
                    ServiceReference::get($resolver, IdentityAccessRepository::class),
                    ServiceReference::get($resolver, TransactionManager::class),
                    ServiceReference::get($resolver, PasswordRecoveryTokenGenerator::class),
                    ServiceReference::get($resolver, IdentityRateLimiter::class),
                    ServiceReference::get($resolver, IdentityFingerprintGenerator::class),
                    ServiceReference::get($resolver, EmailLookupHashGenerator::class),
                    ServiceReference::get($resolver, ContactCipher::class),
                    ServiceReference::get($resolver, PasswordRecoveryMessageFactory::class),
                    ServiceReference::get($resolver, PasswordRecoveryNotifier::class),
                    ServiceReference::get($resolver, IdentityRecoveryConfiguration::class),
                    ServiceReference::get($resolver, Clock::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            PasswordResetService::class,
            self::ID,
            [
                PasswordRecoveryChallengeRepository::class,
                PasswordRecoveryEventRepository::class,
                AccountSecurityNotificationRepository::class,
                IdentityAccessRepository::class,
                UserSessionRepository::class,
                TransactionManager::class,
                PasswordHasher::class,
                IdentityRateLimiter::class,
                IdentityFingerprintGenerator::class,
                SecurityNotificationDeduplicationKeyFactory::class,
                IdentityRecoveryConfiguration::class,
                SecurityNotificationConfiguration::class,
                SecurityAuditEventAppender::class,
                Clock::class,
            ],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): PasswordResetService =>
                new PasswordResetService(
                    ServiceReference::get($resolver, PasswordRecoveryChallengeRepository::class),
                    ServiceReference::get($resolver, PasswordRecoveryEventRepository::class),
                    ServiceReference::get($resolver, AccountSecurityNotificationRepository::class),
                    ServiceReference::get($resolver, IdentityAccessRepository::class),
                    ServiceReference::get($resolver, UserSessionRepository::class),
                    ServiceReference::get($resolver, TransactionManager::class),
                    ServiceReference::get($resolver, PasswordHasher::class),
                    ServiceReference::get($resolver, IdentityRateLimiter::class),
                    ServiceReference::get($resolver, IdentityFingerprintGenerator::class),
                    ServiceReference::get($resolver, SecurityNotificationDeduplicationKeyFactory::class),
                    ServiceReference::get($resolver, IdentityRecoveryConfiguration::class),
                    ServiceReference::get($resolver, SecurityNotificationConfiguration::class),
                    ServiceReference::get($resolver, SecurityAuditEventAppender::class),
                    ServiceReference::get($resolver, Clock::class),
                )),
        ));
    }

    private function registerHttp(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            RecoveryFormInputMapper::class,
            self::ID,
            [PasswordPolicy::class],
            new ClosureServiceFactory(fn (DependencyResolver $resolver): RecoveryFormInputMapper =>
                new RecoveryFormInputMapper(
                    $this->identityAccess->formMaxBytes,
                    ServiceReference::get($resolver, PasswordPolicy::class),
                )),
        ));
        $this->controller(
            $context,
            PasswordRecoveryRequestFormController::class,
            [IdentityCsrf::class, IdentityAccessView::class],
            static fn (DependencyResolver $resolver): PasswordRecoveryRequestFormController =>
                new PasswordRecoveryRequestFormController(
                    ServiceReference::get($resolver, IdentityCsrf::class),
                    ServiceReference::get($resolver, IdentityAccessView::class),
                ),
        );
        $this->controller(
            $context,
            PasswordRecoveryRequestAcceptedController::class,
            [IdentityAccessView::class],
            static fn (DependencyResolver $resolver): PasswordRecoveryRequestAcceptedController =>
                new PasswordRecoveryRequestAcceptedController(
                    ServiceReference::get($resolver, IdentityAccessView::class),
                ),
        );
        $this->controller(
            $context,
            PasswordRecoveryRequestSubmitController::class,
            [
                RecoveryFormInputMapper::class,
                IdentityCsrf::class,
                IdentityAccessView::class,
                IdentityRequestContext::class,
                PasswordRecoveryRequestService::class,
                FragmentRequestDetector::class,
            ],
            static fn (DependencyResolver $resolver): PasswordRecoveryRequestSubmitController =>
                new PasswordRecoveryRequestSubmitController(
                    ServiceReference::get($resolver, RecoveryFormInputMapper::class),
                    ServiceReference::get($resolver, IdentityCsrf::class),
                    ServiceReference::get($resolver, IdentityAccessView::class),
                    ServiceReference::get($resolver, IdentityRequestContext::class),
                    ServiceReference::get($resolver, PasswordRecoveryRequestService::class),
                    ServiceReference::get($resolver, FragmentRequestDetector::class),
                ),
        );
        $this->controller(
            $context,
            PasswordResetFormController::class,
            [IdentityCsrf::class, IdentityAccessView::class],
            static fn (DependencyResolver $resolver): PasswordResetFormController =>
                new PasswordResetFormController(
                    ServiceReference::get($resolver, IdentityCsrf::class),
                    ServiceReference::get($resolver, IdentityAccessView::class),
                ),
        );
        $this->controller(
            $context,
            PasswordResetCompletedController::class,
            [IdentityAccessView::class],
            static fn (DependencyResolver $resolver): PasswordResetCompletedController =>
                new PasswordResetCompletedController(
                    ServiceReference::get($resolver, IdentityAccessView::class),
                ),
        );
        $this->controller(
            $context,
            PasswordResetSubmitController::class,
            [
                RecoveryFormInputMapper::class,
                IdentityCsrf::class,
                IdentityAccessView::class,
                IdentityRequestContext::class,
                PasswordResetService::class,
                SessionCookieFactory::class,
                AuthenticationCookieResponseDecorator::class,
                FragmentRequestDetector::class,
            ],
            static fn (DependencyResolver $resolver): PasswordResetSubmitController =>
                new PasswordResetSubmitController(
                    ServiceReference::get($resolver, RecoveryFormInputMapper::class),
                    ServiceReference::get($resolver, IdentityCsrf::class),
                    ServiceReference::get($resolver, IdentityAccessView::class),
                    ServiceReference::get($resolver, IdentityRequestContext::class),
                    ServiceReference::get($resolver, PasswordResetService::class),
                    ServiceReference::get($resolver, SessionCookieFactory::class),
                    ServiceReference::get($resolver, AuthenticationCookieResponseDecorator::class),
                    ServiceReference::get($resolver, FragmentRequestDetector::class),
                ),
        );
    }

    private function registerReadiness(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            IdentityRecoveryReadinessCheck::class,
            self::ID,
            [
                IdentityRecoveryConfiguration::class,
                IdentityAccessConfiguration::class,
                PasswordHashingPolicy::class,
                PasswordPolicy::class,
                CsrfTokenManager::class,
                IdentityFingerprintGenerator::class,
                SecretsProvider::class,
                SchemaHealthCheck::class,
            ],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): IdentityRecoveryReadinessCheck =>
                new IdentityRecoveryReadinessCheck(
                    ServiceReference::get($resolver, IdentityRecoveryConfiguration::class),
                    ServiceReference::get($resolver, IdentityAccessConfiguration::class),
                    ServiceReference::get($resolver, PasswordHashingPolicy::class),
                    ServiceReference::get($resolver, PasswordPolicy::class),
                    ServiceReference::get($resolver, CsrfTokenManager::class),
                    ServiceReference::get($resolver, IdentityFingerprintGenerator::class),
                    ServiceReference::get($resolver, SecretsProvider::class),
                    ServiceReference::get($resolver, SchemaHealthCheck::class),
                )),
        ));
    }

    /** @param list<class-string> $dependencies */
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
}
