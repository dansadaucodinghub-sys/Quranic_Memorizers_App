<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Domain;

use JsonSerializable;
use LogicException;
use Qmdb\Modules\IdentitySessions\Domain\SessionStatus;
use Qmdb\Shared\Time\UtcDateTime;

final readonly class SessionTenantContextState implements JsonSerializable
{
    public function __construct(
        public int $sessionInternalId,
        public int $accountInternalId,
        public ?int $workspaceInternalId,
        public ?int $membershipInternalId,
        public TenantContextVersion $version,
        ?\DateTimeImmutable $selectedAt,
        public SessionStatus $sessionStatus,
        public int $sessionVersion,
        public bool $hasStoredSelection,
        public ?AccountWorkspaceTenantContext $context,
    ) {
        $allAbsent = $workspaceInternalId === null && $membershipInternalId === null && $selectedAt === null;
        $allPresent = $workspaceInternalId !== null && $membershipInternalId !== null && $selectedAt !== null;
        $normalizedSelectedAt = $selectedAt === null ? null : UtcDateTime::normalize($selectedAt);
        if (
            $sessionInternalId < 1 || $accountInternalId < 1 || $sessionVersion < 1
            || (!$allAbsent && !$allPresent) || $hasStoredSelection !== $allPresent
            || ($context !== null && (
                !$allPresent
                || $context->sessionInternalId !== $sessionInternalId
                || $context->accountInternalId !== $accountInternalId
                || $context->workspaceInternalId !== $workspaceInternalId
                || $context->membershipInternalId() !== $membershipInternalId
                || $context->version->value !== $version->value
                || $normalizedSelectedAt === null
                || $context->selectedAt != $normalizedSelectedAt
            ))
        ) {
            throw new \InvalidArgumentException('Session tenant context state is invalid.');
        }
        $this->selectedAt = $normalizedSelectedAt;
    }

    public ?\DateTimeImmutable $selectedAt;

    public function jsonSerialize(): never
    {
        throw new LogicException('Session tenant context state cannot be serialized.');
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        throw new LogicException('Session tenant context state cannot be serialized.');
    }

    /** @return array<string, bool|int|string> */
    public function __debugInfo(): array
    {
        return [
            'session_id' => '[redacted]',
            'account_id' => '[redacted]',
            'has_stored_selection' => $this->hasStoredSelection,
            'context_resolved' => $this->context !== null,
            'tenant_context_version' => $this->version->value,
            'session_status' => $this->sessionStatus->value,
            'session_version' => $this->sessionVersion,
        ];
    }
}
