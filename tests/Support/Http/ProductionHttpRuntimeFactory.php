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
                'APP_PUBLIC_BASE_URL' => 'http://127.0.0.1:8080',
                'AUTH_CSRF_SIGNING_KEY' => 'test-csrf-signing-key-with-at-least-32-bytes',
                'AUTH_IDENTITY_HMAC_KEY' => 'test-identity-hmac-key-with-at-least-32-bytes',
                'AUTH_CONTACT_ENCRYPTION_KEY' => 'Y2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2M=',
                'AUTH_MFA_ENCRYPTION_KEY' => 'bW1tbW1tbW1tbW1tbW1tbW1tbW1tbW1tbW1tbW1tbW0=',
                'MAILER_DSN' => 'null://null',
                'MAIL_FROM_ADDRESS' => 'no-reply@example.test',
            ]),
            configurationFactory: new ApplicationConfigurationFactory(),
        ))->createHttpRuntime('8.5.0', ['json', 'mbstring']);
    }
}
