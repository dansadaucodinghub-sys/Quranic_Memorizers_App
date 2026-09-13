<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Infrastructure\Persistence;

use PDO;
use PDOStatement;
use Qmdb\Modules\CompetitionLive\Application\CompetitionLivePublicReadRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final readonly class MySqlCompetitionLivePublicReadRepository implements CompetitionLivePublicReadRepository
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function currentSnapshot(string $editionSlug): ?array
    {
        if (preg_match('/\A[a-z0-9][a-z0-9-]{0,127}\z/', $editionSlug) !== 1) {
            throw new \InvalidArgumentException('Edition slug is invalid.');
        }
        $statement = $this->connections->connection()->prepare("SELECT snapshot.event_sequence,snapshot.public_payload_canonical_json,snapshot.snapshot_sha256 FROM competition_live_sessions session_record INNER JOIN competition_editions edition ON edition.workspace_id=session_record.workspace_id AND edition.id=session_record.edition_id INNER JOIN competition_live_projection_streams stream_record ON stream_record.workspace_id=session_record.workspace_id AND stream_record.live_session_id=session_record.id AND stream_record.stream_code='PUBLIC' INNER JOIN competition_live_projection_snapshots snapshot ON snapshot.workspace_id=stream_record.workspace_id AND snapshot.projection_stream_id=stream_record.id AND snapshot.event_sequence=stream_record.projected_event_sequence WHERE edition.slug=:edition_slug AND edition.public_visibility='PUBLIC' AND session_record.public_visibility='PUBLIC' AND session_record.status IN ('OPEN','PAUSED','CLOSED') AND stream_record.status IN ('ACTIVE','PAUSED','DEGRADED','CLOSED') ORDER BY session_record.opened_at DESC,session_record.id DESC LIMIT 1");
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Public live snapshot statement could not be prepared.');
        }
        $statement->execute([':edition_slug' => $editionSlug]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !is_int($row['event_sequence'] ?? null) && !is_string($row['event_sequence'] ?? null) || !is_string($row['public_payload_canonical_json'] ?? null) || !is_string($row['snapshot_sha256'] ?? null)) {
            return null;
        }
        $payload = json_decode($row['public_payload_canonical_json'], true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($payload) || array_is_list($payload) || strlen($row['snapshot_sha256']) !== 32) {
            throw new \RuntimeException('Public live snapshot is structurally invalid.');
        }
        $publicPayload = [];
        foreach ($payload as $key => $value) {
            if (!is_string($key)) {
                throw new \RuntimeException('Public live snapshot has a non-string field name.');
            }
            $publicPayload[$key] = $value;
        }

        return ['sequence' => (int) $row['event_sequence'], 'payload' => $publicPayload, 'checksum' => bin2hex($row['snapshot_sha256'])];
    }
}
