<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Interface\Http;

use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Security\Password\SensitivePlaintextPassword;
use Qmdb\Modules\IdentitySessions\Domain\LoginSubmissionId;

final readonly class IdentitySessionFormInput
{
    /** @return array{string, SensitivePlaintextPassword, LoginSubmissionId, string} */
    public function login(ServerRequestInterface $request): array
    {
        $this->assertForm($request);
        $body = $this->body($request);
        $email = $this->string($body, 'email', 320);
        $password = $this->string($body, 'password', 1024);
        $submission = LoginSubmissionId::fromString($this->string($body, 'login_submission_id', 36));
        $csrf = $this->string($body, 'csrf_token', 512);

        return [$email, new SensitivePlaintextPassword($password), $submission, $csrf];
    }

    /** @return array{int, string} */
    public function revocation(ServerRequestInterface $request): array
    {
        $this->assertForm($request);
        $body = $this->body($request);
        $version = $this->string($body, 'expected_version', 10);
        if (preg_match('/\A[1-9][0-9]*\z/', $version) !== 1) {
            throw new \InvalidArgumentException('Expected version is invalid.');
        }

        return [(int)$version, $this->string($body, 'csrf_token', 512)];
    }

    public function csrf(ServerRequestInterface $request): string
    {
        $this->assertForm($request);

        return $this->string($this->body($request), 'csrf_token', 512);
    }

    private function assertForm(ServerRequestInterface $request): void
    {
        $type = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0]));
        if ($type !== 'application/x-www-form-urlencoded') {
            throw new UnsupportedMediaTypeException();
        }
    }

    /** @return array<string, mixed> */
    private function body(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();
        if ($body === null) {
            $raw = (string)$request->getBody();
            if (strlen($raw) > 16384) {
                throw new \InvalidArgumentException('Form body is too large.');
            }
            parse_str($raw, $parsed);
            $body = $parsed;
        }
        if (!is_array($body)) {
            throw new \InvalidArgumentException('Form body is invalid.');
        }
        $normalized = [];
        foreach ($body as $name => $value) {
            if (!is_string($name)) {
                throw new \InvalidArgumentException('Form field name is invalid.');
            }
            $normalized[$name] = $value;
        }

        return $normalized;
    }

    /** @param array<string, mixed> $body */
    private function string(array $body, string $name, int $maximum): string
    {
        $value = $body[$name] ?? null;
        if (
            !is_string($value)
            || strlen($value) > $maximum
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)
        ) {
            throw new \InvalidArgumentException('Form input is invalid.');
        }

        return $value;
    }
}
