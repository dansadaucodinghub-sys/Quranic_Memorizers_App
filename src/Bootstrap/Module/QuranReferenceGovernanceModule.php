<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\QuranReferenceGovernance\Interface\Console\P4DecompositionVerifyConsoleCommand;
use Qmdb\Modules\QuranReferenceGovernance\Interface\Console\QuranGovernanceVerifyConsoleCommand;
use Qmdb\Modules\QuranReferenceGovernance\Interface\Console\QuranSourcesVerifyConsoleCommand;
use Qmdb\Modules\QuranReferenceGovernance\Interface\Console\QuranSourceArtifactRegisterConsoleCommand;
use Qmdb\Modules\QuranReferenceGovernance\Domain\QuranSourceArtifactPathGuard;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class QuranReferenceGovernanceModule implements Module
{
    public function __construct(private string $projectRoot) {}
    public function id(): ModuleId { return new ModuleId('quran.reference_governance'); }
    public function dependencies(): array { return [new ModuleId('foundation.core'),new ModuleId('foundation.application'),new ModuleId('foundation.observability'),new ModuleId('foundation.database'),new ModuleId('foundation.schema'),new ModuleId('security.web'),new ModuleId('security.authorization'),new ModuleId('security.audit'),new ModuleId('identity.accounts'),new ModuleId('identity.sessions'),new ModuleId('identity.multifactor')]; }
    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(P4DecompositionVerifyConsoleCommand::class, 'quran.reference_governance', [], new ClosureServiceFactory(fn(DependencyResolver $r) => new P4DecompositionVerifyConsoleCommand($this->projectRoot))));
        $context->service(ServiceDefinition::factory(QuranSourcesVerifyConsoleCommand::class, 'quran.reference_governance', [DatabaseConnectionProvider::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranSourcesVerifyConsoleCommand(ServiceReference::get($r, DatabaseConnectionProvider::class)))));
        $context->service(ServiceDefinition::factory(QuranGovernanceVerifyConsoleCommand::class, 'quran.reference_governance', [DatabaseConnectionProvider::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranGovernanceVerifyConsoleCommand(ServiceReference::get($r, DatabaseConnectionProvider::class)))));
        $context->service(ServiceDefinition::instance(QuranSourceArtifactPathGuard::class, 'quran.reference_governance', new QuranSourceArtifactPathGuard()));
        $context->service(ServiceDefinition::factory(QuranSourceArtifactRegisterConsoleCommand::class, 'quran.reference_governance', [DatabaseConnectionProvider::class, QuranSourceArtifactPathGuard::class], new ClosureServiceFactory(fn(DependencyResolver $r) => new QuranSourceArtifactRegisterConsoleCommand($this->projectRoot . '/resources/quran-source-artifacts', ServiceReference::get($r, DatabaseConnectionProvider::class), ServiceReference::get($r, QuranSourceArtifactPathGuard::class)))));
    }
}
