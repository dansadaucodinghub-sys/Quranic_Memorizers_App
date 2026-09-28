<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Application;

/** One immutable registration; thirteen instances form the closed production map. */
final readonly class MappedOfflineOperationHandler implements OfflineOperationHandler
{
    /** @param \Closure(OfflineOperationContext): OfflineOperationResult $handler */
    public function __construct(
        private string $code,
        private string $domain,
        private \Closure $handler,
    ) {
        if ($code === '' || !in_array($domain, ['P6_SCORING', 'P7_LIVE', 'P13_OFFLINE'], true)) {
            throw new \InvalidArgumentException('Offline operation registration is invalid.');
        }
    }

    public function operation(): string
    {
        return $this->code;
    }

    public function owningDomain(): string
    {
        return $this->domain;
    }

    public function apply(OfflineOperationContext $context): OfflineOperationResult
    {
        return ($this->handler)($context);
    }
}
