<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Infrastructure\Persistence;

use PDO;
use PDOStatement;
use Qmdb\Modules\CertificateIssuance\Application\CertificateNumberAllocator;
use Qmdb\Modules\CertificateIssuance\Domain\CertificatePublicIdentifierPolicy;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

/** Database row locks make issuance numbering safe under concurrent workers. */
final readonly class MySqlCertificateNumberAllocator implements CertificateNumberAllocator
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function allocate(int $workspaceId, int $year, string $scope): string
    {
        if ($workspaceId < 1 || $year < 2000 || $year > 9999 || preg_match('/\A[A-Z][A-Z0-9_]{1,62}\z/', $scope) !== 1) {
            throw new \InvalidArgumentException('Certificate number allocation input is invalid.');
        }
        $pdo = $this->connections->connection();
        $workspace = $this->statement($pdo, 'SELECT workspace_code FROM workspaces WHERE id=:workspace_id FOR UPDATE');
        $workspace->execute([':workspace_id' => $workspaceId]);
        $workspaceCode = $workspace->fetchColumn();
        if (!is_string($workspaceCode) || preg_match('/\A[A-Z0-9][A-Z0-9_-]{0,63}\z/i', $workspaceCode) !== 1) {
            throw new \DomainException('Certificate workspace is unavailable.');
        }
        $sequence = $this->statement($pdo, 'SELECT id,next_sequence FROM certificate_number_sequences WHERE workspace_id=:workspace_id AND sequence_scope=:scope AND sequence_year=:year FOR UPDATE');
        $sequence->execute([':workspace_id' => $workspaceId, ':scope' => $scope, ':year' => $year]);
        $row = $sequence->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            $number = 1;
            $insert = $this->statement($pdo, 'INSERT INTO certificate_number_sequences (public_id,workspace_id,sequence_scope,sequence_year,next_sequence,version,created_at,updated_at) VALUES (:public_id,:workspace_id,:scope,:year,2,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))');
            $insert->execute([':public_id' => UuidV7::generate()->toBinary(), ':workspace_id' => $workspaceId, ':scope' => $scope, ':year' => $year]);
        } else {
            $nextSequence = $row['next_sequence'] ?? null;
            $sequenceId = $row['id'] ?? null;
            if ((!is_int($nextSequence) && !is_string($nextSequence)) || (!is_int($sequenceId) && !is_string($sequenceId))) {
                throw new \RuntimeException('Certificate number sequence is invalid.');
            }
            $number = (int) $nextSequence;
            if ($number < 1) {
                throw new \RuntimeException('Certificate number sequence is invalid.');
            }
            $update = $this->statement($pdo, 'UPDATE certificate_number_sequences SET next_sequence=:next_sequence,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=:id AND next_sequence=:current_sequence');
            $update->execute([':next_sequence' => $number + 1, ':id' => (int) $sequenceId, ':current_sequence' => $number]);
            if ($update->rowCount() !== 1) {
                throw new \DomainException('Certificate number sequence changed concurrently.');
            }
        }

        return CertificatePublicIdentifierPolicy::serial($year, $workspaceCode, $number);
    }

    private function statement(PDO $pdo, string $sql): PDOStatement
    {
        $statement = $pdo->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Certificate number statement could not be prepared.');
        }

        return $statement;
    }
}
