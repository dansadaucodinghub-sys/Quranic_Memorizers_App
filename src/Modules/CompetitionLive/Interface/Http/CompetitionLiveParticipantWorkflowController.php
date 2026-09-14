<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\CompetitionLive\Application\CompetitionLiveParticipantWorkflowService;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Modules\TenancyContext\Application\Exception\TenantContextRequiredException;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;

/** Private, progressively enhanced form endpoint for closed participant operations. */
final readonly class CompetitionLiveParticipantWorkflowController implements Controller
{
    public function __construct(private AuthenticatedRequestGuard $authentication, private TenantContextRequiredGuard $tenant, private IdentityCsrf $csrf, private CompetitionLiveParticipantWorkflowService $workflow, private Psr17Factory $responses)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = $this->authentication->context($request);
        if ($actor === null) {
            return $this->authentication->rejection($request);
        }
        $route = $request->getAttribute(RouteAttributes::NAME);
        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
        if (!is_string($route) || !is_array($parameters) || !is_string($parameters['sessionId'] ?? null) || !is_string($parameters['participantId'] ?? null)) {
            return $this->response(404, 'Live participant route is unavailable.');
        }
        try {
            $tenant = $this->tenant->require($request, true);
            [$operation, $csrfAction, $label] = $this->rule($route);
            $sessionId = UuidV7::fromString($parameters['sessionId']);
            $participantId = UuidV7::fromString($parameters['participantId']);
        } catch (TenantContextRequiredException) {
            return $this->responses->createResponse(303)->withHeader('Location', '/account/workspaces?context_required=1')->withHeader('Cache-Control', 'private, no-store');
        } catch (\Throwable) {
            return $this->response(404, 'Live participant route is unavailable.');
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
            $this->workflow->operate($actor, $tenant, $submission, $operation, $sessionId, $participantId, $this->integer($body, 'expected_version'));

            $response = $this->responses->createResponse(303)->withHeader('Location', '/workspace/competitions')->withHeader('Cache-Control', 'private, no-store');

            return $csrf['cookie']->setCookieHeader === null
                ? $response
                : $response->withHeader('Set-Cookie', $csrf['cookie']->setCookieHeader);
        } catch (\DomainException $error) {
            return $this->response(409, $error->getMessage(), $csrf['cookie']->setCookieHeader);
        } catch (\Throwable) {
            return $this->response(422, 'Live participant request is invalid.', $csrf['cookie']->setCookieHeader);
        }
    }

    /** @return array{string,CsrfAction,string} */
    private function rule(string $route): array
    {
        $rules = [
            'check_in' => ['COMPETITION_LIVE_PARTICIPANT_CHECK_IN', CsrfAction::COMPETITION_LIVE_PARTICIPANT_CHECK_IN, 'Check in participant'],
            'call' => ['COMPETITION_LIVE_PARTICIPANT_CALL', CsrfAction::COMPETITION_LIVE_PARTICIPANT_CALL, 'Call participant'],
            'ready' => ['COMPETITION_LIVE_PARTICIPANT_READY', CsrfAction::COMPETITION_LIVE_PARTICIPANT_READY, 'Mark participant ready'],
            'start' => ['COMPETITION_LIVE_PARTICIPANT_START', CsrfAction::COMPETITION_LIVE_PARTICIPANT_START, 'Start performance'],
            'interrupt' => ['COMPETITION_LIVE_PARTICIPANT_INTERRUPT', CsrfAction::COMPETITION_LIVE_PARTICIPANT_INTERRUPT, 'Interrupt performance'],
            'resume' => ['COMPETITION_LIVE_PARTICIPANT_RESUME', CsrfAction::COMPETITION_LIVE_PARTICIPANT_RESUME, 'Resume performance'],
            'complete' => ['COMPETITION_LIVE_PARTICIPANT_COMPLETE', CsrfAction::COMPETITION_LIVE_PARTICIPANT_COMPLETE, 'Complete performance'],
            'absent' => ['COMPETITION_LIVE_PARTICIPANT_ABSENT', CsrfAction::COMPETITION_LIVE_PARTICIPANT_ABSENT, 'Mark participant absent'],
            'withdraw' => ['COMPETITION_LIVE_PARTICIPANT_WITHDRAW', CsrfAction::COMPETITION_LIVE_PARTICIPANT_WITHDRAW, 'Withdraw participant'],
            'disqualify' => ['COMPETITION_LIVE_PARTICIPANT_DISQUALIFY', CsrfAction::COMPETITION_LIVE_PARTICIPANT_DISQUALIFY, 'Disqualify participant'],
        ];
        $suffix = preg_replace('/^workspace\.competition\.live_participant\.([a-z_]+)(?:\.form)?$/', '$1', $route);
        if (!is_string($suffix) || !isset($rules[$suffix])) {
            throw new \InvalidArgumentException('Live participant operation is unavailable.');
        }

        return $rules[$suffix];
    }

    private function form(string $label, string $path, string $token, ?string $cookie): ResponseInterface
    {
        $html = '<main><h1>' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1><p id="live-participant-operation-description">This operation is recorded and may require step-up authentication.</p><form method="post" action="' . htmlspecialchars($path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" aria-describedby="live-participant-operation-description"><input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><input type="hidden" name="submission_id" value="' . UuidV7::generate()->toString() . '"><label>Expected version <input name="expected_version" type="number" min="1" required></label><button type="submit">Confirm</button></form></main>';
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
