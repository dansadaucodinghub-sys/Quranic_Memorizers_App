<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Interface\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Application\AccountSessionInventoryHandler;
use Qmdb\Modules\IdentitySessions\Application\RemoteSessionRevocationService;
use Qmdb\Modules\IdentitySessions\Application\RevocationOutcome;
use Qmdb\Modules\IdentitySessions\Domain\SessionId;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;

final readonly class SessionRevocationController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $guard,
        private AccountSessionInventoryHandler $inventory,
        private RemoteSessionRevocationService $revocation,
        private IdentitySessionFormInput $input,
        private IdentityCsrf $csrf,
        private IdentityAccessView $view,
        private FragmentRequestDetector $fragments,
        private ResponseFactoryInterface $responses,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->guard->context($request);
        if ($context === null) {
            return $this->guard->rejection($request);
        }
        try {
            $target = SessionId::fromString($this->parameter($request, 'sessionId'));
        } catch (\InvalidArgumentException) {
            return $this->responses->createResponse(404);
        }
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_SESSION_REVOKE);
        if (strtoupper($request->getMethod()) === 'GET') {
            foreach ($this->inventory->handle($context)->sessions as $session) {
                if ($session->publicId === $target->toString()) {
                    if ($session->publicId === $context->sessionId->toString()) {
                        return $this->responses->createResponse(409);
                    }

                    return $this->confirmation($request, $target, $session->version, $csrf);
                }
            }

            return $this->responses->createResponse(404);
        }
        try {
            [$version, $token] = $this->input->revocation($request);
        } catch (UnsupportedMediaTypeException) {
            return $this->responses->createResponse(415);
        } catch (\InvalidArgumentException) {
            return $this->responses->createResponse(422);
        }
        if (!$this->csrf->validates($request, CsrfAction::ACCOUNT_SESSION_REVOKE, $csrf['cookie'], $token)) {
            return $this->responses->createResponse(403);
        }
        $outcome = $this->revocation->revoke($context, $target, $version);
        if ($outcome === RevocationOutcome::NOT_FOUND) {
            return $this->responses->createResponse(404);
        }
        if (in_array($outcome, [RevocationOutcome::CURRENT_RESOURCE, RevocationOutcome::VERSION_CONFLICT], true)) {
            return $this->responses->createResponse(409);
        }
        if (!$this->fragments->isFragment($request)) {
            return $this->view->redirect('/account/security/sessions', $csrf['cookie']);
        }

        return $this->panel($request, $context, $csrf);
    }

    /** @param array{cookie: \Qmdb\Modules\SecurityWeb\Csrf\CsrfCookie, token: string} $csrf */
    private function confirmation(
        ServerRequestInterface $request,
        SessionId $target,
        int $version,
        array $csrf,
    ): ResponseInterface {
        return $this->view->render(
            $request,
            'pages.session-revoke-confirm',
            'fragments.session-revoke-dialog',
            IdentitySessionViewDataFactory::confirmation('session', $target->toString(), $version, $csrf['token']),
            'title.session_revoke',
            cookie: $csrf['cookie'],
        )->withHeader('Cache-Control', 'private, no-store');
    }

    /** @param array{cookie: \Qmdb\Modules\SecurityWeb\Csrf\CsrfCookie, token: string} $csrf */
    private function panel(
        ServerRequestInterface $request,
        \Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext $context,
        array $csrf,
    ): ResponseInterface {
        $data = IdentitySessionViewDataFactory::inventory(
            $this->inventory->handle($context),
            $this->csrf->issueForCookie($csrf['cookie'], CsrfAction::ACCOUNT_LOGOUT),
            $csrf['token'],
            $this->csrf->issueForCookie($csrf['cookie'], CsrfAction::ACCOUNT_DEVICE_REVOKE),
        );

        return $this->view->render(
            $request,
            'pages.account-security-sessions',
            'fragments.account-security-session-panel',
            $data,
            'title.account_security',
            cookie: $csrf['cookie'],
        )->withHeader('Cache-Control', 'private, no-store');
    }

    private function parameter(ServerRequestInterface $request, string $name): string
    {
        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
        $value = is_array($parameters) ? ($parameters[$name] ?? null) : null;
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Route parameter is invalid.');
        }

        return $value;
    }
}
