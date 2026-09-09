<?php

declare(strict_types=1);

namespace Qmdb\Tools\Security;

final class TrivyDatabaseException extends \RuntimeException
{
    public const FINDINGS = 10;
    public const DATABASE_UNAVAILABLE = 20;
    public const DATABASE_STALE = 21;
    public const SCHEMA_MISMATCH = 22;
    public const METADATA_INVALID = 23;
    public const CACHE_UNREADABLE = 24;
    public const EXECUTION_FAILURE = 30;
    public const REPORT_INVALID = 31;
    public const ARTIFACT_INVALID = 32;
    public const POLICY_INVALID = 40;
}
