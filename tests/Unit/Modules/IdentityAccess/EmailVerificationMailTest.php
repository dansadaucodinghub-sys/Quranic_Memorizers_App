<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\IdentityAccess;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\IdentityAccess\Application\Mail\EmailVerificationMessageFactory;
use Qmdb\Modules\IdentityAccess\Configuration\PublicApplicationBaseUrl;
use Qmdb\Modules\IdentityAccess\Domain\EmailVerificationChallengeId;
use Qmdb\Modules\IdentityAccess\Infrastructure\Mail\SymfonyMailerEmailVerificationNotifier;
use Qmdb\Modules\IdentityAccess\Infrastructure\Security\SecureEmailVerificationTokenGenerator;
use Qmdb\Shared\Localization\TranslationCatalog;
use Qmdb\Shared\Presentation\Asset\AssetUrlGenerator;
use Qmdb\Shared\Presentation\Html\HtmlEscaper;
use Qmdb\Shared\Presentation\View\PhpViewRenderer;
use Qmdb\Shared\Presentation\View\ViewRegistry;
use Qmdb\Tests\Support\IdentityAccess\RecordingMailer;
use Symfony\Component\Mime\Email;

final class EmailVerificationMailTest extends TestCase
{
    public function testLocalizedMessagesContainOneCanonicalLinkAndNoTrackingContent(): void
    {
        $challenge = EmailVerificationChallengeId::generate();
        $token = (new SecureEmailVerificationTokenGenerator())->generate();
        foreach (['en', 'ar'] as $locale) {
            $message = $this->factory()->create(
                'person@example.test',
                $locale,
                $challenge,
                $token,
                new DateTimeImmutable('2026-08-26T13:00:00Z'),
            );
            self::assertSame(1, substr_count($message->textBody, '/verify-email/'));
            self::assertSame(1, substr_count($message->htmlBody, '/verify-email/'));
            self::assertStringContainsString('lang=' . $locale, $message->textBody);
            self::assertStringNotContainsString('<img', strtolower($message->htmlBody));
            self::assertStringContainsString($locale === 'ar' ? 'dir="rtl"' : 'dir="ltr"', $message->htmlBody);
        }
    }

    public function testSymfonyNotifierBuildsMultipartEmailWithoutNetworkAccess(): void
    {
        $mailer = new RecordingMailer();
        $message = $this->factory()->create(
            'person@example.test',
            'en',
            EmailVerificationChallengeId::generate(),
            (new SecureEmailVerificationTokenGenerator())->generate(),
            new DateTimeImmutable('2026-08-26T13:00:00Z'),
        );
        (new SymfonyMailerEmailVerificationNotifier(
            $mailer,
            'no-reply@example.test',
            'QMDB Test',
        ))->send($message);

        $email = $mailer->message;
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('person@example.test', $email->getTo()[0]->getAddress());
        self::assertSame('no-reply@example.test', $email->getFrom()[0]->getAddress());
        self::assertNotSame('', $email->getTextBody());
        self::assertNotSame('', $email->getHtmlBody());
    }

    private function factory(): EmailVerificationMessageFactory
    {
        $root = dirname(__DIR__, 4);
        return new EmailVerificationMessageFactory(
            new PublicApplicationBaseUrl('https://example.test', true),
            TranslationCatalog::fromFiles([
                'en' => $root . '/resources/translations/en.php',
                'ar' => $root . '/resources/translations/ar.php',
            ]),
            new PhpViewRenderer(
                new ViewRegistry([
                    'emails.email-verification-html' => $root
                        . '/resources/views/emails/email-verification.html.php',
                    'emails.email-verification-text' => $root
                        . '/resources/views/emails/email-verification.txt.php',
                ]),
                new HtmlEscaper(),
                new AssetUrlGenerator(),
            ),
        );
    }
}
