<?php

declare(strict_types=1);

namespace Qmdb\Shared\Configuration;

final readonly class LoadedEnvironment
{
    public function __construct(
        private EnvironmentVariables $variables,
        private ConfigurationSource $source,
    ) {
    }

    public function variables(): EnvironmentVariables
    {
        return $this->variables;
    }

    public function source(): ConfigurationSource
    {
        return $this->source;
    }
}
