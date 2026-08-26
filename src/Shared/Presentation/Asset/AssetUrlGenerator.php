<?php

declare(strict_types=1);

namespace Qmdb\Shared\Presentation\Asset;

use Qmdb\Shared\Presentation\Html\SafeUrl;

final readonly class AssetUrlGenerator
{
    public function css(string $file): SafeUrl
    {
        return $this->asset('css', $file);
    }

    public function js(string $file): SafeUrl
    {
        return $this->asset('js', $file);
    }

    private function asset(string $type, string $file): SafeUrl
    {
        if (preg_match('/^[a-z0-9][a-z0-9.-]*\.(?:css|js)$/D', $file) !== 1) {
            throw new \InvalidArgumentException('Asset filename is invalid.');
        }

        return SafeUrl::applicationRelative('/assets/' . $type . '/' . $file);
    }
}
