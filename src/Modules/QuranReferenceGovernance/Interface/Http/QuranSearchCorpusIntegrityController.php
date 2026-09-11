<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranSearchCorpusIntegrityRepository;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranSearchCorpusValidationCommand;
use Qmdb\Modules\QuranReferenceGovernance\Application\QuranSearchCorpusValidationService;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class QuranSearchCorpusIntegrityController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $authentication,
        private AuthorizationRequirementGuard $authorization,
        private IdentityCsrf $csrf,
        private IdentityAccessView $view,
        private Psr17Factory $responses,
        private QuranSearchCorpusIntegrityRepository $corpora,
        private QuranSearchCorpusValidationService $validation,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = $this->authentication->context($request);
        if ($actor === null) {
            return $this->authentication->rejection($request);
        }
        try {
            $route = $request->getAttribute(RouteAttributes::NAME);
            if (!is_string($route)) {
                return $this->privateResponse(404);
            }
            $release = UuidV7::fromString($this->releaseId($request));
            $permission = $route === 'platform.quran.search_corpus' ? 'platform.quran_search_corpus.view' : 'platform.quran_search_corpus.validate';
            $this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor), new PermissionCode($permission), new PlatformAuthorizationScope()));
            $corpus = $this->corpora->findForRelease($release);
            if ($corpus === null) {
                return $this->privateResponse(404);
            }
            if ($route === 'platform.quran.search_corpus') {
                return $this->render($request, $corpus, false);
            }
            if (strtoupper($request->getMethod()) === 'GET') {
                return $this->render($request, $corpus, true);
            }

            return $this->submit($request, $actor, $release, $corpus);
        } catch (\Qmdb\Modules\SecurityAuthorization\Application\Exception\AuthorizationDeniedException) {
            return $this->privateResponse(403);
        } catch (\InvalidArgumentException) {
            return $this->privateResponse(422);
        }
    }

    /** @param array<string, int|string> $corpus */
    private function render(ServerRequestInterface $request, array $corpus, bool $form): ResponseInterface
    {
        $csrf = $form ? $this->csrf->issue($request, CsrfAction::QURAN_SEARCH_CORPUS_VALIDATE) : null;
        $response = $this->view->render($request, 'pages.quran-search-corpus', 'fragments.quran-search-corpus-integrity', new ViewData(['corpus' => $corpus, 'validation_form' => $form, 'csrf_token' => $csrf['token'] ?? null, 'submission_id' => $form ? UuidV7::generate()->toString() : null]), 'Qur’an search corpus integrity', cookie: $csrf['cookie'] ?? null);

        return $response->withHeader('Cache-Control', 'private, no-store');
    }

    /** @param array<string, int|string> $corpus */
    private function submit(ServerRequestInterface $request, AuthenticatedAccountContext $actor, UuidV7 $release, array $corpus): ResponseInterface
    {
        if (strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0])) !== 'application/x-www-form-urlencoded') {
            return $this->privateResponse(415);
        }
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return $this->privateResponse(422);
        }
        $csrf = $this->csrf->issue($request, CsrfAction::QURAN_SEARCH_CORPUS_VALIDATE);
        if (!is_string($body['csrf_token'] ?? null) || !$this->csrf->validates($request, CsrfAction::QURAN_SEARCH_CORPUS_VALIDATE, $csrf['cookie'], $body['csrf_token'])) {
            return $this->privateResponse(403);
        }
        $version = $body['expected_version'] ?? null;
        if (!is_string($version) || preg_match('/\A[1-9][0-9]*\z/', $version) !== 1 || (int) $version !== (int) $corpus['version'] || !is_string($body['submission_id'] ?? null)) {
            return $this->privateResponse(409);
        }
        try {
            $this->validation->validate(new QuranSearchCorpusValidationCommand($actor, $release, (int) $version, UuidV7::fromString($body['submission_id'])));
        } catch (\DomainException $error) {
            return $this->privateResponse(str_contains($error->getMessage(), 'temporarily') ? 429 : 409);
        }

        return $this->view->redirect('/platform/quran/releases/' . rawurlencode($release->toString()) . '/search-corpus', $csrf['cookie'])->withHeader('Cache-Control', 'private, no-store');
    }

    private function releaseId(ServerRequestInterface $request): string
    {
        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
        if (!is_array($parameters) || !is_string($parameters['releaseId'] ?? null)) {
            throw new \InvalidArgumentException('Release identifier is invalid.');
        }

        return $parameters['releaseId'];
    }

    private function privateResponse(int $status): ResponseInterface
    {
        return $this->responses->createResponse($status)->withHeader('Cache-Control', 'private, no-store');
    }
}
