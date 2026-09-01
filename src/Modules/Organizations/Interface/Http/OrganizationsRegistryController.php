<?php

declare(strict_types=1);

namespace Qmdb\Modules\Organizations\Interface\Http;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\Organizations\Application\OrganizationInput;
use Qmdb\Modules\Organizations\Application\OrganizationsRegistryService;
use Qmdb\Modules\Organizations\Configuration\OrganizationsRegistryConfiguration;
use Qmdb\Modules\Organizations\Domain\OrganizationSubmissionId;
use Qmdb\Modules\SecurityAuthorization\Application\Exception\AuthorizationDeniedException;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Modules\TenancyContext\Application\Exception\TenantContextRequiredException;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;

/** Private server-rendered workspace registry. Public identifiers are not authorization grants. */
final readonly class OrganizationsRegistryController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $authentication,
        private TenantContextRequiredGuard $tenant,
        private IdentityCsrf $csrf,
        private IdentityAccessView $view,
        private OrganizationsRegistryService $service,
        private OrganizationsRegistryConfiguration $configuration,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = $this->authentication->context($request);
        if ($actor === null) return $this->authentication->rejection($request);
        try { $tenant = $this->tenant->require($request, true); } catch (TenantContextRequiredException) { return $this->redirect('/account/workspaces?context_required=1'); }
        $route = $request->getAttribute(RouteAttributes::NAME);
        $params = $request->getAttribute(RouteAttributes::PARAMETERS, []);
        if (!is_string($route) || !is_array($params)) return $this->response(404, 'Organization route is unavailable.');
        $action = $this->csrfAction($route);
        $csrf = $this->csrf->issue($request, $action);
        try {
            if ($request->getMethod() === 'POST') {
                $body = $request->getParsedBody();
                $token = is_array($body) && is_string($body['csrf_token'] ?? null) ? $body['csrf_token'] : '';
                if (!$this->csrf->validates($request, $action, $csrf['cookie'], $token)) return $this->secure($this->response(403, 'Request verification failed.'), $csrf['cookie']);
                $result = $this->post($request, $route, $params, $actor, $tenant);
            } else $result = $this->get($request, $route, $params, $actor, $tenant, $csrf['token']);
            return $this->secure($result, $csrf['cookie']);
        } catch (AuthorizationDeniedException) { return $this->secure($this->response(404, 'Organization is unavailable.'), $csrf['cookie']); }
        catch (\Throwable) { return $this->secure($this->response(422, 'Organization changes could not be saved.'), $csrf['cookie']); }
    }

    /** @param array<string,mixed> $parameters */
    private function get(ServerRequestInterface $request, string $route, array $parameters, AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant, string $csrf): ResponseInterface
    {
        if ($route === 'workspace.organizations.index') return $this->page('Organizations', $this->organizationList($this->service->list($actor, $tenant, (string)($request->getQueryParams()['q'] ?? ''))));
        if ($route === 'workspace.organizations.create.form') return $this->form('Create Organization', '/workspace/organizations', [], $csrf, false);
        $organizationId = $this->parameter($parameters, 'organizationId');
        $organization = $this->service->organization($actor, $tenant, $organizationId);
        if ($organization === null) return $this->response(404, 'Organization is unavailable.');
        if (str_starts_with($route, 'workspace.organizations.units.')) {
            if ($route === 'workspace.organizations.units.create.form') return $this->form('Create Organization Unit', '/workspace/organizations/' . rawurlencode($organizationId) . '/units', $organization, $csrf, true);
            $unitId = $this->parameter($parameters, 'unitId');
            $unit = $this->service->units($actor, $tenant, $organizationId);
            foreach ($unit as $candidate) if (($candidate['public_id'] ?? null) === $unitId) { $organization['unit_name'] = $candidate['display_name']; $organization['unit_type'] = $candidate['unit_type']; $organization['version'] = $candidate['version']; break; }
            if (str_ends_with($route, '.edit.form')) return $this->form('Edit Organization Unit', '/workspace/organizations/' . rawurlencode($organizationId) . '/units/' . rawurlencode($unitId) . '/update', $organization, $csrf, true);
            if (str_ends_with($route, '.retire.form')) return $this->retire('Retire Organization Unit', '/workspace/organizations/' . rawurlencode($organizationId) . '/units/' . rawurlencode($unitId) . '/retire', (int)($organization['version'] ?? 0), $csrf);
            return $this->page('Organization Units', $this->organizationList($unit));
        }
        if (str_ends_with($route, '.edit.form')) return $this->form('Edit Organization', '/workspace/organizations/' . rawurlencode($organizationId) . '/update', $organization, $csrf, false);
        if (str_ends_with($route, '.retire.form')) return $this->retire('Retire Organization', '/workspace/organizations/' . rawurlencode($organizationId) . '/retire', (int)$organization['version'], $csrf);
        return $this->page('Organization', $this->details($organization) . $this->organizationList($this->service->units($actor, $tenant, $organizationId)));
    }

    /** @param array<string,mixed> $parameters */
    private function post(ServerRequestInterface $request, string $route, array $parameters, AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant): ResponseInterface
    {
        $body = $request->getParsedBody(); if (!is_array($body)) return $this->response(400, 'Request is invalid.');
        $submission = OrganizationSubmissionId::fromString($this->value($body, 'submission_id'));
        $version = $this->number($body, 'expected_version');
        $organizationId = $this->parameter($parameters, 'organizationId');
        if ($route === 'workspace.organizations.create.submit') { $created = $this->service->create($actor, $tenant, $submission, OrganizationInput::fromBody($body, $this->configuration)); return $this->redirect('/workspace/organizations/' . rawurlencode((string)$created['public_id'])); }
        if ($route === 'workspace.organizations.update.submit') { $this->service->update($actor, $tenant, $submission, $organizationId, OrganizationInput::fromBody($body, $this->configuration), $version); return $this->redirect('/workspace/organizations/' . rawurlencode($organizationId)); }
        if ($route === 'workspace.organizations.retire.submit') { $this->service->retire($actor, $tenant, $submission, $organizationId, $version); return $this->redirect('/workspace/organizations'); }
        $unitId = $this->parameter($parameters, 'unitId');
        if ($route === 'workspace.organizations.units.create.submit') { $parent = $body['parent_unit_id'] ?? null; $created = $this->service->createUnit($actor, $tenant, $submission, $organizationId, is_string($parent) && $parent !== '' ? $parent : null, OrganizationInput::fromBody($body, $this->configuration)); return $this->redirect('/workspace/organizations/' . rawurlencode($organizationId) . '/units/' . rawurlencode((string)$created['public_id'])); }
        if ($route === 'workspace.organizations.units.update.submit') { $this->service->updateUnit($actor, $tenant, $submission, $organizationId, $unitId, OrganizationInput::fromBody($body, $this->configuration), $version); return $this->redirect('/workspace/organizations/' . rawurlencode($organizationId) . '/units/' . rawurlencode($unitId)); }
        if ($route === 'workspace.organizations.units.retire.submit') { $this->service->retireUnit($actor, $tenant, $submission, $organizationId, $unitId, $version); return $this->redirect('/workspace/organizations/' . rawurlencode($organizationId)); }
        return $this->response(404, 'Organization route is unavailable.');
    }

    /** @param array<string,mixed> $record */
    private function form(string $title, string $action, array $record, string $csrf, bool $unit): ResponseInterface
    {
        $name = self::e((string)($unit ? ($record['unit_name'] ?? '') : ($record['display_name'] ?? '')));
        $type = self::e((string)($unit ? ($record['unit_type'] ?? 'BRANCH') : 'HEADQUARTERS'));
        $codes = self::e(implode(',', $record['classification_codes'] ?? ['OTHER']));
        $extra = $unit ? '<input type="hidden" name="classification_codes" value="OTHER"><input type="hidden" name="primary_classification" value="OTHER"><input type="hidden" name="jurisdiction_level" value="NOT_RECORDED"><label>Parent Unit public ID (optional)<input name="parent_unit_id"></label>' : '<label>Classifications (fixed codes, comma-separated)<input name="classification_codes" required value="' . $codes . '"></label><label>Primary classification<input name="primary_classification" value="' . self::e((string)($record['classification_codes'][0] ?? 'OTHER')) . '"></label><label>Jurisdiction level<select name="jurisdiction_level"><option>NOT_RECORDED</option><option>COUNTRY</option><option>LEVEL_1</option><option>LEVEL_2</option></select></label>';
        return $this->page($title, '<form method="post" action="' . self::e($action) . '"><input type="hidden" name="csrf_token" value="' . self::e($csrf) . '"><input type="hidden" name="submission_id" value="' . OrganizationSubmissionId::generate()->toString() . '"><input type="hidden" name="expected_version" value="' . (int)($record['version'] ?? 0) . '"><label>Primary name<input name="primary_name" required value="' . ($unit ? '' : $name) . '"></label><input type="hidden" name="primary_script" value="LATIN">' . $extra . '<input type="hidden" name="unit_name" value="' . ($unit ? $name : self::e((string)($record['unit_name'] ?? $record['display_name'] ?? 'Headquarters'))) . '"><input type="hidden" name="unit_script" value="LATIN"><label>Unit type<select name="unit_type"><option>' . $type . '</option><option>HEADQUARTERS</option><option>BRANCH</option><option>CAMPUS</option><option>CENTRE</option><option>SCHOOL</option><option>MOSQUE</option><option>OFFICE</option><option>OTHER</option></select></label><button type="submit">Save</button></form>');
    }
    private function retire(string $title, string $action, int $version, string $csrf): ResponseInterface { return $this->page($title, '<form method="post" action="' . self::e($action) . '"><input type="hidden" name="csrf_token" value="' . self::e($csrf) . '"><input type="hidden" name="submission_id" value="' . OrganizationSubmissionId::generate()->toString() . '"><input type="hidden" name="expected_version" value="' . $version . '"><p class="danger">Retirement is permanent and requires a current step-up grant.</p><button type="submit">Confirm retirement</button></form>'); }
    /** @param list<array<string,mixed>> $records */
    private function organizationList(array $records): string { $html = '<table><thead><tr><th>Name</th><th>Code</th><th>Status</th><th>Type</th></tr></thead><tbody>'; foreach ($records as $r) { $id=(string)($r['public_id']??''); $html .= '<tr><td><a href="/workspace/organizations/' . rawurlencode($id) . '">' . self::e((string)($r['display_name']??'')) . '</a></td><td>' . self::e((string)($r['registry_code']??'')) . '</td><td>' . self::e((string)($r['status']??'')) . '</td><td>' . self::e((string)($r['primary_classification']??$r['unit_type']??'')) . '</td></tr>'; } return $html . '</tbody></table>'; }
    /** @param array<string,mixed> $record */
    private function details(array $record): string { return '<dl><dt>Registry code</dt><dd>' . self::e((string)$record['registry_code']) . '</dd><dt>Status</dt><dd>' . self::e((string)$record['status']) . '</dd><dt>Classifications</dt><dd>' . self::e(implode(', ', $record['classification_codes'] ?? [])) . '</dd></dl><p><a href="/workspace/organizations/' . rawurlencode((string)$record['public_id']) . '/edit">Edit</a> <a href="/workspace/organizations/' . rawurlencode((string)$record['public_id']) . '/retire">Retire</a></p>'; }
    private function page(string $title, string $body): ResponseInterface { return $this->response(200, '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>' . self::e($title) . '</title><style>body{font-family:system-ui;max-width:70rem;margin:2rem auto;padding:0 1rem}table{border-collapse:collapse;width:100%}td,th{border-bottom:1px solid #ddd;padding:.6rem;text-align:left}label{display:block;margin:.7rem 0}input,select{width:100%;max-width:35rem;padding:.5rem}.danger{color:#8b0000}</style></head><body><main><h1>' . self::e($title) . '</h1><p>Private workspace registry. Classifications describe Organizations; they never grant access.</p>' . $body . '</main></body></html>'); }
    private function secure(ResponseInterface $response, \Qmdb\Modules\SecurityWeb\Csrf\CsrfCookie $cookie): ResponseInterface { return $this->view->secure($response, $cookie, true)->withHeader('Cache-Control', 'private, no-store'); }
    /** @param array<string,mixed> $parameters */ private function parameter(array $parameters, string $name): string { return is_string($parameters[$name] ?? null) ? $parameters[$name] : ''; }
    /** @param array<string,mixed> $body */ private function value(array $body, string $name): string { if (!is_string($body[$name]??null) || $body[$name] === '') throw new \InvalidArgumentException($name . ' is invalid.'); return $body[$name]; }
    /** @param array<string,mixed> $body */ private function number(array $body, string $name): int { $v=$body[$name]??0; if ((!is_string($v)&&!is_int($v))||preg_match('/\A[0-9]+\z/',(string)$v)!==1) throw new \InvalidArgumentException($name . ' is invalid.'); return (int)$v; }
    private function response(int $status, string $body): ResponseInterface { return (new Response($status,['Cache-Control'=>'private, no-store','Referrer-Policy'=>'no-referrer','X-Robots-Tag'=>'noindex, nofollow','Content-Type'=>'text/html; charset=utf-8']))->withBody(Stream::create($body)); }
    private function redirect(string $path): ResponseInterface { return new Response(303,['Location'=>$path,'Cache-Control'=>'private, no-store','Referrer-Policy'=>'no-referrer','X-Robots-Tag'=>'noindex, nofollow']); }
    private function csrfAction(string $route): CsrfAction { return match($route) { 'workspace.organizations.create.form','workspace.organizations.create.submit'=>CsrfAction::ORGANIZATION_CREATE, 'workspace.organizations.retire.form','workspace.organizations.retire.submit'=>CsrfAction::ORGANIZATION_RETIRE, 'workspace.organizations.units.create.form','workspace.organizations.units.create.submit'=>CsrfAction::ORGANIZATION_UNIT_CREATE, 'workspace.organizations.units.retire.form','workspace.organizations.units.retire.submit'=>CsrfAction::ORGANIZATION_UNIT_RETIRE, 'workspace.organizations.units.edit.form','workspace.organizations.units.update.submit'=>CsrfAction::ORGANIZATION_UNIT_UPDATE, default=>CsrfAction::ORGANIZATION_UPDATE }; }
    private static function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
