<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support;

use Qmdb\Bootstrap\Application;
use Qmdb\Bootstrap\ApplicationFactory;
use Qmdb\Shared\Configuration\ApplicationConfigurationFactory;
use Qmdb\Shared\Configuration\Infrastructure\DotenvEnvironmentLoader;

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
        return (new ApplicationFactory(
            projectRoot: dirname(__DIR__, 3),
            environmentLoader: new DotenvEnvironmentLoader($variables),
            configurationFactory: new ApplicationConfigurationFactory(),
        ))->create('8.5.0', ['json', 'mbstring']);
    }
}
