<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Application;

use DateTimeImmutable;
use OTPHP\TOTP;
use Qmdb\Modules\IdentityMultiFactor\Configuration\IdentityMultiFactorConfiguration;
use Qmdb\Modules\IdentityMultiFactor\Domain\TotpSecret;

final readonly class TotpVerifier
{
    public function __construct(private IdentityMultiFactorConfiguration $configuration)
    {
    }

    public function acceptedCounter(
        TotpSecret $secret,
        string $code,
        DateTimeImmutable $now,
        ?int $lastAcceptedCounter,
    ): ?int {
        if (preg_match('/\A[0-9]{6}\z/', $code) !== 1) {
            return null;
        }
        $totp = TOTP::create(
            $secret->revealForTotp(),
            max(1, $this->configuration->totpPeriodSeconds),
            'sha1',
            max(1, $this->configuration->totpDigits),
        );
        $current = max(0, intdiv($now->getTimestamp(), max(1, $this->configuration->totpPeriodSeconds)));
        for (
            $drift = -$this->configuration->totpAllowedDriftSteps;
            $drift <= $this->configuration->totpAllowedDriftSteps;
            ++$drift
        ) {
            $counter = $current + $drift;
            if ($counter < 0 || $lastAcceptedCounter !== null && $counter <= $lastAcceptedCounter) {
                continue;
            }
            if (hash_equals($totp->at(max(0, $counter * $this->configuration->totpPeriodSeconds)), $code)) {
                return $counter;
            }
        }

        return null;
    }

    public function provisioningUri(TotpSecret $secret, string $accountPublicId): string
    {
        $totp = TOTP::create(
            $secret->revealForTotp(),
            max(1, $this->configuration->totpPeriodSeconds),
            'sha1',
            max(1, $this->configuration->totpDigits),
        );
        $totp->setIssuer($this->configuration->totpIssuer !== '' ? $this->configuration->totpIssuer : 'QMDB');
        $totp->setLabel('account-' . $accountPublicId);

        return $totp->getProvisioningUri();
    }
}
