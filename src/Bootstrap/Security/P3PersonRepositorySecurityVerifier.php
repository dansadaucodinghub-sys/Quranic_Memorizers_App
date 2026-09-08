<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Security;

/** Read-only source verifier for the closed Person-authority repository boundary. */
final readonly class P3PersonRepositorySecurityVerifier
{
    public function __construct(private string $projectRoot)
    {
    }

    public function verify(): P3PersonRepositorySecurityVerificationReport
    {
        $source = file_get_contents($this->projectRoot . '/src/Modules/People/Infrastructure/Persistence/MySqlPersonProfileRepository.php');
        if (!is_string($source)) {
            return new P3PersonRepositorySecurityVerificationReport(0, 0, ['PERSON_REPOSITORY_SOURCE_UNREADABLE']);
        }

        $requirements = [
            'SELF_LOOKUP_ACCOUNT_SCOPE' => 'WHERE l.account_id = :account_id AND l.link_type = \'SELF\' AND l.status = \'ACTIVE\'',
            'DEPENDENT_LOOKUP_GUARDIAN_SCOPE' => 'WHERE g.guardian_person_id = :guardian_id AND g.status = \'ACTIVE\' AND p.public_id = :public_id',
            'GUARDIANSHIP_LOOKUP_GUARDIAN_SCOPE' => 'WHERE g.guardian_person_id = :guardian_id AND g.public_id = :public_id',
            'DEPENDENT_LIST_GUARDIAN_SCOPE' => 'WHERE g.guardian_person_id = :guardian_id AND g.status = \'ACTIVE\'',
        ];
        $errors = [];
        foreach ($requirements as $code => $needle) {
            if (!str_contains($source, $needle)) {
                $errors[] = 'PERSON_REPOSITORY_SCOPE_INVALID:' . $code;
            }
        }
        if (str_contains($source, 'DELETE ' . 'FROM people_persons')) {
            $errors[] = 'PERSON_REPOSITORY_SCOPE_INVALID:NO_PERSON_DELETE';
        }

        return new P3PersonRepositorySecurityVerificationReport(1, count($requirements) + 1, $errors);
    }
}
