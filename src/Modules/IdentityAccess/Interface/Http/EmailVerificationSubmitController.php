<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Application\Verification\EmailVerificationCommand;
use Qmdb\Modules\IdentityAccess\Application\Verification\EmailVerificationOutcome;
use Qmdb\Modules\IdentityAccess\Application\Verification\EmailVerificationService;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationChallengeId;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;

final readonly class EmailVerificationSubmitController implements Controller
{
    public function __construct(
        private IdentityFormInputMapper $input,
        private IdentityCsrf $csrf,
        private IdentityAccessView $view,
        private IdentityRequestContext $context,
        private EmailVerificationService $verification,
        private FragmentRequestDetector $fragments,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_EMAIL_VERIFY);
        $challenge = $this->challenge($request);
        try {
            $input = $this->input->verification($request);
        } catch (FormInputException $exception) {
            return $this->error($request, $csrf, $challenge, $exception->status, 'form.error.verification');
        }
        if (
            $challenge === null
            || !$this->csrf->validates(
                $request,
                CsrfAction::ACCOUNT_EMAIL_VERIFY,
                $csrf['cookie'],
                $input->csrfToken,
            )
        ) {
            return $this->error($request, $csrf, $challenge, 403, 'form.error.csrf');
        }
        $result = $this->verification->verify(new EmailVerificationCommand(
            $challenge,
            $input->token,
            $this->context->peer($request),
        ));
        if ($result->outcome === EmailVerificationOutcome::THROTTLED) {
            return $this->error($request, $csrf, $challenge, 429, 'form.error.throttled')
                ->withHeader('Retry-After', (string)$result->retryAfterSeconds);
        }
        if ($result->outcome === EmailVerificationOutcome::INVALID) {
            return $this->error($request, $csrf, $challenge, 422, 'form.error.verification');
        }
        if (!$this->fragments->isFragment($request)) {
            return $this->view->redirect('/verify-email/completed', $csrf['cookie']);
        }

        return $this->view->render(
            $request,
            'pages.email-verification-completed',
            'fragments.email-verification-completed',
            IdentityViewDataFactory::empty(),
            'title.verification.completed',
            200,
            $csrf['cookie'],
            true,
        );
    }

    /** @param array{cookie: \Qmdb\Modules\SecurityWeb\Csrf\CsrfCookie, token: string} $csrf */
    private function error(
        ServerRequestInterface $request,
        array $csrf,
        ?EmailVerificationChallengeId $challenge,
        int $status,
        string $globalError,
    ): ResponseInterface {
        return $this->view->render(
            $request,
            'pages.email-verification-confirm',
            'fragments.email-verification-confirm',
            IdentityViewDataFactory::verification(
                $csrf['token'],
                $challenge?->toString() ?? '',
                '',
                false,
                globalError: $globalError,
            ),
            'title.verification.confirm',
            $status,
            $csrf['cookie'],
            true,
        );
    }

    private function challenge(ServerRequestInterface $request): ?EmailVerificationChallengeId
    {
        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
        $value = is_array($parameters) && is_string($parameters['challengeId'] ?? null)
            ? $parameters['challengeId']
            : '';
        try {
            return EmailVerificationChallengeId::fromString($value);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
