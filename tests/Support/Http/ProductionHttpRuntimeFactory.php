<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Http;

use Qmdb\Bootstrap\ApplicationFactory;
use Qmdb\Bootstrap\Http\HttpRuntime;
use Qmdb\Shared\Configuration\ApplicationConfigurationFactory;
use Qmdb\Shared\Configuration\Infrastructure\DotenvEnvironmentLoader;

final class ProductionHttpRuntimeFactory
{
    private function __construct()
    {
    }

    public static function create(): HttpRuntime
    {
        return (new ApplicationFactory(
            projectRoot: dirname(__DIR__, 3),
            environmentLoader: new DotenvEnvironmentLoader([
                'APP_ENV' => 'test',
                'APP_DEBUG' => 'false',
                'APP_TIMEZONE' => 'UTC',
                'APP_LOG_LEVEL' => 'emergency',
            ]),
            configurationFactory: new ApplicationConfigurationFactory(),
        ))->createHttpRuntime('8.5.0', ['json', 'mbstring']);
    }
}
