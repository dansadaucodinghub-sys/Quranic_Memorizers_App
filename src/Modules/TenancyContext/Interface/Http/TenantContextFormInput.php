<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Interface\Http;

use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Modules\TenancyContext\Domain\TenantContextVersion;

final readonly class TenantContextFormInput
{
    /** @return array{WorkspaceId, TenantContextVersion, string} */
    public function selection(ServerRequestInterface $request): array
    {
        $body = $this->body($request);
        $this->requireExactFields($body, ['csrf_token', 'tenant_context_version', 'workspace_id']);

        return [
            WorkspaceId::fromString($this->string($body, 'workspace_id', 36)),
            $this->version($request, $body),
            $this->string($body, 'csrf_token', 512),
        ];
    }

    /** @return array{TenantContextVersion, string} */
    public function clearing(ServerRequestInterface $request): array
    {
        $body = $this->body($request);
        $this->requireExactFields($body, ['csrf_token', 'tenant_context_version']);

        return [$this->version($request, $body), $this->string($body, 'csrf_token', 512)];
    }

    /** @return array<string, mixed> */
    private function body(ServerRequestInterface $request): array
    {
        $type = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0]));
        if ($type !== 'application/x-www-form-urlencoded') {
            throw new \DomainException('UNSUPPORTED_MEDIA_TYPE');
        }
        $body = $request->getParsedBody();
        if ($body === null) {
            $raw = (string)$request->getBody();
            if (strlen($raw) > 8192) {
                throw new \InvalidArgumentException('Tenant context form is too large.');
            }
            parse_str($raw, $body);
        }
        if (!is_array($body)) {
            throw new \InvalidArgumentException('Tenant context form is invalid.');
        }

        $normalized = [];
        foreach ($body as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('Tenant context form field is invalid.');
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /** @param array<mixed, mixed> $body */
    private function version(ServerRequestInterface $request, array $body): TenantContextVersion
    {
        $value = $this->string($body, 'tenant_context_version', 16);
        if (preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
            throw new \InvalidArgumentException('Tenant context version is invalid.');
        }
        $header = trim($request->getHeaderLine('X-QMDB-Tenant-Context-Version'));
        if ($header !== '' && $header !== $value) {
            throw new \InvalidArgumentException('Tenant context version sources do not match.');
        }

        return new TenantContextVersion((int)$value);
    }

    /** @param array<mixed, mixed> $body */
    private function string(array $body, string $name, int $maximum): string
    {
        $value = $body[$name] ?? null;
        if (
            !is_string($value) || strlen($value) > $maximum
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)
        ) {
            throw new \InvalidArgumentException('Tenant context form input is invalid.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string> $expected
     */
    private function requireExactFields(array $body, array $expected): void
    {
        $actual = array_keys($body);
        sort($actual);
        sort($expected);
        if ($actual !== $expected) {
            throw new \InvalidArgumentException('Tenant context form fields are invalid.');
        }
    }
}
