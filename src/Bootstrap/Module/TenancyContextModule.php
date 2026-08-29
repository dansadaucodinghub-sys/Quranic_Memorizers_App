<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Closure;
use Nyholm\Psr7\Factory\Psr17Factory;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\TenancyContext\Application\AccountWorkspaceInventoryHandler;
use Qmdb\Modules\TenancyContext\Application\Background\TenantBoundBackgroundContextRepository;
use Qmdb\Modules\TenancyContext\Application\Background\TenantBoundBackgroundJobContextResolver;
use Qmdb\Modules\TenancyContext\Application\Cache\TenantScopedCacheKeyFactory;
use Qmdb\Modules\TenancyContext\Application\TenantContextReadinessCheck;
use Qmdb\Modules\TenancyContext\Application\TenantContextFreshnessValidator;
use Qmdb\Modules\TenancyContext\Application\SessionTenantContextResolver;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Modules\TenancyContext\Application\TenantContextSchemaVerifier;
use Qmdb\Modules\TenancyContext\Application\WorkspaceContextClearingService;
use Qmdb\Modules\TenancyContext\Application\WorkspaceContextSelectionService;
use Qmdb\Modules\TenancyContext\Domain\Repository\SessionTenantContextRepository;
use Qmdb\Modules\TenancyContext\Infrastructure\Persistence\MySqlSessionTenantContextRepository;
use Qmdb\Modules\TenancyContext\Infrastructure\Persistence\MySqlTenantBoundBackgroundContextRepository;
use Qmdb\Modules\TenancyContext\Infrastructure\Persistence\MySqlTenantContextSchemaVerifier;
use Qmdb\Modules\TenancyContext\Interface\Console\TenantContextVerifyConsoleCommand;
use Qmdb\Modules\TenancyContext\Interface\Http\AccountWorkspacesController;
use Qmdb\Modules\TenancyContext\Interface\Http\CurrentWorkspaceController;
use Qmdb\Modules\TenancyContext\Interface\Http\TenantContextFormInput;
use Qmdb\Modules\TenancyContext\Interface\Http\TenantContextMiddleware;
use Qmdb\Modules\TenancyContext\Interface\Http\WorkspaceClearController;
use Qmdb\Modules\TenancyContext\Interface\Http\WorkspaceSwitchController;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;
use Qmdb\Shared\Time\Clock;

final readonly class TenancyContextModule implements Module
{
    private const string ID = 'tenancy.context';

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
            new ModuleId('foundation.database'),
            new ModuleId('foundation.schema'),
            new ModuleId('foundation.background'),
            new ModuleId('foundation.presentation'),
            new ModuleId('foundation.http'),
            new ModuleId('security.web'),
            new ModuleId('identity.accounts'),
            new ModuleId('identity.access'),
            new ModuleId('identity.sessions'),
            new ModuleId('security.authorization'),
            new ModuleId('tenancy.workspaces'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $this->factory(
            $context,
            MySqlSessionTenantContextRepository::class,
            [DatabaseConnectionProvider::class],
            static fn (DependencyResolver $r): MySqlSessionTenantContextRepository =>
            new MySqlSessionTenantContextRepository(ServiceReference::get($r, DatabaseConnectionProvider::class))
        );
        $context->alias(SessionTenantContextRepository::class, MySqlSessionTenantContextRepository::class);
        $this->factory(
            $context,
            SessionTenantContextResolver::class,
            [SessionTenantContextRepository::class, TransactionManager::class, Clock::class, EventLogger::class],
            static fn (DependencyResolver $r): SessionTenantContextResolver => new SessionTenantContextResolver(
                ServiceReference::get($r, SessionTenantContextRepository::class),
                ServiceReference::get($r, TransactionManager::class),
                ServiceReference::get($r, Clock::class),
                ServiceReference::get($r, EventLogger::class),
            )
        );
        $this->factory(
            $context,
            AccountWorkspaceInventoryHandler::class,
            [SessionTenantContextResolver::class, SessionTenantContextRepository::class],
            static fn (DependencyResolver $r): AccountWorkspaceInventoryHandler => new AccountWorkspaceInventoryHandler(
                ServiceReference::get($r, SessionTenantContextResolver::class),
                ServiceReference::get($r, SessionTenantContextRepository::class),
            )
        );
        $this->factory(
            $context,
            WorkspaceContextSelectionService::class,
            [SessionTenantContextRepository::class, TransactionManager::class, Clock::class, EventLogger::class],
            static fn (DependencyResolver $r): WorkspaceContextSelectionService => new WorkspaceContextSelectionService(
                ServiceReference::get($r, SessionTenantContextRepository::class),
                ServiceReference::get($r, TransactionManager::class),
                ServiceReference::get($r, Clock::class),
                ServiceReference::get($r, EventLogger::class),
            )
        );
        $this->factory(
            $context,
            WorkspaceContextClearingService::class,
            [SessionTenantContextRepository::class, TransactionManager::class, Clock::class, EventLogger::class],
            static fn (DependencyResolver $r): WorkspaceContextClearingService => new WorkspaceContextClearingService(
                ServiceReference::get($r, SessionTenantContextRepository::class),
                ServiceReference::get($r, TransactionManager::class),
                ServiceReference::get($r, Clock::class),
                ServiceReference::get($r, EventLogger::class),
            )
        );
        foreach (
            [
                TenantContextFormInput::class => new TenantContextFormInput(),
                TenantContextFreshnessValidator::class => new TenantContextFreshnessValidator(),
                TenantScopedCacheKeyFactory::class => new TenantScopedCacheKeyFactory(),
            ] as $id => $service
        ) {
            $context->service(ServiceDefinition::instance($id, self::ID, $service));
        }
        $this->factory(
            $context,
            TenantContextRequiredGuard::class,
            [TenantContextFreshnessValidator::class],
            static fn (DependencyResolver $r): TenantContextRequiredGuard => new TenantContextRequiredGuard(
                ServiceReference::get($r, TenantContextFreshnessValidator::class),
            )
        );
        $this->factory(
            $context,
            TenantContextMiddleware::class,
            [SessionTenantContextResolver::class],
            static fn (DependencyResolver $r): TenantContextMiddleware =>
            new TenantContextMiddleware(ServiceReference::get($r, SessionTenantContextResolver::class))
        );
        $this->factory(
            $context,
            MySqlTenantContextSchemaVerifier::class,
            [DatabaseConnectionProvider::class],
            fn (DependencyResolver $r): MySqlTenantContextSchemaVerifier => new MySqlTenantContextSchemaVerifier(
                ServiceReference::get($r, DatabaseConnectionProvider::class),
                $this->projectRoot,
            )
        );
        $context->alias(TenantContextSchemaVerifier::class, MySqlTenantContextSchemaVerifier::class);
        $this->factory(
            $context,
            TenantContextReadinessCheck::class,
            [TenantContextSchemaVerifier::class],
            static fn (DependencyResolver $r): TenantContextReadinessCheck =>
            new TenantContextReadinessCheck(ServiceReference::get($r, TenantContextSchemaVerifier::class))
        );
        $this->factory(
            $context,
            TenantContextVerifyConsoleCommand::class,
            [TenantContextSchemaVerifier::class],
            static fn (DependencyResolver $r): TenantContextVerifyConsoleCommand =>
            new TenantContextVerifyConsoleCommand(ServiceReference::get($r, TenantContextSchemaVerifier::class))
        );
        $this->factory(
            $context,
            MySqlTenantBoundBackgroundContextRepository::class,
            [DatabaseConnectionProvider::class],
            static fn (DependencyResolver $r): MySqlTenantBoundBackgroundContextRepository =>
                new MySqlTenantBoundBackgroundContextRepository(
                    ServiceReference::get($r, DatabaseConnectionProvider::class),
                )
        );
        $context->alias(
            TenantBoundBackgroundContextRepository::class,
            MySqlTenantBoundBackgroundContextRepository::class,
        );
        $this->factory(
            $context,
            TenantBoundBackgroundJobContextResolver::class,
            [TenantBoundBackgroundContextRepository::class, EventLogger::class],
            static fn (DependencyResolver $r): TenantBoundBackgroundJobContextResolver =>
                new TenantBoundBackgroundJobContextResolver(
                    ServiceReference::get($r, TenantBoundBackgroundContextRepository::class),
                    ServiceReference::get($r, EventLogger::class),
                )
        );
        $this->controllers($context);
    }

    private function controllers(ModuleRegistrationContext $context): void
    {
        $this->factory(
            $context,
            AccountWorkspacesController::class,
            [AuthenticatedRequestGuard::class, AccountWorkspaceInventoryHandler::class, IdentityCsrf::class,
                IdentityAccessView::class],
            static fn (DependencyResolver $r): AccountWorkspacesController => new AccountWorkspacesController(
                ServiceReference::get($r, AuthenticatedRequestGuard::class),
                ServiceReference::get($r, AccountWorkspaceInventoryHandler::class),
                ServiceReference::get($r, IdentityCsrf::class),
                ServiceReference::get($r, IdentityAccessView::class),
            )
        );
        $switchDependencies = [AuthenticatedRequestGuard::class, TenantContextFormInput::class,
            WorkspaceContextSelectionService::class, AccountWorkspaceInventoryHandler::class, IdentityCsrf::class,
            IdentityAccessView::class, FragmentRequestDetector::class, Psr17Factory::class];
        $this->factory(
            $context,
            WorkspaceSwitchController::class,
            $switchDependencies,
            static fn (DependencyResolver $r): WorkspaceSwitchController => new WorkspaceSwitchController(
                ServiceReference::get($r, AuthenticatedRequestGuard::class),
                ServiceReference::get($r, TenantContextFormInput::class),
                ServiceReference::get($r, WorkspaceContextSelectionService::class),
                ServiceReference::get($r, AccountWorkspaceInventoryHandler::class),
                ServiceReference::get($r, IdentityCsrf::class),
                ServiceReference::get($r, IdentityAccessView::class),
                ServiceReference::get($r, FragmentRequestDetector::class),
                ServiceReference::get($r, Psr17Factory::class),
            )
        );
        $clearDependencies = [AuthenticatedRequestGuard::class, TenantContextFormInput::class,
            WorkspaceContextClearingService::class, AccountWorkspaceInventoryHandler::class, IdentityCsrf::class,
            IdentityAccessView::class, FragmentRequestDetector::class, Psr17Factory::class];
        $this->factory(
            $context,
            WorkspaceClearController::class,
            $clearDependencies,
            static fn (DependencyResolver $r): WorkspaceClearController => new WorkspaceClearController(
                ServiceReference::get($r, AuthenticatedRequestGuard::class),
                ServiceReference::get($r, TenantContextFormInput::class),
                ServiceReference::get($r, WorkspaceContextClearingService::class),
                ServiceReference::get($r, AccountWorkspaceInventoryHandler::class),
                ServiceReference::get($r, IdentityCsrf::class),
                ServiceReference::get($r, IdentityAccessView::class),
                ServiceReference::get($r, FragmentRequestDetector::class),
                ServiceReference::get($r, Psr17Factory::class),
            )
        );
        $this->factory(
            $context,
            CurrentWorkspaceController::class,
            [AuthenticatedRequestGuard::class, TenantContextRequiredGuard::class, IdentityAccessView::class,
                FragmentRequestDetector::class, Psr17Factory::class],
            static fn (DependencyResolver $r): CurrentWorkspaceController => new CurrentWorkspaceController(
                ServiceReference::get($r, AuthenticatedRequestGuard::class),
                ServiceReference::get($r, TenantContextRequiredGuard::class),
                ServiceReference::get($r, IdentityAccessView::class),
                ServiceReference::get($r, FragmentRequestDetector::class),
                ServiceReference::get($r, Psr17Factory::class),
            )
        );
    }

    /** @param list<class-string> $dependencies @param Closure(DependencyResolver): object $factory */
    private function factory(
        ModuleRegistrationContext $context,
        string $id,
        array $dependencies,
        Closure $factory,
    ): void {
        $definition = ServiceDefinition::factory(
            $id,
            self::ID,
            $dependencies,
            new ClosureServiceFactory($factory),
        );
        $context->service($definition);
    }
}
