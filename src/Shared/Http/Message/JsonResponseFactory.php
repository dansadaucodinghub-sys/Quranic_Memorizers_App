<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Message;

use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class JsonResponseFactory
{
    /** @var array<string, string> */
    private const REQUIRED_HEADERS = [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-store',
        'X-Content-Type-Options' => 'nosniff',
    ];

    private const JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, string|list<string>> $headers
     * @throws JsonException
     */
    public function create(array $data, int $status = 200, array $headers = []): ResponseInterface
    {
        return $this->createEncoded($data, $status, $headers, self::REQUIRED_HEADERS);
    }

    /**
     * @param array<string, scalar|null> $data
     * @param array<string, string|list<string>> $headers
     * @throws JsonException
     */
    public function createProblem(array $data, int $status, array $headers = []): ResponseInterface
    {
        $requiredHeaders = self::REQUIRED_HEADERS;
        $requiredHeaders['Content-Type'] = 'application/problem+json; charset=utf-8';

        return $this->createEncoded($data, $status, $headers, $requiredHeaders);
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, string|list<string>> $headers
     * @param array<string, string> $requiredHeaders
     * @throws JsonException
     */
    private function createEncoded(
        array $data,
        int $status,
        array $headers,
        array $requiredHeaders,
    ): ResponseInterface {
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException('HTTP status must be between 100 and 599.');
        }

        $protectedNames = array_map('strtolower', array_keys($requiredHeaders));
        foreach ($headers as $name => $values) {
            if (in_array(strtolower($name), $protectedNames, true)) {
                throw new InvalidArgumentException(sprintf('Required response header cannot be overridden: %s', $name));
            }

            $this->assertHeader($name, $values);
        }

        $encoded = json_encode($data, self::JSON_FLAGS);
        $response = $this->responseFactory
            ->createResponse($status)
            ->withBody($this->streamFactory->createStream($encoded));

        foreach ($headers as $name => $values) {
            $response = $response->withHeader($name, $values);
        }

        foreach ($requiredHeaders as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    /** @param string|list<string> $values */
    private function assertHeader(string $name, string|array $values): void
    {
        if ($name === '' || preg_match('/^[A-Za-z0-9!#$%&\'*+\-.^_`|~]+$/D', $name) !== 1) {
            throw new InvalidArgumentException('Response header name is invalid.');
        }

        $headerValues = is_array($values) ? $values : [$values];
        if ($headerValues === []) {
            throw new InvalidArgumentException('Response header values cannot be empty.');
        }

        foreach ($headerValues as $value) {
            if (preg_match('/[\r\n\0]/', $value) === 1) {
                throw new InvalidArgumentException('Response header value is invalid.');
            }
        }
    }
}
