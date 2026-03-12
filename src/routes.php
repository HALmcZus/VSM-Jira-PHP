<?php

use App\Controller\FeatureController;
use Slim\App;
use App\Controller\VersionController;
use App\Controller\JiraProjectController;
use App\Controller\ProjectVersionsController;
use App\Controller\ProjectFeaturesController;

return function (App $app) {
    $app->get('/', [VersionController::class, 'index']);
    $app->get('/version', [VersionController::class, 'index']);
    $app->post('/version', [VersionController::class, 'process']);
    $app->get('/feature', [FeatureController::class, 'index']);
    $app->post('/feature', [FeatureController::class, 'process']);
    $app->get('/api/jira/projects/search', [JiraProjectController::class, 'search']);
    $app->get('/api/jira/projects/{projectKey}/versions', [ProjectVersionsController::class, 'versionsListByProject']);
    $app->get('/api/jira/projects/{projectKey}/features', [ProjectFeaturesController::class, 'featuresListByProject']);
};
