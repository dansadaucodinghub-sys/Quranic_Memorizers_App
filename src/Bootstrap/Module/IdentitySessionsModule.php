<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Nyholm\Psr7\Factory\Psr17Factory;
use Qmdb\Modules\IdentityAccess\Application\Authentication\PasswordAuthenticationRepository;
use Qmdb\Modules\IdentityAccess\Application\Authentication\PasswordAuthenticationService;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityRequestContext;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordHasher;
use Qmdb\Modules\IdentitySessions\Application\AccountLoginService;
use Qmdb\Modules\IdentitySessions\Application\AccountLogoutService;
use Qmdb\Modules\IdentitySessions\Application\AccountSessionInventoryHandler;
use Qmdb\Modules\IdentitySessions\Application\AuthenticationCookieResponseDecorator;
use Qmdb\Modules\IdentitySessions\Application\DeviceCookieFactory;
use Qmdb\Modules\IdentitySessions\Application\DummySessionTokenHashProvider;
use Qmdb\Modules\IdentitySessions\Application\RemoteDeviceRevocationService;
use Qmdb\Modules\IdentitySessions\Application\RemoteSessionRevocationService;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\IdentitySessions\Application\Readiness\IdentitySessionReadinessCheck;
use Qmdb\Modules\IdentitySessions\Application\SessionAuthenticationService;
use Qmdb\Modules\IdentitySessions\Application\SessionCookieFactory;
use Qmdb\Modules\IdentitySessions\Configuration\IdentitySessionConfiguration;
use Qmdb\Modules\IdentitySessions\Domain\DeviceCookieParser;
use Qmdb\Modules\IdentitySessions\Domain\Repository\UserDeviceRepository;
use Qmdb\Modules\IdentitySessions\Domain\Repository\UserSessionRepository;
use Qmdb\Modules\IdentitySessions\Domain\SessionCookieParser;
use Qmdb\Modules\IdentitySessions\Infrastructure\Persistence\MySqlIdentitySessionRepository;
use Qmdb\Modules\IdentitySessions\Interface\Http\AccountSecurityController;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\IdentitySessions\Interface\Http\DeviceRevocationController;
use Qmdb\Modules\IdentitySessions\Interface\Http\IdentitySessionFormInput;
use Qmdb\Modules\IdentitySessions\Interface\Http\LoginFormController;
use Qmdb\Modules\IdentitySessions\Interface\Http\LoginSubmitController;
use Qmdb\Modules\IdentitySessions\Interface\Http\LogoutController;
use Qmdb\Modules\IdentitySessions\Interface\Http\SessionAuthenticationMiddleware;
use Qmdb\Modules\IdentitySessions\Interface\Http\SessionRevocationController;
use Qmdb\Modules\IdentityMultiFactor\Application\AuthenticationTransactionCookieFactory;
use Qmdb\Modules\IdentityMultiFactor\Application\PasswordLoginMfaGate;
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
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Persistence\MySqlIdentityMultiFactorRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Http\Message\JsonResponseFactory;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;
use Qmdb\Shared\Schema\Health\SchemaHealthCheck;
use Qmdb\Shared\Time\Clock;

final readonly class IdentitySessionsModule implements Module
{
    private const ID = 'identity.sessions';

    public function __construct(
        private IdentitySessionConfiguration $configuration,
        private IdentityMultiFactorConfiguration $multiFactorConfiguration,
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
            new ModuleId('security.audit'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(
            IdentitySessionConfiguration::class,
            self::ID,
            $this->configuration,
        ));
        $context->service(ServiceDefinition::instance(
            IdentityMultiFactorConfiguration::class,
            self::ID,
            $this->multiFactorConfiguration,
        ));
        foreach (
            [
            SessionCookieParser::class => new SessionCookieParser(),
            DeviceCookieParser::class => new DeviceCookieParser(),
            DummySessionTokenHashProvider::class => new DummySessionTokenHashProvider(),
            AuthenticationCookieResponseDecorator::class => new AuthenticationCookieResponseDecorator(),
            IdentitySessionFormInput::class => new IdentitySessionFormInput(),
            ] as $class => $instance
        ) {
            $context->service(ServiceDefinition::instance($class, self::ID, $instance));
        }
        $this->registerPersistence($context);
        $this->registerApplications($context);
        $this->registerReadiness($context);
        $this->registerHttp($context);
    }

    private function registerPersistence(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            MySqlIdentitySessionRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): MySqlIdentitySessionRepository =>
                new MySqlIdentitySessionRepository(ServiceReference::get($r, DatabaseConnectionProvider::class))),
        ));
        $context->alias(UserDeviceRepository::class, MySqlIdentitySessionRepository::class);
        $context->alias(UserSessionRepository::class, MySqlIdentitySessionRepository::class);
        $context->service(ServiceDefinition::factory(
            MySqlIdentityMultiFactorRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): MySqlIdentityMultiFactorRepository =>
                new MySqlIdentityMultiFactorRepository(
                    ServiceReference::get($r, DatabaseConnectionProvider::class),
                )),
        ));
        foreach (
            [
            AccountMfaPolicyRepository::class,
            AuthenticationTransactionRepository::class,
            MultiFactorNotificationTargetRepository::class,
            PasskeyCredentialRepository::class,
            RecoveryCodeSetRepository::class,
            StepUpGrantRepository::class,
            TotpAuthenticatorRepository::class,
            WebAuthnCeremonyRepository::class,
            WebAuthnUserHandleRepository::class,
            ] as $contract
        ) {
            $context->alias($contract, MySqlIdentityMultiFactorRepository::class);
        }
    }

    private function registerApplications(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            AuthenticationTransactionCookieFactory::class,
            self::ID,
            [IdentityMultiFactorConfiguration::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): AuthenticationTransactionCookieFactory =>
                new AuthenticationTransactionCookieFactory(
                    ServiceReference::get($r, IdentityMultiFactorConfiguration::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            PasswordLoginMfaGate::class,
            self::ID,
            [AccountMfaPolicyRepository::class, AuthenticationTransactionRepository::class,
                IdentityMultiFactorConfiguration::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): PasswordLoginMfaGate =>
                new PasswordLoginMfaGate(
                    ServiceReference::get($r, AccountMfaPolicyRepository::class),
                    ServiceReference::get($r, AuthenticationTransactionRepository::class),
                    ServiceReference::get($r, IdentityMultiFactorConfiguration::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            SessionCookieFactory::class,
            self::ID,
            [IdentitySessionConfiguration::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): SessionCookieFactory =>
                new SessionCookieFactory(ServiceReference::get($r, IdentitySessionConfiguration::class))),
        ));
        $context->service(ServiceDefinition::factory(
            DeviceCookieFactory::class,
            self::ID,
            [IdentitySessionConfiguration::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): DeviceCookieFactory =>
                new DeviceCookieFactory(ServiceReference::get($r, IdentitySessionConfiguration::class))),
        ));
        $context->service(ServiceDefinition::factory(
            SessionAuthenticationService::class,
            self::ID,
            [UserSessionRepository::class, TransactionManager::class, SessionCookieParser::class,
                SessionCookieFactory::class, DummySessionTokenHashProvider::class,
                IdentitySessionConfiguration::class, Clock::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): SessionAuthenticationService =>
                new SessionAuthenticationService(
                    ServiceReference::get($r, UserSessionRepository::class),
                    ServiceReference::get($r, TransactionManager::class),
                    ServiceReference::get($r, SessionCookieParser::class),
                    ServiceReference::get($r, SessionCookieFactory::class),
                    ServiceReference::get($r, DummySessionTokenHashProvider::class),
                    ServiceReference::get($r, IdentitySessionConfiguration::class),
                    ServiceReference::get($r, Clock::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            AccountLoginService::class,
            self::ID,
            [PasswordAuthenticationService::class, PasswordAuthenticationRepository::class,
                PasswordHasher::class, UserDeviceRepository::class, UserSessionRepository::class,
                TransactionManager::class, DeviceCookieParser::class, SessionCookieFactory::class,
                DeviceCookieFactory::class, IdentitySessionConfiguration::class, Clock::class,
                PasswordLoginMfaGate::class, AuthenticationTransactionCookieFactory::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): AccountLoginService =>
                new AccountLoginService(
                    ServiceReference::get($r, PasswordAuthenticationService::class),
                    ServiceReference::get($r, PasswordAuthenticationRepository::class),
                    ServiceReference::get($r, PasswordHasher::class),
                    ServiceReference::get($r, UserDeviceRepository::class),
                    ServiceReference::get($r, UserSessionRepository::class),
                    ServiceReference::get($r, TransactionManager::class),
                    ServiceReference::get($r, DeviceCookieParser::class),
                    ServiceReference::get($r, SessionCookieFactory::class),
                    ServiceReference::get($r, DeviceCookieFactory::class),
                    ServiceReference::get($r, IdentitySessionConfiguration::class),
                    ServiceReference::get($r, Clock::class),
                    ServiceReference::get($r, PasswordLoginMfaGate::class),
                    ServiceReference::get($r, AuthenticationTransactionCookieFactory::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            AccountLogoutService::class,
            self::ID,
            [UserSessionRepository::class, SessionCookieFactory::class, Clock::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): AccountLogoutService =>
                new AccountLogoutService(
                    ServiceReference::get($r, UserSessionRepository::class),
                    ServiceReference::get($r, SessionCookieFactory::class),
                    ServiceReference::get($r, Clock::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            AccountSessionInventoryHandler::class,
            self::ID,
            [UserSessionRepository::class, UserDeviceRepository::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): AccountSessionInventoryHandler =>
                new AccountSessionInventoryHandler(
                    ServiceReference::get($r, UserSessionRepository::class),
                    ServiceReference::get($r, UserDeviceRepository::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            RemoteSessionRevocationService::class,
            self::ID,
            [UserSessionRepository::class, SecurityAuditEventAppender::class, TransactionManager::class, Clock::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): RemoteSessionRevocationService =>
                new RemoteSessionRevocationService(
                    ServiceReference::get($r, UserSessionRepository::class),
                    ServiceReference::get($r, SecurityAuditEventAppender::class),
                    ServiceReference::get($r, TransactionManager::class),
                    ServiceReference::get($r, Clock::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            RemoteDeviceRevocationService::class,
            self::ID,
            [UserDeviceRepository::class, UserSessionRepository::class, TransactionManager::class, SecurityAuditEventAppender::class, Clock::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): RemoteDeviceRevocationService =>
                new RemoteDeviceRevocationService(
                    ServiceReference::get($r, UserDeviceRepository::class),
                    ServiceReference::get($r, UserSessionRepository::class),
                    ServiceReference::get($r, TransactionManager::class),
                    ServiceReference::get($r, SecurityAuditEventAppender::class),
                    ServiceReference::get($r, Clock::class),
                )),
        ));
    }

    private function registerHttp(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            SessionAuthenticationMiddleware::class,
            self::ID,
            [SessionAuthenticationService::class, SessionCookieFactory::class,
                AuthenticationCookieResponseDecorator::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): SessionAuthenticationMiddleware =>
                new SessionAuthenticationMiddleware(
                    ServiceReference::get($r, SessionAuthenticationService::class),
                    ServiceReference::get($r, SessionCookieFactory::class),
                    ServiceReference::get($r, AuthenticationCookieResponseDecorator::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            AuthenticatedRequestGuard::class,
            self::ID,
            [FragmentRequestDetector::class, JsonResponseFactory::class, Psr17Factory::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): AuthenticatedRequestGuard =>
                new AuthenticatedRequestGuard(
                    ServiceReference::get($r, FragmentRequestDetector::class),
                    ServiceReference::get($r, JsonResponseFactory::class),
                    ServiceReference::get($r, Psr17Factory::class),
                )),
        ));
        $this->controllers($context);
    }

    private function registerReadiness(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            IdentitySessionReadinessCheck::class,
            self::ID,
            [IdentitySessionConfiguration::class, SessionCookieFactory::class,
                DeviceCookieFactory::class, SchemaHealthCheck::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): IdentitySessionReadinessCheck =>
                new IdentitySessionReadinessCheck(
                    ServiceReference::get($r, IdentitySessionConfiguration::class),
                    ServiceReference::get($r, SessionCookieFactory::class),
                    ServiceReference::get($r, DeviceCookieFactory::class),
                    ServiceReference::get($r, SchemaHealthCheck::class),
                )),
        ));
    }

    private function controllers(ModuleRegistrationContext $context): void
    {
        $this->controller(
            $context,
            LoginFormController::class,
            [AuthenticatedRequestGuard::class, IdentityCsrf::class, IdentityAccessView::class],
            static fn (DependencyResolver $r): LoginFormController => new LoginFormController(
                ServiceReference::get($r, AuthenticatedRequestGuard::class),
                ServiceReference::get($r, IdentityCsrf::class),
                ServiceReference::get($r, IdentityAccessView::class),
            )
        );
        $this->controller(
            $context,
            LoginSubmitController::class,
            [IdentitySessionFormInput::class, IdentityCsrf::class, IdentityAccessView::class,
                IdentityRequestContext::class, AuthenticatedRequestGuard::class, AccountLoginService::class,
                DeviceCookieFactory::class, AuthenticationCookieResponseDecorator::class,
                FragmentRequestDetector::class],
            static fn (DependencyResolver $r): LoginSubmitController => new LoginSubmitController(
                ServiceReference::get($r, IdentitySessionFormInput::class),
                ServiceReference::get($r, IdentityCsrf::class),
                ServiceReference::get($r, IdentityAccessView::class),
                ServiceReference::get($r, IdentityRequestContext::class),
                ServiceReference::get($r, AuthenticatedRequestGuard::class),
                ServiceReference::get($r, AccountLoginService::class),
                ServiceReference::get($r, DeviceCookieFactory::class),
                ServiceReference::get($r, AuthenticationCookieResponseDecorator::class),
                ServiceReference::get($r, FragmentRequestDetector::class),
            )
        );
        $this->controller(
            $context,
            LogoutController::class,
            [IdentitySessionFormInput::class, IdentityCsrf::class, AuthenticatedRequestGuard::class,
                AccountLogoutService::class, IdentityAccessView::class,
                AuthenticationCookieResponseDecorator::class, FragmentRequestDetector::class],
            static fn (DependencyResolver $r): LogoutController => new LogoutController(
                ServiceReference::get($r, IdentitySessionFormInput::class),
                ServiceReference::get($r, IdentityCsrf::class),
                ServiceReference::get($r, AuthenticatedRequestGuard::class),
                ServiceReference::get($r, AccountLogoutService::class),
                ServiceReference::get($r, IdentityAccessView::class),
                ServiceReference::get($r, AuthenticationCookieResponseDecorator::class),
                ServiceReference::get($r, FragmentRequestDetector::class),
            )
        );
        $this->controller(
            $context,
            AccountSecurityController::class,
            [AuthenticatedRequestGuard::class, AccountSessionInventoryHandler::class,
                IdentityCsrf::class, IdentityAccessView::class],
            static fn (DependencyResolver $r): AccountSecurityController => new AccountSecurityController(
                ServiceReference::get($r, AuthenticatedRequestGuard::class),
                ServiceReference::get($r, AccountSessionInventoryHandler::class),
                ServiceReference::get($r, IdentityCsrf::class),
                ServiceReference::get($r, IdentityAccessView::class),
            )
        );
        $this->revocationControllers($context);
    }

    private function revocationControllers(ModuleRegistrationContext $context): void
    {
        $shared = [AuthenticatedRequestGuard::class, AccountSessionInventoryHandler::class,
            IdentitySessionFormInput::class, IdentityCsrf::class, IdentityAccessView::class,
            FragmentRequestDetector::class, Psr17Factory::class];
        $this->controller(
            $context,
            SessionRevocationController::class,
            [...$shared, RemoteSessionRevocationService::class],
            static fn (DependencyResolver $r): SessionRevocationController => new SessionRevocationController(
                ServiceReference::get($r, AuthenticatedRequestGuard::class),
                ServiceReference::get($r, AccountSessionInventoryHandler::class),
                ServiceReference::get($r, RemoteSessionRevocationService::class),
                ServiceReference::get($r, IdentitySessionFormInput::class),
                ServiceReference::get($r, IdentityCsrf::class),
                ServiceReference::get($r, IdentityAccessView::class),
                ServiceReference::get($r, FragmentRequestDetector::class),
                ServiceReference::get($r, Psr17Factory::class),
            )
        );
        $this->controller(
            $context,
            DeviceRevocationController::class,
            [...$shared, RemoteDeviceRevocationService::class],
            static fn (DependencyResolver $r): DeviceRevocationController => new DeviceRevocationController(
                ServiceReference::get($r, AuthenticatedRequestGuard::class),
                ServiceReference::get($r, AccountSessionInventoryHandler::class),
                ServiceReference::get($r, RemoteDeviceRevocationService::class),
                ServiceReference::get($r, IdentitySessionFormInput::class),
                ServiceReference::get($r, IdentityCsrf::class),
                ServiceReference::get($r, IdentityAccessView::class),
                ServiceReference::get($r, FragmentRequestDetector::class),
                ServiceReference::get($r, Psr17Factory::class),
            )
        );
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
