<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\CompetitionLive\Application\CompetitionLiveSessionWorkflowService;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Modules\TenancyContext\Application\Exception\TenantContextRequiredException;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;

/** Server-rendered, private adapter for the closed P7 live-session operations. */
final readonly class CompetitionLiveSessionWorkflowController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $authentication,
        private TenantContextRequiredGuard $tenant,
        private IdentityCsrf $csrf,
        private CompetitionLiveSessionWorkflowService $workflow,
        private Psr17Factory $responses,
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
            return $this->response(404, 'Live session route is unavailable.');
        }
        try {
            $tenant = $this->tenant->require($request, true);
        } catch (TenantContextRequiredException) {
            return $this->responses->createResponse(303)->withHeader('Location', '/account/workspaces?context_required=1')->withHeader('Cache-Control', 'private, no-store');
        }
        try {
            [$operation, $csrfAction, $label] = $this->rule($route);
            $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
            if (!is_array($parameters) || !is_string($parameters['sessionId'] ?? null)) {
                throw new \InvalidArgumentException('Live session is invalid.');
            }
            $sessionId = UuidV7::fromString($parameters['sessionId']);
        } catch (\Throwable) {
            return $this->response(404, 'Live session route is unavailable.');
        }
        $csrf = $this->csrf->issue($request, $csrfAction);
        if (strtoupper($request->getMethod()) === 'GET') {
            return $this->form($label, $request->getUri()->getPath(), $csrf['token'], $csrf['cookie']->setCookieHeader);
        }
        $body = $request->getParsedBody();
        if (!is_array($body) || !is_string($body['csrf_token'] ?? null) || !$this->csrf->validates($request, $csrfAction, $csrf['cookie'], $body['csrf_token'])) {
            return $this->response(403, 'Request verification failed.', $csrf['cookie']->setCookieHeader);
        }
        try {
            $submission = is_string($body['submission_id'] ?? null) ? UuidV7::fromString($body['submission_id']) : throw new \InvalidArgumentException('Submission identifier is invalid.');
            $expectedVersion = $this->integer($body, 'expected_version');
            $this->workflow->operate($actor, $tenant, $submission, $operation, $sessionId, $expectedVersion);

            $response = $this->responses->createResponse(303)->withHeader('Location', '/workspace/competitions')->withHeader('Cache-Control', 'private, no-store');

            return $csrf['cookie']->setCookieHeader === null
                ? $response
                : $response->withHeader('Set-Cookie', $csrf['cookie']->setCookieHeader);
        } catch (\DomainException $error) {
            return $this->response(409, $error->getMessage(), $csrf['cookie']->setCookieHeader);
        } catch (\Throwable) {
            return $this->response(422, 'Live session request is invalid.', $csrf['cookie']->setCookieHeader);
        }
    }

    /** @return array{string,CsrfAction,string} */
    private function rule(string $route): array
    {
        return match ($route) {
            'workspace.competition.live_session.open.form', 'workspace.competition.live_session.open' => ['COMPETITION_LIVE_SESSION_OPEN', CsrfAction::COMPETITION_LIVE_SESSION_OPEN, 'Open live session'],
            'workspace.competition.live_session.pause.form', 'workspace.competition.live_session.pause' => ['COMPETITION_LIVE_SESSION_PAUSE', CsrfAction::COMPETITION_LIVE_SESSION_PAUSE, 'Pause live session'],
            'workspace.competition.live_session.resume.form', 'workspace.competition.live_session.resume' => ['COMPETITION_LIVE_SESSION_RESUME', CsrfAction::COMPETITION_LIVE_SESSION_RESUME, 'Resume live session'],
            'workspace.competition.live_session.start_recovery.form', 'workspace.competition.live_session.start_recovery' => ['COMPETITION_LIVE_SESSION_START_RECOVERY', CsrfAction::COMPETITION_LIVE_SESSION_START_RECOVERY, 'Start live-session recovery'],
            'workspace.competition.live_session.complete_recovery.form', 'workspace.competition.live_session.complete_recovery' => ['COMPETITION_LIVE_SESSION_COMPLETE_RECOVERY', CsrfAction::COMPETITION_LIVE_SESSION_COMPLETE_RECOVERY, 'Complete live-session recovery'],
            'workspace.competition.live_session.close.form', 'workspace.competition.live_session.close' => ['COMPETITION_LIVE_SESSION_CLOSE', CsrfAction::COMPETITION_LIVE_SESSION_CLOSE, 'Close live session'],
            'workspace.competition.live_session.cancel.form', 'workspace.competition.live_session.cancel' => ['COMPETITION_LIVE_SESSION_CANCEL', CsrfAction::COMPETITION_LIVE_SESSION_CANCEL, 'Cancel live session'],
            default => throw new \InvalidArgumentException('Live session operation is unavailable.'),
        };
    }

    private function form(string $label, string $path, string $token, ?string $cookie): ResponseInterface
    {
        $escapedLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escapedPath = htmlspecialchars($path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $submissionId = UuidV7::generate()->toString();
        $html = '<main><h1>' . $escapedLabel . '</h1><p id="live-session-operation-description">This operation is recorded and may require step-up authentication.</p><form method="post" action="' . $escapedPath . '" aria-describedby="live-session-operation-description"><input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><input type="hidden" name="submission_id" value="' . htmlspecialchars($submissionId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><label>Expected version <input name="expected_version" type="number" min="1" required></label><button type="submit">Confirm</button></form></main>';
        $response = $this->responses->createResponse(200)->withHeader('Content-Type', 'text/html; charset=utf-8')->withHeader('Cache-Control', 'private, no-store');
        if ($cookie !== null) {
            $response = $response->withHeader('Set-Cookie', $cookie);
        }
        $response->getBody()->write($html);

        return $response;
    }

    /** @param array<array-key,mixed> $body */
    private function integer(array $body, string $key): int
    {
        $value = $body[$key] ?? null;
        if (is_int($value) || (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1)) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('Expected version is invalid.');
    }

    private function response(int $status, string $message, ?string $cookie = null): ResponseInterface
    {
        $response = $this->responses->createResponse($status)->withHeader('Content-Type', 'text/plain; charset=utf-8')->withHeader('Cache-Control', 'private, no-store');
        if ($cookie !== null) {
            $response = $response->withHeader('Set-Cookie', $cookie);
        }
        $response->getBody()->write($message);

        return $response;
    }
}
