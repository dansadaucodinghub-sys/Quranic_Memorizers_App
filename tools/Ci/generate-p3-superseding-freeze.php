<?php

declare(strict_types=1);

use Qmdb\Tools\Closeout\P3SupersedingFreezeGenerator;

require dirname(__DIR__) . '/bootstrap.php';

$result = (new P3SupersedingFreezeGenerator())->generate(QMDB_PROJECT_ROOT);
printf("P3 superseding freeze: %s (%d files, %s)\n", $result['status'], $result['files'], $result['sha256']);
