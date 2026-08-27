<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Domain;

use Qmdb\Shared\Identifier\UuidV7;

final readonly class PasswordRecoveryEventId
{
    private function __construct(private UuidV7 $value)
    {
    }

    public static function generate(): self
    {
        return new self(UuidV7::generate());
    }

    public function toBinary(): string
    {
        return $this->value->toBinary();
    }
}
