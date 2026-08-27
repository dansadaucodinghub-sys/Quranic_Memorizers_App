<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

use Qmdb\Modules\IdentityMultiFactor\Configuration\IdentityMultiFactorConfiguration;

final readonly class WebAuthnRelyingPartyConfiguration
{
    /** @var list<string> */
    public array $allowedOrigins;

    /** @param list<string> $allowedOrigins */
    public function __construct(
        public string $id,
        public string $name,
        array $allowedOrigins,
        public int $challengeTtlSeconds,
        public int $maximumResponseBytes,
    ) {
        $this->allowedOrigins = $allowedOrigins;
    }

    public static function fromConfiguration(IdentityMultiFactorConfiguration $configuration): self
    {
        return new self(
            $configuration->relyingPartyId,
            $configuration->relyingPartyName,
            $configuration->allowedOrigins,
            $configuration->webauthnChallengeTtlSeconds,
            $configuration->webauthnMaximumResponseBytes,
        );
    }

    public function acceptsOrigin(string $origin): bool
    {
        return in_array($origin, $this->allowedOrigins, true);
    }
}
