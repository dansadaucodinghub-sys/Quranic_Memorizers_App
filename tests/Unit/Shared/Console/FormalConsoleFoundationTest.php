<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Console;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandDispatcher;
use Qmdb\Shared\Console\Command\ConsoleCommandMap;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Command\ConsoleCommandRegistry;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Input\ConsoleInputParser;
use Qmdb\Shared\Console\Output\BufferedConsoleOutput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final class FormalConsoleFoundationTest extends TestCase
{
    #[DataProvider('validCommandNames')]
    public function testCommandNamesAreCanonical(string $name): void
    {
        self::assertSame($name, (new ConsoleCommandName($name))->value());
    }

    /** @return iterable<string, array{string}> */
    public static function validCommandNames(): iterable
    {
        yield 'application' => ['app:about'];
        yield 'migration' => ['db:migrate'];
        yield 'scheduler' => ['schedule:run'];
        yield 'worker' => ['worker:run'];
    }

    #[DataProvider('invalidCommandNames')]
    public function testInvalidCommandNamesAreRejected(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ConsoleCommandName($name);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidCommandNames(): iterable
    {
        yield 'uppercase' => ['App:About'];
        yield 'space' => ['app about'];
        yield 'path' => ['../command'];
        yield 'double separator' => ['app::about'];
        yield 'underscore' => ['app_about'];
    }

    public function testParserProducesImmutableFlagsAndScalarOptions(): void
    {
        $input = (new ConsoleInputParser())->parse([
            'worker:run',
            '--once',
            '--max-jobs=7',
        ]);

        self::assertSame('worker:run', $input->commandName()->value());
        self::assertTrue($input->requireFlag('once'));
        self::assertSame('7', $input->scalar('max-jobs'));
        self::assertNull($input->scalar('max-runtime-seconds'));
    }

    /** @param list<string> $arguments */
    #[DataProvider('malformedInputs')]
    public function testParserRejectsDuplicateMalformedOrSecretOptions(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ConsoleInputParser())->parse($arguments);
    }

    /** @return iterable<string, array{list<string>}> */
    public static function malformedInputs(): iterable
    {
        yield 'duplicate' => [['worker:run', '--once', '--once']];
        yield 'positional' => [['worker:run', 'payload']];
        yield 'short option' => [['worker:run', '-x']];
        yield 'password option' => [['worker:run', '--password=value']];
        yield 'control value' => [['worker:run', "--max-jobs=1\n2"]];
    }

    public function testInputRejectsUnknownOptionsAtOwningCommand(): void
    {
        $input = (new ConsoleInputParser())->parse(['worker:run', '--queue=secret']);
        $this->expectException(InvalidArgumentException::class);
        $input->assertOnlyOptions(['once']);
    }

    public function testBufferedOutputKeepsStandardStreamsSeparate(): void
    {
        $output = new BufferedConsoleOutput();
        $output->write('out');
        $output->writeln(' line');
        $output->error('err');
        $output->errorLine(' line');

        self::assertSame("out line\n", $output->standardOutput());
        self::assertSame("err line\n", $output->standardError());
    }

    public function testRegistryIsOrderedAndDispatchesExactCommand(): void
    {
        $alpha = $this->command('zeta:run', 7);
        $beta = $this->command('alpha:run', 3);
        $map = (new ConsoleCommandRegistry())->register($alpha)->register($beta)->build();
        $output = new BufferedConsoleOutput();
        $exit = (new ConsoleCommandDispatcher($map))->dispatch(
            new ConsoleInput(new ConsoleCommandName('zeta:run'), []),
            $output,
        );

        self::assertSame(['alpha:run', 'zeta:run'], array_map(
            static fn (ConsoleCommand $command): string => $command->name()->value(),
            $map->commands(),
        ));
        self::assertSame(7, $exit);
        self::assertSame("zeta:run\n", $output->standardOutput());
    }

    public function testDuplicateRegistrationIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ConsoleCommandMap([$this->command('test:run', 0), $this->command('test:run', 0)]);
    }

    public function testUnknownDispatchCannotSelectAService(): void
    {
        $dispatcher = new ConsoleCommandDispatcher(new ConsoleCommandMap([]));
        $this->expectException(InvalidArgumentException::class);
        $dispatcher->dispatch(
            new ConsoleInput(new ConsoleCommandName('unknown:command'), []),
            new BufferedConsoleOutput(),
        );
    }

    private function command(string $name, int $exitCode): ConsoleCommand
    {
        return new class ($name, $exitCode) implements ConsoleCommand {
            public function __construct(private string $name, private int $exitCode)
            {
            }

            public function name(): ConsoleCommandName
            {
                return new ConsoleCommandName($this->name);
            }

            public function description(): string
            {
                return 'Test command.';
            }

            public function execute(ConsoleInput $input, ConsoleOutput $output): int
            {
                $output->writeln($this->name);

                return $this->exitCode;
            }
        };
    }
}
