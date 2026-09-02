<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Interface\Http;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\OrganizationAffiliations\Application\OrganizationAffiliationInput;
use Qmdb\Modules\OrganizationAffiliations\Application\OrganizationAffiliationRepository;
use Qmdb\Modules\OrganizationAffiliations\Application\OrganizationAffiliationService;
use Qmdb\Modules\OrganizationAffiliations\Configuration\OrganizationAffiliationsConfiguration;
use Qmdb\Modules\OrganizationAffiliations\Domain\OrganizationAffiliationSubmissionId;
use Qmdb\Modules\SecurityAuthorization\Application\Exception\AuthorizationDeniedException;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Modules\TenancyContext\Application\Exception\TenantContextRequiredException;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;

/**
 * Private, server-rendered organization affiliation workflow. It never exposes
 * a directory, person discovery endpoint, or an invitation contact channel.
 */
/**
 * @phpstan-import-type Row from OrganizationAffiliationRepository
 * @phpstan-type DetailRow array<string, int|string|null|list<Row>>
 */
final readonly class OrganizationAffiliationsController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $authentication,
        private TenantContextRequiredGuard $tenant,
        private IdentityCsrf $csrf,
        private IdentityAccessView $view,
        private OrganizationAffiliationService $service,
        private OrganizationAffiliationsConfiguration $configuration,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = $this->authentication->context($request);
        if ($actor === null) {
            return $this->authentication->rejection($request);
        }
        $route = $request->getAttribute(RouteAttributes::NAME);
        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS, []);
        if (!is_string($route)) {
            return $this->response(404, 'Affiliation route is unavailable.');
        }
        $parameters = $this->namedArray($parameters, 'Affiliation route is unavailable.');

        $action = $this->csrfAction($route);
        $csrf = $this->csrf->issue($request, $action);
        try {
            if ($request->getMethod() === 'POST') {
                $body = $request->getParsedBody();
                $token = is_array($body) && is_string($body['csrf_token'] ?? null) ? $body['csrf_token'] : '';
                if (!$this->csrf->validates($request, $action, $csrf['cookie'], $token)) {
                    return $this->secure($this->response(403, 'Request verification failed.'), $csrf['cookie']);
                }
                $result = str_starts_with($route, 'workspace.')
                    ? $this->workspacePost($request, $route, $parameters, $actor)
                    : $this->accountPost($request, $route, $parameters, $actor);
            } else {
                $result = str_starts_with($route, 'workspace.')
                    ? $this->workspaceGet($request, $route, $parameters, $actor, $csrf['token'])
                    : $this->accountGet($request, $route, $parameters, $actor, $csrf['token']);
            }

            return $this->secure($result, $csrf['cookie']);
        } catch (AuthorizationDeniedException) {
            return $this->secure($this->response(404, 'Affiliation is unavailable.'), $csrf['cookie']);
        } catch (\Throwable) {
            return $this->secure($this->response(422, 'Affiliation changes could not be saved.'), $csrf['cookie']);
        }
    }

    /** @param array<string,mixed> $parameters */
    private function workspaceGet(ServerRequestInterface $request, string $route, array $parameters, AuthenticatedAccountContext $actor, string $csrf): ResponseInterface
    {
        $tenant = $this->workspaceTenant($request);
        if (!$tenant instanceof AccountWorkspaceTenantContext) {
            return $this->redirect('/account/workspaces?context_required=1');
        }
        $organizationId = $this->parameter($parameters, 'organizationId');
        if ($route === 'workspace.organizations.affiliations.index') {
            $status = $this->optionalText($this->namedArray($request->getQueryParams(), 'Affiliation filter is invalid.'), 'status') ?? 'ALL';
            $roster = $this->service->organizationRoster($actor, $tenant, $organizationId, $status);
            $panel = $this->rosterPanel($organizationId, $roster['affiliations']);
            if ($this->isFragment($request)) {
                return $this->fragment($panel);
            }

            return $this->page('Organization affiliations', $this->rosterControls($organizationId, $status) . $panel . '<p><a data-qmdb-modal data-qmdb-modal-fallback href="/workspace/organizations/' . rawurlencode($organizationId) . '/affiliations/request">Request an affiliation</a></p>');
        }
        if ($route === 'workspace.organizations.affiliations.request.form') {
            return $this->assignmentForm($request, 'Request organization affiliation', '/workspace/organizations/' . rawurlencode($organizationId) . '/affiliations/request', $csrf, null, true);
        }
        $affiliationId = $this->parameter($parameters, 'affiliationId');
        $affiliation = $this->service->organizationAffiliation($actor, $tenant, $organizationId, $affiliationId);
        if ($affiliation === null) {
            return $this->response(404, 'Affiliation is unavailable.');
        }
        if ($route === 'workspace.organizations.affiliations.view') {
            return $this->page('Organization affiliation', $this->affiliationDetails($organizationId, $affiliation));
        }
        if ($route === 'workspace.organizations.affiliations.assignments.form') {
            return $this->assignmentForm($request, 'Update affiliation assignments', '/workspace/organizations/' . rawurlencode($organizationId) . '/affiliations/' . rawurlencode($affiliationId) . '/assignments', $csrf, $affiliation, false);
        }

        $operation = match ($route) {
            'workspace.organizations.affiliations.withdraw.form' => 'WITHDRAW',
            'workspace.organizations.affiliations.suspend.form' => 'SUSPEND',
            'workspace.organizations.affiliations.resume.form' => 'RESUME',
            'workspace.organizations.affiliations.end.form' => 'END',
            default => throw new \InvalidArgumentException('Affiliation route is invalid.'),
        };

        return $this->operationForm(
            $request,
            'Confirm ' . strtolower($operation),
            '/workspace/organizations/' . rawurlencode($organizationId) . '/affiliations/' . rawurlencode($affiliationId) . '/' . strtolower($operation),
            $csrf,
            $this->integerValue($affiliation, 'version'),
            $operation,
        );
    }

    /** @param array<string,mixed> $parameters */
    private function workspacePost(ServerRequestInterface $request, string $route, array $parameters, AuthenticatedAccountContext $actor): ResponseInterface
    {
        $tenant = $this->workspaceTenant($request);
        if (!$tenant instanceof AccountWorkspaceTenantContext) {
            return $this->redirect('/account/workspaces?context_required=1');
        }
        $body = $this->body($request);
        $organizationId = $this->parameter($parameters, 'organizationId');
        $submission = OrganizationAffiliationSubmissionId::fromString($this->value($body, 'submission_id'));
        if ($route === 'workspace.organizations.affiliations.request.submit') {
            $this->service->request($actor, $tenant, $submission, $organizationId, $this->value($body, 'person_registry_code'), $this->input($body));

            return $this->mutationRedirect($request, '/workspace/organizations/' . rawurlencode($organizationId) . '/affiliations?requested=1');
        }
        $affiliationId = $this->parameter($parameters, 'affiliationId');
        $version = $this->number($body, 'expected_version');
        if ($route === 'workspace.organizations.affiliations.assignments.submit') {
            $this->service->updateAssignments($actor, $tenant, $submission, $organizationId, $affiliationId, $version, $this->input($body));

            return $this->mutationRedirect($request, '/workspace/organizations/' . rawurlencode($organizationId) . '/affiliations/' . rawurlencode($affiliationId));
        }
        $operation = match ($route) {
            'workspace.organizations.affiliations.withdraw.submit' => 'WITHDRAW',
            'workspace.organizations.affiliations.suspend.submit' => 'SUSPEND',
            'workspace.organizations.affiliations.resume.submit' => 'RESUME',
            'workspace.organizations.affiliations.end.submit' => 'END',
            default => throw new \InvalidArgumentException('Affiliation route is invalid.'),
        };
        $reason = $operation === 'SUSPEND' ? $this->value($body, 'reason_code') : 'NOT_APPLICABLE';
        $this->service->organizationTransition($actor, $tenant, $submission, $organizationId, $affiliationId, $operation, $version, $reason);

        return $this->mutationRedirect($request, '/workspace/organizations/' . rawurlencode($organizationId) . '/affiliations/' . rawurlencode($affiliationId));
    }

    /** @param array<string,mixed> $parameters */
    private function accountGet(ServerRequestInterface $request, string $route, array $parameters, AuthenticatedAccountContext $actor, string $csrf): ResponseInterface
    {
        $affiliations = $this->service->accountAffiliations($actor);
        if ($route === 'account.affiliations.index') {
            return $this->page('My organization affiliations', $this->accountList($affiliations));
        }
        $affiliationId = $this->parameter($parameters, 'affiliationId');
        $affiliation = $this->accountAffiliation($affiliations, $affiliationId);
        if ($affiliation === null) {
            return $this->response(404, 'Affiliation is unavailable.');
        }
        if ($route === 'account.affiliations.detail') {
            return $this->page('Organization affiliation', $this->accountDetail($affiliation));
        }
        $operation = match ($route) {
            'account.affiliations.accept.form' => 'ACCEPT',
            'account.affiliations.decline.form' => 'DECLINE',
            'account.affiliations.leave.form' => 'LEAVE',
            default => throw new \InvalidArgumentException('Affiliation route is invalid.'),
        };

        return $this->operationForm($request, 'Confirm ' . strtolower($operation), '/account/affiliations/' . rawurlencode($affiliationId) . '/' . strtolower($operation), $csrf, $this->integerValue($affiliation, 'version'), $operation);
    }

    /** @param array<string,mixed> $parameters */
    private function accountPost(ServerRequestInterface $request, string $route, array $parameters, AuthenticatedAccountContext $actor): ResponseInterface
    {
        $body = $this->body($request);
        $operation = match ($route) {
            'account.affiliations.accept.submit' => 'ACCEPT',
            'account.affiliations.decline.submit' => 'DECLINE',
            'account.affiliations.leave.submit' => 'LEAVE',
            default => throw new \InvalidArgumentException('Affiliation route is invalid.'),
        };
        $affiliationId = $this->parameter($parameters, 'affiliationId');
        $this->service->respond($actor, OrganizationAffiliationSubmissionId::fromString($this->value($body, 'submission_id')), $affiliationId, $operation);

        return $this->mutationRedirect($request, '/account/affiliations');
    }

    /** @param DetailRow|null $affiliation */
    private function assignmentForm(ServerRequestInterface $request, string $title, string $action, string $csrf, ?array $affiliation, bool $isRequest): ResponseInterface
    {
        $existing = $affiliation === null ? [] : $this->assignments($affiliation);
        $primary = $existing === [] ? 'SUPPORT_STAFF' : $this->textValue($existing[0], 'code', 'SUPPORT_STAFF');
        $unit = $existing === [] ? '' : $this->textValue($existing[0], 'unit_assignment_public_id');
        $person = $isRequest ? '<label>Person registry code<input name="person_registry_code" required autocomplete="off" pattern="QMP-[A-Z2-9]{16}"></label>' : '';
        $warning = $isRequest ? '<p>Use an existing registry code. This form does not search for people or send an invitation.</p>' : '<p>Changing leadership requires phishing-resistant step-up authentication.</p>';
        $options = $this->roleOptions($primary);
        $submission = OrganizationAffiliationSubmissionId::generate()->toString();
        $version = $affiliation === null ? 0 : $this->integerValue($affiliation, 'version');
        $form = '<section data-qmdb-form-region><form method="post" data-qmdb-progressive-form data-qmdb-close-modal-on-success="true" action="' . self::e($action) . '"><input type="hidden" name="csrf_token" value="' . self::e($csrf) . '"><input type="hidden" name="submission_id" data-qmdb-idempotency-key value="' . self::e($submission) . '"><input type="hidden" name="expected_version" value="' . $version . '">' . $person . '<label>Primary affiliation role<select name="role_code">' . $options . '</select></label><label>Additional roles, one exact code per line (optional)<textarea name="additional_role_codes" rows="4" maxlength="575" spellcheck="false" autocomplete="off"></textarea></label><label>Primary active organization unit public ID (optional)<input name="unit_public_id" value="' . self::e($unit) . '" autocomplete="off"></label><label>Additional active unit public IDs, one per line (optional)<textarea name="additional_unit_public_ids" rows="3" maxlength="1849" spellcheck="false" autocomplete="off"></textarea></label><label>Primary role title (optional)<input name="title" maxlength="' . $this->configuration->titleMaximumBytes . '"></label>' . $warning . '<button type="submit">' . ($isRequest ? 'Request affiliation' : 'Update assignments') . '</button></form></section>';

        return $this->formResponse($request, $title, $form);
    }

    private function operationForm(ServerRequestInterface $request, string $title, string $action, string $csrf, int $version, string $operation): ResponseInterface
    {
        $reason = $operation === 'SUSPEND' ? '<label>Suspension reason code<select name="reason_code"><option value="CONDUCT_REVIEW">Conduct review</option><option value="SAFEGUARDING_REVIEW">Safeguarding review</option><option value="ADMINISTRATIVE_REVIEW">Administrative review</option></select></label>' : '';

        $submission = OrganizationAffiliationSubmissionId::generate()->toString();
        $form = '<section data-qmdb-form-region><form method="post" data-qmdb-progressive-form data-qmdb-close-modal-on-success="true" action="' . self::e($action) . '"><input type="hidden" name="csrf_token" value="' . self::e($csrf) . '"><input type="hidden" name="submission_id" data-qmdb-idempotency-key value="' . self::e($submission) . '"><input type="hidden" name="expected_version" value="' . $version . '">' . $reason . '<p>This private lifecycle action is recorded in the affiliation history.</p><button type="submit">Confirm ' . self::e(strtolower($operation)) . '</button></form></section>';

        return $this->formResponse($request, $title, $form);
    }

    /** @param array<string,mixed> $body */
    private function input(array $body): OrganizationAffiliationInput
    {
        $role = $this->value($body, 'role_code');
        $unit = $this->optionalText($body, 'unit_public_id') ?? '';
        $title = $this->optionalText($body, 'title') ?? '';
        $roles = [[
            'code' => $role,
            'is_primary' => true,
            'unit_public_id' => $unit === '' ? null : $unit,
            'title' => $title === '' ? null : $title,
        ]];
        foreach ($this->lines($body['additional_role_codes'] ?? null) as $additionalRole) {
            $roles[] = [
                'code' => $additionalRole,
                'is_primary' => false,
                'unit_public_id' => null,
                'title' => null,
            ];
        }
        $units = $unit === '' ? [] : [['public_id' => $unit, 'is_primary' => true]];
        foreach ($this->lines($body['additional_unit_public_ids'] ?? null) as $additionalUnit) {
            $units[] = ['public_id' => $additionalUnit, 'is_primary' => false];
        }

        return OrganizationAffiliationInput::fromBody([
            'roles' => $roles,
            'units' => $units,
        ], $this->configuration);
    }

    /** @return list<string> */
    private function lines(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Assignment input is invalid.');
        }

        return array_values(array_filter(array_map(static fn (string $line): string => trim($line), preg_split('/\R/', $value) ?: []), static fn (string $line): bool => $line !== ''));
    }

    private function rosterControls(string $organizationId, string $selected): string
    {
        $base = '/workspace/organizations/' . rawurlencode($organizationId) . '/affiliations';
        $options = '';
        foreach (['ALL' => 'All records', 'PENDING_ACCEPTANCE' => 'Pending acceptance', 'ACTIVE' => 'Active', 'SUSPENDED' => 'Suspended', 'DECLINED' => 'Declined', 'WITHDRAWN' => 'Withdrawn', 'EXPIRED' => 'Expired', 'ENDED' => 'Ended'] as $value => $label) {
            $options .= '<option value="' . $value . '"' . ($value === $selected ? ' selected' : '') . '>' . $label . '</option>';
        }

        return '<form method="get" action="' . self::e($base) . '"><label>Roster status<select name="status" data-qmdb-affiliation-status-filter>' . $options . '</select></label><button type="submit">Filter roster</button></form><p><a data-qmdb-refresh data-qmdb-refresh-target="#organization-affiliation-roster" data-qmdb-affiliation-refresh href="' . self::e($base . '?status=' . rawurlencode($selected)) . '">Refresh roster</a></p>';
    }

    /** @param list<Row> $records */
    private function rosterPanel(string $organizationId, array $records): string
    {
        return '<section id="organization-affiliation-roster" data-qmdb-fragment-root tabindex="-1">' . $this->roster($organizationId, $records) . '</section>';
    }

    /** @param list<Row> $records */
    private function roster(string $organizationId, array $records): string
    {
        $html = '<table><thead><tr><th>Affiliation</th><th>Role</th><th>Status</th><th>Person</th></tr></thead><tbody>';
        foreach ($records as $record) {
            $id = $this->textValue($record, 'public_id');
            $html .= '<tr><td><a href="/workspace/organizations/' . rawurlencode($organizationId) . '/affiliations/' . rawurlencode($id) . '">' . self::e($this->textValue($record, 'affiliation_code')) . '</a></td><td>' . self::e($this->textValue($record, 'primary_role')) . '</td><td>' . self::e($this->textValue($record, 'status')) . '</td><td>' . self::e($this->textValue($record, 'display_name', 'Withheld')) . '</td></tr>';
        }

        return $html . '</tbody></table>';
    }

    /** @param DetailRow $affiliation */
    private function affiliationDetails(string $organizationId, array $affiliation): string
    {
        $id = $this->textValue($affiliation, 'public_id');
        $roles = '';
        foreach ($this->assignments($affiliation) as $assignment) {
            $roles .= '<li>' . self::e($this->textValue($assignment, 'code')) . ' — ' . self::e($this->textValue($assignment, 'status')) . '</li>';
        }

        $base = '/workspace/organizations/' . rawurlencode($organizationId) . '/affiliations/' . rawurlencode($id);

        return '<dl><dt>Affiliation code</dt><dd>' . self::e($this->textValue($affiliation, 'affiliation_code')) . '</dd><dt>Status</dt><dd>' . self::e($this->textValue($affiliation, 'status')) . '</dd><dt>Version</dt><dd>' . $this->integerValue($affiliation, 'version') . '</dd></dl><h2>Assignments</h2><ul>' . $roles . '</ul><p><a data-qmdb-modal data-qmdb-modal-fallback href="' . self::e($base . '/assignments') . '">Update assignments</a> · <a data-qmdb-modal data-qmdb-modal-fallback href="' . self::e($base . '/withdraw') . '">Withdraw</a> · <a data-qmdb-modal data-qmdb-modal-fallback href="' . self::e($base . '/suspend') . '">Suspend</a> · <a data-qmdb-modal data-qmdb-modal-fallback href="' . self::e($base . '/resume') . '">Resume</a> · <a data-qmdb-modal data-qmdb-modal-fallback href="' . self::e($base . '/end') . '">End</a></p>';
    }

    /** @param list<Row> $affiliations */
    private function accountList(array $affiliations): string
    {
        $html = '<p>Only affiliations you may act on are listed. Guardian-managed dependent entries are governed by active guardianship authority.</p><table><thead><tr><th>Organization</th><th>Affiliation</th><th>Status</th></tr></thead><tbody>';
        foreach ($affiliations as $affiliation) {
            $id = $this->textValue($affiliation, 'public_id');
            $html .= '<tr><td>' . self::e($this->textValue($affiliation, 'organization_name')) . '</td><td><a href="/account/affiliations/' . rawurlencode($id) . '">' . self::e($this->textValue($affiliation, 'affiliation_code')) . '</a></td><td>' . self::e($this->textValue($affiliation, 'status')) . '</td></tr>';
        }

        return $html . '</tbody></table>';
    }

    /** @param Row $affiliation */
    private function accountDetail(array $affiliation): string
    {
        $id = $this->textValue($affiliation, 'public_id');
        $actions = '';
        if ($affiliation['status'] === 'PENDING_ACCEPTANCE') {
            $actions = '<a data-qmdb-modal data-qmdb-modal-fallback href="/account/affiliations/' . rawurlencode($id) . '/accept">Accept</a> · <a data-qmdb-modal data-qmdb-modal-fallback href="/account/affiliations/' . rawurlencode($id) . '/decline">Decline</a>';
        } elseif (in_array($affiliation['status'], ['ACTIVE', 'SUSPENDED'], true)) {
            $actions = '<a data-qmdb-modal data-qmdb-modal-fallback href="/account/affiliations/' . rawurlencode($id) . '/leave">Leave organization</a>';
        }

        return '<dl><dt>Organization</dt><dd>' . self::e($this->textValue($affiliation, 'organization_name')) . '</dd><dt>Affiliation code</dt><dd>' . self::e($this->textValue($affiliation, 'affiliation_code')) . '</dd><dt>Status</dt><dd>' . self::e($this->textValue($affiliation, 'status')) . '</dd></dl><p>' . $actions . '</p>';
    }

    private function roleOptions(string $selected): string
    {
        $roles = ['MEMBER', 'STUDENT', 'MEMORIZER', 'RECITER', 'TEACHER', 'QURAN_TEACHER', 'IMAM', 'MUADHDHIN', 'STAFF', 'VOLUNTEER', 'LEADER', 'REPRESENTATIVE'];
        $html = '';
        foreach ($roles as $role) {
            $html .= '<option value="' . $role . '"' . ($role === $selected ? ' selected' : '') . '>' . self::e(str_replace('_', ' ', $role)) . '</option>';
        }

        return $html;
    }

    private function workspaceTenant(ServerRequestInterface $request): ?AccountWorkspaceTenantContext
    {
        try {
            return $this->tenant->require($request, true);
        } catch (TenantContextRequiredException) {
            return null;
        }
    }

    /**
     * @param list<Row> $affiliations
     *
     * @return Row|null
     */
    private function accountAffiliation(array $affiliations, string $publicId): ?array
    {
        foreach ($affiliations as $affiliation) {
            if (($affiliation['public_id'] ?? null) === $publicId) {
                return $affiliation;
            }
        }

        return null;
    }

    /**
     * @param Row|DetailRow $record
     */
    private function textValue(array $record, string $key, string $default = ''): string
    {
        $value = $record[$key] ?? null;
        if ($value === null) {
            return $default;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value)) {
            return (string) $value;
        }

        throw new \InvalidArgumentException('Affiliation display value is invalid.');
    }

    /**
     * @param Row|DetailRow $record
     */
    private function integerValue(array $record, string $key): int
    {
        $value = $record[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/\A-?[0-9]+\z/', $value) !== 1) {
            throw new \InvalidArgumentException('Affiliation numeric value is invalid.');
        }

        return (int) $value;
    }

    /**
     * @param DetailRow $record
     *
     * @return list<Row>
     */
    private function assignments(array $record): array
    {
        $value = $record['assignments'] ?? [];
        if (!is_array($value)) {
            throw new \InvalidArgumentException('Affiliation assignments are invalid.');
        }

        return $value;
    }

    private function csrfAction(string $route): CsrfAction
    {
        return match ($route) {
            'workspace.organizations.affiliations.request.form', 'workspace.organizations.affiliations.request.submit' => CsrfAction::ORGANIZATION_AFFILIATION_REQUEST,
            'workspace.organizations.affiliations.assignments.form', 'workspace.organizations.affiliations.assignments.submit' => CsrfAction::ORGANIZATION_AFFILIATION_ASSIGNMENTS_UPDATE,
            'workspace.organizations.affiliations.withdraw.form', 'workspace.organizations.affiliations.withdraw.submit' => CsrfAction::ORGANIZATION_AFFILIATION_WITHDRAW,
            'workspace.organizations.affiliations.suspend.form', 'workspace.organizations.affiliations.suspend.submit' => CsrfAction::ORGANIZATION_AFFILIATION_SUSPEND,
            'workspace.organizations.affiliations.resume.form', 'workspace.organizations.affiliations.resume.submit' => CsrfAction::ORGANIZATION_AFFILIATION_RESUME,
            'workspace.organizations.affiliations.end.form', 'workspace.organizations.affiliations.end.submit' => CsrfAction::ORGANIZATION_AFFILIATION_END,
            'account.affiliations.accept.form', 'account.affiliations.accept.submit' => CsrfAction::ORGANIZATION_AFFILIATION_ACCEPT,
            'account.affiliations.decline.form', 'account.affiliations.decline.submit' => CsrfAction::ORGANIZATION_AFFILIATION_DECLINE,
            'account.affiliations.leave.form', 'account.affiliations.leave.submit' => CsrfAction::ORGANIZATION_AFFILIATION_LEAVE,
            default => CsrfAction::ORGANIZATION_AFFILIATION_REQUEST,
        };
    }

    /** @param array<string,mixed> $parameters */
    private function parameter(array $parameters, string $name): string
    {
        return is_string($parameters[$name] ?? null) ? $parameters[$name] : '';
    }

    /** @return array<string,mixed> */
    private function body(ServerRequestInterface $request): array
    {
        return $this->namedArray($request->getParsedBody(), 'Request is invalid.');
    }

    /** @return array<string, mixed> */
    private function namedArray(mixed $value, string $message): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException($message);
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException($message);
            }
            $result[$key] = $item;
        }

        return $result;
    }

    /** @param array<string,mixed> $body */
    private function value(array $body, string $name): string
    {
        $value = $body[$name] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException($name . ' is invalid.');
        }

        return trim($value);
    }

    /** @param array<string, mixed> $body */
    private function optionalText(array $body, string $name): ?string
    {
        $value = $body[$name] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException($name . ' is invalid.');
        }

        return trim($value);
    }

    /** @param array<string,mixed> $body */
    private function number(array $body, string $name): int
    {
        $value = $body[$name] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/\A[0-9]+\z/', $value) !== 1) {
            throw new \InvalidArgumentException($name . ' is invalid.');
        }

        return (int) $value;
    }

    private function formResponse(ServerRequestInterface $request, string $title, string $form): ResponseInterface
    {
        if ($this->isFragment($request)) {
            return $this->fragment('<section data-qmdb-fragment-root data-qmdb-dialog-title="' . self::e($title) . '">' . $form . '</section>');
        }

        return $this->page($title, $form);
    }

    private function mutationRedirect(ServerRequestInterface $request, string $path): ResponseInterface
    {
        if ($this->isFragment($request)) {
            return $this->fragment('<section data-qmdb-fragment-root data-qmdb-completion-heading tabindex="-1">Request completed.</section>')->withHeader('X-QMDB-Navigate', $path);
        }

        return $this->redirect($path);
    }

    private function isFragment(ServerRequestInterface $request): bool
    {
        return str_contains(strtolower($request->getHeaderLine('Accept')), 'text/vnd.qmdb.fragment+html');
    }

    private function fragment(string $body): ResponseInterface
    {
        return (new Response(200, [
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Content-Type' => 'text/vnd.qmdb.fragment+html; charset=utf-8',
            'X-QMDB-Fragment' => '1',
        ]))->withBody(Stream::create($body));
    }

    private function page(string $title, string $body): ResponseInterface
    {
        return $this->response(200, '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>' . self::e($title) . '</title><style>body{font-family:system-ui;max-width:70rem;margin:2rem auto;padding:0 1rem}table{border-collapse:collapse;width:100%}td,th{border-bottom:1px solid #ddd;padding:.6rem;text-align:left}label{display:block;margin:.7rem 0}input,select{width:100%;max-width:35rem;padding:.5rem}dialog{max-width:42rem;width:calc(100% - 2rem)}</style></head><body><div id="qmdb-live-region" aria-live="polite" aria-atomic="true" class="sr-only"></div><main><h1>' . self::e($title) . '</h1><p>Private consent-based affiliation records. Identifiers never grant access.</p>' . $body . '</main><dialog id="qmdb-dialog" aria-labelledby="qmdb-dialog-title"><header><h2 id="qmdb-dialog-title">System details</h2><button type="button" data-qmdb-modal-close>Close</button></header><p data-qmdb-modal-loading hidden>Loading…</p><div data-qmdb-modal-error role="alert" hidden tabindex="-1"></div><div data-qmdb-modal-content></div><p><a data-qmdb-modal-fallback href="#">Open without enhanced controls</a></p></dialog><script type="module" src="/assets/js/app.js"></script></body></html>');
    }

    private function secure(ResponseInterface $response, \Qmdb\Modules\SecurityWeb\Csrf\CsrfCookie $cookie): ResponseInterface
    {
        return $this->view->secure($response, $cookie, true)->withHeader('Cache-Control', 'private, no-store');
    }

    private function response(int $status, string $body): ResponseInterface
    {
        return (new Response($status, ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow', 'Content-Type' => 'text/html; charset=utf-8']))->withBody(Stream::create($body));
    }

    private function redirect(string $path): ResponseInterface
    {
        return new Response(303, ['Location' => $path, 'Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
