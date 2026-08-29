<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
chdir($projectRoot);

/** @param list<string> $arguments */
$run = static function (array $arguments): int {
    $escaped = [];
    foreach ([PHP_BINARY, ...$arguments] as $argument) {
        if (!is_string($argument)) {
            throw new LogicException('MySQL test command argument is invalid.');
        }
        $escaped[] = escapeshellarg($argument);
    }
    $command = implode(' ', $escaped);
    passthru($command, $exitCode);

    return $exitCode;
};

/** @var list<list<string>> $canonicalSchemaSteps */
$canonicalSchemaSteps = [
    ['tools/ci/reset-test-schema.php'],
    ['bin/console', 'db:schema:install'],
    ['bin/console', 'db:migrate'],
    ['bin/console', 'db:seed'],
    ['bin/console', 'security:authorization:verify'],
    ['bin/console', 'tenancy:context:verify'],
    ['bin/console', 'db:schema:verify'],
];

$restoreCanonicalSchema = static function () use ($run, $canonicalSchemaSteps): int {
    foreach ($canonicalSchemaSteps as $arguments) {
        $exitCode = $run($arguments);
        if ($exitCode !== 0) {
            return $exitCode;
        }
    }

    return 0;
};

$preparationExit = $restoreCanonicalSchema();
if ($preparationExit !== 0) {
    exit($preparationExit);
}

$testExit = $run([
    'vendor/bin/phpunit',
    '--configuration',
    'phpunit.xml.dist',
    '--testsuite',
    'MySQL',
]);
$restorationExit = $restoreCanonicalSchema();

exit($testExit !== 0 ? $testExit : $restorationExit);
