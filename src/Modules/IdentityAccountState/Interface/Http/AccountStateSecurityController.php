<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccountState\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentityAccountState\Application\AccountStateHttpRepository;
use Qmdb\Modules\IdentityAccountState\Application\AccountStateOperationCommand;
use Qmdb\Modules\IdentityAccountState\Application\AccountStateOperationService;
use Qmdb\Modules\IdentityAccountState\Domain\AccountStateJustification;
use Qmdb\Modules\IdentityAccountState\Domain\AccountStateOperationType;
use Qmdb\Modules\IdentityAccountState\Domain\AccountStateReasonCode;
use Qmdb\Modules\IdentityAccountState\Domain\AccountStateReference;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditRepository;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Application\BaseRoleAuthorizationGuard;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class AccountStateSecurityController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $authentication,
        private BaseRoleAuthorizationGuard $authorization,
        private IdentityCsrf $csrf,
        private IdentityAccessView $view,
        private Psr17Factory $responses,
        private AccountStateHttpRepository $accounts,
        private AccountStateOperationService $operations,
        private SecurityAuditRepository $audit,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = $this->authentication->context($request);
        if ($actor === null) {
            return $this->authentication->rejection($request);
        }
        try {
            $this->authorization->requireAllowed(new AuthorizationRequest(
                AuthorizationSubject::fromAuthenticatedContext($actor),
                new PermissionCode('platform.accounts.view'),
                new PlatformAuthorizationScope(),
            ));
            $accountId = $this->parameter($request, 'accountId');
            UuidV7::fromString($accountId);
            $account = $this->accounts->findSafeAccount($accountId);
            if ($account === null) {
                return $this->responses->createResponse(404)->withHeader('Cache-Control', 'private, no-store');
            }
            $route = $request->getAttribute(RouteAttributes::NAME);
            if (!is_string($route)) {
                return $this->responses->createResponse(404);
            }
            if ($route === 'platform.security.accounts.detail') {
                return $this->detail($request, $account);
            }
            $operation = $route === 'platform.security.accounts.suspend' ? AccountStateOperationType::SUSPEND : AccountStateOperationType::REACTIVATE;
            if (strtoupper($request->getMethod()) === 'GET') {
                return $this->form($request, $account, $operation);
            }

            return $this->submit($request, $actor, $account, $operation);
        } catch (\Qmdb\Modules\SecurityAuthorization\Application\Exception\AuthorizationDeniedException) {
            return $this->responses->createResponse(403)->withHeader('Cache-Control', 'private, no-store');
        } catch (\InvalidArgumentException) {
            return $this->responses->createResponse(422)->withHeader('Cache-Control', 'private, no-store');
        }
    }

    /** @param array{public_id:string,status:string,version:int,created_at:string,updated_at:string,activated_at:?string,suspended_at:?string} $account */
    private function detail(ServerRequestInterface $request, array $account): ResponseInterface
    {
        $events = $this->audit->listForAccount($account['public_id'], null, 20);
        $data = new ViewData(['account' => $account, 'events' => array_map(static fn ($event): array => [
            'code' => $event->eventCode, 'severity' => $event->severity->value, 'outcome' => $event->outcome->value,
            'occurred_at' => $event->occurredAt->format('Y-m-d H:i:s T'),
        ], $events->events)]);

        return $this->view->render($request, 'pages.account-state-detail', 'fragments.account-state-panel', $data, 'account_state.detail')
            ->withHeader('Cache-Control', 'private, no-store');
    }

    /** @param array{public_id:string,status:string,version:int,created_at:string,updated_at:string,activated_at:?string,suspended_at:?string} $account */
    private function form(ServerRequestInterface $request, array $account, AccountStateOperationType $operation): ResponseInterface
    {
        if (
            ($operation === AccountStateOperationType::SUSPEND && $account['status'] !== 'ACTIVE')
            || ($operation === AccountStateOperationType::REACTIVATE && $account['status'] !== 'SUSPENDED')
        ) {
            return $this->responses->createResponse(409)->withHeader('Cache-Control', 'private, no-store');
        }
        $action = $operation === AccountStateOperationType::SUSPEND ? CsrfAction::ACCOUNT_STATE_SUSPEND : CsrfAction::ACCOUNT_STATE_REACTIVATE;
        $csrf = $this->csrf->issue($request, $action);
        $data = new ViewData([
            'account' => $account, 'operation' => $operation->value, 'csrf_token' => $csrf['token'],
            'submission_id' => UuidV7::generate()->toString(), 'reason_codes' => array_map(
                static fn (AccountStateReasonCode $reason): string => $reason->value,
                array_values(array_filter(AccountStateReasonCode::cases(), static fn (AccountStateReasonCode $reason): bool => $reason->permits($operation))),
            ),
        ]);

        return $this->view->render($request, 'pages.account-state-operation', 'fragments.account-state-operation-form', $data, 'account_state.operation', cookie: $csrf['cookie'])
            ->withHeader('Cache-Control', 'private, no-store');
    }

    /** @param array{public_id:string,status:string,version:int,created_at:string,updated_at:string,activated_at:?string,suspended_at:?string} $account */
    private function submit(ServerRequestInterface $request, \Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext $actor, array $account, AccountStateOperationType $operation): ResponseInterface
    {
        if (strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0])) !== 'application/x-www-form-urlencoded') {
            return $this->responses->createResponse(415)->withHeader('Cache-Control', 'private, no-store');
        }
        $body = $this->formBody($request->getParsedBody());
        if ($body === null) {
            return $this->responses->createResponse(422)->withHeader('Cache-Control', 'private, no-store');
        }
        $action = $operation === AccountStateOperationType::SUSPEND ? CsrfAction::ACCOUNT_STATE_SUSPEND : CsrfAction::ACCOUNT_STATE_REACTIVATE;
        $csrf = $this->csrf->issue($request, $action);
        if (!$this->csrf->validates($request, $action, $csrf['cookie'], $this->field($body, 'csrf_token', 512))) {
            return $this->responses->createResponse(403)->withHeader('Cache-Control', 'private, no-store');
        }
        try {
            $this->operations->execute(new AccountStateOperationCommand(
                $actor,
                $operation,
                UuidV7::fromString($account['public_id']),
                $this->positiveInteger($this->field($body, 'expected_account_version', 10)),
                UuidV7::fromString($this->field($body, 'submission_id', 36)),
                AccountStateReasonCode::from($this->field($body, 'reason_code', 48)),
                new AccountStateJustification($this->field($body, 'justification', 2000), 2000),
                new AccountStateReference($this->optionalField($body, 'reference_code', 128), 128),
                null,
            ));
        } catch (\Qmdb\Modules\SecurityAuthorization\Application\Exception\AuthorizationDeniedException) {
            return $this->responses->createResponse(403)->withHeader('Cache-Control', 'private, no-store');
        } catch (\DomainException $exception) {
            return $this->responses->createResponse(str_contains($exception->getMessage(), 'temporarily') ? 429 : 409)
                ->withHeader('Cache-Control', 'private, no-store');
        }

        return $this->view->redirect('/platform/security/accounts/' . rawurlencode($account['public_id']), $csrf['cookie'])
            ->withHeader('Cache-Control', 'private, no-store');
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

    /** @param array<string, mixed> $body */
    private function field(array $body, string $name, int $maximum): string
    {
        $value = $body[$name] ?? null;
        if (!is_string($value) || $value === '' || strlen($value) > $maximum) {
            throw new \InvalidArgumentException('Account-state form is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $body */
    private function optionalField(array $body, string $name, int $maximum): ?string
    {
        $value = $body[$name] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || strlen($value) > $maximum) {
            throw new \InvalidArgumentException('Account-state form is invalid.');
        }

        return $value;
    }

    private function positiveInteger(string $value): int
    {
        if (preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
            throw new \InvalidArgumentException('Account-state version is invalid.');
        }

        return (int) $value;
    }

    /** @return array<string, mixed>|null */
    private function formBody(mixed $body): ?array
    {
        if (!is_array($body)) {
            return null;
        }
        $result = [];
        foreach ($body as $key => $value) {
            if (!is_string($key)) {
                return null;
            }
            $result[$key] = $value;
        }

        return $result;
    }
}
