<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranReleaseLifecycleService;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranReleaseTransitionCommand;
use Qmdb\Modules\QuranReferenceGovernance\Domain\QuranReleaseAction;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class QuranReleaseGovernanceController implements Controller
{
    public function __construct(private AuthenticatedRequestGuard $authentication, private AuthorizationRequirementGuard $authorization, private IdentityCsrf $csrf, private IdentityAccessView $view, private Psr17Factory $responses, private DatabaseConnectionProvider $connections, private QuranReleaseLifecycleService $lifecycle) {}
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor=$this->authentication->context($request); if($actor===null) return $this->authentication->rejection($request);
        $route=$request->getAttribute(RouteAttributes::NAME); if(!is_string($route)) return $this->responses->createResponse(404);
        try {
            $permission=$route==='platform.quran.releases.index'?'platform.quran_releases.view':($route==='platform.quran.releases.detail'?'platform.quran_releases.view':QuranReleaseAction::from($this->actionName($route))->permission());
            $this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor),new PermissionCode($permission),new PlatformAuthorizationScope()));
            if($route==='platform.quran.releases.index') return $this->index($request);
            $id=$this->parameter($request,'releaseId'); UuidV7::fromString($id); $release=$this->release($id); if($release===null) return $this->responses->createResponse(404)->withHeader('Cache-Control','private, no-store');
            if($route==='platform.quran.releases.detail') return $this->detail($request,$release);
            $action=QuranReleaseAction::from($this->actionName($route)); if(strtoupper($request->getMethod())==='GET') return $this->form($request,$release,$action);
            return $this->submit($request,$actor,$release,$action);
        } catch(\Qmdb\Modules\SecurityAuthorization\Application\Exception\AuthorizationDeniedException) { return $this->responses->createResponse(403)->withHeader('Cache-Control','private, no-store'); }
        catch(\InvalidArgumentException) { return $this->responses->createResponse(422)->withHeader('Cache-Control','private, no-store'); }
    }
    private function index(ServerRequestInterface $request): ResponseInterface { $rows=$this->connections->connection()->query('SELECT BIN_TO_UUID(public_id) public_id, release_code, release_version, status, version, created_at FROM quran_reference_releases ORDER BY created_at DESC,id DESC LIMIT 50')->fetchAll(\PDO::FETCH_ASSOC); return $this->view->render($request,'pages.quran-release-index','fragments.quran-release-list',new ViewData(['releases'=>is_array($rows)?$rows:[]]),'Qur’an release governance')->withHeader('Cache-Control','private, no-store'); }
    private function detail(ServerRequestInterface $request,array $release): ResponseInterface { return $this->view->render($request,'pages.quran-release-detail','fragments.quran-release-detail',new ViewData(['release'=>$release]),'Qur’an release governance')->withHeader('Cache-Control','private, no-store'); }
    private function form(ServerRequestInterface $request,array $release,QuranReleaseAction $action): ResponseInterface { $csrf=$this->csrf->issue($request,$this->csrfAction($action)); return $this->view->render($request,'pages.quran-release-transition','fragments.quran-release-transition-form',new ViewData(['release'=>$release,'action'=>$action->value,'csrf_token'=>$csrf['token'],'submission_id'=>UuidV7::generate()->toString()]),'Qur’an release transition',cookie:$csrf['cookie'])->withHeader('Cache-Control','private, no-store'); }
    private function submit(ServerRequestInterface $request,\Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext $actor,array $release,QuranReleaseAction $action): ResponseInterface { if(strtolower(trim(explode(';',$request->getHeaderLine('Content-Type'))[0]))!=='application/x-www-form-urlencoded') return $this->responses->createResponse(415); $body=$request->getParsedBody(); if(!is_array($body)) return $this->responses->createResponse(422); $csrf=$this->csrf->issue($request,$this->csrfAction($action)); $token=$body['csrf_token']??null; if(!is_string($token)||!$this->csrf->validates($request,$this->csrfAction($action),$csrf['cookie'],$token)) return $this->responses->createResponse(403)->withHeader('Cache-Control','private, no-store'); try{$this->lifecycle->transition(new QuranReleaseTransitionCommand($actor,UuidV7::fromString((string)$release['public_id']),(int)$release['version'],UuidV7::fromString($this->field($body,'submission_id',36)),$action,$this->optional($body,'reason_code',96),null));}catch(\DomainException $e){return $this->responses->createResponse(str_contains($e->getMessage(),'temporarily')?429:409)->withHeader('Cache-Control','private, no-store');} return $this->view->redirect('/platform/quran/releases/'.rawurlencode((string)$release['public_id']),$csrf['cookie'])->withHeader('Cache-Control','private, no-store'); }
    private function release(string $id): ?array { $s=$this->connections->connection()->prepare('SELECT BIN_TO_UUID(public_id) public_id,release_code,release_version,status,version,created_at,updated_at FROM quran_reference_releases WHERE public_id=UUID_TO_BIN(:id)'); $s->execute([':id'=>$id]); $r=$s->fetch(\PDO::FETCH_ASSOC); return is_array($r)?$r:null; }
    private function parameter(ServerRequestInterface $r,string $name): string { $p=$r->getAttribute(RouteAttributes::PARAMETERS); if(!is_array($p)||!is_string($p[$name]??null))throw new \InvalidArgumentException(); return $p[$name]; }
    private function actionName(string $route): string { foreach(QuranReleaseAction::cases() as $a) if($route==='platform.quran.releases.'.strtolower($a->value)) return $a->value; throw new \InvalidArgumentException(); }
    private function csrfAction(QuranReleaseAction $a): CsrfAction { return match($a){QuranReleaseAction::STAGE=>CsrfAction::QURAN_RELEASE_STAGE,QuranReleaseAction::VALIDATE=>CsrfAction::QURAN_RELEASE_VALIDATE,QuranReleaseAction::APPROVE=>CsrfAction::QURAN_RELEASE_APPROVE,QuranReleaseAction::ACTIVATE=>CsrfAction::QURAN_RELEASE_ACTIVATE,QuranReleaseAction::REJECT=>CsrfAction::QURAN_RELEASE_REJECT}; }
    private function field(array $b,string $n,int $m): string { $v=$b[$n]??null; if(!is_string($v)||$v===''||strlen($v)>$m)throw new \InvalidArgumentException(); return $v; }
    private function optional(array $b,string $n,int $m): ?string { $v=$b[$n]??null; if($v===null||$v==='')return null; return $this->field($b,$n,$m); }
}
