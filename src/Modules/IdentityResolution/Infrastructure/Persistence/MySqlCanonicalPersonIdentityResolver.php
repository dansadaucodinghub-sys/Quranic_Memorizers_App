<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Infrastructure\Persistence;

use PDO;
use Qmdb\Modules\People\Application\PersonCanonicalIdentityGroup;
use Qmdb\Modules\People\Application\PersonCanonicalIdentityResolver;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

/** Alias lookup is internal-only; this resolver intentionally has no HTTP API. */
final readonly class MySqlCanonicalPersonIdentityResolver implements PersonCanonicalIdentityResolver
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function resolve(int $personId): int
    {
        $statement = $this->provider->connection()->prepare('SELECT canonical_person_id FROM people_person_aliases WHERE source_person_id = :person_id LIMIT 1');
        $statement->execute(['person_id' => $personId]);
        $canonical = $statement->fetchColumn();

        if ($canonical === false) {
            return $personId;
        }
        if (is_int($canonical)) {
            return $canonical;
        }
        if (is_string($canonical) && ctype_digit($canonical)) {
            return (int) $canonical;
        }

        throw new \UnexpectedValueException('Canonical Person reference is invalid.');
    }

    public function group(int $personId): PersonCanonicalIdentityGroup
    {
        $canonical = $this->resolve($personId);
        $statement = $this->provider->connection()->prepare('SELECT source_person_id FROM people_person_aliases WHERE canonical_person_id = :canonical_person_id ORDER BY source_person_id');
        $statement->execute(['canonical_person_id' => $canonical]);
        $values = $statement->fetchAll(PDO::FETCH_COLUMN);
        $sources = [];
        foreach ($values as $value) {
            if (is_int($value)) {
                $sources[] = $value;
                continue;
            }
            if (is_string($value) && ctype_digit($value)) {
                $sources[] = (int) $value;
                continue;
            }

            throw new \UnexpectedValueException('Canonical Person alias is invalid.');
        }

        return new PersonCanonicalIdentityGroup($canonical, $sources);
    }
}
