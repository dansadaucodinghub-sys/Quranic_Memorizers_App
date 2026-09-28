<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Application;

use Qmdb\Modules\CompetitionResults\Application\CompetitionP6WorkflowService;

final readonly class P6OfflineScoreSheetSubmissionAdapter
{
    public function __construct(private CompetitionP6WorkflowService $service)
    {
    }

    public function apply(OfflineOperationContext $context): OfflineOperationResult
    {
        $context->assertExactPayload([]);
        $result = $this->service->transition(
            $context->actor,
            $context->tenant,
            $context->changeId,
            'COMPETITION_SCORE_SUBMIT',
            $context->entityId,
            $context->expectedVersion,
            $context->correlationId,
        );

        return OfflineOperationResult::accepted($context->entityId, $result['version'], $result['status']);
    }
}
