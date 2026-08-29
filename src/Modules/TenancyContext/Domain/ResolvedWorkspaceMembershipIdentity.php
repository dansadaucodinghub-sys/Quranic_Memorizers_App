<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Domain;

use JsonSerializable;
use LogicException;
use Qmdb\Modules\Tenancy\Domain\MembershipStatus;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class ResolvedWorkspaceMembershipIdentity implements JsonSerializable
{
    private function __construct(
        public int $membershipInternalId,
        public UuidV7 $membershipId,
        public int $workspaceInternalId,
        public int $accountInternalId,
        public MembershipStatus $status,
        public int $version,
    ) {
        if (
            $membershipInternalId < 1 || $workspaceInternalId < 1
            || $accountInternalId < 1 || $version < 1
        ) {
            throw new \InvalidArgumentException('Resolved workspace membership identity is invalid.');
        }
    }

    public static function trusted(
        int $membershipInternalId,
        UuidV7 $membershipId,
        int $workspaceInternalId,
        int $accountInternalId,
        MembershipStatus $status,
        int $version,
    ): self {
        return new self(
            $membershipInternalId,
            $membershipId,
            $workspaceInternalId,
            $accountInternalId,
            $status,
            $version,
        );
    }

    public function jsonSerialize(): never
    {
        throw new LogicException('Resolved workspace membership identity cannot be serialized.');
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        throw new LogicException('Resolved workspace membership identity cannot be serialized.');
    }

    public function equals(self $other): bool
    {
        return $this->membershipInternalId === $other->membershipInternalId
            && $this->membershipId->toString() === $other->membershipId->toString()
            && $this->workspaceInternalId === $other->workspaceInternalId
            && $this->accountInternalId === $other->accountInternalId
            && $this->status === $other->status
            && $this->version === $other->version;
    }

    /** @return array<string, int|string> */
    public function __debugInfo(): array
    {
        return [
            'status' => $this->status->value,
            'version' => $this->version,
        ];
    }
}
