<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Routing\Security;

use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Qmdb\Shared\Http\Routing\RouteCollection;

final readonly class RouteSecurityVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(
        private RouteCollection $routes,
        private RouteSecurityVerifier $verifier,
    ) {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('security:routes:verify');
    }

    public function description(): string
    {
        return 'Verify the closed production-route security policy inventory.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        $report = $this->verifier->verify($this->routes);
        $output->writeln('Route security verification: ' . ($report->isValid() ? 'PASS' : 'FAIL'));
        $output->writeln('Production routes: ' . $report->routeCount);
        $output->writeln('Classified routes: ' . $report->classifiedRouteCount);
        $output->writeln('Mutation routes: ' . $report->mutationRouteCount);
        $output->writeln('CSRF-protected mutations: ' . $report->csrfProtectedMutationCount);
        foreach ($report->errors as $error) {
            $output->errorLine($error);
        }

        return $report->isValid() ? 0 : 1;
    }
}
