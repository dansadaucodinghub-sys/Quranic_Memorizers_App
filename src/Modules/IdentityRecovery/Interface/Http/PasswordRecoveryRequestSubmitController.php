<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityRequestContext;
use Qmdb\Modules\IdentityRecovery\Application\PasswordRecoveryRequestCommand;
use Qmdb\Modules\IdentityRecovery\Application\PasswordRecoveryRequestOutcome;
use Qmdb\Modules\IdentityRecovery\Application\PasswordRecoveryRequestService;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryRequestSubmissionId;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Request\RequestContextAttributes;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;

final readonly class PasswordRecoveryRequestSubmitController implements Controller
{
    public function __construct(
        private RecoveryFormInputMapper $input,
        private IdentityCsrf $csrf,
        private IdentityAccessView $view,
        private IdentityRequestContext $context,
        private PasswordRecoveryRequestService $recovery,
        private FragmentRequestDetector $fragments,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_PASSWORD_RECOVERY_REQUEST);
        try {
            $input = $this->input->request($request);
        } catch (RecoveryFormInputException $exception) {
            return $this->form(
                $request,
                $csrf,
                $exception->status,
                $exception->safeEmail,
                $exception->errors,
            );
        }
        if (
            !$this->csrf->validates(
                $request,
                CsrfAction::ACCOUNT_PASSWORD_RECOVERY_REQUEST,
                $csrf['cookie'],
                $input->csrfToken,
            )
        ) {
            return $this->form($request, $csrf, 403, $input->email, [], 'form.error.csrf');
        }
        if (!$this->idempotencyMatches($request, $input->submissionId->toString())) {
            return $this->form($request, $csrf, 409, $input->email, [], 'form.error.idempotency');
        }
        $requestId = $request->getAttribute(RequestContextAttributes::REQUEST_ID);
        $result = $this->recovery->request(new PasswordRecoveryRequestCommand(
            $input->email,
            $input->submissionId,
            $this->context->peer($request),
            $this->context->locale($request),
            $requestId instanceof CorrelationId ? $requestId->value() : null,
        ));
        if ($result->outcome === PasswordRecoveryRequestOutcome::THROTTLED) {
            return $this->form($request, $csrf, 429, $input->email, [], 'form.error.throttled')
                ->withHeader('Retry-After', (string)$result->retryAfterSeconds);
        }
        if ($result->outcome === PasswordRecoveryRequestOutcome::IDEMPOTENCY_CONFLICT) {
            return $this->form($request, $csrf, 409, $input->email, [], 'form.error.idempotency');
        }
        if (!$this->fragments->isFragment($request)) {
            return $this->secureRedirect($this->view->redirect('/forgot-password/accepted', $csrf['cookie']));
        }

        return $this->view->render(
            $request,
            'pages.password-recovery-request-accepted',
            'fragments.password-recovery-request-accepted',
            RecoveryViewDataFactory::empty(),
            'title.password_recovery.accepted',
            202,
            $csrf['cookie'],
            true,
        );
    }

    /** @param array{cookie: \Qmdb\Modules\SecurityWeb\Csrf\CsrfCookie, token: string} $csrf
     * @param array<string, string> $errors
     */
    private function form(
        ServerRequestInterface $request,
        array $csrf,
        int $status,
        string $email,
        array $errors,
        string $globalError = '',
    ): ResponseInterface {
        return $this->view->render(
            $request,
            'pages.password-recovery-request',
            'fragments.password-recovery-request-form',
            RecoveryViewDataFactory::request(
                $csrf['token'],
                PasswordRecoveryRequestSubmissionId::generate()->toString(),
                $email,
                $errors,
                $globalError,
            ),
            'title.password_recovery.request',
            $status,
            $csrf['cookie'],
            true,
        );
    }

    private function idempotencyMatches(ServerRequestInterface $request, string $submissionId): bool
    {
        $header = trim($request->getHeaderLine('Idempotency-Key'));

        return $header === ''
            ? !$this->fragments->isFragment($request)
            : hash_equals($submissionId, $header);
    }

    private function secureRedirect(ResponseInterface $response): ResponseInterface
    {
        return $response
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
