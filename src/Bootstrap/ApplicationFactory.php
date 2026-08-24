<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap;

use Qmdb\Shared\Configuration\ApplicationConfigurationFactory;
use Qmdb\Shared\Configuration\EnvironmentLoader;
use Qmdb\Shared\Configuration\Infrastructure\DotenvEnvironmentLoader;
use RuntimeException;

final readonly class ApplicationFactory
{
    public function __construct(
        private string $projectRoot,
        private EnvironmentLoader $environmentLoader,
        private ApplicationConfigurationFactory $configurationFactory,
    ) {
    }

    public static function fromCurrentProcess(?string $projectRoot = null): self
    {
        return new self(
            projectRoot: $projectRoot ?? dirname(__DIR__, 2),
            environmentLoader: new DotenvEnvironmentLoader(),
            configurationFactory: new ApplicationConfigurationFactory(),
        );
    }

    /**
     * @param list<string>|null $loadedExtensions
     */
    public function create(
        ?string $phpVersion = null,
        ?array $loadedExtensions = null,
    ): Application {
        $loadedEnvironment = $this->environmentLoader->load($this->projectRoot);
        $configuration = $this->configurationFactory->create(
            $loadedEnvironment->variables(),
            $loadedEnvironment->source(),
        );
        $application = new Application(
            metadata: ApplicationMetadata::current(),
            runtimeRequirements: new RuntimeRequirements(),
            configuration: $configuration,
        );
        $runtimeResult = $application->validateRuntime($phpVersion, $loadedExtensions);

        if (!$runtimeResult->isSatisfied()) {
            throw new RuntimeException($runtimeResult->toCliString());
        }

        return $application;
    }
}
