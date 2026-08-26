<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Application\Verification\EmailVerificationResendCommand;
use Qmdb\Modules\IdentityAccess\Application\Verification\EmailVerificationResendOutcome;
use Qmdb\Modules\IdentityAccess\Application\Verification\EmailVerificationResendService;
use Qmdb\Modules\IdentityAccess\Domain\VerificationResendSubmissionId;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;

final readonly class EmailVerificationResendSubmitController implements Controller
{
    public function __construct(
        private IdentityFormInputMapper $input,
        private IdentityCsrf $csrf,
        private IdentityAccessView $view,
        private IdentityRequestContext $context,
        private EmailVerificationResendService $resend,
        private FragmentRequestDetector $fragments,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_EMAIL_RESEND);
        try {
            $input = $this->input->resend($request);
        } catch (FormInputException $exception) {
            return $this->formResponse(
                $request,
                $csrf,
                $exception->status,
                $exception->safeEmail,
                $exception->errors,
            );
        }
        if (!$this->csrf->validates($request, CsrfAction::ACCOUNT_EMAIL_RESEND, $csrf['cookie'], $input->csrfToken)) {
            return $this->formResponse($request, $csrf, 403, $input->email, [], 'form.error.csrf');
        }
        if (!$this->idempotencyHeaderMatches($request, $input->submissionId->toString())) {
            return $this->formResponse($request, $csrf, 409, $input->email, [], 'form.error.idempotency');
        }
        $result = $this->resend->resend(new EmailVerificationResendCommand(
            $input->email,
            $input->submissionId,
            $this->context->peer($request),
            $this->context->locale($request),
        ));
        if ($result->outcome === EmailVerificationResendOutcome::THROTTLED) {
            return $this->formResponse($request, $csrf, 429, $input->email, [], 'form.error.throttled')
                ->withHeader('Retry-After', (string)$result->retryAfterSeconds);
        }
        if ($result->outcome === EmailVerificationResendOutcome::IDEMPOTENCY_CONFLICT) {
            return $this->formResponse($request, $csrf, 409, $input->email, [], 'form.error.idempotency');
        }
        if (!$this->fragments->isFragment($request)) {
            return $this->view->redirect('/register/accepted', $csrf['cookie']);
        }

        return $this->view->render(
            $request,
            'pages.account-registration-accepted',
            'fragments.email-verification-resend-accepted',
            IdentityViewDataFactory::empty(),
            'title.verification.resend',
            202,
            $csrf['cookie'],
            true,
        );
    }

    /** @param array{cookie: \Qmdb\Modules\SecurityWeb\Csrf\CsrfCookie, token: string} $csrf
     * @param array<string, string> $errors
     */
    private function formResponse(
        ServerRequestInterface $request,
        array $csrf,
        int $status,
        string $email,
        array $errors,
        string $globalError = '',
    ): ResponseInterface {
        return $this->view->render(
            $request,
            'pages.email-verification-resend',
            'fragments.email-verification-resend-form',
            IdentityViewDataFactory::resend(
                $csrf['token'],
                VerificationResendSubmissionId::generate()->toString(),
                $email,
                $errors,
                $globalError,
            ),
            'title.verification.resend',
            $status,
            $csrf['cookie'],
            true,
        );
    }

    private function idempotencyHeaderMatches(ServerRequestInterface $request, string $submissionId): bool
    {
        if (!$this->fragments->isFragment($request)) {
            return true;
        }
        $header = trim($request->getHeaderLine('Idempotency-Key'));

        return $header !== '' && hash_equals($submissionId, $header);
    }
}
