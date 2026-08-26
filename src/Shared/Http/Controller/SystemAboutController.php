<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Shared\Application\Query\QueryBus;
use Qmdb\Shared\Application\System\GetSystemInformation;
use Qmdb\Shared\Application\System\SystemInformation;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Message\JsonResponseFactory;

final readonly class SystemAboutController implements Controller
{
    public function __construct(
        private QueryBus $queryBus,
        private JsonResponseFactory $responseFactory,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $result = $this->queryBus->ask(new GetSystemInformation());
        if (!$result instanceof SystemInformation) {
            throw new \UnexpectedValueException('System-information query returned an invalid result.');
        }

        return $this->responseFactory->create([
            'application' => $result->applicationCode(),
            'name' => $result->applicationName(),
            'status' => 'ready',
            'phase' => $result->currentPhase(),
            'batch' => $result->currentBatch(),
            'baseline' => $result->frozenBaseline(),
        ]);
    }
}
