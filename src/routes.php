<?php

use Slim\App;
use App\Controller\VersionController;
use App\Controller\JiraProjectController;
use App\Controller\ProjectVersionsController;

return function (App $app) {
    $app->get('/', [VersionController::class, 'index']);
    $app->post('/version', [VersionController::class, 'process']);
    $app->get('/api/jira/projects/search', [JiraProjectController::class, 'search']);
    $app->get('/api/jira/projects/{projectKey}/versions', [ProjectVersionsController::class, 'versionsListByProject']);
};
