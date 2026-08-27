<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryChallengeId;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryToken;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordResetSubmissionId;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;

final readonly class PasswordResetFormController implements Controller
{
    public function __construct(private IdentityCsrf $csrf, private IdentityAccessView $view)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $csrf = $this->csrf->issue($request, CsrfAction::ACCOUNT_PASSWORD_RECOVERY_RESET);
        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
        $challenge = is_array($parameters) && is_string($parameters['challengeId'] ?? null)
            ? $parameters['challengeId']
            : '';
        $token = $request->getQueryParams()['token'] ?? '';
        $token = is_string($token) ? $token : '';
        $valid = $this->validLinkShape($challenge, $token);

        return $this->view->render(
            $request,
            'pages.password-reset',
            'fragments.password-reset-form',
            RecoveryViewDataFactory::reset(
                $csrf['token'],
                PasswordResetSubmissionId::generate()->toString(),
                $challenge,
                $valid ? $token : '',
                $valid,
            ),
            'title.password_reset',
            cookie: $csrf['cookie'],
            verificationHeaders: true,
        );
    }

    private function validLinkShape(string $challenge, string $token): bool
    {
        try {
            PasswordRecoveryChallengeId::fromString($challenge);
            new PasswordRecoveryToken($token);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
