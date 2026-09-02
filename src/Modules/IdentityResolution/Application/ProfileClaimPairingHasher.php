<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityResolution\Configuration\IdentityResolutionConfiguration;
use Qmdb\Modules\IdentityResolution\Domain\ProfileClaimPairingCode;

final readonly class ProfileClaimPairingHasher
{
    public function __construct(private IdentityResolutionConfiguration $configuration)
    {
    }

    public function hash(ProfileClaimPairingCode $code): string
    {
        return hash_hmac('sha256', "qmdb.profile_claim_pairing.v{$this->configuration->pairingHmacKeyVersion}\0{$code->selector}\0{$code->secret}", $this->configuration->pairingHmacKey, true);
    }

    public function matches(ProfileClaimPairingCode $code, string $storedHash): bool
    {
        return hash_equals($storedHash, $this->hash($code));
    }

    public function dummyHash(ProfileClaimPairingCode $code): string
    {
        return $this->hash($code);
    }
}
