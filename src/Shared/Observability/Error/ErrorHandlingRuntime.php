<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Error;

final class ErrorHandlingRuntime
{
    private bool $registered = false;

    public function __construct(
        private readonly PhpErrorHandler $phpErrorHandler,
        private readonly FatalErrorShutdownReporter $shutdownReporter,
    ) {
    }

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->phpErrorHandler->register();
        $this->shutdownReporter->register();
        $this->registered = true;
    }

    public function unregister(): void
    {
        if (!$this->registered) {
            return;
        }

        $this->phpErrorHandler->unregister();
        $this->registered = false;
    }
}
