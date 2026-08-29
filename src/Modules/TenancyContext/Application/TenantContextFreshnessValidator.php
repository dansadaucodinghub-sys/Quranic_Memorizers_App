<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application;

use Qmdb\Modules\TenancyContext\Application\Exception\StaleTenantContextException;
use Qmdb\Modules\TenancyContext\Domain\TenantContextVersion;

final readonly class TenantContextFreshnessValidator
{
    public function validate(
        TenantContextVersion $authoritativeVersion,
        ?TenantContextVersion $clientVersion,
        bool $required,
    ): TenantContextFreshnessResult {
        if (
            ($required && $clientVersion === null)
            || ($clientVersion !== null && $clientVersion->value !== $authoritativeVersion->value)
        ) {
            throw new StaleTenantContextException();
        }

        return new TenantContextFreshnessResult($authoritativeVersion, $clientVersion !== null);
    }
}
