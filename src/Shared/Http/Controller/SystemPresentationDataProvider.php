<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Controller;

use Qmdb\Shared\Application\Query\QueryBus;
use Qmdb\Shared\Application\System\GetSystemInformation;
use Qmdb\Shared\Application\System\SystemInformation;
use Qmdb\Shared\Database\Health\DatabaseHealthCheck;
use Qmdb\Shared\Schema\Health\SchemaHealthCheck;
use UnexpectedValueException;

final readonly class SystemPresentationDataProvider
{
    public function __construct(
        private QueryBus $queryBus,
        private DatabaseHealthCheck $databaseHealth,
        private SchemaHealthCheck $schemaHealth,
    ) {
    }

    /** @return array{application: string, name: string, phase: string, batch: string, baseline: string} */
    public function information(): array
    {
        $result = $this->queryBus->ask(new GetSystemInformation());
        if (!$result instanceof SystemInformation) {
            throw new UnexpectedValueException('System-information query returned an invalid result.');
        }

        return [
            'application' => $result->applicationCode(),
            'name' => $result->applicationName(),
            'phase' => $result->currentPhase(),
            'batch' => $result->currentBatch(),
            'baseline' => $result->frozenBaseline(),
        ];
    }

    /** @return array{application: bool, database: bool, schema: bool} */
    public function status(): array
    {
        return [
            'application' => true,
            'database' => $this->databaseHealth->check()->isReady(),
            'schema' => $this->schemaHealth->check()->isReady(),
        ];
    }
}
