<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\Community\Application\CommunityInteractionService;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class CommunityBookmarksController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $authentication,
        private CommunityInteractionService $interactions,
        private IdentityAccessView $views,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = $this->authentication->context($request);
        if ($actor === null) {
            return $this->authentication->rejection($request);
        }
        return $this->views->render(
            $request,
            'pages.community-bookmarks',
            'fragments.community-bookmarks',
            new ViewData(['clips' => $this->interactions->bookmarks($actor)]),
            'community.bookmarks.title',
            200,
            null,
            true
        )->withHeader('Cache-Control', 'private, no-store');
    }
}
