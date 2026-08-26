<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Domain\VerificationResendSubmissionId;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;

final readonly class EmailVerificationResendFormController implements Controller
{
    public function __construct(private IdentityCsrf $csrf, private IdentityAccessView $view)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_EMAIL_RESEND);

        return $this->view->render(
            $request,
            'pages.email-verification-resend',
            'fragments.email-verification-resend-form',
            IdentityViewDataFactory::resend(
                $csrf['token'],
                VerificationResendSubmissionId::generate()->toString(),
            ),
            'title.verification.resend',
            cookie: $csrf['cookie'],
            verificationHeaders: true,
        );
    }
}
