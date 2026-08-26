<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Qmdb\Tools\Support\FileSystem;
$failures = [];
$count = 0;
foreach (FileSystem::files(QMDB_PROJECT_ROOT) as $file) {
    $relative = FileSystem::relative(QMDB_PROJECT_ROOT, $file);
    if (!str_ends_with(strtolower($relative), '.php')) {
        continue;
    }
    $excluded = '#(^|/)(\.git|\.runtime|\.build|\.phpstan\.cache|\.phpunit\.cache'
        . '|vendor|node_modules|build|reports)(/|$)#';
    if (preg_match($excluded, $relative) === 1) {
        continue;
    }
    $count++;
    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        $failures[] = $relative . ': unreadable';
        continue;
    }
    try {
        $tokens = token_get_all($contents, TOKEN_PARSE);
        if ($tokens === []) {
            $failures[] = $relative . ': tokenization returned no tokens';
        }
    } catch (ParseError $error) {
        $failures[] = $relative . ': ' . $error->getMessage();
    }
}
foreach ($failures as $failure) {
    fwrite(STDERR, "ERROR: {$failure}\n");
}
printf("PHP syntax: %s (%d files)\n", $failures === [] ? 'PASS' : 'FAIL', $count);
exit($failures === [] ? 0 : 1);
