<?php
use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Cherry\Controller\IndexController;
use Cherry\Controller\ApiController;
use Cherry\Middleware\AuthMiddleware;
use Cherry\Middleware\AcceptHeaderMiddleware;
use Cherry\Middleware\InitialMiddleware;
use Cherry\Middleware\LockSiteMiddleware;

return function (App $app) {
    // Web Routes
    $app->get('/login', IndexController::class . ':login');
    $app->post('/login', IndexController::class . ':verifyPassword');
    $app->get('/logout', IndexController::class . ':logout');

    $app->group('', function(RouteCollectorProxy $group) {
        $group->get('/init', IndexController::class . ':showInitialForm');
        $group->post('/init', IndexController::class . ':init');
    })->add(InitialMiddleware::class);

    $app->group('', function (RouteCollectorProxy $group) {
        $group->get('/', IndexController::class . ':home');
        $group->get('/notes/{snowflake_id}', IndexController::class . ':note');
        $group->get('/tags/{tag}', IndexController::class . ':tags');
    })->add(LockSiteMiddleware::class);

    $app->group('', function(RouteCollectorProxy $group) {
        $group->get('/timeline', IndexController::class . ':timeline');
        $group->get('/editor', IndexController::class . ':editor');
        $group->post('/notes', IndexController::class . ':createPost');
        $group->post('/notes/{snowflake_id}/delete', IndexController::class . ':deletePost');
        $group->get('/notifications', IndexController::class . ':notifications');
        $group->get('/follow-requests/{notification_id}/answer', IndexController::class . ':handleFollowRequest');
        $group->post('/following', IndexController::class . ':sendFollow');
        $group->post('/objects/{object_id}/like', IndexController::class . ':liked');
        $group->post('/objects/{object_id}/boost', IndexController::class . ':boosted');
        $group->get('/objects/{object_id}/reply', IndexController::class . ':replyTo');
        $group->get('/web/threads/{object_id}', IndexController::class . ':showThread');
        $group->get('/web/following', IndexController::class . ':following');
        $group->get('/web/followers', IndexController::class . ':followers');
        $group->post('/following/{id}/delete', IndexController::class . ':deleteFollowing');
        $group->post('/followers/{id}/delete', IndexController::class . ':deleteFollowers');
        $group->post('/profiles/{profile_id}/fetch', IndexController::class . ':fetchProfile');
        $group->post('/profiles/{profile_id}/update', IndexController::class . ':updateProfile');
        $group->get('/settings/profile', IndexController::class . ':showProfileForm');
        $group->post('/web/preferences/update', IndexController::class . ':updatePreferences');
        $group->post('/web/polls/{poll_id}/vote', IndexController::class . ':vote');
    })->add(AuthMiddleware::class);

    // Server API Routes
    $app->post('/inbox', ApiController::class . ':inbox');
    $app->group('', function (RouteCollectorProxy $group) {
        $group->get('/.well-known/webfinger', ApiController::class . ':webFinger');
        $group->get('/.well-known/nodeinfo', ApiController::class . ':nodeInfo');
        $group->get('/nodeinfo/{version}.json', ApiController::class . ':nodeInfoDetails');
        $group->get('/profile', ApiController::class . ':profile')->setName('api_profile');
        $group->get('/outbox/{snowflake_id}/object', ApiController::class . ':objectInfo');
        $group->get('/outbox/{snowflake_id}', ApiController::class . ':activityInfo');
        $group->get('/outbox', ApiController::class . ':outbox');
        $group->get('/followers', ApiController::class . ':followers');
        $group->get('/following', ApiController::class . ':following');
    })->add(AcceptHeaderMiddleware::class);
};
