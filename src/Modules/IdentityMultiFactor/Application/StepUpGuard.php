<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Application;

use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\StepUpGrantRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Shared\Time\Clock;

final readonly class StepUpGuard
{
    public function __construct(
        private StepUpGrantRepository $grants,
        private Clock $clock,
    ) {
    }

    public function consume(AuthenticatedAccountContext $context, StepUpAction $action): void
    {
        $grant = $this->require($context, $action, true);
        if (!$this->grants->consumeGrant($grant, $this->clock->now())) {
            throw new \DomainException('A fresh action-bound identity verification is required.');
        }
    }

    public function permits(AuthenticatedAccountContext $context, StepUpAction $action): bool
    {
        try {
            $this->require($context, $action);

            return true;
        } catch (\DomainException) {
            return false;
        }
    }

    public function require(
        AuthenticatedAccountContext $context,
        StepUpAction $action,
        bool $forUpdate = false,
    ): \Qmdb\Modules\IdentityMultiFactor\Domain\StepUpGrant {
        $now = $this->clock->now();
        $grant = $this->grants->findActiveGrant(
            $context->accountInternalId,
            $context->sessionInternalId,
            $action,
            $forUpdate,
        );
        if (
            $grant === null || !$grant->permits(
                $context->accountInternalId,
                $context->sessionInternalId,
                $action,
                $now,
            )
        ) {
            throw new \DomainException('A fresh action-bound identity verification is required.');
        }

        return $grant;
    }
}
