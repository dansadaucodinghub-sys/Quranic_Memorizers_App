<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Application;

use Qmdb\Modules\Identity\Infrastructure\Security\ContactCipher;
use Qmdb\Modules\IdentitySecurityNotifications\Application\Mail\AccountSecurityNotificationMessage;
use Qmdb\Modules\IdentitySecurityNotifications\Application\Mail\AccountSecurityNotificationNotifier;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;
use Throwable;

/** Claims in a transaction, calls the provider outside it, then records a bounded outcome. */
final readonly class CommunityNotificationDeliveryService
{
    public function __construct(
        private CommunityNotificationIntentRepository $notifications,
        private TransactionManager $transactions,
        private ContactCipher $contacts,
        private AccountSecurityNotificationNotifier $notifier,
        private Clock $clock,
        private string $publicBaseUrl,
    ) {
    }

    public function deliverDue(int $limit = 25): CommunityNotificationDeliveryResult
    {
        $owner = UuidV7::generate();
        $now = $this->clock->now();
        $claimed = $this->transactions->transactional(fn (): array => $this->notifications->claimDue(
            $owner,
            $now,
            $now->modify('+120 seconds'),
            $limit,
        ));
        $delivered = 0;
        $retried = 0;
        $dead = 0;
        $stale = 0;
        foreach ($claimed as $notification) {
            try {
                if (
                    $notification['email_ciphertext'] === null || $notification['email_key_id'] === null
                    || !hash_equals($this->contacts->keyId(), $notification['email_key_id'])
                ) {
                    $changed = $this->transactions->transactional(fn (): bool => $this->notifications->deadLetter(
                        $notification['id'],
                        $owner,
                        'RECIPIENT_UNAVAILABLE',
                        $this->clock->now(),
                    ));
                    $dead += $changed ? 1 : 0;
                    $stale += $changed ? 0 : 1;
                    continue;
                }
                $this->notifier->send($this->message(
                    $this->contacts->decrypt($notification['email_ciphertext']),
                    $notification['locale'],
                    $notification['type_code'],
                    $notification['safe_state_code'],
                    $notification['subject_public_id'],
                ));
                $changed = $this->transactions->transactional(fn (): bool => $this->notifications->delivered(
                    $notification['id'],
                    $owner,
                    $this->clock->now(),
                ));
                $delivered += $changed ? 1 : 0;
                $stale += $changed ? 0 : 1;
            } catch (Throwable) {
                if ($notification['attempt_count'] >= 5) {
                    $changed = $this->transactions->transactional(fn (): bool => $this->notifications->deadLetter(
                        $notification['id'],
                        $owner,
                        'PROVIDER_FAILURE',
                        $this->clock->now(),
                    ));
                    $dead += $changed ? 1 : 0;
                    $stale += $changed ? 0 : 1;
                    continue;
                }
                $delay = min(3600, 30 * (2 ** ($notification['attempt_count'] - 1)));
                $changed = $this->transactions->transactional(fn (): bool => $this->notifications->retry(
                    $notification['id'],
                    $owner,
                    'PROVIDER_FAILURE',
                    $this->clock->now()->modify('+' . $delay . ' seconds'),
                    $this->clock->now(),
                ));
                $retried += $changed ? 1 : 0;
                $stale += $changed ? 0 : 1;
            }
        }
        return new CommunityNotificationDeliveryResult(count($claimed), $delivered, $retried, $dead, $stale);
    }

    private function message(
        string $recipient,
        string $locale,
        string $type,
        string $state,
        UuidV7 $subject,
    ): AccountSecurityNotificationMessage {
        $arabic = $locale === 'ar';
        $subjectLine = $arabic ? 'تحديث حالة المجتمع' : 'Community status update';
        $body = $arabic
            ? 'تم تحديث حالة عنصر مجتمعي. الحالة: ' . $state
            : 'A community item status was updated. Status: ' . $state;
        $url = rtrim($this->publicBaseUrl, '/') . '/community?notice=' . rawurlencode($subject->toString());
        $text = $body . "\n" . $url;
        return new AccountSecurityNotificationMessage(
            $recipient,
            $subjectLine,
            $text,
            '<p>' . htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p><p><a href="'
            . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            . ($arabic ? 'فتح المجتمع' : 'Open community') . '</a></p>',
        );
    }
}
