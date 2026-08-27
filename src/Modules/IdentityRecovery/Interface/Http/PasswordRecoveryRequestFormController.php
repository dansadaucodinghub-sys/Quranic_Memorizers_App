<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryRequestSubmissionId;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;

final readonly class PasswordRecoveryRequestFormController implements Controller
{
    public function __construct(private IdentityCsrf $csrf, private IdentityAccessView $view)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_PASSWORD_RECOVERY_REQUEST);

        return $this->view->render(
            $request,
            'pages.password-recovery-request',
            'fragments.password-recovery-request-form',
            RecoveryViewDataFactory::request(
                $csrf['token'],
                PasswordRecoveryRequestSubmissionId::generate()->toString(),
            ),
            'title.password_recovery.request',
            cookie: $csrf['cookie'],
            verificationHeaders: true,
        );
    }
}
