<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityRequestContext;
use Qmdb\Modules\IdentityRecovery\Application\PasswordResetCommand;
use Qmdb\Modules\IdentityRecovery\Application\PasswordResetOutcome;
use Qmdb\Modules\IdentityRecovery\Application\PasswordResetService;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryChallengeId;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordResetSubmissionId;
use Qmdb\Modules\IdentitySessions\Application\AuthenticationCookieResponseDecorator;
use Qmdb\Modules\IdentitySessions\Application\SessionCookieFactory;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Request\RequestContextAttributes;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;

final readonly class PasswordResetSubmitController implements Controller
{
    public function __construct(
        private RecoveryFormInputMapper $input,
        private IdentityCsrf $csrf,
        private IdentityAccessView $view,
        private IdentityRequestContext $context,
        private PasswordResetService $reset,
        private SessionCookieFactory $sessionCookies,
        private AuthenticationCookieResponseDecorator $cookies,
        private FragmentRequestDetector $fragments,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_PASSWORD_RECOVERY_RESET);
        $challenge = $this->challenge($request);
        try {
            $input = $this->input->reset($request);
        } catch (RecoveryFormInputException $exception) {
            return $this->form(
                $request,
                $csrf,
                $challenge,
                $exception->status,
                $exception->safeToken,
                $exception->errors,
            );
        }
        if ($challenge === null) {
            return $this->form($request, $csrf, null, 422, '', [], 'form.error.recovery');
        }
        if (
            !$this->csrf->validates(
                $request,
                CsrfAction::ACCOUNT_PASSWORD_RECOVERY_RESET,
                $csrf['cookie'],
                $input->csrfToken,
            )
        ) {
            return $this->form(
                $request,
                $csrf,
                $challenge,
                403,
                $input->token->revealForProof(),
                [],
                'form.error.csrf',
            );
        }
        if (!$this->idempotencyMatches($request, $input->submissionId->toString())) {
            return $this->form(
                $request,
                $csrf,
                $challenge,
                409,
                $input->token->revealForProof(),
                [],
                'form.error.idempotency',
            );
        }
        $requestId = $request->getAttribute(RequestContextAttributes::REQUEST_ID);
        $result = $this->reset->reset(new PasswordResetCommand(
            $challenge,
            $input->token,
            $input->password,
            $input->confirmation,
            $input->submissionId,
            $this->context->peer($request),
            $requestId instanceof CorrelationId ? $requestId->value() : null,
        ));
        if ($result->outcome === PasswordResetOutcome::THROTTLED) {
            return $this->form($request, $csrf, $challenge, 429, '', [], 'form.error.throttled')
                ->withHeader('Retry-After', (string)$result->retryAfterSeconds);
        }
        if ($result->outcome === PasswordResetOutcome::IDEMPOTENCY_CONFLICT) {
            return $this->form($request, $csrf, $challenge, 409, '', [], 'form.error.idempotency');
        }
        if ($result->outcome === PasswordResetOutcome::INVALID) {
            return $this->form($request, $csrf, $challenge, 422, '', [], 'form.error.recovery');
        }
        $rotatedCsrf = $this->csrf->rotate(CsrfAction::ACCOUNT_PASSWORD_RECOVERY_RESET);
        $response = $this->fragments->isFragment($request)
            ? $this->view->render(
                $request,
                'pages.password-reset-completed',
                'fragments.password-reset-completed',
                RecoveryViewDataFactory::empty(),
                'title.password_reset.completed',
                verificationHeaders: true,
            )->withHeader('X-QMDB-Navigate', '/reset-password/completed')
            : $this->view->redirect('/reset-password/completed');
        $response = $response
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('X-Robots-Tag', 'noindex, nofollow');

        return $this->cookies->apply($response, [$this->sessionCookies->clear()], $rotatedCsrf['cookie']);
    }

    /** @param array{cookie: \Qmdb\Modules\SecurityWeb\Csrf\CsrfCookie, token: string} $csrf
     * @param array<string, string> $errors
     */
    private function form(
        ServerRequestInterface $request,
        array $csrf,
        ?PasswordRecoveryChallengeId $challenge,
        int $status,
        string $token,
        array $errors,
        string $globalError = '',
    ): ResponseInterface {
        return $this->view->render(
            $request,
            'pages.password-reset',
            'fragments.password-reset-form',
            RecoveryViewDataFactory::reset(
                $csrf['token'],
                PasswordResetSubmissionId::generate()->toString(),
                $challenge?->toString() ?? '',
                $token,
                $challenge !== null && $token !== '',
                $errors,
                $globalError,
            ),
            'title.password_reset',
            $status,
            $csrf['cookie'],
            true,
        );
    }

    private function challenge(ServerRequestInterface $request): ?PasswordRecoveryChallengeId
    {
        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
        $value = is_array($parameters) && is_string($parameters['challengeId'] ?? null)
            ? $parameters['challengeId']
            : '';
        try {
            return PasswordRecoveryChallengeId::fromString($value);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function idempotencyMatches(ServerRequestInterface $request, string $submissionId): bool
    {
        $header = trim($request->getHeaderLine('Idempotency-Key'));

        return $header === ''
            ? !$this->fragments->isFragment($request)
            : hash_equals($submissionId, $header);
    }
}
