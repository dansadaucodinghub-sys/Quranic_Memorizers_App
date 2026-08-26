<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Interface\Http;

use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Security\Peer\DirectPeerAddress;
use Qmdb\Modules\IdentityAccess\Security\Peer\DirectPeerAddressResolver;
use Qmdb\Shared\Http\Request\RequestContextAttributes;
use Qmdb\Shared\Localization\LocaleContext;

final readonly class IdentityRequestContext
{
    public function __construct(private DirectPeerAddressResolver $peers)
    {
    }

    public function locale(ServerRequestInterface $request): string
    {
        $context = $request->getAttribute(RequestContextAttributes::LOCALE);

        return $context instanceof LocaleContext ? $context->locale()->value() : 'en';
    }

    public function peer(ServerRequestInterface $request): DirectPeerAddress
    {
        return $this->peers->resolve($request);
    }
}
