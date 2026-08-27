<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Infrastructure\Security;

use Webauthn\Counter\CounterChecker;
use Webauthn\CredentialRecord;
use Webauthn\Exception\CounterException;

final readonly class PasskeyCounterChecker implements CounterChecker
{
    public function check(CredentialRecord $credentialRecord, int $currentCounter): void
    {
        if ($credentialRecord->backupEligible === true || $credentialRecord->counter === 0 || $currentCounter === 0) {
            return;
        }
        if ($currentCounter <= $credentialRecord->counter) {
            throw CounterException::create(
                $currentCounter,
                $credentialRecord->counter,
                'Possible cloned single-device credential.',
            );
        }
    }
}
