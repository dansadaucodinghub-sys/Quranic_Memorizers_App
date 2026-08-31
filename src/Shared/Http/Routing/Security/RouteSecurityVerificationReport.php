<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Routing\Security;

final readonly class RouteSecurityVerificationReport
{
    /** @param list<string> $errors */
    public function __construct(
        public int $routeCount,
        public int $classifiedRouteCount,
        public int $mutationRouteCount,
        public int $csrfProtectedMutationCount,
        public array $errors,
    ) {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
