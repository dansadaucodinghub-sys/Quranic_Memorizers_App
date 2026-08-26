<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Interface\Http;

use Qmdb\Modules\IdentitySessions\Application\AccountSessionInventory;
use Qmdb\Modules\IdentitySessions\Domain\Repository\DeviceInventoryRecord;
use Qmdb\Modules\IdentitySessions\Domain\Repository\SessionInventoryRecord;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class IdentitySessionViewDataFactory
{
    public static function login(
        string $csrfToken,
        string $submissionId,
        string $email = '',
        string $globalError = '',
    ): ViewData {
        return new ViewData([
            'csrf_token' => $csrfToken,
            'submission_id' => $submissionId,
            'email' => $email,
            'global_error' => $globalError,
            'errors' => [],
        ]);
    }

    public static function inventory(
        AccountSessionInventory $inventory,
        string $logoutCsrf,
        string $sessionRevokeCsrf,
        string $deviceRevokeCsrf,
    ): ViewData {
        return new ViewData([
            'sessions' => array_map(static fn (SessionInventoryRecord $record): array => [
                'public_id' => $record->publicId,
                'device_public_id' => $record->devicePublicId,
                'status' => $record->status,
                'version' => $record->version,
                'issued_at' => $record->issuedAt,
                'last_seen_at' => $record->lastSeenAt,
                'idle_expires_at' => $record->idleExpiresAt,
                'absolute_expires_at' => $record->absoluteExpiresAt,
                'current' => $record->publicId === $inventory->currentSessionId,
            ], $inventory->sessions),
            'devices' => array_map(static fn (DeviceInventoryRecord $record): array => [
                'public_id' => $record->publicId,
                'status' => $record->status,
                'version' => $record->version,
                'created_at' => $record->createdAt,
                'last_seen_at' => $record->lastSeenAt,
                'active_session_count' => $record->activeSessionCount,
                'current' => $record->publicId === $inventory->currentDeviceId,
            ], $inventory->devices),
            'logout_csrf_token' => $logoutCsrf,
            'session_revoke_csrf_token' => $sessionRevokeCsrf,
            'device_revoke_csrf_token' => $deviceRevokeCsrf,
        ]);
    }

    public static function confirmation(
        string $kind,
        string $publicId,
        int $version,
        string $csrfToken,
    ): ViewData {
        return new ViewData([
            'kind' => $kind,
            'public_id' => $publicId,
            'version' => $version,
            'csrf_token' => $csrfToken,
        ]);
    }

    public static function completion(): ViewData
    {
        // Page-or-fragment responses render both representations before selecting
        // the negotiated body, so the full-page login template still requires its
        // complete, non-sensitive form data contract on an enhanced success.
        return self::login('', '');
    }
}
