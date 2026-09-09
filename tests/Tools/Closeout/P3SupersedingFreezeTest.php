<?php

declare(strict_types=1);

namespace Qmdb\Tests\Tools\Closeout;

use PHPUnit\Framework\TestCase;
use Qmdb\Tools\Closeout\PostP3ExtensionLedger;
use Qmdb\Tools\Closeout\P3SupersedingFreezePolicy;

final class P3SupersedingFreezeTest extends TestCase
{
    public function testEmptyLedgerIsValidAndPolicyExcludesOnlyItsMutableControlFiles(): void
    {
        $ledger = new PostP3ExtensionLedger();
        $ledger->verify([
            'ledger_id' => PostP3ExtensionLedger::ID,
            'base_freeze' => 'QMDB-P3-FRZ-003',
            'entries' => [],
        ]);

        $policy = new P3SupersedingFreezePolicy();
        self::assertFalse($policy->isIncluded(P3SupersedingFreezePolicy::MANIFEST));
        self::assertFalse($policy->isIncluded(PostP3ExtensionLedger::PATH));
        self::assertTrue($policy->isIncluded('src/Modules/People/Application/PersonProfileService.php'));
        self::assertFalse($policy->isIncluded('build/release/qmdb.tar.gz'));
    }

    public function testLedgerRejectsBroadOrTamperedAuthorization(): void
    {
        $ledger = new PostP3ExtensionLedger();
        $entry = [
            'extension_id' => 'QMDB-P3-EXT-001',
            'authorizing_change' => 'QMDB-CR-002',
            'base_freeze' => 'QMDB-P3-FRZ-003',
            'phase' => 'P4',
            'batch' => 'QMDB-P4-B01',
            'previous_entry_sha256' => $ledger->genesisHash(),
            'new_files' => [['path' => 'src/Modules/QuranReference/Example.php', 'sha256' => str_repeat('a', 64)]],
            'modified_extension_points' => [],
        ];
        $entry['entry_sha256'] = hash('sha256', json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $valid = ['ledger_id' => PostP3ExtensionLedger::ID, 'base_freeze' => 'QMDB-P3-FRZ-003', 'entries' => [$entry]];
        $ledger->verify($valid);
        self::assertSame(str_repeat('a', 64), $ledger->authorizedNewFiles($valid)['src/Modules/QuranReference/Example.php']);

        $entry['batch'] = 'QMDB-P4-B02';
        self::expectException(\RuntimeException::class);
        $ledger->verify(['ledger_id' => PostP3ExtensionLedger::ID, 'base_freeze' => 'QMDB-P3-FRZ-003', 'entries' => [$entry]]);
    }
}
