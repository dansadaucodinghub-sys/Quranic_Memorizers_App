<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Nyholm\Psr7\Factory\Psr17Factory;
use Qmdb\Modules\Geography\Application\AdministrativeAreaChildrenHandler;
use Qmdb\Modules\Geography\Application\AdministrativeAreaSearchHandler;
use Qmdb\Modules\Geography\Application\GeographyReferenceReadinessCheck;
use Qmdb\Modules\Geography\Application\GeographyReferenceReadinessProbe;
use Qmdb\Modules\Geography\Application\NigeriaGeographyDirectoryHandler;
use Qmdb\Modules\Geography\Domain\Repository\AdministrativeAreaRepository;
use Qmdb\Modules\Geography\Domain\Repository\CountryRepository;
use Qmdb\Modules\Geography\Domain\Repository\GeographyDatasetRepository;
use Qmdb\Modules\Geography\Infrastructure\Dataset\NigeriaAdministrativeGeographyDatasetLoader;
use Qmdb\Modules\Geography\Infrastructure\Dataset\NigeriaAdministrativeGeographyDatasetValidator;
use Qmdb\Modules\Geography\Infrastructure\Persistence\MySqlAdministrativeAreaRepository;
use Qmdb\Modules\Geography\Infrastructure\Persistence\MySqlCountryRepository;
use Qmdb\Modules\Geography\Infrastructure\Persistence\MySqlGeographyDatasetRepository;
use Qmdb\Modules\Geography\Infrastructure\Persistence\MySqlGeographyReferenceReadinessProbe;
use Qmdb\Modules\Geography\Interface\Console\GeographyReferenceVerifyConsoleCommand;
use Qmdb\Modules\Geography\Interface\Http\GeographyChildrenLookupController;
use Qmdb\Modules\Geography\Interface\Http\GeographyPublicResponseCache;
use Qmdb\Modules\Geography\Interface\Http\NigeriaGeographyAreaController;
use Qmdb\Modules\Geography\Interface\Http\NigeriaGeographyDirectoryController;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Http\Message\ProblemDetailsResponseFactory;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Qmdb\Shared\Presentation\Response\FragmentResponseFactory;
use Qmdb\Shared\Presentation\View\PageRenderer;
use Qmdb\Shared\Presentation\View\PhpViewRenderer;
use Qmdb\Shared\Presentation\View\PresentationRequestContext;

final readonly class GeographyReferenceModule implements Module
{
    private const ID = 'reference.geography';

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
            new ModuleId('foundation.core'), new ModuleId('foundation.application'),
            new ModuleId('foundation.observability'), new ModuleId('foundation.database'),
            new ModuleId('foundation.schema'), new ModuleId('foundation.http'), new ModuleId('foundation.presentation'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $root = $this->projectRoot;
        $context->service(ServiceDefinition::instance(
            NigeriaAdministrativeGeographyDatasetLoader::class,
            self::ID,
            new NigeriaAdministrativeGeographyDatasetLoader($root),
        ));
        $context->service(ServiceDefinition::instance(
            NigeriaAdministrativeGeographyDatasetValidator::class,
            self::ID,
            new NigeriaAdministrativeGeographyDatasetValidator(),
        ));
        $context->service(ServiceDefinition::factory(
            MySqlCountryRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): MySqlCountryRepository =>
                new MySqlCountryRepository(ServiceReference::get($r, DatabaseConnectionProvider::class))),
        ));
        $context->alias(CountryRepository::class, MySqlCountryRepository::class);
        $context->service(ServiceDefinition::factory(
            MySqlGeographyDatasetRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): MySqlGeographyDatasetRepository =>
                new MySqlGeographyDatasetRepository(ServiceReference::get($r, DatabaseConnectionProvider::class))),
        ));
        $context->alias(GeographyDatasetRepository::class, MySqlGeographyDatasetRepository::class);
        $context->service(ServiceDefinition::factory(
            MySqlAdministrativeAreaRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): MySqlAdministrativeAreaRepository =>
                new MySqlAdministrativeAreaRepository(ServiceReference::get($r, DatabaseConnectionProvider::class))),
        ));
        $context->alias(AdministrativeAreaRepository::class, MySqlAdministrativeAreaRepository::class);
        $context->service(ServiceDefinition::factory(
            MySqlGeographyReferenceReadinessProbe::class,
            self::ID,
            [DatabaseConnectionProvider::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): MySqlGeographyReferenceReadinessProbe =>
                new MySqlGeographyReferenceReadinessProbe(ServiceReference::get($r, DatabaseConnectionProvider::class))),
        ));
        $context->alias(GeographyReferenceReadinessProbe::class, MySqlGeographyReferenceReadinessProbe::class);
        $context->service(ServiceDefinition::factory(
            NigeriaGeographyDirectoryHandler::class,
            self::ID,
            [CountryRepository::class, GeographyDatasetRepository::class, AdministrativeAreaRepository::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): NigeriaGeographyDirectoryHandler =>
                new NigeriaGeographyDirectoryHandler(
                    ServiceReference::get($r, CountryRepository::class),
                    ServiceReference::get($r, GeographyDatasetRepository::class),
                    ServiceReference::get($r, AdministrativeAreaRepository::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            AdministrativeAreaChildrenHandler::class,
            self::ID,
            [AdministrativeAreaRepository::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): AdministrativeAreaChildrenHandler =>
                new AdministrativeAreaChildrenHandler(ServiceReference::get($r, AdministrativeAreaRepository::class))),
        ));
        $context->service(ServiceDefinition::factory(
            AdministrativeAreaSearchHandler::class,
            self::ID,
            [AdministrativeAreaRepository::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): AdministrativeAreaSearchHandler =>
                new AdministrativeAreaSearchHandler(ServiceReference::get($r, AdministrativeAreaRepository::class))),
        ));
        $context->service(ServiceDefinition::factory(
            GeographyReferenceReadinessCheck::class,
            self::ID,
            [GeographyReferenceReadinessProbe::class, NigeriaAdministrativeGeographyDatasetLoader::class,
                NigeriaAdministrativeGeographyDatasetValidator::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): GeographyReferenceReadinessCheck =>
                new GeographyReferenceReadinessCheck(
                    ServiceReference::get($r, GeographyReferenceReadinessProbe::class),
                    ServiceReference::get($r, NigeriaAdministrativeGeographyDatasetLoader::class),
                    ServiceReference::get($r, NigeriaAdministrativeGeographyDatasetValidator::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            GeographyReferenceVerifyConsoleCommand::class,
            self::ID,
            [NigeriaAdministrativeGeographyDatasetLoader::class, NigeriaAdministrativeGeographyDatasetValidator::class,
                GeographyReferenceReadinessCheck::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): GeographyReferenceVerifyConsoleCommand =>
                new GeographyReferenceVerifyConsoleCommand(
                    ServiceReference::get($r, NigeriaAdministrativeGeographyDatasetLoader::class),
                    ServiceReference::get($r, NigeriaAdministrativeGeographyDatasetValidator::class),
                    ServiceReference::get($r, GeographyReferenceReadinessCheck::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            GeographyPublicResponseCache::class,
            self::ID,
            [],
            new ClosureServiceFactory(static fn (DependencyResolver $r): GeographyPublicResponseCache =>
                new GeographyPublicResponseCache(new Psr17Factory())),
        ));
        $context->service(ServiceDefinition::factory(
            NigeriaGeographyDirectoryController::class,
            self::ID,
            [NigeriaGeographyDirectoryHandler::class, PresentationRequestContext::class, PageRenderer::class,
                GeographyPublicResponseCache::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): NigeriaGeographyDirectoryController =>
                new NigeriaGeographyDirectoryController(
                    ServiceReference::get($r, NigeriaGeographyDirectoryHandler::class),
                    ServiceReference::get($r, PresentationRequestContext::class),
                    ServiceReference::get($r, PageRenderer::class),
                    ServiceReference::get($r, GeographyPublicResponseCache::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            NigeriaGeographyAreaController::class,
            self::ID,
            [AdministrativeAreaRepository::class, GeographyDatasetRepository::class, PresentationRequestContext::class,
                PageRenderer::class, ProblemDetailsResponseFactory::class, GeographyPublicResponseCache::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): NigeriaGeographyAreaController =>
                new NigeriaGeographyAreaController(
                    ServiceReference::get($r, AdministrativeAreaRepository::class),
                    ServiceReference::get($r, GeographyDatasetRepository::class),
                    ServiceReference::get($r, PresentationRequestContext::class),
                    ServiceReference::get($r, PageRenderer::class),
                    ServiceReference::get($r, ProblemDetailsResponseFactory::class),
                    ServiceReference::get($r, GeographyPublicResponseCache::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            GeographyChildrenLookupController::class,
            self::ID,
            [AdministrativeAreaChildrenHandler::class, AdministrativeAreaRepository::class, GeographyDatasetRepository::class,
                PresentationRequestContext::class, PhpViewRenderer::class, FragmentResponseFactory::class,
                ProblemDetailsResponseFactory::class, GeographyPublicResponseCache::class],
            new ClosureServiceFactory(static fn (DependencyResolver $r): GeographyChildrenLookupController =>
                new GeographyChildrenLookupController(
                    ServiceReference::get($r, AdministrativeAreaChildrenHandler::class),
                    ServiceReference::get($r, AdministrativeAreaRepository::class),
                    ServiceReference::get($r, GeographyDatasetRepository::class),
                    ServiceReference::get($r, PresentationRequestContext::class),
                    ServiceReference::get($r, PhpViewRenderer::class),
                    ServiceReference::get($r, FragmentResponseFactory::class),
                    ServiceReference::get($r, ProblemDetailsResponseFactory::class),
                    ServiceReference::get($r, GeographyPublicResponseCache::class),
                )),
        ));
    }
}
