<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Interface\Http;

use DomainException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentityResolution\Application\GuardianProfileClaimAuthorizationCommand;
use Qmdb\Modules\IdentityResolution\Application\GuardianProfileClaimAuthorizationService;
use Qmdb\Modules\IdentityResolution\Application\PersonDuplicateConsentCommand;
use Qmdb\Modules\IdentityResolution\Application\PersonDuplicateConsentService;
use Qmdb\Modules\IdentityResolution\Application\PersonDuplicateDismissalCommand;
use Qmdb\Modules\IdentityResolution\Application\PersonDuplicateDismissalService;
use Qmdb\Modules\IdentityResolution\Application\PersonDuplicateReportCommand;
use Qmdb\Modules\IdentityResolution\Application\PersonDuplicateReportService;
use Qmdb\Modules\IdentityResolution\Application\PersonDuplicateResolutionCommand;
use Qmdb\Modules\IdentityResolution\Application\PersonDuplicateResolutionService;
use Qmdb\Modules\IdentityResolution\Application\PersonDuplicateReviewService;
use Qmdb\Modules\IdentityResolution\Application\PlatformProfileClaimAuthorizationCommand;
use Qmdb\Modules\IdentityResolution\Application\PlatformProfileClaimAuthorizationService;
use Qmdb\Modules\IdentityResolution\Application\ProfileClaimAcceptanceCommand;
use Qmdb\Modules\IdentityResolution\Application\ProfileClaimAcceptanceService;
use Qmdb\Modules\IdentityResolution\Application\ProfileClaimDeclineCommand;
use Qmdb\Modules\IdentityResolution\Application\ProfileClaimDeclineService;
use Qmdb\Modules\IdentityResolution\Application\ProfileClaimPairingCreationCommand;
use Qmdb\Modules\IdentityResolution\Application\ProfileClaimPairingCreationService;
use Qmdb\Modules\IdentityResolution\Application\ProfileClaimPairingRevocationService;
use Qmdb\Modules\IdentityResolution\Application\ProfileClaimRevocationCommand;
use Qmdb\Modules\IdentityResolution\Application\ProfileClaimRevocationService;
use Qmdb\Modules\IdentityResolution\Application\ProfileVerificationAssertionCommand;
use Qmdb\Modules\IdentityResolution\Application\ProfileVerificationAssertionService;
use Qmdb\Modules\IdentityResolution\Application\ProfileVerificationRevocationCommand;
use Qmdb\Modules\IdentityResolution\Application\ProfileVerificationRevocationService;
use Qmdb\Modules\IdentityResolution\Domain\IdentityResolutionSubmissionId;
use Qmdb\Modules\IdentityResolution\Domain\PersonDuplicateConsentDecision;
use Qmdb\Modules\IdentityResolution\Infrastructure\Persistence\MySqlIdentityResolutionRepository;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Presentation\View\ViewData;
use Throwable;

/**
 * Private, server-rendered identity-resolution workflows.  There are no
 * discovery routes here: every target is reached by an already-authorized
 * account, an exact registry code, or a case/claim reference bound to it.
 */
final readonly class PeopleIdentityResolutionController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $guard,
        private IdentityCsrf $csrf,
        private IdentityAccessView $view,
        private MySqlIdentityResolutionRepository $repository,
        private AuthorizationRequirementGuard $authorization,
        private ProfileClaimPairingCreationService $pairings,
        private ProfileClaimPairingRevocationService $pairingRevocations,
        private GuardianProfileClaimAuthorizationService $guardianClaims,
        private PlatformProfileClaimAuthorizationService $platformClaims,
        private ProfileClaimAcceptanceService $claimAcceptance,
        private ProfileClaimDeclineService $claimDecline,
        private ProfileClaimRevocationService $claimRevocation,
        private ProfileVerificationAssertionService $verificationAssertions,
        private ProfileVerificationRevocationService $verificationRevocations,
        private PersonDuplicateReportService $duplicateReports,
        private PersonDuplicateConsentService $duplicateConsent,
        private PersonDuplicateDismissalService $duplicateDismissal,
        private PersonDuplicateResolutionService $duplicateResolution,
        private PersonDuplicateReviewService $duplicateReview,
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
            throw new \LogicException('Private identity-resolution route identity is unavailable.');
        }
        $action = $this->csrfAction($route);
        $csrf = $this->csrf->issue($request, $action);

        try {
            if ($request->getMethod() === 'GET') {
                return $this->get($request, $route, $actor, $csrf);
            }
            $body = $request->getParsedBody();
            if (!is_array($body)) {
                return $this->render($request, $route, $actor, $csrf, 400, [], 'This private request is invalid.');
            }
            $token = $body['csrf_token'] ?? null;
            if (!is_string($token) || !$this->csrf->validates($request, $action, $csrf['cookie'], $token)) {
                return $this->render($request, $route, $actor, $csrf, 403, [], 'This private request could not be verified.');
            }

            $result = $this->post($request, $route, $actor, $this->body($body));
            if (is_string($result) && $route === 'account.profile_claim_pairing.create') {
                return $this->render($request, $route, $actor, $csrf, 201, ['pairing_code' => $result], 'Pairing code created. Copy it now; QMDB will not display it again.');
            }

            return $this->view->redirect($this->redirectPath($route, $request), $csrf['cookie'])
                ->withHeader('Cache-Control', 'private, no-store')
                ->withHeader('Referrer-Policy', 'no-referrer')
                ->withHeader('X-Robots-Tag', 'noindex, nofollow');
        } catch (InvalidArgumentException) {
            return $this->render($request, $route, $actor, $csrf, 422, [], 'This private request could not be completed.');
        } catch (DomainException) {
            return $this->render($request, $route, $actor, $csrf, 409, [], 'This private request could not be completed.');
        } catch (Throwable) {
            return $this->render($request, $route, $actor, $csrf, 422, [], 'This private request could not be completed.');
        }
    }

    /** @param array{cookie: \Qmdb\Modules\SecurityWeb\Csrf\CsrfCookie, token: string} $csrf */
    private function get(ServerRequestInterface $request, string $route, AuthenticatedAccountContext $actor, array $csrf): ResponseInterface
    {
        $data = $this->read($route, $actor, $this->parameters($request));

        return $this->render($request, $route, $actor, $csrf, 200, $data);
    }

    /** @param array<string, mixed> $body */
    private function post(ServerRequestInterface $request, string $route, AuthenticatedAccountContext $actor, array $body): ?string
    {
        $parameters = $this->parameters($request);
        $submission = IdentityResolutionSubmissionId::fromString($this->string($body, 'submission_id'));
        $version = $this->integer($body, 'expected_version', 0);

        switch ($route) {
            case 'account.profile_claim_pairing.create':
                return $this->pairingCode($actor, $submission);
            case 'account.profile_claim_pairing.revoke':
                $this->revokePairing($actor, $this->routeParameter($parameters, 'pairingId'), $version, $submission);
                break;
            case 'account.dependent_profile_claim.authorization.submit':
                $this->authorizeGuardianClaim($actor, $this->routeParameter($parameters, 'personId'), $body, $submission);
                break;
            case 'account.dependent_profile_claim.revoke':
                $this->assertDependentClaimScope($actor, $this->routeParameter($parameters, 'personId'), $this->routeParameter($parameters, 'claimId'));
                $this->revokeGuardianClaim($actor, $this->routeParameter($parameters, 'claimId'), $version, $submission);
                break;
            case 'account.profile_claim.accept':
                $this->acceptClaim($actor, $this->routeParameter($parameters, 'claimId'), $version, $submission);
                break;
            case 'account.profile_claim.decline':
                $this->declineClaim($actor, $this->routeParameter($parameters, 'claimId'), $version, $submission);
                break;
            case 'platform.profile_claim.authorization.submit':
                $this->authorizePlatformClaim($actor, $body, $submission);
                break;
            case 'platform.profile_claim.revoke':
                $this->revokePlatformClaim($actor, $this->routeParameter($parameters, 'claimId'), $version, $submission);
                break;
            case 'platform.profile_verification.record':
                $this->recordVerification($actor, $this->routeParameter($parameters, 'personId'), $body, $submission);
                break;
            case 'platform.profile_verification.revoke':
                $this->revokeVerification($actor, $this->routeParameter($parameters, 'personId'), $this->routeParameter($parameters, 'assertionId'), $version, $submission);
                break;
            case 'account.person_duplicate.report.submit':
                $this->reportDuplicate($actor, $this->optionalString($body, 'managed_person_id'), $body, $submission);
                break;
            case 'account.dependent_duplicate.report.submit':
                $this->reportDuplicate($actor, $this->routeParameter($parameters, 'personId'), $body, $submission);
                break;
            case 'account.person_duplicate.consent':
                $this->decideConsent($actor, $body, 'APPROVED', $version, $submission);
                break;
            case 'account.person_duplicate.decline':
                $this->decideConsent($actor, $body, 'DECLINED', $version, $submission);
                break;
            case 'account.dependent_duplicate.consent':
                $this->assertDependentCaseScope($actor, $this->routeParameter($parameters, 'personId'), $this->routeParameter($parameters, 'caseId'));
                $this->decideConsent($actor, $body, 'APPROVED', $version, $submission);
                break;
            case 'account.dependent_duplicate.decline':
                $this->assertDependentCaseScope($actor, $this->routeParameter($parameters, 'personId'), $this->routeParameter($parameters, 'caseId'));
                $this->decideConsent($actor, $body, 'DECLINED', $version, $submission);
                break;
            case 'platform.person_duplicate.review':
                $this->startDuplicateReview($actor, $this->routeParameter($parameters, 'caseId'), $version, $submission);
                break;
            case 'platform.person_duplicate.dismiss':
                $this->dismissDuplicate($actor, $this->routeParameter($parameters, 'caseId'), $body, $version, $submission);
                break;
            case 'platform.person_duplicate.resolve.submit':
                $this->resolveDuplicate($actor, $this->routeParameter($parameters, 'caseId'), $body, $version, $submission);
                break;
            default:
                throw new \LogicException('Private identity-resolution mutation route is not handled.');
        }

        return null;
    }

    private function pairingCode(AuthenticatedAccountContext $actor, IdentityResolutionSubmissionId $submission): ?string
    {
        $result = $this->pairings->create($actor, new ProfileClaimPairingCreationCommand($submission));

        return $result->displayOnceCode;
    }

    private function revokePairing(AuthenticatedAccountContext $actor, string $pairingId, int $version, IdentityResolutionSubmissionId $submission): void
    {
        $this->pairingRevocations->revoke($actor, $pairingId, $version, $submission);
    }

    /** @param array<string, mixed> $body */
    private function authorizeGuardianClaim(AuthenticatedAccountContext $actor, string $personId, array $body, IdentityResolutionSubmissionId $submission): void
    {
        $this->guardianClaims->authorize($actor, new GuardianProfileClaimAuthorizationCommand($personId, $this->string($body, 'pairing_code'), $submission));
    }

    private function revokeGuardianClaim(AuthenticatedAccountContext $actor, string $claimId, int $version, IdentityResolutionSubmissionId $submission): void
    {
        $this->claimRevocation->revokeByGuardian($actor, new ProfileClaimRevocationCommand($claimId, $version, $submission));
    }

    private function acceptClaim(AuthenticatedAccountContext $actor, string $claimId, int $version, IdentityResolutionSubmissionId $submission): void
    {
        $this->claimAcceptance->accept($actor, new ProfileClaimAcceptanceCommand($claimId, $version, $submission));
    }

    private function declineClaim(AuthenticatedAccountContext $actor, string $claimId, int $version, IdentityResolutionSubmissionId $submission): void
    {
        $this->claimDecline->decline($actor, new ProfileClaimDeclineCommand($claimId, $version, $submission));
    }

    /** @param array<string, mixed> $body */
    private function authorizePlatformClaim(AuthenticatedAccountContext $actor, array $body, IdentityResolutionSubmissionId $submission): void
    {
        $this->platformClaims->authorize($actor, new PlatformProfileClaimAuthorizationCommand($this->string($body, 'registry_code'), $this->string($body, 'pairing_code'), $this->string($body, 'review_reference'), $this->string($body, 'review_justification'), $submission));
    }

    private function revokePlatformClaim(AuthenticatedAccountContext $actor, string $claimId, int $version, IdentityResolutionSubmissionId $submission): void
    {
        $this->claimRevocation->revokeByPlatform($actor, new ProfileClaimRevocationCommand($claimId, $version, $submission));
    }

    /** @param array<string, mixed> $body */
    private function recordVerification(AuthenticatedAccountContext $actor, string $personId, array $body, IdentityResolutionSubmissionId $submission): void
    {
        $this->verificationAssertions->record($actor, new ProfileVerificationAssertionCommand($personId, $this->string($body, 'review_reference'), $this->string($body, 'review_justification'), $submission));
    }

    private function revokeVerification(AuthenticatedAccountContext $actor, string $personId, string $assertionId, int $version, IdentityResolutionSubmissionId $submission): void
    {
        $this->verificationRevocations->revoke($actor, new ProfileVerificationRevocationCommand($personId, $assertionId, $version, $submission));
    }

    /** @param array<string, mixed> $body */
    private function reportDuplicate(AuthenticatedAccountContext $actor, ?string $personId, array $body, IdentityResolutionSubmissionId $submission): void
    {
        $this->duplicateReports->reportForAccount($actor, new PersonDuplicateReportCommand($personId, $this->string($body, 'other_registry_code'), $submission));
    }

    /** @param array<string, mixed> $body */
    private function decideConsent(AuthenticatedAccountContext $actor, array $body, string $decision, int $version, IdentityResolutionSubmissionId $submission): void
    {
        $this->duplicateConsent->decide($actor, new PersonDuplicateConsentCommand($this->string($body, 'requirement_id'), PersonDuplicateConsentDecision::from($decision), $version, $submission));
    }

    private function startDuplicateReview(AuthenticatedAccountContext $actor, string $caseId, int $version, IdentityResolutionSubmissionId $submission): void
    {
        $this->duplicateReview->start($actor, $caseId, $version, $submission);
    }

    /** @param array<string, mixed> $body */
    private function dismissDuplicate(AuthenticatedAccountContext $actor, string $caseId, array $body, int $version, IdentityResolutionSubmissionId $submission): void
    {
        $this->duplicateDismissal->dismiss($actor, new PersonDuplicateDismissalCommand($caseId, $version, $this->string($body, 'review_reference'), $this->string($body, 'review_justification'), $submission));
    }

    /** @param array<string, mixed> $body */
    private function resolveDuplicate(AuthenticatedAccountContext $actor, string $caseId, array $body, int $version, IdentityResolutionSubmissionId $submission): void
    {
        $this->duplicateResolution->resolve($actor, new PersonDuplicateResolutionCommand($caseId, $this->string($body, 'canonical_person_id'), $this->string($body, 'duplicate_person_id'), $version, $this->string($body, 'review_reference'), $this->string($body, 'review_justification'), $submission));
    }

    /** @param array<string, string> $parameters
     * @return array<string, mixed>
     */
    private function read(string $route, AuthenticatedAccountContext $actor, array $parameters): array
    {
        $data = [];
        if ($route === 'account.profile_claim_pairing.form') {
            $data['pairing'] = $this->repository->activePairingForAccount($actor->accountInternalId) ?? [];
        } elseif (in_array($route, ['account.profile_claims.index', 'account.profile_claim.detail'], true)) {
            $data['claims'] = $this->repository->claimsForAccount($actor->accountInternalId);
            if ($route === 'account.profile_claim.detail') {
                $data['claim'] = $this->repository->claimDetailForAccount($this->routeParameter($parameters, 'claimId'), $actor->accountInternalId) ?? [];
            }
        } elseif (str_starts_with($route, 'account.dependent_profile_claim.')) {
            $dependentId = $this->routeParameter($parameters, 'personId');
            $person = $this->dependentPersonForGuardian($actor, $dependentId);
            $data['dependent_id'] = $dependentId;
            if ($route === 'account.dependent_profile_claim.revoke') {
                $claim = $this->repository->claimByPublicId($this->routeParameter($parameters, 'claimId'));
                if ($claim === null || (int) $claim['person_id'] !== (int) $person['id']) {
                    throw new DomainException('Private dependent profile claim is unavailable.');
                }
                $data['claim'] = $claim;
            }
        } elseif (str_starts_with($route, 'platform.profile_claim.')) {
            $this->requirePlatform($actor, 'platform.people_profile_claims.authorize');
            $data['claims'] = $this->repository->platformClaimQueue();
        } elseif (str_starts_with($route, 'platform.profile_verification.')) {
            $this->requirePlatform($actor, 'platform.people_profile_verifications.manage');
            $personId = $parameters['personId'] ?? null;
            if (is_string($personId)) {
                $person = $this->repository->personByPublicId($personId);
                $data['person'] = $person === null ? [] : $person;
            }
        } elseif (str_starts_with($route, 'account.person_duplicate.') || str_starts_with($route, 'account.dependent_duplicate.')) {
            $data['cases'] = $this->repository->duplicateCasesForAccount($actor->accountInternalId);
            $caseId = $parameters['caseId'] ?? null;
            if (is_string($caseId)) {
                $case = $this->repository->duplicateCaseForAccount($caseId, $actor->accountInternalId);
                if (str_starts_with($route, 'account.dependent_duplicate.') && $case !== null) {
                    $person = $this->dependentPersonForGuardian($actor, $this->routeParameter($parameters, 'personId'));
                    if (!in_array((int) $person['id'], [(int) $case['first_person_id'], (int) $case['second_person_id']], true)) {
                        throw new DomainException('Private dependent duplicate case is unavailable.');
                    }
                }
                $data['case'] = $case ?? [];
                $data['requirements'] = $case === null ? [] : $this->repository->consentRequirementsForCase((int) $case['id'], $actor->accountInternalId);
            }
        } elseif (str_starts_with($route, 'platform.person_duplicate.')) {
            $permission = str_contains($route, '.resolve') || str_contains($route, '.dismiss') ? 'platform.people_duplicates.resolve' : 'platform.people_duplicates.view';
            $this->requirePlatform($actor, $permission);
            $data['cases'] = $this->repository->platformDuplicateCases();
            $caseId = $parameters['caseId'] ?? null;
            if (is_string($caseId)) {
                $case = $this->repository->duplicateCase($caseId);
                $data['case'] = $case ?? [];
                if ($case !== null) {
                    $data['first_person'] = $this->repository->personComparisonDetail((int) $case['first_person_id']) ?? [];
                    $data['second_person'] = $this->repository->personComparisonDetail((int) $case['second_person_id']) ?? [];
                    $data['requirements'] = $this->repository->consentRequirementsForCase((int) $case['id']);
                }
            }
        }

        return $data;
    }

    private function requirePlatform(AuthenticatedAccountContext $actor, string $permission): void
    {
        $this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor), new PermissionCode($permission), new PlatformAuthorizationScope()));
    }

    /** @return array<string, int|string> */
    private function dependentPersonForGuardian(AuthenticatedAccountContext $actor, string $personPublicId): array
    {
        $person = $this->repository->personByPublicId($personPublicId);
        if ($person === null || $this->repository->guardianAuthority($actor->accountInternalId, (int) $person['id']) === null) {
            throw new DomainException('Private dependent workflow is unavailable.');
        }

        return $person;
    }

    private function assertDependentClaimScope(AuthenticatedAccountContext $actor, string $personPublicId, string $claimPublicId): void
    {
        $person = $this->dependentPersonForGuardian($actor, $personPublicId);
        $claim = $this->repository->claimByPublicId($claimPublicId);
        if ($claim === null || (int) $claim['person_id'] !== (int) $person['id']) {
            throw new DomainException('Private dependent profile claim is unavailable.');
        }
    }

    private function assertDependentCaseScope(AuthenticatedAccountContext $actor, string $personPublicId, string $casePublicId): void
    {
        $person = $this->dependentPersonForGuardian($actor, $personPublicId);
        $case = $this->repository->duplicateCaseForAccount($casePublicId, $actor->accountInternalId);
        if ($case === null || !in_array((int) $person['id'], [(int) $case['first_person_id'], (int) $case['second_person_id']], true)) {
            throw new DomainException('Private dependent duplicate case is unavailable.');
        }
    }

    /** @param array{cookie: \Qmdb\Modules\SecurityWeb\Csrf\CsrfCookie, token: string} $csrf
     * @param array<string, mixed> $extra
     */
    private function render(ServerRequestInterface $request, string $route, AuthenticatedAccountContext $actor, array $csrf, int $status, array $extra = [], string $message = ''): ResponseInterface
    {
        $parameters = $this->parameters($request);
        $data = array_merge($this->readForRender($route, $actor, $parameters), $extra, [
            'route_name' => $route,
            'route_parameters' => $parameters,
            'csrf_token' => $csrf['token'],
            'submission_id' => IdentityResolutionSubmissionId::generate()->toString(),
            'global_message' => $message,
        ]);

        return $this->view->render(
            $request,
            $this->page($route),
            'fragments.person-identity-resolution',
            new ViewData($data),
            'title.person_profile',
            $status,
            $csrf['cookie'],
            true,
        )->withHeader('Cache-Control', 'private, no-store');
    }

    /** @param array<string, string> $parameters
     * @return array<string, mixed>
     */
    private function readForRender(string $route, AuthenticatedAccountContext $actor, array $parameters): array
    {
        try {
            return $this->read($route, $actor, $parameters);
        } catch (Throwable) {
            return [];
        }
    }

    private function page(string $route): string
    {
        return match (true) {
            $route === 'account.profile_claim_pairing.form' || $route === 'account.profile_claim_pairing.create' => 'pages.account-profile-claim-pairing',
            str_starts_with($route, 'account.dependent_profile_claim.') => 'pages.account-dependent-profile-claim-authorize',
            str_starts_with($route, 'account.profile_claim.') => 'pages.account-profile-claims',
            str_starts_with($route, 'platform.profile_claim.') => 'pages.platform-profile-claims',
            str_starts_with($route, 'platform.profile_verification.') => 'pages.platform-profile-verifications',
            str_starts_with($route, 'platform.person_duplicate.resolve') => 'pages.platform-person-duplicate-resolution',
            str_starts_with($route, 'platform.person_duplicate.') => 'pages.platform-person-duplicate-cases',
            default => 'pages.account-person-duplicate-cases',
        };
    }

    private function csrfAction(string $route): CsrfAction
    {
        return match ($route) {
            'account.profile_claim_pairing.form', 'account.profile_claim_pairing.create' => CsrfAction::PEOPLE_CLAIM_PAIRING_CREATE,
            'account.profile_claim_pairing.revoke' => CsrfAction::PEOPLE_CLAIM_PAIRING_REVOKE,
            'account.dependent_profile_claim.authorization.form', 'account.dependent_profile_claim.authorization.submit', 'platform.profile_claim.authorization.form', 'platform.profile_claim.authorization.submit' => CsrfAction::PEOPLE_PROFILE_CLAIM_AUTHORIZE,
            'account.profile_claim.accept' => CsrfAction::PEOPLE_PROFILE_CLAIM_ACCEPT,
            'account.profile_claim.decline' => CsrfAction::PEOPLE_PROFILE_CLAIM_DECLINE,
            'account.dependent_profile_claim.revoke', 'platform.profile_claim.revoke' => CsrfAction::PEOPLE_PROFILE_CLAIM_REVOKE,
            'platform.profile_verification.record' => CsrfAction::PEOPLE_PROFILE_VERIFICATION_RECORD,
            'platform.profile_verification.revoke' => CsrfAction::PEOPLE_PROFILE_VERIFICATION_REVOKE,
            'account.person_duplicate.report.form', 'account.person_duplicate.report.submit', 'account.dependent_duplicate.report.form', 'account.dependent_duplicate.report.submit', 'platform.person_duplicate.review' => CsrfAction::PEOPLE_DUPLICATE_REPORT,
            'account.person_duplicate.consent', 'account.person_duplicate.decline', 'account.dependent_duplicate.consent', 'account.dependent_duplicate.decline' => CsrfAction::PEOPLE_DUPLICATE_CONSENT,
            'platform.person_duplicate.dismiss' => CsrfAction::PEOPLE_DUPLICATE_DISMISS,
            'platform.person_duplicate.resolve.form', 'platform.person_duplicate.resolve.submit' => CsrfAction::PEOPLE_DUPLICATE_RESOLVE,
            default => CsrfAction::PEOPLE_PROFILE_CLAIM_AUTHORIZE,
        };
    }

    private function redirectPath(string $route, ServerRequestInterface $request): string
    {
        $parameters = $this->parameters($request);
        $path = match (true) {
            str_starts_with($route, 'account.profile_claim') => '/account/profile/claims',
            str_starts_with($route, 'account.dependent_profile_claim') => '/account/dependents/' . rawurlencode($this->routeParameter($parameters, 'personId')),
            str_starts_with($route, 'platform.profile_claim') => '/platform/people/profile-claims',
            str_starts_with($route, 'platform.profile_verification') => '/platform/people/profile-verifications',
            str_starts_with($route, 'platform.person_duplicate') => '/platform/people/duplicates',
            default => '/account/profile/duplicate-cases',
        };

        $locale = $request->getQueryParams()['lang'] ?? null;

        return is_string($locale) && in_array($locale, ['en', 'ar'], true)
            ? $path . '?lang=' . $locale
            : $path;
    }

    /** @param array<string, mixed> $body */
    private function string(array $body, string $field): string
    {
        $value = $body[$field] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException($field . ' is invalid.');
        }

        return trim($value);
    }

    /** @param array<array-key, mixed> $body
     * @return array<string, mixed>
     */
    private function body(array $body): array
    {
        $normalized = [];
        foreach ($body as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('Private request body is invalid.');
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /** @param array<string, mixed> $body */
    private function optionalString(array $body, string $field): ?string
    {
        $value = $body[$field] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @param array<string, mixed> $body */
    private function integer(array $body, string $field, int $default): int
    {
        $value = $body[$field] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }
        if ((!is_int($value) && !is_string($value)) || preg_match('/\A[0-9]+\z/', (string) $value) !== 1) {
            throw new InvalidArgumentException($field . ' is invalid.');
        }

        return (int) $value;
    }

    /** @return array<string, string> */
    private function parameters(ServerRequestInterface $request): array
    {
        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS, []);

        if (!is_array($parameters)) {
            return [];
        }
        $normalized = [];
        foreach ($parameters as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $normalized[$name] = $value;
            }
        }

        return $normalized;
    }

    /** @param array<string, string> $parameters */
    private function routeParameter(array $parameters, string $name): string
    {
        $value = $parameters[$name] ?? null;
        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException('Route parameter is invalid.');
        }

        return $value;
    }
}
