<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaModeration\Interface\Http;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Qmdb\Modules\IdentityAccess\Interface\Http\{IdentityAccessView, IdentityCsrf};
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\MediaModeration\Application\{MediaGovernanceRepository, MediaGovernanceService, MediaGovernanceRateLimited};
use Qmdb\Modules\MediaModeration\Domain\{MediaGovernanceAction, MediaGovernanceRecord};
use Qmdb\Modules\SecurityAuthorization\Application\{AuthorizationRequest, AuthorizationRequirementGuard, AuthorizationSubject};
use Qmdb\Modules\SecurityAuthorization\Application\Exception\AuthorizationDeniedException;
use Qmdb\Modules\SecurityAuthorization\Domain\{PermissionCode, WorkspaceAuthorizationScope};
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Modules\TenancyContext\Application\Exception\TenantContextRequiredException;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class MediaGovernanceController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $authentication,
        private TenantContextRequiredGuard $tenant,
        private AuthorizationRequirementGuard $authorization,
        private IdentityCsrf $csrf,
        private MediaGovernanceService $governance,
        private MediaGovernanceRepository $repository,
        private IdentityAccessView $views,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = $this->authentication->context($request);
        if ($actor === null) {
            return $this->authentication->rejection($request);
        }
        $action = MediaGovernanceAction::tryFrom(basename($request->getUri()->getPath()));
        $csrf = $action === null ? null : $this->csrf->issue($request, $action->csrf());
        $error = '';
        $status = 200;
        $retryAfter = null;
        $records = [];
        $saved = ($request->getQueryParams()['saved'] ?? null) === '1';
        $tenantVersion = '';
        $workspaceId = '';
        $submission = UuidV7::generate()->toString();
        try {
            $tenant = $this->tenant->require($request, $request->hasHeader('X-QMDB-Tenant-Context-Version'));
            $tenantVersion = (string) $tenant->version->value;
            $workspaceId = $tenant->workspacePublicId();
            $permission = $action?->permission() ?? 'workspace.media.view';
            $this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor), new PermissionCode($permission), new WorkspaceAuthorizationScope($tenant)));
            if ($action === null) {
                $records = $this->repository->recent($tenant->workspaceInternalId);
            } else {
                $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
                $id = is_array($parameters) ? ($parameters['assetId'] ?? null) : null;
                if (!is_string($id)) {
                    throw new \InvalidArgumentException('Missing media identifier.');
                }
                $asset = $this->repository->find($tenant->workspaceInternalId, UuidV7::fromString($id));
                if ($asset === null || ($action === MediaGovernanceAction::WITHDRAW_CONSENT && $asset->creatorId !== $actor->accountInternalId)) {
                    $status = 404;
                    $error = 'media.governance.unavailable';
                } else {
                    $records = [$asset];
                    if ($request->getMethod() === 'POST') {
                        $body = $request->getParsedBody();
                        if (!is_array($body) || $csrf === null) {
                            throw new \InvalidArgumentException('Invalid form.');
                        }
                        if ($this->field($body, 'tenant_context_version') !== $tenantVersion || $this->field($body, 'workspace_id') !== $workspaceId) {
                            throw new \DomainException('The workspace selection changed. Reload the form.');
                        }
                        $token = $this->headerOrField($request, $body, 'X-QMDB-CSRF', 'csrf_token');
                        if (!$this->csrf->validates($request, $action->csrf(), $csrf['cookie'], $token)) {
                            $status = 403;
                            $error = 'media.governance.csrf_failed';
                        } else {
                            $submission = UuidV7::fromString($this->headerOrField($request, $body, 'Idempotency-Key', 'submission_id'))->toString();
                            $version = $this->field($body, 'version');
                            if (preg_match('/\A[1-9][0-9]{0,9}\z/', $version) !== 1) {
                                throw new \InvalidArgumentException('Invalid version.');
                            }
                            $evidence = $action === MediaGovernanceAction::GRANT_CONSENT ? new \Qmdb\Modules\MediaModeration\Domain\MediaConsentEvidence(
                                UuidV7::fromString($this->field($body, 'evidence_reference')),
                                $this->field($body, 'evidence_sha256'),
                                $this->count($body, 'participants'),
                                $this->count($body, 'participant_consents'),
                                $this->count($body, 'minors'),
                                $this->count($body, 'guardian_consents'),
                                $this->field($body, 'rights_verified') === '1',
                                $this->field($body, 'organization_authority_verified') === '1',
                            ) : null;
                            $this->governance->execute($actor, $tenant, UuidV7::fromString($submission), UuidV7::fromString($id), (int) $version, $action, $this->field($body, 'reason_code'), $this->field($body, 'hold_code'), $evidence);
                            if (!(new \Qmdb\Shared\Presentation\Response\FragmentRequestDetector())->isFragment($request)) {
                                return $this->views->redirect($request->getUri()->getPath() . '?saved=1', $csrf['cookie']);
                            }
                            $fresh = $this->repository->find($tenant->workspaceInternalId, UuidV7::fromString($id));
                            $records = $fresh === null ? [] : [$fresh];
                            $saved = true;
                            $submission = UuidV7::generate()->toString();
                        }
                    }
                }
            }
        } catch (TenantContextRequiredException) {
            return $this->views->redirect('/account/workspaces?context_required=1');
        } catch (AuthorizationDeniedException) {
            $status = 403;
            $error = 'media.governance.denied';
        } catch (MediaGovernanceRateLimited $limited) {
            $status = 429;
            $retryAfter = $limited->retryAfterSeconds;
            $error = 'media.governance.rate_limited';
        } catch (\InvalidArgumentException) {
            $status = 422;
            $error = 'media.governance.invalid';
        } catch (\DomainException) {
            $status = 409;
            $error = 'media.governance.conflict';
        }
        $stepUp = $action?->stepUp();
        $response = $this->views->render($request, 'pages.media-governance', 'fragments.media-governance', new ViewData([
            'records' => array_map(static fn (MediaGovernanceRecord $record): array => ['public_id' => $record->publicId, 'status' => $record->status, 'version' => $record->version, 'held' => $record->held], $records),
            'action' => $action === null ? '' : $action->value, 'step_up' => $stepUp === null ? '' : $stepUp->value,
            'csrf_token' => $csrf['token'] ?? '', 'submission_id' => $submission, 'error' => $error,
            'tenant_context_version' => $tenantVersion, 'workspace_id' => $workspaceId,
            'saved' => $saved,
        ]), 'media.governance.title', $status, $csrf['cookie'] ?? null, true);
        return $retryAfter === null ? $response : $response->withHeader('Retry-After', (string) $retryAfter);
    }

    /** @param array<array-key,mixed> $body */
    private function field(array $body, string $key): string
    {
        $value = $body[$key] ?? null;
        return is_string($value) && strlen($value) <= 128 ? $value : throw new \InvalidArgumentException('Invalid form field.');
    }
    /** @param array<array-key,mixed> $body */
    private function count(array $body, string $key): int
    {
        $value = $this->field($body, $key);
        if (preg_match('/\A(?:0|[1-9][0-9]{0,3})\z/', $value) !== 1) {
            throw new \InvalidArgumentException('Invalid evidence count.');
        }
        return (int) $value;
    }

    /** @param array<array-key,mixed> $body */
    private function headerOrField(ServerRequestInterface $request, array $body, string $header, string $field): string
    {
        $headerValue = $request->getHeaderLine($header);
        $value = $body[$field] ?? '';
        if (!is_string($value) || strlen($headerValue) > 512 || strlen($value) > 512 || ($headerValue !== '' && $value !== '' && !hash_equals($value, $headerValue))) {
            throw new \InvalidArgumentException('Conflicting request tokens.');
        }
        return $headerValue !== '' ? $headerValue : $value;
    }
}
