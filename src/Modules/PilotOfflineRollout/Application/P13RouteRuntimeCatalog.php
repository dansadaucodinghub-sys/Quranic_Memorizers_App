<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Application;

use Qmdb\Shared\Http\Routing\Security\ProductionRouteSecurityPolicyCatalog;
use Qmdb\Modules\PilotOfflineRollout\Infrastructure\Persistence\MySqlPilotOfflineRolloutRepository;

/** Executable completeness catalog for the frozen 62-route P13 surface. */
final readonly class P13RouteRuntimeCatalog
{
    public const array ROUTES = [
        'platform.pilots.index', 'platform.pilots.create_form', 'platform.pilots.create', 'platform.pilots.detail',
        'platform.pilots.sites', 'platform.pilots.readiness', 'platform.pilots.approve', 'platform.pilots.start',
        'platform.pilots.pause', 'platform.pilots.resume', 'platform.pilots.complete', 'platform.pilots.cancel',
        'platform.pilots.incidents.index', 'platform.pilots.incidents.create', 'platform.rollouts.index',
        'platform.rollouts.create_form', 'platform.rollouts.create', 'platform.rollouts.detail',
        'platform.rollouts.waves.create', 'platform.rollout_waves.detail', 'platform.rollout_waves.assignments',
        'platform.rollout_waves.readiness', 'platform.rollout_waves.decide', 'platform.rollout_waves.activate',
        'platform.rollout_waves.pause', 'platform.rollout_waves.resume', 'platform.rollout_waves.contain',
        'platform.rollout_waves.cancel', 'platform.rollout_waves.complete', 'platform.rollout_waves.health',
        'workspace.offline_devices.index', 'workspace.offline_devices.register', 'workspace.offline_devices.create',
        'workspace.offline_devices.detail', 'workspace.offline_devices.activate', 'workspace.offline_devices.suspend',
        'workspace.offline_devices.revoke', 'workspace.offline_devices.rotate_key', 'workspace.offline_packages.index',
        'workspace.offline_packages.create', 'workspace.offline_packages.detail', 'workspace.offline_packages.download',
        'workspace.offline_packages.revoke', 'workspace.offline_packages.supersede', 'workspace.offline_sync.index',
        'workspace.offline_sync.detail', 'workspace.offline_conflicts.index', 'workspace.offline_conflicts.detail',
        'workspace.offline_conflicts.assign', 'workspace.offline_conflicts.start', 'workspace.offline_conflicts.accept_server',
        'workspace.offline_conflicts.accept_client', 'workspace.offline_conflicts.resolve_manually',
        'workspace.offline_conflicts.dismiss', 'offline.v1.devices.authenticate', 'offline.v1.packages.current',
        'offline.v1.packages.download', 'offline.v1.packages.activate', 'offline.v1.sync.open',
        'offline.v1.sync.change', 'offline.v1.sync.complete', 'offline.v1.sync.receipts',
    ];

    public const array MUTATIONS = [
        'platform.pilots.create', 'platform.pilots.sites', 'platform.pilots.readiness', 'platform.pilots.approve',
        'platform.pilots.start', 'platform.pilots.pause', 'platform.pilots.resume', 'platform.pilots.complete',
        'platform.pilots.cancel', 'platform.pilots.incidents.create', 'platform.rollouts.create',
        'platform.rollouts.waves.create', 'platform.rollout_waves.assignments', 'platform.rollout_waves.readiness',
        'platform.rollout_waves.decide', 'platform.rollout_waves.activate', 'platform.rollout_waves.pause',
        'platform.rollout_waves.resume', 'platform.rollout_waves.contain', 'platform.rollout_waves.cancel',
        'platform.rollout_waves.complete', 'workspace.offline_devices.create', 'workspace.offline_devices.activate',
        'workspace.offline_devices.suspend', 'workspace.offline_devices.revoke', 'workspace.offline_devices.rotate_key',
        'workspace.offline_packages.create', 'workspace.offline_packages.revoke', 'workspace.offline_packages.supersede',
        'workspace.offline_conflicts.assign', 'workspace.offline_conflicts.start', 'workspace.offline_conflicts.accept_server',
        'workspace.offline_conflicts.accept_client', 'workspace.offline_conflicts.resolve_manually',
        'workspace.offline_conflicts.dismiss', 'offline.v1.devices.authenticate', 'offline.v1.packages.activate',
        'offline.v1.sync.open', 'offline.v1.sync.change', 'offline.v1.sync.complete',
    ];

    public const array BROWSER_MUTATIONS = [
        'platform.pilots.create', 'platform.pilots.sites', 'platform.pilots.readiness', 'platform.pilots.approve',
        'platform.pilots.start', 'platform.pilots.pause', 'platform.pilots.resume', 'platform.pilots.complete',
        'platform.pilots.cancel', 'platform.pilots.incidents.create', 'platform.rollouts.create',
        'platform.rollouts.waves.create', 'platform.rollout_waves.assignments', 'platform.rollout_waves.readiness',
        'platform.rollout_waves.decide', 'platform.rollout_waves.activate', 'platform.rollout_waves.pause',
        'platform.rollout_waves.resume', 'platform.rollout_waves.contain', 'platform.rollout_waves.cancel',
        'platform.rollout_waves.complete', 'workspace.offline_devices.create', 'workspace.offline_devices.activate',
        'workspace.offline_devices.suspend', 'workspace.offline_devices.revoke', 'workspace.offline_devices.rotate_key',
        'workspace.offline_packages.create', 'workspace.offline_packages.revoke', 'workspace.offline_packages.supersede',
        'workspace.offline_conflicts.assign', 'workspace.offline_conflicts.start', 'workspace.offline_conflicts.accept_server',
        'workspace.offline_conflicts.accept_client', 'workspace.offline_conflicts.resolve_manually',
        'workspace.offline_conflicts.dismiss',
    ];

    /** @return array<string,array<string,bool|string|null>> */
    public function entries(): array
    {
        $runtime = $this->routeDefinitions();
        $policies = (new ProductionRouteSecurityPolicyCatalog())->policies();
        $entries = [];
        foreach (self::ROUTES as $route) {
            $mutation = in_array($route, self::MUTATIONS, true);
            $protocol = str_starts_with($route, 'offline.v1.');
            $registered = $runtime[$route] ?? null;
            $policy = $policies[$route] ?? throw new \RuntimeException('P13 route security policy is missing: ' . $route);
            $entries[$route] = [
                'method' => $registered['method'] ?? ($mutation ? 'POST' : 'GET'),
                'path' => $registered['path'] ?? 'RUNTIME_ROUTE_REGISTRY_REQUIRED',
                'controller' => $protocol ? 'Qmdb\\Modules\\PilotOfflineRollout\\Interface\\Http\\OfflineProtocolController' : 'Qmdb\\Modules\\PilotOfflineRollout\\Interface\\Http\\P13PortalController',
                'controller_method' => 'handle',
                'application_service' => $protocol ? ($route === 'offline.v1.sync.change' ? OfflineOperationDispatcher::class : MySqlPilotOfflineRolloutRepository::class) : ($mutation ? P13AdministrativeMutationService::class : MySqlPilotOfflineRolloutRepository::class),
                'authority' => $protocol ? 'DEVICE_SIGNATURE' : $policy->classification->value,
                'permission' => $policy->permissionCode,
                'assurance' => $protocol ? 'DEVICE_SIGNATURE' : $policy->requiredAssurance,
                'step_up_action' => $policy->stepUpAction,
                'csrf_action' => $policy->csrfAction,
                'csrf' => $policy->csrfAction !== null,
                'idempotency' => $policy->requiresIdempotency,
                'expected_version' => $mutation && !str_ends_with($route, '.create') && !in_array($route, ['offline.v1.devices.authenticate', 'offline.v1.packages.activate', 'offline.v1.sync.open', 'offline.v1.sync.change', 'offline.v1.sync.complete'], true),
                'rate_limit_scope' => 'p13.pilot_offline_rollout',
                'cache_policy' => 'PRIVATE_NO_STORE',
                'full_page_fallback' => $protocol ? null : $this->fallbackRoute($route),
                'fragment_support' => !$protocol,
                'runtime_capability' => strtoupper(str_replace(['.', '-'], '_', $route)),
            ];
        }

        return $entries;
    }

    private function fallbackRoute(string $route): string
    {
        return match (true) {
            str_starts_with($route, 'platform.pilots') => 'platform.pilots.index',
            str_starts_with($route, 'platform.rollout') => 'platform.rollouts.index',
            str_starts_with($route, 'workspace.offline_devices') => 'workspace.offline_devices.index',
            str_starts_with($route, 'workspace.offline_packages') => 'workspace.offline_packages.index',
            str_starts_with($route, 'workspace.offline_sync') => 'workspace.offline_sync.index',
            str_starts_with($route, 'workspace.offline_conflicts') => 'workspace.offline_conflicts.index',
            default => throw new \LogicException('P13 browser route has no full-page fallback: ' . $route),
        };
    }

    /** @return array<string,array{method:string,path:string}> */
    private function routeDefinitions(): array
    {
        $source = file_get_contents(dirname(__DIR__, 4) . '/routes/web.php');
        if (!is_string($source)) {
            throw new \RuntimeException('The production route registry is unavailable.');
        }
        preg_match_all(
            "/new Route\\(\\s*'(?<name>(?:platform\\.(?:pilots|rollouts|rollout_waves)|workspace\\.offline_|offline\\.v1\\.)[^']+)'\\s*,\\s*\\[HttpMethod::(?<method>[A-Z]+)\\]\\s*,\\s*new RoutePattern\\('(?<path>[^']+)'\\)/",
            $source,
            $matches,
            PREG_SET_ORDER,
        );
        $definitions = [];
        foreach ($matches as $match) {
            $definitions[$match['name']] = ['method' => $match['method'], 'path' => $match['path']];
        }

        return $definitions;
    }
}
