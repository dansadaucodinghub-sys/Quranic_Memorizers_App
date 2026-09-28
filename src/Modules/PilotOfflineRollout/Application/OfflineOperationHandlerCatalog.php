<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Application;

use Qmdb\Modules\CompetitionLive\Application\CompetitionLiveParticipantWorkflowService;
use Qmdb\Modules\CompetitionResults\Application\CompetitionP6WorkflowService;
use Qmdb\Modules\CompetitionScoring\Application\CompetitionScoreSheetService;

/** Machine-readable ownership and authority contract for the closed P13 offline dispatcher. */
final readonly class OfflineOperationHandlerCatalog
{
    /**
     * @return array<string,array{
     *     owner:string,handler:string,service:string,entity_type:string,package_scope:string,
     *     base_version:bool,idempotency:string,receipt:string,conflicts:list<string>,audit:bool,notification:bool
     * }>
     */
    public function entries(): array
    {
        $p7 = [];
        foreach (
            [
                'PARTICIPANT_CHECK_IN', 'PARTICIPANT_ABSENT', 'PARTICIPANT_CALLED', 'PARTICIPANT_READY',
                'PERFORMANCE_STARTED', 'PERFORMANCE_INTERRUPTED', 'PERFORMANCE_RESUMED',
                'PERFORMANCE_COMPLETED',
            ] as $operation
        ) {
            $p7[$operation] = $this->entry(
                'P7_LIVE',
                P7OfflineParticipantOperationAdapter::class,
                CompetitionLiveParticipantWorkflowService::class,
                'LIVE_PARTICIPANT',
                'VENUE_EDITION',
                true,
                true,
            );
        }

        return $p7 + [
            'SCORE_DRAFT_SAVED' => $this->entry(
                'P6_SCORING',
                P6OfflineScoreDraftAdapter::class,
                CompetitionScoreSheetService::class,
                'SCORE_SHEET',
                'JUDGE_ASSIGNMENT',
                true,
                false,
            ),
            'SCORE_SHEET_SUBMITTED' => $this->entry(
                'P6_SCORING',
                P6OfflineScoreSheetSubmissionAdapter::class,
                CompetitionP6WorkflowService::class,
                'SCORE_SHEET',
                'JUDGE_ASSIGNMENT',
                true,
                true,
            ),
            'VENUE_INCIDENT_RECORDED' => $this->entry(
                'P13_OFFLINE',
                P13NativeOfflineOperationAdapter::class,
                P13NativeOfflineOperationAdapter::class,
                'VENUE_INCIDENT',
                'VENUE',
                false,
                true,
            ),
            'OPERATIONAL_NOTE_RECORDED' => $this->entry(
                'P13_OFFLINE',
                P13NativeOfflineOperationAdapter::class,
                P13NativeOfflineOperationAdapter::class,
                'OPERATIONAL_NOTE',
                'VENUE',
                false,
                false,
            ),
            'JUDGE_ACKNOWLEDGEMENT_RECORDED' => $this->entry(
                'P13_OFFLINE',
                P13NativeOfflineOperationAdapter::class,
                P13NativeOfflineOperationAdapter::class,
                'JUDGE_ACKNOWLEDGEMENT',
                'JUDGE_ASSIGNMENT',
                false,
                false,
            ),
        ];
    }

    /**
     * @return array{
     *     owner:string,handler:string,service:string,entity_type:string,package_scope:string,
     *     base_version:bool,idempotency:string,receipt:string,conflicts:list<string>,audit:bool,notification:bool
     * }
     */
    private function entry(
        string $owner,
        string $handler,
        string $service,
        string $entityType,
        string $packageScope,
        bool $baseVersion,
        bool $notification,
    ): array {
        return [
            'owner' => $owner,
            'handler' => $handler,
            'service' => $service,
            'entity_type' => $entityType,
            'package_scope' => $packageScope,
            'base_version' => $baseVersion,
            'idempotency' => 'CLIENT_CHANGE_UUID_AND_FINGERPRINT',
            'receipt' => 'IMMUTABLE_SYNC_RECEIPT',
            'conflicts' => [
                'VERSION_MISMATCH', 'INVALID_TRANSITION', 'PERMISSION_REVOKED', 'ENTITY_SUPERSEDED',
                'ENTITY_REMOVED', 'SOURCE_CHANGED', 'PACKAGE_STALE',
            ],
            'audit' => true,
            'notification' => $notification,
        ];
    }
}
