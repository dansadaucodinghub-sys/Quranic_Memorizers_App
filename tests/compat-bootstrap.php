<?php

declare(strict_types=1);

// Diagnostic-only bootstrap for the host's PHP 8.2 compatibility suite.
// Acceptance remains bound to Composer's normal PHP 8.5 platform check.
if (!class_exists(Composer\Autoload\ClassLoader::class, false)) {
    require_once dirname(__DIR__) . '/vendor/composer/ClassLoader.php';
}
$loader = new Composer\Autoload\ClassLoader();
$psr4 = require dirname(__DIR__) . '/vendor/composer/autoload_psr4.php';
if (!is_array($psr4)) {
    throw new RuntimeException('Composer PSR-4 map is invalid.');
}
foreach ($psr4 as $prefix => $paths) {
    if (!is_string($prefix) || (!is_string($paths) && !is_array($paths))) {
        throw new RuntimeException('Composer PSR-4 entry is invalid.');
    }
    if (is_array($paths) && array_filter($paths, 'is_string') !== $paths) {
        throw new RuntimeException('Composer PSR-4 paths are invalid.');
    }
    if (preg_match('/^(?:PHPUnit|SebastianBergmann|DeepCopy|PharIo|TheSeer)\\\\/', $prefix) === 1) {
        continue;
    }
    $loader->addPsr4($prefix, is_array($paths) ? array_values($paths) : $paths);
}
$rawClassMap = require dirname(__DIR__) . '/vendor/composer/autoload_classmap.php';
if (!is_array($rawClassMap)) {
    throw new RuntimeException('Composer class map is invalid.');
}
$classMap = [];
foreach ($rawClassMap as $class => $file) {
    if (is_string($class) && is_string($file) && str_starts_with($class, 'Qmdb\\')) {
        $classMap[$class] = $file;
    }
}
$loader->addClassMap($classMap);
$loader->register(true);

require_once dirname(__DIR__) . '/tools/bootstrap.php';

$autoloadFiles = require dirname(__DIR__) . '/vendor/composer/autoload_files.php';
if (!is_array($autoloadFiles)) {
    throw new RuntimeException('Composer files map is invalid.');
}
foreach ($autoloadFiles as $file) {
    if (!is_string($file)) {
        throw new RuntimeException('Composer autoload file path is invalid.');
    }
    if (str_contains(str_replace('\\', '/', $file), '/symfony/polyfill-')) {
        require_once $file;
    }
}
