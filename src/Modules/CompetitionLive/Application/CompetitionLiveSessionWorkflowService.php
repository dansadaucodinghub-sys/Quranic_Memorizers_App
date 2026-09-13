<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Application;

use Qmdb\Modules\CompetitionLive\Domain\LiveSessionLifecycle;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;

/**
 * The application entry point for live-session lifecycle transitions.
 * Authorization, CSRF, and step-up are enforced by its HTTP/console callers;
 * this service owns only transactional integrity, idempotency, and event order.
 */
final readonly class CompetitionLiveSessionWorkflowService
{
    public function __construct(
        private CompetitionLiveRuntimeRepository $repository,
        private LiveSessionLifecycle $lifecycle,
        private IdentityFingerprintGenerator $fingerprints,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    /** @return array{status:string,version:int,sequence:int,replayed:bool} */
    public function transition(int $workspaceId, UuidV7 $submissionId, UuidV7 $sessionPublicId, int $expectedVersion, string $targetStatus, ?int $actorAccountId, UuidV7 $correlationId): array
    {
        if ($expectedVersion < 1) {
            throw new \InvalidArgumentException('Live session version is invalid.');
        }
        $eventType = self::eventType($targetStatus);

        return $this->transactions->transactional(
            fn (): array => $this->inTransaction($workspaceId, $submissionId, $sessionPublicId, $expectedVersion, $targetStatus, $eventType, $actorAccountId, $correlationId),
            TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)),
        );
    }

    /** @return array{status:string,version:int,sequence:int,replayed:bool} */
    private function inTransaction(int $workspaceId, UuidV7 $submissionId, UuidV7 $sessionPublicId, int $expectedVersion, string $targetStatus, string $eventType, ?int $actorAccountId, UuidV7 $correlationId): array
    {
        $fingerprint = $this->fingerprints->generate('competition-p7-live-session', implode("\0", [$workspaceId, $sessionPublicId->toString(), $expectedVersion, $targetStatus, $actorAccountId ?? 0]));
        $completed = $this->repository->completed($submissionId, $fingerprint->toBinary());
        if ($completed !== null) {
            return ['status' => $completed['status'], 'version' => $completed['version'], 'sequence' => $completed['sequence'], 'replayed' => true];
        }
        $session = $this->repository->lockSession($workspaceId, $sessionPublicId);
        if ($session === null || $session['version'] !== $expectedVersion) {
            throw new \DomainException('Live session is stale or unavailable.');
        }
        $this->lifecycle->assertTransition($session['status'], $targetStatus);
        $now = $this->clock->now();
        if (!$this->repository->transitionSession($session, $targetStatus, $now)) {
            throw new \DomainException('Live session changed concurrently.');
        }
        $sequence = $this->repository->appendEvent($session, $eventType, 'WORKSPACE_PRIVATE', ['session_public_id' => $session['public_id'], 'previous_status' => $session['status'], 'status' => $targetStatus], $actorAccountId, $correlationId, $now);
        $this->repository->record($submissionId, $fingerprint->toBinary(), 'COMPETITION_LIVE_SESSION_' . $targetStatus, $session, $targetStatus, $session['version'] + 1, $sequence, $now);

        return ['status' => $targetStatus, 'version' => $session['version'] + 1, 'sequence' => $sequence, 'replayed' => false];
    }

    private static function eventType(string $status): string
    {
        return match ($status) {
            'OPEN' => 'SESSION_OPENED',
            'PAUSED' => 'SESSION_PAUSED',
            'RECOVERING' => 'SESSION_RECOVERY_STARTED',
            'CLOSED' => 'SESSION_CLOSED',
            'CANCELLED' => 'SESSION_CANCELLED',
            default => throw new \InvalidArgumentException('Live session target status is invalid.'),
        };
    }
}
