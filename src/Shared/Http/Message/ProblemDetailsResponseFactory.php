<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Message;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Shared\Http\Request\RequestContextAttributes;
use Qmdb\Shared\Observability\Correlation\CorrelationId;

final readonly class ProblemDetailsResponseFactory
{
    public function __construct(private JsonResponseFactory $jsonResponseFactory)
    {
    }

    /**
     * @param array<string, string|list<string>> $headers
     * @throws JsonException
     */
    public function create(ProblemDetails $problem, array $headers = []): ResponseInterface
    {
        return $this->jsonResponseFactory->createProblem(
            data: $problem->toArray(),
            status: $problem->status(),
            headers: $headers,
        );
    }

    /** @param array<string, string|list<string>> $headers */
    public function createForRequest(
        ProblemDetails $problem,
        ServerRequestInterface $request,
        array $headers = [],
    ): ResponseInterface {
        $correlationId = $request->getAttribute(RequestContextAttributes::REQUEST_ID);
        if ($correlationId instanceof CorrelationId) {
            $problem = $problem->withRequestId($correlationId->value());
        }

        return $this->create($problem, $headers);
    }
}
