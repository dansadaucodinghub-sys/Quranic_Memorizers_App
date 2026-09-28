<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Application;

use Qmdb\Modules\CompetitionScoring\Application\CompetitionScoreSheetService;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class P6OfflineScoreDraftAdapter
{
    public function __construct(private CompetitionScoreSheetService $service)
    {
    }

    public function apply(OfflineOperationContext $context): OfflineOperationResult
    {
        $keys = array_keys($context->payload);
        sort($keys);
        if ($keys !== ['assignment_id', 'scores'] && $keys !== ['assignment_id', 'new_draft', 'scores']) {
            throw new \InvalidArgumentException('P6 offline score-draft payload schema is invalid.');
        }
        $newDraft = $context->payload['new_draft'] ?? false;
        if (!is_bool($newDraft)) {
            throw new \InvalidArgumentException('P6 offline score-draft creation flag is invalid.');
        }
        $version = $newDraft ? null : $context->expectedVersion;
        $result = $this->service->saveDraft(
            $context->actor,
            $context->tenant,
            $context->requiredUuid('assignment_id'),
            $context->entityId,
            $version,
            $context->scoreUnits(),
        );

        return OfflineOperationResult::accepted(UuidV7::fromString($result['public_id']), $result['version'], $result['status']);
    }
}
