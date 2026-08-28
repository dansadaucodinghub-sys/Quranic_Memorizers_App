<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application;

use DateTimeImmutable;
use JsonSerializable;
use LogicException;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentitySessions\Domain\SessionId;

final readonly class AuthorizationSubject implements JsonSerializable
{
    private function __construct(
        public int $accountInternalId,
        public AccountId $accountId,
        public int $sessionInternalId,
        public SessionId $sessionId,
        public AuthenticationAssuranceLevel $assurance,
        public DateTimeImmutable $authenticatedAt,
        public ?DateTimeImmutable $strongAuthenticatedAt,
    ) {
        if ($accountInternalId < 1 || $sessionInternalId < 1) {
            throw new \InvalidArgumentException('Authorization subject is invalid.');
        }
    }

    public static function fromAuthenticatedContext(AuthenticatedAccountContext $context): self
    {
        return new self(
            $context->accountInternalId,
            $context->accountId,
            $context->sessionInternalId,
            $context->sessionId,
            $context->assurance->level,
            $context->authenticatedAt,
            $context->assurance->strongAuthenticatedAt,
        );
    }

    public function jsonSerialize(): never
    {
        throw new LogicException('Authorization subject cannot be serialized.');
    }
}
