<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Application;

use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\PilotOfflineRollout\Infrastructure\Persistence\MySqlPilotOfflineRolloutRepository;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;

/** Complete route-to-transaction application service for all 35 P13 browser mutations. */
final readonly class P13AdministrativeMutationService
{
    public const array SUPPORTED_ROUTES = P13RouteRuntimeCatalog::BROWSER_MUTATIONS;
    public const string PRODUCTION_REPOSITORY = MySqlPilotOfflineRolloutRepository::class;

    public function __construct(
        private MySqlPilotOfflineRolloutRepository $repository,
        private P13MutationRequestParser $input,
        private SecurityAuditEventAppender $audit,
        private Clock $clock,
    ) {
    }

    /**
     * @param array<array-key,mixed> $body
     * @param array<string,string> $parameters
     */
    public function execute(string $route, array $body, AuthenticatedAccountContext $actorContext, ?AccountWorkspaceTenantContext $tenant, array $parameters): ?UuidV7
    {
        $actor = $actorContext->accountInternalId;
        $workspaceId = $tenant?->workspaceInternalId;
        $resource = match ($route) {
            'platform.pilots.create' => $this->repository->createPilot($this->input->text($body, 'code', 80), $this->input->text($body, 'name', 191), $this->input->text($body, 'scope', 24), $this->input->text($body, 'starts_at', 32), $this->input->text($body, 'ends_at', 32), $actor),
            'platform.pilots.sites' => $this->repository->assignPilotSite($this->input->routeUuid($parameters, 'pilotId'), $this->input->uuid($body, 'workspace_id'), $this->input->uuid($body, 'venue_id'), $this->input->uuid($body, 'edition_id'), $this->input->integer($body, 'owner_account_id'), $this->input->integer($body, 'expected_version'), $actor),
            'platform.pilots.readiness' => $this->repository->evaluatePilotReadiness($this->input->routeUuid($parameters, 'pilotId'), $this->input->uuid($body, 'site_id'), $this->input->integer($body, 'expected_version'), $this->input->text($body, 'check_code', 96), $this->input->boolean($body, 'blocking'), $this->input->text($body, 'result', 16), $this->input->text($body, 'evidence_reference', 500), $this->input->text($body, 'expires_at', 32), $actor),
            'platform.pilots.incidents.create' => $this->repository->createPilotIncident($this->input->routeUuid($parameters, 'pilotId'), $this->input->uuid($body, 'site_id'), $this->input->integer($body, 'expected_version'), $this->input->text($body, 'classification', 32), $this->input->text($body, 'severity', 16), $this->input->text($body, 'statement', 4000), $actor),
            'platform.pilots.approve', 'platform.pilots.start', 'platform.pilots.pause', 'platform.pilots.resume', 'platform.pilots.complete', 'platform.pilots.cancel' => $this->pilotTransition($route, $body, $actor, $parameters),
            'platform.rollouts.create' => $this->repository->createRollout($this->input->text($body, 'code', 80), $this->input->text($body, 'name', 191), $this->input->text($body, 'scope', 24), $this->input->integer($body, 'maximum_wave_size'), $actor),
            'platform.rollouts.waves.create' => $this->repository->createWave($this->input->routeUuid($parameters, 'rolloutId'), $this->input->text($body, 'name', 191), $this->input->text($body, 'starts_at', 32), $this->input->text($body, 'ends_at', 32), $actor),
            'platform.rollout_waves.assignments' => $this->repository->assignRolloutWave($this->input->routeUuid($parameters, 'waveId'), $this->input->integer($body, 'expected_version'), $this->input->optionalUuid($body, 'workspace_id'), $this->input->optionalUuid($body, 'venue_id'), $this->input->optionalUuid($body, 'geography_id'), $this->input->optionalText($body, 'cohort_code', 80), $actor),
            'platform.rollout_waves.readiness' => $this->run(fn () => $this->repository->evaluateRolloutWave($this->input->routeUuid($parameters, 'waveId'), $this->input->integer($body, 'expected_version'), $this->input->text($body, 'readiness_sha256', 64), $actor)),
            'platform.rollout_waves.decide' => $this->repository->decideRolloutWave($this->input->routeUuid($parameters, 'waveId'), $this->input->integer($body, 'expected_version'), $this->input->text($body, 'decision', 16), $this->input->text($body, 'readiness_sha256', 64), $this->input->text($body, 'health_sha256', 64), $this->input->text($body, 'reason_code', 64), $actor),
            'platform.rollout_waves.activate', 'platform.rollout_waves.pause', 'platform.rollout_waves.resume', 'platform.rollout_waves.contain', 'platform.rollout_waves.cancel', 'platform.rollout_waves.complete' => $this->waveTransition($route, $body, $actor, $parameters),
            'workspace.offline_devices.create' => $this->repository->registerDevice($this->workspace($workspaceId), $this->input->uuid($body, 'venue_id'), $this->input->text($body, 'code', 80), $this->input->text($body, 'name', 191), $this->input->text($body, 'device_type', 24), $this->input->text($body, 'platform', 32), $this->publicKey($body), $actor),
            'workspace.offline_devices.activate', 'workspace.offline_devices.suspend', 'workspace.offline_devices.revoke' => $this->deviceTransition($route, $body, $actor, $this->workspace($workspaceId), $parameters),
            'workspace.offline_devices.rotate_key' => $this->repository->rotateDeviceKey($this->workspace($workspaceId), $this->input->routeUuid($parameters, 'deviceId'), $this->input->integer($body, 'expected_version'), $this->input->text($body, 'key_code', 96), $this->publicKey($body), $this->input->text($body, 'valid_until', 32), $actor),
            'workspace.offline_packages.create' => $this->repository->preparePackage($this->workspace($workspaceId), $this->input->uuid($body, 'device_id'), $this->input->uuid($body, 'edition_id'), $this->input->packageEntities($body), $actor),
            'workspace.offline_packages.revoke' => $this->run(fn () => $this->repository->revokePackage($this->workspace($workspaceId), $this->input->routeUuid($parameters, 'packageId'), $this->input->integer($body, 'expected_version'), $actor)),
            'workspace.offline_packages.supersede' => $this->run(fn () => $this->repository->supersedePackage($this->workspace($workspaceId), $this->input->routeUuid($parameters, 'packageId'), $this->input->uuid($body, 'replacement_package_id'), $this->input->integer($body, 'expected_version'), $actor)),
            'workspace.offline_conflicts.assign' => $this->repository->assignConflict($this->workspace($workspaceId), $this->input->routeUuid($parameters, 'conflictId'), $this->input->integer($body, 'expected_version'), $this->input->integer($body, 'reviewer_account_id'), $actor),
            'workspace.offline_conflicts.start' => $this->run(fn () => $this->repository->startConflictReview($this->workspace($workspaceId), $this->input->routeUuid($parameters, 'conflictId'), $this->input->integer($body, 'expected_version'), $actor)),
            'workspace.offline_conflicts.accept_server', 'workspace.offline_conflicts.accept_client', 'workspace.offline_conflicts.resolve_manually', 'workspace.offline_conflicts.dismiss' => $this->conflictDecision($route, $body, $actor, $this->workspace($workspaceId), $parameters),
            default => throw new \LogicException('P13 mutation route is missing its application handler: ' . $route),
        };
        $subject = $resource ?? $this->subjectFromParameters($parameters);
        $metadata = ['route' => $route, 'scope' => $tenant === null ? 'PLATFORM' : 'WORKSPACE'];
        if ($tenant === null) {
            $this->audit->platform(SecurityEventCode::P13_ADMINISTRATIVE_MUTATION_APPLIED, SecurityEventSubjectKind::PILOT_OFFLINE_OPERATION, $subject->toString(), $actorContext->accountId->toString(), $this->clock->now(), $metadata, null, $subject->toString());
        } else {
            $this->audit->workspace(SecurityEventCode::P13_ADMINISTRATIVE_MUTATION_APPLIED, $tenant->workspacePublicId(), SecurityEventSubjectKind::PILOT_OFFLINE_OPERATION, $subject->toString(), $actorContext->accountId->toString(), $this->clock->now(), $metadata, null, $subject->toString());
            $this->repository->notificationIntent(
                $tenant->workspaceInternalId,
                $actor,
                $this->notificationType($route),
                $subject,
                $route,
            );
        }

        return $resource;
    }

    /**
     * @param array<array-key,mixed> $body
     * @param array<string,string> $parameters
     */
    private function pilotTransition(string $route, array $body, int $actor, array $parameters): null
    {
        $targets = ['approve' => 'APPROVED', 'start' => 'ACTIVE', 'pause' => 'PAUSED', 'resume' => 'ACTIVE', 'complete' => 'COMPLETED', 'cancel' => 'CANCELLED'];
        $action = substr($route, strrpos($route, '.') + 1);
        $this->repository->transitionPilot($this->input->routeUuid($parameters, 'pilotId'), $targets[$action], $this->input->integer($body, 'expected_version'), $actor, $this->input->text($body, 'reason_code', 64, 'GOVERNED_ACTION'));

        return null;
    }

    /**
     * @param array<array-key,mixed> $body
     * @param array<string,string> $parameters
     */
    private function waveTransition(string $route, array $body, int $actor, array $parameters): null
    {
        $targets = ['activate' => 'ACTIVE', 'pause' => 'PAUSED', 'resume' => 'ACTIVE', 'contain' => 'CONTAINED', 'cancel' => 'CANCELLED', 'complete' => 'COMPLETED'];
        $action = substr($route, strrpos($route, '.') + 1);
        $this->repository->transitionRolloutWave($this->input->routeUuid($parameters, 'waveId'), $this->input->integer($body, 'expected_version'), $targets[$action], $this->input->text($body, 'reason_code', 64, 'GOVERNED_ACTION'), $actor);

        return null;
    }

    /**
     * @param array<array-key,mixed> $body
     * @param array<string,string> $parameters
     */
    private function deviceTransition(string $route, array $body, int $actor, int $workspaceId, array $parameters): null
    {
        $targets = ['activate' => 'ACTIVE', 'suspend' => 'SUSPENDED', 'revoke' => 'REVOKED'];
        $action = substr($route, strrpos($route, '.') + 1);
        $this->repository->transitionDevice($workspaceId, $this->input->routeUuid($parameters, 'deviceId'), $targets[$action], $this->input->integer($body, 'expected_version'), $actor);

        return null;
    }

    /**
     * @param array<array-key,mixed> $body
     * @param array<string,string> $parameters
     */
    private function conflictDecision(string $route, array $body, int $actor, int $workspaceId, array $parameters): null
    {
        $decisions = ['accept_server' => 'ACCEPT_SERVER', 'accept_client' => 'ACCEPT_CLIENT_PROPOSAL', 'resolve_manually' => 'MANUAL', 'dismiss' => 'DISMISS'];
        $action = substr($route, strrpos($route, '.') + 1);
        $this->repository->decideConflict($workspaceId, $this->input->routeUuid($parameters, 'conflictId'), $decisions[$action], $this->input->integer($body, 'expected_version'), $this->input->text($body, 'reason_code', 64), $actor);

        return null;
    }

    /** @param array<array-key,mixed> $body */
    private function publicKey(array $body): string
    {
        $decoded = base64_decode($this->input->text($body, 'public_key', 128), true);
        if (!is_string($decoded) || strlen($decoded) !== 32) {
            throw new \InvalidArgumentException('Device public key is invalid.');
        }

        return $decoded;
    }

    private function workspace(?int $workspaceId): int
    {
        return $workspaceId ?? throw new \LogicException('Workspace context is required.');
    }

    private function notificationType(string $route): string
    {
        return match (true) {
            str_contains($route, 'offline_devices') => 'P13_DEVICE_STATUS',
            str_contains($route, 'offline_packages') => 'P13_PACKAGE_STATUS',
            str_contains($route, 'offline_conflicts') => 'P13_CONFLICT_STATUS',
            default => 'P13_SYNC_STATUS',
        };
    }

    /** @param array<string,string> $parameters */
    private function subjectFromParameters(array $parameters): UuidV7
    {
        foreach (['pilotId', 'rolloutId', 'waveId', 'deviceId', 'packageId', 'conflictId'] as $name) {
            if (isset($parameters[$name])) {
                return UuidV7::fromString($parameters[$name]);
            }
        }

        throw new \LogicException('P13 mutation did not produce an auditable public subject.');
    }

    /** @param callable():void $operation */
    private function run(callable $operation): null
    {
        $operation();

        return null;
    }
}
