<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Request;

final readonly class RequestTargetValidator
{
    public function validate(string $requestTarget): RequestTargetValidationResult
    {
        if (
            $requestTarget === ''
            || $requestTarget[0] !== '/'
            || str_starts_with($requestTarget, '//')
            || str_contains($requestTarget, '#')
            || str_contains($requestTarget, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $requestTarget) === 1
        ) {
            return RequestTargetValidationResult::invalid();
        }

        $questionMark = strpos($requestTarget, '?');
        $encodedPath = $questionMark === false
            ? $requestTarget
            : substr($requestTarget, 0, $questionMark);

        if (
            $encodedPath === ''
            || preg_match('/%(?![0-9A-Fa-f]{2})/', $encodedPath) === 1
            || preg_match('/%(?:2[fF]|5[cC])/', $encodedPath) === 1
        ) {
            return RequestTargetValidationResult::invalid();
        }

        $decodedPath = rawurldecode($encodedPath);
        if (
            !mb_check_encoding($decodedPath, 'UTF-8')
            || str_contains($decodedPath, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $decodedPath) === 1
        ) {
            return RequestTargetValidationResult::invalid();
        }

        foreach (explode('/', substr($decodedPath, 1)) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return RequestTargetValidationResult::invalid();
            }
        }

        return RequestTargetValidationResult::valid($decodedPath);
    }
}
