<?php

declare(strict_types=1);

namespace Qmdb\Shared\Configuration;

use DateTimeZone;

final readonly class ApplicationConfiguration
{
    public function __construct(
        private ApplicationEnvironment $environment,
        private bool $debugEnabled,
        private DateTimeZone $timezone,
        private ConfigurationSource $source,
    ) {
    }

    public function environment(): ApplicationEnvironment
    {
        return $this->environment;
    }

    public function debugEnabled(): bool
    {
        return $this->debugEnabled;
    }

    public function timezone(): DateTimeZone
    {
        return $this->timezone;
    }

    public function source(): ConfigurationSource
    {
        return $this->source;
    }

    public function isProductionLike(): bool
    {
        return $this->environment->isProductionLike();
    }

    /** @return array{environment: string, debug_enabled: bool, timezone: string, source: string} */
    public function toSafeArray(): array
    {
        return [
            'environment' => $this->environment->toSafeString(),
            'debug_enabled' => $this->debugEnabled,
            'timezone' => $this->timezone->getName(),
            'source' => $this->source->toSafeDisplay(),
        ];
    }
}
