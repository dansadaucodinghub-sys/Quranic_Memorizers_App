<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\CompetitionLive\Application\CompetitionLivePublicReadRepository;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;

final readonly class CompetitionPublicLiveController implements Controller
{
    public function __construct(private CompetitionLivePublicReadRepository $snapshots, private Psr17Factory $responses)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
        if (!is_array($parameters) || !is_string($parameters['editionSlug'] ?? null)) {
            return $this->responses->createResponse(404);
        }
        try {
            $snapshot = $this->snapshots->currentSnapshot($parameters['editionSlug']);
        } catch (\Throwable) {
            return $this->responses->createResponse(404)->withHeader('Cache-Control', 'public, max-age=30');
        }
        if ($snapshot === null) {
            return $this->responses->createResponse(404)->withHeader('Cache-Control', 'public, max-age=30');
        }
        $etag = '"' . $snapshot['checksum'] . '"';
        if (trim($request->getHeaderLine('If-None-Match')) === $etag) {
            return $this->responses->createResponse(304)->withHeader('ETag', $etag)->withHeader('Cache-Control', 'public, max-age=15, stale-while-revalidate=30');
        }
        $path = $request->getUri()->getPath();
        if (str_ends_with($path, '/snapshot')) {
            return $this->json($snapshot, $etag);
        }
        if (str_ends_with($path, '/stream')) {
            return $this->sse($parameters['editionSlug'], $request, $snapshot);
        }
        $payload = json_encode($snapshot['payload'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $html = '<main class="shell public-reference" data-qmdb-live="poll"><h1>Live competition</h1><p id="live-status" aria-live="polite">Live updates are available.</p><pre id="live-snapshot" dir="auto">' . htmlspecialchars($payload, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre><p><a href="' . htmlspecialchars($path . '/snapshot', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Refresh live snapshot</a></p></main>';
        $response = $this->responses->createResponse(200)->withHeader('Content-Type', 'text/html; charset=utf-8')->withHeader('ETag', $etag)->withHeader('Cache-Control', 'public, max-age=15, stale-while-revalidate=30');
        $response->getBody()->write($html);

        return $response;
    }

    /** @param array{sequence:int,payload:array<string,mixed>,checksum:string} $snapshot */
    private function json(array $snapshot, string $etag): ResponseInterface
    {
        $response = $this->responses->createResponse(200)->withHeader('Content-Type', 'application/json; charset=utf-8')->withHeader('ETag', $etag)->withHeader('Cache-Control', 'public, max-age=15, stale-while-revalidate=30');
        $response->getBody()->write(json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $response;
    }

    /** @param array{sequence:int,payload:array<string,mixed>,checksum:string} $snapshot */
    private function sse(string $editionSlug, ServerRequestInterface $request, array $snapshot): ResponseInterface
    {
        $lastEventId = $this->lastEventId($request);
        if ($lastEventId === null) {
            return $this->responses->createResponse(400)->withHeader('Cache-Control', 'no-store')->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }
        try {
            $history = $this->snapshots->snapshotsAfter($editionSlug, $lastEventId, 25);
        } catch (\Throwable) {
            return $this->responses->createResponse(404)->withHeader('Cache-Control', 'no-store');
        }
        $response = $this->responses->createResponse(200)->withHeader('Content-Type', 'text/event-stream; charset=utf-8')->withHeader('Cache-Control', 'no-store')->withHeader('X-Accel-Buffering', 'no');
        $body = "retry: 5000\n: heartbeat\n\n";
        if ($lastEventId > $snapshot['sequence']) {
            $body .= $this->sseEvent('reset', $snapshot);
        } elseif ($history === []) {
            $body .= ": heartbeat; no newer public snapshot\n\n";
        } else {
            foreach ($history as $event) {
                $body .= $this->sseEvent('snapshot', $event);
            }
        }
        $response->getBody()->write($body . ': bounded stream complete; reconnect with Last-Event-ID' . "\n\n");

        return $response;
    }

    private function lastEventId(ServerRequestInterface $request): ?int
    {
        $value = trim($request->getHeaderLine('Last-Event-ID'));
        if ($value === '') {
            return 0;
        }
        if (preg_match('/\A(?:0|[1-9][0-9]{0,18})\z/', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }

    /** @param array{sequence:int,payload:array<string,mixed>,checksum:string} $snapshot */
    private function sseEvent(string $event, array $snapshot): string
    {
        return 'id: ' . $snapshot['sequence'] . "\nevent: {$event}\ndata: " . json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    }
}
