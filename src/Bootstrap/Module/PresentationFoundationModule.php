<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Nyholm\Psr7\Factory\Psr17Factory;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Localization\LocaleResolver;
use Qmdb\Shared\Localization\SupportedLocales;
use Qmdb\Shared\Localization\TranslationCatalog;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Qmdb\Shared\Presentation\Asset\AssetUrlGenerator;
use Qmdb\Shared\Presentation\Html\HtmlEscaper;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;
use Qmdb\Shared\Presentation\Response\FragmentResponseFactory;
use Qmdb\Shared\Presentation\Response\HtmlResponseFactory;
use Qmdb\Shared\Presentation\Response\PageOrFragmentResponseFactory;
use Qmdb\Shared\Presentation\Security\CspNonceGenerator;
use Qmdb\Shared\Presentation\Security\SecureCspNonceGenerator;
use Qmdb\Shared\Presentation\View\PageRenderer;
use Qmdb\Shared\Presentation\View\PhpViewRenderer;
use Qmdb\Shared\Presentation\View\PresentationRequestContext;
use Qmdb\Shared\Presentation\View\ViewRegistry;

final readonly class PresentationFoundationModule implements Module
{
    private const ID = 'foundation.presentation';

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
            new ModuleId('foundation.core'),
            new ModuleId('foundation.application'),
            new ModuleId('foundation.observability'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $root = $this->projectRoot;
        $context->service(ServiceDefinition::instance(SupportedLocales::class, self::ID, new SupportedLocales()));
        $context->service(ServiceDefinition::factory(
            LocaleResolver::class,
            self::ID,
            [SupportedLocales::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): LocaleResolver =>
                new LocaleResolver(ServiceReference::get($resolver, SupportedLocales::class))),
        ));
        $context->service(ServiceDefinition::factory(
            TranslationCatalog::class,
            self::ID,
            [],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): TranslationCatalog =>
                TranslationCatalog::fromFiles([
                    'en' => $root . '/resources/translations/en.php',
                    'ar' => $root . '/resources/translations/ar.php',
                ])),
        ));
        $context->service(ServiceDefinition::instance(HtmlEscaper::class, self::ID, new HtmlEscaper()));
        $context->service(ServiceDefinition::instance(AssetUrlGenerator::class, self::ID, new AssetUrlGenerator()));
        $context->service(ServiceDefinition::factory(
            ViewRegistry::class,
            self::ID,
            [],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): ViewRegistry =>
                new ViewRegistry([
                    'layouts.application' => $root . '/resources/views/layouts/application.php',
                    'pages.home' => $root . '/resources/views/pages/home.php',
                    'pages.system-about' => $root . '/resources/views/pages/system-about.php',
                    'pages.system-status' => $root . '/resources/views/pages/system-status.php',
                    'fragments.system-about-dialog' => $root . '/resources/views/fragments/system-about-dialog.php',
                    'fragments.system-status-card' => $root . '/resources/views/fragments/system-status-card.php',
                    'components.application-header' => $root . '/resources/views/components/application-header.php',
                    'components.application-footer' => $root . '/resources/views/components/application-footer.php',
                    'components.language-switcher' => $root . '/resources/views/components/language-switcher.php',
                    'components.theme-switcher' => $root . '/resources/views/components/theme-switcher.php',
                    'components.status-badge' => $root . '/resources/views/components/status-badge.php',
                    'components.modal-shell' => $root . '/resources/views/components/modal-shell.php',
                    'components.live-region' => $root . '/resources/views/components/live-region.php',
                ])),
        ));
        $context->service(ServiceDefinition::factory(
            PhpViewRenderer::class,
            self::ID,
            [ViewRegistry::class, HtmlEscaper::class, AssetUrlGenerator::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): PhpViewRenderer => new PhpViewRenderer(
                ServiceReference::get($resolver, ViewRegistry::class),
                ServiceReference::get($resolver, HtmlEscaper::class),
                ServiceReference::get($resolver, AssetUrlGenerator::class),
            )),
        ));
        $context->service(ServiceDefinition::factory(
            PageRenderer::class,
            self::ID,
            [PhpViewRenderer::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): PageRenderer =>
                new PageRenderer(ServiceReference::get($resolver, PhpViewRenderer::class))),
        ));
        $context->service(ServiceDefinition::factory(
            PresentationRequestContext::class,
            self::ID,
            [TranslationCatalog::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): PresentationRequestContext =>
                new PresentationRequestContext(ServiceReference::get($resolver, TranslationCatalog::class))),
        ));
        $context->service(ServiceDefinition::instance(
            CspNonceGenerator::class,
            self::ID,
            new SecureCspNonceGenerator(),
        ));
        $context->service(ServiceDefinition::instance(
            FragmentRequestDetector::class,
            self::ID,
            new FragmentRequestDetector(),
        ));
        $context->service(ServiceDefinition::factory(
            HtmlResponseFactory::class,
            self::ID,
            [],
            new ClosureServiceFactory(static function (DependencyResolver $resolver): HtmlResponseFactory {
                $factory = new Psr17Factory();
                return new HtmlResponseFactory($factory, $factory);
            }),
        ));
        $context->service(ServiceDefinition::factory(
            FragmentResponseFactory::class,
            self::ID,
            [],
            new ClosureServiceFactory(static function (DependencyResolver $resolver): FragmentResponseFactory {
                $factory = new Psr17Factory();
                return new FragmentResponseFactory($factory, $factory);
            }),
        ));
        $context->service(ServiceDefinition::factory(
            PageOrFragmentResponseFactory::class,
            self::ID,
            [FragmentRequestDetector::class, HtmlResponseFactory::class, FragmentResponseFactory::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): PageOrFragmentResponseFactory =>
                new PageOrFragmentResponseFactory(
                    ServiceReference::get($resolver, FragmentRequestDetector::class),
                    ServiceReference::get($resolver, HtmlResponseFactory::class),
                    ServiceReference::get($resolver, FragmentResponseFactory::class),
                )),
        ));
    }
}
