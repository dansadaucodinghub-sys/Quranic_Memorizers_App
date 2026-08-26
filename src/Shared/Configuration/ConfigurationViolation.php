<?php

declare(strict_types=1);

namespace Qmdb\Shared\Configuration;

final readonly class ConfigurationViolation
{
    public const REQUIRED_VALUE_MISSING = 'CONFIG_REQUIRED_VALUE_MISSING';
    public const INVALID_ENVIRONMENT = 'CONFIG_INVALID_ENVIRONMENT';
    public const INVALID_BOOLEAN = 'CONFIG_INVALID_BOOLEAN';
    public const INVALID_TIMEZONE = 'CONFIG_INVALID_TIMEZONE';
    public const DEBUG_PROHIBITED = 'CONFIG_DEBUG_PROHIBITED';
    public const DOTENV_PROHIBITED = 'CONFIG_DOTENV_PROHIBITED';
    public const DOTENV_PARSE_FAILED = 'CONFIG_DOTENV_PARSE_FAILED';
    public const INVALID_LOG_LEVEL = 'CONFIG_INVALID_LOG_LEVEL';
    public const INVALID_BACKGROUND_CONFIGURATION = 'CONFIG_INVALID_BACKGROUND_CONFIGURATION';

    public function __construct(
        private string $code,
        private string $variableName,
        private string $message,
        private string $severity = 'error',
    ) {
    }

    public function code(): string
    {
        return $this->code;
    }

    public function variableName(): string
    {
        return $this->variableName;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function severity(): string
    {
        return $this->severity;
    }

    /** @return array{code: string, variable: string, message: string, severity: string} */
    public function toSafeArray(): array
    {
        return [
            'code' => $this->code,
            'variable' => $this->variableName,
            'message' => $this->message,
            'severity' => $this->severity,
        ];
    }
}
