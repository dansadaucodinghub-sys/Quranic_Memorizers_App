<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\IdentityAccess;

use Qmdb\Modules\Identity\Domain\Value\SensitivePasswordHash;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordVerificationResult;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordVerifier;
use Qmdb\Modules\IdentityAccess\Security\Password\SensitivePlaintextPassword;

final class RecordingPasswordVerifier implements PasswordVerifier
{
    public int $calls = 0;
    public ?SensitivePasswordHash $lastHash = null;

    public function __construct(private readonly PasswordVerificationResult $result)
    {
    }

    public function verify(
        SensitivePlaintextPassword $password,
        SensitivePasswordHash $hash,
    ): PasswordVerificationResult {
        $this->calls++;
        $this->lastHash = $hash;
        return $this->result;
    }
}
