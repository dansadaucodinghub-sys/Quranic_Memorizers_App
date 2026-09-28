<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Application;

use Qmdb\Modules\PilotOfflineRollout\Domain\OfflineOperationPolicy;

/** Closed authoritative dispatcher: no default handler and no missing-handler conflict. */
final readonly class OfflineOperationDispatcher
{
    /** @var array<string,OfflineOperationHandler> */
    private array $handlers;

    /** @param iterable<OfflineOperationHandler> $handlers */
    public function __construct(iterable $handlers)
    {
        $mapped = [];
        foreach ($handlers as $handler) {
            if (isset($mapped[$handler->operation()])) {
                throw new \LogicException('Duplicate offline operation handler: ' . $handler->operation());
            }
            $mapped[$handler->operation()] = $handler;
        }
        $expected = OfflineOperationPolicy::ALLOWED;
        sort($expected);
        $actual = array_keys($mapped);
        sort($actual);
        if ($actual !== $expected) {
            throw new \LogicException('Offline operation handler map must exactly match the allowlist.');
        }
        $this->handlers = $mapped;
    }

    public function dispatch(OfflineOperationContext $context): OfflineOperationResult
    {
        $handler = $this->handlers[$context->operation] ?? null;
        if ($handler === null) {
            throw new \InvalidArgumentException('Offline operation is not allowlisted.');
        }

        try {
            return $handler->apply($context);
        } catch (\InvalidArgumentException) {
            return OfflineOperationResult::rejected($context->entityId, 'PAYLOAD_INVALID');
        } catch (\DomainException $error) {
            return OfflineOperationResult::conflict($context->entityId, $this->conflictReason($error), $context->expectedVersion);
        }
    }

    /** @return array<string,string> */
    public function registrations(): array
    {
        return array_map(static fn (OfflineOperationHandler $handler): string => $handler->owningDomain(), $this->handlers);
    }

    private function conflictReason(\DomainException $error): string
    {
        $message = strtolower($error->getMessage());
        return match (true) {
            str_contains($message, 'stale'), str_contains($message, 'version') => 'VERSION_MISMATCH',
            str_contains($message, 'transition'), str_contains($message, 'open session'), str_contains($message, 'lifecycle') => 'INVALID_TRANSITION',
            str_contains($message, 'assignment'), str_contains($message, 'permission'), str_contains($message, 'authorization') => 'PERMISSION_REVOKED',
            str_contains($message, 'supersed') => 'ENTITY_SUPERSEDED',
            str_contains($message, 'unavailable'), str_contains($message, 'removed') => 'ENTITY_REMOVED',
            default => 'SOURCE_CHANGED',
        };
    }
}
