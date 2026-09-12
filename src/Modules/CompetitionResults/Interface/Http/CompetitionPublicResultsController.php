<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\CompetitionResults\Application\CompetitionP6RuntimeRepository;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;

final readonly class CompetitionPublicResultsController implements Controller
{
    public function __construct(private CompetitionP6RuntimeRepository $results, private Psr17Factory $responses)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
        if (!is_array($parameters) || !is_string($parameters['editionSlug'] ?? null)) {
            return $this->responses->createResponse(404);
        }
        $edition = $parameters['editionSlug'];
        $category = is_string($parameters['categorySlug'] ?? null) ? $parameters['categorySlug'] : null;
        $round = is_string($parameters['roundCode'] ?? null) ? $parameters['roundCode'] : null;
        try {
            $records = $this->results->publicResults($edition, $category, $round, 200);
        } catch (\InvalidArgumentException) {
            return $this->responses->createResponse(404);
        }
        $etag = '"' . hash('sha256', json_encode($records, JSON_THROW_ON_ERROR)) . '"';
        if (trim($request->getHeaderLine('If-None-Match')) === $etag) {
            return $this->responses->createResponse(304)->withHeader('ETag', $etag)->withHeader('Cache-Control', 'public, max-age=300');
        }
        $html = '<main class="shell public-reference" dir="auto"><h1>Competition results</h1><table><thead><tr><th>Category</th><th>Round</th><th>Rank</th><th>Participant</th><th>Total</th></tr></thead><tbody>';
        foreach ($records as $record) {
            $html .= '<tr><td>' . $this->escape($record['category_slug']) . '</td><td>' . $this->escape($record['round_code']) . '</td><td>' . $record['rank_position'] . '</td><td>' . $this->escape($record['public_label']) . '</td><td>' . $record['total_units'] . '</td></tr>';
        }
        $html .= '</tbody></table></main>';
        $response = $this->responses->createResponse()->withHeader('Content-Type', 'text/html; charset=utf-8')->withHeader('ETag', $etag)->withHeader('Cache-Control', 'public, max-age=300');
        $response->getBody()->write($html);

        return $response;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
