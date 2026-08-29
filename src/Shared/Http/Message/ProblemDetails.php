<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Message;

use InvalidArgumentException;

final readonly class ProblemDetails
{
    /** @var array<int, string> */
    private const TITLES = [
        400 => 'Bad Request',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        500 => 'Internal Server Error',
    ];

    /** @var array<int, string> */
    private const CODES = [
        400 => 'REQUEST_TARGET_INVALID',
        403 => 'AUTHORIZATION_DENIED',
        404 => 'ROUTE_NOT_FOUND',
        405 => 'METHOD_NOT_ALLOWED',
        409 => 'CONFLICT',
        500 => 'INTERNAL_SERVER_ERROR',
    ];

    /** @var array<string, string> */
    private const CODE_TITLES = [
        'TENANT_CONTEXT_STALE' => 'Workspace Context Changed',
    ];

    /** @param array<string, scalar|null> $extensions */
    public function __construct(
        private int $status,
        private array $extensions = [],
        private ?string $code = null,
    ) {
        if (!isset(self::TITLES[$status])) {
            throw new InvalidArgumentException('Unsupported problem-details status.');
        }

        foreach (array_keys($extensions) as $name) {
            if (in_array($name, ['type', 'title', 'status', 'code'], true)) {
                throw new InvalidArgumentException('Problem extension collides with a required field.');
            }

            if (preg_match('/^[a-z][a-z0-9_]*$/D', $name) !== 1) {
                throw new InvalidArgumentException('Problem extension name is invalid.');
            }
        }
    }

    public static function badRequest(): self
    {
        return new self(400);
    }

    public static function notFound(): self
    {
        return new self(404);
    }

    public static function forbidden(): self
    {
        return new self(403);
    }

    public static function methodNotAllowed(): self
    {
        return new self(405);
    }

    public static function internalServerError(): self
    {
        return new self(500);
    }

    public static function safe(int $status, string $code): self
    {
        if (preg_match('/\A[A-Z][A-Z0-9_]{2,79}\z/', $code) !== 1) {
            throw new InvalidArgumentException('Problem-details code is invalid.');
        }

        return new self($status, [], $code);
    }

    public function withRequestId(string $requestId): self
    {
        return new self($this->status, [...$this->extensions, 'request_id' => $requestId], $this->code);
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string, scalar|null> */
    public function toArray(): array
    {
        return [
            'type' => 'about:blank',
            'title' => self::CODE_TITLES[$this->code ?? ''] ?? self::TITLES[$this->status],
            'status' => $this->status,
            'code' => $this->code ?? self::CODES[$this->status],
            ...$this->extensions,
        ];
    }
}
