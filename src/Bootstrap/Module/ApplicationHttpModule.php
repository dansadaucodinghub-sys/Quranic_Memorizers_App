<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Bootstrap\Module;

use Closure;
use Nyholm\Psr7\Factory\Psr17Factory;
use Qmdb\Bootstrap\Http\HttpRuntime;
use Qmdb\Modules\IdentityAccess\Interface\Http\AccountRegistrationAcceptedController;
use Qmdb\Modules\IdentityAccess\Interface\Http\AccountRegistrationFormController;
use Qmdb\Modules\IdentityAccess\Interface\Http\AccountRegistrationSubmitController;
use Qmdb\Modules\IdentityAccess\Interface\Http\EmailVerificationCompletedController;
use Qmdb\Modules\IdentityAccess\Interface\Http\EmailVerificationFormController;
use Qmdb\Modules\IdentityAccess\Interface\Http\EmailVerificationResendFormController;
use Qmdb\Modules\IdentityAccess\Interface\Http\EmailVerificationResendSubmitController;
use Qmdb\Modules\IdentityAccess\Interface\Http\EmailVerificationSubmitController;
use Qmdb\Modules\IdentityAccess\Application\Readiness\IdentityAccessReadinessCheck;
use Qmdb\Modules\IdentitySessions\Interface\Http\AccountSecurityController;
use Qmdb\Modules\IdentitySessions\Interface\Http\ApplicationReadinessController;
use Qmdb\Modules\IdentitySessions\Interface\Http\DeviceRevocationController;
use Qmdb\Modules\IdentitySessions\Interface\Http\LoginFormController;
use Qmdb\Modules\IdentitySessions\Interface\Http\LoginSubmitController;
use Qmdb\Modules\IdentitySessions\Interface\Http\LogoutController;
use Qmdb\Modules\IdentitySessions\Interface\Http\SessionAuthenticationMiddleware;
use Qmdb\Modules\IdentitySessions\Interface\Http\SessionRevocationController;
use Qmdb\Modules\IdentitySessions\Application\Readiness\IdentitySessionReadinessCheck;
use Qmdb\Modules\IdentityRecovery\Application\Readiness\IdentityRecoveryReadinessCheck;
use Qmdb\Modules\IdentitySecurityNotifications\Application\Readiness\IdentitySecurityNotificationReadinessCheck;
use Qmdb\Modules\IdentityMultiFactor\Application\Readiness\IdentityMultiFactorReadinessCheck;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationReadinessCheck;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditReadinessCheck;
use Qmdb\Modules\TenancyContext\Application\TenantContextReadinessCheck;
use Qmdb\Modules\TenancyContext\Interface\Http\AccountWorkspacesController;
use Qmdb\Modules\TenancyContext\Interface\Http\CurrentWorkspaceController;
use Qmdb\Modules\TenancyContext\Interface\Http\TenantContextMiddleware;
use Qmdb\Modules\SecurityPrivilegedAccess\Interface\Http\PrivilegedAccessContextMiddleware;
use Qmdb\Modules\SecurityPrivilegedAccess\Interface\Http\PrivilegedAccessController;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessReadinessCheck;
use Qmdb\Modules\IdentityAccountState\Interface\Http\AccountStateSecurityController;
use Qmdb\Modules\IdentityAccountState\Interface\Http\SecurityAuditViewerController;
use Qmdb\Modules\TenancyContext\Interface\Http\WorkspaceClearController;
use Qmdb\Modules\TenancyContext\Interface\Http\WorkspaceSwitchController;
use Qmdb\Modules\IdentityMultiFactor\Interface\Http\IdentityMultiFactorController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordRecoveryRequestAcceptedController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordRecoveryRequestFormController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordRecoveryRequestSubmitController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordResetCompletedController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordResetFormController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordResetSubmitController;
use Qmdb\Modules\Geography\Application\GeographyReferenceReadinessCheck;
use Qmdb\Modules\Geography\Interface\Http\GeographyChildrenLookupController;
use Qmdb\Modules\Geography\Interface\Http\NigeriaGeographyAreaController;
use Qmdb\Modules\Geography\Interface\Http\NigeriaGeographyDirectoryController;
use Qmdb\Modules\People\Application\PeopleProfilesReadinessCheck;
use Qmdb\Modules\People\Interface\Http\PeopleProfilesController;
use Qmdb\Modules\Organizations\Application\OrganizationsRegistryReadinessCheck;
use Qmdb\Modules\Organizations\Interface\Http\OrganizationsRegistryController;
use Qmdb\Modules\OrganizationAffiliations\Application\OrganizationAffiliationsReadinessCheck;
use Qmdb\Modules\OrganizationAffiliations\Interface\Http\OrganizationAffiliationsController;
use Qmdb\Modules\IdentityResolution\Application\PeopleIdentityResolutionReadinessCheck;
use Qmdb\Modules\IdentityResolution\Interface\Http\PeopleIdentityResolutionController;
use Qmdb\Modules\QuranReferenceGovernance\Interface\Http\QuranReleaseGovernanceController;
use Qmdb\Modules\QuranReferenceGovernance\Interface\Http\QuranPublicReferenceController;
use Qmdb\Modules\QuranReferenceGovernance\Interface\Http\QuranSearchCorpusIntegrityController;
use Qmdb\Modules\CompetitionResults\Interface\Http\CompetitionP6WorkflowController;
use Qmdb\Modules\CompetitionResults\Interface\Http\CompetitionPublicResultsController;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Database\Health\DatabaseHealthCheck;
use Qmdb\Shared\Http\Controller\ControllerDispatcher;
use Qmdb\Shared\Http\Controller\LivenessController;
use Qmdb\Shared\Http\Controller\SystemAboutApiController;
use Qmdb\Shared\Http\Controller\SystemAboutPageController;
use Qmdb\Shared\Http\Controller\SystemHomeController;
use Qmdb\Shared\Http\Controller\SystemStatusPageController;
use Qmdb\Shared\Http\Kernel\HttpKernel;
use Qmdb\Shared\Http\Message\ProblemDetailsResponseFactory;
use Qmdb\Shared\Http\Message\JsonResponseFactory;
use Qmdb\Shared\Http\Middleware\CorrelationIdMiddleware;
use Qmdb\Shared\Http\Middleware\CspNonceMiddleware;
use Qmdb\Shared\Http\Middleware\ExceptionHandlingMiddleware;
use Qmdb\Shared\Http\Middleware\HttpRequestLoggingMiddleware;
use Qmdb\Shared\Http\Middleware\LocaleMiddleware;
use Qmdb\Shared\Http\Middleware\RequestTargetValidationMiddleware;
use Qmdb\Shared\Http\Middleware\SecurityHeadersMiddleware;
use Qmdb\Shared\Http\Request\NativeServerRequestFactory;
use Qmdb\Shared\Http\Response\ResponseEmitter;
use Qmdb\Shared\Http\Routing\RouteCollection;
use Qmdb\Shared\Http\Routing\Security\ProductionRouteSecurityPolicyCatalog;
use Qmdb\Shared\Http\Routing\Security\RouteSecurityVerifier;
use Qmdb\Shared\Http\Routing\Security\RouteSecurityVerifyConsoleCommand;
use Qmdb\Shared\Http\Routing\Router;
use Qmdb\Shared\Http\Routing\RoutingRequestHandler;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Qmdb\Shared\Observability\Error\ErrorHandlingRuntime;
use Qmdb\Shared\Schema\Health\SchemaHealthCheck;
use RuntimeException;

final readonly class ApplicationHttpModule implements Module
{
    private const ID = 'application.http';

    public function __construct(private string $projectRoot)
    {
    }

    public function id(): ModuleId
    {
        return new ModuleId(self::ID);
    }

    public function dependencies(): array
    {
        return [
            new ModuleId('foundation.http'),
            new ModuleId('identity.access'),
            new ModuleId('identity.sessions'),
            new ModuleId('identity.recovery'),
            new ModuleId('identity.multifactor'),
            new ModuleId('security.authorization'),
            new ModuleId('security.audit'),
            new ModuleId('tenancy.context'),
            new ModuleId('security.privileged_access'),
            new ModuleId('identity.account_state'),
            new ModuleId('reference.geography'),
            new ModuleId('people.profiles'),
            new ModuleId('organizations.registry'),
            new ModuleId('organizations.affiliations'),
            new ModuleId('people.identity_resolution'),
            new ModuleId('quran.reference_governance'),
            new ModuleId('competition.results'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(
            ProductionRouteSecurityPolicyCatalog::class,
            self::ID,
            new ProductionRouteSecurityPolicyCatalog(),
        ));
        $context->service(ServiceDefinition::factory(
            ApplicationReadinessController::class,
            self::ID,
            [
                JsonResponseFactory::class,
                DatabaseHealthCheck::class,
                SchemaHealthCheck::class,
                IdentityAccessReadinessCheck::class,
                IdentitySessionReadinessCheck::class,
                IdentityRecoveryReadinessCheck::class,
                IdentitySecurityNotificationReadinessCheck::class,
                IdentityMultiFactorReadinessCheck::class,
                AuthorizationReadinessCheck::class,
                SecurityAuditReadinessCheck::class,
                TenantContextReadinessCheck::class,
                PrivilegedAccessReadinessCheck::class,
                GeographyReferenceReadinessCheck::class,
                PeopleProfilesReadinessCheck::class,
                OrganizationsRegistryReadinessCheck::class,
                OrganizationAffiliationsReadinessCheck::class,
                PeopleIdentityResolutionReadinessCheck::class,
            ],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): ApplicationReadinessController =>
                new ApplicationReadinessController(
                    ServiceReference::get($resolver, JsonResponseFactory::class),
                    ServiceReference::get($resolver, DatabaseHealthCheck::class),
                    ServiceReference::get($resolver, SchemaHealthCheck::class),
                    ServiceReference::get($resolver, IdentityAccessReadinessCheck::class),
                    ServiceReference::get($resolver, IdentitySessionReadinessCheck::class),
                    ServiceReference::get($resolver, IdentityRecoveryReadinessCheck::class),
                    ServiceReference::get($resolver, IdentitySecurityNotificationReadinessCheck::class),
                    ServiceReference::get($resolver, IdentityMultiFactorReadinessCheck::class),
                    ServiceReference::get($resolver, AuthorizationReadinessCheck::class),
                    ServiceReference::get($resolver, SecurityAuditReadinessCheck::class),
                    ServiceReference::get($resolver, TenantContextReadinessCheck::class),
                    ServiceReference::get($resolver, PrivilegedAccessReadinessCheck::class),
                    ServiceReference::get($resolver, GeographyReferenceReadinessCheck::class),
                    ServiceReference::get($resolver, PeopleProfilesReadinessCheck::class),
                    ServiceReference::get($resolver, OrganizationsRegistryReadinessCheck::class),
                    ServiceReference::get($resolver, OrganizationAffiliationsReadinessCheck::class),
                    ServiceReference::get($resolver, PeopleIdentityResolutionReadinessCheck::class),
                )),
        ));
        $controllers = [
            SystemHomeController::class,
            SystemAboutPageController::class,
            SystemStatusPageController::class,
            SystemAboutApiController::class,
            LivenessController::class,
            ApplicationReadinessController::class,
            AccountRegistrationFormController::class,
            AccountRegistrationSubmitController::class,
            AccountRegistrationAcceptedController::class,
            EmailVerificationResendFormController::class,
            EmailVerificationResendSubmitController::class,
            EmailVerificationFormController::class,
            EmailVerificationSubmitController::class,
            EmailVerificationCompletedController::class,
            LoginFormController::class,
            LoginSubmitController::class,
            LogoutController::class,
            AccountSecurityController::class,
            SessionRevocationController::class,
            DeviceRevocationController::class,
            PasswordRecoveryRequestFormController::class,
            PasswordRecoveryRequestSubmitController::class,
            PasswordRecoveryRequestAcceptedController::class,
            PasswordResetFormController::class,
            PasswordResetSubmitController::class,
            PasswordResetCompletedController::class,
            IdentityMultiFactorController::class,
            AccountWorkspacesController::class,
            WorkspaceSwitchController::class,
            WorkspaceClearController::class,
            CurrentWorkspaceController::class,
            PrivilegedAccessController::class,
            AccountStateSecurityController::class,
            SecurityAuditViewerController::class,
            NigeriaGeographyDirectoryController::class,
            NigeriaGeographyAreaController::class,
            GeographyChildrenLookupController::class,
            PeopleProfilesController::class,
            OrganizationsRegistryController::class,
            OrganizationAffiliationsController::class,
            PeopleIdentityResolutionController::class,
            QuranReleaseGovernanceController::class,
            QuranPublicReferenceController::class,
            QuranSearchCorpusIntegrityController::class,
            CompetitionP6WorkflowController::class,
            CompetitionPublicResultsController::class,
        ];
        $context->service(ServiceDefinition::factory(
            RouteCollection::class,
            self::ID,
            $controllers,
            new ClosureServiceFactory(function (DependencyResolver $resolver) use ($controllers): RouteCollection {
                $factory = require $this->projectRoot . '/routes/web.php';
                if (!$factory instanceof Closure) {
                    throw new RuntimeException('Production route definitions must return a factory closure.');
                }
                $services = array_map(
                    static fn (string $class): object => ServiceReference::get($resolver, $class),
                    $controllers,
                );
                $routes = $factory(...$services);
                if (!$routes instanceof RouteCollection) {
                    throw new RuntimeException('Production route factory returned an invalid route collection.');
                }

                return $routes;
            }),
        ));
        $context->service(ServiceDefinition::factory(
            RouteSecurityVerifier::class,
            self::ID,
            [ProductionRouteSecurityPolicyCatalog::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): RouteSecurityVerifier =>
                new RouteSecurityVerifier(ServiceReference::get($resolver, ProductionRouteSecurityPolicyCatalog::class))),
        ));
        $context->service(ServiceDefinition::factory(
            RouteSecurityVerifyConsoleCommand::class,
            self::ID,
            [RouteCollection::class, RouteSecurityVerifier::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): RouteSecurityVerifyConsoleCommand =>
                new RouteSecurityVerifyConsoleCommand(
                    ServiceReference::get($resolver, RouteCollection::class),
                    ServiceReference::get($resolver, RouteSecurityVerifier::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            Router::class,
            self::ID,
            [RouteCollection::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): Router =>
                new Router(ServiceReference::get($resolver, RouteCollection::class))),
        ));
        $context->service(ServiceDefinition::factory(
            RoutingRequestHandler::class,
            self::ID,
            [Router::class, ControllerDispatcher::class, ProblemDetailsResponseFactory::class, Psr17Factory::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): RoutingRequestHandler =>
                new RoutingRequestHandler(
                    ServiceReference::get($resolver, Router::class),
                    ServiceReference::get($resolver, ControllerDispatcher::class),
                    ServiceReference::get($resolver, ProblemDetailsResponseFactory::class),
                    ServiceReference::get($resolver, Psr17Factory::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            HttpKernel::class,
            self::ID,
            [CorrelationIdMiddleware::class, CspNonceMiddleware::class, SecurityHeadersMiddleware::class,
                HttpRequestLoggingMiddleware::class, ExceptionHandlingMiddleware::class,
                RequestTargetValidationMiddleware::class, LocaleMiddleware::class,
                SessionAuthenticationMiddleware::class, PrivilegedAccessContextMiddleware::class, TenantContextMiddleware::class,
                RoutingRequestHandler::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): HttpKernel => new HttpKernel(
                [
                    ServiceReference::get($resolver, CorrelationIdMiddleware::class),
                    ServiceReference::get($resolver, CspNonceMiddleware::class),
                    ServiceReference::get($resolver, SecurityHeadersMiddleware::class),
                    ServiceReference::get($resolver, HttpRequestLoggingMiddleware::class),
                    ServiceReference::get($resolver, ExceptionHandlingMiddleware::class),
                    ServiceReference::get($resolver, RequestTargetValidationMiddleware::class),
                    ServiceReference::get($resolver, LocaleMiddleware::class),
                    ServiceReference::get($resolver, SessionAuthenticationMiddleware::class),
                    ServiceReference::get($resolver, PrivilegedAccessContextMiddleware::class),
                    ServiceReference::get($resolver, TenantContextMiddleware::class),
                ],
                ServiceReference::get($resolver, RoutingRequestHandler::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            HttpRuntime::class,
            self::ID,
            [NativeServerRequestFactory::class, HttpKernel::class, ResponseEmitter::class, ErrorHandlingRuntime::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): HttpRuntime => new HttpRuntime(
                ServiceReference::get($resolver, NativeServerRequestFactory::class),
                ServiceReference::get($resolver, HttpKernel::class),
                ServiceReference::get($resolver, ResponseEmitter::class),
                ServiceReference::get($resolver, ErrorHandlingRuntime::class),
            )),
        ));
    }
}
