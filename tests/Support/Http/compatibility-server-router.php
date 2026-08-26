<?php

declare(strict_types=1);

// Diagnostic-only HTTP router for the PHP 8.2 host. Production uses public/index.php
// and retains the PHP 8.5 fail-closed runtime gate.
$root = dirname(__DIR__, 3);
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url(is_string($requestUri) ? $requestUri : '/', PHP_URL_PATH);
if (is_string($path)) {
    $asset = realpath($root . '/public' . $path);
    $public = realpath($root . '/public');
    $publicPrefix = $public === false ? null : rtrim($public, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if ($asset !== false && $publicPrefix !== null && str_starts_with($asset, $publicPrefix) && is_file($asset)) {
        return false;
    }
}

require $root . '/tests/compat-bootstrap.php';

$factory = new Qmdb\Bootstrap\ApplicationFactory(
    projectRoot: $root,
    environmentLoader: new Qmdb\Shared\Configuration\Infrastructure\DotenvEnvironmentLoader([
        'APP_ENV' => 'test',
        'APP_DEBUG' => 'false',
        'APP_TIMEZONE' => 'UTC',
        'APP_LOG_LEVEL' => 'emergency',
    ]),
    configurationFactory: new Qmdb\Shared\Configuration\ApplicationConfigurationFactory(),
);
$factory->createHttpRuntime('8.5.0', ['json', 'mbstring'])->run();
