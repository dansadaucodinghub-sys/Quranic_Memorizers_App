<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Interface\Http;

use Qmdb\Shared\Presentation\View\ViewData;

final class IdentityViewDataFactory
{
    private function __construct()
    {
    }

    /** @param array<string, string> $errors */
    public static function registration(
        string $csrf,
        string $submission,
        string $email = '',
        array $errors = [],
        string $globalError = '',
    ): ViewData {
        return new ViewData([
            'csrf_token' => $csrf,
            'submission_id' => $submission,
            'email' => $email,
            'errors' => $errors,
            'global_error' => $globalError,
        ]);
    }

    /** @param array<string, string> $errors */
    public static function resend(
        string $csrf,
        string $submission,
        string $email = '',
        array $errors = [],
        string $globalError = '',
    ): ViewData {
        return self::registration($csrf, $submission, $email, $errors, $globalError);
    }

    /** @param array<string, string> $errors */
    public static function verification(
        string $csrf,
        string $challenge,
        string $token,
        bool $linkValid,
        array $errors = [],
        string $globalError = '',
    ): ViewData {
        return new ViewData([
            'csrf_token' => $csrf,
            'challenge_id' => $challenge,
            'token' => $token,
            'link_valid' => $linkValid,
            'errors' => $errors,
            'global_error' => $globalError,
        ]);
    }

    public static function empty(): ViewData
    {
        return new ViewData();
    }
}
