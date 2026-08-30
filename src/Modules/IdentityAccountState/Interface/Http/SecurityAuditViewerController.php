<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccountState\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventRecord;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditListFilter;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditRepository;
use Qmdb\Modules\SecurityAudit\Domain\SecurityAuditStreamType;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventOutcome;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSeverity;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Application\BaseRoleAuthorizationGuard;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class SecurityAuditViewerController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $authentication,
        private BaseRoleAuthorizationGuard $authorization,
        private SecurityAuditRepository $audit,
        private IdentityAccessView $view,
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
            return $this->responses->createResponse(404);
        }
        try {
            if ($route === 'account.security.events') {
                $page = $this->audit->listForAccount($actor->accountId->toString(), $this->cursor($request), 25);

                return $this->eventsPage($request, $page->events, $page->nextCursor, false);
            }
            $permission = $route === 'platform.security.audit' ? 'platform.audit.verify' : 'platform.security_events.view';
            $this->authorization->requireAllowed(new AuthorizationRequest(
                AuthorizationSubject::fromAuthenticatedContext($actor),
                new PermissionCode($permission),
                new PlatformAuthorizationScope(),
            ));
            if ($route === 'platform.security.audit') {
                $status = $this->audit->integrityStatus();

                return $this->view->render($request, 'pages.security-audit-status', 'fragments.security-audit-status', new ViewData([
                    'stream_count' => $status->streamCount, 'event_count' => $status->eventCount,
                    'checkpoint_number' => $status->latestCheckpointNumber === null ? '—' : (string) $status->latestCheckpointNumber,
                    'checkpoint_at' => $status->latestCheckpointAt?->format('Y-m-d H:i:s T') ?? '—',
                    'checkpoint_hash' => $status->latestCheckpointHash ?? '—', 'publication' => $status->externalPublicationStatus,
                ]), 'title.security_audit_status')->withHeader('Cache-Control', 'private, no-store');
            }
            if ($route === 'platform.security.events.detail') {
                $event = $this->audit->findByPublicId($this->parameter($request, 'eventId'));
                if ($event === null) {
                    return $this->responses->createResponse(404)->withHeader('Cache-Control', 'private, no-store');
                }

                return $this->eventsPage($request, [$event], null, true);
            }
            $page = $this->audit->listPlatform($this->filter($request), $this->cursor($request), 25);

            return $this->eventsPage($request, $page->events, $page->nextCursor, true);
        } catch (\Qmdb\Modules\SecurityAuthorization\Application\Exception\AuthorizationDeniedException) {
            return $this->responses->createResponse(403)->withHeader('Cache-Control', 'private, no-store');
        } catch (\InvalidArgumentException) {
            return $this->responses->createResponse(422)->withHeader('Cache-Control', 'private, no-store');
        }
    }

    /** @param list<SecurityAuditEventRecord> $events */
    private function eventsPage(ServerRequestInterface $request, array $events, ?string $nextCursor, bool $platform): ResponseInterface
    {
        return $this->view->render($request, 'pages.security-audit-events', 'fragments.security-audit-events', new ViewData([
            'events' => array_map(static fn (SecurityAuditEventRecord $event): array => [
                'public_id' => $event->publicId, 'event_code' => $event->eventCode, 'severity' => $event->severity->value,
                'outcome' => $event->outcome->value, 'stream_type' => $event->streamType->value,
                'subject_kind' => $event->subjectKind, 'subject_public_id' => $event->subjectPublicId,
                'occurred_at' => $event->occurredAt->format('Y-m-d H:i:s T'),
            ], $events),
            'next_cursor' => $nextCursor ?? '', 'platform' => $platform,
        ]), 'title.security_audit_events')->withHeader('Cache-Control', 'private, no-store');
    }

    private function filter(ServerRequestInterface $request): SecurityAuditListFilter
    {
        $query = $request->getQueryParams();
        $value = static fn (string $name): ?string => is_string($query[$name] ?? null) && $query[$name] !== '' ? $query[$name] : null;
        $subject = $value('subject_id');
        $workspace = $value('workspace_id');
        if ($subject !== null) {
            UuidV7::fromString($subject);
        }
        if ($workspace !== null) {
            UuidV7::fromString($workspace);
        }

        return new SecurityAuditListFilter(
            ($code = $value('event_code')) === null ? null : SecurityEventCode::fromTrustedString($code),
            ($severity = $value('severity')) === null ? null : SecurityEventSeverity::from($severity),
            ($outcome = $value('outcome')) === null ? null : SecurityEventOutcome::from($outcome),
            ($stream = $value('stream_type')) === null ? null : SecurityAuditStreamType::from($stream),
            $subject,
            $workspace,
        );
    }

    private function cursor(ServerRequestInterface $request): ?string
    {
        $cursor = $request->getQueryParams()['cursor'] ?? null;
        if ($cursor === null || $cursor === '') {
            return null;
        }
        if (!is_string($cursor) || strlen($cursor) > 256) {
            throw new \InvalidArgumentException('Cursor is invalid.');
        }

        return $cursor;
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
