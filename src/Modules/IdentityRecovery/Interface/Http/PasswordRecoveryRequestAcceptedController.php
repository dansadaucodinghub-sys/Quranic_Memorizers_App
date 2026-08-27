<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Shared\Http\Contract\Controller;

final readonly class PasswordRecoveryRequestAcceptedController implements Controller
{
    public function __construct(private IdentityAccessView $view)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->view->render(
            $request,
            'pages.password-recovery-request-accepted',
            'fragments.password-recovery-request-accepted',
            RecoveryViewDataFactory::empty(),
            'title.password_recovery.accepted',
            verificationHeaders: true,
        );
    }
}
