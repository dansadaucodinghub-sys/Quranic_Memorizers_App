<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Domain;

use DateTimeImmutable;
use LogicException;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;

/** Server-resolved, session-bound context. Exact permissions remain in persistence, never in this object. */
final readonly class PrivilegedAccessContext
{
    public function __construct(
        public int $subjectAccountInternalId,
        public int $sessionInternalId,
        public PrivilegedAccessRequestId $requestId,
        public PrivilegedAccessActivationId $activationId,
        public PrivilegedAccessType $type,
        public AuthorizationScopeType $scope,
        public ?int $workspaceInternalId,
        public int $tenantContextVersion,
        public AuthenticationAssuranceLevel $assurance,
        public DateTimeImmutable $activatedAt,
        public DateTimeImmutable $expiresAt,
    ) {
        if (
            $subjectAccountInternalId < 1 || $sessionInternalId < 1 || $tenantContextVersion < 1
            || ($scope === AuthorizationScopeType::PLATFORM && $workspaceInternalId !== null)
            || ($scope === AuthorizationScopeType::WORKSPACE && ($workspaceInternalId === null || $workspaceInternalId < 1))
            || $expiresAt <= $activatedAt
        ) {
            throw new \InvalidArgumentException('Privileged-access context is invalid.');
        }
    }

    public function jsonSerialize(): never
    {
        throw new LogicException('Privileged-access context cannot be serialized.');
    }

    /** @return array<string, int|string> */
    public function __debugInfo(): array
    {
        return [
            'type' => $this->type->value,
            'scope' => $this->scope->value,
            'workspace_internal_id' => $this->workspaceInternalId ?? 0,
            'tenant_context_version' => $this->tenantContextVersion,
            'expires_at' => $this->expiresAt->format(DATE_ATOM),
            'subject' => '[redacted]',
            'session' => '[redacted]',
            'request' => '[redacted]',
            'activation' => '[redacted]',
        ];
    }
}
