<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Logging;

use DateTimeZone;
use InvalidArgumentException;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Qmdb\Shared\Configuration\Logging\LoggingConfiguration;

final readonly class MonologStructuredLoggerFactory implements StructuredLoggerFactory
{
    /** @param resource|null $testStream */
    public function __construct(
        private LoggingConfiguration $configuration,
        private StaticApplicationContextProcessor $processor,
        private mixed $testStream = null,
    ) {
        if ($testStream !== null && !is_resource($testStream)) {
            throw new InvalidArgumentException('Test logging stream must be a resource.');
        }
    }

    public function create(): LoggerInterface
    {
        $handler = new StreamHandler(
            $this->testStream ?? 'php://stderr',
            $this->configuration->minimumLevel()->toMonologLevel(),
            true,
        );
        $handler->setFormatter(new JsonFormatter(
            JsonFormatter::BATCH_MODE_JSON,
            true,
            false,
            false,
        ));

        $logger = new Logger(LoggingConfiguration::CHANNEL, [$handler]);
        $logger->setTimezone(new DateTimeZone(LoggingConfiguration::TIMEZONE));
        $logger->pushProcessor($this->processor);

        return $logger;
    }
}
