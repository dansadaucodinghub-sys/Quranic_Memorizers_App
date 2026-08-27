<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySecurityNotifications\Application\Mail;

use DateTimeImmutable;
use Qmdb\Modules\IdentityAccess\Configuration\PublicApplicationBaseUrl;
use Qmdb\Shared\Localization\Locale;
use Qmdb\Shared\Localization\TranslationCatalog;
use Qmdb\Shared\Localization\Translator;
use Qmdb\Shared\Presentation\View\PhpViewRenderer;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class PasswordResetCompletedNotificationMessageFactory
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
        DateTimeImmutable $occurredAt,
    ): AccountSecurityNotificationMessage {
        $locale = $locale === 'ar' ? 'ar' : 'en';
        $translator = new Translator($this->translations, new Locale($locale));
        $view = new ViewData([
            'login_url' => $this->baseUrl->path('/login') . '?lang=' . $locale,
            'occurred_at' => $occurredAt->format('Y-m-d H:i \U\T\C'),
            'locale' => $locale,
        ]);

        return new AccountSecurityNotificationMessage(
            $recipient,
            $translator->trans('email.password_reset_completed.subject'),
            $this->views->render('emails.password-reset-completed-text', $view, $translator)->trustedHtml(),
            $this->views->render('emails.password-reset-completed-html', $view, $translator)->trustedHtml(),
        );
    }
}
