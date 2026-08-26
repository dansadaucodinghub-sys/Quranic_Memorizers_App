<?php

declare(strict_types=1);

use Qmdb\Bootstrap\ApplicationFactory;
use Qmdb\Bootstrap\Error\BootstrapSupport;
use Qmdb\Bootstrap\RuntimeRequirements;
use Qmdb\Shared\Configuration\ConfigurationException;

$root = dirname(__DIR__);
require_once $root . '/src/Bootstrap/Error/BootstrapSupport.php';
$fallback = BootstrapSupport::failureResponder();
$failure = null;
$emitted = false;

try {
    if (!(new RuntimeRequirements())->evaluateCurrentRuntime()->isSatisfied()) {
        $failure = $fallback->create('runtime_requirements');
    } else {
        require_once $root . '/vendor/autoload.php';
        ApplicationFactory::fromCurrentProcess($root)->createHttpRuntime()->run();
        $emitted = true;
    }
} catch (ConfigurationException) {
    $failure = $fallback->create('configuration');
} catch (Throwable) {
    $failure = $fallback->create('unexpected_throwable');
}

if (!$emitted && $failure !== null && !headers_sent()) {
    header_remove('X-Powered-By');
    http_response_code($failure->status());

    foreach ($failure->headers() as $name => $value) {
        header(sprintf('%s: %s', $name, $value), true);
    }

    echo $failure->body();
}
