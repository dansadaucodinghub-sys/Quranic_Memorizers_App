<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\CompetitionResults\Application\CompetitionP6WorkflowService;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Modules\TenancyContext\Application\Exception\TenantContextRequiredException;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;

/**
 * Shared P6 private HTTP adapter. It contains no domain decisions: each POST
 * maps a route to one closed workflow operation and a matching CSRF action.
 */
final readonly class CompetitionP6WorkflowController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $authentication,
        private TenantContextRequiredGuard $tenant,
        private IdentityCsrf $csrf,
        private CompetitionP6WorkflowService $workflow,
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
            return $this->response(404, 'Competition route is unavailable.');
        }
        try {
            $tenant = $this->tenant->require($request, str_starts_with($route, 'workspace.'));
        } catch (TenantContextRequiredException) {
            return $this->responses->createResponse(303)->withHeader('Location', '/account/workspaces?context_required=1')->withHeader('Cache-Control', 'private, no-store');
        }
        $csrfAction = $this->csrfAction($route);
        $csrf = $this->csrf->issue($request, $csrfAction);
        if (strtoupper($request->getMethod()) === 'GET') {
            return $this->form($route, $request->getUri()->getPath(), $csrf['token'], $csrf['cookie']->setCookieHeader);
        }
        $body = $request->getParsedBody();
        if (!is_array($body) || !is_string($body['csrf_token'] ?? null) || !$this->csrf->validates($request, $csrfAction, $csrf['cookie'], $body['csrf_token'])) {
            return $this->response(403, 'Request verification failed.', $csrf['cookie']->setCookieHeader);
        }
        try {
            $operation = $this->operation($route);
            $publicId = $this->aggregateId($request, $body, $operation);
            $expectedVersion = $this->integer($body, 'expected_version');
            $submission = UuidV7::fromString($this->field($body, 'submission_id', 36));
            $this->workflow->transition($actor, $tenant, $submission, $operation, UuidV7::fromString($publicId), $expectedVersion);
        } catch (\DomainException $exception) {
            return $this->response(str_contains($exception->getMessage(), 'temporarily') ? 429 : 409, 'Competition action could not be completed.', $csrf['cookie']->setCookieHeader);
        } catch (\InvalidArgumentException) {
            return $this->response(422, 'Competition request is invalid.', $csrf['cookie']->setCookieHeader);
        }

        return $this->responses->createResponse(303)->withHeader('Location', $this->safePath($request))->withHeader('Cache-Control', 'private, no-store')->withAddedHeader('Set-Cookie', $csrf['cookie']->setCookieHeader);
    }

    private function form(string $route, string $path, string $token, ?string $cookie): ResponseInterface
    {
        $operation = $this->operationForDisplay($route);
        $html = '<main class="shell identity-page" data-qmdb-p6-workflow><h1>Competition workflow</h1><p>Use the controlled form below. JavaScript is optional for this action.</p><form method="post" action="' . $this->escape($path) . '"><input type="hidden" name="csrf_token" value="' . $this->escape($token) . '"><input type="hidden" name="submission_id" value="' . UuidV7::generate()->toString() . '"><label>Expected version <input name="expected_version" type="number" min="1" required></label><label>Record public ID <input name="aggregate_id" inputmode="text" autocomplete="off" required></label><button type="submit">' . $this->escape($operation) . '</button></form></main>';

        return $this->response(200, $html, $cookie, true);
    }

    /** @param array<array-key,mixed> $body */
    private function aggregateId(ServerRequestInterface $request, array $body, string $operation): string
    {
        if (str_contains($operation, 'SCORE_')) {
            return $this->field($body, 'aggregate_id', 36);
        }
        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
        if (is_array($parameters)) {
            foreach (['assignmentId', 'scoreSheetId', 'resultRunId', 'appealId', 'roundId'] as $name) {
                if (is_string($parameters[$name] ?? null)) {
                    return $parameters[$name];
                }
            }
        }

        return $this->field($body, 'aggregate_id', 36);
    }

    private function operation(string $route): string
    {
        return match ($route) {
            'account.competition_judging.assignment.accept' => 'COMPETITION_ASSIGNMENT_ACCEPT',
            'account.competition_judging.assignment.decline' => 'COMPETITION_ASSIGNMENT_DECLINE',
            'workspace.competition.assignment.revoke' => 'COMPETITION_ASSIGNMENT_REVOKE',
            'workspace.competition.round.ready' => 'COMPETITION_ROUND_READY',
            'workspace.competition.round.open_scoring' => 'COMPETITION_ROUND_OPEN_SCORING',
            'workspace.competition.round.close_scoring' => 'COMPETITION_ROUND_CLOSE_SCORING',
            'workspace.competition.round.cancel' => 'COMPETITION_ROUND_CANCEL',
            'account.competition_judging.score.submit' => 'COMPETITION_SCORE_SUBMIT',
            'account.competition_judging.score.lock' => 'COMPETITION_SCORE_LOCK',
            'workspace.competition.score_sheet.supersede' => 'COMPETITION_SCORE_SUPERSEDE',
            'workspace.competition.score_sheet.void' => 'COMPETITION_SCORE_VOID',
            'workspace.competition.result.verify' => 'COMPETITION_RESULT_VERIFY',
            'workspace.competition.result.publish' => 'COMPETITION_RESULT_PUBLISH',
            'workspace.competition.result.void' => 'COMPETITION_RESULT_VOID',
            'workspace.competition.appeal.start_review' => 'COMPETITION_APPEAL_START_REVIEW',
            'workspace.competition.appeal.uphold' => 'COMPETITION_APPEAL_UPHOLD',
            'workspace.competition.appeal.dismiss' => 'COMPETITION_APPEAL_DISMISS',
            'account.competition_appeal.withdraw' => 'COMPETITION_APPEAL_WITHDRAW',
            default => throw new \InvalidArgumentException('Competition route is not a lifecycle mutation.'),
        };
    }

    private function operationForDisplay(string $route): string
    {
        try {
            return strtolower(str_replace('_', ' ', $this->operation($route)));
        } catch (\InvalidArgumentException) {
            return 'Review competition record';
        }
    }

    private function csrfAction(string $route): CsrfAction
    {
        return match ($route) {
            'account.competition_judging.assignment.accept' => CsrfAction::COMPETITION_ASSIGNMENT_ACCEPT,
            'account.competition_judging.assignment.decline' => CsrfAction::COMPETITION_ASSIGNMENT_DECLINE,
            'workspace.competition.assignment.revoke' => CsrfAction::COMPETITION_ASSIGNMENT_REVOKE,
            'workspace.competition.round.ready' => CsrfAction::COMPETITION_ROUND_READY,
            'workspace.competition.round.open_scoring' => CsrfAction::COMPETITION_ROUND_OPEN_SCORING,
            'workspace.competition.round.close_scoring' => CsrfAction::COMPETITION_ROUND_CLOSE_SCORING,
            'workspace.competition.round.cancel' => CsrfAction::COMPETITION_ROUND_CANCEL,
            'account.competition_judging.score.submit' => CsrfAction::COMPETITION_SCORE_SUBMIT,
            'account.competition_judging.score.lock' => CsrfAction::COMPETITION_SCORE_LOCK,
            'workspace.competition.score_sheet.supersede' => CsrfAction::COMPETITION_SCORE_SUPERSEDE,
            'workspace.competition.score_sheet.void' => CsrfAction::COMPETITION_SCORE_VOID,
            'workspace.competition.result.verify' => CsrfAction::COMPETITION_RESULT_VERIFY,
            'workspace.competition.result.publish' => CsrfAction::COMPETITION_RESULT_PUBLISH,
            'workspace.competition.result.void' => CsrfAction::COMPETITION_RESULT_VOID,
            'workspace.competition.appeal.start_review' => CsrfAction::COMPETITION_APPEAL_START_REVIEW,
            'workspace.competition.appeal.uphold' => CsrfAction::COMPETITION_APPEAL_UPHOLD,
            'workspace.competition.appeal.dismiss' => CsrfAction::COMPETITION_APPEAL_DISMISS,
            'account.competition_appeal.withdraw' => CsrfAction::COMPETITION_APPEAL_WITHDRAW,
            default => CsrfAction::COMPETITION_ROUND_READY,
        };
    }

    /** @param array<array-key,mixed> $body */
    private function field(array $body, string $name, int $limit): string
    {
        $value = $body[$name] ?? null;
        if (!is_string($value) || $value === '' || strlen($value) > $limit) {
            throw new \InvalidArgumentException('Competition field is invalid.');
        }

        return $value;
    }

    /** @param array<array-key,mixed> $body */
    private function integer(array $body, string $name): int
    {
        $value = $this->field($body, $name, 10);
        if (preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
            throw new \InvalidArgumentException('Competition version is invalid.');
        }

        return (int) $value;
    }

    private function response(int $status, string $content, ?string $cookie = null, bool $html = false): ResponseInterface
    {
        $response = $this->responses->createResponse($status)->withHeader('Cache-Control', 'private, no-store');
        if ($html) {
            $response = $response->withHeader('Content-Type', 'text/html; charset=utf-8');
        }
        if ($cookie !== null) {
            $response = $response->withAddedHeader('Set-Cookie', $cookie);
        }
        $response->getBody()->write($content);

        return $response;
    }

    private function safePath(ServerRequestInterface $request): string
    {
        $path = $request->getUri()->getPath();
        return str_starts_with($path, '/') && !str_starts_with($path, '//') ? $path : '/workspace';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
