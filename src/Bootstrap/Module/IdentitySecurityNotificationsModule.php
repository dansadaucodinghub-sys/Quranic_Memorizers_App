<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\Identity\Infrastructure\Security\ContactCipher;
use Qmdb\Modules\IdentityAccess\Configuration\IdentityAccessConfiguration;
use Qmdb\Modules\IdentitySecurityNotifications\Application\AccountSecurityNotificationDeliveryService;
use Qmdb\Modules\IdentitySecurityNotifications\Application\AccountSecurityNotificationFailureClassifier;
use Qmdb\Modules\IdentitySecurityNotifications\Application\Mail\AccountSecurityNotificationNotifier;
use Qmdb\Modules\IdentitySecurityNotifications\Application\Mail\AccountSecurityNotificationMessageFactory;
use Qmdb\Modules\IdentitySecurityNotifications\Application\Mail\PasswordResetCompletedNotificationMessageFactory;
use Qmdb\Modules\IdentitySecurityNotifications\Application\ScheduledSecurityNotificationTask;
use Qmdb\Modules\IdentitySecurityNotifications\Application\SecurityNotificationRetryPolicy;
use Qmdb\Modules\IdentitySecurityNotifications\Application\Readiness\IdentitySecurityNotificationReadinessCheck;
use Qmdb\Modules\IdentitySecurityNotifications\Configuration\SecurityNotificationConfiguration;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\Repository\AccountSecurityNotificationEventRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\Repository\AccountSecurityNotificationRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\SecurityNotificationDeduplicationKeyFactory;
use Qmdb\Modules\IdentitySecurityNotifications\Infrastructure\Mail\SymfonyMailerSecurityNotificationNotifier;
use Qmdb\Modules\IdentitySecurityNotifications\Infrastructure\Persistence\MySqlAccountSecurityNotificationRepository;
use Qmdb\Shared\Background\Scheduler\FixedIntervalSchedule;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskId;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskMap;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRegistration;
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
use Qmdb\Shared\Schema\Health\SchemaHealthCheck;
use Qmdb\Shared\Security\Secrets\SecretsProvider;
use Qmdb\Shared\Time\Clock;
use Symfony\Component\Mailer\MailerInterface;

final readonly class IdentitySecurityNotificationsModule implements Module
{
    private const ID = 'identity.security_notifications';

    public function __construct(
        private SecurityNotificationConfiguration $configuration,
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
            new ModuleId('foundation.background'),
            new ModuleId('foundation.presentation'),
            new ModuleId('identity.accounts'),
            new ModuleId('identity.access'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(
            SecurityNotificationConfiguration::class,
            self::ID,
            $this->configuration,
        ));
        $context->service(ServiceDefinition::instance(
            SecurityNotificationDeduplicationKeyFactory::class,
            self::ID,
            new SecurityNotificationDeduplicationKeyFactory(),
        ));
        $context->service(ServiceDefinition::instance(
            AccountSecurityNotificationFailureClassifier::class,
            self::ID,
            new AccountSecurityNotificationFailureClassifier(),
        ));
        $this->registerPersistence($context);
        $this->registerDelivery($context);
        $this->registerReadiness($context);
        $context->scheduledTask(new ScheduledTaskRegistration(
            new ScheduledTaskId('identity.security_notifications.deliver'),
            'Deliver due account security notifications.',
            new FixedIntervalSchedule(60),
            ScheduledSecurityNotificationTask::class,
            $this->configuration->leaseSeconds,
            self::ID,
        ));
    }

    private function registerPersistence(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            MySqlAccountSecurityNotificationRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): MySqlAccountSecurityNotificationRepository =>
                    new MySqlAccountSecurityNotificationRepository(
                        ServiceReference::get($resolver, DatabaseConnectionProvider::class),
                    ),
            ),
        ));
        $context->alias(
            AccountSecurityNotificationRepository::class,
            MySqlAccountSecurityNotificationRepository::class,
        );
        $context->alias(
            AccountSecurityNotificationEventRepository::class,
            MySqlAccountSecurityNotificationRepository::class,
        );
    }

    private function registerDelivery(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            PasswordResetCompletedNotificationMessageFactory::class,
            self::ID,
            [TranslationCatalog::class, PhpViewRenderer::class],
            new ClosureServiceFactory(
                fn (DependencyResolver $resolver): PasswordResetCompletedNotificationMessageFactory =>
                    new PasswordResetCompletedNotificationMessageFactory(
                        $this->identityAccess->publicBaseUrl,
                        ServiceReference::get($resolver, TranslationCatalog::class),
                        ServiceReference::get($resolver, PhpViewRenderer::class),
                    ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            AccountSecurityNotificationMessageFactory::class,
            self::ID,
            [
                PasswordResetCompletedNotificationMessageFactory::class,
                TranslationCatalog::class,
                PhpViewRenderer::class,
            ],
            new ClosureServiceFactory(
                fn (DependencyResolver $resolver): AccountSecurityNotificationMessageFactory =>
                    new AccountSecurityNotificationMessageFactory(
                        ServiceReference::get($resolver, PasswordResetCompletedNotificationMessageFactory::class),
                        $this->identityAccess->publicBaseUrl,
                        ServiceReference::get($resolver, TranslationCatalog::class),
                        ServiceReference::get($resolver, PhpViewRenderer::class),
                    ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            SymfonyMailerSecurityNotificationNotifier::class,
            self::ID,
            [MailerInterface::class],
            new ClosureServiceFactory(
                fn (DependencyResolver $resolver): SymfonyMailerSecurityNotificationNotifier =>
                    new SymfonyMailerSecurityNotificationNotifier(
                        ServiceReference::get($resolver, MailerInterface::class),
                        $this->identityAccess->mailFromAddress,
                        $this->identityAccess->mailFromName,
                    ),
            ),
        ));
        $context->alias(
            AccountSecurityNotificationNotifier::class,
            SymfonyMailerSecurityNotificationNotifier::class,
        );
        $context->service(ServiceDefinition::factory(
            SecurityNotificationRetryPolicy::class,
            self::ID,
            [SecurityNotificationConfiguration::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): SecurityNotificationRetryPolicy =>
                new SecurityNotificationRetryPolicy(
                    ServiceReference::get($resolver, SecurityNotificationConfiguration::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            AccountSecurityNotificationDeliveryService::class,
            self::ID,
            [
                AccountSecurityNotificationRepository::class,
                TransactionManager::class,
                ContactCipher::class,
                AccountSecurityNotificationMessageFactory::class,
                AccountSecurityNotificationNotifier::class,
                AccountSecurityNotificationFailureClassifier::class,
                SecurityNotificationRetryPolicy::class,
                SecurityNotificationConfiguration::class,
                Clock::class,
            ],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): AccountSecurityNotificationDeliveryService =>
                    new AccountSecurityNotificationDeliveryService(
                        ServiceReference::get($resolver, AccountSecurityNotificationRepository::class),
                        ServiceReference::get($resolver, TransactionManager::class),
                        ServiceReference::get($resolver, ContactCipher::class),
                        ServiceReference::get($resolver, AccountSecurityNotificationMessageFactory::class),
                        ServiceReference::get($resolver, AccountSecurityNotificationNotifier::class),
                        ServiceReference::get($resolver, AccountSecurityNotificationFailureClassifier::class),
                        ServiceReference::get($resolver, SecurityNotificationRetryPolicy::class),
                        ServiceReference::get($resolver, SecurityNotificationConfiguration::class),
                        ServiceReference::get($resolver, Clock::class),
                    ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            ScheduledSecurityNotificationTask::class,
            self::ID,
            [AccountSecurityNotificationDeliveryService::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): ScheduledSecurityNotificationTask =>
                new ScheduledSecurityNotificationTask(
                    ServiceReference::get($resolver, AccountSecurityNotificationDeliveryService::class),
                )),
        ));
    }

    private function registerReadiness(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            IdentitySecurityNotificationReadinessCheck::class,
            self::ID,
            [
                SecurityNotificationConfiguration::class,
                IdentityAccessConfiguration::class,
                ScheduledTaskMap::class,
                SecretsProvider::class,
                SchemaHealthCheck::class,
            ],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): IdentitySecurityNotificationReadinessCheck =>
                    new IdentitySecurityNotificationReadinessCheck(
                        ServiceReference::get($resolver, SecurityNotificationConfiguration::class),
                        ServiceReference::get($resolver, IdentityAccessConfiguration::class),
                        ServiceReference::get($resolver, ScheduledTaskMap::class),
                        ServiceReference::get($resolver, SecretsProvider::class),
                        ServiceReference::get($resolver, SchemaHealthCheck::class),
                    ),
            ),
        ));
    }
}
