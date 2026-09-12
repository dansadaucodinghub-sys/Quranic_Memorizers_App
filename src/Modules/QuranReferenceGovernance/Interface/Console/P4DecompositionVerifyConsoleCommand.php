<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Interface\Console;

use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class P4DecompositionVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private string $projectRoot)
    {
    }
    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('governance:p4-decomposition:verify');
    }
    public function description(): string
    {
        return 'Verify the owner-approved P4 decomposition and authorization boundary.';
    }
    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        $decomposition = @file_get_contents($this->projectRoot . '/docs/implementation/P4-quran-reference-and-governance-decomposition.md');
        $authorization = @file_get_contents($this->projectRoot . '/docs/project/phase-authorizations/QMDB-P4-B01.yaml');
        $required = ['Qur’an Reference and Governance','Competition Configuration and Registration','QMDB-P4-B01','Qur’an Source Registry, Release Governance, Provenance, and Canonical Integrity Policy','QMDB-P4-B02','Canonical Qur’an Text, Surah and Ayah Structure, and Partition Metadata Import','QMDB-P4-B03','Public Qur’an Reference, Navigation, Search, Accessibility, and Security Hardening','QMDB-P4-REQ-001 through QMDB-P4-REQ-008','DEFINED / NOT AUTHORIZED'];
        if (!is_string($decomposition) || !is_string($authorization)) {
            $output->write("P4 decomposition verification: FAIL\n");
            return 1;
        }
        $valid = true;
        foreach ($required as $value) {
            $valid = $valid && str_contains($decomposition, $value);
        }
        foreach (range(1, 8) as $number) {
            $valid = $valid && str_contains($authorization, sprintf('QMDB-P4-REQ-%03d', $number));
        }
        $valid = $valid && !str_contains($authorization, 'QMDB-P4-REQ-009') && str_contains($authorization, 'Competition');
        $output->write('P4 decomposition verification: ' . ($valid ? "PASS\n" : "FAIL\n"));
        return $valid ? 0 : 1;
    }
}
