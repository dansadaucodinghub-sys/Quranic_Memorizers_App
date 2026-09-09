<?php

declare(strict_types=1);

use Qmdb\Tools\Closeout\P3GovernanceRefreshFreezeGenerator;

require dirname(__DIR__) . '/bootstrap.php';

$result = (new P3GovernanceRefreshFreezeGenerator())->generate(QMDB_PROJECT_ROOT);
printf("P3 governance-refresh freeze: %s (%d files, %s)\n", $result['status'], $result['files'], $result['sha256']);
