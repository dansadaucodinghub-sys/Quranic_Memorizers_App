<?php

declare(strict_types=1);

namespace Qmdb\Shared\Presentation\Security;

interface CspNonceGenerator
{
    public function generate(): CspNonce;
}
