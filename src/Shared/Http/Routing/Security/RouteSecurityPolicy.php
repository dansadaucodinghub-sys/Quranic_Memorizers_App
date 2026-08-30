<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Routing\Security;

final readonly class RouteSecurityPolicy
{
    public function __construct(
        public RouteSecurityClassification $classification,
        public bool $requiresTenantContext,
        public ?string $permissionCode,
        public ?string $requiredAssurance,
        public ?string $stepUpAction,
        public ?string $csrfAction,
        public bool $requiresIdempotency,
        public bool $noStore,
        public ?string $requiredContentType,
    ) {
    }
}
