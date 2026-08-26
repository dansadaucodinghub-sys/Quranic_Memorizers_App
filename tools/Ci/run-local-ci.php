<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Qmdb\Tools\Ci\LocalCiRunner;

$rawArguments = is_array($_SERVER['argv'] ?? null) ? $_SERVER['argv'] : [];
$arguments = array_values(array_filter($rawArguments, is_string(...)));
$hosted = in_array('--ci', array_slice($arguments, 1), true) || getenv('CI') === 'true';
exit((new LocalCiRunner(QMDB_PROJECT_ROOT))->run($hosted));
