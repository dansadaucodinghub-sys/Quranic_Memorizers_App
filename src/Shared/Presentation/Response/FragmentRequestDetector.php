<?php

declare(strict_types=1);

namespace Qmdb\Shared\Presentation\Response;

use Psr\Http\Message\ServerRequestInterface;

final readonly class FragmentRequestDetector
{
    public const MEDIA_TYPE = 'text/vnd.qmdb.fragment+html';

    public function isFragment(ServerRequestInterface $request): bool
    {
        $header = $request->getHeaderLine('Accept');
        if ($header === '' || strlen($header) > 2048) {
            return false;
        }
        foreach (array_slice(explode(',', $header), 0, 30) as $item) {
            $parts = array_map('trim', explode(';', strtolower($item)));
            if ($parts[0] !== self::MEDIA_TYPE) {
                continue;
            }
            foreach (array_slice($parts, 1) as $parameter) {
                if (preg_match('/^q=0(?:\.0{1,3})?$/D', $parameter) === 1) {
                    continue 2;
                }
            }
            return true;
        }

        return false;
    }
}
