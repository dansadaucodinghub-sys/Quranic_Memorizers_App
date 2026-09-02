<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

/** The secret is intentionally populated only on the initial successful submission. */
final readonly class ProfileClaimPairingCreationResult
{
    public function __construct(
        public string $pairingPublicId,
        public ?string $displayOnceCode,
        public bool $replayed,
    ) {
    }
}
