<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Infrastructure\Security;

use InvalidArgumentException;
use Qmdb\Modules\IdentityResolution\Domain\ProfileClaimPairingCode;
use Qmdb\Modules\IdentityResolution\Domain\ProfileClaimPairingCodeGenerator;

final readonly class SecureProfileClaimPairingCodeGenerator implements ProfileClaimPairingCodeGenerator
{
    public function generate(int $entropyBits): ProfileClaimPairingCode
    {
        if ($entropyBits < 128 || $entropyBits > 256 || $entropyBits % 8 !== 0) {
            throw new InvalidArgumentException('Profile claim pairing entropy is invalid.');
        }
        $selector = strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
        $byteLength = intdiv($entropyBits, 8);
        if ($byteLength < 1) {
            throw new InvalidArgumentException('Profile claim pairing entropy is invalid.');
        }
        $secret = rtrim(strtr(base64_encode(random_bytes($byteLength)), '+/', '-_'), '=');

        return ProfileClaimPairingCode::issue($selector, $secret);
    }
}
