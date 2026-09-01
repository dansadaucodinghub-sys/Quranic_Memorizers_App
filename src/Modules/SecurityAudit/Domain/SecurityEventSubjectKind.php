<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Domain;

enum SecurityEventSubjectKind: string
{
    case ACCOUNT = 'ACCOUNT';
    case SESSION = 'SESSION';
    case DEVICE = 'DEVICE';
    case ROLE_ASSIGNMENT = 'ROLE_ASSIGNMENT';
    case AUTHENTICATOR = 'AUTHENTICATOR';
    case RECOVERY_CODE_SET = 'RECOVERY_CODE_SET';
    case PRIVILEGED_ACCESS = 'PRIVILEGED_ACCESS';
    case WORKSPACE = 'WORKSPACE';
    case SECURITY_AUDIT = 'SECURITY_AUDIT';
    case PERSON = 'PERSON';
    case PERSON_ROLE = 'PERSON_ROLE';
    case GUARDIANSHIP = 'GUARDIANSHIP';
    case ORGANIZATION = 'ORGANIZATION';
    case ORGANIZATION_UNIT = 'ORGANIZATION_UNIT';
}
