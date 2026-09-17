<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaDelivery\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\MediaCatalog\Application\MediaEvidenceRepository;
use Qmdb\Modules\MediaIngestion\Application\MediaBlobStore;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceAuthorizationScope;
use Qmdb\Modules\TenancyContext\Application\Exception\TenantContextRequiredException;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;

/** Tenant-private, approval/consent/hold-gated binary delivery with ETag and one bounded Range. */
final readonly class PrivateMediaDeliveryController implements Controller
{
    public function __construct(private AuthenticatedRequestGuard $authentication, private TenantContextRequiredGuard $tenant, private AuthorizationRequirementGuard $authorization, private MediaEvidenceRepository $assets, private MediaBlobStore $storage, private Psr17Factory $responses) {}
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor=$this->authentication->context($request);if($actor===null)return $this->authentication->rejection($request);
        try{$tenant=$this->tenant->require($request,true);$this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor),new PermissionCode('workspace.media.view'),new WorkspaceAuthorizationScope($tenant)));$parameters=$request->getAttribute(RouteAttributes::PARAMETERS);if(!is_array($parameters)||!is_string($parameters['assetId']??null))throw new \InvalidArgumentException();$record=$this->assets->findDeliverable($tenant->workspaceInternalId,UuidV7::fromString($parameters['assetId']));if($record===null)return $this->responses->createResponse(404)->withHeader('Cache-Control','private, no-store');$etag='"'.bin2hex($record['sha256']).'"';if(trim($request->getHeaderLine('If-None-Match'))===$etag)return $this->responses->createResponse(304)->withHeader('ETag',$etag)->withHeader('Cache-Control','private, no-store');$contents=$this->storage->get($record['storage_key']);if(strlen($contents)!==$record['byte_size']||!hash_equals($record['sha256'],hash('sha256',$contents,true)))throw new \RuntimeException('Private media integrity verification failed.');return $this->deliver($request,$contents,$record['mime_type'],$etag);
        }catch(\Qmdb\Modules\SecurityAuthorization\Application\Exception\AuthorizationDeniedException){return $this->responses->createResponse(403)->withHeader('Cache-Control','private, no-store');}catch(TenantContextRequiredException){return $this->responses->createResponse(303)->withHeader('Location','/account/workspaces?context_required=1')->withHeader('Cache-Control','private, no-store');}catch(\InvalidArgumentException){return $this->responses->createResponse(404)->withHeader('Cache-Control','private, no-store');}
    }
    private function deliver(ServerRequestInterface $request,string $contents,string $mime,string $etag):ResponseInterface{$size=strlen($contents);$start=0;$end=$size-1;$status=200;$range=$request->getHeaderLine('Range');if($range!==''){if(preg_match('/\Abytes=(\d+)-(\d*)\z/',$range,$matches)!==1)return $this->responses->createResponse(416)->withHeader('Content-Range','bytes */'.$size)->withHeader('Cache-Control','private, no-store');$start=(int)$matches[1];$end=$matches[2]===''?$end:(int)$matches[2];if($start>$end||$start>=$size)return $this->responses->createResponse(416)->withHeader('Content-Range','bytes */'.$size)->withHeader('Cache-Control','private, no-store');$end=min($end,$size-1);$status=206;}$body=substr($contents,$start,$end-$start+1);$response=$this->responses->createResponse($status)->withHeader('Content-Type',$mime)->withHeader('Content-Length',(string)strlen($body))->withHeader('Accept-Ranges','bytes')->withHeader('ETag',$etag)->withHeader('Cache-Control','private, no-store')->withHeader('X-Content-Type-Options','nosniff');if($status===206)$response=$response->withHeader('Content-Range','bytes '.$start.'-'.$end.'/'.$size);$response->getBody()->write($body);return$response;}
}
