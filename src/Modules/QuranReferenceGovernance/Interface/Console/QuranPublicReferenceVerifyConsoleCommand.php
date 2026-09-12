<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Interface\Console;

use PDO;
use PDOStatement;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final readonly class QuranPublicReferenceVerifyConsoleCommand implements ConsoleCommand
{
    private const array ROUTES = ['quran.public.home','quran.public.surahs','quran.public.surah','quran.public.ayah','quran.public.partition','quran.public.sajdahs','quran.public.search'];
    public function __construct(private string $root, private DatabaseConnectionProvider $connections)
    {
    }
    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('quran:public-reference:verify');
    }
    public function description(): string
    {
        return 'Verify public Qur’an route coverage and active reference data boundaries.';
    }
    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        try {
            $source = file_get_contents($this->root . '/routes/web.php');
            if (!is_string($source)) {
                throw new \RuntimeException('Route registry is unreadable.');
            }foreach (self::ROUTES as $route) {
                if (substr_count($source, "'{$route}'") !== 1) {
                    throw new \RuntimeException('Required public Qur’an route is missing or duplicated: ' . $route);
                }
            }foreach (self::ROUTES as $route) {
                if (!preg_match("/new Route\\('" . preg_quote($route, '/') . "'\\s*,\\s*\\[HttpMethod::GET\\]/", $source)) {
                        throw new \RuntimeException('Public Qur’an route is not GET-only.');
                }
            }$pdo = $this->connections->connection();
            $activeStatement = $pdo->query("SELECT COUNT(*) FROM quran_reference_releases WHERE status='ACTIVE'");
            $corporaStatement = $pdo->query("SELECT COUNT(*) FROM quran_search_corpora WHERE status='ACTIVE'");
            if (!$activeStatement instanceof PDOStatement || !$corporaStatement instanceof PDOStatement) {
                throw new \RuntimeException('Public reference verification query could not be prepared.');
            }
            $active = (int)$activeStatement->fetchColumn();
            $corpora = (int)$corporaStatement->fetchColumn();
            if ($active !== 1 || $corpora !== 1) {
                throw new \RuntimeException('Public reference requires exactly one active release and corpus.');
            }$output->write("Qur’an public-reference verification: PASS\nRoutes: " . count(self::ROUTES) . "\n");
            return 0;
        } catch (\Throwable $e) {
            $output->write("Qur’an public-reference verification: FAIL\n{$e->getMessage()}\n");
            return 1;
        }
    }
}
