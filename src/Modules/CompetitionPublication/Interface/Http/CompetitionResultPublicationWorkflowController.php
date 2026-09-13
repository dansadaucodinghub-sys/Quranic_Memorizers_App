<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionPublication\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\CompetitionPublication\Application\CompetitionResultPublicationWorkflowService;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Modules\TenancyContext\Application\Exception\TenantContextRequiredException;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;

/** Private no-store forms for the closed P7 publication lifecycle. */
final readonly class CompetitionResultPublicationWorkflowController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $authentication,
        private TenantContextRequiredGuard $tenant,
        private IdentityCsrf $csrf,
        private CompetitionResultPublicationWorkflowService $workflow,
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
            return $this->message(404, 'Publication route is unavailable.');
        }
        try {
            $tenant = $this->tenant->require($request, true);
        } catch (TenantContextRequiredException) {
            return $this->responses->createResponse(303)->withHeader('Location', '/account/workspaces?context_required=1')->withHeader('Cache-Control', 'private, no-store');
        }
        try {
            [$operation, $csrfAction, $label, $source] = $this->rule($route);
            $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
            if (!is_array($parameters) || !is_string($parameters[$source] ?? null)) {
                throw new \InvalidArgumentException('Publication target is invalid.');
            }
            $aggregateId = UuidV7::fromString($parameters[$source]);
        } catch (\Throwable) {
            return $this->message(404, 'Publication route is unavailable.');
        }
        $csrf = $this->csrf->issue($request, $csrfAction);
        if (strtoupper($request->getMethod()) === 'GET') {
            return $this->form($label, $request->getUri()->getPath(), $csrf['token'], $csrf['cookie']->setCookieHeader, $operation === 'COMPETITION_RESULT_PUBLICATION_PREPARE');
        }
        $body = $request->getParsedBody();
        if (!is_array($body) || !is_string($body['csrf_token'] ?? null) || !$this->csrf->validates($request, $csrfAction, $csrf['cookie'], $body['csrf_token'])) {
            return $this->message(403, 'Request verification failed.', $csrf['cookie']->setCookieHeader);
        }
        try {
            $submission = is_string($body['submission_id'] ?? null) ? UuidV7::fromString($body['submission_id']) : throw new \InvalidArgumentException('Submission identifier is invalid.');
            if ($operation === 'COMPETITION_RESULT_PUBLICATION_PREPARE') {
                $this->workflow->prepare($actor, $tenant, $submission, $aggregateId);
            } else {
                $this->workflow->transition($actor, $tenant, $submission, $operation, $aggregateId, $this->positiveInteger($body, 'expected_version'));
            }

            $response = $this->responses->createResponse(303)->withHeader('Location', '/workspace/competitions')->withHeader('Cache-Control', 'private, no-store');

            return $this->withCookie($response, $csrf['cookie']->setCookieHeader);
        } catch (\DomainException $error) {
            return $this->message(409, $error->getMessage(), $csrf['cookie']->setCookieHeader);
        } catch (\Throwable) {
            return $this->message(422, 'Publication request is invalid.', $csrf['cookie']->setCookieHeader);
        }
    }

    /** @return array{string,CsrfAction,string,string} */
    private function rule(string $route): array
    {
        return match ($route) {
            'workspace.competition.result_publication.prepare.form', 'workspace.competition.result_publication.prepare' => ['COMPETITION_RESULT_PUBLICATION_PREPARE', CsrfAction::COMPETITION_RESULT_PUBLICATION_PREPARE, 'Prepare result publication', 'resultRunId'],
            'workspace.competition.result_publication.publish_provisional.form', 'workspace.competition.result_publication.publish_provisional' => ['COMPETITION_RESULT_PUBLICATION_PUBLISH_PROVISIONAL', CsrfAction::COMPETITION_RESULT_PUBLICATION_PUBLISH_PROVISIONAL, 'Publish provisional result', 'publicationId'],
            'workspace.competition.result_publication.hold.form', 'workspace.competition.result_publication.hold' => ['COMPETITION_RESULT_PUBLICATION_HOLD', CsrfAction::COMPETITION_RESULT_PUBLICATION_HOLD, 'Hold result publication', 'publicationId'],
            'workspace.competition.result_publication.release_hold.form', 'workspace.competition.result_publication.release_hold' => ['COMPETITION_RESULT_PUBLICATION_RELEASE_HOLD', CsrfAction::COMPETITION_RESULT_PUBLICATION_RELEASE_HOLD, 'Release publication hold', 'publicationId'],
            'workspace.competition.result_publication.finalize.form', 'workspace.competition.result_publication.finalize' => ['COMPETITION_RESULT_PUBLICATION_FINALIZE', CsrfAction::COMPETITION_RESULT_PUBLICATION_FINALIZE, 'Finalize result publication', 'publicationId'],
            'workspace.competition.result_publication.withdraw.form', 'workspace.competition.result_publication.withdraw' => ['COMPETITION_RESULT_PUBLICATION_WITHDRAW', CsrfAction::COMPETITION_RESULT_PUBLICATION_WITHDRAW, 'Withdraw result publication', 'publicationId'],
            'workspace.competition.result_publication.supersede.form', 'workspace.competition.result_publication.supersede' => ['COMPETITION_RESULT_PUBLICATION_SUPERSEDE', CsrfAction::COMPETITION_RESULT_PUBLICATION_SUPERSEDE, 'Supersede result publication', 'publicationId'],
            'workspace.competition.result_publication.archive.form', 'workspace.competition.result_publication.archive' => ['COMPETITION_RESULT_PUBLICATION_ARCHIVE', CsrfAction::COMPETITION_RESULT_PUBLICATION_ARCHIVE, 'Archive result publication', 'publicationId'],
            default => throw new \InvalidArgumentException('Publication operation is invalid.'),
        };
    }

    private function form(string $label, string $path, string $token, ?string $cookie, bool $preparation): ResponseInterface
    {
        $version = $preparation ? '' : '<label>Expected version <input name="expected_version" type="number" min="1" required></label>';
        $html = '<main><h1>' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1><p id="publication-operation-description">This governed operation is auditable and may require phishing-resistant step-up authentication.</p><form method="post" action="' . htmlspecialchars($path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" aria-describedby="publication-operation-description"><input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><input type="hidden" name="submission_id" value="' . UuidV7::generate()->toString() . '">' . $version . '<button type="submit">Confirm</button></form></main>';
        $response = $this->responses->createResponse(200)->withHeader('Content-Type', 'text/html; charset=utf-8')->withHeader('Cache-Control', 'private, no-store');
        $response = $this->withCookie($response, $cookie);
        $response->getBody()->write($html);

        return $response;
    }

    /** @param array<array-key,mixed> $body */
    private function positiveInteger(array $body, string $key): int
    {
        $value = $body[$key] ?? null;
        if (is_int($value) || (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1)) {
            return (int) $value;
        }
        throw new \InvalidArgumentException('Expected version is invalid.');
    }

    private function message(int $status, string $message, ?string $cookie = null): ResponseInterface
    {
        $response = $this->responses->createResponse($status)->withHeader('Content-Type', 'text/plain; charset=utf-8')->withHeader('Cache-Control', 'private, no-store');
        if ($cookie !== null) {
            $response = $response->withHeader('Set-Cookie', $cookie);
        }
        $response->getBody()->write($message);

        return $response;
    }

    private function withCookie(ResponseInterface $response, ?string $cookie): ResponseInterface
    {
        return $cookie === null ? $response : $response->withHeader('Set-Cookie', $cookie);
    }
}
