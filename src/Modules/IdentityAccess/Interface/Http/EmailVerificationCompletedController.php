<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Shared\Http\Contract\Controller;

final readonly class EmailVerificationCompletedController implements Controller
{
    public function __construct(private IdentityAccessView $view)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->view->render(
            $request,
            'pages.email-verification-completed',
            'fragments.email-verification-completed',
            IdentityViewDataFactory::empty(),
            'title.verification.completed',
            verificationHeaders: true,
        );
    }
}
