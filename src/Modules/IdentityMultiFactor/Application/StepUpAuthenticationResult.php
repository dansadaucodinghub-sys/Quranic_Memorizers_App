<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Application;

use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentitySessions\Application\AuthenticationCookieInstruction;

final readonly class StepUpAuthenticationResult
{
    private function __construct(
        public bool $succeeded,
        public ?StepUpAction $action,
        public ?AuthenticationCookieInstruction $cookie,
        public string $reason,
    ) {
    }

    public static function started(StepUpAction $action, AuthenticationCookieInstruction $cookie): self
    {
        return new self(true, $action, $cookie, 'STARTED');
    }

    public static function completed(StepUpAction $action, AuthenticationCookieInstruction $cookie): self
    {
        return new self(true, $action, $cookie, 'COMPLETED');
    }

    public static function rejected(string $reason): self
    {
        return new self(false, null, null, $reason);
    }
}
