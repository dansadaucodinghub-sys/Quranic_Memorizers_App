<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccountState\Domain;

enum AccountStateReasonCode: string
{
    case SECURITY_INCIDENT = 'SECURITY_INCIDENT';
    case POLICY_REVIEW = 'POLICY_REVIEW';
    case CREDENTIAL_COMPROMISE = 'CREDENTIAL_COMPROMISE';
    case ABUSE_RESPONSE = 'ABUSE_RESPONSE';
    case ADMINISTRATIVE_CORRECTION = 'ADMINISTRATIVE_CORRECTION';
    case SECURITY_REMEDIATION_COMPLETE = 'SECURITY_REMEDIATION_COMPLETE';

    public function permits(AccountStateOperationType $operation): bool
    {
        return $operation === AccountStateOperationType::SUSPEND
            ? $this !== self::SECURITY_REMEDIATION_COMPLETE
            : in_array($this, [self::ADMINISTRATIVE_CORRECTION, self::SECURITY_REMEDIATION_COMPLETE], true);
    }
}
