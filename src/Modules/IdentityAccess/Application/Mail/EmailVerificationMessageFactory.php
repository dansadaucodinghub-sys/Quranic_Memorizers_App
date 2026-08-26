<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Mail;

use DateTimeImmutable;
use Qmdb\Modules\IdentityAccess\Configuration\PublicApplicationBaseUrl;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationChallengeId;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationToken;
use Qmdb\Shared\Localization\Locale;
use Qmdb\Shared\Localization\TranslationCatalog;
use Qmdb\Shared\Localization\Translator;
use Qmdb\Shared\Presentation\View\PhpViewRenderer;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class EmailVerificationMessageFactory
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
        EmailVerificationChallengeId $challengeId,
        EmailVerificationToken $token,
        DateTimeImmutable $expiresAt,
    ): EmailVerificationMessage {
        $locale = $locale === 'ar' ? 'ar' : 'en';
        $url = $this->baseUrl->path('/verify-email/' . rawurlencode($challengeId->toString()))
            . '?token=' . rawurlencode($token->revealForProof()) . '&lang=' . $locale;
        $expiry = $expiresAt->format('Y-m-d H:i \U\T\C');
        $translator = new Translator($this->translations, new Locale($locale));
        $view = new ViewData(['url' => $url, 'expiry' => $expiry, 'locale' => $locale]);
        $text = $this->views->render('emails.email-verification-text', $view, $translator)->trustedHtml();
        $html = $this->views->render('emails.email-verification-html', $view, $translator)->trustedHtml();

        return new EmailVerificationMessage($recipient, $translator->trans('email.verification.subject'), $text, $html);
    }
}
