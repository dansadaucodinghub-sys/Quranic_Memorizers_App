<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Domain;

use InvalidArgumentException;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class SecurityAuditStreamIdentity
{
    public function __construct(public SecurityAuditStreamType $type, public ?UuidV7 $scopePublicId)
    {
        if (($type === SecurityAuditStreamType::PLATFORM) !== ($scopePublicId === null)) {
            throw new InvalidArgumentException('Security audit stream scope is invalid.');
        }
    }

    public static function platform(): self
    {
        return new self(SecurityAuditStreamType::PLATFORM, null);
    }

    public function streamKey(): SecurityAuditStreamKey
    {
        $scope = $this->scopePublicId?->toString() ?? 'platform';

        return new SecurityAuditStreamKey(hash('sha256', "QMDB-AUDIT-STREAM-V1\\0{$this->type->value}\\0{$scope}", true));
    }

    /**
     * Kept as a binary convenience for persistence code; use streamKey() at domain boundaries.
     */
    public function key(): string
    {
        return $this->streamKey()->binary();
    }
}
