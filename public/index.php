<?php

declare(strict_types=1);

use Qmdb\Bootstrap\Application;
use Qmdb\Bootstrap\Http\BootstrapHttpApplication;
use Qmdb\Bootstrap\RuntimeRequirements;

$statusCode = 500;
$headers = [
    'Content-Type' => 'application/json; charset=utf-8',
    'Cache-Control' => 'no-store',
    'X-Content-Type-Options' => 'nosniff',
];
$body = '{"application":"QMDB","status":"error","code":"BOOTSTRAP_FAILURE"}';

try {
    require_once dirname(__DIR__) . '/src/Bootstrap/RuntimeViolation.php';
    require_once dirname(__DIR__) . '/src/Bootstrap/RuntimeRequirementResult.php';
    require_once dirname(__DIR__) . '/src/Bootstrap/RuntimeRequirements.php';

    if (!(new RuntimeRequirements())->evaluateCurrentRuntime()->isSatisfied()) {
        error_log('QMDB bootstrap failure [runtime requirements].');
    } else {
        require_once dirname(__DIR__) . '/vendor/autoload.php';

        $response = (new BootstrapHttpApplication(Application::bootstrap()))->handle();
        $statusCode = $response->statusCode();
        $headers = $response->headers();
        $body = $response->body();
    }
} catch (Throwable $throwable) {
    error_log(sprintf('QMDB bootstrap failure [%s].', get_debug_type($throwable)));
}

http_response_code($statusCode);

foreach ($headers as $name => $value) {
    header(sprintf('%s: %s', $name, $value));
}

echo $body;
