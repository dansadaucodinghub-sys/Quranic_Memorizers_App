<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranReleaseGovernanceReadRepository;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranReleaseLifecycleService;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranReleaseTransitionCommand;
use Qmdb\Modules\QuranReferenceGovernance\Domain\QuranReleaseAction;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\SecurityAuthorization\Application\Exception\AuthorizationDeniedException;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Presentation\View\ViewData;

/** @phpstan-type ReleaseDetail array{public_id:string,release_code:string,release_version:string,status:string,version:int,created_at:string,updated_at:string} */
final readonly class QuranReleaseGovernanceController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $authentication,
        private AuthorizationRequirementGuard $authorization,
        private IdentityCsrf $csrf,
        private IdentityAccessView $view,
        private Psr17Factory $responses,
        private QuranReleaseGovernanceReadRepository $releases,
        private QuranReleaseLifecycleService $lifecycle,
    ) {
    }
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = $this->authentication->context($request);
        if ($actor === null) {
            return $this->authentication->rejection($request);
        }
        $route = $request->getAttribute(RouteAttributes::NAME);
        if (!is_string($route)) {
            return $this->responses->createResponse(404);
        }
        try {
            $permission = $this->permissionForRoute($route);
            $this->authorization->requireAllowed(new AuthorizationRequest(
                AuthorizationSubject::fromAuthenticatedContext($actor),
                new PermissionCode($permission),
                new PlatformAuthorizationScope(),
            ));
            if ($route === 'platform.quran.releases.index') {
                return $this->index($request);
            }
            $id = $this->parameter($request, 'releaseId');
            UuidV7::fromString($id);
            $release = $this->releases->find(UuidV7::fromString($id));
            if ($release === null) {
                return $this->responses->createResponse(404)->withHeader('Cache-Control', 'private, no-store');
            }
            if ($route === 'platform.quran.releases.detail') {
                return $this->detail($request, $release);
            }
            $action = QuranReleaseAction::from($this->actionName($route));
            if (strtoupper($request->getMethod()) === 'GET') {
                return $this->form($request, $release, $action);
            }
            return $this->submit($request, $actor, $release, $action);
        } catch (AuthorizationDeniedException) {
            return $this->responses->createResponse(403)->withHeader('Cache-Control', 'private, no-store');
        } catch (\InvalidArgumentException) {
            return $this->responses->createResponse(422)->withHeader('Cache-Control', 'private, no-store');
        }
    }
    private function index(ServerRequestInterface $request): ResponseInterface
    {
        return $this->privateView(
            $this->view->render(
                $request,
                'pages.quran-release-index',
                'fragments.quran-release-list',
                new ViewData(['releases' => $this->releases->listRecent(50)]),
                'Qur’an release governance',
            ),
        );
    }
    /** @param array{public_id:string,release_code:string,release_version:string,status:string,version:int,created_at:string,updated_at:string} $release */
    private function detail(ServerRequestInterface $request, array $release): ResponseInterface
    {
        return $this->privateView($this->view->render(
            $request,
            'pages.quran-release-detail',
            'fragments.quran-release-detail',
            new ViewData(['release' => $release]),
            'Qur’an release governance',
        ));
    }
    /** @param array{public_id:string,release_code:string,release_version:string,status:string,version:int,created_at:string,updated_at:string} $release */
    private function form(ServerRequestInterface $request, array $release, QuranReleaseAction $action): ResponseInterface
    {
        $csrf = $this->csrf->issue($request, $this->csrfAction($action));
        return $this->privateView($this->view->render(
            $request,
            'pages.quran-release-transition',
            'fragments.quran-release-transition-form',
            new ViewData([
                'release' => $release,
                'action' => $action->value,
                'csrf_token' => $csrf['token'],
                'submission_id' => UuidV7::generate()->toString(),
            ]),
            'Qur’an release transition',
            cookie: $csrf['cookie'],
        ));
    }
    /** @param array{public_id:string,release_code:string,release_version:string,status:string,version:int,created_at:string,updated_at:string} $release */
    private function submit(
        ServerRequestInterface $request,
        AuthenticatedAccountContext $actor,
        array $release,
        QuranReleaseAction $action,
    ): ResponseInterface {
        if (strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0])) !== 'application/x-www-form-urlencoded') {
            return $this->responses->createResponse(415);
        }
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return $this->responses->createResponse(422);
        }
        $csrf = $this->csrf->issue($request, $this->csrfAction($action));
        $token = $body['csrf_token'] ?? null;
        if (!is_string($token) || !$this->csrf->validates($request, $this->csrfAction($action), $csrf['cookie'], $token)) {
            return $this->responses->createResponse(403)->withHeader('Cache-Control', 'private, no-store');
        }
        try {
            $this->lifecycle->transition(new QuranReleaseTransitionCommand(
                $actor,
                UuidV7::fromString((string) $release['public_id']),
                (int) $release['version'],
                UuidV7::fromString($this->field($body, 'submission_id', 36)),
                $action,
                $this->optional($body, 'reason_code', 96),
                null,
            ));
        } catch (\DomainException $e) {
            return $this->privateResponse(
                str_contains($e->getMessage(), 'temporarily') ? 429 : 409,
            );
        }

        return $this->privateView($this->view->redirect(
            '/platform/quran/releases/' . rawurlencode((string) $release['public_id']),
            $csrf['cookie'],
        ));
    }
    private function parameter(ServerRequestInterface $r, string $name): string
    {
        $p = $r->getAttribute(RouteAttributes::PARAMETERS);
        if (!is_array($p) || !is_string($p[$name] ?? null)) {
            throw new \InvalidArgumentException();
        }

        return $p[$name];
    }
    private function actionName(string $route): string
    {
        foreach (QuranReleaseAction::cases() as $a) {
            if ($route === 'platform.quran.releases.' . strtolower($a->value)) {
                return $a->value;
            }
        }

        throw new \InvalidArgumentException();
    }
    private function csrfAction(QuranReleaseAction $a): CsrfAction
    {
        return match ($a) {
            QuranReleaseAction::STAGE => CsrfAction::QURAN_RELEASE_STAGE,
            QuranReleaseAction::VALIDATE => CsrfAction::QURAN_RELEASE_VALIDATE,
            QuranReleaseAction::APPROVE => CsrfAction::QURAN_RELEASE_APPROVE,
            QuranReleaseAction::ACTIVATE => CsrfAction::QURAN_RELEASE_ACTIVATE,
            QuranReleaseAction::REJECT => CsrfAction::QURAN_RELEASE_REJECT,
        };
    }
    /** @param array<array-key, mixed> $b */
    private function field(array $b, string $n, int $m): string
    {
        $v = $b[$n] ?? null;
        if (!is_string($v) || $v === '' || strlen($v) > $m) {
            throw new \InvalidArgumentException();
        }

        return $v;
    }
    /** @param array<array-key, mixed> $b */
    private function optional(array $b, string $n, int $m): ?string
    {
        $v = $b[$n] ?? null;
        if ($v === null || $v === '') {
            return null;
        }

        return $this->field($b, $n, $m);
    }

    private function permissionForRoute(string $route): string
    {
        if (
            $route === 'platform.quran.releases.index'
            || $route === 'platform.quran.releases.detail'
        ) {
            return 'platform.quran_releases.view';
        }

        return QuranReleaseAction::from($this->actionName($route))->permission();
    }

    private function privateResponse(int $status): ResponseInterface
    {
        return $this->responses->createResponse($status)->withHeader(
            'Cache-Control',
            'private, no-store',
        );
    }

    private function privateView(ResponseInterface $response): ResponseInterface
    {
        return $response->withHeader('Cache-Control', 'private, no-store');
    }
}
