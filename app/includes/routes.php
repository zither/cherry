<?php
use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Cherry\Controller\IndexController;
use Cherry\Controller\ApiController;
use Cherry\Controller\DevController;
use Cherry\Middleware\AuthenticationMiddleware;
use Cherry\Middleware\ApiCheckingMiddleware;
use Cherry\Middleware\InitiatingMiddleware;
use Cherry\Middleware\SiteLockingMiddleware;

return function (App $app) {
    $app->group('', function(RouteCollectorProxy $group) {

        /**
         * Web routes
         */
        $group->get('/install', [IndexController::class, 'install']);
        $group->post('/install', [IndexController::class, 'initDatabase']);
        $group->get('/login', [IndexController::class, 'login']);
        $group->post('/login', [IndexController::class, 'verifyPassword']);
        $group->get('/logout', [IndexController::class, 'logout']);

        $group->group('', function (RouteCollectorProxy $group) {
            $group->get('/', [IndexController::class, 'home'])->setName('home');
            $group->get('/@{name}', [IndexController::class, 'home'])->setName('home');
            $group->get('/@{name}/{public_id}', [IndexController::class, 'objectDetails']);
            $group->get('/tags/{tag}', [IndexController::class, 'tags']);
        })->add(SiteLockingMiddleware::class);

        $group->group('', function(RouteCollectorProxy $group) {
            $group->get('/timeline', [IndexController::class, 'timeline']);
            $group->get('/editor', [IndexController::class, 'editor']);
            $group->post('/editor', [IndexController::class, 'createActivity']);

            $group->get('/notifications', [IndexController::class, 'notifications']);
            $group->get('/notifications/{id}/answer', [IndexController::class, 'handleFollowRequest']);

            $group->post('/objects/{id}/delete', [IndexController::class, 'deleteObject']);
            $group->post('/objects/{id}/like', [IndexController::class, 'like']);
            $group->post('/objects/{id}/boost', [IndexController::class, 'boost']);
            $group->get('/objects/{id}/editor', [IndexController::class, 'objectEditor']);
            $group->get('/objects/{id}/thread', [IndexController::class, 'objectThread']);

            $group->post('/following', [IndexController::class, 'sendFollow']);
            $group->get('/following', [IndexController::class, 'following']);
            $group->post('/following/{id}/delete', [IndexController::class, 'deleteFollowing']);

            $group->get('/followers', [IndexController::class, 'followers']);
            $group->post('/followers/{id}/delete', [IndexController::class, 'deleteFollower']);

            $group->post('/profiles/{id}/fetch', [IndexController::class, 'fetchProfile']);
            $group->post('/profiles/{id}/update', [IndexController::class, 'updateProfile']);

            $group->get('/settings', [IndexController::class, 'settings']);
            $group->post('/settings', [IndexController::class, 'updatePreferences']);

            $group->post('/polls/{id}/vote', [IndexController::class, 'vote']);


            $group->get('/dev/objects', [DevController::class, 'objects']);
        })->add(AuthenticationMiddleware::class);

        /**
         * API Routes
         */
        $group->get('/.well-known/webfinger', [ApiController::class, 'webFinger']);
        $group->get('/.well-known/nodeinfo', [ApiController::class, 'nodeInfo']);
        $group->get('/nodeinfo/{version}.json', [ApiController::class, 'nodeInfoDetails']);

        $group->post('/inbox', [ApiController::class, 'inbox']);

        $group->get('/users/{name:\w+}', [ApiController::class, 'profile'])->setName('api_profile');
        $group->post('/users/{name:\w+}/inbox', [ApiController::class, 'inbox']);
        $group->get('/users/{name:\w+}/outbox', [ApiController::class, 'outbox']);
        $group->get('/users/{name:\w+}/followers', [ApiController::class, 'followers']);
        $group->get('/users/{name:\w+}/following', [ApiController::class, 'following']);

        $group->get('/activities/{public_id}', [ApiController::class, 'activityInfo']);
        $group->get('/objects/{public_id}', [ApiController::class, 'objectInfo']);
    })->add(InitiatingMiddleware::class);
};
