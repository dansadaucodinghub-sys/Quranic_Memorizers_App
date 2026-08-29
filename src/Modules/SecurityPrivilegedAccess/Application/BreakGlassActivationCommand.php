<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessType;

final readonly class BreakGlassActivationCommand
{
    public function __construct(public PrivilegedAccessRequestCommand $request)
    {
        if ($request->type !== PrivilegedAccessType::BREAK_GLASS) {
            throw new \InvalidArgumentException('Break-glass activation requires a break-glass request payload.');
        }
    }
}
