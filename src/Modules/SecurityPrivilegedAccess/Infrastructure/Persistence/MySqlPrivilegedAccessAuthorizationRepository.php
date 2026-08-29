<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationDecisionSource;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessAuthorizationRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final readonly class MySqlPrivilegedAccessAuthorizationRepository implements PrivilegedAccessAuthorizationRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function activeSource(
        AuthorizationSubject $subject,
        PermissionCode $permission,
        AuthorizationScopeType $scope,
        ?int $workspaceInternalId,
        DateTimeImmutable $now,
    ): ?AuthorizationDecisionSource {
        if (($scope === AuthorizationScopeType::PLATFORM) !== ($workspaceInternalId === null)) {
            return null;
        }
        $pdo = $this->provider->connection();
        if (!$this->schemaIsAvailable($pdo)) {
            return null;
        }
        $statement = $pdo->prepare(<<<'SQL'
SELECT activation.access_type
FROM privileged_access_activations activation
INNER JOIN privileged_access_requests request_record ON request_record.id = activation.request_id
INNER JOIN privileged_access_request_permissions granted_permission
    ON granted_permission.request_id = request_record.id
INNER JOIN authorization_permissions permission_definition
    ON permission_definition.id = granted_permission.permission_id
INNER JOIN user_accounts account ON account.id = activation.subject_account_id
INNER JOIN user_sessions session_record
    ON session_record.id = activation.session_id AND session_record.account_id = activation.subject_account_id
LEFT JOIN workspaces workspace ON workspace.id = activation.workspace_id
LEFT JOIN workspace_memberships membership ON membership.id = request_record.subject_membership_id
WHERE activation.subject_account_id = :account_id
    AND activation.session_id = :session_id
    AND activation.status = 'ACTIVE'
    AND activation.expires_at > :now_activation
    AND request_record.status = 'ACTIVE'
    AND request_record.scope_type = :scope_type
    AND permission_definition.code = :permission_code
    AND permission_definition.scope_type = :scope_type
    AND permission_definition.status = 'ACTIVE'
    AND granted_permission.permission_scope_type = :scope_type
    AND account.account_status = 'ACTIVE'
    AND session_record.status = 'ACTIVE'
    AND session_record.idle_expires_at > :now_idle
    AND session_record.absolute_expires_at > :now_absolute
    AND (
        (:workspace_id IS NULL AND activation.workspace_id IS NULL)
        OR (:workspace_id IS NOT NULL AND activation.workspace_id = :workspace_id_match
            AND workspace.status_code = 'ACTIVE'
            AND (request_record.subject_membership_id IS NULL OR membership.status_code = 'ACTIVE'))
    )
ORDER BY activation.id DESC
LIMIT 1
SQL);
        $instant = $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        $statement->bindValue(':account_id', $subject->accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':session_id', $subject->sessionInternalId, PDO::PARAM_INT);
        $statement->bindValue(':now_activation', $instant);
        $statement->bindValue(':now_idle', $instant);
        $statement->bindValue(':now_absolute', $instant);
        $statement->bindValue(':scope_type', $scope->value);
        $statement->bindValue(':permission_code', $permission->value());
        $statement->bindValue(':workspace_id', $workspaceInternalId, $workspaceInternalId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':workspace_id_match', $workspaceInternalId, $workspaceInternalId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->execute();
        $value = $statement->fetchColumn();
        if (!is_string($value)) {
            return null;
        }

        return match ($value) {
            'TEMPORARY_PRIVILEGE' => AuthorizationDecisionSource::TEMPORARY_PRIVILEGE,
            'SUPPORT_ACCESS' => AuthorizationDecisionSource::SUPPORT_ACCESS,
            'BREAK_GLASS' => AuthorizationDecisionSource::BREAK_GLASS,
            default => throw new \UnexpectedValueException('Privileged access source is invalid.'),
        };
    }

    private function schemaIsAvailable(PDO $pdo): bool
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() '
            . "AND table_name IN ('privileged_access_requests', 'privileged_access_activations', 'privileged_access_request_permissions')",
        );
        $statement->execute();
        $count = $statement->fetchColumn();

        return (is_int($count) || is_string($count) && ctype_digit($count)) && (int) $count === 3;
    }
}
