<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Closure;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Qmdb\Bootstrap\Http\HttpRuntime;
use Qmdb\Shared\Application\Query\QueryBus;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Database\Health\DatabaseHealthCheck;
use Qmdb\Shared\Http\Controller\ControllerDispatcher;
use Qmdb\Shared\Http\Controller\LivenessController;
use Qmdb\Shared\Http\Controller\ReadinessController;
use Qmdb\Shared\Http\Controller\SystemAboutApiController;
use Qmdb\Shared\Http\Controller\SystemAboutPageController;
use Qmdb\Shared\Http\Controller\SystemHomeController;
use Qmdb\Shared\Http\Controller\SystemPresentationDataProvider;
use Qmdb\Shared\Http\Controller\SystemStatusPageController;
use Qmdb\Shared\Http\Kernel\HttpKernel;
use Qmdb\Shared\Http\Message\JsonResponseFactory;
use Qmdb\Shared\Http\Message\ProblemDetailsResponseFactory;
use Qmdb\Shared\Http\Middleware\ExceptionHandlingMiddleware;
use Qmdb\Shared\Http\Middleware\CorrelationIdMiddleware;
use Qmdb\Shared\Http\Middleware\CspNonceMiddleware;
use Qmdb\Shared\Http\Middleware\HttpRequestLoggingMiddleware;
use Qmdb\Shared\Http\Middleware\LocaleMiddleware;
use Qmdb\Shared\Http\Middleware\RequestTargetValidationMiddleware;
use Qmdb\Shared\Http\Middleware\SecurityHeadersMiddleware;
use Qmdb\Shared\Http\Request\NativeServerRequestFactory;
use Qmdb\Shared\Http\Request\RequestTargetValidator;
use Qmdb\Shared\Http\Response\ResponseEmitter;
use Qmdb\Shared\Http\Response\SapiResponseEmitter;
use Qmdb\Shared\Http\Routing\RouteCollection;
use Qmdb\Shared\Http\Routing\Router;
use Qmdb\Shared\Http\Routing\RoutingRequestHandler;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Qmdb\Shared\Schema\Health\SchemaHealthCheck;
use Qmdb\Shared\Observability\Correlation\CorrelationIdGenerator;
use Qmdb\Shared\Observability\Error\ErrorHandlingRuntime;
use Qmdb\Shared\Observability\Error\ThrowableReporter;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Localization\LocaleResolver;
use Qmdb\Shared\Presentation\Response\HtmlResponseFactory;
use Qmdb\Shared\Presentation\Response\PageOrFragmentResponseFactory;
use Qmdb\Shared\Presentation\Security\CspNonceGenerator;
use Qmdb\Shared\Presentation\View\PageRenderer;
use Qmdb\Shared\Presentation\View\PhpViewRenderer;
use Qmdb\Shared\Presentation\View\PresentationRequestContext;
use Qmdb\Shared\Time\MonotonicClock;
use RuntimeException;

final readonly class HttpFoundationModule implements Module
{
    private const ID = 'foundation.http';

    public function __construct(string $projectRoot)
    {
        unset($projectRoot);
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
            new ModuleId('foundation.presentation'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            Psr17Factory::class,
            self::ID,
            [],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): Psr17Factory => new Psr17Factory()),
        ));
        $context->service(ServiceDefinition::factory(
            ServerRequestCreator::class,
            self::ID,
            [Psr17Factory::class],
            new ClosureServiceFactory(static function (DependencyResolver $resolver): ServerRequestCreator {
                $factory = ServiceReference::get($resolver, Psr17Factory::class);

                return new ServerRequestCreator($factory, $factory, $factory, $factory);
            }),
        ));
        $context->service(ServiceDefinition::factory(
            NativeServerRequestFactory::class,
            self::ID,
            [ServerRequestCreator::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): NativeServerRequestFactory =>
                    new NativeServerRequestFactory(ServiceReference::get($resolver, ServerRequestCreator::class)),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            JsonResponseFactory::class,
            self::ID,
            [Psr17Factory::class],
            new ClosureServiceFactory(static function (DependencyResolver $resolver): JsonResponseFactory {
                $factory = ServiceReference::get($resolver, Psr17Factory::class);

                return new JsonResponseFactory($factory, $factory);
            }),
        ));
        $context->service(ServiceDefinition::factory(
            ProblemDetailsResponseFactory::class,
            self::ID,
            [JsonResponseFactory::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): ProblemDetailsResponseFactory =>
                    new ProblemDetailsResponseFactory(ServiceReference::get($resolver, JsonResponseFactory::class)),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            RequestTargetValidator::class,
            self::ID,
            [],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): RequestTargetValidator => new RequestTargetValidator(),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            SystemPresentationDataProvider::class,
            self::ID,
            [QueryBus::class, DatabaseHealthCheck::class, SchemaHealthCheck::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): SystemPresentationDataProvider =>
                new SystemPresentationDataProvider(
                    ServiceReference::get($resolver, QueryBus::class),
                    ServiceReference::get($resolver, DatabaseHealthCheck::class),
                    ServiceReference::get($resolver, SchemaHealthCheck::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            SystemHomeController::class,
            self::ID,
            [SystemPresentationDataProvider::class, PresentationRequestContext::class, PageRenderer::class,
                HtmlResponseFactory::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): SystemHomeController =>
                new SystemHomeController(
                    ServiceReference::get($resolver, SystemPresentationDataProvider::class),
                    ServiceReference::get($resolver, PresentationRequestContext::class),
                    ServiceReference::get($resolver, PageRenderer::class),
                    ServiceReference::get($resolver, HtmlResponseFactory::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            SystemAboutPageController::class,
            self::ID,
            [SystemPresentationDataProvider::class, PresentationRequestContext::class, PhpViewRenderer::class,
                PageRenderer::class, PageOrFragmentResponseFactory::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): SystemAboutPageController =>
                new SystemAboutPageController(
                    ServiceReference::get($resolver, SystemPresentationDataProvider::class),
                    ServiceReference::get($resolver, PresentationRequestContext::class),
                    ServiceReference::get($resolver, PhpViewRenderer::class),
                    ServiceReference::get($resolver, PageRenderer::class),
                    ServiceReference::get($resolver, PageOrFragmentResponseFactory::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            SystemStatusPageController::class,
            self::ID,
            [SystemPresentationDataProvider::class, PresentationRequestContext::class, PhpViewRenderer::class,
                PageRenderer::class, PageOrFragmentResponseFactory::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): SystemStatusPageController =>
                new SystemStatusPageController(
                    ServiceReference::get($resolver, SystemPresentationDataProvider::class),
                    ServiceReference::get($resolver, PresentationRequestContext::class),
                    ServiceReference::get($resolver, PhpViewRenderer::class),
                    ServiceReference::get($resolver, PageRenderer::class),
                    ServiceReference::get($resolver, PageOrFragmentResponseFactory::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            SystemAboutApiController::class,
            self::ID,
            [SystemPresentationDataProvider::class, JsonResponseFactory::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): SystemAboutApiController =>
                new SystemAboutApiController(
                    ServiceReference::get($resolver, SystemPresentationDataProvider::class),
                    ServiceReference::get($resolver, JsonResponseFactory::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            LivenessController::class,
            self::ID,
            [JsonResponseFactory::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): LivenessController =>
                    new LivenessController(ServiceReference::get($resolver, JsonResponseFactory::class)),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            ReadinessController::class,
            self::ID,
            [JsonResponseFactory::class, DatabaseHealthCheck::class, SchemaHealthCheck::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): ReadinessController =>
                    new ReadinessController(
                        ServiceReference::get($resolver, JsonResponseFactory::class),
                        ServiceReference::get($resolver, DatabaseHealthCheck::class),
                        ServiceReference::get($resolver, SchemaHealthCheck::class),
                    ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            ControllerDispatcher::class,
            self::ID,
            [],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): ControllerDispatcher => new ControllerDispatcher(),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            CorrelationIdMiddleware::class,
            self::ID,
            [CorrelationIdGenerator::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): CorrelationIdMiddleware =>
                    new CorrelationIdMiddleware(
                        ServiceReference::get($resolver, CorrelationIdGenerator::class),
                    ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            CspNonceMiddleware::class,
            self::ID,
            [CspNonceGenerator::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): CspNonceMiddleware =>
                new CspNonceMiddleware(ServiceReference::get($resolver, CspNonceGenerator::class))),
        ));
        $context->service(ServiceDefinition::factory(
            SecurityHeadersMiddleware::class,
            self::ID,
            [ApplicationConfiguration::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): SecurityHeadersMiddleware =>
                    new SecurityHeadersMiddleware(
                        ServiceReference::get($resolver, ApplicationConfiguration::class)->environment(),
                    ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            HttpRequestLoggingMiddleware::class,
            self::ID,
            [EventLogger::class, MonotonicClock::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): HttpRequestLoggingMiddleware =>
                    new HttpRequestLoggingMiddleware(
                        ServiceReference::get($resolver, EventLogger::class),
                        ServiceReference::get($resolver, MonotonicClock::class),
                    ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            ExceptionHandlingMiddleware::class,
            self::ID,
            [ProblemDetailsResponseFactory::class, ThrowableReporter::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): ExceptionHandlingMiddleware =>
                    new ExceptionHandlingMiddleware(
                        ServiceReference::get($resolver, ProblemDetailsResponseFactory::class),
                        ServiceReference::get($resolver, ThrowableReporter::class),
                    ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            RequestTargetValidationMiddleware::class,
            self::ID,
            [RequestTargetValidator::class, ProblemDetailsResponseFactory::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): RequestTargetValidationMiddleware =>
                    new RequestTargetValidationMiddleware(
                        ServiceReference::get($resolver, RequestTargetValidator::class),
                        ServiceReference::get($resolver, ProblemDetailsResponseFactory::class),
                    ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            LocaleMiddleware::class,
            self::ID,
            [LocaleResolver::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): LocaleMiddleware =>
                new LocaleMiddleware(ServiceReference::get($resolver, LocaleResolver::class))),
        ));
        $context->service(ServiceDefinition::factory(
            ResponseEmitter::class,
            self::ID,
            [],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): SapiResponseEmitter => new SapiResponseEmitter(),
            ),
        ));
    }
}
