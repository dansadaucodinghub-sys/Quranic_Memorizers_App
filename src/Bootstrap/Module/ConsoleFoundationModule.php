<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Bootstrap\Application;
use Qmdb\Bootstrap\Console\ConsoleApplication;
use Qmdb\Bootstrap\Console\P2SecurityHardeningVerifyConsoleCommand;
use Qmdb\Bootstrap\Security\P2SecurityHardeningVerifier;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditControlVerifier;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationCatalogVerifier;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessSchemaVerifier;
use Qmdb\Modules\TenancyContext\Application\TenantContextSchemaVerifier;
use Qmdb\Modules\TenancyContext\Application\TenantRepositorySecurityVerifier;
use Qmdb\Shared\Background\Console\ScheduleListConsoleCommand;
use Qmdb\Shared\Background\Console\ScheduleRunConsoleCommand;
use Qmdb\Shared\Background\Console\WorkerRunConsoleCommand;
use Qmdb\Shared\Console\Command\AppAboutConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandDispatcher;
use Qmdb\Shared\Console\Command\ConsoleCommandMap;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Command\ConsoleCommandRegistry;
use Qmdb\Shared\Console\Command\SchemaConsoleCommand;
use Qmdb\Shared\Console\Input\ConsoleInputParser;
use Qmdb\Shared\Console\Observability\ConsoleExecutionObserver;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Http\Routing\RouteCollection;
use Qmdb\Shared\Http\Routing\Security\RouteSecurityVerifier;
use Qmdb\Shared\Http\Routing\Security\RouteSecurityVerifyConsoleCommand;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Qmdb\Shared\Observability\Error\ErrorHandlingRuntime;
use Qmdb\Shared\Schema\Console\SchemaConsoleApplication;
use Qmdb\Modules\SecurityAuthorization\Interface\Console\AuthorizationVerifyConsoleCommand;
use Qmdb\Modules\TenancyContext\Interface\Console\TenantContextVerifyConsoleCommand;
use Qmdb\Modules\TenancyContext\Interface\Console\TenantRepositorySecurityVerifyConsoleCommand;
use Qmdb\Modules\SecurityPrivilegedAccess\Interface\Console\PrivilegedAccessVerifyConsoleCommand;
use Qmdb\Modules\SecurityAudit\Interface\Console\SecurityAuditCheckpointConsoleCommand;
use Qmdb\Modules\SecurityAudit\Interface\Console\SecurityAuditVerifyConsoleCommand;
use Qmdb\Modules\Geography\Interface\Console\GeographyReferenceVerifyConsoleCommand;
use Qmdb\Modules\People\Interface\Console\PeopleProfilesVerifyConsoleCommand;

final readonly class ConsoleFoundationModule implements Module
{
    private const ID = 'foundation.console';

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
            new ModuleId('foundation.schema'),
            new ModuleId('foundation.background'),
            new ModuleId('security.authorization'),
            new ModuleId('tenancy.context'),
            new ModuleId('security.privileged_access'),
            new ModuleId('security.audit'),
            new ModuleId('application.http'),
            new ModuleId('reference.geography'),
            new ModuleId('people.profiles'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(
            ConsoleInputParser::class,
            self::ID,
            new ConsoleInputParser(),
        ));
        $context->service(ServiceDefinition::factory(
            AppAboutConsoleCommand::class,
            self::ID,
            [Application::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): AppAboutConsoleCommand =>
                new AppAboutConsoleCommand(ServiceReference::get($resolver, Application::class))),
        ));
        $context->service(ServiceDefinition::factory(
            P2SecurityHardeningVerifier::class,
            self::ID,
            [
                AuthorizationCatalogVerifier::class,
                TenantContextSchemaVerifier::class,
                TenantRepositorySecurityVerifier::class,
                PrivilegedAccessSchemaVerifier::class,
                SecurityAuditControlVerifier::class,
                RouteSecurityVerifier::class,
                RouteCollection::class,
            ],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): P2SecurityHardeningVerifier =>
                new P2SecurityHardeningVerifier(
                    ServiceReference::get($resolver, AuthorizationCatalogVerifier::class),
                    ServiceReference::get($resolver, TenantContextSchemaVerifier::class),
                    ServiceReference::get($resolver, TenantRepositorySecurityVerifier::class),
                    ServiceReference::get($resolver, PrivilegedAccessSchemaVerifier::class),
                    ServiceReference::get($resolver, SecurityAuditControlVerifier::class),
                    ServiceReference::get($resolver, RouteSecurityVerifier::class),
                    ServiceReference::get($resolver, RouteCollection::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            P2SecurityHardeningVerifyConsoleCommand::class,
            self::ID,
            [P2SecurityHardeningVerifier::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): P2SecurityHardeningVerifyConsoleCommand =>
                new P2SecurityHardeningVerifyConsoleCommand(
                    ServiceReference::get($resolver, P2SecurityHardeningVerifier::class),
                )),
        ));
        $this->registerCommandMap($context);
        $context->service(ServiceDefinition::factory(
            ConsoleCommandDispatcher::class,
            self::ID,
            [ConsoleCommandMap::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): ConsoleCommandDispatcher =>
                new ConsoleCommandDispatcher(ServiceReference::get($resolver, ConsoleCommandMap::class))),
        ));
        $context->service(ServiceDefinition::factory(
            ConsoleApplication::class,
            self::ID,
            [
                Application::class,
                ConsoleInputParser::class,
                ConsoleCommandMap::class,
                ConsoleCommandDispatcher::class,
                ConsoleExecutionObserver::class,
                ErrorHandlingRuntime::class,
            ],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): ConsoleApplication =>
                new ConsoleApplication(
                    application: ServiceReference::get($resolver, Application::class),
                    executionObserver: ServiceReference::get($resolver, ConsoleExecutionObserver::class),
                    errorHandlingRuntime: ServiceReference::get($resolver, ErrorHandlingRuntime::class),
                    inputParser: ServiceReference::get($resolver, ConsoleInputParser::class),
                    commands: ServiceReference::get($resolver, ConsoleCommandMap::class),
                    dispatcher: ServiceReference::get($resolver, ConsoleCommandDispatcher::class),
                )),
        ));
    }

    private function registerCommandMap(ModuleRegistrationContext $context): void
    {
        $dependencies = [
            AppAboutConsoleCommand::class,
            SchemaConsoleApplication::class,
            ScheduleListConsoleCommand::class,
            ScheduleRunConsoleCommand::class,
            WorkerRunConsoleCommand::class,
            AuthorizationVerifyConsoleCommand::class,
            TenantContextVerifyConsoleCommand::class,
            TenantRepositorySecurityVerifyConsoleCommand::class,
            PrivilegedAccessVerifyConsoleCommand::class,
            SecurityAuditVerifyConsoleCommand::class,
            RouteSecurityVerifyConsoleCommand::class,
            P2SecurityHardeningVerifyConsoleCommand::class,
            SecurityAuditCheckpointConsoleCommand::class,
            GeographyReferenceVerifyConsoleCommand::class,
            PeopleProfilesVerifyConsoleCommand::class,
        ];
        $context->service(ServiceDefinition::factory(
            ConsoleCommandMap::class,
            self::ID,
            $dependencies,
            new ClosureServiceFactory(static function (DependencyResolver $resolver): ConsoleCommandMap {
                $schema = ServiceReference::get($resolver, SchemaConsoleApplication::class);
                $registry = new ConsoleCommandRegistry();
                $registry->register(ServiceReference::get($resolver, AppAboutConsoleCommand::class));
                foreach (self::schemaCommands() as $name => [$description, $options]) {
                    $registry->register(new SchemaConsoleCommand(
                        new ConsoleCommandName($name),
                        $description,
                        $schema,
                        $options,
                    ));
                }
                $registry->register(ServiceReference::get($resolver, ScheduleListConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, ScheduleRunConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, WorkerRunConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, AuthorizationVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, TenantContextVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, TenantRepositorySecurityVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, PrivilegedAccessVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, SecurityAuditVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, RouteSecurityVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, P2SecurityHardeningVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, SecurityAuditCheckpointConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, GeographyReferenceVerifyConsoleCommand::class));
                $registry->register(ServiceReference::get($resolver, PeopleProfilesVerifyConsoleCommand::class));

                return $registry->build();
            }),
        ));
    }

    /** @return array<string, array{string, list<string>}> */
    private static function schemaCommands(): array
    {
        return [
            'db:schema:install' => ['Install and verify schema ledger metadata.', []],
            'db:schema:verify' => ['Verify schema metadata and migration state.', []],
            'db:migrate:plan' => ['Display the deterministic migration plan.', []],
            'db:migrate' => ['Apply pending explicitly registered migrations.', []],
            'db:migrate:status' => ['Display deterministic migration status.', []],
            'db:migrate:rollback' => [
                'Roll back one confirmed migration in local/test only.',
                ['migration', 'confirm'],
            ],
            'db:seed' => ['Apply pending explicitly registered seeds.', []],
            'db:seed:status' => ['Display deterministic seed status.', []],
        ];
    }
}
