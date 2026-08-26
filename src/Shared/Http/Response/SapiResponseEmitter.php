<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Response;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final readonly class SapiResponseEmitter implements ResponseEmitter
{
    private const DEFAULT_CHUNK_SIZE = 8192;

    /** @var Closure(): bool */
    private Closure $headersSent;

    /** @var Closure(int): void */
    private Closure $emitStatus;

    /** @var Closure(string, bool): void */
    private Closure $emitHeader;

    /** @var Closure(string): void */
    private Closure $emitOutput;

    /**
     * @param (callable(): bool)|null $headersSent
     * @param (callable(int): void)|null $emitStatus
     * @param (callable(string, bool): void)|null $emitHeader
     * @param (callable(string): void)|null $emitOutput
     */
    public function __construct(
        ?callable $headersSent = null,
        ?callable $emitStatus = null,
        ?callable $emitHeader = null,
        ?callable $emitOutput = null,
        private int $chunkSize = self::DEFAULT_CHUNK_SIZE,
    ) {
        if ($chunkSize < 1 || $chunkSize > 1_048_576) {
            throw new ResponseEmissionException('Response chunk size is outside the supported range.');
        }

        $this->headersSent = Closure::fromCallable(
            $headersSent ?? static fn (): bool => headers_sent(),
        );
        $this->emitStatus = Closure::fromCallable(
            $emitStatus ?? static function (int $status): void {
                header_remove('X-Powered-By');
                http_response_code($status);
            },
        );
        $this->emitHeader = Closure::fromCallable(
            $emitHeader ?? static function (string $line, bool $replace): void {
                header($line, $replace);
            },
        );
        $this->emitOutput = Closure::fromCallable(
            $emitOutput ?? static function (string $chunk): void {
                echo $chunk;
            },
        );
    }

    public function emit(ResponseInterface $response, string $requestMethod): void
    {
        if (($this->headersSent)()) {
            throw new ResponseEmissionException('Response headers have already been sent.');
        }

        $plan = $this->plan($response, $requestMethod);
        ($this->emitStatus)($plan->statusCode());
        foreach ($plan->headers() as $header) {
            ($this->emitHeader)($header['line'], $header['replace']);
        }

        if (!$plan->shouldEmitBody()) {
            return;
        }

        $body = $response->getBody();
        $originalPosition = null;

        try {
            if ($body->isSeekable()) {
                $originalPosition = $body->tell();
                $body->rewind();
            }

            while (!$body->eof()) {
                $chunk = $body->read($this->chunkSize);
                if ($chunk === '') {
                    break;
                }

                ($this->emitOutput)($chunk);
            }
        } catch (ResponseEmissionException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new ResponseEmissionException('Response body emission failed.', 0, $throwable);
        } finally {
            if ($originalPosition !== null) {
                try {
                    $body->seek($originalPosition);
                } catch (Throwable) {
                    // Emission already completed or failed; cursor restoration is best effort only.
                }
            }
        }
    }

    public function plan(ResponseInterface $response, string $requestMethod): ResponseEmissionPlan
    {
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            if (strcasecmp($name, 'X-Powered-By') === 0) {
                continue;
            }

            $this->assertHeaderName($name);
            foreach ($values as $index => $value) {
                $this->assertHeaderValue($value);
                $headers[] = [
                    'line' => sprintf('%s: %s', $name, $value),
                    'replace' => $index === 0,
                ];
            }
        }

        $status = $response->getStatusCode();
        $bodylessStatus = ($status >= 100 && $status < 200) || $status === 204 || $status === 304;

        return new ResponseEmissionPlan(
            statusCode: $status,
            headers: $headers,
            emitBody: strtoupper($requestMethod) !== 'HEAD' && !$bodylessStatus,
        );
    }

    private function assertHeaderName(string $name): void
    {
        if ($name === '' || preg_match('/^[A-Za-z0-9!#$%&\'*+\-.^_`|~]+$/D', $name) !== 1) {
            throw new ResponseEmissionException('Response header name is invalid.');
        }
    }

    private function assertHeaderValue(string $value): void
    {
        if (preg_match('/[\r\n\0]/', $value) === 1) {
            throw new ResponseEmissionException('Response header value is invalid.');
        }
    }
}
