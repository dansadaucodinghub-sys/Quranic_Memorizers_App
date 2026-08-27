<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Application\Mail;

use DateTimeImmutable;
use Qmdb\Modules\IdentityAccess\Configuration\PublicApplicationBaseUrl;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryChallengeId;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryToken;
use Qmdb\Shared\Localization\Locale;
use Qmdb\Shared\Localization\TranslationCatalog;
use Qmdb\Shared\Localization\Translator;
use Qmdb\Shared\Presentation\View\PhpViewRenderer;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class PasswordRecoveryMessageFactory
{
    public function __construct(
        private PublicApplicationBaseUrl $baseUrl,
        private TranslationCatalog $translations,
        private PhpViewRenderer $views,
    ) {
    }

    public function create(
        string $recipient,
        string $locale,
        PasswordRecoveryChallengeId $challengeId,
        PasswordRecoveryToken $token,
        DateTimeImmutable $expiresAt,
    ): PasswordRecoveryMessage {
        $locale = $locale === 'ar' ? 'ar' : 'en';
        $url = $this->baseUrl->path('/reset-password/' . rawurlencode($challengeId->toString()))
            . '?token=' . rawurlencode($token->revealForProof()) . '&lang=' . $locale;
        $translator = new Translator($this->translations, new Locale($locale));
        $view = new ViewData([
            'url' => $url,
            'expiry' => $expiresAt->format('Y-m-d H:i \U\T\C'),
            'locale' => $locale,
        ]);

        return new PasswordRecoveryMessage(
            $recipient,
            $translator->trans('email.recovery.subject'),
            $this->views->render('emails.password-recovery-text', $view, $translator)->trustedHtml(),
            $this->views->render('emails.password-recovery-html', $view, $translator)->trustedHtml(),
        );
    }
}
