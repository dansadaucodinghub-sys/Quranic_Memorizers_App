<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\PilotOfflineRollout\Domain\DeviceRequestSignature;
use Qmdb\Modules\PilotOfflineRollout\Infrastructure\Persistence\MySqlPilotOfflineRolloutRepository;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class OfflineProtocolController implements Controller
{
    public function __construct(
        private MySqlPilotOfflineRolloutRepository $repository,
        private DeviceRequestSignature $signatures,
        private Psr17Factory $responses,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $route = $request->getAttribute(RouteAttributes::NAME);
            if (!is_string($route)) {
                throw new \RuntimeException('Offline protocol route is unavailable.');
            }
            $deviceId = UuidV7::fromString($request->getHeaderLine('X-QMDB-Device-ID'));
            $packageId = UuidV7::fromString($request->getHeaderLine('X-QMDB-Package-ID'));
            $timestampText = $request->getHeaderLine('X-QMDB-Timestamp');
            if (preg_match('/\A[0-9]{10}\z/', $timestampText) !== 1) {
                throw new \DomainException('Device timestamp is invalid.');
            }
            $nonce = $request->getHeaderLine('X-QMDB-Nonce');
            $body = (string) $request->getBody();
            $bodyHash = hash('sha256', $body);
            $canonical = $this->signatures->canonical($request->getMethod(), $request->getUri()->getPath(), (int) $timestampText, $nonce, $bodyHash, $deviceId->toString(), $packageId->toString());
            $authority = $this->repository->deviceAuthority($deviceId, $packageId);
            $signature = base64_decode($request->getHeaderLine('X-QMDB-Signature'), true);
            if (!is_string($signature) || !$this->signatures->verify($canonical, $signature, $authority['public_key'])) {
                return $this->problem(401, 'DEVICE_SIGNATURE_INVALID', 'Device request authentication failed.');
            }
            if (!$this->repository->claimDeviceNonce($authority, $nonce, hash('sha256', $canonical), (int) $timestampText)) {
                return $this->problem(409, 'DEVICE_REPLAY_DETECTED', 'Duplicate device request rejected.');
            }
            $decoded = $body === '' ? [] : json_decode($body, true, 32, JSON_THROW_ON_ERROR);
            $payload = $this->stringKeyedArray($decoded, 'Device request body must be an object.');

            return match ($route) {
                'offline.v1.devices.authenticate' => $this->json(200, ['data' => ['authenticated' => true, 'device_status' => $authority['device_status'], 'package_status' => $authority['package_status'], 'package_expires_at' => $authority['package_expires']]]),
                'offline.v1.packages.current' => $this->json(200, ['data' => $this->repository->packageEnvelope($authority, false)]),
                'offline.v1.packages.download' => $this->json(200, ['data' => $this->repository->packageEnvelope($authority, true)]),
                'offline.v1.packages.activate' => $this->activate($authority),
                'offline.v1.sync.open' => $this->open($authority, $payload),
                'offline.v1.sync.change' => $this->change($authority, $this->routeId($request, 'sessionId'), $payload),
                'offline.v1.sync.complete' => $this->json(200, ['data' => $this->repository->completeSyncSession($authority, $this->routeId($request, 'sessionId'))]),
                'offline.v1.sync.receipts' => $this->json(200, ['data' => $this->repository->syncReceipts($authority, $this->routeId($request, 'sessionId'))]),
                default => throw new \RuntimeException('Offline protocol operation is unsupported.'),
            };
        } catch (\JsonException | \InvalidArgumentException) {
            return $this->problem(422, 'OFFLINE_REQUEST_INVALID', 'The offline request is invalid.');
        } catch (\OverflowException) {
            return $this->problem(413, 'OFFLINE_REQUEST_BOUNDED', 'The offline request exceeds a protocol limit.');
        } catch (\DomainException) {
            return $this->problem(409, 'OFFLINE_AUTHORITY_UNAVAILABLE', 'The offline authority or resource state is unavailable.');
        }
    }

    /** @param array{workspace_id:int,venue_id:int,device_id:int,package_id:int,public_key:string,device_status:string,package_status:string,package_expires:string} $authority */
    private function activate(array $authority): ResponseInterface
    {
        $this->repository->activatePackage($authority);

        return $this->json(200, ['data' => ['status' => 'ACTIVE']]);
    }

    /**
     * @param array{workspace_id:int,venue_id:int,device_id:int,package_id:int,public_key:string,device_status:string,package_status:string,package_expires:string} $authority
     * @param array<string,mixed> $payload
     */
    private function open(array $authority, array $payload): ResponseInterface
    {
        $client = $payload['client_session_id'] ?? null;
        if (!is_string($client)) {
            throw new \InvalidArgumentException('Client session identifier is required.');
        }
        $id = $this->repository->openSyncSession($authority, UuidV7::fromString($client));

        return $this->json(201, ['data' => ['session_id' => $id->toString(), 'maximum_changes' => 100, 'maximum_bytes' => 2_097_152]]);
    }

    /**
     * @param array{workspace_id:int,venue_id:int,device_id:int,package_id:int,public_key:string,device_status:string,package_status:string,package_expires:string} $authority
     * @param array<string,mixed> $payload
     */
    private function change(array $authority, UuidV7 $session, array $payload): ResponseInterface
    {
        $changes = $payload['changes'] ?? null;
        if (!is_array($changes) || count($changes) < 1 || count($changes) > 100) {
            throw new \InvalidArgumentException('A bounded change list is required.');
        }
        $results = [];
        foreach ($changes as $change) {
            $results[] = $this->repository->receiveSyncChange(
                $authority,
                $session,
                $this->stringKeyedArray($change, 'Offline change must be an object.'),
            );
        }

        return $this->json(207, ['data' => $results]);
    }

    private function routeId(ServerRequestInterface $request, string $name): UuidV7
    {
        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS, []);
        if (!is_array($parameters) || !is_string($parameters[$name] ?? null)) {
            throw new \InvalidArgumentException('Offline route identifier is invalid.');
        }

        return UuidV7::fromString($parameters[$name]);
    }

    /** @param array<string,mixed> $payload */
    private function json(int $status, array $payload): ResponseInterface
    {
        $response = $this->responses->createResponse($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('X-QMDB-Offline-Protocol-Version', '1');
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $response;
    }

    private function problem(int $status, string $code, string $detail): ResponseInterface
    {
        return $this->json($status, ['type' => 'about:blank', 'title' => $code, 'status' => $status, 'detail' => $detail]);
    }

    /** @return array<string,mixed> */
    private function stringKeyedArray(mixed $value, string $message): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException($message);
        }
        $normalized = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException($message);
            }
            $normalized[$key] = $item;
        }

        return $normalized;
    }
}
