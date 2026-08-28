<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\IdentitySecurityNotifications;

use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\IdentitySecurityNotifications\Application\AccountSecurityNotificationFailureClassifier;
use Qmdb\Modules\IdentitySecurityNotifications\Application\SecurityNotificationRetryPolicy;
use Qmdb\Modules\IdentitySecurityNotifications\Configuration\SecurityNotificationConfiguration;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotification;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationId;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationStatus;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\NotificationClaimExecutionId;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\SecurityNotificationDeduplicationKeyFactory;
use Qmdb\Modules\IdentityAccess\Application\Mail\EmailDeliveryException;
use Qmdb\Modules\IdentityAccess\Configuration\PublicApplicationBaseUrl;
use Qmdb\Modules\IdentitySecurityNotifications\Application\Mail\AccountSecurityNotificationMessageFactory;
use Qmdb\Modules\IdentitySecurityNotifications\Application\Mail\PasswordResetCompletedNotificationMessageFactory;
use Qmdb\Shared\Localization\TranslationCatalog;
use Qmdb\Shared\Presentation\Asset\AssetUrlGenerator;
use Qmdb\Shared\Presentation\Html\HtmlEscaper;
use Qmdb\Shared\Presentation\View\PhpViewRenderer;
use Qmdb\Shared\Presentation\View\ViewRegistry;

final class SecurityNotificationTest extends TestCase
{
    public function testDeduplicationIsStableAndScopedToSourceAndAccount(): void
    {
        $factory = new SecurityNotificationDeduplicationKeyFactory();
        $first = $factory->passwordResetCompleted('challenge-1', 'account-1');

        self::assertSame($first->toBinary(), $factory->passwordResetCompleted(
            'challenge-1',
            'account-1',
        )->toBinary());
        self::assertNotSame($first->toBinary(), $factory->passwordResetCompleted(
            'challenge-2',
            'account-1',
        )->toBinary());
        self::assertSame(32, strlen($first->toBinary()));
    }

    public function testRetryPolicyIsExponentialBoundedAndStopsAtMaximumAttempts(): void
    {
        $policy = new SecurityNotificationRetryPolicy(new SecurityNotificationConfiguration(25, 5, 120, 60, 300));

        self::assertSame(60, $policy->delaySeconds(1));
        self::assertSame(120, $policy->delaySeconds(2));
        self::assertSame(240, $policy->delaySeconds(3));
        self::assertSame(300, $policy->delaySeconds(4));
        self::assertNull($policy->delaySeconds(5));
    }

    public function testFailureClassifierDoesNotPersistExceptionText(): void
    {
        $classifier = new AccountSecurityNotificationFailureClassifier();

        self::assertSame(
            ['retryable' => true, 'code' => 'MAIL_TRANSPORT'],
            $classifier->classify(new EmailDeliveryException('smtp secret detail')),
        );
        self::assertSame(
            ['retryable' => false, 'code' => 'DELIVERY_CONFIGURATION'],
            $classifier->classify(new \RuntimeException('configuration secret detail')),
        );
    }

    public function testClaimedNotificationRequiresAnExecutionIdAndContainsNoRecipientOrBody(): void
    {
        $notification = new AccountSecurityNotification(
            1,
            AccountSecurityNotificationId::generate(),
            2,
            3,
            AccountSecurityNotificationType::PASSWORD_RESET_COMPLETED,
            (new SecurityNotificationDeduplicationKeyFactory())->passwordResetCompleted('c', 'a'),
            'ar',
            AccountSecurityNotificationStatus::CLAIMED,
            1,
            5,
            new DateTimeImmutable(),
            NotificationClaimExecutionId::generate(),
            new DateTimeImmutable('+2 minutes'),
            2,
            new DateTimeImmutable(),
        );

        $this->expectException(DomainException::class);
        new AccountSecurityNotification(
            1,
            AccountSecurityNotificationId::generate(),
            2,
            3,
            AccountSecurityNotificationType::PASSWORD_RESET_COMPLETED,
            (new SecurityNotificationDeduplicationKeyFactory())->passwordResetCompleted('c', 'a'),
            'en',
            AccountSecurityNotificationStatus::CLAIMED,
            1,
            5,
            new DateTimeImmutable(),
            null,
            new DateTimeImmutable('+2 minutes'),
            2,
            new DateTimeImmutable(),
        );
    }

    public function testRoleChangeNotificationsRenderDistinctSafeEnglishAndArabicMessages(): void
    {
        $factory = $this->roleNotificationFactory();
        foreach (
            [
            AccountSecurityNotificationType::PLATFORM_ROLE_ASSIGNED,
            AccountSecurityNotificationType::PLATFORM_ROLE_REVOKED,
            AccountSecurityNotificationType::WORKSPACE_ROLE_ASSIGNED,
            AccountSecurityNotificationType::WORKSPACE_ROLE_REVOKED,
            ] as $type
        ) {
            foreach (['en', 'ar'] as $locale) {
                $message = $factory->create(
                    'person@example.test',
                    $locale,
                    new DateTimeImmutable('2026-08-28T09:15:00Z'),
                    $type,
                );
                self::assertNotSame('', $message->subject);
                self::assertStringContainsString('2026-08-28 09:15 UTC', $message->textBody);
                self::assertStringContainsString($locale === 'ar' ? 'dir="rtl"' : 'dir="ltr"', $message->htmlBody);
                self::assertStringNotContainsString('person@example.test', $message->textBody . $message->htmlBody);
                self::assertStringNotContainsString('permission', strtolower($message->textBody . $message->htmlBody));
                self::assertDoesNotMatchRegularExpression(
                    '/\b[0-9a-f]{8}-[0-9a-f-]{27}\b/i',
                    $message->textBody . $message->htmlBody,
                );
            }
        }
    }

    private function roleNotificationFactory(): AccountSecurityNotificationMessageFactory
    {
        $root = dirname(__DIR__, 4);
        $translations = TranslationCatalog::fromFiles([
            'en' => $root . '/resources/translations/en.php',
            'ar' => $root . '/resources/translations/ar.php',
        ]);
        $views = new PhpViewRenderer(
            new ViewRegistry([
                'emails.password-reset-completed-html' => $root
                    . '/resources/views/emails/password-reset-completed.html.php',
                'emails.password-reset-completed-text' => $root
                    . '/resources/views/emails/password-reset-completed.txt.php',
                'emails.security-event-html' => $root . '/resources/views/emails/security-event.html.php',
                'emails.security-event-text' => $root . '/resources/views/emails/security-event.txt.php',
            ]),
            new HtmlEscaper(),
            new AssetUrlGenerator(),
        );
        $baseUrl = new PublicApplicationBaseUrl('https://example.test', true);

        return new AccountSecurityNotificationMessageFactory(
            new PasswordResetCompletedNotificationMessageFactory($baseUrl, $translations, $views),
            $baseUrl,
            $translations,
            $views,
        );
    }
}
