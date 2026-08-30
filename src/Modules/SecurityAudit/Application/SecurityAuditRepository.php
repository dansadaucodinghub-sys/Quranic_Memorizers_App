<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Application;

interface SecurityAuditRepository
{
    public function findByPublicId(string $eventPublicId): ?SecurityAuditEventRecord;

    public function listForAccount(string $accountPublicId, ?string $cursor, int $limit): SecurityAuditPage;

    public function listPlatform(SecurityAuditListFilter $filter, ?string $cursor, int $limit): SecurityAuditPage;

    public function integrityStatus(): SecurityAuditIntegrityStatus;
}
