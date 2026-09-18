<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaDelivery\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\MediaCatalog\Application\MediaEvidenceRepository;
use Qmdb\Modules\MediaDelivery\Domain\MediaRange;
use Qmdb\Modules\MediaIngestion\Application\MediaBlobStore;
use Qmdb\Modules\SecurityAuthorization\Application\{AuthorizationRequest, AuthorizationRequirementGuard, AuthorizationSubject};
use Qmdb\Modules\SecurityAuthorization\Application\Exception\AuthorizationDeniedException;
use Qmdb\Modules\SecurityAuthorization\Domain\{PermissionCode, WorkspaceAuthorizationScope};
use Qmdb\Modules\TenancyContext\Application\Exception\TenantContextRequiredException;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;

/** Private delivery rechecks authorization and revocable policy on every request, including 304s. */
final readonly class PrivateMediaDeliveryController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $authentication,
        private TenantContextRequiredGuard $tenant,
        private AuthorizationRequirementGuard $authorization,
        private MediaEvidenceRepository $assets,
        private MediaBlobStore $storage,
        private Psr17Factory $responses,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = $this->authentication->context($request);
        if ($actor === null) {
            return $this->authentication->rejection($request);
        }
        try {
            $tenant = $this->tenant->require($request, $request->hasHeader('X-QMDB-Tenant-Context-Version'));
            if ($actor->accountInternalId !== $tenant->accountInternalId || $actor->sessionInternalId !== $tenant->sessionInternalId) {
                return $this->response(403);
            }
            $this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor), new PermissionCode('workspace.media.view'), new WorkspaceAuthorizationScope($tenant)));
            $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
            if (!is_array($parameters) || !is_string($parameters['assetId'] ?? null)) {
                throw new \InvalidArgumentException('Missing media identifier.');
            }
            $record = $this->assets->findDeliverable($tenant->workspaceInternalId, UuidV7::fromString($parameters['assetId']));
            if ($record === null) {
                return $this->response(404);
            }
            if ($record['byte_size'] < 1 || $record['byte_size'] >= 16_777_216 || !in_array($record['mime_type'], ['audio/mpeg', 'video/mp4'], true)) {
                throw new \RuntimeException('Media delivery representation is invalid.');
            }
            $contents = $this->storage->get($record['storage_key']);
            if (strlen($contents) !== $record['byte_size'] || !hash_equals($record['sha256'], hash('sha256', $contents, true))) {
                throw new \RuntimeException('Private media integrity verification failed.');
            }
            $etag = '"' . bin2hex($record['sha256']) . '"';
            if (trim($request->getHeaderLine('If-None-Match')) === $etag) {
                return $this->response(304)->withHeader('ETag', $etag);
            }
            return $this->deliver($request, $contents, $record['mime_type'], $etag);
        } catch (AuthorizationDeniedException) {
            return $this->response(403);
        } catch (TenantContextRequiredException) {
            return $this->response(303)->withHeader('Location', '/account/workspaces?context_required=1');
        } catch (\InvalidArgumentException) {
            return $this->response(404);
        }
    }

    private function deliver(ServerRequestInterface $request, string $contents, string $mime, string $etag): ResponseInterface
    {
        $size = strlen($contents);
        $header = $request->getHeaderLine('Range');
        if ($request->hasHeader('If-Range') && $request->getHeaderLine('If-Range') !== $etag) {
            $header = '';
        }
        try {
            $range = MediaRange::fromHeader($header, $size);
        } catch (\InvalidArgumentException) {
            return $this->response(416)->withHeader('Content-Range', 'bytes */' . $size);
        }
        $body = $range === null ? $contents : substr($contents, $range->start, $range->length());
        $response = $this->response($range === null ? 200 : 206)
            ->withHeader('Content-Type', $mime)
            ->withHeader('Content-Disposition', 'inline')
            ->withHeader('Content-Length', (string) strlen($body))
            ->withHeader('Accept-Ranges', 'bytes')
            ->withHeader('ETag', $etag);
        if ($range !== null) {
            $response = $response->withHeader('Content-Range', 'bytes ' . $range->start . '-' . $range->end . '/' . $size);
        }
        $response->getBody()->write($body);
        return $response;
    }

    private function response(int $status): ResponseInterface
    {
        return $this->responses->createResponse($status)
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }
}
