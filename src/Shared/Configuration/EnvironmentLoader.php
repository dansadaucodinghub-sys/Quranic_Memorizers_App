<?php

declare(strict_types=1);

namespace Qmdb\Shared\Configuration;

interface EnvironmentLoader
{
    public function load(string $projectRoot): LoadedEnvironment;
}
