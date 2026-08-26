<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Security\Password;

final readonly class PasswordPolicy
{
    public function __construct(private int $minimumCharacters = 12, private int $maximumBytes = 1024)
    {
        if ($minimumCharacters < 1 || $maximumBytes < $minimumCharacters) {
            throw new \InvalidArgumentException('Password policy is invalid.');
        }
    }

    public function evaluate(SensitivePlaintextPassword $password, string $confirmation): PasswordPolicyResult
    {
        $value = $password->revealForPolicy();
        $violations = [];
        if ($value === '') {
            $violations[] = PasswordPolicyViolation::EMPTY;
        }
        if (!mb_check_encoding($value, 'UTF-8')) {
            $violations[] = PasswordPolicyViolation::INVALID_UTF8;
        } elseif (mb_strlen($value, 'UTF-8') < $this->minimumCharacters) {
            $violations[] = PasswordPolicyViolation::TOO_SHORT;
        }
        if (strlen($value) > $this->maximumBytes) {
            $violations[] = PasswordPolicyViolation::TOO_LONG;
        }
        if (str_contains($value, "\0")) {
            $violations[] = PasswordPolicyViolation::NUL;
        }
        if ($value !== '' && trim($value) === '') {
            $violations[] = PasswordPolicyViolation::WHITESPACE_ONLY;
        }
        if (!hash_equals($value, $confirmation)) {
            $violations[] = PasswordPolicyViolation::CONFIRMATION_MISMATCH;
        }

        return new PasswordPolicyResult(array_values(array_unique($violations, SORT_REGULAR)));
    }
}
