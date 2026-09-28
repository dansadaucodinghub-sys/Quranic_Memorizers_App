<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\SessionAuthenticationAssurance;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentitySessions\Domain\DeviceId;
use Qmdb\Modules\IdentitySessions\Domain\SessionId;
use Qmdb\Modules\Tenancy\Domain\MembershipStatus;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Modules\Tenancy\Domain\WorkspaceStatus;
use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;
use Qmdb\Modules\TenancyContext\Domain\ResolvedWorkspaceMembershipIdentity;
use Qmdb\Modules\TenancyContext\Domain\TenantContextVersion;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

/** Resolves a device registrar's current account, workspace membership, and delegated authority server-side. */
final readonly class OfflineAuthoritativeContextFactory
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    /**
     * @param array{workspace_id:int,venue_id:int,device_id:int,package_id:int,public_key:string,device_status:string,package_status:string,package_expires:string} $authority
     * @return array{actor:AuthenticatedAccountContext,tenant:AccountWorkspaceTenantContext}
     */
    public function resolve(array $authority, UuidV7 $changeId): array
    {
        $statement = $this->connections->connection()->prepare(
            "SELECT d.public_id device_public_id,d.registered_by_account_id,a.public_id account_public_id,"
            . "w.public_id workspace_public_id,w.name workspace_name,w.status_code workspace_status,w.version workspace_version,"
            . "m.id membership_id,m.public_id membership_public_id,m.status_code membership_status,m.version membership_version "
            . "FROM venue_edge_node_registrations d "
            . "INNER JOIN user_accounts a ON a.id=d.registered_by_account_id AND a.account_status='ACTIVE' "
            . "INNER JOIN workspaces w ON w.id=d.workspace_id AND w.status_code='ACTIVE' "
            . "INNER JOIN workspace_memberships m ON m.workspace_id=w.id AND m.user_account_id=a.id AND m.status_code='ACTIVE' "
            . 'WHERE d.id=:device AND d.workspace_id=:workspace LIMIT 1',
        );
        $statement->execute([':device' => $authority['device_id'], ':workspace' => $authority['workspace_id']]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new \DomainException('Offline operation actor authority is unavailable.');
        }
        $row = $this->normalize($row);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $actor = new AuthenticatedAccountContext(
            $authority['device_id'],
            AccountId::fromBinary($this->binary($row, 'account_public_id')),
            $authority['device_id'],
            SessionId::fromString($changeId->toString()),
            $authority['device_id'],
            DeviceId::fromBinary($this->binary($row, 'device_public_id')),
            $now,
            1,
            new SessionAuthenticationAssurance(AuthenticationMethod::PASSKEY, null, AuthenticationAssuranceLevel::PHISHING_RESISTANT, $now, $now),
        );
        // The public account identity is authoritative; the internal account id must be the registrar, not the device id.
        $registeredBy = $this->integer($row, 'registered_by_account_id', $authority['device_id']);
        $actor = new AuthenticatedAccountContext(
            $registeredBy,
            $actor->accountId,
            $authority['device_id'],
            $actor->sessionId,
            $authority['device_id'],
            $actor->deviceId,
            $now,
            1,
            $actor->assurance,
        );
        $membershipId = $this->integer($row, 'membership_id');
        $membershipVersion = $this->integer($row, 'membership_version');
        $tenant = AccountWorkspaceTenantContext::trusted(
            $registeredBy,
            $actor->accountId,
            $actor->sessionInternalId,
            $actor->sessionId,
            $authority['workspace_id'],
            WorkspaceId::fromBinary($this->binary($row, 'workspace_public_id')),
            WorkspaceStatus::from($this->text($row, 'workspace_status')),
            $this->integer($row, 'workspace_version'),
            ResolvedWorkspaceMembershipIdentity::trusted(
                $membershipId,
                UuidV7::fromBinary($this->binary($row, 'membership_public_id')),
                $authority['workspace_id'],
                $registeredBy,
                MembershipStatus::from($this->text($row, 'membership_status')),
                $membershipVersion,
            ),
            $this->text($row, 'workspace_name'),
            new TenantContextVersion(max(1, $membershipVersion)),
            $now,
        );

        return ['actor' => $actor, 'tenant' => $tenant];
    }

    /** @param array<string,mixed> $row */
    private function binary(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new \RuntimeException('Offline authority identifier is invalid.');
        }

        return $value;
    }

    /**
     * @param array<array-key,mixed> $row
     * @return array<string,mixed>
     */
    private function normalize(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            if (!is_string($key)) {
                throw new \RuntimeException('Offline authority column is invalid.');
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /** @param array<string,mixed> $row */
    private function text(array $row, string $key): string
    {
        return $this->binary($row, $key);
    }

    /** @param array<string,mixed> $row */
    private function integer(array $row, string $key, ?int $default = null): int
    {
        $value = $row[$key] ?? $default;
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
            throw new \RuntimeException('Offline authority integer is invalid.');
        }

        return (int) $value;
    }
}
