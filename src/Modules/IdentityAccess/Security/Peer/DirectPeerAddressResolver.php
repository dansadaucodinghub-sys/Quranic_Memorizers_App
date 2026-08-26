<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Security\Peer;

use Psr\Http\Message\ServerRequestInterface;

final readonly class DirectPeerAddressResolver
{
    public function resolve(ServerRequestInterface $request): DirectPeerAddress
    {
        $parameters = $request->getServerParams();
        $remote = $parameters['REMOTE_ADDR'] ?? null;

        return DirectPeerAddress::fromObserved(is_string($remote) ? $remote : null);
    }
}
