<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Error;

use ErrorException;

final class PhpErrorHandler
{
    private const SUPPORTED = E_WARNING
        | E_NOTICE
        | E_USER_ERROR
        | E_USER_WARNING
        | E_USER_NOTICE
        | E_RECOVERABLE_ERROR;

    private bool $registered = false;

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        set_error_handler($this->handle(...));
        $this->registered = true;
    }

    public function unregister(): void
    {
        if (!$this->registered) {
            return;
        }

        restore_error_handler();
        $this->registered = false;
    }

    public function isRegistered(): bool
    {
        return $this->registered;
    }

    public function handle(int $severity, string $message, string $file, int $line): bool
    {
        if ((error_reporting() & $severity) === 0 || (self::SUPPORTED & $severity) === 0) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }
}
