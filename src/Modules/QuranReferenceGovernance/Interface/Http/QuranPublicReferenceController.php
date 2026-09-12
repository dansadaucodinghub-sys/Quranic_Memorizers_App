<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Interface\Http;

use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranPublicReferenceRepository;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranSearchQueryNormalizer;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class QuranPublicReferenceController implements Controller
{
    public function __construct(private QuranPublicReferenceRepository $repository, private QuranSearchQueryNormalizer $normalizer, private IdentityRateLimiter $rateLimits, private IdentityFingerprintGenerator $fingerprints, private IdentityAccessView $views, private Psr17Factory $responses)
    {
    }
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute(RouteAttributes::NAME);
        if (!is_string($route)) {
            return $this->responses->createResponse(404);
        }
        try {
            return match ($route) {
                'quran.public.home' => $this->render($request, 'pages.quran-public-home', 'fragments.quran-public-home', ['summary' => $this->repository->home()], 'title.quran_reference'),
                'quran.public.surahs' => $this->render($request, 'pages.quran-surah-directory', 'fragments.quran-surah-directory', ['surahs' => $this->repository->surahs()], 'title.quran_reference'),
                'quran.public.surah' => $this->surah($request),
                'quran.public.ayah' => $this->ayah($request),
                'quran.public.sajdahs' => $this->render($request, 'pages.quran-sajdahs', 'fragments.quran-sajdahs', ['sajdahs' => $this->repository->sajdahs()], 'title.quran_reference'),
                'quran.public.partition' => $this->partition($request),
                'quran.public.search' => $this->search($request),
                default => $this->responses->createResponse(404),
            };
        } catch (\InvalidArgumentException) {
            return $this->responses->createResponse(422)->withHeader('Cache-Control', 'no-store');
        }
    }
    private function surah(ServerRequestInterface $request): ResponseInterface
    {
        $number = $this->number($request, 'surahNumber');
        $data = $this->repository->surah($number);
        return $data === null ? $this->responses->createResponse(404) : $this->render($request, 'pages.quran-surah-reader', 'fragments.quran-surah-reader', $data, 'title.quran_reference');
    }
    private function ayah(ServerRequestInterface $request): ResponseInterface
    {
        $data = $this->repository->ayah($this->number($request, 'surahNumber'), $this->number($request, 'ayahNumber'));
        return $data === null ? $this->responses->createResponse(404) : $this->render($request, 'pages.quran-ayah-detail', 'fragments.quran-ayah-detail', ['ayah' => $data], 'title.quran_reference');
    }
    private function partition(ServerRequestInterface $request): ResponseInterface
    {
        $type = (string)$this->parameter($request, 'type');
        if (!in_array($type, ['JUZ','HIZB','HIZB_QUARTER','MANZIL','RUKU','MUSHAF_PAGE'], true)) {
            throw new \InvalidArgumentException();
        } $number = $this->number($request, 'partitionNumber');
        return $this->render($request, 'pages.quran-partition', 'fragments.quran-partition', ['type' => $type,'number' => $number,'partitions' => $this->repository->partitions($type, $number)], 'title.quran_reference');
    }
    private function search(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams()['q'] ?? '';
        $mode = $request->getQueryParams()['mode'] ?? 'SIMPLE';
        if (!is_string($mode) || !in_array($mode, ['SIMPLE','EXACT_UTHMANI'], true)) {
            throw new \InvalidArgumentException();
        } $results = [];
        if ($query !== '') {
            $peer = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
            if (!is_string($peer)) {
                $peer = 'unknown';
            } $peerAttempt = new IdentityRateLimitAttempt(IdentityRateLimitScope::QURAN_PUBLIC_SEARCH_PEER, $this->fingerprints->generate('quran-public-search-peer', $peer), new IdentityRateLimitPolicy(60, 60, 60));
            $peerDecision = $this->rateLimits->consume([$peerAttempt], new DateTimeImmutable('now'));
            if (!$peerDecision->allowed) {
                return $this->responses->createResponse(429)->withHeader('Cache-Control', 'no-store')->withHeader('Retry-After', (string)$peerDecision->retryAfterSeconds);
            } $normalized = $this->normalizer->normalize($mode, $query);
            $queryAttempt = new IdentityRateLimitAttempt(IdentityRateLimitScope::QURAN_PUBLIC_SEARCH_QUERY, $this->fingerprints->generate('quran-public-search-query', $normalized), new IdentityRateLimitPolicy(60, 30, 60));
            $queryDecision = $this->rateLimits->consume([$queryAttempt], new DateTimeImmutable('now'));
            if (!$queryDecision->allowed) {
                return $this->responses->createResponse(429)->withHeader('Cache-Control', 'no-store')->withHeader('Retry-After', (string)$queryDecision->retryAfterSeconds);
            } $results = $this->repository->search($mode, $normalized, 50);
        }
        return $this->render($request, 'pages.quran-search', 'fragments.quran-search', ['query' => is_string($query) ? $query : '','mode' => $mode,'results' => $results], 'title.quran_reference', true);
    }
    /** @param array<string,mixed> $data */ private function render(ServerRequestInterface $request, string $page, string $fragment, array $data, string $title, bool $private = false): ResponseInterface
    {
        $response = $this->views->render($request, $page, $fragment, new ViewData($data), $title);
        $etag = '"' . hash('sha256', $request->getUri()->getPath() . '|' . http_build_query($request->getQueryParams())) . '"';
        if ($request->getHeaderLine('If-None-Match') === $etag) {
            return $this->responses->createResponse(304)->withHeader('ETag', $etag);
        } return $response->withHeader('Cache-Control', $private ? 'private, no-store' : 'public, max-age=300')->withHeader('ETag', $etag)->withHeader('Vary', 'Accept, X-Requested-With');
    }
    private function number(ServerRequestInterface $request, string $key): int
    {
        $value = $this->parameter($request, $key);
        if (!preg_match('/\A[1-9][0-9]*\z/', $value)) {
            throw new \InvalidArgumentException();
        } return (int)$value;
    }
    private function parameter(ServerRequestInterface $request, string $key): string
    {
        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
        if (!is_array($parameters) || !is_string($parameters[$key] ?? null)) {
            throw new \InvalidArgumentException();
        } return $parameters[$key];
    }
}
