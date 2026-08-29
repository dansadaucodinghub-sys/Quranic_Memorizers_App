<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

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
        $this->consumeWithGrant($context, $action);
    }

    /**
     * Consumes and returns the exact action-bound grant used by an auditable operation.
     *
     * Callers must invoke this inside their surrounding database transaction so a failed
     * business operation cannot leave an otherwise-valid step-up grant consumed.
     */
    public function consumeWithGrant(AuthenticatedAccountContext $context, StepUpAction $action): \Qmdb\Modules\IdentityMultiFactor\Domain\StepUpGrant
    {
        $grant = $this->require($context, $action, true);
        if (!$this->grants->consumeGrant($grant, $this->clock->now())) {
            throw new \DomainException('A fresh action-bound identity verification is required.');
        }

        return $grant;
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
