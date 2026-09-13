<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionAppealAdjudication\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\CompetitionAppealAdjudication\Application\CompetitionAppealAdjudicationService;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Modules\TenancyContext\Application\Exception\TenantContextRequiredException;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;

/** Private server-rendered forms; no adjudication authority is held in the browser. */
final readonly class CompetitionAppealAdjudicationController implements Controller
{
    public function __construct(private AuthenticatedRequestGuard $authentication, private TenantContextRequiredGuard $tenant, private IdentityCsrf $csrf, private CompetitionAppealAdjudicationService $workflow, private Psr17Factory $responses)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = $this->authentication->context($request);
        if ($actor === null) {
            return $this->authentication->rejection($request);
        }
        $route = $request->getAttribute(RouteAttributes::NAME);
        if (!is_string($route)) {
            return $this->message(404, 'Appeal adjudication route is unavailable.');
        }
        try {
            $tenant = $this->tenant->require($request, true);
        } catch (TenantContextRequiredException) {
            return $this->responses->createResponse(303)->withHeader('Location', '/account/workspaces?context_required=1')->withHeader('Cache-Control', 'private, no-store');
        }
        try {
            [$operation, $action, $label, $parameter] = $this->rule($route);
            $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
            if (!is_array($parameters) || !is_string($parameters[$parameter] ?? null)) {
                throw new \InvalidArgumentException();
            }
            $aggregateId = UuidV7::fromString($parameters[$parameter]);
        } catch (\Throwable) {
            return $this->message(404, 'Appeal adjudication route is unavailable.');
        }
        $csrf = $this->csrf->issue($request, $action);
        if (strtoupper($request->getMethod()) === 'GET') {
            return $this->form($label, $request->getUri()->getPath(), $csrf['token'], $csrf['cookie']->setCookieHeader, $operation);
        }
        $body = $request->getParsedBody();
        if (!is_array($body) || !is_string($body['csrf_token'] ?? null) || !$this->csrf->validates($request, $action, $csrf['cookie'], $body['csrf_token'])) {
            return $this->message(403, 'Request verification failed.', $csrf['cookie']->setCookieHeader);
        }
        try {
            $submission = is_string($body['submission_id'] ?? null) ? UuidV7::fromString($body['submission_id']) : throw new \InvalidArgumentException();
            if ($operation === 'ASSIGN') {
                $this->workflow->assignSelf($actor, $tenant, $submission, $aggregateId);
            } elseif ($operation === 'ACCEPT') {
                $this->workflow->accept($actor, $tenant, $submission, $aggregateId, $this->version($body));
            } else {
                $this->workflow->decide($actor, $tenant, $submission, $aggregateId, $this->version($body), $this->field($body, 'decision', 24), $this->field($body, 'reason_code', 64), $this->field($body, 'confidential_summary', 12000));
            }
            $response = $this->responses->createResponse(303)->withHeader('Location', '/workspace/competitions')->withHeader('Cache-Control', 'private, no-store');
            return $csrf['cookie']->setCookieHeader === null ? $response : $response->withHeader('Set-Cookie', $csrf['cookie']->setCookieHeader);
        } catch (\DomainException $error) {
            return $this->message(409, $error->getMessage(), $csrf['cookie']->setCookieHeader);
        } catch (\Throwable) {
            return $this->message(422, 'Appeal adjudication request is invalid.', $csrf['cookie']->setCookieHeader);
        }
    }

    /** @return array{string,CsrfAction,string,string} */
    private function rule(string $route): array
    {
        return match ($route) {
            'workspace.competition.appeal_adjudication.assign.form', 'workspace.competition.appeal_adjudication.assign' => ['ASSIGN', CsrfAction::COMPETITION_APPEAL_ADJUDICATION_ASSIGN, 'Join appeal review', 'appealId'],
            'workspace.competition.appeal_adjudication.accept.form', 'workspace.competition.appeal_adjudication.accept' => ['ACCEPT', CsrfAction::COMPETITION_APPEAL_ADJUDICATION_ACCEPT, 'Accept appeal review', 'assignmentId'],
            'workspace.competition.appeal_adjudication.decide.form', 'workspace.competition.appeal_adjudication.decide' => ['DECIDE', CsrfAction::COMPETITION_APPEAL_ADJUDICATION_DECIDE, 'Record appeal adjudication', 'appealId'],
            default => throw new \InvalidArgumentException(),
        };
    }

    private function form(string $label, string $path, string $token, ?string $cookie, string $operation): ResponseInterface
    {
        $version = $operation === 'ASSIGN' ? '' : '<label>Expected version <input name="expected_version" type="number" min="1" required></label>';
        $decision = $operation !== 'DECIDE' ? '' : '<label>Decision <select name="decision" required><option value="UPHELD">Upheld</option><option value="PARTIALLY_UPHELD">Partially upheld</option><option value="DISMISSED">Dismissed</option></select></label><label>Reason code <input name="reason_code" pattern="[A-Z0-9_]{1,64}" required></label><label>Confidential summary <textarea name="confidential_summary" maxlength="4000" required></textarea></label>';
        $html = '<main><h1>' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1><p id="appeal-adjudication-description">This private operation is recorded, idempotent, and may require step-up authentication.</p><form method="post" action="' . htmlspecialchars($path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" aria-describedby="appeal-adjudication-description"><input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><input type="hidden" name="submission_id" value="' . UuidV7::generate()->toString() . '">' . $version . $decision . '<button type="submit">Confirm</button></form></main>';
        $response = $this->responses->createResponse(200)->withHeader('Content-Type', 'text/html; charset=utf-8')->withHeader('Cache-Control', 'private, no-store');
        if ($cookie !== null) {
            $response = $response->withHeader('Set-Cookie', $cookie);
        }
        $response->getBody()->write($html);
        return $response;
    }

    /** @param array<array-key,mixed> $body */ private function version(array $body): int
    {
        $value = $body['expected_version'] ?? null;
        if (is_int($value) || (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1)) {
            return (int) $value;
        } throw new \InvalidArgumentException();
    }
    /** @param array<array-key,mixed> $body */ private function field(array $body, string $key, int $max): string
    {
        $value = $body[$key] ?? null;
        if (!is_string($value) || $value === '' || strlen($value) > $max) {
            throw new \InvalidArgumentException();
        } return $value;
    }
    private function message(int $status, string $text, ?string $cookie = null): ResponseInterface
    {
        $response = $this->responses->createResponse($status)->withHeader('Content-Type', 'text/plain; charset=utf-8')->withHeader('Cache-Control', 'private, no-store');
        if ($cookie !== null) {
            $response = $response->withHeader('Set-Cookie', $cookie);
        } $response->getBody()->write($text);
        return $response;
    }
}
