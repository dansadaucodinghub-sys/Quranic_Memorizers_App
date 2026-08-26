<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationChallengeId;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationToken;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;

final readonly class EmailVerificationFormController implements Controller
{
    public function __construct(private IdentityCsrf $csrf, private IdentityAccessView $view)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_EMAIL_VERIFY);
        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
        $challenge = is_array($parameters) && is_string($parameters['challengeId'] ?? null)
            ? $parameters['challengeId']
            : '';
        $token = $request->getQueryParams()['token'] ?? '';
        $token = is_string($token) ? $token : '';
        $valid = $this->validLinkShape($challenge, $token);

        return $this->view->render(
            $request,
            'pages.email-verification-confirm',
            'fragments.email-verification-confirm',
            IdentityViewDataFactory::verification(
                $csrf['token'],
                $challenge,
                $valid ? $token : '',
                $valid,
            ),
            'title.verification.confirm',
            cookie: $csrf['cookie'],
            verificationHeaders: true,
        );
    }

    private function validLinkShape(string $challenge, string $token): bool
    {
        try {
            EmailVerificationChallengeId::fromString($challenge);
            new EmailVerificationToken($token);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
