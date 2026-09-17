<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaIngestion\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\MediaIngestion\Application\AuthorizedMediaUploadService;
use Qmdb\Modules\MediaIngestion\Domain\MediaUploadPolicy;
use Qmdb\Modules\SecurityAuthorization\Application\Exception\AuthorizationDeniedException;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Modules\TenancyContext\Application\Exception\TenantContextRequiredException;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Identifier\UuidV7;

/** Private no-store P9 upload UI. The server, never the form, sets lifecycle state. */
final readonly class MediaUploadController implements Controller
{
    public function __construct(private AuthenticatedRequestGuard $authentication, private TenantContextRequiredGuard $tenant, private IdentityCsrf $csrf, private AuthorizedMediaUploadService $uploads, private Psr17Factory $responses) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor=$this->authentication->context($request); if($actor===null)return $this->authentication->rejection($request);
        try {$tenant=$this->tenant->require($request,true);} catch(TenantContextRequiredException) { return $this->redirect('/account/workspaces?context_required=1'); }
        $csrf=$this->csrf->issue($request,CsrfAction::MEDIA_UPLOAD);
        if(strtoupper($request->getMethod())==='GET')return $this->form($csrf['token'],$csrf['cookie']->setCookieHeader);
        $body=$request->getParsedBody();
        if(!is_array($body)||!is_string($body['csrf_token']??null)||!$this->csrf->validates($request,CsrfAction::MEDIA_UPLOAD,$csrf['cookie'],$body['csrf_token']))return $this->response(403,'Request verification failed.',$csrf['cookie']->setCookieHeader);
        try {
            $submission=UuidV7::fromString($this->field($body,'submission_id',36));
            $purpose=$this->field($body,'purpose_code',48); $kind=$this->field($body,'media_kind',16);
            $upload=$request->getUploadedFiles()['media_file']??null; if(!$upload instanceof UploadedFileInterface||$upload->getError()!==UPLOAD_ERR_OK)throw new \InvalidArgumentException('Media file upload is unavailable.');
            $name=$upload->getClientFilename(); if(!is_string($name))throw new \InvalidArgumentException('Media filename is unavailable.');
            $contents=$this->boundedContents($upload);
            $result=$this->uploads->upload($actor,$tenant,$submission,$purpose,$kind,$name,$contents);
        } catch(AuthorizationDeniedException) { return $this->response(403,'You are not permitted to upload media.',$csrf['cookie']->setCookieHeader); }
        catch(\InvalidArgumentException) { return $this->response(422,'Media upload request is invalid.',$csrf['cookie']->setCookieHeader); }
        catch(\DomainException $error) { return $this->response(str_contains($error->getMessage(),'temporarily')?429:409,'Media upload could not be completed.',$csrf['cookie']->setCookieHeader); }
        return $this->redirect('/workspace/media/uploads?asset='.rawurlencode($result['asset_id']),$csrf['cookie']->setCookieHeader);
    }

    private function form(string $token, ?string $cookie): ResponseInterface
    {
        $submission=UuidV7::generate()->toString();
        $html='<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Private media upload</title></head><body><main><h1>Private media upload</h1><p id="upload-help">Uploaded media is private, quarantined, scanned, and reviewed before any controlled delivery.</p><form method="post" action="/workspace/media/uploads" enctype="multipart/form-data" aria-describedby="upload-help"><input type="hidden" name="csrf_token" value="'.$this->escape($token).'"><input type="hidden" name="submission_id" value="'.$this->escape($submission).'"><div><label for="purpose">Purpose code</label><input id="purpose" name="purpose_code" pattern="[A-Z][A-Z0-9_]{1,47}" required aria-describedby="purpose-help"><p id="purpose-help">Use the approved evidence purpose for this upload.</p></div><div><label for="kind">Media kind</label><select id="kind" name="media_kind" required><option value="AUDIO">Audio</option><option value="VIDEO">Video</option><option value="IMAGE">Image</option></select></div><div><label for="media-file">Media file</label><input id="media-file" name="media_file" type="file" required></div><button type="submit">Upload for quarantine</button></form></main></body></html>';
        return $this->response(200,$html,$cookie,true);
    }
    /** @param array<array-key,mixed> $body */ private function field(array $body,string $name,int $limit):string{$value=$body[$name]??null;if(!is_string($value)||$value===''||strlen($value)>$limit)throw new \InvalidArgumentException('Media field is invalid.');return $value;}
    private function boundedContents(UploadedFileInterface $upload): string { $stream=$upload->getStream(); $contents=''; $limit=min(MediaUploadPolicy::MAX_UPLOAD_BYTES,16_777_216); while(!$stream->eof()){ $chunk=$stream->read(8192); if($chunk==='')break; $contents.=$chunk; if(strlen($contents)>$limit)throw new \InvalidArgumentException('Media file exceeds the direct-upload limit. Use the resumable upload workflow.'); } return $contents; }
    private function response(int $status,string $body,?string $cookie=null,bool $html=false):ResponseInterface{$response=$this->responses->createResponse($status)->withHeader('Cache-Control','private, no-store')->withHeader('Content-Type',$html?'text/html; charset=utf-8':'text/plain; charset=utf-8');if($cookie!==null)$response=$response->withAddedHeader('Set-Cookie',$cookie);$response->getBody()->write($body);return$response;}
    private function redirect(string $location,?string $cookie=null):ResponseInterface{$response=$this->responses->createResponse(303)->withHeader('Location',$location)->withHeader('Cache-Control','private, no-store');return$cookie===null?$response:$response->withAddedHeader('Set-Cookie',$cookie);}
    private function escape(string $value):string{return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
}
