<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Application;

use Qmdb\Modules\PilotOfflineRollout\Domain\OfflineOperationPolicy;

/** Pure runtime-catalog verifier used by CLI and architecture regression tests. */
final readonly class P13RuntimeCatalogVerifier
{
    /** @param array<string,array<string,bool|string|null>> $entries */
    public function verifyRoutes(array $entries): void
    {
        if (array_keys($entries) !== P13RouteRuntimeCatalog::ROUTES) {
            throw new \RuntimeException('P13 route runtime catalog differs from the production route surface.');
        }
        foreach ($entries as $name => $entry) {
            foreach (['method', 'path', 'controller', 'controller_method', 'application_service', 'authority', 'rate_limit_scope', 'cache_policy', 'runtime_capability'] as $field) {
                if (!isset($entry[$field]) || !is_string($entry[$field]) || $entry[$field] === '' || $entry[$field] === 'RUNTIME_ROUTE_REGISTRY_REQUIRED') {
                    throw new \RuntimeException('P13 route runtime metadata is incomplete: ' . $name . ':' . $field);
                }
            }
            $controller = (string) $entry['controller'];
            $method = (string) $entry['controller_method'];
            if (!class_exists($controller) || !method_exists($controller, $method) || !(new \ReflectionMethod($controller, $method))->isPublic()) {
                throw new \RuntimeException('P13 route controller is not callable: ' . $name);
            }
            if (!class_exists((string) $entry['application_service'])) {
                throw new \RuntimeException('P13 route application service is unavailable: ' . $name);
            }
            if ($entry['cache_policy'] !== 'PRIVATE_NO_STORE') {
                throw new \RuntimeException('P13 route cache policy is unsafe: ' . $name);
            }
            $protocol = str_starts_with($name, 'offline.v1.');
            $mutation = in_array($name, P13RouteRuntimeCatalog::MUTATIONS, true);
            if ($mutation && $entry['method'] !== 'POST') {
                throw new \RuntimeException('P13 mutation must use POST: ' . $name);
            }
            if (!$protocol && ($entry['permission'] === null || $entry['assurance'] === null || $entry['full_page_fallback'] === null || $entry['fragment_support'] !== true)) {
                throw new \RuntimeException('P13 browser route authority or fallback is incomplete: ' . $name);
            }
            if ($mutation && !$protocol && ($entry['csrf_action'] === null || $entry['step_up_action'] === null || $entry['idempotency'] !== true)) {
                throw new \RuntimeException('P13 browser mutation security control is missing: ' . $name);
            }
        }
    }

    /**
     * @param array<string,array{owner:string,handler:string,service:string,entity_type:string,package_scope:string,base_version:bool,idempotency:string,receipt:string,conflicts:list<string>,audit:bool,notification:bool}> $catalog
     * @param array<string,string> $registrations
     */
    public function verifyHandlers(array $catalog, array $registrations): void
    {
        if (array_keys($catalog) !== OfflineOperationPolicy::ALLOWED || array_keys($registrations) !== OfflineOperationPolicy::ALLOWED) {
            throw new \RuntimeException('P13 offline handler map differs from the closed allowlist.');
        }
        $counts = array_count_values($registrations);
        if (($counts['P7_LIVE'] ?? 0) !== 8 || ($counts['P6_SCORING'] ?? 0) !== 2 || ($counts['P13_OFFLINE'] ?? 0) !== 3) {
            throw new \RuntimeException('P13 offline handler ownership counts are invalid.');
        }
        foreach ($catalog as $operation => $entry) {
            if (
                $entry['owner'] !== $registrations[$operation]
                || !class_exists($entry['handler'])
                || !class_exists($entry['service'])
                || $entry['entity_type'] === ''
                || $entry['package_scope'] === ''
                || $entry['idempotency'] !== 'CLIENT_CHANGE_UUID_AND_FINGERPRINT'
                || $entry['receipt'] !== 'IMMUTABLE_SYNC_RECEIPT'
                || $entry['audit'] !== true
                || $entry['conflicts'] === []
            ) {
                throw new \RuntimeException('P13 offline handler metadata is incomplete: ' . $operation);
            }
        }
    }
}
