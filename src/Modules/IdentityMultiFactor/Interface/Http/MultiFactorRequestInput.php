<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Interface\Http;

use Psr\Http\Message\ServerRequestInterface;

final readonly class MultiFactorRequestInput
{
    /** @return array<string, string> */
    public function form(ServerRequestInterface $request): array
    {
        $type = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0]));
        if ($type !== 'application/x-www-form-urlencoded') {
            throw new \InvalidArgumentException('Form media type is invalid.');
        }
        $body = $request->getParsedBody();
        if ($body === null) {
            $raw = (string)$request->getBody();
            if (strlen($raw) > 65536) {
                throw new \InvalidArgumentException('Form body is too large.');
            }
            parse_str($raw, $body);
        }
        if (!is_array($body)) {
            throw new \InvalidArgumentException('Form body is invalid.');
        }

        return $this->strings($body);
    }

    /** @return array<string, mixed> */
    public function json(ServerRequestInterface $request, int $maximumBytes): array
    {
        $type = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0]));
        if ($type !== 'application/json') {
            throw new \InvalidArgumentException('JSON media type is invalid.');
        }
        $raw = (string)$request->getBody();
        if ($raw === '' || strlen($raw) > $maximumBytes) {
            throw new \InvalidArgumentException('JSON body is invalid.');
        }
        $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new \InvalidArgumentException('JSON body is invalid.');
        }
        $result = [];
        foreach ($decoded as $name => $value) {
            if (!is_string($name)) {
                throw new \InvalidArgumentException('JSON field name is invalid.');
            }
            $result[$name] = $value;
        }

        return $result;
    }

    /** @param array<string, mixed> $values */
    public function string(array $values, string $name, int $maximum = 4096): string
    {
        $value = $values[$name] ?? null;
        if (
            !is_string($value) || $value === '' || strlen($value) > $maximum
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1
        ) {
            throw new \InvalidArgumentException('Request field is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    public function integer(array $values, string $name): int
    {
        $value = $this->string($values, $name, 10);
        if (preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
            throw new \InvalidArgumentException('Request integer is invalid.');
        }

        return (int)$value;
    }

    /**
     * @param array<mixed, mixed> $values
     * @return array<string, string>
     */
    private function strings(array $values): array
    {
        $result = [];
        foreach ($values as $name => $value) {
            if (!is_string($name) || !is_string($value) || strlen($value) > 65536) {
                throw new \InvalidArgumentException('Form field is invalid.');
            }
            $result[$name] = $value;
        }

        return $result;
    }
}
