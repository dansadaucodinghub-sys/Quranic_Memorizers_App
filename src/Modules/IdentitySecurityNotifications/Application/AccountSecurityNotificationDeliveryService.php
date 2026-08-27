<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySecurityNotifications\Application;

use Qmdb\Modules\Identity\Infrastructure\Security\ContactCipher;
use Qmdb\Modules\IdentitySecurityNotifications\Application\Mail\AccountSecurityNotificationNotifier;
use Qmdb\Modules\IdentitySecurityNotifications\Application\Mail\PasswordResetCompletedNotificationMessageFactory;
use Qmdb\Modules\IdentitySecurityNotifications\Configuration\SecurityNotificationConfiguration;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotification;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\NotificationClaimExecutionId;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\Repository\AccountSecurityNotificationRepository;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;
use Throwable;

final readonly class AccountSecurityNotificationDeliveryService
{
    public function __construct(
        private AccountSecurityNotificationRepository $notifications,
        private TransactionManager $transactions,
        private ContactCipher $contacts,
        private PasswordResetCompletedNotificationMessageFactory $messages,
        private AccountSecurityNotificationNotifier $notifier,
        private AccountSecurityNotificationFailureClassifier $failureClassifier,
        private SecurityNotificationRetryPolicy $retryPolicy,
        private SecurityNotificationConfiguration $configuration,
        private Clock $clock,
    ) {
    }

    public function deliverDue(): AccountSecurityNotificationDeliveryResult
    {
        $executionId = NotificationClaimExecutionId::generate();
        $now = $this->clock->now();
        $leaseExpiresAt = $now->modify('+' . $this->configuration->leaseSeconds . ' seconds');
        $claimed = $this->transactions->transactional(fn (): array => $this->notifications->claimDue(
            $executionId,
            $now,
            $leaseExpiresAt,
            $this->configuration->batchSize,
        ));
        $delivered = 0;
        $retried = 0;
        $failed = 0;
        $stale = 0;
        foreach ($claimed as $notification) {
            try {
                $ciphertext = $this->notifications->verifiedRecipientCiphertext($notification);
                if ($ciphertext === null) {
                    $changed = $this->fail($notification, $executionId, 'RECIPIENT_NOT_VERIFIED');
                    $failed += $changed ? 1 : 0;
                    $stale += $changed ? 0 : 1;
                    continue;
                }
                $recipient = $this->contacts->decrypt($ciphertext);
                $this->notifier->send($this->messages->create(
                    $recipient,
                    $notification->locale,
                    $notification->occurredAt,
                ));
                $changed = $this->transactions->transactional(fn (): bool =>
                    $this->notifications->markDelivered($notification, $executionId, $this->clock->now()));
                $delivered += $changed ? 1 : 0;
                $stale += $changed ? 0 : 1;
            } catch (Throwable $failure) {
                $classification = $this->failureClassifier->classify($failure);
                $delay = $classification['retryable']
                    ? $this->retryPolicy->delaySeconds($notification->attemptCount)
                    : null;
                if ($delay === null) {
                    $changed = $this->fail($notification, $executionId, $classification['code']);
                    $failed += $changed ? 1 : 0;
                    $stale += $changed ? 0 : 1;
                    continue;
                }
                $changed = $this->transactions->transactional(fn (): bool =>
                    $this->notifications->scheduleRetry(
                        $notification,
                        $executionId,
                        $this->clock->now()->modify('+' . $delay . ' seconds'),
                        $classification['code'],
                        $this->clock->now(),
                    ));
                $retried += $changed ? 1 : 0;
                $stale += $changed ? 0 : 1;
            }
        }

        return new AccountSecurityNotificationDeliveryResult(
            count($claimed),
            $delivered,
            $retried,
            $failed,
            $stale,
        );
    }

    private function fail(
        AccountSecurityNotification $notification,
        NotificationClaimExecutionId $executionId,
        string $code,
    ): bool {
        return $this->transactions->transactional(fn (): bool => $this->notifications->markFailed(
            $notification,
            $executionId,
            $code,
            $this->clock->now(),
        ));
    }
}
