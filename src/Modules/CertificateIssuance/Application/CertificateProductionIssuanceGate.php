<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Application;

/** Prevents production issuance until a governed authority implementation replaces the open-decision gate. */
interface CertificateProductionIssuanceGate
{
    public function assertPreparationPermitted(): void;

    public function assertIssuancePermitted(): void;
}
