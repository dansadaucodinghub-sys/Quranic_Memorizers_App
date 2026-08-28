<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySecurityNotifications\Application\Mail;

use DateTimeImmutable;
use Qmdb\Modules\IdentityAccess\Configuration\PublicApplicationBaseUrl;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Shared\Localization\Locale;
use Qmdb\Shared\Localization\TranslationCatalog;
use Qmdb\Shared\Localization\Translator;
use Qmdb\Shared\Presentation\View\PhpViewRenderer;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class AccountSecurityNotificationMessageFactory
{
    public function __construct(
        private PasswordResetCompletedNotificationMessageFactory $passwordResetMessages,
        private PublicApplicationBaseUrl $baseUrl,
        private TranslationCatalog $translations,
        private PhpViewRenderer $views,
    ) {
    }

    public function create(
        string $recipient,
        string $locale,
        DateTimeImmutable $occurredAt,
        AccountSecurityNotificationType $type,
    ): AccountSecurityNotificationMessage {
        if ($type === AccountSecurityNotificationType::PASSWORD_RESET_COMPLETED) {
            return $this->passwordResetMessages->create($recipient, $locale, $occurredAt);
        }

        $locale = $locale === 'ar' ? 'ar' : 'en';
        $translator = new Translator($this->translations, new Locale($locale));
        $translationPrefix = 'email.security_event.' . strtolower($type->value);
        $view = new ViewData([
            'heading_key' => $translationPrefix . '.heading',
            'body_key' => $translationPrefix . '.body',
            'login_url' => $this->baseUrl->path('/login') . '?lang=' . $locale,
            'occurred_at' => $occurredAt->format('Y-m-d H:i \U\T\C'),
            'locale' => $locale,
        ]);

        return new AccountSecurityNotificationMessage(
            $recipient,
            $translator->trans($translationPrefix . '.heading'),
            $this->views->render('emails.security-event-text', $view, $translator)->trustedHtml(),
            $this->views->render('emails.security-event-html', $view, $translator)->trustedHtml(),
        );
    }
}
