<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\Geography\Application\NigeriaGeographyDirectoryHandler;
use Qmdb\Modules\Geography\Application\NigeriaGeographyDirectoryQuery;
use Qmdb\Modules\People\Application\PersonProfileInput;
use Qmdb\Modules\People\Application\PersonProfileService;
use Qmdb\Modules\People\Configuration\PeopleProfilesConfiguration;
use Qmdb\Modules\People\Domain\PersonProfileSubmissionId;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Presentation\View\ViewData;
use Qmdb\Shared\Time\Clock;
use DomainException;
use InvalidArgumentException;
use Throwable;

final readonly class PeopleProfilesController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $guard,
        private IdentityCsrf $csrf,
        private IdentityAccessView $view,
        private PersonProfileService $profiles,
        private PeopleProfilesConfiguration $configuration,
        private Clock $clock,
        private NigeriaGeographyDirectoryHandler $geography,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = $this->guard->context($request);
        if ($actor === null) {
            return $this->guard->rejection($request);
        }
        $route = $request->getAttribute(RouteAttributes::NAME);
        if (!is_string($route)) {
            throw new \LogicException('Private Person route identity is unavailable.');
        }
        $action = $this->csrfAction($route);
        $csrf = $this->csrf->issue($request, $action);
        if ($request->getMethod() === 'GET') {
            return $this->get($request, $route, $actor, $csrf);
        }
        $parsedBody = $request->getParsedBody();
        if (!is_array($parsedBody)) {
            return $this->render($request, $route, $actor, $csrf, 400, [], 'Private profile request is invalid.');
        }
        $body = $this->body($parsedBody);
        $token = $body['csrf_token'] ?? '';
        if (!is_string($token) || !$this->csrf->validates($request, $action, $csrf['cookie'], $token)) {
            return $this->render($request, $route, $actor, $csrf, 403, [], 'Private profile request could not be verified.');
        }
        try {
            $this->post($route, $actor, $body, $this->parameters($request));

            return $this->view->redirect($this->redirectPath($route, $this->parameters($request)), $csrf['cookie'])
                ->withHeader('Referrer-Policy', 'no-referrer')
                ->withHeader('X-Robots-Tag', 'noindex, nofollow');
        } catch (InvalidArgumentException) {
            return $this->render($request, $route, $actor, $csrf, 422, [], 'Private profile changes could not be saved.');
        } catch (DomainException) {
            return $this->render($request, $route, $actor, $csrf, 409, [], 'Private profile changes could not be saved.');
        } catch (Throwable) {
            return $this->render($request, $route, $actor, $csrf, 422, [], 'Private profile changes could not be saved.');
        }
    }

    /** @param array{cookie: \Qmdb\Modules\SecurityWeb\Csrf\CsrfCookie, token: string} $csrf */
    private function get(ServerRequestInterface $request, string $route, AuthenticatedAccountContext $actor, array $csrf): ResponseInterface
    {
        $profile = $this->profiles->profile($actor);
        $parameters = $this->parameters($request);
        if ($route === 'account.person_profile.view' && $profile === null) {
            return $this->view->redirect('/account/profile/create', $csrf['cookie'])
                ->withHeader('Referrer-Policy', 'no-referrer')->withHeader('X-Robots-Tag', 'noindex, nofollow');
        }

        if (
            in_array($route, [
            'account.person_profile.dependent.view',
            'account.person_profile.dependent.edit.form',
            'account.person_profile.dependent.memorizer_progress.form',
            'account.person_profile.guardianship.revoke.form',
            ], true)
        ) {
            $profile = $this->profiles->dependent($actor, $this->routeParameter($parameters, 'person_id'));
            if ($profile === null) {
                return $this->render($request, $route, $actor, $csrf, 404, [], 'Private profile is unavailable.');
            }
        }

        return $this->render($request, $route, $actor, $csrf, 200, $profile ?? []);
    }

    /** @param array<string, mixed> $body */
    /** @param array<string, mixed> $body
     * @param array<string, string> $parameters
     */
    private function post(string $route, AuthenticatedAccountContext $actor, array $body, array $parameters): void
    {
        $submission = PersonProfileSubmissionId::fromString($this->string($body, 'submission_id'));
        $version = $this->integer($body, 'expected_version', 0);
        $nameVersion = $this->integer($body, 'expected_name_version', 0);
        switch ($route) {
            case 'account.person_profile.create.submit':
                $this->profiles->createSelf($actor, $submission, $this->input($body));
                return;
            case 'account.person_profile.update.submit':
                $this->profiles->updateSelf($actor, $submission, $this->input($body), $version, $nameVersion);
                return;
            case 'account.person_profile.role.activate':
                $this->profiles->changeRole($actor, $submission, $this->routeParameter($parameters, 'role_type'), true);
                return;
            case 'account.person_profile.role.deactivate.submit':
                $this->profiles->changeRole($actor, $submission, $this->routeParameter($parameters, 'role_type'), false, $version);
                return;
            case 'account.person_profile.memorizer_progress.submit':
                $this->profiles->updateMemorizerProgress($actor, $submission, $this->integer($body, 'memorized_juz_count', 0), $this->string($body, 'progress_status'), $this->optionalString($body, 'completed_on'), $version);
                return;
            case 'account.person_profile.dependent.create.submit':
                $this->profiles->createDependent($actor, $submission, $this->input($body));
                return;
            case 'account.person_profile.dependent.update.submit':
                $this->profiles->updateDependent($actor, $submission, $this->routeParameter($parameters, 'person_id'), $this->input($body), $version, $nameVersion);
                return;
            case 'account.person_profile.dependent.memorizer_progress.submit':
                $this->profiles->updateDependentMemorizerProgress($actor, $submission, $this->routeParameter($parameters, 'person_id'), $this->integer($body, 'memorized_juz_count', 0), $this->string($body, 'progress_status'), $this->optionalString($body, 'completed_on'), $version);
                return;
            case 'account.person_profile.guardianship.revoke.submit':
                $guardianship = $this->profiles->dependentGuardianship($actor, $this->routeParameter($parameters, 'person_id'));
                if ($guardianship === null || !is_string($guardianship['public_id'] ?? null)) {
                    throw new DomainException('Guardianship is unavailable.');
                }
                $this->profiles->revokeGuardianship($actor, $submission, $guardianship['public_id'], $version);
                return;
            default:
                throw new \LogicException('Private Person mutation route is not handled.');
        }
    }

    /** @param array{cookie: \Qmdb\Modules\SecurityWeb\Csrf\CsrfCookie, token: string} $csrf
     * @param array<string, mixed> $profile
     * @param array<string, string> $errors
     */
    private function render(ServerRequestInterface $request, string $route, AuthenticatedAccountContext $actor, array $csrf, int $status, array $profile = [], string $globalError = '', array $errors = []): ResponseInterface
    {
        $dependents = $profile === [] ? [] : $this->profiles->dependents($actor);
        $parameters = $this->parameters($request);
        $dependentId = is_string($parameters['person_id'] ?? null) ? $parameters['person_id'] : null;
        $roles = $profile === [] ? [] : ($dependentId === null ? $this->profiles->roles($actor) : $this->profiles->dependentRoles($actor, $dependentId));
        $progress = $profile === [] ? null : ($dependentId === null ? $this->profiles->memorizerProgress($actor) : $this->profiles->dependentMemorizerProgress($actor, $dependentId));
        $guardianship = $profile === [] || $dependentId === null ? null : $this->profiles->dependentGuardianship($actor, $dependentId);
        $geography = $this->geography->handle(new NigeriaGeographyDirectoryQuery(''));
        $data = new ViewData([
            'csrf_token' => $csrf['token'],
            'submission_id' => PersonProfileSubmissionId::generate()->toString(),
            'profile' => $profile,
            'dependents' => $dependents,
            'roles' => $roles,
            'memorizer_progress' => $progress ?? [],
            'guardianship' => $guardianship ?? [],
            'route_name' => $route,
            'route_parameters' => $this->parameters($request),
            'csrf_role_activate' => $this->csrf->issueForCookie($csrf['cookie'], CsrfAction::PEOPLE_ROLE_ACTIVATE),
            'csrf_role_deactivate' => $this->csrf->issueForCookie($csrf['cookie'], CsrfAction::PEOPLE_ROLE_DEACTIVATE),
            'csrf_progress' => $this->csrf->issueForCookie($csrf['cookie'], CsrfAction::PEOPLE_MEMORIZER_PROGRESS_UPDATE),
            'csrf_dependent_create' => $this->csrf->issueForCookie($csrf['cookie'], CsrfAction::PEOPLE_DEPENDENT_CREATE),
            'csrf_guardianship_revoke' => $this->csrf->issueForCookie($csrf['cookie'], CsrfAction::PEOPLE_GUARDIANSHIP_REVOKE),
            'errors' => $errors,
            'global_error' => $globalError,
            'geography_country_public_id' => $geography['country']['public_id'],
            'geography_level_one_areas' => $geography['areas'],
        ]);

        return $this->view->render(
            $request,
            'pages.account-person-profile',
            'fragments.account-person-profile',
            $data,
            'title.person_profile',
            $status,
            $csrf['cookie'],
            true,
        )->withHeader('Cache-Control', 'private, no-store');
    }

    /** @param array<string, mixed> $body */
    private function input(array $body): PersonProfileInput
    {
        return PersonProfileInput::fromBody($body, $this->configuration, $this->clock->now());
    }

    private function csrfAction(string $route): CsrfAction
    {
        return match ($route) {
            'account.person_profile.create.form', 'account.person_profile.create.submit' => CsrfAction::PEOPLE_PROFILE_CREATE,
            'account.person_profile.edit.form', 'account.person_profile.update.submit' => CsrfAction::PEOPLE_PROFILE_UPDATE,
            'account.person_profile.role.activate' => CsrfAction::PEOPLE_ROLE_ACTIVATE,
            'account.person_profile.role.deactivate.form', 'account.person_profile.role.deactivate.submit' => CsrfAction::PEOPLE_ROLE_DEACTIVATE,
            'account.person_profile.memorizer_progress.form', 'account.person_profile.memorizer_progress.submit', 'account.person_profile.dependent.memorizer_progress.form', 'account.person_profile.dependent.memorizer_progress.submit' => CsrfAction::PEOPLE_MEMORIZER_PROGRESS_UPDATE,
            'account.person_profile.dependent.create.form', 'account.person_profile.dependent.create.submit' => CsrfAction::PEOPLE_DEPENDENT_CREATE,
            'account.person_profile.dependent.edit.form', 'account.person_profile.dependent.update.submit' => CsrfAction::PEOPLE_DEPENDENT_UPDATE,
            'account.person_profile.guardianship.revoke.form', 'account.person_profile.guardianship.revoke.submit' => CsrfAction::PEOPLE_GUARDIANSHIP_REVOKE,
            default => CsrfAction::PEOPLE_PROFILE_UPDATE,
        };
    }

    /** @param array<string, string> $parameters */
    private function redirectPath(string $route, array $parameters): string
    {
        return match ($route) {
            'account.person_profile.dependent.create.submit' => '/account/dependents',
            'account.person_profile.dependent.update.submit', 'account.person_profile.dependent.memorizer_progress.submit' => '/account/dependents/' . rawurlencode($this->routeParameter($parameters, 'person_id')) . '/edit',
            'account.person_profile.guardianship.revoke.submit' => '/account/dependents',
            default => '/account/profile',
        };
    }

    /** @param array<string, mixed> $body */
    private function string(array $body, string $field): string
    {
        $value = $body[$field] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException($field . ' is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $body */
    private function optionalString(array $body, string $field): ?string
    {
        $value = $body[$field] ?? null;

        return $value === null || $value === '' ? null : $this->string($body, $field);
    }

    /** @param array<string, mixed> $body */
    private function integer(array $body, string $field, int $default): int
    {
        $value = $body[$field] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }
        if ((!is_string($value) && !is_int($value)) || preg_match('/\A[0-9]+\z/', (string) $value) !== 1) {
            throw new \InvalidArgumentException($field . ' is invalid.');
        }

        return (int) $value;
    }

    /** @param array<string, string> $parameters */
    private function routeParameter(array $parameters, string $name): string
    {
        if (!is_string($parameters[$name] ?? null)) {
            throw new \InvalidArgumentException('Route parameter is invalid.');
        }

        return $parameters[$name];
    }

    /** @return array<string, string> */
    private function parameters(ServerRequestInterface $request): array
    {
        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS, []);

        if (!is_array($parameters)) {
            return [];
        }
        $result = [];
        foreach ($parameters as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /** @param array<mixed, mixed> $parsed
     * @return array<string, mixed>
     */
    private function body(array $parsed): array
    {
        $body = [];
        foreach ($parsed as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('Private profile request is invalid.');
            }
            $body[$key] = $value;
        }

        return $body;
    }
}
