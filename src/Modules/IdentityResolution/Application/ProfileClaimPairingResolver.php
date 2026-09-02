<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use DateTimeImmutable;
use Qmdb\Modules\IdentityResolution\Domain\ProfileClaimPairingCode;
use Qmdb\Modules\IdentityResolution\Infrastructure\Persistence\MySqlIdentityResolutionRepository;

/**
 * Resolves a one-time pairing only for an already-authorized application
 * workflow.  It deliberately exposes no controller-facing Account lookup.
 */
final readonly class ProfileClaimPairingResolver
{
    public function __construct(
        private MySqlIdentityResolutionRepository $repository,
        private ProfileClaimPairingHasher $hasher,
    ) {
    }

    /** @return array<string, int|string> */
    public function resolveForAuthorization(string $submittedCode, DateTimeImmutable $now): array
    {
        $code = ProfileClaimPairingCode::parse($submittedCode);
        if ($code === null) {
            $this->dummyCompare();
            throw new \DomainException('Claim pairing is unavailable.');
        }

        $pairing = $this->repository->pairingBySelector($code->selector, true);
        if ($pairing === null) {
            if ($this->hasher->matches($code, $this->hasher->dummyHash($code))) {
                // The comparison is intentionally performed to equalize timing.
            }
            throw new \DomainException('Claim pairing is unavailable.');
        }
        if (!$this->hasher->matches($code, (string) $pairing['secret_hash'])) {
            $this->repository->recordPairingFailure($pairing, $now);
            throw new \DomainException('Claim pairing is unavailable.');
        }
        if ($pairing['status'] !== 'ACTIVE' || new DateTimeImmutable((string) $pairing['expires_at']) <= $now) {
            if ($pairing['status'] === 'ACTIVE') {
                $this->repository->expirePairing($pairing, $now);
            }
            throw new \DomainException('Claim pairing is unavailable.');
        }

        return $pairing;
    }

    private function dummyCompare(): void
    {
        $code = ProfileClaimPairingCode::issue('000000000000', str_repeat('A', 22));
        if ($this->hasher->matches($code, $this->hasher->dummyHash($code))) {
            // The comparison is intentionally performed to equalize timing.
        }
    }
}
