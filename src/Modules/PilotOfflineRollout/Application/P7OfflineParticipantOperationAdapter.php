<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Application;

use Qmdb\Modules\CompetitionLive\Application\CompetitionLiveParticipantWorkflowService;

final readonly class P7OfflineParticipantOperationAdapter
{
    private const array MAP = [
        'PARTICIPANT_CHECK_IN' => 'COMPETITION_LIVE_PARTICIPANT_CHECK_IN',
        'PARTICIPANT_ABSENT' => 'COMPETITION_LIVE_PARTICIPANT_ABSENT',
        'PARTICIPANT_CALLED' => 'COMPETITION_LIVE_PARTICIPANT_CALL',
        'PARTICIPANT_READY' => 'COMPETITION_LIVE_PARTICIPANT_READY',
        'PERFORMANCE_STARTED' => 'COMPETITION_LIVE_PARTICIPANT_START',
        'PERFORMANCE_INTERRUPTED' => 'COMPETITION_LIVE_PARTICIPANT_INTERRUPT',
        'PERFORMANCE_RESUMED' => 'COMPETITION_LIVE_PARTICIPANT_RESUME',
        'PERFORMANCE_COMPLETED' => 'COMPETITION_LIVE_PARTICIPANT_COMPLETE',
    ];

    public function __construct(private CompetitionLiveParticipantWorkflowService $service)
    {
    }

    public function apply(OfflineOperationContext $context): OfflineOperationResult
    {
        $operation = self::MAP[$context->operation] ?? throw new \InvalidArgumentException('P7 offline operation is invalid.');
        $context->assertExactPayload(['session_id']);
        $result = $this->service->operate(
            $context->actor,
            $context->tenant,
            $context->changeId,
            $operation,
            $context->requiredUuid('session_id'),
            $context->entityId,
            $context->expectedVersion,
            $context->correlationId,
        );

        return OfflineOperationResult::accepted($context->entityId, $result['version'], $result['state']);
    }
}
