<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Domain;

interface ProfileClaimPairingCodeGenerator
{
    public function generate(int $entropyBits): ProfileClaimPairingCode;
}
