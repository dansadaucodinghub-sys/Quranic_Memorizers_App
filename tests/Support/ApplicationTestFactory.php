<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support;

use Qmdb\Bootstrap\Application;
use Qmdb\Bootstrap\ApplicationMetadata;
use Qmdb\Bootstrap\RuntimeRequirements;
use Qmdb\Shared\Configuration\ApplicationConfigurationFactory;
use Qmdb\Shared\Configuration\ConfigurationSource;
use Qmdb\Shared\Configuration\EnvironmentVariables;

final readonly class ApplicationTestFactory
{
    /** @param array<string, string> $variables */
    public static function create(array $variables = []): Application
    {
        $variables = array_replace(
            [
                'APP_ENV' => 'test',
                'APP_DEBUG' => 'false',
                'APP_TIMEZONE' => 'UTC',
            ],
            $variables,
        );
        $configuration = (new ApplicationConfigurationFactory())->create(
            new EnvironmentVariables($variables),
            ConfigurationSource::PROCESS,
        );

        return new Application(
            metadata: ApplicationMetadata::current(),
            runtimeRequirements: new RuntimeRequirements(),
            configuration: $configuration,
        );
    }
}
