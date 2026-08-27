<?php

declare(strict_types=1);

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
use Qmdb\Modules\IdentityMultiFactor\Interface\Http\IdentityMultiFactorController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordRecoveryRequestAcceptedController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordRecoveryRequestFormController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordRecoveryRequestSubmitController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordResetCompletedController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordResetFormController;
use Qmdb\Modules\IdentityRecovery\Interface\Http\PasswordResetSubmitController;
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
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
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
                SessionAuthenticationMiddleware::class, RoutingRequestHandler::class],
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
