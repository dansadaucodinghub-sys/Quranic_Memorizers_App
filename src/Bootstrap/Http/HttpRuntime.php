<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Shared\Http\Kernel\HttpKernel;
use Qmdb\Shared\Http\Request\NativeServerRequestFactory;
use Qmdb\Shared\Http\Response\ResponseEmitter;
use Qmdb\Shared\Observability\Error\ErrorHandlingRuntime;

final readonly class HttpRuntime
{
    public function __construct(
        private NativeServerRequestFactory $requestFactory,
        private HttpKernel $kernel,
        private ResponseEmitter $responseEmitter,
        private ErrorHandlingRuntime $errorHandlingRuntime,
    ) {
    }

    public function run(): void
    {
        $this->errorHandlingRuntime->register();

        try {
            $request = $this->requestFactory->createFromGlobals();
            $response = $this->kernel->handle($request);
            $this->responseEmitter->emit($response, $request->getMethod());
        } finally {
            $this->errorHandlingRuntime->unregister();
        }
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->kernel->handle($request);
    }

    public function requestFactory(): NativeServerRequestFactory
    {
        return $this->requestFactory;
    }
}
