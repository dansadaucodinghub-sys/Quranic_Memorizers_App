<?php

declare(strict_types=1);

use Qmdb\Shared\Http\Controller\LivenessController;
use Qmdb\Shared\Http\Controller\ReadinessController;
use Qmdb\Shared\Http\Controller\SystemAboutApiController;
use Qmdb\Shared\Http\Controller\SystemAboutPageController;
use Qmdb\Shared\Http\Controller\SystemHomeController;
use Qmdb\Shared\Http\Controller\SystemStatusPageController;
use Qmdb\Shared\Http\Routing\HttpMethod;
use Qmdb\Shared\Http\Routing\Route;
use Qmdb\Shared\Http\Routing\RouteCollection;
use Qmdb\Shared\Http\Routing\RoutePattern;

return static function (
    SystemHomeController $homeController,
    SystemAboutPageController $aboutPageController,
    SystemStatusPageController $statusPageController,
    SystemAboutApiController $aboutApiController,
    LivenessController $livenessController,
    ReadinessController $readinessController,
): RouteCollection {
    return new RouteCollection(
        new Route('system.home', [HttpMethod::GET], new RoutePattern('/'), $homeController),
        new Route('system.about.page', [HttpMethod::GET], new RoutePattern('/system/about'), $aboutPageController),
        new Route('system.status.page', [HttpMethod::GET], new RoutePattern('/system/status'), $statusPageController),
        new Route(
            'system.health.live',
            [HttpMethod::GET],
            new RoutePattern('/health/live'),
            $livenessController,
        ),
        new Route(
            'system.health.ready',
            [HttpMethod::GET],
            new RoutePattern('/health/ready'),
            $readinessController,
        ),
        new Route(
            'api.v1.system.about',
            [HttpMethod::GET],
            new RoutePattern('/api/v1/system/about'),
            $aboutApiController,
        ),
    );
};
