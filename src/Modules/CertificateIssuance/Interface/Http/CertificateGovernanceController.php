<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\CertificateIssuance\Application\CertificateIssuanceService;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentityAccess\Configuration\PublicApplicationBaseUrl;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Modules\TenancyContext\Application\Exception\TenantContextRequiredException;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;

/** Server-rendered P8 governance forms; browser input has no authority over lifecycle policy. */
final readonly class CertificateGovernanceController implements Controller
{
    public function __construct(private AuthenticatedRequestGuard $authentication, private TenantContextRequiredGuard $tenant, private IdentityCsrf $csrf, private CertificateIssuanceService $certificates, private PublicApplicationBaseUrl $publicBaseUrl, private Psr17Factory $responses)
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
            return $this->response(404, 'Certificate route is unavailable.');
        }
        try {
            $tenant = $this->tenant->require($request, true);
        } catch (TenantContextRequiredException) {
            return $this->responses->createResponse(303)->withHeader('Location', '/account/workspaces?context_required=1')->withHeader('Cache-Control', 'private, no-store');
        }
        $action = $this->csrfAction($route);
        $csrf = $this->csrf->issue($request, $action);
        if (strtoupper($request->getMethod()) === 'GET') {
            return $this->form($route, $request, $csrf['token'], $csrf['cookie']->setCookieHeader);
        }
        $body = $request->getParsedBody();
        if (!is_array($body) || !is_string($body['csrf_token'] ?? null) || !$this->csrf->validates($request, $action, $csrf['cookie'], $body['csrf_token'])) {
            return $this->response(403, 'Request verification failed.', $csrf['cookie']->setCookieHeader);
        }
        try {
            $submission = UuidV7::fromString($this->field($body, 'submission_id', 36));
            if ($route === 'workspace.certificates.issue') {
                $prepared = $this->certificates->prepare($actor, $tenant, $submission, UuidV7::fromString($this->field($body, 'publication_id', 36)), UuidV7::fromString($this->field($body, 'result_row_id', 36)), UuidV7::fromString($this->field($body, 'template_id', 36)), $this->field($body, 'certificate_type', 24));
                if (!$prepared['replayed']) {
                    $this->certificates->issue($actor, $tenant, UuidV7::generate(), UuidV7::fromString($prepared['certificate_id']), $prepared['version'], $prepared['verification_code'], $this->publicBaseUrl->path('/verify/certificates/' . rawurlencode($prepared['verification_code'])));
                }
            } else {
                $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
                if (!is_array($parameters) || !is_string($parameters['certificateId'] ?? null)) {
                    throw new \InvalidArgumentException('Certificate identifier is invalid.');
                }
                $operation = $route === 'workspace.certificates.revoke' ? 'CERTIFICATE_REVOKE' : 'CERTIFICATE_ARCHIVE';
                $this->certificates->transition($actor, $tenant, $submission, UuidV7::fromString($parameters['certificateId']), $this->integer($body, 'expected_version'), $operation, $route === 'workspace.certificates.revoke' ? $this->field($body, 'reason_code', 64) : null);
            }
        } catch (\DomainException $error) {
            return $this->response(str_contains($error->getMessage(), 'temporarily') ? 429 : 409, 'Certificate action could not be completed.', $csrf['cookie']->setCookieHeader);
        } catch (\InvalidArgumentException) {
            return $this->response(422, 'Certificate request is invalid.', $csrf['cookie']->setCookieHeader);
        }
        return $this->withCookie($this->responses->createResponse(303)->withHeader('Location', $this->safePath($request))->withHeader('Cache-Control', 'private, no-store'), $csrf['cookie']->setCookieHeader);
    }

    private function form(string $route, ServerRequestInterface $request, string $token, ?string $cookie): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $issue = $route === 'workspace.certificates.issue.form';
        $fields = $issue ? '<label>Finalized publication public ID <input name="publication_id" required></label><label>Result row public ID <input name="result_row_id" required></label><label>Active template public ID <input name="template_id" required></label><label>Certificate type <select name="certificate_type"><option>WINNER</option><option>PLACEMENT</option><option>PARTICIPATION</option><option>RECOGNITION</option><option>OTHER_APPROVED</option></select></label>' : '<label>Expected version <input name="expected_version" type="number" min="1" required></label>' . ($route === 'workspace.certificates.revoke.form' ? '<label>Reason code <input name="reason_code" pattern="[A-Z][A-Z0-9_]{1,62}" required></label>' : '');
        if ($issue) {
            $path = '/workspace/certificates/issue';
        } else {
            $path = rtrim($path, '/');
        }
        $title = $issue ? 'Issue a certificate' : ($route === 'workspace.certificates.revoke.form' ? 'Revoke certificate' : 'Archive certificate');
        $html = '<main class="shell identity-page"><h1>' . $this->escape($title) . '</h1><p>All actions are authenticated, tenant-scoped, CSRF-protected, replay-safe, and may require phishing-resistant step-up.</p><form method="post" action="' . $this->escape($path) . '"><input type="hidden" name="csrf_token" value="' . $this->escape($token) . '"><input type="hidden" name="submission_id" value="' . UuidV7::generate()->toString() . '">' . $fields . '<button type="submit">' . $this->escape($title) . '</button></form></main>';
        return $this->response(200, $html, $cookie, true);
    }
    private function csrfAction(string $route): CsrfAction
    {
        return match ($route) {
            'workspace.certificates.issue.form','workspace.certificates.issue'=>CsrfAction::CERTIFICATE_ISSUE,'workspace.certificates.revoke.form','workspace.certificates.revoke'=>CsrfAction::CERTIFICATE_REVOKE,'workspace.certificates.archive.form','workspace.certificates.archive'=>CsrfAction::CERTIFICATE_ARCHIVE,default=>throw new \InvalidArgumentException('Certificate CSRF route is invalid.')
        };
    }
    /** @param array<array-key,mixed> $body */ private function field(array $body, string $name, int $limit): string
    {
        $value = $body[$name] ?? null;
        if (!is_string($value) || $value === '' || strlen($value) > $limit) {
            throw new \InvalidArgumentException('Certificate field is invalid.');
        }return $value;
    }
    /** @param array<array-key,mixed> $body */ private function integer(array $body, string $name): int
    {
        $value = $this->field($body, $name, 10);
        if (preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
            throw new \InvalidArgumentException('Certificate version is invalid.');
        }return(int)$value;
    }
    private function response(int $status, string $body, ?string $cookie = null, bool $html = false): ResponseInterface
    {
        $response = $this->responses->createResponse($status)->withHeader('Cache-Control', 'private, no-store')->withHeader('Content-Type', $html ? 'text/html; charset=utf-8' : 'text/plain; charset=utf-8');
        if ($cookie !== null) {
            $response = $response->withAddedHeader('Set-Cookie', $cookie);
        }$response->getBody()->write($body);
        return$response;
    }
    private function withCookie(ResponseInterface $response, ?string $cookie): ResponseInterface
    {
        return $cookie === null ? $response : $response->withAddedHeader('Set-Cookie', $cookie);
    }
    private function safePath(ServerRequestInterface $request): string
    {
        $path = $request->getUri()->getPath();
        return str_starts_with($path, '/workspace/') ? $path : '/workspace/certificates/issue';
    }
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
