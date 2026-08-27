<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Interface\Http;

use Qmdb\Shared\Presentation\View\ViewData;

final class RecoveryViewDataFactory
{
    private function __construct()
    {
    }

    /** @param array<string, string> $errors */
    public static function request(
        string $csrf,
        string $submissionId,
        string $email = '',
        array $errors = [],
        string $globalError = '',
    ): ViewData {
        return new ViewData([
            'csrf_token' => $csrf,
            'submission_id' => $submissionId,
            'email' => $email,
            'errors' => $errors,
            'global_error' => $globalError,
        ]);
    }

    /** @param array<string, string> $errors */
    public static function reset(
        string $csrf,
        string $submissionId,
        string $challengeId,
        string $token,
        bool $linkValid,
        array $errors = [],
        string $globalError = '',
    ): ViewData {
        return new ViewData([
            'csrf_token' => $csrf,
            'submission_id' => $submissionId,
            'challenge_id' => $challengeId,
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
