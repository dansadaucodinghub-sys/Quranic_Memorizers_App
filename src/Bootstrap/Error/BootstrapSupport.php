<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Error;

use Qmdb\Shared\Identifier\SecureRandomRuntimeIdentifierGenerator;
use Qmdb\Shared\Observability\Correlation\SecureCorrelationIdGenerator;
use Qmdb\Shared\Observability\Error\BootstrapFailureReporter;
use Qmdb\Shared\Observability\Error\BootstrapFailureResponder;
use Qmdb\Shared\Observability\Error\InternalErrorChannel;

final class BootstrapSupport
{
    private function __construct()
    {
    }

    public static function failureResponder(): BootstrapFailureResponder
    {
        self::loadFoundation();

        return new BootstrapFailureResponder(
            new SecureCorrelationIdGenerator(new SecureRandomRuntimeIdentifierGenerator()),
            new BootstrapFailureReporter(new InternalErrorChannel()),
        );
    }

    private static function loadFoundation(): void
    {
        $root = dirname(__DIR__, 3);
        $bootstrapFiles = [
            '/src/Bootstrap/Shared/ExitCode.php',
            '/src/Bootstrap/RuntimeViolation.php',
            '/src/Bootstrap/RuntimeRequirementResult.php',
            '/src/Bootstrap/RuntimeRequirements.php',
            '/src/Shared/Identifier/RuntimeIdentifier.php',
            '/src/Shared/Identifier/RuntimeIdentifierGenerator.php',
            '/src/Shared/Identifier/SecureRandomRuntimeIdentifierGenerator.php',
            '/src/Shared/Observability/Correlation/CorrelationId.php',
            '/src/Shared/Observability/Correlation/CorrelationIdGenerator.php',
            '/src/Shared/Observability/Correlation/SecureCorrelationIdGenerator.php',
            '/src/Shared/Observability/Error/InternalErrorChannel.php',
            '/src/Shared/Observability/Error/BootstrapFailureReporter.php',
            '/src/Shared/Observability/Error/BootstrapFailureResponse.php',
            '/src/Shared/Observability/Error/BootstrapFailureResponder.php',
        ];

        foreach ($bootstrapFiles as $bootstrapFile) {
            require_once $root . $bootstrapFile;
        }
    }
}
