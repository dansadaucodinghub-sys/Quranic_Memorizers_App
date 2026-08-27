<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Shared\Http\Contract\Controller;

final readonly class PasswordResetCompletedController implements Controller
{
    public function __construct(private IdentityAccessView $view)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->view->render(
            $request,
            'pages.password-reset-completed',
            'fragments.password-reset-completed',
            RecoveryViewDataFactory::empty(),
            'title.password_reset.completed',
            verificationHeaders: true,
        );
    }
}
